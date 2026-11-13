<?php
// ========================================================
// TRANG NHẬT KÝ HỆ THỐNG & BẢO MẬT (SYSTEM AUDIT LOGS)
// vankhateamshop - Theo dõi hoạt động, IP thật Cloudflare & cảnh báo
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

$levelLoc = $_GET['level'] ?? '';
$tuKhoa = trim($_GET['s'] ?? '');

$sql = "SELECT * FROM `system_logs` WHERE 1=1";
$params = [];

if (!empty($levelLoc) && in_array($levelLoc, ['INFO', 'WARNING', 'ERROR'])) {
    $sql .= " AND `level` = ?";
    $params[] = $levelLoc;
}

if (!empty($tuKhoa)) {
    $sql .= " AND (`action` LIKE ? OR `message` LIKE ? OR `ip_address` LIKE ?)";
    $like = "%{$tuKhoa}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY `id` DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Thống kê nhanh
$tongLog = (int)$pdo->query("SELECT COUNT(*) FROM `system_logs`")->fetchColumn();
$logLoi = (int)$pdo->query("SELECT COUNT(*) FROM `system_logs` WHERE `level` = 'ERROR'")->fetchColumn();
$logCanhBao = (int)$pdo->query("SELECT COUNT(*) FROM `system_logs` WHERE `level` = 'WARNING'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhật Ký Hệ Thống & Bảo Mật - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
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
                        <i class="fas fa-book text-amber-500"></i>
                        <span class="truncate">Nhật Ký Hoạt Động & Bảo Mật</span>
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

        <!-- THẺ THỐNG KÊ LOG -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tổng Sự Kiện Đã Ghi</span>
                <div class="text-2xl font-black text-white mt-1"><?= number_format($tongLog) ?></div>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Cảnh Báo (WARNING)</span>
                <div class="text-2xl font-black text-amber-400 mt-1"><?= number_format($logCanhBao) ?></div>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-red-400 uppercase tracking-wider">Lỗi Nghiêm Trọng (ERROR)</span>
                <div class="text-2xl font-black text-red-400 mt-1"><?= number_format($logLoi) ?></div>
            </div>
        </div>

        <!-- BẢNG NHẬT KÝ -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-blue-400"></i> Nhật Ký Hoạt Động & Địa Chỉ IP Khách
                    </h2>
                    <p class="text-xs text-gray-400">Theo dõi thời gian thực các sự kiện từ Cloudflare Connecting IP</p>
                </div>

                <!-- Bộ lọc -->
                <form method="GET" class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <select name="level" class="bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white">
                        <option value="">-- Tất cả mức độ --</option>
                        <option value="INFO" <?= $levelLoc === 'INFO' ? 'selected' : '' ?>>INFO</option>
                        <option value="WARNING" <?= $levelLoc === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                        <option value="ERROR" <?= $levelLoc === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                    </select>

                    <input type="text" name="s" value="<?= htmlspecialchars($tuKhoa) ?>" placeholder="Tìm IP hoặc hành động..." class="bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white placeholder-gray-500">

                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                        <tr>
                            <th class="py-3 px-4">Thời Gian</th>
                            <th class="py-3 px-4">Mức Độ</th>
                            <th class="py-3 px-4">Hành Động</th>
                            <th class="py-3 px-4">Thông Điệp Chi Tiết</th>
                            <th class="py-3 px-4 text-right">Cloudflare Real IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-500 italic">
                                    Không tìm thấy nhật ký phù hợp với bộ lọc.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $lg): ?>
                                <tr>
                                    <td class="py-3 px-4 font-mono text-gray-400 whitespace-nowrap"><?= $lg['created_at'] ?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $lg['level'] === 'ERROR' ? 'bg-red-500/10 text-red-400 border border-red-500/30' : ($lg['level'] === 'WARNING' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'bg-blue-500/10 text-blue-400 border border-blue-500/30') ?>">
                                            <?= $lg['level'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-white"><?= htmlspecialchars($lg['action']) ?></td>
                                    <td class="py-3 px-4 text-gray-300 font-mono"><?= htmlspecialchars($lg['message']) ?></td>
                                    <td class="py-3 px-4 text-right font-mono text-blue-400 whitespace-nowrap"><?= htmlspecialchars($lg['ip_address'] ?? 'N/A') ?></td>
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
    </body>
</html>
