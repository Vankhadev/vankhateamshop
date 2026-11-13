<?php
// ========================================================
// TRANG BIẾN ĐỘNG SỐ DƯ VÍ (CLIENT TRANSACTIONS)
// vankhateamshop - Theo dõi lịch sử nạp tiền, trừ tiền, hoàn tiền
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ../../backend/logout.php');
    exit;
}

// Lấy danh sách giao dịch
$stmtTx = $pdo->prepare("
    SELECT * FROM `transactions` 
    WHERE `user_id` = ? 
    ORDER BY `id` DESC 
    LIMIT 50
");
$stmtTx->execute([$user['id']]);
$transactions = $stmtTx->fetchAll();

// Thống kê tổng tiền nạp và tổng tiền tiêu
$stmtTongNap = $pdo->prepare("SELECT SUM(amount) FROM `transactions` WHERE `user_id` = ? AND `type` = 'DEPOSIT'");
$stmtTongNap->execute([$user['id']]);
$tongTienNap = (float)$stmtTongNap->fetchColumn();

$stmtTongTieu = $pdo->prepare("SELECT SUM(amount) FROM `transactions` WHERE `user_id` = ? AND `type` = 'PURCHASE'");
$stmtTongTieu->execute([$user['id']]);
$tongTienTieu = (float)$stmtTongTieu->fetchColumn();

$siteNameDisplay = layCaiDatHeThong('site_name', SHOP_NAME);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biến Động Số Dư Ví - <?= htmlspecialchars($siteNameDisplay) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- THANH MENU TAB QUẢN TRỊ TÀI KHOẢN KHÁCH HÀNG -->
    <div class="bg-[#111827] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-6 text-xs font-semibold overflow-x-auto py-3">
                <a href="/user/dashboard" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-chart-pie mr-1"></i> Tổng Quan
                </a>
                <a href="/user/don-hang" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-box-open mr-1"></i> Đơn Hàng Của Tôi
                </a>
                <a href="/user/nap-tien" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-wallet mr-1 text-green-400"></i> Nạp Tiền Ví
                </a>
                <a href="/user/api-docs" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-code mr-1"></i> Kết Nối API & Cursor
                </a>
                <a href="/user/bien-dong-so-du" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
                    <i class="fas fa-clock-rotate-left mr-1"></i> Biến Động Số Dư
                </a>
                <a href="/user/ho-tro" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-headset mr-1"></i> Hỗ Trợ
                </a>
                <a href="/user/tai-khoan" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-user-gear mr-1"></i> Cài Đặt Tài Khoản
                </a>
            </nav>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-clock-rotate-left text-green-400"></i> Lịch Sử Biến Động Số Dư Ví
                </h1>
                <p class="text-xs text-gray-400 mt-1">Ghi nhận mọi giao dịch nạp tiền VietQR, mua gói API và hoàn tiền tự động</p>
            </div>
            <a href="/user/nap-tien" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Nạp Tiền Vào Ví
            </a>
        </div>

        <!-- 3 THẺ TỔNG KẾT VÍ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs text-gray-400 block font-medium">Số Dư Khả Dụng Hiện Tại</span>
                <span class="text-2xl font-black text-green-400 mt-1 block">
                    <?= number_format($user['balance'], 0, ',', '.') ?> VNĐ
                </span>
                <span class="text-[11px] text-gray-500">Sẵn sàng để mua ngay các gói API</span>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs text-gray-400 block font-medium">Tổng Tiền Đã Nạp</span>
                <span class="text-2xl font-black text-blue-400 mt-1 block">
                    <?= number_format($tongTienNap, 0, ',', '.') ?> VNĐ
                </span>
                <span class="text-[11px] text-gray-500">Qua quét mã VietQR tự động</span>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs text-gray-400 block font-medium">Tổng Tiền Đã Mua API</span>
                <span class="text-2xl font-black text-amber-400 mt-1 block">
                    <?= number_format($tongTienTieu, 0, ',', '.') ?> VNĐ
                </span>
                <span class="text-[11px] text-gray-500">Cho các gói Cursor, Claude, DeepSeek</span>
            </div>
        </div>

        <!-- BẢNG DANH SÁCH BIẾN ĐỘNG SỐ DƯ -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã GD / Thời Gian</th>
                            <th class="py-3.5 px-4 text-center">Loại Giao Dịch</th>
                            <th class="py-3.5 px-4">Số Tiền</th>
                            <th class="py-3.5 px-4">Số Dư Trước</th>
                            <th class="py-3.5 px-4">Số Dư Sau</th>
                            <th class="py-3.5 px-4">Nội Dung / Lý Do</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-500">
                                    Chưa có giao dịch biến động số dư nào phát sinh.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($transactions as $tx): 
                            $isPlus = in_array($tx['type'], ['DEPOSIT', 'REFUND', 'BONUS']);
                        ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono text-gray-300 font-bold">#<?= $tx['id'] ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?= date('H:i - d/m/Y', strtotime($tx['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($tx['type'] === 'DEPOSIT'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">
                                            <i class="fas fa-arrow-down mr-0.5"></i> Nạp tiền
                                        </span>
                                    <?php elseif ($tx['type'] === 'PURCHASE'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-950 text-red-400 border border-red-800">
                                            <i class="fas fa-cart-shopping mr-0.5"></i> Mua hàng
                                        </span>
                                    <?php elseif ($tx['type'] === 'REFUND'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-950 text-purple-400 border border-purple-800">
                                            <i class="fas fa-rotate-left mr-0.5"></i> Hoàn tiền
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-950 text-blue-400 border border-blue-800">
                                            <?= htmlspecialchars($tx['type']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 font-bold <?= $isPlus ? 'text-green-400' : 'text-red-400' ?>">
                                    <?= $isPlus ? '+' : '-' ?><?= number_format($tx['amount'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4 text-gray-400">
                                    <?= number_format($tx['balance_before'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4 font-semibold text-white">
                                    <?= number_format($tx['balance_after'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4 text-gray-300">
                                    <?= htmlspecialchars($tx['note'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
