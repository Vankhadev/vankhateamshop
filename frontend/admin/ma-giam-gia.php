<?php
// ========================================================
// TRANG QUẢN LÝ MÃ GIẢM GIÁ / VOUCHER (ADMIN COUPONS)
// vankhateamshop - Tạo khuyến mãi % hoặc số tiền cố định, giới hạn lượt dùng
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

// Xử lý tạo mã giảm giá mới
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'them_ma') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = in_array($_POST['discount_type'] ?? '', ['PERCENT', 'FIXED']) ? $_POST['discount_type'] : 'PERCENT';
        $val = (float)($_POST['discount_value'] ?? 0);
        $minOrder = (float)($_POST['min_order_amount'] ?? 0);
        $maxUses = (int)($_POST['max_uses'] ?? 100);

        if (empty($code) || $val <= 0) {
            $thongBao = 'Vui lòng nhập mã và giá trị giảm hợp lệ!';
            $loaiThongBao = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO `coupons` (`code`, `discount_type`, `discount_value`, `min_order_amount`, `max_uses`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$code, $type, $val, $minOrder, $maxUses]);
                $thongBao = "Đã tạo mã giảm giá '{$code}' thành công!";
                $loaiThongBao = 'success';
            } catch (Exception $e) {
                $thongBao = 'Lỗi tạo mã: ' . $e->getMessage();
                $loaiThongBao = 'error';
            }
        }
    } elseif ($action === 'xoa_ma') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM `coupons` WHERE `id` = ?")->execute([$id]);
        $thongBao = 'Đã xóa mã giảm giá thành công!';
        $loaiThongBao = 'success';
    }
}

$coupons = $pdo->query("SELECT * FROM `coupons` ORDER BY `id` DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Mã Giảm Giá - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="{ moModalThem: false }" class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ VIÊN -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-ticket"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Khuyến Mãi</span>
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
                <a href="/admin/ma-giam-gia" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-ticket mr-1"></i> Mã Giảm Giá</a>
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
                    <i class="fas fa-ticket text-amber-500"></i> Quản Lý Mã Giảm Giá (Coupons)
                </h1>
                <p class="text-xs text-gray-400 mt-1">Tạo mã ưu đãi cho khách hàng, kích cầu mua các gói Cursor và Claude</p>
            </div>
            
            <button @click="moModalThem = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Tạo Mã Mới
            </button>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> mr-1.5"></i>
                <?= htmlspecialchars($thongBao) ?>
            </div>
        <?php endif; ?>

        <!-- BẢNG MÃ GIẢM GIÁ -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Voucher</th>
                            <th class="py-3.5 px-4">Mức Giảm</th>
                            <th class="py-3.5 px-4">Đơn Tối Thiểu</th>
                            <th class="py-3.5 px-4 text-center">Đã Dùng / Tối Đa</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-center">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-500">Chưa có mã giảm giá nào. Hãy tạo mã đầu tiên!</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($coupons as $c): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-amber-400 text-sm">
                                    <?= htmlspecialchars($c['code']) ?>
                                </td>

                                <td class="py-3.5 px-4 font-bold text-white">
                                    <?php if ($c['discount_type'] === 'PERCENT'): ?>
                                        <span class="text-green-400">Giảm <?= (int)$c['discount_value'] ?>%</span>
                                    <?php else: ?>
                                        <span class="text-green-400">Giảm <?= number_format($c['discount_value'], 0, ',', '.') ?>đ</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-gray-400">
                                    Từ <?= number_format($c['min_order_amount'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4 text-center font-mono">
                                    <span class="text-amber-400 font-bold"><?= $c['used_count'] ?></span> / <?= $c['max_uses'] ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $c['is_active'] ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                        <?= $c['is_active'] ? 'Hoạt động' : 'Tạm tắt' ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <form method="POST" action="" onsubmit="return confirm('Bạn có chắc muốn xóa mã giảm giá này?');">
                                        <input type="hidden" name="action" value="xoa_ma">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white text-xs font-bold transition">
                                            <i class="fas fa-trash-can mr-1"></i> Xóa
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL THÊM MÃ GIẢM GIÁ -->
        <div x-show="moModalThem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-md w-full p-6 relative shadow-2xl">
                <button @click="moModalThem = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-plus text-blue-500"></i> Tạo Mã Giảm Giá Mới
                </h3>

                <form method="POST" action="" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="them_ma">

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Mã Voucher (viết liền, không dấu):</label>
                        <input type="text" name="code" required placeholder="VD: CHAOMUNG, GIAM10K" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono uppercase focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Loại giảm giá:</label>
                        <select name="discount_type" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="PERCENT">Giảm theo phần trăm (%)</option>
                            <option value="FIXED">Giảm theo số tiền cố định (VNĐ)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Giá trị giảm (% hoặc VNĐ):</label>
                        <input type="number" step="1" name="discount_value" required placeholder="10 (nếu là %) hoặc 20000 (nếu là VNĐ)" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-green-400 font-bold focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Đơn hàng tối thiểu để được áp dụng (VNĐ):</label>
                        <input type="number" step="10000" name="min_order_amount" value="0" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Số lượt sử dụng tối đa:</label>
                        <input type="number" name="max_uses" value="100" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold transition shadow-lg shadow-blue-600/30">
                        Phát Hành Mã Giảm Giá
                    </button>
                </form>
            </div>
        </div>

    </main>

    <footer class="border-t border-gray-800/80 bg-[#0F172A] py-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống
    </footer>

</body>
</html>
