<?php
// ========================================================
// TRANG QUẢN LÝ ĐƠN HÀNG TOÀN DIỆN (ADMIN ORDERS)
// vankhateamshop - Xem lịch sử mua hàng, tài khoản/key đã giao, tra cứu lỗi
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';
require_once __DIR__ . '/../../backend/dispatcher.php';

// Thiết lập tiêu đề bảo mật HTTP
thietLapBaoMatHeaders();

// Bắt buộc phải đăng nhập quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

$thongBao = '';
$loaiThongBao = '';

// Xử lý khi Admin bấm Thử Giao Lại (Retry Delivery) đối với đơn bị lỗi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'thu_giao_lai') {
        $orderId = (int)$_POST['order_id'];
        $dispatcher = new BoDieuPhoiDonHang($pdo);
        $ketQua = $dispatcher->xuLyDonHang($orderId);

        if ($ketQua['success']) {
            $thongBao = "Giao đơn lại thành công qua nhà cung cấp: " . ($ketQua['supplier'] ?? 'Thành công');
            $loaiThongBao = "success";
        } else {
            $thongBao = "Thử giao lại thất bại: " . ($ketQua['error'] ?? 'Lỗi không xác định');
            $loaiThongBao = "error";
        }
    }
}

// Bộ lọc trạng thái & từ khóa tìm kiếm
$statusFilter = trim($_GET['status'] ?? '');
$searchKeyword = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        o.*,
        p.name AS product_name,
        p.sku AS product_sku,
        s.name AS supplier_name
    FROM `orders` o
    LEFT JOIN `products` p ON o.product_id = p.id
    LEFT JOIN `suppliers` s ON o.supplier_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchKeyword)) {
    $sql .= " AND (o.order_code LIKE ? OR o.customer_email LIKE ?)";
    $params[] = "%$searchKeyword%";
    $params[] = "%$searchKeyword%";
}

