<?php
// ========================================================
// TRANG QUẢN LÝ SẢN PHẨM & CẤU HÌNH GIÁ VỐN / GIÁ BÁN
// vankhateamshop - Phân hệ Quản trị viên (Admin)
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

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
    batBuocCsrf();
    $action = $_POST['action'];

    // 1. Thêm gói sản phẩm mới
    if ($action === 'them_san_pham') {
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $originalPrice = (float)($_POST['original_price'] ?? 0);
        $image = trim($_POST['image'] ?? '');
        $requestCount = trim($_POST['request_count'] ?? '');
        $durationDays = (int)($_POST['duration_days'] ?? 30);
        $isHot = isset($_POST['is_hot']) ? 1 : 0;
        $supplierSku = trim($_POST['supplier_sku'] ?? '');
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $supportedModels = trim($_POST['supported_models'] ?? '');

        if (empty($name) || empty($sku) || $categoryId <= 0 || $price <= 0) {
            $thongBao = "Vui lòng điền đủ Tên, SKU, Danh mục và Giá bán!";
            $loaiThongBao = "error";
        } else {
            // Tự động tạo slug chuẩn SEO
            $slugBase = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slugBase = trim($slugBase, '-');
            $slug = $slugBase . '-' . rand(100, 999);

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO `products` 
                    (`category_id`, `sku`, `name`, `slug`, `image`, `price`, `original_price`, `request_count`, `duration_days`, `description`, `supported_models`, `is_hot`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$categoryId, $sku, $name, $slug, $image, $price, $originalPrice, $requestCount, $durationDays, $description, $supportedModels, $isHot]);
                $newProductId = $pdo->lastInsertId();

                if (!empty($supplierSku)) {
                    $insertMap = $pdo->prepare("INSERT INTO `supplier_product_mappings` (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`) VALUES (?, 1, ?, ?, 999, 1, 1)");
                    $insertMap->execute([$newProductId, $supplierSku, $costPrice]);
                }

                $pdo->commit();
                $thongBao = "Thêm sản phẩm '{$name}' thành công!";
                $loaiThongBao = "success";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $thongBao = "Lỗi khi thêm sản phẩm: " . $e->getMessage();
                $loaiThongBao = "error";
            }
        }
    }

    // 1b. Cập nhật Model AI cho sản phẩm
    if ($action === 'cap_nhat_models_san_pham') {
        $productId = (int)$_POST['product_id'];
        $models = trim($_POST['supported_models'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE `products` SET `supported_models` = ? WHERE `id` = ?");
            $stmt->execute([$models, $productId]);
            $thongBao = "Đã cập nhật danh sách Model AI cho gói sản phẩm thành công!";
            $loaiThongBao = "success";
        } catch (Exception $e) {
            $thongBao = "Lỗi khi lưu Model AI: " . $e->getMessage();
            $loaiThongBao = "error";
        }
    }

    // 2. Cập nhật giá & thông tin sản phẩm
    if ($action === 'cap_nhat_san_pham') {
        $productId = (int)$_POST['product_id'];
        $price = (float)$_POST['price'];
        $originalPrice = (float)$_POST['original_price'];
        $image = trim($_POST['image'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isHot = isset($_POST['is_hot']) ? 1 : 0;
        $supplierSku = trim($_POST['supplier_sku'] ?? '');
        $costPrice = (float)($_POST['cost_price'] ?? 0);

        try {
            $stmt = $pdo->prepare("UPDATE `products` SET `price` = ?, `original_price` = ?, `image` = ?, `is_active` = ?, `is_hot` = ? WHERE `id` = ?");
            $stmt->execute([$price, $originalPrice, $image, $isActive, $isHot, $productId]);

            if (!empty($supplierSku)) {
                $checkMapping = $pdo->prepare("SELECT id FROM `supplier_product_mappings` WHERE `product_id` = ? LIMIT 1");
                $checkMapping->execute([$productId]);
                if ($checkMapping->fetch()) {
                    $updateMap = $pdo->prepare("UPDATE `supplier_product_mappings` SET `supplier_sku` = ?, `cost_price` = ? WHERE `product_id` = ?");
                    $updateMap->execute([$supplierSku, $costPrice, $productId]);
                } else {
                    $insertMap = $pdo->prepare("INSERT INTO `supplier_product_mappings` (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`) VALUES (?, 1, ?, ?, 999, 1, 1)");
                    $insertMap->execute([$productId, $supplierSku, $costPrice]);
                }
            }

            $thongBao = "Đã cập nhật sản phẩm thành công!";
            $loaiThongBao = "success";
        } catch (Exception $e) {
            $thongBao = "Lỗi khi lưu dữ liệu: " . $e->getMessage();
            $loaiThongBao = "error";
        }
    }

    // 3. Xóa đơn lẻ 1 sản phẩm
    if ($action === 'xoa_san_pham') {
        $productId = (int)$_POST['product_id'];
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM `transactions` WHERE `order_id` IN (SELECT id FROM `orders` WHERE `product_id` = $productId)");
            $pdo->exec("DELETE FROM `orders` WHERE `product_id` = $productId");
            $pdo->exec("DELETE FROM `supplier_product_mappings` WHERE `product_id` = $productId");
            $pdo->exec("DELETE FROM `products` WHERE `id` = $productId");
            $pdo->commit();

            $thongBao = "Đã xóa vĩnh viễn sản phẩm thành công!";
            $loaiThongBao = "success";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $thongBao = "Lỗi khi xóa sản phẩm: " . $e->getMessage();
            $loaiThongBao = "error";
        }
    }

    // 4. Xóa hàng loạt nhiều sản phẩm
    if ($action === 'xoa_hang_loat') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $idsSanitized = array_map('intval', $ids);
            $idsSanitized = array_filter($idsSanitized, fn($i) => $i > 0);
            if (!empty($idsSanitized)) {
                $inClause = implode(',', $idsSanitized);
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("DELETE FROM `transactions` WHERE `order_id` IN (SELECT id FROM `orders` WHERE `product_id` IN ($inClause))");
                    $pdo->exec("DELETE FROM `orders` WHERE `product_id` IN ($inClause)");
                    $pdo->exec("DELETE FROM `supplier_product_mappings` WHERE `product_id` IN ($inClause)");
                    $pdo->exec("DELETE FROM `products` WHERE `id` IN ($inClause)");
                    $pdo->commit();

                    $thongBao = 'Đã xóa thành công ' . count($idsSanitized) . ' sản phẩm được chọn!';
                    $loaiThongBao = 'success';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $thongBao = 'Lỗi khi xóa hàng loạt: ' . $e->getMessage();
                    $loaiThongBao = 'error';
                }
            }
        }
    }
}

