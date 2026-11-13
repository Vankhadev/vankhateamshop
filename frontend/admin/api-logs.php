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
    header('Location: ../login.php');
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
<body x-data="ungDungApiLogs()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER QUẢN TRỊ -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Giám Sát Key & Model Khách Dùng</span>
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
                <a href="/admin/dai-ly" class="text-gray-300 hover:text-white"><i class="fas fa-handshake mr-1"></i> Đại Lý</a>
                <a href="/admin/api-logs" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-laptop-code mr-1"></i> Lịch Sử Gọi API</a>
                <a href="/admin/ngan-hang" class="text-gray-300 hover:text-white"><i class="fas fa-building-columns mr-1"></i> Ngân Hàng</a>
                <a href="/admin/nha-cung-cap" class="text-gray-300 hover:text-white"><i class="fas fa-network-wired mr-1"></i> Nhà Cung Cấp</a>
                <a href="/admin/ma-giam-gia" class="text-gray-300 hover:text-white"><i class="fas fa-ticket mr-1"></i> Mã Giảm Giá</a>
                <a href="/admin/ho-tro" class="text-gray-300 hover:text-white"><i class="fas fa-headset mr-1"></i> Hỗ Trợ</a>
                <a href="/admin/thong-bao" class="text-gray-300 hover:text-white"><i class="fas fa-bullhorn mr-1"></i> Thông Báo</a>
                <a href="/admin/nhat-ky" class="text-gray-300 hover:text-white"><i class="fas fa-shield-virus mr-1"></i> Nhật Ký</a>
                <a href="/admin/cai-dat" class="text-gray-300 hover:text-white"><i class="fas fa-gear mr-1"></i> Cài Đặt</a>
                <a href="/logout" class="text-red-400 hover:text-red-300 ml-2">
                    <i class="fas fa-right-from-bracket mr-1"></i> Đăng Xuất
                </a>
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

    <script>
    function ungDungApiLogs() {
        return {}
    }
    </script>
</body>
</html>