$sql .= " ORDER BY o.id DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đơn Hàng - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ TRÊN CÙNG -->
    <header class="bg-[#0F172A] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-receipt"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Đơn Hàng & Khách Mua</span>
                </div>
            </div>

            <!-- Menu quản trị -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/danh-muc" class="text-gray-300 hover:text-white"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
                <a href="/admin/thanh-vien" class="text-gray-300 hover:text-white"><i class="fas fa-users mr-1"></i> Thành Viên</a>
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
        </div>
    </header>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-list-check text-amber-500"></i> Quản Lý Tất Cả Đơn Hàng
                </h1>
                <p class="text-xs text-gray-400 mt-1">Theo dõi mã đơn, tài khoản API đã giao tự động, nguồn nhập và xử lý lỗi fallback</p>
            </div>
            <a href="/admin/dashboard" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-bold transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Bảng Thống Kê
            </a>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 flex items-center gap-3 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> text-base"></i>
                <span><?= htmlspecialchars($thongBao) ?></span>
            </div>
        <?php endif; ?>

        <!-- THANH TÌM KIẾM & BỘ LỌC -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
            <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="text" name="q" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="Tìm mã đơn hoặc email khách..." class="px-3 py-2 rounded-xl bg-gray-900 border border-gray-700 text-white text-xs w-64 focus:border-amber-500 focus:outline-none">
                
                <select name="status" class="px-3 py-2 rounded-xl bg-gray-900 border border-gray-700 text-white text-xs focus:border-amber-500 focus:outline-none">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="COMPLETED" <?= $statusFilter === 'COMPLETED' ? 'selected' : '' ?>>Thành công (Đã giao key)</option>
                    <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Chờ thanh toán</option>
                    <option value="PAID" <?= $statusFilter === 'PAID' ? 'selected' : '' ?>>Đã thanh toán (Đang giao)</option>
                    <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>Lỗi giao hàng</option>
                    <option value="REFUNDED" <?= $statusFilter === 'REFUNDED' ? 'selected' : '' ?>>Đã hoàn tiền vào ví</option>
                </select>

                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    <i class="fas fa-filter mr-1"></i> Lọc
                </button>
            </form>

            <span class="text-xs text-gray-400">Hiển thị 50 đơn hàng mới nhất</span>
        </div>

        <!-- BẢNG ĐƠN HÀNG -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Đơn / Thời Gian</th>
                            <th class="py-3.5 px-4">Sản Phẩm</th>
                            <th class="py-3.5 px-4">Khách Hàng (Email)</th>
                            <th class="py-3.5 px-4">Số Tiền (VNĐ)</th>
                            <th class="py-3.5 px-4">Nguồn Cung Cấp</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4">Key / Dữ Liệu Đã Giao</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="py-8 text-center text-gray-500">Chưa có đơn hàng nào thỏa mãn điều kiện lọc.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-bold text-amber-400"><?= htmlspecialchars($order['order_code']) ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
                                    <?php if (!empty($order['ip_address'])): ?>
                                        <div class="text-[9px] text-gray-600 font-mono">IP: <?= htmlspecialchars($order['ip_address']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($order['product_name'] ?? 'Sản phẩm đã xóa') ?></div>
                                    <div class="text-[11px] text-gray-400">SL: <?= $order['quantity'] ?></div>
                                </td>

                                <td class="py-3.5 px-4 text-gray-300">
                                    <?= htmlspecialchars($order['customer_email']) ?>
                                </td>

                                <td class="py-3.5 px-4 font-bold text-white">
                                    <?= number_format($order['total_amount'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if (!empty($order['supplier_name'])): ?>
                                        <span class="px-2 py-0.5 rounded bg-blue-900/60 border border-blue-700/60 text-blue-300 text-[10px] font-bold">
                                            <?= htmlspecialchars($order['supplier_name']) ?>
                                        </span>
                                        <?php if (!empty($order['supplier_order_code'])): ?>
                                            <div class="text-[10px] text-gray-400 font-mono mt-0.5"><?= htmlspecialchars($order['supplier_order_code']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-[11px]">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($order['status'] === 'COMPLETED'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">Thành công</span>
                                    <?php elseif ($order['status'] === 'PAID'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-950 text-blue-400 border border-blue-800">Đã thanh toán</span>
                                    <?php elseif ($order['status'] === 'PENDING'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">Chờ tiền</span>
                                    <?php elseif ($order['status'] === 'REFUNDED'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-950 text-purple-400 border border-purple-800">Đã hoàn tiền</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-950 text-red-400 border border-red-800">Lỗi giao hàng</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if (!empty($order['delivered_data'])): ?>
                                        <div class="max-w-[200px] truncate font-mono text-[11px] text-green-300 bg-gray-900 px-2 py-1 rounded border border-gray-800" title="<?= htmlspecialchars($order['delivered_data']) ?>">
                                            <?= htmlspecialchars($order['delivered_data']) ?>
                                        </div>
                                    <?php elseif (!empty($order['error_log'])): ?>
                                        <div class="max-w-[200px] truncate font-mono text-[11px] text-red-400 bg-red-950/40 px-2 py-1 rounded border border-red-900/60" title="<?= htmlspecialchars($order['error_log']) ?>">
                                            Lỗi: <?= htmlspecialchars($order['error_log']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-[11px]">Chưa giao</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($order['status'] === 'FAILED' || $order['status'] === 'PAID'): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Bạn có chắc muốn thử gọi nhà cung cấp để giao lại đơn này?');">
                                            <input type="hidden" name="action" value="thu_giao_lai">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white font-bold text-[10px] transition shadow-md flex items-center gap-1 mx-auto">
                                                <i class="fas fa-rotate"></i> Giao Lại
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-600 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="border-t border-gray-800/80 bg-[#0F172A] py-4 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?> - Hệ Thống Bán Hàng Tự Động
    </footer>

</body>
</html>
