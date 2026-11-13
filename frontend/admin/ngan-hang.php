<?php
// ========================================================
// TRANG QUẢN LÝ GIAO DỊCH NGÂN HÀNG & SEPAY (ADMIN)
// vankhateamshop - Theo dõi tiền vào VietQR, biến động tài khoản
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

// Kiểm tra quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 1. Thống kê nạp tiền ngân hàng
$tongNap = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM `transactions` WHERE `type` = 'DEPOSIT'")->fetchColumn();
$soLuotNap = (int)$pdo->query("SELECT COUNT(*) FROM `transactions` WHERE `type` = 'DEPOSIT'")->fetchColumn();

// 2. Danh sách các giao dịch nạp tiền gần nhất
$stmtGiaoDich = $pdo->query("
    SELECT t.*, u.name AS user_name, u.email AS user_email
    FROM `transactions` t
    JOIN `users` u ON t.user_id = u.id
    WHERE t.type = 'DEPOSIT'
    ORDER BY t.id DESC
    LIMIT 50
");
$giaoDichs = $stmtGiaoDich->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giao Dịch Ngân Hàng - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungNganHang()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER QUẢN TRỊ -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Giám Sát VietQR & Webhook SePay</span>
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
                <a href="/admin/ngan-hang" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-building-columns mr-1"></i> Ngân Hàng</a>
                <a href="/admin/nha-cung-cap" class="text-gray-300 hover:text-white"><i class="fas fa-network-wired mr-1"></i> Nhà Cung Cấp</a>
                <a href="/admin/ma-giam-gia" class="text-gray-300 hover:text-white"><i class="fas fa-ticket mr-1"></i> Mã Giảm Giá</a>
                <a href="/admin/ho-tro" class="text-gray-300 hover:text-white"><i class="fas fa-headset mr-1"></i> Hỗ Trợ</a>
                <a href="/admin/thong-bao" class="text-gray-300 hover:text-white"><i class="fas fa-bullhorn mr-1"></i> Thông Báo</a>
                <a href="/admin/nhat-ky" class="text-gray-300 hover:text-white"><i class="fas fa-shield-virus mr-1"></i> Nhật Ký</a>
                <a href="/admin/cai-dat" class="text-gray-300 hover:text-white"><i class="fas fa-gear mr-1"></i> Cài Đặt</a>
            </nav>

            <div class="flex items-center space-x-3">
                <a href="../index.php" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold">
                    <i class="fas fa-store mr-1"></i> Cửa Hàng
                </a>
            </div>
        </div>
    </header>

    <!-- NỘI DUNG CHÍNH -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- THÔNG TIN TÀI KHOẢN NGÂN HÀNG & SEPAY WEBHOOK -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold text-lg">
                        <i class="fas fa-building-columns"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white"><?= BANK_NAME ?></h3>
                        <p class="text-xs text-gray-400"><?= BANK_ACCOUNT_NAME ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-800 flex justify-between items-center text-xs">
                    <span class="text-gray-400">Số tài khoản:</span>
                    <span class="font-mono text-green-400 font-bold text-sm"><?= BANK_ACCOUNT_NO ?></span>
                </div>
            </div>

            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-green-600/20 text-green-400 flex items-center justify-center font-bold text-lg">
                        <i class="fas fa-money-bill-transfer"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Tổng Nạp Tự Động</h3>
                        <p class="text-xs text-gray-400"><?= $soLuotNap ?> lượt chuyển khoản thành công</p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-800 flex justify-between items-center text-xs">
                    <span class="text-gray-400">Tổng tiền nạp vào:</span>
                    <span class="font-mono text-amber-400 font-bold text-base"><?= number_format($tongNap, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>

            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center font-bold text-lg">
                        <i class="fas fa-satellite-dish"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Webhook Cổng SePay</h3>
                        <p class="text-xs text-gray-400">Tự động bắt giao dịch 24/7</p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-800 flex justify-between items-center text-xs">
                    <span class="text-gray-400">Endpoint Webhook:</span>
                    <span class="font-mono text-blue-400 font-semibold">/backend/webhook_sepay.php</span>
                </div>
            </div>
        </div>

        <!-- BẢNG LỊCH SỬ GIAO DỊCH NGÂN HÀNG -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-receipt text-green-400"></i> Lịch Sử Nhận Tiền Ngân Hàng (VietQR SePay)
                    </h2>
                    <p class="text-xs text-gray-400">Danh sách các giao dịch được cộng tiền tự động vào ví khách hàng</p>
                </div>
                <a href="../../backend/export.php?type=giao_dich" class="px-3 py-1.5 rounded-xl bg-green-600 hover:bg-green-500 text-white text-xs font-bold transition duration-150 flex items-center gap-1.5 shadow-md shadow-green-600/30">
                    <i class="fas fa-file-excel"></i> Xuất CSV Giao Dịch
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                        <tr>
                            <th class="py-3 px-4">Thời Gian</th>
                            <th class="py-3 px-4">Thành Viên</th>
                            <th class="py-3 px-4">Số Tiền Nạp</th>
                            <th class="py-3 px-4">Số Dư Trước</th>
                            <th class="py-3 px-4">Số Dư Sau</th>
                            <th class="py-3 px-4">Nội Dung Chuyển Khoản</th>
                            <th class="py-3 px-4 text-right">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans">
                        <?php if (empty($giaoDichs)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-500 italic">
                                    Chưa có giao dịch nạp tiền ngân hàng nào được ghi nhận.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($giaoDichs as $gd): ?>
                                <tr>
                                    <td class="py-3 px-4 font-mono text-gray-400 whitespace-nowrap"><?= $gd['created_at'] ?></td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-white"><?= htmlspecialchars($gd['user_name']) ?></div>
                                        <div class="text-[11px] text-gray-400"><?= htmlspecialchars($gd['user_email']) ?></div>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-green-400 font-mono text-sm">
                                        +<?= number_format($gd['amount'], 0, ',', '.') ?> VNĐ
                                    </td>
                                    <td class="py-3 px-4 font-mono text-gray-400"><?= number_format($gd['balance_before'], 0, ',', '.') ?>đ</td>
                                    <td class="py-3 px-4 font-mono text-blue-400 font-semibold"><?= number_format($gd['balance_after'], 0, ',', '.') ?>đ</td>
                                    <td class="py-3 px-4 text-gray-300 font-mono"><?= htmlspecialchars($gd['note']) ?></td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="px-2.5 py-0.5 rounded-full bg-green-500/10 border border-green-500/30 text-green-400 text-[10px] font-bold">
                                            <i class="fas fa-circle-check mr-1"></i> Đã Cộng Ví
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
    function ungDungNganHang() {
        return {}
    }
    </script>
</body>
</html>