// Lấy danh mục phục vụ tạo mới
$allCategories = $pdo->query("SELECT id, name FROM `categories` ORDER BY `sort_order` ASC, `name` ASC")->fetchAll();

// Lấy danh sách toàn bộ sản phẩm và thông tin cấu hình nhà cung cấp
$sql = "
    SELECT 
        p.*, 
        c.name AS category_name,
        m.supplier_sku,
        m.cost_price,
        m.stock,
        s.name AS supplier_name
    FROM `products` p
    LEFT JOIN `categories` c ON p.category_id = c.id
    LEFT JOIN `supplier_product_mappings` m ON p.id = m.product_id
    LEFT JOIN `suppliers` s ON m.supplier_id = s.id
    ORDER BY COALESCE(c.sort_order, 99) ASC, c.id ASC, p.price ASC, p.id ASC
";
$products = $pdo->query($sql)->fetchAll();
$allProductIds = array_map(fn($p) => (int)$p['id'], $products);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm & Giá - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungSanPham()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

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
                        <i class="fas fa-cubes text-amber-500"></i>
                        <span class="truncate">Quản Lý Sản Phẩm & Giá Bán</span>
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
                    <i class="fas fa-boxes-stacked text-amber-500"></i> Danh Sách Gói API & Cấu Hình Giá
                </h1>
                <p class="text-xs text-gray-400 mt-1">Cài đặt giá bán cho khách, mã SKU kết nối nhà cung cấp, thêm mới và xóa sản phẩm</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button @click="dongBoTonKho()" :disabled="dangDongBo" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-emerald-600/30">
                    <i class="fas fa-arrows-rotate" :class="dangDongBo ? 'fa-spin' : ''"></i>
                    <span x-text="dangDongBo ? 'Đang đồng bộ...' : 'Đồng Bộ Kho & Giá'"></span>
                </button>
                <a href="/admin/nha-cung-cap" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-purple-600/30">
                    <i class="fas fa-boxes-stacked"></i> Nhập Từ Kho NCC
                </a>
                <button type="button" @click="moModalCron = true" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-amber-600/30">
                    <i class="fas fa-clock"></i> Lệnh Cron cPanel
                </button>
                <button @click="moModalThem = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                    <i class="fas fa-plus"></i> Thêm Sản Phẩm Mới
                </button>
            </div>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 flex items-center gap-3 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> text-base"></i>
                <span><?= htmlspecialchars($thongBao) ?></span>
            </div>
        <?php endif; ?>

        <!-- THANH CÔNG CỤ XÓA HÀNG LOẠT (BULK ACTION BAR) -->
        <div x-show="danhSachChon.length > 0" class="mb-4 p-3.5 rounded-2xl bg-red-950/70 border border-red-800 flex items-center justify-between shadow-lg" style="display: none;" x-transition>
            <div class="text-xs text-red-200 font-bold flex items-center gap-2">
                <i class="fas fa-trash-can text-red-400"></i>
                <span>Đã tích chọn <strong class="text-white text-sm" x-text="danhSachChon.length"></strong> sản phẩm</span>
            </div>
            <form method="POST" action="" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn XÓA HÀNG LOẠT tất cả ' + danhSachChon.length + ' sản phẩm đã chọn? Thao tác này sẽ xóa vĩnh viễn và không thể khôi phục!');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="xoa_hang_loat">
                <template x-for="id in danhSachChon" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs transition shadow-md flex items-center gap-2">
                    <i class="fas fa-trash-can"></i> Xóa Đã Chọn
                </button>
            </form>
        </div>

        <!-- BẢNG DANH SÁCH SẢN PHẨM -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-3 w-10 text-center">
                                <input type="checkbox" @change="chonTatCa($event)" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                            </th>
                            <th class="py-3.5 px-3">Ảnh</th>
                            <th class="py-3.5 px-4">Tên Gói / SKU</th>
                            <th class="py-3.5 px-3">Danh Mục</th>
                            <th class="py-3.5 px-3">Giá Bán (VNĐ)</th>
                            <th class="py-3.5 px-3">Giá Gốc</th>
                            <th class="py-3.5 px-3">Link Ảnh Đại Diện</th>
                            <th class="py-3.5 px-3">Mã SKU Nguồn</th>
                            <th class="py-3.5 px-3">Giá Vốn</th>
                            <th class="py-3.5 px-3 text-center">Tồn Kho NCC</th>
                            <th class="py-3.5 px-3">Lợi Nhuận</th>
                            <th class="py-3.5 px-3 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-3 text-center">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="12" class="py-12 text-center text-gray-500 font-medium">
                                    Chưa có sản phẩm nào. Hãy bấm "Thêm Sản Phẩm Mới" để tạo gói đầu tiên!
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($products as $p): 
                            $loiNhuan = $p['price'] - ($p['cost_price'] ?? 0);
                            $formId = "form_sp_" . $p['id'];
                        ?>
                            <!-- FORM RIÊNG LẺ ĐỂ LƯU CẬP NHẬT TỪNG DÒNG -->
                            <form id="<?= $formId ?>" method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="cap_nhat_san_pham">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            </form>

                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-3 text-center">
                                    <input type="checkbox" :value="<?= $p['id'] ?>" x-model="danhSachChon" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                </td>

                                <td class="py-3.5 px-3 text-center">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="img" class="w-10 h-10 object-contain rounded-lg border border-gray-700 bg-gray-900 mx-auto" onerror="this.src='https://cdn-icons-png.flaticon.com/512/8644/8644517.png'">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-lg bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-500 mx-auto">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white flex items-center gap-1.5">
                                        <a href="/san-pham/<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="hover:text-amber-400 transition" title="Xem ngoài web">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </a>
                                        <?php if ($p['is_hot']): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/40 text-[9px] font-bold">HOT</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[11px] text-gray-400 font-mono mt-0.5">SKU: <?= htmlspecialchars($p['sku']) ?></div>
                                </td>

                                <td class="py-3.5 px-3 text-gray-300">
                                    <span class="px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-[11px]">
                                        <?= htmlspecialchars($p['category_name'] ?? 'Chưa phân loại') ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-3">
                                    <input form="<?= $formId ?>" type="number" step="1000" name="price" value="<?= (int)$p['price'] ?>" class="w-24 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-amber-400 font-bold focus:border-amber-500 focus:outline-none text-xs">
                                </td>

                                <td class="py-3.5 px-3">
                                    <input form="<?= $formId ?>" type="number" step="1000" name="original_price" value="<?= (int)$p['original_price'] ?>" class="w-24 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-400 line-through focus:border-gray-500 focus:outline-none text-xs">
                                </td>

                                <td class="py-3.5 px-3">
                                    <input form="<?= $formId ?>" type="text" name="image" value="<?= htmlspecialchars($p['image'] ?? '') ?>" placeholder="https://... ảnh" class="w-36 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-300 text-[11px] focus:border-amber-500 focus:outline-none">
                                </td>

                                <td class="py-3.5 px-3">
                                    <input form="<?= $formId ?>" type="text" name="supplier_sku" value="<?= htmlspecialchars($p['supplier_sku'] ?? '') ?>" placeholder="vd: CUR-30D" class="w-24 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-blue-400 font-mono text-[11px] focus:border-blue-500 focus:outline-none">
                                </td>

                                <td class="py-3.5 px-3">
                                    <input form="<?= $formId ?>" type="number" step="1000" name="cost_price" value="<?= (int)($p['cost_price'] ?? 0) ?>" class="w-20 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-300 focus:border-gray-500 focus:outline-none text-xs">
                                </td>

                                <td class="py-3.5 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= (isset($p['stock']) && (int)$p['stock'] > 0) ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                        <?= (isset($p['stock']) && (int)$p['stock'] > 0) ? ($p['stock'] . ' gói') : 'Hết hàng' ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-3">
                                    <span class="font-bold <?= $loiNhuan > 0 ? 'text-green-400' : 'text-gray-400' ?>">
                                        +<?= number_format($loiNhuan, 0, ',', '.') ?>đ
                                    </span>
                                </td>

                                <td class="py-3.5 px-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input form="<?= $formId ?>" type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?> class="sr-only peer">
                                            <div class="w-7 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-green-600 relative"></div>
                                        </label>
                                        <span class="text-[9px] text-gray-400"><?= $p['is_active'] ? 'Bật' : 'Tắt' ?></span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" @click="moSuaModel(<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>)" class="px-2 py-1.5 rounded-lg bg-indigo-950/70 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-700/60 font-bold text-xs transition flex items-center gap-1 shadow-sm" title="Cấu hình Model AI cho gói này">
                                            <i class="fas fa-microchip text-indigo-400"></i> Model
                                        </button>

                                        <button form="<?= $formId ?>" type="submit" class="px-2.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition shadow-md shadow-amber-600/30" title="Lưu chỉnh sửa">
                                            <i class="fas fa-floppy-disk"></i>
                                        </button>
                                        
                                        <form method="POST" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn gói \'<?= addslashes($p['name']) ?>\'? Thao tác không thể khôi phục!');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="xoa_san_pham">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-red-950/60 hover:bg-red-600 text-red-400 hover:text-white font-bold text-xs transition border border-red-800/80" title="Xóa sản phẩm">
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

        <!-- MODAL THÊM SẢN PHẨM MỚI -->
        <div x-show="moModalThem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-xl w-full p-6 relative shadow-2xl overflow-y-auto max-h-[90vh]">
                <button @click="moModalThem = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-plus text-blue-500"></i> Thêm Gói Sản Phẩm Mới
                </h3>

                <form method="POST" action="" class="space-y-4 text-xs">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="them_san_pham">

                    <div>
                        <label class="block text-gray-300 font-bold mb-1">Tên gói sản phẩm <span class="text-red-400">*</span></label>
                        <input type="text" name="name" required placeholder="Ví dụ: API Cursor 30 Ngày (700 Request)" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Danh mục <span class="text-red-400">*</span></label>
                            <select name="category_id" required class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white focus:border-amber-500 focus:outline-none">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($allCategories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Mã SKU hệ thống <span class="text-red-400">*</span></label>
                            <input type="text" name="sku" required placeholder="Ví dụ: VK-CUR-30D-700" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-amber-400 font-mono focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Giá bán cho khách (VNĐ) <span class="text-red-400">*</span></label>
                            <input type="number" step="1000" name="price" required placeholder="85000" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-green-400 font-bold focus:border-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Giá niêm yết gốc (gạch ngang)</label>
                            <input type="number" step="1000" name="original_price" placeholder="110000" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-gray-400 focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Mã SKU Đối Tác Nguồn</label>
                            <input type="text" name="supplier_sku" placeholder="Ví dụ: CUR-30D-700REQ" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-blue-400 font-mono focus:border-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Giá vốn nhập (VNĐ)</label>
                            <input type="number" step="1000" name="cost_price" placeholder="65000" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-gray-300 focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Số Request / Giới hạn</label>
                            <input type="text" name="request_count" placeholder="Ví dụ: 700 Request" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white focus:border-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-300 font-bold mb-1">Thời hạn (ngày)</label>
                            <input type="number" name="duration_days" value="30" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-bold mb-1">Model AI Hỗ Trợ (cách nhau bởi dấu phẩy)</label>
                        <input type="text" name="supported_models" placeholder="VD: claude-3-5-sonnet, gpt-4o, deepseek-reasoner, cursor-small" class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-amber-400 font-mono focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-bold mb-1">Link Ảnh Đại Diện (URL)</label>
                        <input type="text" name="image" placeholder="https://cdn-icons-png.flaticon.com/..." class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white focus:border-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-bold mb-1">Mô tả chi tiết / Hướng dẫn sử dụng</label>
                        <textarea name="description" rows="3" placeholder="Nhập tóm tắt mô tả sản phẩm..." class="w-full px-3 py-2 rounded-xl bg-gray-950 border border-gray-700 text-white focus:border-amber-500 focus:outline-none"></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_hot" id="chkHot" value="1" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0">
                        <label for="chkHot" class="text-gray-300 cursor-pointer">Đánh dấu gói nổi bật (HOT badge)</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                        <button type="button" @click="moModalThem = false" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold transition">
                            Hủy
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold transition shadow-lg shadow-blue-600/30">
                            <i class="fas fa-check mr-1"></i> Tạo Sản Phẩm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL CẤU HÌNH MODEL AI CHO SẢN PHẨM -->
        <div x-show="moModalModel" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-lg w-full p-6 relative shadow-2xl space-y-4">
                <button type="button" @click="moModalModel = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <div class="flex items-center gap-2 border-b border-gray-800 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Cấu Hình Danh Sách Model AI</h3>
                        <p class="text-xs text-gray-400 truncate max-w-xs" x-text="sanPhamModel?.name"></p>
                    </div>
                </div>

                <form method="POST" action="" class="space-y-4 text-xs">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="cap_nhat_models_san_pham">
                    <input type="hidden" name="product_id" :value="sanPhamModel?.id">

                    <div>
                        <label class="block text-gray-300 font-bold mb-2">Bấm chọn nhanh Model phổ biến:</label>
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            <template x-for="m in ['claude-3-5-sonnet', 'claude-3-5-haiku', 'claude-3-opus', 'gpt-4o', 'gpt-4o-mini', 'o1-preview', 'o3-mini', 'deepseek-reasoner', 'deepseek-chat', 'cursor-small', 'gemini-1.5-pro', 'gemini-2.0-flash', 'grok-2']" :key="m">
                                <button type="button" @click="themNhanhModel(m)" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-indigo-600/30 text-gray-300 hover:text-indigo-300 border border-gray-800 hover:border-indigo-500/50 text-[11px] font-mono transition flex items-center gap-1">
                                    <i class="fas fa-plus text-[9px] text-indigo-400"></i>
                                    <span x-text="m"></span>
                                </button>
                            </template>
                        </div>

                        <label class="block text-gray-300 font-bold mb-1">Danh sách Model (cách nhau bởi dấu phẩy hoặc dòng):</label>
                        <textarea name="supported_models" x-model="chuoiModels" rows="4" placeholder="VD: claude-3-5-sonnet, gpt-4o, deepseek-reasoner, deepseek-chat, cursor-small" class="w-full px-3 py-2.5 rounded-xl bg-gray-950 border border-gray-700 text-amber-300 font-mono focus:border-indigo-500 focus:outline-none"></textarea>
                        <p class="text-[11px] text-gray-400 mt-1">Danh sách model này sẽ xuất hiện ở bảng chi tiết tính năng của gói ngoài trang sản phẩm để khách hàng biết gói hỗ trợ những AI nào.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-800">
                        <button type="button" @click="moModalModel = false" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold transition">
                            Hủy
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold transition shadow-lg shadow-indigo-600/30 flex items-center gap-1.5">
                            <i class="fas fa-floppy-disk"></i> Lưu Cấu Hình Model
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL HƯỚNG DẪN CRON JOB CPANEL & LINUX -->
        <div x-show="moModalCron" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-xl w-full p-6 relative shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                <button type="button" @click="moModalCron = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <div class="flex items-center gap-2 border-b border-gray-800 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Hướng Dẫn Cài Đặt Cron Job cPanel & VPS</h3>
                        <p class="text-xs text-gray-400">Tự động cập nhật kho, giá vốn & tự thêm sản phẩm mới từ đối tác</p>
                    </div>
                </div>

                <div class="space-y-3 text-xs text-gray-300">
                    <div class="p-3 bg-amber-950/30 border border-amber-800/60 rounded-2xl text-amber-300 text-[11px] leading-relaxed">
                        <i class="fas fa-circle-info mr-1"></i> Cron Job là các tác vụ chạy ngầm tự động định kỳ (ví dụ mỗi phút) giúp hệ thống luôn đồng bộ kho hàng, giá vốn, tự nhập sản phẩm mới và tự động ẩn khi đối tác hết hàng mà bạn không cần phải thao tác thủ công.
                    </div>

                    <div class="space-y-2">
                        <div class="font-bold text-white">Các bước thiết lập trong cPanel:</div>
                        <ol class="list-decimal list-inside space-y-1.5 text-gray-300 pl-1 leading-relaxed">
                            <li>Đăng nhập vào tài khoản <strong>cPanel</strong> của bạn.</li>
                            <li>Trong mục <strong>Advanced (Nâng cao)</strong>, nhấp vào <strong>Cron Jobs</strong>.</li>
                            <li>Trong phần <strong>Add New Cron Job</strong>:
                                <ul class="list-disc list-inside pl-4 mt-1 text-gray-400 space-y-0.5">
                                    <li>Chọn <em>Common Settings:</em> <strong>Once Per Minute (* * * * *)</strong> để cron chạy mỗi phút.</li>
                                    <li>Trong trường <em>Command</em>, sao chép và dán 1 trong các lệnh bên dưới:</li>
                                </ul>
                            </li>
                        </ol>
                    </div>

                    <!-- LỆNH 1: CRON ĐỒNG BỘ KHO, GIÁ & SẢN PHẨM MỚI -->
                    <div class="p-3.5 bg-gray-950 rounded-2xl border border-gray-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-emerald-400 flex items-center gap-1.5">
                                <i class="fas fa-arrows-rotate"></i> Lệnh 1: Đồng bộ kho, giá & tự thêm sản phẩm mới
                            </span>
                            <button type="button" @click="navigator.clipboard.writeText('curl -s &quot;https://tiemai.io.vn/backend/cron_sync.php?secret=VK_CRON_SECRET_2026&quot; >/dev/null 2>&1'); alert('Đã sao chép lệnh Cron 1!')" class="px-2.5 py-1 rounded bg-blue-600/30 hover:bg-blue-600 text-blue-300 hover:text-white font-bold transition flex items-center gap-1">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <code class="block font-mono text-[11px] text-amber-300 bg-gray-900 p-2.5 rounded-xl border border-gray-800 break-all select-all">curl -s "https://tiemai.io.vn/backend/cron_sync.php?secret=VK_CRON_SECRET_2026" >/dev/null 2>&1</code>
                        <div class="text-[10px] text-gray-500">Khuyên dùng chạy mỗi phút (* * * * *) hoặc mỗi 5 phút (*/5 * * * *).</div>
                    </div>

                    <!-- LỆNH 2: CRON TOÀN DIỆN HỆ THỐNG -->
                    <div class="p-3.5 bg-gray-950 rounded-2xl border border-gray-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-blue-400 flex items-center gap-1.5">
                                <i class="fas fa-shield-halved"></i> Lệnh 2: Cron tổng hợp (Kho, SePay ACB, Hủy đơn treo quá 2h)
                            </span>
                            <button type="button" @click="navigator.clipboard.writeText('curl -s &quot;https://tiemai.io.vn/backend/cron.php?secret=VK_CRON_SECRET_2026&quot; >/dev/null 2>&1'); alert('Đã sao chép lệnh Cron 2!')" class="px-2.5 py-1 rounded bg-blue-600/30 hover:bg-blue-600 text-blue-300 hover:text-white font-bold transition flex items-center gap-1">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <code class="block font-mono text-[11px] text-amber-300 bg-gray-900 p-2.5 rounded-xl border border-gray-800 break-all select-all">curl -s "https://tiemai.io.vn/backend/cron.php?secret=VK_CRON_SECRET_2026" >/dev/null 2>&1</code>
                        <div class="text-[10px] text-gray-500">Chạy toàn bộ tiến trình quét đơn, đối soát SePay và đồng bộ kho hàng.</div>
                    </div>

                    <div class="text-right pt-2 border-t border-gray-800">
                        <button type="button" @click="moModalCron = false" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-white font-bold transition">
                            Đã Hiểu & Đóng
                        </button>
                    </div>
                </div>
            </div>
        </div>

            </main>

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>

    <script>
        function ungDungSanPham() {
            return {
                danhSachChon: [],
                moModalThem: false,
                moModalModel: false,
                moModalCron: false,
                sanPhamModel: null,
                chuoiModels: '',
                tatCaIds: <?= json_encode($allProductIds) ?>,
                dangDongBo: false,

                moSuaModel(p) {
                    this.sanPhamModel = p;
                    this.chuoiModels = p.supported_models || '';
                    this.moModalModel = true;
                },

                themNhanhModel(m) {
                    let cur = this.chuoiModels.trim();
                    if (!cur) {
                        this.chuoiModels = m;
                    } else {
                        let arr = cur.split(',').map(s => s.trim());
                        if (!arr.includes(m)) {
                            arr.push(m);
                            this.chuoiModels = arr.join(', ');
                        }
                    }
                },

                chonTatCa(e) {
                    if (e.target.checked) {
                        this.danhSachChon = [...this.tatCaIds];
                    } else {
                        this.danhSachChon = [];
                    }
                },

                async dongBoTonKho() {
                    this.dangDongBo = true;
                    try {
                        const res = await fetch('/backend/api.php?action=admin_dong_bo_ton_kho');
                        const d = await res.json();
                        this.dangDongBo = false;
                        if (d.thanh_cong) {
                            alert(d.thong_bao);
                            location.reload();
                        } else {
                            alert('Lỗi đồng bộ: ' + (d.thong_bao || ''));
                        }
                    } catch(e) {
                        this.dangDongBo = false;
                        alert('Lỗi kết nối máy chủ');
                    }
                }
            };
        }
    </script>
</body>
</html>