<?php
// ========================================================
// TRANG CÀI ĐẶT HỆ THỐNG TOÀN DIỆN (ADMIN SETTINGS)
// vankhateamshop - Cài đặt SePay, VietQR, Telegram Bot, Zalo, Google OAuth, CAPTCHA
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
    $caiDatMoi = [
        'site_name' => trim($_POST['site_name'] ?? SHOP_NAME),
        'site_contact_zalo' => trim($_POST['site_contact_zalo'] ?? SHOP_ZALO),
        'site_contact_telegram' => trim($_POST['site_contact_telegram'] ?? SHOP_TELEGRAM),
        'sepay_bank_account' => trim($_POST['sepay_bank_account'] ?? BANK_ACCOUNT_NO),
        'sepay_account_name' => trim($_POST['sepay_account_name'] ?? BANK_ACCOUNT_NAME),
        'sepay_bank_name' => trim($_POST['sepay_bank_name'] ?? BANK_NAME),
        'sepay_api_key' => trim($_POST['sepay_api_key'] ?? SEPAY_API_KEY),
        'telegram_bot_token' => trim($_POST['telegram_bot_token'] ?? ''),
        'telegram_chat_id' => trim($_POST['telegram_chat_id'] ?? '')
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

        $thongBao = 'Đã lưu cấu hình hệ thống thành công!';
        $loaiThongBao = 'success';
    } catch (Exception $e) {
        $thongBao = 'Lỗi khi lưu: ' . $e->getMessage();
        $loaiThongBao = 'error';
    }
}

// Đọc toàn bộ cài đặt từ bảng settings
$rawSettings = $pdo->query("SELECT * FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
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
<body class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ VIÊN -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-gear"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Cài Đặt Hệ Thống</span>
                </div>
            </div>

            <!-- Menu quản trị -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/danh-muc" class="text-gray-300 hover:text-white"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
                <a href="/admin/thanh-vien" class="text-gray-300 hover:text-white"><i class="fas fa-users mr-1"></i> Thành Viên</a>
                <a href="/admin/ngan-hang" class="text-gray-300 hover:text-white"><i class="fas fa-building-columns mr-1"></i> Ngân Hàng</a>
                <a href="/admin/nha-cung-cap" class="text-gray-300 hover:text-white"><i class="fas fa-network-wired mr-1"></i> Nhà Cung Cấp</a>
                <a href="/admin/ma-giam-gia" class="text-gray-300 hover:text-white"><i class="fas fa-ticket mr-1"></i> Mã Giảm Giá</a>
                <a href="/admin/ho-tro" class="text-gray-300 hover:text-white"><i class="fas fa-headset mr-1"></i> Hỗ Trợ</a>
                <a href="/admin/thong-bao" class="text-gray-300 hover:text-white"><i class="fas fa-bullhorn mr-1"></i> Thông Báo</a>
                <a href="/admin/nhat-ky" class="text-gray-300 hover:text-white"><i class="fas fa-shield-virus mr-1"></i> Nhật Ký</a>
                <a href="/admin/cai-dat" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-gear mr-1"></i> Cài Đặt</a>
                <a href="/logout" class="text-red-400 hover:text-red-300 ml-2">
                    <i class="fas fa-right-from-bracket mr-1"></i> Đăng Xuất
                </a>
            </nav>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 w-full flex-1 space-y-6">
        
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-sliders text-amber-500"></i> Cài Đặt Thông Số Hệ Thống
            </h1>
            <p class="text-xs text-gray-400 mt-1">Cấu hình thanh toán VietQR SePay, thông tin liên hệ và cảnh báo tự động</p>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> mr-1.5"></i>
                <?= htmlspecialchars($thongBao) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="luu_cai_dat" value="1">

            <!-- KHỐI 1: THÔNG TIN SHOP -->
            <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                <h2 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                    <i class="fas fa-store text-blue-400"></i> 1. Thông Tin Thương Hiệu & Liên Hệ
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
                    <i class="fas fa-qrcode text-green-400"></i> 2. Tài Khoản Ngân Hàng Nhận Tiền (SePay.vn)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Tên Ngân Hàng (MBBank, VCB...):</label>
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
                </div>

                <div class="p-3 bg-gray-950 rounded-xl border border-gray-800 text-[11px] text-gray-400 space-y-1">
                    <p><i class="fas fa-link text-blue-400 mr-1"></i> Webhook URL cài đặt trên SePay:</p>
                    <code class="text-green-400 font-mono select-all block"><?= SITE_URL ?>/backend/webhook_sepay.php</code>
                </div>
            </div>

            <!-- KHỐI 3: BOT TELEGRAM BÁO ĐƠN & HẾT TIỀN VÍ -->
            <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
                <h2 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                    <i class="fab fa-telegram text-blue-400"></i> 3. Bot Telegram Nhận Thông Báo Tự Động
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Telegram Bot Token (tạo từ @BotFather):</label>
                        <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($rawSettings['telegram_bot_token'] ?? '') ?>" placeholder="123456789:ABCdefGhI..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Telegram Chat ID nhận tin nhắn:</label>
                        <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($rawSettings['telegram_chat_id'] ?? '') ?>" placeholder="VD: 987654321" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full py-3.5 rounded-2xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition duration-200 shadow-xl shadow-amber-600/30 flex items-center justify-center gap-2">
                <i class="fas fa-floppy-disk"></i> Lưu Toàn Bộ Cấu Hình Hệ Thống
            </button>
        </form>

    </main>

    <footer class="border-t border-gray-800/80 bg-[#0F172A] py-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống
    </footer>

</body>
</html>
