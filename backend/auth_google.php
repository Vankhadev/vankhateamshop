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

// Lấy thông số cấu hình Google OAuth linh hoạt (Ưu tiên bảng settings -> .env)
$googleClientId     = layCaiDatHeThong('google_client_id', GOOGLE_CLIENT_ID);
$googleClientSecret = layCaiDatHeThong('google_client_secret', GOOGLE_CLIENT_SECRET);
$googleRedirectUri  = layCaiDatHeThong('google_redirect_uri', GOOGLE_REDIRECT_URI);
$googleLoginEnabled = layCaiDatHeThong('google_login_enabled', '1');

// 1. Nếu người dùng bấm "Đăng nhập bằng Google" (chưa có tham số code)
if (!isset($_GET['code'])) {
    // Kiểm tra cấu hình Google Client ID
    if (empty($googleClientId) || $googleClientId === 'YOUR_GOOGLE_CLIENT_ID_HERE' || $googleLoginEnabled === '0') {
        die('<div style="font-family:sans-serif;text-align:center;padding:50px;">
            <h2>Chức năng Đăng nhập bằng Google chưa được bật hoặc chưa điền Client ID</h2>
            <p>Quản trị viên vui lòng vào <b>/admin/cai-dat</b> để điền Google Client ID & Secret Key.</p>
            <a href="/login" style="color:#2563eb;text-decoration:none;font-weight:bold;">&larr; Quay lại trang đăng nhập</a>
        </div>');
    }

    // Tạo mã chống giả mạo CSRF (State Token)
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    // Chuyển hướng người dùng sang trang đăng nhập của Google
    $params = [
        'client_id'     => $googleClientId,
        'redirect_uri'  => $googleRedirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'state'         => $state,
        'prompt'        => 'select_account'
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
    'code'          => $code,
    'client_id'     => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri'  => $googleRedirectUri,
    'grant_type'    => 'authorization_code'
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

// 3. Tra cứu hoặc Tạo mới người dùng trong Database
try {
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `google_id` = ? OR `email` = ? LIMIT 1");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Nếu tài khoản đã bị khóa bởi Admin
        if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
            die('<div style="font-family:sans-serif;text-align:center;padding:50px;">
                <h2 style="color:red;">Tài khoản của bạn đã bị tạm khóa</h2>
                <p>Vui lòng liên hệ ban quản trị để được hỗ trợ mở khóa tài khoản.</p>
                <a href="/" style="color:#2563eb;text-decoration:none;">&larr; Về trang chủ</a>
            </div>');
        }

        // Đã có tài khoản: Cập nhật Google ID và ảnh đại diện mới nhất
        $updateStmt = $pdo->prepare("UPDATE `users` SET `google_id` = ?, `avatar` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
        $updateStmt->execute([$googleId, $avatar, $user['id']]);

        // Lưu thông tin vào phiên làm việc (Session)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_avatar'] = !empty($avatar) ? $avatar : $user['avatar'];
        $_SESSION['role'] = $user['role'];

    } else {
        // Tài khoản đầu tiên đăng ký sẽ tự động gán quyền ADMIN
        $demUser = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        $userRole = ($demUser === 0) ? 'admin' : 'user';

        // Tạo API Token cá nhân ngẫu nhiên
        $apiToken = bin2hex(random_bytes(32));

        $insertStmt = $pdo->prepare("
            INSERT INTO `users` (`name`, `email`, `password`, `google_id`, `avatar`, `balance`, `role`, `api_token`, `is_active`)
            VALUES (?, ?, NULL, ?, ?, 0.00, ?, ?, 1)
        ");
        $insertStmt->execute([$hoTen, $email, $googleId, $avatar, $userRole, $apiToken]);

        $newUserId = $pdo->lastInsertId();

        // Lưu thông tin vào phiên làm việc (Session)
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_name'] = $hoTen;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_avatar'] = $avatar;
        $_SESSION['role'] = $userRole;
    }

    // 4. Chuyển hướng tới trang phù hợp theo quyền hạn
    if ($_SESSION['role'] === 'admin') {
        header('Location: /admin/dashboard');
    } else {
        header('Location: /user/dashboard');
    }
    exit;

} catch (Exception $e) {
    die('Lỗi xử lý cơ sở dữ liệu đăng nhập Google: ' . $e->getMessage());
}
