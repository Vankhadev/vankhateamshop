<?php
// ========================================================
// TRANG CÀI ĐẶT HỆ THỐNG TOÀN DIỆN (ADMIN SETTINGS)
// vankhateamshop - Cài đặt SePay, VietQR, Telegram Bot, Zalo, Google OAuth, Cloudflare Turnstile, reCAPTCHA
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

// Kiểm tra quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

$thongBao = '';
$loaiThongBao = '';

// Xử lý lưu cấu hình
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['luu_cai_dat'])) {
    batBuocCsrf();
    $caiDatMoi = [
        // 1. Thương hiệu & Liên hệ
        'site_name'             => trim($_POST['site_name'] ?? SHOP_NAME),
        'site_contact_zalo'     => trim($_POST['site_contact_zalo'] ?? SHOP_ZALO),
        'site_contact_telegram' => trim($_POST['site_contact_telegram'] ?? SHOP_TELEGRAM),
        
        // 2. SePay VietQR
        'sepay_bank_account'    => trim($_POST['sepay_bank_account'] ?? BANK_ACCOUNT_NO),
        'sepay_account_name'    => trim($_POST['sepay_account_name'] ?? BANK_ACCOUNT_NAME),
        'sepay_bank_name'       => trim($_POST['sepay_bank_name'] ?? BANK_NAME),
        'sepay_api_key'         => trim($_POST['sepay_api_key'] ?? SEPAY_API_KEY),
        'sepay_webhook_secret'  => trim($_POST['sepay_webhook_secret'] ?? ''),

        // 3. Google OAuth 2.0
        'google_login_enabled'  => isset($_POST['google_login_enabled']) ? '1' : '0',
        'google_client_id'      => trim($_POST['google_client_id'] ?? ''),
        'google_client_secret'  => trim($_POST['google_client_secret'] ?? ''),

        // 4. Bảo mật Anti-Bot & CAPTCHA (Cloudflare Turnstile / Google reCAPTCHA)
        'captcha_enabled'       => isset($_POST['captcha_enabled']) ? '1' : '0',
        'captcha_provider'      => in_array($_POST['captcha_provider'] ?? '', ['turnstile', 'google']) ? $_POST['captcha_provider'] : 'turnstile',
        'captcha_site_key'      => trim($_POST['captcha_site_key'] ?? ''),
        'captcha_secret_key'    => trim($_POST['captcha_secret_key'] ?? ''),

        // 5. Telegram Bot dùng chung, mỗi nhóm nhận một loại thông báo riêng
        'telegram_bot_token'             => trim($_POST['telegram_bot_token'] ?? ''),
        'telegram_chat_id'               => trim($_POST['telegram_chat_id'] ?? ''),
        'telegram_chat_order_id'         => trim($_POST['telegram_chat_order_id'] ?? ''),
        'telegram_chat_product_new_id'   => trim($_POST['telegram_chat_product_new_id'] ?? ''),
        'telegram_chat_product_out_id'   => trim($_POST['telegram_chat_product_out_id'] ?? ''),
        'telegram_chat_product_price_id' => trim($_POST['telegram_chat_product_price_id'] ?? ''),
        'telegram_chat_catalog_id'       => trim($_POST['telegram_chat_catalog_id'] ?? ''),
        'telegram_chat_finance_id'       => trim($_POST['telegram_chat_finance_id'] ?? ''),
        'telegram_chat_support_id'       => trim($_POST['telegram_chat_support_id'] ?? '')
    ];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `settings` (`key`, `value`) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");

        foreach ($caiDatMoi as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        $thongBao = 'Đã lưu toàn bộ cấu hình hệ thống thành công!';
        $loaiThongBao = 'success';
    } catch (Exception $e) {
        $thongBao = 'Lỗi khi lưu cấu hình: ' . $e->getMessage();
        $loaiThongBao = 'error';
    }
}

