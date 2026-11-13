<?php
// ========================================================
// TRANG QUẢN LÝ ĐẠI LÝ & CẤP QUYỀN ĐẤU API (ADMIN)
// vankhateamshop - Nâng cấp thành viên, cấp key riêng & chiết khấu sỉ
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

// Bắt buộc quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

// 1. Lấy danh sách toàn bộ Đại lý hiện có
$stmtDaiLy = $pdo->query("
    SELECT id, name, email, phone, balance, reseller_key, reseller_discount, is_active, created_at, updated_at
    FROM `users`
    WHERE `is_reseller` = 1
    ORDER BY id DESC
");
$danhSachDaiLy = $stmtDaiLy->fetchAll();

// 2. Lấy danh sách thành viên thường để chọn nâng cấp
$stmtUsers = $pdo->query("
    SELECT id, name, email, balance 
    FROM `users` 
    WHERE `is_reseller` = 0 AND `role` != 'admin'
    ORDER BY id DESC
");
$danhSachUserThuong = $stmtUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đại Lý & Cấp Key API - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungDaiLy()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-handshake text-amber-500"></i>
                        <span>Quản Lý Đại Lý & Chiết Khấu API</span>
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

        <!-- THẺ THỐNG KÊ ĐẠI LÝ -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tổng Số Đại Lý Đang Hoạt Động</span>
                <div class="text-2xl font-black text-amber-400 mt-1"><?= count($danhSachDaiLy) ?> Đối Tác</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Mỗi đại lý có 1 mã API Key riêng biệt</span>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Endpoint API Đấu Nối</span>
                <div class="text-sm font-mono font-bold text-green-400 mt-1">/api/partner/v1/purchase.php</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Hỗ trợ Idempotency Key tự động</span>
            </div>
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tài Liệu Cho Đại Lý</span>
                <div class="mt-2">
                    <a href="../docs.php" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-blue-400 hover:underline font-bold">
                        <i class="fas fa-book-open"></i> Xem Trang Docs Partner API &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- BẢNG DANH SÁCH ĐẠI LÝ -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-handshake text-amber-400"></i> Danh Sách Đối Tác Đại Lý Đấu API
                    </h2>
                    <p class="text-xs text-gray-400">Quản lý mã X-API-Key riêng biệt, mức chiết khấu và số dư ví của từng đối tác</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                        <tr>
                            <th class="py-3 px-4">Đại Lý</th>
                            <th class="py-3 px-4">Số Dư Ví</th>
                            <th class="py-3 px-4">Mức Giảm Giá (Chiết Khấu)</th>
                            <th class="py-3 px-4">Mã X-API-Key Riêng Biệt</th>
                            <th class="py-3 px-4">Trạng Thái</th>
                            <th class="py-3 px-4 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans">
                        <?php if (empty($danhSachDaiLy)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-500 italic">
                                    Chưa có đại lý nào. Bấm nút "Nâng Cấp Đại Lý" để kích hoạt đối tác đầu tiên!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($danhSachDaiLy as $dl): ?>
                                <tr>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-white"><?= htmlspecialchars($dl['name']) ?></div>
                                        <div class="text-[11px] text-gray-400"><?= htmlspecialchars($dl['email']) ?></div>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-green-400">
                                        <?= number_format($dl['balance'], 0, ',', '.') ?> VNĐ
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 font-bold text-xs">
                                            Giảm <?= (float)$dl['reseller_discount'] ?>%
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-1.5 font-mono text-blue-300 bg-[#0B0F19] px-2.5 py-1 rounded-lg border border-gray-800 w-fit">
                                            <span><?= htmlspecialchars(substr($dl['reseller_key'], 0, 14)) ?>...</span>
                                            <button @click="saoChep('<?= htmlspecialchars($dl['reseller_key']) ?>')" class="text-gray-400 hover:text-white" title="Sao chép toàn bộ key">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $dl['is_active'] ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                            <?= $dl['is_active'] ? 'Hoạt động' : 'Tạm khóa' ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                                        <button @click="capLaiKey(<?= $dl['id'] ?>)" class="text-amber-400 hover:text-amber-300 font-semibold" title="Tạo mới lại key đại lý này">
                                            <i class="fas fa-rotate mr-0.5"></i> Đổi Key
                                        </button>
                                        <button @click="suaChietKhau(<?= $dl['id'] ?>, <?= (float)$dl['reseller_discount'] ?>)" class="text-blue-400 hover:text-blue-300 font-semibold" title="Sửa mức % chiết khấu">
                                            <i class="fas fa-percent mr-0.5"></i> Chiết Khấu
                                        </button>
                                        <button @click="haCap(<?= $dl['id'] ?>)" class="text-red-400 hover:text-red-300 font-semibold" title="Hạ cấp về khách thường">
                                            <i class="fas fa-user-minus mr-0.5"></i> Thu Hồi
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
    function ungDungDaiLy() {
        return {
            moModalNangCap: false,
            formNangCap: { user_id: '', discount: 5.0 },

            saoChep(text) {
                navigator.clipboard.writeText(text);
                alert('Đã sao chép API Key Đại Lý: ' + text);
            },

            async xacNhanNangCap() {
                const fd = new FormData();
                fd.append('user_id', this.formNangCap.user_id);
                fd.append('discount', this.formNangCap.discount);

                const res = await fetch('/backend/api.php?action=admin_nang_cap_dai_ly', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.thanh_cong) {
                    alert(d.thong_bao);
                    location.reload();
                } else {
                    alert(d.thong_bao || 'Lỗi nâng cấp đại lý');
                }
            },

            async capLaiKey(userId) {
                if (!confirm('Bạn có chắc muốn cấp lại API Key cho đại lý này? Khóa cũ sẽ bị vô hiệu hóa ngay lập tức!')) return;
                const fd = new FormData();
                fd.append('user_id', userId);
                const res = await fetch('/backend/api.php?action=admin_doi_key_dai_ly', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.thanh_cong) {
                    alert(d.thong_bao);
                    location.reload();
                } else {
                    alert(d.thong_bao || 'Lỗi cấp lại key');
                }
            },

            async suaChietKhau(userId, chietKhauCu) {
                const mucMoi = prompt('Nhập mức chiết khấu mới (%) cho đại lý này:', chietKhauCu);
                if (mucMoi === null) return;
                const fd = new FormData();
                fd.append('user_id', userId);
                fd.append('discount', parseFloat(mucMoi));
                const res = await fetch('/backend/api.php?action=admin_sua_chiet_khau_dai_ly', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.thanh_cong) {
                    alert(d.thong_bao);
                    location.reload();
                } else {
                    alert(d.thong_bao || 'Lỗi sửa chiết khấu');
                }
            },

            async haCap(userId) {
                if (!confirm('Bạn có chắc muốn thu hồi quyền đại lý của người dùng này?')) return;
                const fd = new FormData();
                fd.append('user_id', userId);
                const res = await fetch('/backend/api.php?action=admin_ha_cap_dai_ly', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.thanh_cong) {
                    alert(d.thong_bao);
                    location.reload();
                } else {
                    alert(d.thong_bao || 'Lỗi thu hồi quyền');
                }
            }
        }
    }
    </script>
</body>
</html>
