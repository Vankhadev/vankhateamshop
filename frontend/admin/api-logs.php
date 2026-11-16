<?php
// ========================================================
// TRANG QUẢN LÝ KEY & LỊCH SỬ GỌI MODEL/API (ADMIN)
// vankhateamshop - Giám sát IP, Tên khách, Email, HTTPS, Models, Độ trễ (ms), HTTP Status, Phản hồi
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

// Bắt buộc quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

$modelFilter = trim($_GET['model'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$searchKeyword = trim($_GET['s'] ?? '');

$sql = "SELECT * FROM `api_logs` WHERE 1=1";
$params = [];

if (!empty($modelFilter)) {
    $sql .= " AND `model` = ?";
    $params[] = $modelFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND `http_status` = ?";
    $params[] = (int)$statusFilter;
}

if (!empty($searchKeyword)) {
    $sql .= " AND (`ip_address` LIKE ? OR `customer_email` LIKE ? OR `customer_name` LIKE ? OR `api_key_used` LIKE ?)";
    $like = "%{$searchKeyword}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY `id` DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Thống kê nhanh
$tongCalls = (int)$pdo->query("SELECT COUNT(*) FROM `api_logs`")->fetchColumn();
$doTreTB = (int)$pdo->query("SELECT COALESCE(AVG(latency_ms), 0) FROM `api_logs`")->fetchColumn();
$tongLoi = (int)$pdo->query("SELECT COUNT(*) FROM `api_logs` WHERE `http_status` >= 400")->fetchColumn();
$tyLeLoi = $tongCalls > 0 ? round(($tongLoi / $tongCalls) * 100, 1) : 0;

// Danh sách các model duy nhất
$modelsList = $pdo->query("SELECT DISTINCT `model` FROM `api_logs` WHERE `model` IS NOT NULL AND `model` != ''")->fetchAll(PDO::FETCH_COLUMN);
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
    <title>Giám Sát Gọi Model & API - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungApiLogs()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

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
                        <i class="fas fa-bolt text-amber-500"></i>
                        <span class="truncate">Nhật Ký Gọi API Tự Động</span>
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
            <main class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto">

        <!-- THẺ THỐNG KÊ GỌI API -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tổng Lượt Gọi Model</span>
                <div class="text-2xl font-black text-white mt-1"><?= number_format($tongCalls) ?> Requests</div>
                <span class="text-[11px] text-blue-400 mt-1 block">Bao gồm Cursor & Partner API</span>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Độ Trễ Trung Bình</span>
                <div class="text-2xl font-black text-green-400 mt-1"><?= $doTreTB ?> ms</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Tốc độ phản hồi Gateway</span>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tỷ Lệ Lỗi (HTTP >= 400)</span>
                <div class="text-2xl font-black text-amber-400 mt-1"><?= $tyLeLoi ?>%</div>
                <span class="text-[11px] text-gray-400 mt-1 block"><?= $tongLoi ?> yêu cầu lỗi (hết tiền, sai key)</span>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Giao Thức Kênh Truyền</span>
                <div class="text-2xl font-black text-purple-400 mt-1">HTTPS 100%</div>
                <span class="text-[11px] text-green-400 mt-1 block"><i class="fas fa-lock mr-1"></i> Mã hóa SSL/TLS</span>
            </div>
        </div>

        <!-- BẢNG CHI TIẾT LỊCH SỬ GỌI API & MODEL -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-list-check text-blue-400"></i> Bảng Giám Sát Chi Tiết Khách Dùng API & Model
                    </h2>
                    <p class="text-xs text-gray-400">Xem chính xác IP, Tên khách hàng, Email, Model AI gọi, Độ trễ (ms), Mã phản hồi HTTP</p>
                </div>

                <!-- BỘ LỌC TÌM KIẾM -->
                <form method="GET" class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <select name="model" class="bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white">
                        <option value="">-- Tất cả Model --</option>
                        <?php foreach ($modelsList as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $modelFilter === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" class="bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white">
                        <option value="">-- HTTP Status --</option>
                        <option value="200" <?= $statusFilter === '200' ? 'selected' : '' ?>>200 OK</option>
                        <option value="401" <?= $statusFilter === '401' ? 'selected' : '' ?>>401 Unauthorized</option>
                        <option value="402" <?= $statusFilter === '402' ? 'selected' : '' ?>>402 Insufficient Balance</option>
                        <option value="500" <?= $statusFilter === '500' ? 'selected' : '' ?>>500 Internal Error</option>
                    </select>

                    <input type="text" name="s" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="Tìm IP, Email, Tên..." class="bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white placeholder-gray-500">

                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                        <tr>
                            <th class="py-3 px-3">Thời Gian</th>
                            <th class="py-3 px-3">Khách Hàng / Email</th>
                            <th class="py-3 px-3">IP Thật & HTTPS</th>
                            <th class="py-3 px-3">Model AI Gọi</th>
                            <th class="py-3 px-3">Endpoint</th>
                            <th class="py-3 px-3 text-center">Độ Trễ</th>
                            <th class="py-3 px-3 text-center">HTTP Status</th>
                            <th class="py-3 px-3 text-right">Phản Hồi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="py-8 text-center text-gray-500 italic">
                                    Chưa có yêu cầu gọi API nào được ghi nhận.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $lg): ?>
                                <tr>
                                    <td class="py-3 px-3 font-mono text-gray-400 whitespace-nowrap text-[11px]"><?= $lg['created_at'] ?></td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold text-white"><?= htmlspecialchars($lg['customer_name'] ?? 'Khách vãng lai') ?></div>
                                        <div class="text-[11px] text-gray-400 font-mono"><?= htmlspecialchars($lg['customer_email'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="font-mono text-blue-400 font-bold"><?= htmlspecialchars($lg['ip_address']) ?></div>
                                        <span class="text-[10px] font-semibold <?= $lg['is_https'] ? 'text-green-400' : 'text-amber-400' ?>">
                                            <i class="fas <?= $lg['is_https'] ? 'fa-lock' : 'fa-lock-open' ?> mr-0.5"></i> <?= $lg['is_https'] ? 'HTTPS' : 'HTTP' ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded font-mono text-[11px] font-bold bg-purple-500/10 text-purple-300 border border-purple-500/30">
                                            <?= htmlspecialchars($lg['model'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-gray-300 text-[11px]">
                                        <?= htmlspecialchars($lg['endpoint']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono font-bold <?= $lg['latency_ms'] < 1000 ? 'text-green-400' : ($lg['latency_ms'] < 3000 ? 'text-amber-400' : 'text-red-400') ?>">
                                        <?= $lg['latency_ms'] ?> ms
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold <?= $lg['http_status'] === 200 ? 'bg-green-500/10 text-green-400 border border-green-500/30' : 'bg-red-500/10 text-red-400 border border-red-500/30' ?>">
                                            <?= $lg['http_status'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right max-w-xs truncate text-gray-400 text-[11px]" title="<?= htmlspecialchars($lg['response_summary'] ?? '') ?>">
                                        <?= htmlspecialchars($lg['response_summary'] ?? 'Thành công') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
    <script>
    function ungDungApiLogs() {
        return {}
    }
    </script>
</body>
</html>