// Đọc toàn bộ cài đặt từ bảng settings (an toàn, tương thích mọi chế độ PDO)
$rawSettings = [];
try {
    $caiDatRows = $pdo->query("SELECT `key`, `value` FROM `settings`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($caiDatRows as $r) {
        if (!empty($r['key'])) {
            $rawSettings[$r['key']] = $r['value'] ?? '';
        }
    }
} catch (Exception $e) {
    // Dự phòng nếu có lỗi truy vấn
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài Đặt Hệ Thống - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-4 sm:px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-2 sm:gap-3 overflow-hidden">
                    <button type="button" onclick="moAdminSidebar()" class="lg:hidden p-2 -ml-1 rounded-xl bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none shrink-0" aria-label="Mở Menu">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <h1 class="text-sm sm:text-base font-bold text-white flex items-center gap-2 truncate">
                        <i class="fas fa-gear text-amber-500"></i>
                        <span class="truncate">Cài Đặt Hệ Thống & Bảo Mật</span>
                    </h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a href="/" target="_blank" class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-semibold text-gray-200 transition flex items-center gap-1.5 border border-gray-700">
                        <i class="fas fa-globe text-blue-400"></i>
                        <span class="hidden sm:inline">Xem Website Shop</span>
                    </a>
                    <a href="/logout" class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-red-600/10 hover:bg-red-600/20 text-red-400 border border-red-500/20 text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fas fa-right-from-bracket"></i>
                        <span class="hidden sm:inline">Thoát</span>
                    </a>
                </div>
            </header>

            <!-- NỘI DUNG QUẢN TRỊ CHÍNH -->
            <main class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto space-y-6">
        
                <div>
                    <h1 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="fas fa-sliders text-amber-500"></i> Cài Đặt Thông Số Hệ Thống & Bảo Mật
                    </h1>
                    <p class="text-xs text-gray-400 mt-1">Cấu hình thanh toán VietQR SePay, Đăng nhập Google, Chống spam Cloudflare Turnstile & reCAPTCHA, Telegram Bot</p>
                </div>

                <?php if (!empty($thongBao)): ?>
                    <div class="p-4 rounded-xl text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                        <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> mr-1.5"></i>
                        <?= htmlspecialchars($thongBao) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="luu_cai_dat" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                    <!-- KHỐI 1: THÔNG TIN SHOP -->
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                            <i class="fas fa-store text-blue-400"></i> 1. Thông Tin Thương Hiệu & Kênh Hỗ Trợ
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Tên website / Shop:</label>
                                <input type="text" name="site_name" value="<?= htmlspecialchars($rawSettings['site_name'] ?? SHOP_NAME) ?>" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Số Zalo CSKH:</label>
                                <input type="text" name="site_contact_zalo" value="<?= htmlspecialchars($rawSettings['site_contact_zalo'] ?? SHOP_ZALO) ?>" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-gray-300 font-semibold mb-1">Link Kênh / Nhóm Telegram Hỗ Trợ:</label>
                                <input type="url" name="site_contact_telegram" value="<?= htmlspecialchars($rawSettings['site_contact_telegram'] ?? SHOP_TELEGRAM) ?>" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                            </div>
                        </div>
                    </div>

                    <!-- KHỐI 2: CỔNG THANH TOÁN VIETQR (SEPAY) -->
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                            <i class="fas fa-qrcode text-green-400"></i> 2. Cổng Thanh Toán Tự Động VietQR (SePay.vn)
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Tên Ngân Hàng (MBBank, ACB, VCB...):</label>
                                <input type="text" name="sepay_bank_name" value="<?= htmlspecialchars($rawSettings['sepay_bank_name'] ?? BANK_NAME) ?>" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Số Tài Khoản Nhận Tiền:</label>
                                <input type="text" name="sepay_bank_account" value="<?= htmlspecialchars($rawSettings['sepay_bank_account'] ?? BANK_ACCOUNT_NO) ?>" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-amber-400 font-mono font-bold focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Tên Chủ Tài Khoản:</label>
                                <input type="text" name="sepay_account_name" value="<?= htmlspecialchars($rawSettings['sepay_account_name'] ?? BANK_ACCOUNT_NAME) ?>" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-bold focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">API Key SePay.vn:</label>
                                <input type="text" name="sepay_api_key" value="<?= htmlspecialchars($rawSettings['sepay_api_key'] ?? SEPAY_API_KEY) ?>" placeholder="API Key từ SePay.vn" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-gray-300 font-semibold mb-1">Webhook Secret Key (HMAC-SHA256):</label>
                                <input type="text" name="sepay_webhook_secret" value="<?= htmlspecialchars($rawSettings['sepay_webhook_secret'] ?? '') ?>" placeholder="VD: whsec_abc123... (Chọn phương thức HMAC-SHA256 trên SePay rồi dán Secret Key vào đây)" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-green-400 font-mono focus:outline-none focus:border-amber-500">
                            </div>
                        </div>

                        <div class="p-3.5 bg-gray-950 rounded-2xl border border-gray-800 text-[11px] text-gray-400 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-blue-400 font-semibold">
                                <i class="fas fa-circle-info"></i> Hướng dẫn kết nối Webhook SePay:
                            </div>
                            <p>1. Trên SePay.vn vào mục <strong>Webhook &gt; Thêm Webhook</strong>.</p>
                            <p>2. Dán địa chỉ sau vào ô <strong>URL Webhook</strong>:</p>
                            <div class="flex items-center gap-2">
                                <code class="text-green-400 font-mono bg-gray-900 px-3 py-1 rounded-lg border border-gray-800 select-all flex-1"><?= SITE_URL ?>/backend/webhook_sepay.php</code>
                            </div>
                            <p>3. Tại bước <strong>Bảo mật</strong>: Chọn <strong>HMAC-SHA256</strong> (hoặc API Key) &gt; Bấm icon xoay tròn để tạo key rồi copy dán vào ô <strong>Webhook Secret Key</strong> ở trên.</p>
                        </div>
                    </div>

                    <!-- KHỐI 3: ĐĂNG NHẬP GOOGLE OAUTH 2.0 -->
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                                <i class="fab fa-google text-red-400"></i> 3. Cấu Hình Đăng Nhập Bằng Google (OAuth 2.0)
                            </h2>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="google_login_enabled" value="1" <?= (!empty($rawSettings['google_login_enabled']) && $rawSettings['google_login_enabled'] == '1') ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-2 text-xs font-semibold text-gray-300">Bật đăng nhập Google</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Google Client ID:</label>
                                <input type="text" name="google_client_id" value="<?= htmlspecialchars($rawSettings['google_client_id'] ?? layGiaTriEnv('GOOGLE_CLIENT_ID', '')) ?>" placeholder="VD: 123456789-abc.apps.googleusercontent.com" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Google Client Secret:</label>
                                <input type="password" name="google_client_secret" value="<?= htmlspecialchars($rawSettings['google_client_secret'] ?? layGiaTriEnv('GOOGLE_CLIENT_SECRET', '')) ?>" placeholder="VD: GOCSPX-abc123xyz..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                        </div>

                        <div class="p-3.5 bg-gray-950 rounded-2xl border border-gray-800 text-[11px] text-gray-400 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-amber-400 font-semibold">
                                <i class="fab fa-google"></i> Authorized Redirect URI (Cần điền vào Google Cloud Console):
                            </div>
                            <div class="flex items-center gap-2">
                                <code id="uriGoogle" class="text-green-400 font-mono bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-800 select-all flex-1"><?= SITE_URL ?>/backend/auth_google.php</code>
                                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('uriGoogle').innerText); alert('Đã sao chép Redirect URI!');" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-xs font-bold transition">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <p class="text-[10px] text-gray-500">Tạo tại: <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-400 hover:underline">Google Cloud Console &gt; Credentials &gt; OAuth 2.0 Client IDs</a></p>
                        </div>
                    </div>

                    <!-- KHỐI 4: BẢO MẬT CAPTCHA & CHỐNG SPAM BOT (CLOUDFLARE TURNSTILE & RECAPTCHA) -->
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                                <i class="fas fa-shield-halved text-amber-400"></i> 4. Bảo Mật Chống Bot & Spam (Cloudflare Turnstile / reCAPTCHA)
                            </h2>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="captcha_enabled" value="1" <?= (!empty($rawSettings['captcha_enabled']) && $rawSettings['captcha_enabled'] == '1') ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                                <span class="ml-2 text-xs font-semibold text-gray-300">Bật CAPTCHA khi Đăng nhập / Đăng ký</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Nhà cung cấp CAPTCHA:</label>
                                <select name="captcha_provider" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                                    <option value="turnstile" <?= (($rawSettings['captcha_provider'] ?? 'turnstile') === 'turnstile') ? 'selected' : '' ?>>Cloudflare Turnstile (Khuyên dùng, miễn phí 100%)</option>
                                    <option value="google" <?= (($rawSettings['captcha_provider'] ?? '') === 'google') ? 'selected' : '' ?>>Google reCAPTCHA v2</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Site Key (Công khai):</label>
                                <input type="text" name="captcha_site_key" value="<?= htmlspecialchars($rawSettings['captcha_site_key'] ?? layGiaTriEnv('CAPTCHA_SITE_KEY', '')) ?>" placeholder="Site Key..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Secret Key (Bảo mật máy chủ):</label>
                                <input type="password" name="captcha_secret_key" value="<?= htmlspecialchars($rawSettings['captcha_secret_key'] ?? layGiaTriEnv('CAPTCHA_SECRET_KEY', '')) ?>" placeholder="Secret Key..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                        </div>

                        <div class="p-3.5 bg-gray-950 rounded-2xl border border-gray-800 text-[11px] text-gray-400 space-y-1">
                            <p><i class="fab fa-cloudflare text-amber-500 mr-1"></i> <strong>Cloudflare Turnstile:</strong> Đăng ký miễn phí tại <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" class="text-blue-400 hover:underline">Cloudflare Dashboard &gt; Turnstile</a>. Người dùng chỉ cần tích chuột 1 giây, chống bot cực mạnh.</p>
                            <p><i class="fab fa-google text-red-400 mr-1"></i> <strong>Google reCAPTCHA v2:</strong> Lấy key tại <a href="https://www.google.com/recaptcha/admin" target="_blank" class="text-blue-400 hover:underline">Google reCAPTCHA Admin</a> (Chọn loại reCAPTCHA v2 "I'm not a robot").</p>
                        </div>
                    </div>

                    <!-- KHỐI 5: BOT TELEGRAM BÁO ĐƠN & HẾT TIỀN VÍ -->
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                            <i class="fab fa-telegram text-blue-400"></i> 5. Bot Telegram Nhận Thông Báo Đơn Hàng & Cảnh Báo Số Dư
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Telegram Bot Token (tạo từ @BotFather):</label>
                                <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($rawSettings['telegram_bot_token'] ?? '') ?>" placeholder="123456789:ABCdefGhI..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Telegram Chat ID nhận tin nhắn (lấy từ @userinfobot):</label>
                                <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($rawSettings['telegram_chat_id'] ?? '') ?>" placeholder="VD: 987654321" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition duration-200 shadow-xl shadow-amber-600/30 flex items-center justify-center gap-2">
                        <i class="fas fa-floppy-disk"></i> Lưu Toàn Bộ Cấu Hình Hệ Thống
                    </button>
                </form>

                <!-- KHỐI 6: THIẾT LẬP CRON JOB TỰ ĐỘNG HÓA (CPANEL & MÁY CHỦ VPS) -->
                <div class="bg-gray-900 border border-emerald-900/60 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-800 pb-3">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <i class="fas fa-clock-rotate-left text-emerald-400"></i> 6. Thiết Lập Cron Job Tự Động Hóa (cPanel & Máy Chủ VPS)
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold self-start sm:self-auto">
                            <i class="fas fa-check-circle mr-1"></i> Tác vụ chạy định kỳ mỗi 1 phút
                        </span>
                    </div>

                    <p class="text-xs text-gray-300 leading-relaxed">
                        Cron Job là các tác vụ chạy ngầm tự động để đảm bảo hệ thống luôn đồng bộ kho hàng, cập nhật giá nhà cung cấp, quét đơn hàng SePay ACB và giao Key tức thì cho khách mà không cần can thiệp thủ công.
                    </p>

                    <!-- LỆNH CRON 1: ĐỒNG BỘ KHO, GIÁ, TỰ ĐỘNG THÊM SẢN PHẨM MỚI -->
                    <div class="bg-gray-950 p-4 rounded-2xl border border-emerald-900/40 space-y-2.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="text-xs font-bold text-emerald-400 flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono text-[10px]">CRON 1</span>
                                <span>Đồng bộ kho hàng, tự động cập nhật giá & tự thêm sản phẩm mới:</span>
                            </div>
                            <button type="button" onclick="saoChepCron('curl -s &quot;https://tiemai.io.vn/backend/cron_sync.php?secret=VK_CRON_SECRET_2026&quot; >/dev/null 2>&1')" class="self-start sm:self-center px-3 py-1 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 border border-emerald-500/30">
                                <i class="fas fa-copy"></i> Sao chép lệnh
                            </button>
                        </div>
                        <div class="relative">
                            <code class="block bg-black/90 p-3 rounded-xl text-emerald-300 font-mono text-xs border border-gray-800 break-all select-all">curl -s "https://tiemai.io.vn/backend/cron_sync.php?secret=VK_CRON_SECRET_2026" >/dev/null 2>&1</code>
                        </div>
                        <p class="text-[11px] text-gray-400">
                            <i class="fas fa-info-circle text-emerald-400 mr-1"></i> Tự động kiểm tra API nhà cung cấp: lấy số lượng tồn kho mới nhất, nếu đối tác tăng giá thì tự tăng giá theo tỷ lệ lợi nhuận, nếu đối tác có sản phẩm mới thì tự thêm vào danh mục shop.
                        </p>
                    </div>

                    <!-- LỆNH CRON 2: TỔNG HỢP HỆ THỐNG SEPAY & HỦY ĐƠN TREO -->
                    <div class="bg-gray-950 p-4 rounded-2xl border border-blue-900/40 space-y-2.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="text-xs font-bold text-blue-400 flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-blue-500/20 text-blue-300 font-mono text-[10px]">CRON 2</span>
                                <span>Cron tổng hợp toàn diện (SePay ACB, đối soát đơn và đồng bộ kho):</span>
                            </div>
                            <button type="button" onclick="saoChepCron('curl -s &quot;https://tiemai.io.vn/backend/cron.php?secret=VK_CRON_SECRET_2026&quot; >/dev/null 2>&1')" class="self-start sm:self-center px-3 py-1 rounded-xl bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 border border-blue-500/30">
                                <i class="fas fa-copy"></i> Sao chép lệnh
                            </button>
                        </div>
                        <div class="relative">
                            <code class="block bg-black/90 p-3 rounded-xl text-blue-300 font-mono text-xs border border-gray-800 break-all select-all">curl -s "https://tiemai.io.vn/backend/cron.php?secret=VK_CRON_SECRET_2026" >/dev/null 2>&1</code>
                        </div>
                        <p class="text-[11px] text-gray-400">
                            <i class="fas fa-info-circle text-blue-400 mr-1"></i> Đối soát giao dịch SePay ACB tự động kích hoạt nạp tiền & mua hàng, tự động hủy các đơn treo quá 15 phút tránh giữ chỗ.
                        </p>
                    </div>

                    <!-- HƯỚNG DẪN CÀI ĐẶT CPANEL -->
                    <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 sm:p-5 space-y-3 text-xs text-amber-200">
                        <div class="font-bold flex items-center gap-2 text-amber-300 text-sm">
                            <i class="fas fa-gear"></i> Hướng Dẫn Thiết Lập Cron Job Trong cPanel:
                        </div>
                        <ol class="list-decimal list-inside space-y-2 text-gray-300 leading-relaxed text-xs">
                            <li>Đăng nhập vào tài khoản <strong>cPanel</strong> của bạn.</li>
                            <li>Trong mục <strong>Advanced (Nâng cao)</strong>, nhấp vào <strong>Cron Jobs</strong>.</li>
                            <li>Trong phần <strong>Add New Cron Job</strong>:
                                <ul class="list-disc list-inside ml-5 my-1 text-gray-400 space-y-1">
                                    <li>Chọn <strong>Common Settings</strong>: <em>Every minute (* * * * *)</em> để cron chạy mỗi phút.</li>
                                    <li>Trong trường <strong>Command</strong>, nhập lệnh <code class="text-emerald-300 font-mono">curl</code> cho từng cron job (sao chép từ bên trên).</li>
                                </ul>
                            </li>
                            <li>Nhấn <strong>Add New Cron Job</strong> để lưu lại.</li>
                        </ol>
                    </div>
                </div>

            </main>

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>

    <script>
    function saoChepCron(lenh) {
        navigator.clipboard.writeText(lenh);
        alert('Đã sao chép lệnh Cron vào bộ nhớ tạm:\n\n' + lenh);
    }
    </script>
</body>
</html>
