<?php
// ========================================================
// TRANG QUẢN LÝ BIẾN ĐỘNG SỐ DƯ TOÀN HỆ THỐNG (ADMIN TRANSACTIONS)
// vankhateamshop - Theo dõi toàn bộ dòng tiền, lịch sử nạp/mua/hoàn tiền
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

$typeFilter = trim($_GET['type'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT t.*, u.name AS user_name, u.email AS user_email
    FROM `transactions` t
    JOIN `users` u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($typeFilter)) {
    $sql .= " AND t.type = ?";
    $params[] = $typeFilter;
}

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR t.note LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY t.id DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Tổng số dư hiện có của tất cả thành viên trong hệ thống
$tongSoDuKhach = (float)$pdo->query("SELECT SUM(balance) FROM `users` WHERE `role` = 'user'")->fetchColumn();
$tongNap = (float)$pdo->query("SELECT SUM(amount) FROM `transactions` WHERE `type` = 'DEPOSIT'")->fetchColumn();
$tongTieu = (float)$pdo->query("SELECT SUM(amount) FROM `transactions` WHERE `type` = 'PURCHASE'")->fetchColumn();
$tongHoan = (float)$pdo->query("SELECT SUM(amount) FROM `transactions` WHERE `type` = 'REFUND'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biến Động Số Dư Toàn Hệ Thống - <?= SHOP_NAME ?></title>
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
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-amber-500"></i>
                        <span>Lịch Sử Biến Động Số Dư</span>
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/" target="_blank" class="px-3.5 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-semibold text-gray-200 transition flex items-center gap-1.5 border border-gray-700">
                        <i class="fas fa-globe text-blue-400"></i> Xem Website Shop
                    </a>
                    <a href="/logout" class="px-3.5 py-1.5 rounded-xl bg-red-600/10 hover:bg-red-600/20 text-red-400 border border-red-500/20 text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fas fa-right-from-bracket"></i> Thoát
                    </a>
                </div>
            </header>

            <!-- NỘI DUNG QUẢN TRỊ CHÍNH -->
            <main class="p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-money-bill-transfer text-amber-500"></i> Lịch Sử Dòng Tiền & Biến Động Số Dư
                </h1>
                <p class="text-xs text-gray-400 mt-1">Giám sát mọi giao dịch nạp tiền, trừ tiền, hoàn trả và điều chỉnh thủ công</p>
            </div>
        </div>

        <!-- 4 THẺ TỔNG KẾT TÀI CHÍNH -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <span class="text-xs text-gray-400 block font-medium">Tổng Tiền Nạp Hệ Thống</span>
                <span class="text-2xl font-black text-green-400 mt-1 block">
                    <?= number_format($tongNap, 0, ',', '.') ?>đ
                </span>
                <span class="text-[11px] text-gray-500">Tiền vào qua VietQR</span>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <span class="text-xs text-gray-400 block font-medium">Tổng Tiền Bán Gói API</span>
                <span class="text-2xl font-black text-blue-400 mt-1 block">
                    <?= number_format($tongTieu, 0, ',', '.') ?>đ
                </span>
                <span class="text-[11px] text-gray-500">Khách đã chi tiêu</span>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <span class="text-xs text-gray-400 block font-medium">Tổng Tiền Hoàn Trả</span>
                <span class="text-2xl font-black text-purple-400 mt-1 block">
                    <?= number_format($tongHoan, 0, ',', '.') ?>đ
                </span>
                <span class="text-[11px] text-gray-500">Hoàn ví khi lỗi nguồn</span>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <span class="text-xs text-gray-400 block font-medium">Số Dư Khách Còn Giữ</span>
                <span class="text-2xl font-black text-amber-400 mt-1 block">
                    <?= number_format($tongSoDuKhach, 0, ',', '.') ?>đ
                </span>
                <span class="text-[11px] text-gray-500">Tiền ví chưa tiêu</span>
            </div>
        </div>

        <!-- BỘ LỌC GIAO DỊCH -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
            <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên khách, email, ghi chú..." class="px-3.5 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white text-xs w-64 focus:border-amber-500 focus:outline-none">
                
                <select name="type" class="px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white text-xs focus:border-amber-500 focus:outline-none">
                    <option value="">-- Tất cả loại giao dịch --</option>
                    <option value="DEPOSIT" <?= $typeFilter === 'DEPOSIT' ? 'selected' : '' ?>>Nạp tiền (DEPOSIT)</option>
                    <option value="PURCHASE" <?= $typeFilter === 'PURCHASE' ? 'selected' : '' ?>>Mua hàng (PURCHASE)</option>
                    <option value="REFUND" <?= $typeFilter === 'REFUND' ? 'selected' : '' ?>>Hoàn tiền (REFUND)</option>
                    <option value="BONUS" <?= $typeFilter === 'BONUS' ? 'selected' : '' ?>>Cộng thưởng (BONUS)</option>
                </select>

                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    <i class="fas fa-filter mr-1"></i> Lọc Dữ Liệu
                </button>
            </form>
            <span class="text-xs text-gray-400">Hiển thị 50 giao dịch gần nhất</span>
        </div>

        <!-- BẢNG GIAO DỊCH -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã GD / Thời Gian</th>
                            <th class="py-3.5 px-4">Thành Viên</th>
                            <th class="py-3.5 px-4 text-center">Loại GD</th>
                            <th class="py-3.5 px-4">Số Tiền</th>
                            <th class="py-3.5 px-4">Số Dư Trước</th>
                            <th class="py-3.5 px-4">Số Dư Sau</th>
                            <th class="py-3.5 px-4">Lý Do / Ghi Chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-500">Chưa có giao dịch nào phù hợp điều kiện lọc.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($transactions as $tx): 
                            $isPlus = in_array($tx['type'], ['DEPOSIT', 'REFUND', 'BONUS']);
                        ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono text-gray-400 font-bold">#<?= $tx['id'] ?></div>
                                    <div class="text-[10px] text-gray-500"><?= date('H:i - d/m/Y', strtotime($tx['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($tx['user_name']) ?></div>
                                    <div class="text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($tx['user_email']) ?></div>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($tx['type'] === 'DEPOSIT'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">Nạp tiền</span>
                                    <?php elseif ($tx['type'] === 'PURCHASE'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-950 text-red-400 border border-red-800">Mua hàng</span>
                                    <?php elseif ($tx['type'] === 'REFUND'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-950 text-purple-400 border border-purple-800">Hoàn tiền</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-950 text-blue-400 border border-blue-800"><?= htmlspecialchars($tx['type']) ?></span>
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

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>
    </body>
</html>
