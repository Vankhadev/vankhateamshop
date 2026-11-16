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

// Xử lý các thao tác POST của Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Giao lại đơn hàng qua đối tác (bắt buộc gọi lại đối tác)
    if ($action === 'thu_giao_lai') {
        $orderId = (int)$_POST['order_id'];
        $dispatcher = new BoDieuPhoiDonHang($pdo);
        $ketQua = $dispatcher->xuLyDonHang($orderId, true); // $forceRetry = true

        if (!empty($ketQua['thanh_cong']) || !empty($ketQua['success'])) {
            $thongBao = "Giao đơn lại thành công qua đối tác: " . ($ketQua['supplier'] ?? 'Thành công');
            $loaiThongBao = "success";
        } else {
            $thongBao = "Thử mua từ đối tác thất bại: " . ($ketQua['error'] ?? $ketQua['message'] ?? 'Lỗi không xác định');
            $loaiThongBao = "error";
        }
    }

    // 1b. Nhập key thủ công trực tiếp cho đơn hàng
    if ($action === 'nhap_key_thu_cong') {
        $orderId = (int)$_POST['order_id'];
        $manualKey = trim($_POST['manual_key'] ?? '');
        if (empty($manualKey)) {
            $thongBao = "Vui lòng nhập nội dung Key hoặc tài khoản cần giao!";
            $loaiThongBao = "error";
        } else {
            try {
                $deliveredJson = json_encode([$manualKey], JSON_UNESCAPED_UNICODE);
                $stmtManual = $pdo->prepare("
                    UPDATE `orders` 
                    SET `status` = 'COMPLETED',
                        `delivered_data` = ?,
                        `supplier_order_code` = 'MANUAL-ADMIN',
                        `error_log` = 'Nhập key thủ công bởi Admin'
                    WHERE `id` = ?
                ");
                $stmtManual->execute([$deliveredJson, $orderId]);

                // Tự động gửi email thông báo hoàn thành & giao key cho khách
                try {
                    $stmtOrderInfo = $pdo->prepare("SELECT o.*, p.name AS product_name FROM `orders` o LEFT JOIN `products` p ON o.product_id = p.id WHERE o.id = ?");
                    $stmtOrderInfo->execute([$orderId]);
                    $ordInfo = $stmtOrderInfo->fetch();
                    if ($ordInfo) {
                        require_once __DIR__ . '/../../backend/mailer.php';
                        guiEmailDonHang('COMPLETED', $ordInfo, ['delivered_data' => $deliveredJson]);
                    }
                } catch (Throwable $eMail) {
                    error_log("Lỗi gửi email manual key: " . $eMail->getMessage());
                }

                $thongBao = "Đã cập nhật key thủ công và hoàn thành đơn hàng #{$orderId}!";
                $loaiThongBao = "success";
            } catch (Exception $e) {
                $thongBao = "Lỗi khi lưu key thủ công: " . $e->getMessage();
                $loaiThongBao = "error";
            }
        }
    }

    // 2. Xóa đơn hàng đơn lẻ
    if ($action === 'xoa_don_hang') {
        $orderId = (int)$_POST['order_id'];
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM `transactions` WHERE `order_id` = $orderId");
            $pdo->exec("DELETE FROM `orders` WHERE `id` = $orderId");
            $pdo->commit();

            $thongBao = "Đã xóa vĩnh viễn đơn hàng #{$orderId} thành công!";
            $loaiThongBao = "success";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $thongBao = "Lỗi khi xóa đơn hàng: " . $e->getMessage();
            $loaiThongBao = "error";
        }
    }

    // 3. Xóa hàng loạt nhiều đơn hàng
    if ($action === 'xoa_hang_loat') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $idsSanitized = array_map('intval', $ids);
            $idsSanitized = array_filter($idsSanitized, fn($i) => $i > 0);
            if (!empty($idsSanitized)) {
                $inClause = implode(',', $idsSanitized);
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("DELETE FROM `transactions` WHERE `order_id` IN ($inClause)");
                    $pdo->exec("DELETE FROM `orders` WHERE `id` IN ($inClause)");
                    $pdo->commit();

                    $thongBao = "Đã xóa thành công " . count($idsSanitized) . " đơn hàng được chọn!";
                    $loaiThongBao = "success";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $thongBao = "Lỗi khi xóa hàng loạt: " . $e->getMessage();
                    $loaiThongBao = "error";
                }
            }
        }
    }
}

// Bộ lọc trạng thái & từ khóa tìm kiếm
$statusFilter = trim($_GET['status'] ?? '');
$searchKeyword = trim($_GET['q'] ?? '');

