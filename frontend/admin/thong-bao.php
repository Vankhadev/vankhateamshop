<?php
// ========================================================
// TRANG QUẢN LÝ THÔNG BÁO & BANNER TRANG CHỦ (ADMIN)
// vankhateamshop - Ghim tin tức khuyến mãi, bảo trì hệ thống
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

// Lấy danh sách thông báo
$thongBaos = $pdo->query("SELECT * FROM `announcements` ORDER BY `id` DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Thông Báo - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungThongBao()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER QUẢN TRỊ -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Thông Báo Hệ Thống</span>
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
                <a href="/admin/thong-bao" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-bullhorn mr-1"></i> Thông Báo</a>
                <a href="/admin/nhat-ky" class="text-gray-300 hover:text-white"><i class="fas fa-shield-virus mr-1"></i> Nhật Ký</a>
                <a href="/admin/cai-dat" class="text-gray-300 hover:text-white"><i class="fas fa-gear mr-1"></i> Cài Đặt</a>
            </nav>

            <div class="flex items-center space-x-3">
                <button @click="moThemThongBao()" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition duration-150 flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                    <i class="fas fa-plus"></i> Tạo Thông Báo
                </button>
                <a href="../index.php" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold">
                    <i class="fas fa-store mr-1"></i> Cửa Hàng
                </a>
            </div>
        </div>
    </header>

    <!-- NỘI DUNG CHÍNH -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-bullhorn text-amber-400"></i> Danh Sách Thông Báo Hiển Thị Trang Chủ
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                        <tr>
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
                                <td colspan="7" class="py-8 text-center text-gray-500 italic">
                                    Chưa có thông báo nào. Bấm nút "Tạo Thông Báo" để tạo mới!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($thongBaos as $tb): ?>
                                <tr>
                                    <td class="py-3 px-4 font-mono text-gray-400">#<?= $tb['id'] ?></td>
                                    <td class="py-3 px-4 font-bold text-white"><?= htmlspecialchars($tb['title']) ?></td>
                                    <td class="py-3 px-4 text-gray-300 max-w-xs truncate"><?= htmlspecialchars($tb['content']) ?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $tb['type'] === 'WARNING' ? 'bg-amber-500/10 text-amber-400' : ($tb['type'] === 'SUCCESS' ? 'bg-green-500/10 text-green-400' : 'bg-blue-500/10 text-blue-400') ?>">
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
                                        <button @click="suaThongBao(<?= htmlspecialchars(json_encode($tb)) ?>)" class="text-blue-400 hover:text-blue-300 font-semibold text-xs">
                                            <i class="fas fa-pen-to-square mr-0.5"></i> Sửa
                                        </button>
                                        <button @click="xoaThongBao(<?= $tb['id'] ?>)" class="text-red-400 hover:text-red-300 font-semibold text-xs">
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

    </main>

    <!-- MODAL THÊM / SỬA THÔNG BÁO -->
    <div x-show="moModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                <h3 class="text-base font-bold text-white" x-text="form.id ? 'Chỉnh Sửa Thông Báo' : 'Tạo Thông Báo Mới'"></h3>
                <button @click="moModal = false" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            <form @submit.prevent="luuThongBao()" class="space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-400 mb-1">Tiêu Đề Thông Báo</label>
                    <input type="text" x-model="form.title" required class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-2 text-white">
                </div>

                <div>
                    <label class="block font-semibold text-gray-400 mb-1">Nội Dung Chi Tiết</label>
                    <textarea x-model="form.content" rows="4" required class="w-full bg-[#1E293B] border border-gray-700 rounded-xl p-3 text-white"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-gray-400 mb-1">Loại Thông Báo</label>
                        <select x-model="form.type" class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-2 text-white">
                            <option value="INFO">INFO (Xanh dương)</option>
                            <option value="SUCCESS">SUCCESS (Xanh lá)</option>
                            <option value="WARNING">WARNING (Vàng cam)</option>
                            <option value="DANGER">DANGER (Đỏ)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-400 mb-1">Trạng Thái</label>
                        <select x-model="form.is_active" class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-2 text-white">
                            <option value="1">Hiển thị ra trang chủ</option>
                            <option value="0">Tạm ẩn</option>
                        </select>
                    </div>
                </div>

                <div class="pt-3 flex justify-end space-x-3">
                    <button type="button" @click="moModal = false" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300">Hủy</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold">Lưu Thông Báo</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function ungDungThongBao() {
        return {
            moModal: false,
            form: { id: 0, title: '', content: '', type: 'INFO', is_active: 1 },

            moThemThongBao() {
                this.form = { id: 0, title: '', content: '', type: 'INFO', is_active: 1 };
                this.moModal = true;
            },

            suaThongBao(tb) {
                this.form = { id: tb.id, title: tb.title, content: tb.content, type: tb.type, is_active: tb.is_active };
                this.moModal = true;
            },

            async luuThongBao() {
                const fd = new FormData();
                fd.append('id', this.form.id);
                fd.append('title', this.form.title);
                fd.append('content', this.form.content);
                fd.append('type', this.form.type);
                fd.append('is_active', this.form.is_active);

                const res = await fetch('/backend/api.php?action=admin_luu_thong_bao', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.thanh_cong) {
                    alert(d.thong_bao);
                    location.reload();
                } else {
                    alert(d.thong_bao || 'Lỗi lưu thông báo');
                }
            },

            async xoaThongBao(id) {
                if (!confirm('Bạn có chắc muốn xóa thông báo này?')) return;
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('/backend/api.php?action=admin_xoa_thong_bao', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.thanh_cong) {
                    alert('Đã xóa thông báo');
                    location.reload();
                } else {
                    alert('Lỗi xóa thông báo');
                }
            }
        }
    }
    </script>
</body>
</html>
