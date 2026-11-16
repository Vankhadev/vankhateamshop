<?php
// ========================================================
// TRANG ĐĂNG NHẬP THÀNH VIÊN (LOGIN PAGE)
// vankhateamshop - Hỗ trợ đăng nhập Google OAuth 2.0 & reCAPTCHA / Cloudflare Turnstile
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Lấy thông số hệ thống
$captchaEnabled     = (layCaiDatHeThong('captcha_enabled', '0') === '1');
$captchaProvider    = layCaiDatHeThong('captcha_provider', (defined('CAPTCHA_PROVIDER') ? CAPTCHA_PROVIDER : 'turnstile'));
$captchaSiteKey     = layCaiDatHeThong('captcha_site_key', (defined('CAPTCHA_SITE_KEY') ? CAPTCHA_SITE_KEY : ''));
$googleLoginEnabled = (layCaiDatHeThong('google_login_enabled', '1') === '1');
$googleClientId     = layCaiDatHeThong('google_client_id', (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''));

// Nếu đã đăng nhập rồi thì tự động điều hướng
if (!empty($_SESSION['user_id'])) {
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: /admin/dashboard');
    } else {
        header('Location: /user/dashboard');
    }
    exit;
}

// Hủy bước 2FA nếu người dùng bấm quay lại đăng nhập tài khoản khác
if (isset($_GET['cancel_2fa'])) {
    unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_remember']);
    header('Location: /login');
    exit;
}

$is2FaStep = !empty($_SESSION['2fa_pending_user_id']) || (isset($_GET['step']) && $_GET['step'] === '2fa');
$thongBaoLoi = '';

// Xử lý thông báo lỗi từ callback Google
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'khong_lay_duoc_token') {
        $thongBaoLoi = 'Không thể xác thực với máy chủ Google. Vui lòng thử lại!';
    } elseif ($_GET['error'] === 'khong_lay_duoc_thong_tin') {
        $thongBaoLoi = 'Google không trả về thông tin email. Vui lòng cấp quyền truy cập email!';
    } else {
        $thongBaoLoi = 'Đã xảy ra lỗi khi đăng nhập bằng Google!';
    }
}

// XỬ LÝ 1: XÁC THỰC MÃ 2FA AUTHENTICATOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    batBuocCsrf();
    if (empty($_SESSION['2fa_pending_user_id'])) {
        header('Location: /login');
        exit;
    }

    require_once __DIR__ . '/../backend/totp.php';
    $code = trim($_POST['totp_code'] ?? '');
    $pendingUid = (int)$_SESSION['2fa_pending_user_id'];

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$pendingUid]);
    $user = $stmt->fetch();

    if (!$user || empty($user['two_factor_secret'])) {
        unset($_SESSION['2fa_pending_user_id']);
        header('Location: /login');
        exit;
    }

    $isValid = false;
    // Kiểm tra mã OTP 6 số
    if (TotpAuth::xacThuc($user['two_factor_secret'], $code)) {
        $isValid = true;
    } else {
        // Kiểm tra nếu khách nhập Mã dự phòng (Recovery code)
        $recoveryJson = $user['two_factor_recovery_codes'] ?? '';
        if (TotpAuth::kiemTraVaDungMaDuPhong($code, $recoveryJson)) {
            $isValid = true;
            $pdo->prepare("UPDATE `users` SET `two_factor_recovery_codes` = ? WHERE `id` = ?")->execute([$recoveryJson, $user['id']]);
        }
    }

    if ($isValid) {
        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_remember']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: /admin/dashboard');
        } else {
            header('Location: /user/dashboard');
        }
        exit;
    } else {
        $thongBaoLoi = 'Mã xác thực 6 số hoặc mã dự phòng không đúng! Vui lòng thử lại.';
        $is2FaStep = true;
    }
}