// Lấy thông tin số dư nhà cung cấp chính (GenzShop)
$supplierPrimary = null;
try {
    $supStmt = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1");
    $supplierPrimary = $supStmt->fetch();
} catch (Exception $e) {
    // Bỏ qua nếu lỗi
}

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
$allOrderIds = array_map(fn($o) => (int)$o['id'], $orders);
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
    <title>Quản Lý Đơn Hàng - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungDonHang()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

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
                        <i class="fas fa-receipt text-amber-500"></i>
                        <span class="truncate">Quản Lý Đơn Hàng & Doanh Thu</span>
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
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-list-check text-amber-500"></i> Quản Lý Tất Cả Đơn Hàng
                </h1>
                <p class="text-xs text-gray-400 mt-1">Theo dõi mã đơn, tài khoản API đã giao tự động, nguồn nhập, xử lý lỗi hoặc xóa đơn rác</p>
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

        <?php if ($supplierPrimary): ?>
            <!-- THẺ THÔNG TIN VÍ NHÀ CUNG CẤP / ĐỐI TÁC -->
            <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-blue-950/60 to-indigo-950/60 border border-blue-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 text-xl shrink-0">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Số Dư Ví Đối Tác Lấy Key:</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-900/60 text-blue-300 border border-blue-700/60 uppercase"><?= htmlspecialchars($supplierPrimary['name']) ?></span>
                        </div>
                        <div class="text-2xl font-black text-white mt-0.5">
                            <?= number_format($supplierPrimary['balance'] ?? 0, 0, ',', '.') ?> <span class="text-xs font-normal text-gray-400">VNĐ</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <a href="https://genzshop.vn" target="_blank" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-extrabold text-xs transition flex items-center gap-1.5 shadow-md">
                        <i class="fas fa-arrow-up-right-from-square"></i> Nạp Ví GenzShop.vn
                    </a>
                </div>
            </div>

            <?php if (($supplierPrimary['balance'] ?? 0) < 25000): ?>
                <div class="mb-6 p-3.5 rounded-2xl bg-amber-950/80 border border-amber-600/80 text-amber-200 text-xs flex items-start gap-3 shadow-lg">
                    <i class="fas fa-triangle-exclamation text-amber-400 text-base shrink-0 mt-0.5"></i>
                    <div class="leading-relaxed">
                        <strong class="text-white font-bold">CẢNH BÁO SỐ DƯ ĐỐI TÁC THẤP:</strong> Số dư trên ví GenzShop hiện tại chỉ còn <strong><?= number_format($supplierPrimary['balance'] ?? 0) ?>đ</strong>. Trong khi gói <em>API Cursor 1 Ngày</em> có giá vốn là <strong>25.000đ</strong>. Khách mua gói này sẽ bị đối tác từ chối trả key do thiếu tiền! Vui lòng nạp thêm tiền vào <a href="https://genzshop.vn" target="_blank" class="underline text-amber-300 font-bold">genzshop.vn</a> để hệ thống mua key tự động.
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- THANH CÔNG CỤ XÓA HÀNG LOẠT (BULK ACTION BAR) -->
        <div x-show="danhSachChon.length > 0" class="mb-4 p-3.5 rounded-2xl bg-red-950/70 border border-red-800 flex items-center justify-between shadow-lg" style="display: none;" x-transition>
            <div class="text-xs text-red-200 font-bold flex items-center gap-2">
                <i class="fas fa-trash-can text-red-400"></i>
                <span>Đã tích chọn <strong class="text-white text-sm" x-text="danhSachChon.length"></strong> đơn hàng</span>
            </div>
            <form method="POST" action="" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn XÓA HÀNG LOẠT ' + danhSachChon.length + ' đơn hàng đã chọn? Thao tác không thể hoàn tác!');">
                <input type="hidden" name="action" value="xoa_hang_loat">
                <template x-for="id in danhSachChon" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs transition shadow-md flex items-center gap-2">
                    <i class="fas fa-trash-can"></i> Xóa Đã Chọn
                </button>
            </form>
        </div>

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
                            <th class="py-3.5 px-3 w-10 text-center">
                                <input type="checkbox" @change="chonTatCa($event)" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                            </th>
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
                                <td colspan="9" class="py-12 text-center text-gray-500">Chưa có đơn hàng nào thỏa mãn điều kiện lọc.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-3 text-center">
                                    <input type="checkbox" :value="<?= $order['id'] ?>" x-model="danhSachChon" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                </td>

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
                                        <?php if ($order['supplier_order_code'] === 'GATEWAY-INSTANT'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-800" title="Key cấp tạm thời">Cấp qua Gateway</span>
                                        <?php elseif ($order['supplier_order_code'] === 'MANUAL-ADMIN'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">Admin nhập key</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">Thành công (NCC)</span>
                                        <?php endif; ?>
                                    <?php elseif ($order['status'] === 'PROCESSING' || $order['status'] === 'PAID'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800 animate-pulse">Chờ cấp key</span>
                                    <?php elseif ($order['status'] === 'PENDING'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-800 text-gray-400 border border-gray-700">Chờ thanh toán</span>
                                    <?php elseif ($order['status'] === 'REFUNDED'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-950 text-purple-400 border border-purple-800">Đã hoàn tiền</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-950 text-red-400 border border-red-800">Lỗi giao hàng</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if (!empty($order['delivered_data'])): ?>
                                        <div class="max-w-[220px] truncate font-mono text-[11px] text-green-300 bg-gray-900 px-2 py-1 rounded border border-gray-800 select-all cursor-pointer" title="<?= htmlspecialchars($order['delivered_data']) ?>">
                                            <?= htmlspecialchars($order['delivered_data']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['error_log'])): ?>
                                        <div class="max-w-[220px] text-[10px] text-red-400 bg-red-950/60 p-1.5 rounded border border-red-800/80 mt-1 leading-tight select-all" title="<?= htmlspecialchars($order['error_log']) ?>">
                                            <i class="fas fa-triangle-exclamation mr-1 text-red-400"></i><?= htmlspecialchars($order['error_log']) ?>
                                        </div>
                                    <?php elseif (empty($order['delivered_data'])): ?>
                                        <span class="text-gray-500 text-[11px]">Chưa có dữ liệu</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <?php if ($order['status'] !== 'COMPLETED' || $order['supplier_order_code'] === 'GATEWAY-INSTANT' || $order['supplier_order_code'] === 'GENZSHOP-ERR'): ?>
                                            <form method="POST" action="" onsubmit="return confirm('Bạn có chắc muốn gọi lại API Đối Tác để mua và cấp key cho đơn #<?= addslashes($order['order_code']) ?>?');" class="inline">
                                                <input type="hidden" name="action" value="thu_giao_lai">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" class="px-2 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white font-bold text-[10px] transition shadow flex items-center gap-1" title="Gọi đối tác giao lại">
                                                    <i class="fas fa-rotate"></i> <span>Giao lại</span>
                                                </button>
                                            </form>

                                            <button type="button" @click="moModalNhapKey(<?= $order['id'] ?>, '<?= addslashes($order['order_code']) ?>')" class="px-2 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white font-bold text-[10px] transition shadow flex items-center gap-1" title="Nhập key thủ công">
                                                <i class="fas fa-keyboard"></i> <span>Nhập key</span>
                                            </button>
                                        <?php endif; ?>

                                        <form method="POST" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa đơn hàng <?= addslashes($order['order_code']) ?>? Thao tác không thể hoàn tác!');" class="inline">
                                            <input type="hidden" name="action" value="xoa_don_hang">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" class="p-1 rounded bg-red-950/60 hover:bg-red-600 text-red-400 hover:text-white font-bold text-[10px] transition border border-red-800/80" title="Xóa đơn hàng">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

            </main>

            <!-- MODAL NHẬP KEY THỦ CÔNG -->
            <div x-show="modalNhapKey" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
                <div class="bg-[#111827] border border-gray-700 rounded-2xl max-w-md w-full p-6 shadow-2xl" @click.away="modalNhapKey = false">
                    <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
                        <i class="fas fa-keyboard text-amber-500"></i>
                        <span>Nhập Key Thủ Công Đơn <span class="text-amber-400" x-text="donHangDangChon.code"></span></span>
                    </h3>
                    <p class="text-xs text-gray-400 mb-4">Nhập API key hoặc định dạng tài khoản để giao ngay cho khách hàng. Đơn sẽ chuyển sang trạng thái Thành công.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="nhap_key_thu_cong">
                        <input type="hidden" name="order_id" :value="donHangDangChon.id">
                        
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-300 mb-1.5">Nội dung Key / Tài khoản bàn giao:</label>
                            <textarea name="manual_key" rows="4" required placeholder="Dán API Key hoặc tài khoản user|pass vào đây..." class="w-full px-3 py-2 rounded-xl bg-gray-900 border border-gray-700 text-white font-mono text-xs focus:border-amber-500 focus:outline-none"></textarea>
                        </div>
                        
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" @click="modalNhapKey = false" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition">
                                Hủy bỏ
                            </button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-500 text-white font-bold text-xs transition shadow flex items-center gap-1.5">
                                <i class="fas fa-check"></i> Lưu & Giao Cho Khách
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>

    <script>
        function ungDungDonHang() {
            return {
                danhSachChon: [],
                tatCaIds: <?= json_encode($allOrderIds) ?>,
                modalNhapKey: false,
                donHangDangChon: { id: 0, code: '' },

                chonTatCa(e) {
                    if (e.target.checked) {
                        this.danhSachChon = [...this.tatCaIds];
                    } else {
                        this.danhSachChon = [];
                    }
                },

                moModalNhapKey(id, code) {
                    this.donHangDangChon = { id: id, code: code };
                    this.modalNhapKey = true;
                }
            };
        }
    </script>
                        this.danhSachChon = [];
                    }
                }
            };
        }
    </script>
</body>
</html>