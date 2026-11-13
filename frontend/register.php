<?php
// ========================================================
// TRANG ĐĂNG KÝ TÀI KHOẢN THÀNH VIÊN (REGISTER)
// vankhateamshop - Đăng ký bằng Email & Mật khẩu, bảo mật CAPTCHA & Rate Limit
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Nếu đã đăng nhập thì chuyển hướng
if (!empty($_SESSION['user_id'])) {
    header('Location: client/dashboard.php');
    exit;
}

$thongBaoLoi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Kiểm tra chống Spam đăng ký (Tối đa 5 lần/phút/IP)
    if (!kiemTraRateLimit('register_attempt', 5, 60)) {
        $thongBaoLoi = 'Bạn đang thao tác quá nhanh. Vui lòng đợi 1 phút trước khi đăng ký tiếp!';
    } else {
        // 2. Xác thực CAPTCHA
        $captchaToken = $_POST['captcha_token'] ?? $_POST['cf-turnstile-response'] ?? $_POST['g-recaptcha-response'] ?? '';
        $ketQuaCaptcha = xacThucCaptcha($captchaToken);

        if (!$ketQuaCaptcha['success']) {
            $thongBaoLoi = $ketQuaCaptcha['message'];
        } else {
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (empty($name) || empty($email) || empty($password)) {
                $thongBaoLoi = 'Vui lòng điền đầy đủ tất cả các trường thông tin!';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $thongBaoLoi = 'Địa chỉ Email không hợp lệ!';
            } elseif (strlen($password) < 6) {
                $thongBaoLoi = 'Mật khẩu phải có độ dài từ 6 ký tự trở lên!';
            } elseif ($password !== $passwordConfirm) {
                $thongBaoLoi = 'Mật khẩu xác nhận không khớp!';
            } else {
                // Kiểm tra xem email đã tồn tại chưa
                $stmtCheck = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ? LIMIT 1");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    $thongBaoLoi = 'Địa chỉ Email này đã được đăng ký tài khoản trước đó!';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $apiToken = 'vk_' . bin2hex(random_bytes(24));

                    // Tự động gán quyền Admin cho tài khoản đầu tiên tạo trên hệ thống
                    $soLuongUserHienTai = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
                    $roleKhoiTao = ($soLuongUserHienTai === 0) ? 'admin' : 'user';

                    $insertStmt = $pdo->prepare("
                        INSERT INTO `users` (`name`, `email`, `password`, `balance`, `role`, `api_token`, `is_active`)
                        VALUES (?, ?, ?, 0.00, ?, ?, 1)
                    ");
                    $insertStmt->execute([$name, $email, $hash, $roleKhoiTao, $apiToken]);
                    $newId = $pdo->lastInsertId();

                    // Đăng nhập luôn cho người dùng
                    $_SESSION['user_id'] = $newId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_avatar'] = null;
                    $_SESSION['role'] = $roleKhoiTao;

                    if ($roleKhoiTao === 'admin') {
                        header('Location: /admin/dashboard');
                    } else {
                        header('Location: /user/dashboard');
                    }
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản Mới - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <?php if (CAPTCHA_PROVIDER === 'turnstile'): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php else: ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>

    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full bg-[#111827] border border-gray-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
        
        <div class="text-center mb-6">
            <a href="/" class="inline-flex items-center space-x-2 mb-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white font-black text-lg">VK</div>
                <span class="text-xl font-black text-white">VanKha<span class="text-blue-500">Shop</span></span>
            </a>
            <h1 class="text-xl font-bold text-white">Tạo Tài Khoản Mới</h1>
            <p class="text-xs text-gray-400 mt-1">Đăng ký để quản lý ví tiền và các gói API của bạn</p>
        </div>

        <?php if (!empty($thongBaoLoi)): ?>
            <div class="p-3.5 rounded-xl mb-5 bg-red-950/70 border border-red-800 text-red-300 text-xs font-semibold flex items-center gap-2">
                <i class="fas fa-triangle-exclamation text-red-400"></i>
                <span><?= htmlspecialchars($thongBaoLoi) ?></span>
            </div>
        <?php endif; ?>

        <!-- Đăng nhập bằng Google nhanh -->
        <a href="/backend/auth_google.php" class="w-full flex items-center justify-center gap-3 py-2.5 px-4 rounded-xl bg-white hover:bg-gray-100 text-gray-800 font-bold text-xs transition duration-200 shadow mb-5">
            <svg class="w-4 h-4" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Đăng ký nhanh bằng Google</span>
        </a>

        <div class="relative flex items-center justify-center my-4">
            <div class="border-t border-gray-800 w-full"></div>
            <span class="bg-[#111827] px-3 text-[10px] text-gray-500 uppercase font-semibold absolute">hoặc điền thông tin</span>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">Họ và tên của bạn:</label>
                <input type="text" name="name" required placeholder="Nguyễn Văn A" class="w-full px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white text-xs focus:border-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">Địa chỉ Email:</label>
                <input type="email" name="email" required placeholder="name@example.com" class="w-full px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white text-xs focus:border-blue-500 focus:outline-none">
            </div>

            <div x-data="{ hienPass: false }">
                <label class="block text-xs font-semibold text-gray-300 mb-1">Mật khẩu (Tối thiểu 6 ký tự):</label>
                <div class="relative">
                    <input :type="hienPass ? 'text' : 'password'" name="password" required placeholder="••••••••" class="w-full pl-3 pr-10 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white text-xs focus:border-blue-500 focus:outline-none transition">
                    <button type="button" @click="hienPass = !hienPass" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-white transition focus:outline-none" title="Hiện/ẩn mật khẩu">
                        <i class="fas" :class="hienPass ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
            </div>

            <div x-data="{ hienPassConfirm: false }">
                <label class="block text-xs font-semibold text-gray-300 mb-1">Nhập lại mật khẩu:</label>
                <div class="relative">
                    <input :type="hienPassConfirm ? 'text' : 'password'" name="password_confirm" required placeholder="••••••••" class="w-full pl-3 pr-10 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white text-xs focus:border-blue-500 focus:outline-none transition">
                    <button type="button" @click="hienPassConfirm = !hienPassConfirm" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-white transition focus:outline-none" title="Hiện/ẩn mật khẩu">
                        <i class="fas" :class="hienPassConfirm ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
            </div>

            <div class="pt-1 flex justify-center">
                <?php if (CAPTCHA_PROVIDER === 'turnstile'): ?>
                    <div class="cf-turnstile" data-sitekey="<?= CAPTCHA_SITE_KEY ?>" data-theme="dark"></div>
                <?php else: ?>
                    <div class="g-recaptcha" data-sitekey="<?= CAPTCHA_SITE_KEY ?>" data-theme="dark"></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                <i class="fas fa-user-plus"></i> Đăng Ký Tài Khoản
            </button>
        </form>

        <div class="mt-5 text-center text-xs text-gray-400">
            Đã có tài khoản? <a href="/login" class="text-blue-400 hover:text-blue-300 font-bold">Đăng nhập tại đây</a>
        </div>
    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
