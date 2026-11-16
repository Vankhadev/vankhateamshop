<?php
// ========================================================
// TRANG CHÍNH SÁCH BẢO HÀNH & ĐIỀU KHOẢN DỊCH VỤ
// vankhateamshop - Minh bạch điều khoản, bảo hành 1 đổi 1, hoàn tiền ví
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();
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
    <title>Chính Sách Bảo Hành & Điều Khoản - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-4xl mx-auto px-4 py-10 w-full flex-1 space-y-6">
        <div class="text-center mb-8">
            <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Cam Kết Uy Tín & Dài Lâu</span>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1">Chính Sách Bảo Hành & Điều Khoản Sử Dụng</h1>
            <p class="text-xs text-gray-400 mt-2">Áp dụng cho toàn bộ các gói API Cursor, Claude, Codex, DeepSeek bán tại <?= SHOP_NAME ?></p>
        </div>

        <!-- THẺ 1: BẢO HÀNH 1 ĐỔI 1 -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 sm:p-8 shadow-xl">
            <div class="flex items-center gap-3 text-amber-400 mb-3">
                <i class="fas fa-shield-halved text-xl"></i>
                <h2 class="text-base font-bold text-white">1. Chính Sách Bảo Hành 1 Đổi 1 Suốt Thời Hạn Gói</h2>
            </div>
            <ul class="space-y-2 text-xs text-gray-300 leading-relaxed list-disc pl-5">
                <li>Mọi gói API mua tại shop (gói 1 ngày, 3 ngày, 7 ngày hoặc 30 ngày) đều được bảo hành đầy đủ trong thời gian hiệu lực.</li>
                <li>Trường hợp Key gặp lỗi (mất kết nối, bị khóa ngoài ý muốn), khách hàng chỉ cần nhắn mã đơn hàng qua Zalo hoặc Telegram để được cấp Key mới ngay lập tức.</li>
                <li>Thời gian xử lý bảo hành: Từ 5 phút đến tối đa 30 phút trong giờ làm việc (8h00 - 23h30 hàng ngày).</li>
            </ul>
        </div>

        <!-- THẺ 2: HOÀN TIỀN TỰ ĐỘNG -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 sm:p-8 shadow-xl">
            <div class="flex items-center gap-3 text-green-400 mb-3">
                <i class="fas fa-hand-holding-dollar text-xl"></i>
                <h2 class="text-base font-bold text-white">2. Chính Sách Hoàn Tiền Tự Động</h2>
            </div>
            <ul class="space-y-2 text-xs text-gray-300 leading-relaxed list-disc pl-5">
                <li>Nếu hệ thống gặp sự cố hết hàng tại tất cả các nguồn cung cấp, hệ thống tự động hoàn 100% số tiền đã thanh toán vào số dư ví của khách trên website.</li>
                <li>Khách hàng có thể dùng số dư ví để mua các gói khác hoặc yêu cầu rút tiền về tài khoản ngân hàng chính chủ bất cứ lúc nào.</li>
            </ul>
        </div>

        <!-- THẺ 3: QUY ĐỊNH SỬ DỤNG HỢP LỆ -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 sm:p-8 shadow-xl">
            <div class="flex items-center gap-3 text-blue-400 mb-3">
                <i class="fas fa-file-contract text-xl"></i>
                <h2 class="text-base font-bold text-white">3. Quy Định Sử Dụng & Giới Hạn</h2>
            </div>
            <ul class="space-y-2 text-xs text-gray-300 leading-relaxed list-disc pl-5">
                <li>API được cấp cho mục đích lập trình, hỗ trợ viết code, phát triển phần mềm cá nhân hoặc nhóm nhỏ.</li>
                <li>Nghiêm cấm chia sẻ Key công khai lên GitHub, mạng xã hội hoặc sử dụng tool bot DDoS làm nghẽn băng thông hệ thống.</li>
                <li>Đối với các gói reset theo ngày: Lượt tin nhắn sẽ tự động làm mới vào lúc 7h00 sáng mỗi ngày.</li>
            </ul>
        </div>

        <!-- THẺ 4: HỖ TRỢ TRỰC TIẾP -->
        <div class="bg-gradient-to-r from-blue-950/60 to-indigo-950/60 border border-blue-800/80 rounded-3xl p-6 text-center shadow-xl">
            <h3 class="text-sm font-bold text-white mb-2">Cần Trợ Giúp Về Đơn Hàng Hoặc Bảo Hành?</h3>
            <p class="text-xs text-gray-400 mb-4">Đội ngũ kỹ thuật viên của <?= SHOP_NAME ?> luôn túc trực hỗ trợ bạn:</p>
            <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-bold">
                <a href="https://zalo.me/<?= SHOP_ZALO ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white transition flex items-center gap-2">
                    <i class="fas fa-headset"></i> Zalo: <?= SHOP_ZALO ?>
                </a>
                <a href="<?= SHOP_TELEGRAM ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-blue-400 border border-gray-700 transition flex items-center gap-2">
                    <i class="fab fa-telegram"></i> Kênh Telegram Hỗ Trợ
                </a>
            </div>
        </div>
    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
