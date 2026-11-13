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

// Xử lý khi Admin cập nhật giá hoặc trạng thái sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cap_nhat_san_pham') {
        $productId = (int)$_POST['product_id'];
        $price = (float)$_POST['price'];
        $originalPrice = (float)$_POST['original_price'];
        $image = trim($_POST['image'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isHot = isset($_POST['is_hot']) ? 1 : 0;
        $supplierSku = trim($_POST['supplier_sku'] ?? '');
        $costPrice = (float)($_POST['cost_price'] ?? 0);

        try {
            // 1. Cập nhật bảng products bao gồm cả link ảnh đại diện
            $stmt = $pdo->prepare("UPDATE `products` SET `price` = ?, `original_price` = ?, `image` = ?, `is_active` = ?, `is_hot` = ? WHERE `id` = ?");
            $stmt->execute([$price, $originalPrice, $image, $isActive, $isHot, $productId]);

            // 2. Cập nhật bảng mapping nhà cung cấp đối tác
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
}

// Lấy danh sách toàn bộ sản phẩm và thông tin cấu hình nhà cung cấp
$sql = "
    SELECT 
        p.*, 
        c.name AS category_name,
        m.supplier_sku,
        m.cost_price,
        s.name AS supplier_name
    FROM `products` p
    LEFT JOIN `categories` c ON p.category_id = c.id
    LEFT JOIN `supplier_product_mappings` m ON p.id = m.product_id
    LEFT JOIN `suppliers` s ON m.supplier_id = s.id
    ORDER BY p.category_id ASC, p.price ASC
";
$products = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm & Giá - <?= SHOP_NAME ?></title>
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
                    <i class="fas fa-cubes"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Sản Phẩm & Nguồn Hàng</span>
                </div>
            </div>

            <!-- Menu quản trị -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/dai-ly" class="text-gray-300 hover:text-white"><i class="fas fa-handshake mr-1"></i> Đại Lý</a>
                <a href="/admin/api-logs" class="text-gray-300 hover:text-white"><i class="fas fa-bolt mr-1"></i> Logs API</a>
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
                    <i class="fas fa-boxes-stacked text-amber-500"></i> Danh Sách Gói API & Cấu Hình Giá
                </h1>
                <p class="text-xs text-gray-400 mt-1">Cài đặt giá bán cho khách, mã SKU kết nối với nhà cung cấp và giá vốn nhập</p>
            </div>
            <a href="/admin/dashboard" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-bold transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Quay lại Dashboard
            </a>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 flex items-center gap-3 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> text-base"></i>
                <span><?= htmlspecialchars($thongBao) ?></span>
            </div>
        <?php endif; ?>

        <!-- BẢNG DANH SÁCH SẢN PHẨM -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-3">Ảnh</th>
                            <th class="py-3.5 px-4">Tên Gói / SKU</th>
                            <th class="py-3.5 px-3">Danh Mục</th>
                            <th class="py-3.5 px-3">Giá Bán (VNĐ)</th>
                            <th class="py-3.5 px-3">Giá Gốc</th>
                            <th class="py-3.5 px-3">Link Ảnh Đại Diện (URL)</th>
                            <th class="py-3.5 px-3">Mã SKU Đối Tác</th>
                            <th class="py-3.5 px-3">Giá Vốn</th>
                            <th class="py-3.5 px-3">Lợi Nhuận</th>
                            <th class="py-3.5 px-3 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-3 text-center">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php foreach ($products as $p): 
                            $loiNhuan = $p['price'] - ($p['cost_price'] ?? 0);
                        ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="cap_nhat_san_pham">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">

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
                                            <?= htmlspecialchars($p['name']) ?>
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
                                        <input type="number" step="1000" name="price" value="<?= (int)$p['price'] ?>" class="w-24 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-amber-400 font-bold focus:border-amber-500 focus:outline-none text-xs">
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <input type="number" step="1000" name="original_price" value="<?= (int)$p['original_price'] ?>" class="w-24 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-400 line-through focus:border-gray-500 focus:outline-none text-xs">
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <input type="text" name="image" value="<?= htmlspecialchars($p['image'] ?? '') ?>" placeholder="https://... ảnh .png/.jpg" class="w-40 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-300 text-[11px] focus:border-amber-500 focus:outline-none">
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <input type="text" name="supplier_sku" value="<?= htmlspecialchars($p['supplier_sku'] ?? '') ?>" placeholder="vd: CUR-30D" class="w-28 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-blue-400 font-mono text-[11px] focus:border-blue-500 focus:outline-none">
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <input type="number" step="1000" name="cost_price" value="<?= (int)($p['cost_price'] ?? 0) ?>" class="w-20 px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-300 focus:border-gray-500 focus:outline-none text-xs">
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <span class="font-bold <?= $loiNhuan > 0 ? 'text-green-400' : 'text-gray-400' ?>">
                                            +<?= number_format($loiNhuan, 0, ',', '.') ?>đ
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-3 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?> class="sr-only peer">
                                                <div class="w-7 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-green-600 relative"></div>
                                            </label>
                                            <span class="text-[9px] text-gray-400"><?= $p['is_active'] ? 'Hiện bán' : 'Ẩn' ?></span>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-3 text-center">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition shadow-md shadow-amber-600/30 flex items-center justify-center gap-1 mx-auto">
                                            <i class="fas fa-floppy-disk"></i> Lưu
                                        </button>
                                    </td>
                                </form>
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
