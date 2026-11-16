<?php
// ========================================================
// TRANG QUẢN LÝ THÔNG BÁO & BANNER TRANG CHỦ (ADMIN)
// vankhateamshop - Ghim tin tức khuyến mãi, thông báo hệ thống, bảo trì
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

$thongBaoMsg = '';
$loaiThongBaoMsg = '';

// Xử lý xóa hàng loạt qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'xoa_hang_loat') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $idsSanitized = array_map('intval', $ids);
            $idsSanitized = array_filter($idsSanitized, fn($i) => $i > 0);
            if (!empty($idsSanitized)) {
                $inClause = implode(',', $idsSanitized);
                $pdo->exec("DELETE FROM `announcements` WHERE `id` IN ($inClause)");
                $thongBaoMsg = 'Đã xóa thành công ' . count($idsSanitized) . ' thông báo!';
                $loaiThongBaoMsg = 'success';
            }
        }
    }
}

// Lấy danh sách thông báo
$thongBaos = $pdo->query("SELECT * FROM `announcements` ORDER BY `id` DESC")->fetchAll();
$allIds = array_map(fn($t) => (int)$t['id'], $thongBaos);
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
    <title>Quản Lý Thông Báo - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="ungDungThongBao()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

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
                        <i class="fas fa-bullhorn text-amber-500"></i>
                        <span class="truncate">Quản Lý Thông Báo Website</span>
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
            <main class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto space-y-6">

                <?php if (!empty($thongBaoMsg)): ?>
                    <div class="p-4 rounded-xl flex items-center gap-3 text-xs font-bold <?= $loaiThongBaoMsg === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                        <i class="fas <?= $loaiThongBaoMsg === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> text-base"></i>
                        <span><?= htmlspecialchars($thongBaoMsg) ?></span>
                    </div>
                <?php endif; ?>

                <!-- THANH CÔNG CỤ XÓA HÀNG LOẠT -->
                <div x-show="danhSachChon.length > 0" x-cloak class="p-3.5 rounded-2xl bg-red-950/70 border border-red-800 flex items-center justify-between shadow-lg" x-transition>
                    <div class="text-xs text-red-200 font-bold flex items-center gap-2">
                        <i class="fas fa-trash-can text-red-400"></i>
                        <span>Đã chọn <strong class="text-white text-sm" x-text="danhSachChon.length"></strong> thông báo</span>
                    </div>
                    <form method="POST" action="" onsubmit="return confirm('Bạn có chắc muốn XÓA ' + danhSachChon.length + ' thông báo đã chọn?');">
                        <input type="hidden" name="action" value="xoa_hang_loat">
                        <template x-for="id in danhSachChon" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs transition shadow flex items-center gap-1.5">
                            <i class="fas fa-trash-can"></i> Xóa Đã Chọn
                        </button>
                    </form>
                </div>

                <div class="bg-[#111827] border border-gray-800 rounded-3xl p-6 shadow-xl">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-base font-bold text-white flex items-center gap-2">
                                <i class="fas fa-bullhorn text-amber-400"></i> Danh Sách Thông Báo Hiển Thị Trang Chủ
                            </h2>
                            <p class="text-xs text-gray-400 mt-1">Ghim bảng tin đầu trang để thông báo khuyến mãi, bảo trì hoặc cập nhật quan trọng</p>
                        </div>
                        <button @click="moThemThongBao()" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-amber-600/30">
                            <i class="fas fa-plus"></i> Thêm Thông Báo Mới
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800 bg-gray-900/50">
                                <tr>
                                    <th class="py-3 px-3 w-10 text-center">
                                        <input type="checkbox" @change="chonTatCa($event)" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                    </th>
                                    <th class="py-3 px-4">ID</th>
                                    <th class="py-3 px-4">Tiêu Đề</th>
                                    <th class="py-3 px-4">Nội Dung</th>
                                    <th class="py-3 px-4">Loại Thông Báo</th>
                                    <th class="py-3 px-4">Trạng Thái</th>
                                    <th class="py-3 px-4">Ngày Tạo</th>
                                    <th class="py-3 px-4 text-right">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/60 font-sans">
                                <?php if (empty($thongBaos)): ?>
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-gray-500 italic">
                                            Chưa có thông báo nào. Bấm nút "+ Thêm Thông Báo Mới" để tạo ngay!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($thongBaos as $tb): ?>
                                        <tr class="hover:bg-gray-800/20 transition">
                                            <td class="py-3 px-3 text-center">
                                                <input type="checkbox" :value="<?= $tb['id'] ?>" x-model="danhSachChon" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                            </td>
                                            <td class="py-3 px-4 font-mono text-gray-400">#<?= $tb['id'] ?></td>
                                            <td class="py-3 px-4 font-bold text-white"><?= htmlspecialchars($tb['title']) ?></td>
                                            <td class="py-3 px-4 text-gray-300 max-w-xs truncate" title="<?= htmlspecialchars($tb['content']) ?>"><?= htmlspecialchars($tb['content']) ?></td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $tb['type'] === 'WARNING' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : ($tb['type'] === 'SUCCESS' ? 'bg-green-500/10 text-green-400 border border-green-500/30' : ($tb['type'] === 'DANGER' ? 'bg-red-500/10 text-red-400 border border-red-500/30' : 'bg-blue-500/10 text-blue-400 border border-blue-500/30')) ?>">
                                                    <?= $tb['type'] ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $tb['is_active'] ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                                    <?= $tb['is_active'] ? 'Hiển thị' : 'Tạm ẩn' ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 font-mono text-gray-400 whitespace-nowrap"><?= $tb['created_at'] ?></td>
                                            <td class="py-3 px-4 text-right space-x-2">
                                                <button type="button" @click="suaThongBao(<?= (int)$tb['id'] ?>)" class="text-blue-400 hover:text-blue-300 font-semibold text-xs transition">
                                                    <i class="fas fa-pen-to-square mr-0.5"></i> Sửa
                                                </button>
                                                <button type="button" @click="xoaThongBao(<?= (int)$tb['id'] ?>)" class="text-red-400 hover:text-red-300 font-semibold text-xs transition">
                                                    <i class="fas fa-trash mr-0.5"></i> Xóa
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MODAL THÊM / SỬA THÔNG BÁO -->
                <div x-show="moModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition>
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-lg w-full p-6 relative shadow-2xl space-y-4">
                        <button @click="moModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
                            <i class="fas fa-times text-lg"></i>
                        </button>

                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas" :class="form.id ? 'fa-pen-to-square text-amber-500' : 'fa-plus text-blue-500'"></i>
                            <span x-text="form.id ? 'Chỉnh Sửa Thông Báo #' + form.id : 'Thêm Thông Báo Mới'"></span>
                        </h3>

                        <div class="space-y-4 text-xs">
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Tiêu đề thông báo:</label>
                                <input type="text" x-model="form.title" placeholder="VD: Khuyến mãi Black Friday - Giảm 20% cho đại lý" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Nội dung thông báo (hiển thị trên trang chủ):</label>
                                <textarea x-model="form.content" rows="4" placeholder="Nhập nội dung thông báo đầy đủ gửi đến khách hàng..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-300 font-semibold mb-1">Loại thông báo:</label>
                                    <select x-model="form.type" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                                        <option value="INFO">INFO (Xanh dương)</option>
                                        <option value="SUCCESS">SUCCESS (Xanh lá - Khuyến mãi)</option>
                                        <option value="WARNING">WARNING (Vàng cam - Lưu ý)</option>
                                        <option value="DANGER">DANGER (Đỏ - Bảo trì khẩn cấp)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-gray-300 font-semibold mb-1">Trạng thái hiển thị:</label>
                                    <select x-model="form.is_active" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                                        <option value="1">Hiển thị ngay trên web</option>
                                        <option value="0">Tạm ẩn (Bản nháp)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-2 flex items-center justify-end gap-3">
                                <button type="button" @click="moModal = false" class="px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold transition">
                                    Hủy bỏ
                                </button>
                                <button type="button" @click="luuThongBao()" :disabled="dangLuu" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold transition shadow-lg shadow-amber-600/30 flex items-center gap-2">
                                    <span x-show="!dangLuu"><i class="fas fa-check"></i> Lưu Thông Báo</span>
                                    <span x-show="dangLuu"><i class="fas fa-spinner fa-spin"></i> Đang lưu...</span>
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
    function ungDungThongBao() {
        return {
            moModal: false,
            dangLuu: false,
            danhSachChon: [],
            tatCaIds: <?= json_encode($allIds) ?>,
            tatCaThongBao: <?= json_encode($thongBaos, JSON_UNESCAPED_UNICODE) ?>,
            form: { id: 0, title: '', content: '', type: 'INFO', is_active: 1 },

            chonTatCa(e) {
                if (e.target.checked) {
                    this.danhSachChon = [...this.tatCaIds];
                } else {
                    this.danhSachChon = [];
                }
            },

            moThemThongBao() {
                this.form = { id: 0, title: '', content: '', type: 'INFO', is_active: 1 };
                this.moModal = true;
            },

            suaThongBao(id) {
                const tb = this.tatCaThongBao.find(t => parseInt(t.id) === parseInt(id));
                if (!tb) {
                    alert('Không tìm thấy thông tin thông báo #' + id);
                    return;
                }
                this.form = { 
                    id: parseInt(tb.id), 
                    title: tb.title || '', 
                    content: tb.content || '', 
                    type: tb.type || 'INFO', 
                    is_active: parseInt(tb.is_active) 
                };
                this.moModal = true;
            },

            async luuThongBao() {
                if (!this.form.title || !this.form.content) {
                    alert('Vui lòng nhập đầy đủ tiêu đề và nội dung thông báo!');
                    return;
                }
                this.dangLuu = true;
                const fd = new FormData();
                fd.append('id', this.form.id);
                fd.append('title', this.form.title);
                fd.append('content', this.form.content);
                fd.append('type', this.form.type);
                fd.append('is_active', this.form.is_active);

                try {
                    const res = await fetch('/backend/api.php?action=admin_luu_thong_bao', { method: 'POST', body: fd });
                    const d = await res.json();
                    this.dangLuu = false;
                    if (d.thanh_cong) {
                        alert(d.thong_bao);
                        location.reload();
                    } else {
                        alert(d.thong_bao || 'Lỗi lưu thông báo');
                    }
                } catch(e) {
                    this.dangLuu = false;
                    alert('Lỗi kết nối máy chủ');
                }
            },

            async xoaThongBao(id) {
                if (!confirm('Bạn có chắc muốn xóa thông báo này?')) return;
                const fd = new FormData();
                fd.append('id', id);
                try {
                    const res = await fetch('/backend/api.php?action=admin_xoa_thong_bao', { method: 'POST', body: fd });
                    const d = await res.json();
                    if (d.thanh_cong) {
                        alert('Đã xóa thông báo');
                        location.reload();
                    } else {
                        alert('Lỗi xóa thông báo: ' + (d.thong_bao || ''));
                    }
                } catch(e) {
                    alert('Lỗi kết nối máy chủ');
                }
            }
        }
    }
    </script>
</body>
</html>
