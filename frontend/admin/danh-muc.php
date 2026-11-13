<?php
// ========================================================
// TRANG QUẢN LÝ DANH MỤC SẢN PHẨM (ADMIN CATEGORIES)
// vankhateamshop - Thêm, sửa, sắp xếp các danh mục (Cursor, Claude, Codex...)
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

$thongBao = '';
$loaiThongBao = '';

// Xử lý thêm / sửa danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'them_danh_muc') {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(trim($_POST['slug'] ?? ''));
        $icon = trim($_POST['icon'] ?? 'fa-bolt');
        $color = trim($_POST['color'] ?? '#1f2937');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($name) || empty($slug)) {
            $thongBao = 'Vui lòng nhập đầy đủ tên và slug danh mục!';
            $loaiThongBao = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `icon`, `color`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $slug, $icon, $color, $sortOrder]);
                $thongBao = 'Thêm danh mục mới thành công!';
                $loaiThongBao = 'success';
            } catch (Exception $e) {
                $thongBao = 'Lỗi: ' . $e->getMessage();
                $loaiThongBao = 'error';
            }
        }
    } elseif ($action === 'sua_danh_muc') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-bolt');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE `categories` SET `name` = ?, `icon` = ?, `sort_order` = ?, `is_active` = ? WHERE `id` = ?");
            $stmt->execute([$name, $icon, $sortOrder, $isActive, $id]);
            $thongBao = 'Cập nhật danh mục thành công!';
            $loaiThongBao = 'success';
        } catch (Exception $e) {
            $thongBao = 'Lỗi cập nhật: ' . $e->getMessage();
            $loaiThongBao = 'error';
        }
    } elseif ($action === 'xoa_danh_muc') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                // 1. Xóa transactions liên quan đến đơn hàng của các sản phẩm thuộc danh mục này
                $pdo->exec("DELETE FROM `transactions` WHERE `order_id` IN (SELECT id FROM `orders` WHERE `product_id` IN (SELECT id FROM `products` WHERE `category_id` = $id))");
                // 2. Xóa đơn hàng thuộc sản phẩm trong danh mục này
                $pdo->exec("DELETE FROM `orders` WHERE `product_id` IN (SELECT id FROM `products` WHERE `category_id` = $id)");
                // 3. Xóa mapping nhà cung cấp
                $pdo->exec("DELETE FROM `supplier_product_mappings` WHERE `product_id` IN (SELECT id FROM `products` WHERE `category_id` = $id)");
                // 4. Xóa sản phẩm
                $pdo->exec("DELETE FROM `products` WHERE `category_id` = $id");
                // 5. Xóa danh mục
                $pdo->exec("DELETE FROM `categories` WHERE `id` = $id");
                $pdo->commit();
                $thongBao = 'Đã xóa danh mục và toàn bộ dữ liệu liên quan thành công!';
                $loaiThongBao = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $thongBao = 'Lỗi khi xóa: ' . $e->getMessage();
                $loaiThongBao = 'error';
            }
        }
    } elseif ($action === 'xoa_hang_loat') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $idsSanitized = array_map('intval', $ids);
            $idsSanitized = array_filter($idsSanitized, fn($i) => $i > 0);
            if (!empty($idsSanitized)) {
                $inClause = implode(',', $idsSanitized);
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("DELETE FROM `transactions` WHERE `order_id` IN (SELECT id FROM `orders` WHERE `product_id` IN (SELECT id FROM `products` WHERE `category_id` IN ($inClause)))");
                    $pdo->exec("DELETE FROM `orders` WHERE `product_id` IN (SELECT id FROM `products` WHERE `category_id` IN ($inClause))");
                    $pdo->exec("DELETE FROM `supplier_product_mappings` WHERE `product_id` IN (SELECT id FROM `products` WHERE `category_id` IN ($inClause))");
                    $pdo->exec("DELETE FROM `products` WHERE `category_id` IN ($inClause)");
                    $pdo->exec("DELETE FROM `categories` WHERE `id` IN ($inClause)");
                    $pdo->commit();
                    $thongBao = 'Đã xóa thành công ' . count($idsSanitized) . ' danh mục được chọn!';
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

// Lấy danh sách danh mục kèm số lượng sản phẩm
$sql = "
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM `categories` c
    LEFT JOIN `products` p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC
";
$categories = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Danh Mục - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungDanhMuc()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-folder-tree text-amber-500"></i>
                        <span>Quản Lý Danh Mục Sản Phẩm</span>
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
                    <i class="fas fa-folder-tree text-amber-500"></i> Danh Mục Gói API
                </h1>
                <p class="text-xs text-gray-400 mt-1">Phân loại các gói Cursor, Claude, Codex, DeepSeek hiển thị ngoài trang chủ</p>
            </div>
            
            <button @click="moModalThem = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Thêm Danh Mục Mới
            </button>
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
                <span>Đã tích chọn <strong class="text-white text-sm" x-text="danhSachChon.length"></strong> danh mục</span>
            </div>
            <form method="POST" action="" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn XÓA HÀNG LOẠT tất cả ' + danhSachChon.length + ' danh mục đã chọn? Các sản phẩm thuộc danh mục này cũng sẽ bị xóa!');">
                <input type="hidden" name="action" value="xoa_hang_loat">
                <template x-for="id in danhSachChon" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs transition shadow-md flex items-center gap-2">
                    <i class="fas fa-trash-can"></i> Xóa Đã Chọn
                </button>
            </form>
        </div>

        <!-- BẢNG DANH MỤC -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4 w-10 text-center">
                                <input type="checkbox" @change="chonTatCa($event)" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                            </th>
                            <th class="py-3.5 px-4">Thứ Tự</th>
                            <th class="py-3.5 px-4">Icon / Tên Danh Mục</th>
                            <th class="py-3.5 px-4">Slug (Đường Dẫn)</th>
                            <th class="py-3.5 px-4 text-center">Số Sản Phẩm</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-center">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4 text-center">
                                    <input type="checkbox" :value="<?= $cat['id'] ?>" x-model="danhSachChon" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                </td>

                                <td class="py-3.5 px-4 font-mono text-amber-400 font-bold">
                                    #<?= $cat['sort_order'] ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5 font-bold text-white">
                                        <div class="w-8 h-8 rounded-xl bg-gray-800 border border-gray-700 flex items-center justify-center text-blue-400">
                                            <i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i>
                                        </div>
                                        <span><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-gray-400">
                                    <?= htmlspecialchars($cat['slug']) ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-gray-800 border border-gray-700 text-gray-300">
                                        <?= $cat['product_count'] ?> gói
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $cat['is_active'] ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                        <?= $cat['is_active'] ? 'Hiển thị' : 'Ẩn' ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="moSua(<?= htmlspecialchars(json_encode($cat)) ?>)" class="px-2.5 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition border border-gray-700" title="Chỉnh sửa">
                                            <i class="fas fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục \'<?= addslashes($cat['name']) ?>\'? Tất cả sản phẩm thuộc danh mục này cũng sẽ bị xóa!');" class="inline">
                                            <input type="hidden" name="action" value="xoa_danh_muc">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-red-950/60 hover:bg-red-600 text-red-400 hover:text-white font-bold text-xs transition border border-red-800/80" title="Xóa danh mục">
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

        <!-- MODAL THÊM DANH MỤC -->
        <div x-show="moModalThem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-md w-full p-6 relative shadow-2xl">
                <button @click="moModalThem = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-plus text-blue-500"></i> Thêm Danh Mục Mới
                </h3>

                <form method="POST" action="" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="them_danh_muc">

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Tên danh mục (vd: OpenAI API):</label>
                        <input type="text" name="name" required placeholder="OpenAI API" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Slug (đường dẫn rút gọn):</label>
                        <input type="text" name="slug" required placeholder="openai-api" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Icon FontAwesome (vd: fa-robot, fa-bolt):</label>
                        <input type="text" name="icon" value="fa-bolt" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Thứ tự hiển thị (số nhỏ xếp trước):</label>
                        <input type="number" name="sort_order" value="10" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-amber-400 font-bold focus:outline-none focus:border-blue-500">
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold transition shadow-lg shadow-blue-600/30">
                        Thêm Danh Mục
                    </button>
                </form>
            </div>
        </div>

        <!-- MODAL SỬA DANH MỤC -->
        <div x-show="moModalSua" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-md w-full p-6 relative shadow-2xl">
                <button @click="moModalSua = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-pen-to-square text-amber-500"></i> Cập Nhật Danh Mục
                </h3>

                <form method="POST" action="" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="sua_danh_muc">
                    <input type="hidden" name="id" :value="suaData.id">

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Tên danh mục:</label>
                        <input type="text" name="name" x-model="suaData.name" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Icon FontAwesome:</label>
                        <input type="text" name="icon" x-model="suaData.icon" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Thứ tự hiển thị:</label>
                        <input type="number" name="sort_order" x-model="suaData.sort_order" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-amber-400 font-bold focus:outline-none focus:border-amber-500">
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="is_cat_active" name="is_active" value="1" :checked="suaData.is_active == 1" class="rounded bg-gray-800 border-gray-700 text-blue-600">
                        <label for="is_cat_active" class="text-xs text-gray-300 font-semibold cursor-pointer">Bật hiển thị danh mục này</label>
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold transition shadow-lg shadow-amber-600/30">
                        Lưu Thay Đổi
                    </button>
                </form>
            </div>
        </div>

                </main>

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>
    <script>
        function ungDungDanhMuc() {
            return {
                moModalThem: false,
                moModalSua: false,
                suaData: {},
                danhSachChon: [],
                tatCaIds: [<?= implode(',', array_column($categories, 'id')) ?>],

                chonTatCa(e) {
                    this.danhSachChon = e.target.checked ? [...this.tatCaIds] : [];
                },

                moSua(cat) {
                    this.suaData = cat;
                    this.moModalSua = true;
                }
            };
        }
    </script>
</body>
</html>
