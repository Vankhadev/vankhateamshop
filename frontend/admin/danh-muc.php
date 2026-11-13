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
    header('Location: ../login.php');
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
<body x-data="ungDungDanhMuc()" class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ VIÊN -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-folder-tree"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Danh Mục Gói API</span>
                </div>
            </div>

            <!-- Menu quản trị -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/danh-muc" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
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

        <!-- BẢNG DANH MỤC -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
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
                                    <button @click="moSua(<?= htmlspecialchars(json_encode($cat)) ?>)" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition border border-gray-700">
                                        <i class="fas fa-pen-to-square mr-1"></i> Sửa
                                    </button>
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

    <footer class="border-t border-gray-800/80 bg-[#0F172A] py-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống
    </footer>

    <script>
        function ungDungDanhMuc() {
            return {
                moModalThem: false,
                moModalSua: false,
                suaData: {},

                moSua(cat) {
                    this.suaData = cat;
                    this.moModalSua = true;
                }
            };
        }
    </script>
</body>
</html>
