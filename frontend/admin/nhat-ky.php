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
<body class="min-h-screen flex flex-col antialiased">

    <!-- HEADER QUẢN TRỊ -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Nhật Ký Bảo Mật & Hệ Thống</span>
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
                <a href="/admin/nhat-ky" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-shield-virus mr-1"></i> Nhật Ký</a>
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

    <!-- FOOTER -->
    <footer class="bg-[#0F172A] border-t border-gray-800 py-6 text-center text-xs text-gray-500">
        <?= SHOP_NAME ?> &copy; 2026. Bảng điều khiển quản trị tối cao.
    </footer>

</body>
</html>
