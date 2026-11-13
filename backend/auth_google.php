<?php
// ========================================================
// HỆ THỐNG ĐĂNG NHẬP BẰNG GOOGLE (GOOGLE OAUTH 2.0)
// vankhateamshop - Phân hệ xác thực tài khoản an toàn
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Nếu người dùng bấm "Đăng nhập bằng Google" (chưa có tham số code)
if (!isset($_GET['code'])) {
    // Kiểm tra cấu hình Google Client ID
    if (empty(GOOGLE_CLIENT_ID) || GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID_HERE') {
        die('Hệ thống chưa được cấu hình Google Client ID trong backend/config.php. Vui lòng liên hệ quản trị viên.');
    }

    // Tạo mã chống giả mạo CSRF (State Token)
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    // Chuyển hướng người dùng sang trang đăng nhập của Google
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'state' => $state,
        'prompt' => 'select_account'
    ];

    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}

// 2. Khi Google chuyển hướng khách quay trở lại (có tham số ?code=...)
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// Kiểm tra mã chống giả mạo State
if (empty($state) || !isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    unset($_SESSION['oauth_state']);
    die('Xác thực bảo mật phiên đăng nhập Google thất bại (Lỗi CSRF State)!');
}
unset($_SESSION['oauth_state']);

// Gửi yêu cầu đổi mã code lấy Token truy cập (Access Token) từ máy chủ Google
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);

if (empty($tokenData['access_token'])) {
    header('Location: /login?error=khong_lay_duoc_token');
    exit;
}

// Dùng Access Token lấy thông tin cá nhân của người dùng từ Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token'],
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$userInfoResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userInfoResponse, true);

if (empty($googleUser['id']) || empty($googleUser['email'])) {
    header('Location: /login?error=khong_lay_duoc_thong_tin');
    exit;
}

$googleId = (string)$googleUser['id'];
$email = strtolower(trim($googleUser['email']));
$hoTen = trim($googleUser['name'] ?? 'Khách Hàng');
$avatar = $googleUser['picture'] ?? '';

try {
    // 3. Kiểm tra xem người dùng đã có trong CSDL chưa
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `google_id` = ? OR `email` = ? LIMIT 1");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Đã có tài khoản: Cập nhật Google ID và ảnh đại diện mới nhất
        $updateStmt = $pdo->prepare("UPDATE `users` SET `google_id` = ?, `avatar` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
        $updateStmt->execute([$googleId, $avatar, $user['id']]);
        
        $userId = $user['id'];
        $userRole = $user['role'];
    } else {
        // Khách mới lần đầu đăng nhập: Tự động đăng ký tài khoản (User đầu tiên là Admin)
        $soLuongUser = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        $userRole = ($soLuongUser === 0) ? 'admin' : 'user';

        $apiToken = bin2hex(random_bytes(32));
        $insertStmt = $pdo->prepare("
            INSERT INTO `users` (`name`, `email`, `password`, `google_id`, `avatar`, `balance`, `role`, `api_token`, `is_active`)
            VALUES (?, ?, NULL, ?, ?, 0.00, ?, ?, 1)
        ");
        $insertStmt->execute([$hoTen, $email, $googleId, $avatar, $userRole, $apiToken]);
        $userId = $pdo->lastInsertId();
    }

    // 4. Lưu thông tin đăng nhập vào Session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $hoTen;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_avatar'] = $avatar;
    $_SESSION['role'] = $userRole;

    // Chuyển hướng người dùng tương ứng theo quyền
    if ($userRole === 'admin') {
        header('Location: /admin/dashboard');
    } else {
        header('Location: /user/dashboard');
    }
    exit;

} catch (Exception $e) {
    die('Lỗi xử lý cơ sở dữ liệu đăng nhập Google: ' . $e->getMessage());
}