// XỬ LÝ 2: ĐĂNG NHẬP BẰNG EMAIL & MẬT KHẨU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_2fa'])) {
    batBuocCsrf();
    // 1. Kiểm tra chống Brute-Force mật khẩu (Giới hạn 10 lần/phút)
    if (!kiemTraRateLimit('login_attempt', 10, 60)) {
        $thongBaoLoi = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng đợi 1 phút!';
    } else {
        // 2. Xác thực CAPTCHA nếu được bật
        $captchaToken = $_POST['captcha_token'] ?? $_POST['cf-turnstile-response'] ?? $_POST['g-recaptcha-response'] ?? '';
        $ketQuaCaptcha = xacThucCaptcha($captchaToken);

        if (!$ketQuaCaptcha['success']) {
            $thongBaoLoi = $ketQuaCaptcha['message'];
        } else {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $matKhau = $_POST['password'] ?? '';

            if (empty($email) || empty($matKhau)) {
                $thongBaoLoi = 'Vui lòng điền đầy đủ Email và Mật khẩu!';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || empty($user['password']) || !password_verify($matKhau, $user['password'])) {
                    $thongBaoLoi = 'Email hoặc mật khẩu không chính xác!';
                } elseif (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    $thongBaoLoi = 'Tài khoản của bạn đã bị tạm khóa. Vui lòng liên hệ Admin để mở khóa!';
                } else {
                    // KIỂM TRA NẾU TÀI KHOẢN ĐÃ BẬT XÁC THỰC 2FA QUA AUTHENTICATOR
                    if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
                        $_SESSION['2fa_pending_user_id'] = $user['id'];
                        $_SESSION['2fa_pending_remember'] = !empty($_POST['remember']);
                        $is2FaStep = true;
                    } else {
                        // Đăng nhập thành công: đổi session ID để chống session fixation.
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_avatar'] = $user['avatar'];
                        $_SESSION['role'] = $user['role'];

                        if ($user['role'] === 'admin') {
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
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <!-- Favicon Logo Website Tiem AI -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Tài Khoản - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Nạp thư viện CAPTCHA tương ứng khi được kích hoạt -->
    <?php if ($captchaEnabled && !empty($captchaSiteKey)): ?>
        <?php if ($captchaProvider === 'turnstile'): ?>
            <!-- Cloudflare Turnstile API -->
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php else: ?>
            <!-- Google reCAPTCHA v2 API -->
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <?php endif; ?>
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
        
        <!-- Hiệu ứng nền nhẹ -->
        <div class="absolute -top-16 -right-16 w-36 h-36 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <?php if ($is2FaStep): ?>
            <!-- GIAO DIỆN XÁC THỰC 2 LỚP (AUTHENTICATOR CHALLENGE) -->
            <div class="text-center mb-6">
                <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 flex items-center justify-center text-2xl mx-auto mb-3 shadow-lg shadow-emerald-500/20">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1 class="text-lg font-bold text-white">Xác Thực 2 Lớp (Authenticator)</h1>
                <p class="text-xs text-gray-400 mt-1.5 leading-relaxed">
                    Tài khoản đã được bảo vệ bằng ứng dụng <b>Authenticator</b>.<br>
                    Vui lòng mở ứng dụng trên điện thoại để lấy mã 6 số:
                </p>
            </div>

            <?php if (!empty($thongBaoLoi)): ?>
                <div class="p-3.5 rounded-xl mb-6 bg-red-950/70 border border-red-800 text-red-300 text-xs font-semibold flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation text-red-400"></i>
                    <span><?= htmlspecialchars($thongBaoLoi) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="verify_2fa" value="1">

                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1.5 text-center">
                        Nhập mã OTP 6 số (hoặc Mã dự phòng 8 ký tự):
                    </label>
                    <input type="text" name="totp_code" maxlength="9" autofocus required autocomplete="one-time-code" placeholder="000000" class="w-full py-3.5 rounded-xl bg-gray-950 border border-emerald-500/70 text-emerald-400 text-center font-mono text-2xl tracking-widest font-black focus:border-emerald-400 focus:outline-none transition shadow-inner select-all">
                </div>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs transition duration-200 shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                    <i class="fas fa-unlock"></i> Xác Nhận Đăng Nhập
                </button>

                <div class="pt-2 text-center text-xs">
                    <a href="/login?cancel_2fa=1" class="text-gray-400 hover:text-gray-200 transition flex items-center justify-center gap-1">
                        <i class="fas fa-arrow-left text-[10px]"></i> Hủy & Đăng nhập bằng tài khoản khác
                    </a>
                </div>
            </form>

        <?php else: ?>
            <!-- GIAO DIỆN ĐĂNG NHẬP THƯỜNG -->
            <!-- Logo & Tiêu đề -->
            <div class="text-center mb-8">
                <a href="/" class="inline-flex items-center space-x-2 mb-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white font-black text-lg shadow-lg shadow-blue-600/30">VK</div>
                    <span class="text-xl font-black text-white tracking-wide"><?= SHOP_NAME ?></span>
                </a>
                <h1 class="text-lg font-bold text-white">Đăng Nhập Tài Khoản</h1>
                <p class="text-xs text-gray-400 mt-1">Đăng nhập để xem lịch sử mua hàng, lấy API Key và nạp ví tự động</p>
            </div>

            <?php if (!empty($thongBaoLoi)): ?>
                <div class="p-3.5 rounded-xl mb-6 bg-red-950/70 border border-red-800 text-red-300 text-xs font-semibold flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation text-red-400"></i>
                    <span><?= htmlspecialchars($thongBaoLoi) ?></span>
                </div>
            <?php endif; ?>

            <!-- NÚT 1: ĐĂNG NHẬP BẰNG GOOGLE (TIỆN LỢI & BẢO MẬT) -->
            <?php if ($googleLoginEnabled && !empty($googleClientId)): ?>
                <div class="mb-6">
                    <a href="/backend/auth_google.php" class="w-full flex items-center justify-center gap-3 py-3 px-4 rounded-xl bg-white hover:bg-gray-100 text-gray-800 font-bold text-xs transition duration-200 shadow-md">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>Đăng nhập nhanh bằng Google</span>
                    </a>
                </div>

                <div class="relative flex items-center justify-center my-6">
                    <div class="border-t border-gray-800 w-full"></div>
                    <span class="bg-[#111827] px-3 text-[11px] text-gray-500 uppercase font-semibold absolute">hoặc tài khoản mật khẩu</span>
                </div>
            <?php endif; ?>

            <!-- FORM ĐĂNG NHẬP TRUYỀN THỐNG -->
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1.5">Địa chỉ Email:</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class="fas fa-envelope text-xs"></i>
                        </span>
                        <input type="email" name="email" autocomplete="email" required placeholder="name@example.com" class="w-full pl-9 pr-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white text-xs focus:border-blue-500 focus:outline-none transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-gray-300">Mật khẩu:</label>
                        <a href="/quen-mat-khau" class="text-[11px] font-semibold text-blue-400 hover:text-blue-300 transition">Quên mật khẩu?</a>
                    </div>
                    <div class="relative" x-data="{ hienPass: false }">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class="fas fa-lock text-xs"></i>
                        </span>
                        <input :type="hienPass ? 'text' : 'password'" name="password" autocomplete="current-password" required placeholder="••••••••" class="w-full pl-9 pr-10 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white text-xs focus:border-blue-500 focus:outline-none transition">
                        <button type="button" @click="hienPass = !hienPass" aria-label="Hiện hoặc ẩn mật khẩu" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-white transition focus:outline-none" title="Hiện/ẩn mật khẩu">
                            <i class="fas" :class="hienPass ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <!-- XÁC THỰC BẢO MẬT CAPTCHA KHI ĐƯỢC BẬT -->
                <?php if ($captchaEnabled && !empty($captchaSiteKey)): ?>
                    <div class="pt-2 flex justify-center">
                        <?php if ($captchaProvider === 'turnstile'): ?>
                            <!-- Widget Cloudflare Turnstile -->
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($captchaSiteKey) ?>" data-theme="dark"></div>
                        <?php else: ?>
                            <!-- Widget Google reCAPTCHA v2 -->
                            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($captchaSiteKey) ?>" data-theme="dark"></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                    <i class="fas fa-right-from-bracket"></i> Đăng Nhập
                </button>

                <!-- NÚT CHUYỂN SANG ĐĂNG KÝ -->
                <div class="pt-2 text-center text-xs text-gray-400">
                    Chưa có tài khoản? <a href="/register" class="text-blue-400 hover:text-blue-300 font-bold transition">Đăng ký ngay tại đây</a>
                </div>
            </form>
        <?php endif; ?>

        </div>
    </main>

    <!-- FOOTER DÙNG CHUNG -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
