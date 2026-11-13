<?php
// ========================================================
// TRANG QUẢN LÝ THÀNH VIÊN (ADMIN USERS MANAGEMENT)
// vankhateamshop - Danh sách người dùng, xem số dư, cộng/trừ tiền ví thủ công, khóa tài khoản
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

$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM `users` WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (`name` LIKE ? OR `email` LIKE ? OR `phone` LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY `id` DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Thành Viên - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungThanhVien()" class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ VIÊN -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-users-gear"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Thành Viên</span>
                </div>
            </div>

            <!-- Menu quản trị hoàn chỉnh -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/dai-ly" class="text-gray-300 hover:text-white"><i class="fas fa-handshake mr-1"></i> Đại Lý</a>
                <a href="/admin/api-logs" class="text-gray-300 hover:text-white"><i class="fas fa-bolt mr-1"></i> Logs API</a>
                <a href="/admin/danh-muc" class="text-gray-300 hover:text-white"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
                <a href="/admin/thanh-vien" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-users mr-1"></i> Thành Viên</a>
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
                    <i class="fas fa-users text-amber-500"></i> Quản Lý Danh Sách Thành Viên
                </h1>
                <p class="text-xs text-gray-400 mt-1">Xem thông tin khách hàng, số dư ví hiện tại, cộng/trừ tiền thủ công và phân quyền</p>
            </div>
            
            <form method="GET" action="" class="flex items-center gap-2 w-full sm:w-auto">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên, email..." class="px-3.5 py-2 rounded-xl bg-gray-900 border border-gray-700 text-white text-xs w-64 focus:border-amber-500 focus:outline-none">
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    <i class="fas fa-magnifying-glass"></i>
                </button>
            </form>
        </div>

        <!-- BẢNG DANH SÁCH THÀNH VIÊN -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">ID / Thành Viên</th>
                            <th class="py-3.5 px-4">Email</th>
                            <th class="py-3.5 px-4">Số Dư Ví</th>
                            <th class="py-3.5 px-4 text-center">Vai Trò</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4">Ngày Đăng Ký</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-500">Không tìm thấy thành viên nào.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($u['avatar']) ?>" class="w-8 h-8 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                                                <?= mb_substr($u['name'], 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-bold text-white flex items-center gap-1.5">
                                                <?= htmlspecialchars($u['name']) ?>
                                                <?php if (!empty($u['google_id'])): ?>
                                                    <i class="fab fa-google text-[10px] text-blue-400" title="Tài khoản Google"></i>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[10px] text-gray-500 font-mono">ID: #<?= $u['id'] ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-gray-300 font-mono text-[11px]">
                                    <?= htmlspecialchars($u['email']) ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-green-400 text-sm">
                                        <?= number_format($u['balance'], 0, ',', '.') ?>đ
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $u['role'] === 'admin' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-gray-800 text-gray-400' ?>">
                                        <?= $u['role'] ?>
                                    </span>
                                    <?php if (!empty($u['is_reseller'])): ?>
                                        <a href="/admin/dai-ly" class="block mt-1 px-2 py-0.5 rounded text-[9px] font-bold bg-purple-900/60 text-purple-300 border border-purple-700/60 hover:bg-purple-800" title="Xem thông tin đại lý">
                                            <i class="fas fa-handshake mr-0.5"></i> Đại Lý (-<?= (float)$u['reseller_discount'] ?>%)
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <button @click="doiTrangThaiUser(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)" 
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold transition <?= $u['is_active'] ? 'bg-green-950 text-green-400 border border-green-800 hover:bg-red-950' : 'bg-red-950 text-red-400 border border-red-800 hover:bg-green-950' ?>">
                                        <?= $u['is_active'] ? 'Đang hoạt động' : 'Đã khóa' ?>
                                    </button>
                                </td>

                                <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                    <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <button @click="moModalDieuChinh(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>', <?= $u['balance'] ?>)" 
                                            class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition shadow-md shadow-amber-600/30 flex items-center gap-1 mx-auto">
                                        <i class="fas fa-coins"></i> Cộng/Trừ Tiền
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL CỘNG / TRỪ TIỀN VÍ THỦ CÔNG -->
        <div x-show="moModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-md w-full p-6 relative shadow-2xl">
                <button @click="moModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <i class="fas fa-coins text-amber-500"></i> Điều Chỉnh Số Dư Thành Viên
                </h3>
                <p class="text-xs text-gray-400 mb-4">Thành viên: <span class="text-white font-bold" x-text="tenUserDangChon"></span></p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Loại thao tác:</label>
                        <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                            <button @click="loaiThaoTac = 'CONG'" :class="loaiThaoTac === 'CONG' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-400'" class="py-2 rounded-xl transition">
                                <i class="fas fa-plus mr-1"></i> Cộng Tiền (Nạp)
                            </button>
                            <button @click="loaiThaoTac = 'TRU'" :class="loaiThaoTac === 'TRU' ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400'" class="py-2 rounded-xl transition">
                                <i class="fas fa-minus mr-1"></i> Trừ Tiền
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Số tiền (VNĐ):</label>
                        <input type="number" step="1000" min="1000" x-model="soTienDieuChinh" placeholder="Ví dụ: 50000" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-sm text-green-400 font-bold focus:outline-none focus:border-amber-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Lý do điều chỉnh:</label>
                        <input type="text" x-model="lyDoDieuChinh" placeholder="Ví dụ: Khách chuyển khoản bù tiền, hỗ trợ..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500">
                    </div>

                    <button @click="xacNhanDieuChinh()" :disabled="dangXuLy" class="w-full py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition duration-200 shadow-md shadow-amber-600/30 flex items-center justify-center gap-1.5">
                        <span x-show="!dangXuLy"><i class="fas fa-check mr-1"></i> Xác Nhận Điều Chỉnh</span>
                        <span x-show="dangXuLy"><i class="fas fa-circle-notch fa-spin mr-1"></i> Đang xử lý...</span>
                    </button>
                </div>
            </div>
        </div>

    </main>

    <footer class="border-t border-gray-800/80 bg-[#0F172A] py-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống
    </footer>

    <script>
        function ungDungThanhVien() {
            return {
                moModal: false,
                userDangChonId: 0,
                tenUserDangChon: '',
                loaiThaoTac: 'CONG',
                soTienDieuChinh: '',
                lyDoDieuChinh: '',
                dangXuLy: false,

                moModalDieuChinh(id, name, balance) {
                    this.userDangChonId = id;
                    this.tenUserDangChon = name + ' (Hiện có: ' + new Intl.NumberFormat('vi-VN').format(balance) + 'đ)';
                    this.soTienDieuChinh = '';
                    this.lyDoDieuChinh = '';
                    this.loaiThaoTac = 'CONG';
                    this.moModal = true;
                },

                xacNhanDieuChinh() {
                    if (!this.soTienDieuChinh || this.soTienDieuChinh <= 0) {
                        alert('Vui lòng nhập số tiền hợp lệ!');
                        return;
                    }

                    this.dangXuLy = true;
                    const formData = new FormData();
                    formData.append('action', 'admin_dieu_chinh_so_du');
                    formData.append('user_id', this.userDangChonId);
                    formData.append('type', this.loaiThaoTac);
                    formData.append('amount', this.soTienDieuChinh);
                    formData.append('note', this.lyDoDieuChinh);

                    fetch('/backend/api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangXuLy = false;
                        if (data.thanh_cong) {
                            alert(data.thong_bao);
                            location.reload();
                        } else {
                            alert('Lỗi: ' + data.thong_bao);
                        }
                    })
                    .catch(() => {
                        this.dangXuLy = false;
                        alert('Lỗi kết nối máy chủ!');
                    });
                },

                doiTrangThaiUser(id, newStatus) {
                    const msg = newStatus === 1 ? 'Mở khóa tài khoản này?' : 'Khóa tài khoản người dùng này?';
                    if (!confirm(msg)) return;

                    const formData = new FormData();
                    formData.append('action', 'admin_doi_trang_thai_user');
                    formData.append('user_id', id);
                    formData.append('is_active', newStatus);

                    fetch('/backend/api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.thanh_cong) {
                            location.reload();
                        } else {
                            alert('Lỗi: ' + data.thong_bao);
                        }
                    });
                }
            };
        }
    </script>
</body>
</html>
