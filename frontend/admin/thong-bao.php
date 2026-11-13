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
    header('Location: /login');
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
<body x-data="ungDungThongBao()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-bullhorn text-amber-500"></i>
                        <span>Quản Lý Thông Báo Website</span>
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

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
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
