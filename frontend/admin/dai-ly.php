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
<body x-data="ungDungDaiLy()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER QUẢN TRỊ -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-handshake"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Đại Lý & Đối Tác API</span>
                </div>
            </div>

            <!-- Menu quản trị hoàn chỉnh -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/danh-muc" class="text-gray-300 hover:text-white"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
                <a href="/admin/thanh-vien" class="text-gray-300 hover:text-white"><i class="fas fa-users mr-1"></i> Thành Viên</a>
                <a href="/admin/dai-ly" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-handshake mr-1"></i> Đại Lý</a>
                <a href="/admin/api-logs" class="text-gray-300 hover:text-white"><i class="fas fa-laptop-code mr-1"></i> Lịch Sử Gọi API</a>
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

            <div class="flex items-center space-x-3">
                <button @click="moModalNangCap = true" class="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition duration-150 flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                    <i class="fas fa-user-plus"></i> Nâng Cấp Đại Lý
                </button>
            </div>
        </div>
    </header>

    <!-- NỘI DUNG CHÍNH -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

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

    <!-- MODAL NÂNG CẤP ĐẠI LÝ -->
    <div x-show="moModalNangCap" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-user-shield text-amber-400"></i> Nâng Cấp Thành Viên Lên Đại Lý
                </h3>
                <button @click="moModalNangCap = false" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            <form @submit.prevent="xacNhanNangCap()" class="space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-gray-400 mb-1">Chọn Khách Hàng</label>
                    <select x-model="formNangCap.user_id" required class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-2 text-white">
                        <option value="">-- Chọn thành viên cần nâng cấp --</option>
                        <?php foreach ($danhSachUserThuong as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>) - Ví: <?= number_format($u['balance'], 0, ',', '.') ?>đ
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-gray-400 mb-1">Mức Giảm Giá (Chiết Khấu %)</label>
                    <input type="number" step="0.1" min="0" max="50" x-model="formNangCap.discount" required class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-2 text-white" placeholder="VD: 5, 8 hoặc 10%">
                    <p class="text-[11px] text-gray-500 mt-1">Hệ thống sẽ tự động trừ % này khi đại lý gọi API mua sản phẩm.</p>
                </div>

                <div class="p-3 rounded-xl bg-blue-950/40 border border-blue-900 text-blue-300 text-[11px]">
                    <i class="fas fa-circle-info mr-1"></i> Khi nâng cấp thành công, hệ thống sẽ <strong>tự động sinh 1 mã X-API-Key riêng biệt</strong> duy nhất gán riêng cho đại lý này!
                </div>

                <div class="pt-3 flex justify-end space-x-3">
                    <button type="button" @click="moModalNangCap = false" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300">Hủy</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold">Kích Hoạt Đại Lý</button>
                </div>
            </form>
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
