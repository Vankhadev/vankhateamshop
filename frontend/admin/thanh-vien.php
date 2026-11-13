<?php
// ========================================================
// TRANG QUẢN LÝ THÀNH VIÊN (ADMIN USERS MANAGEMENT)
// vankhateamshop - Danh sách người dùng, xem số dư, cộng/trừ tiền ví, khóa/mở, nâng đại lý, xóa tài khoản
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
$currentAdminId = (int)($_SESSION['user_id'] ?? 0);

// Xử lý các thao tác POST của Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Xóa đơn lẻ 1 người dùng
    if ($action === 'xoa_user') {
        $userId = (int)$_POST['user_id'];
        if ($userId === $currentAdminId) {
            $thongBao = 'Không thể tự xóa tài khoản Admin đang đăng nhập!';
            $loaiThongBao = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->exec("DELETE FROM `transactions` WHERE `user_id` = $userId");
                $pdo->exec("UPDATE `orders` SET `user_id` = NULL WHERE `user_id` = $userId");
                $pdo->exec("DELETE FROM `users` WHERE `id` = $userId");
                $pdo->commit();

                $thongBao = 'Đã xóa vĩnh viễn tài khoản thành viên thành công!';
                $loaiThongBao = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $thongBao = 'Lỗi khi xóa người dùng: ' . $e->getMessage();
                $loaiThongBao = 'error';
            }
        }
    }

    // 2. Xóa hàng loạt nhiều người dùng
    if ($action === 'xoa_hang_loat') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $idsSanitized = array_map('intval', $ids);
            // Loại trừ tài khoản của admin đang đăng nhập
            $idsSanitized = array_filter($idsSanitized, fn($i) => $i > 0 && $i !== $currentAdminId);
            if (!empty($idsSanitized)) {
                $inClause = implode(',', $idsSanitized);
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("DELETE FROM `transactions` WHERE `user_id` IN ($inClause)");
                    $pdo->exec("UPDATE `orders` SET `user_id` = NULL WHERE `user_id` IN ($inClause)");
                    $pdo->exec("DELETE FROM `users` WHERE `id` IN ($inClause)");
                    $pdo->commit();

                    $thongBao = 'Đã xóa thành công ' . count($idsSanitized) . ' tài khoản được chọn!';
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

// Lấy danh sách ID cho phép xóa (loại trừ chính admin đang đăng nhập)
$deletableUserIds = [];
foreach ($users as $u) {
    if ((int)$u['id'] !== $currentAdminId) {
        $deletableUserIds[] = (int)$u['id'];
    }
}
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
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="ungDungThanhVien()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-users text-amber-500"></i>
                        <span>Quản Lý Thành Viên & Người Dùng</span>
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
            <main class="p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto space-y-6">
        
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-bold text-white flex items-center gap-2">
                            <i class="fas fa-users text-amber-500"></i> Quản Lý Danh Sách Thành Viên
                        </h1>
                        <p class="text-xs text-gray-400 mt-1">Xem thông tin khách hàng, số dư ví hiện tại, nạp/trừ tiền thủ công, nâng đại lý, khóa/mở và xóa tài khoản</p>
                    </div>
                    
                    <form method="GET" action="" class="flex items-center gap-2 w-full sm:w-auto">
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên, email..." class="px-3.5 py-2 rounded-xl bg-gray-900 border border-gray-700 text-white text-xs w-64 focus:border-amber-500 focus:outline-none">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                            <i class="fas fa-magnifying-glass"></i>
                        </button>
                    </form>
                </div>

                <?php if (!empty($thongBao)): ?>
                    <div class="p-4 rounded-xl flex items-center gap-3 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                        <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> text-base"></i>
                        <span><?= htmlspecialchars($thongBao) ?></span>
                    </div>
                <?php endif; ?>

                <!-- THANH CÔNG CỤ XÓA HÀNG LOẠT (BULK ACTION BAR) -->
                <div x-show="danhSachChon.length > 0" x-cloak class="p-3.5 rounded-2xl bg-red-950/70 border border-red-800 flex items-center justify-between shadow-lg" x-transition>
                    <div class="text-xs text-red-200 font-bold flex items-center gap-2">
                        <i class="fas fa-trash-can text-red-400"></i>
                        <span>Đã tích chọn <strong class="text-white text-sm" x-text="danhSachChon.length"></strong> thành viên</span>
                    </div>
                    <form method="POST" action="" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn XÓA HÀNG LOẠT tất cả ' + danhSachChon.length + ' tài khoản đã chọn? Thao tác không thể hoàn tác!');">
                        <input type="hidden" name="action" value="xoa_hang_loat">
                        <template x-for="id in danhSachChon" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs transition shadow-md flex items-center gap-2">
                            <i class="fas fa-trash-can"></i> Xóa Đã Chọn
                        </button>
                    </form>
                </div>

                <!-- BẢNG DANH SÁCH THÀNH VIÊN -->
                <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                                <tr>
                                    <th class="py-3.5 px-3 w-10 text-center">
                                        <input type="checkbox" @change="chonTatCa($event)" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                    </th>
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
                                        <td colspan="8" class="py-8 text-center text-gray-500">Không tìm thấy thành viên nào.</td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($users as $u): 
                                    $isCurrentAdmin = ((int)$u['id'] === $currentAdminId);
                                ?>
                                    <tr class="hover:bg-gray-800/30 transition">
                                        <td class="py-3.5 px-3 text-center">
                                            <?php if (!$isCurrentAdmin): ?>
                                                <input type="checkbox" :value="<?= $u['id'] ?>" x-model="danhSachChon" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-amber-500 focus:ring-0 cursor-pointer">
                                            <?php else: ?>
                                                <i class="fas fa-shield-halved text-amber-500/60 text-xs" title="Tài khoản Admin hiện tại"></i>
                                            <?php endif; ?>
                                        </td>

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
                                                <div class="mt-1 px-2 py-0.5 rounded text-[9px] font-bold bg-purple-900/60 text-purple-300 border border-purple-700/60" title="Đại lý chiết khấu">
                                                    <i class="fas fa-handshake mr-0.5"></i> Đại Lý (-<?= (float)$u['reseller_discount'] ?>%)
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="py-3.5 px-4 text-center">
                                            <?php if (!$isCurrentAdmin): ?>
                                                <button @click="doiTrangThaiUser(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)" 
                                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold transition <?= $u['is_active'] ? 'bg-green-950 text-green-400 border border-green-800 hover:bg-red-950 hover:text-red-400' : 'bg-red-950 text-red-400 border border-red-800 hover:bg-green-950 hover:text-green-400' ?>"
                                                        title="Bấm để đổi trạng thái khóa/mở">
                                                    <i class="fas <?= $u['is_active'] ? 'fa-circle-check text-green-400' : 'fa-ban text-red-400' ?> mr-1"></i>
                                                    <?= $u['is_active'] ? 'Đang hoạt động' : 'Đã khóa' ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">
                                                    <i class="fas fa-circle-check mr-1"></i> Đang hoạt động
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                            <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                                        </td>

                                        <!-- CỘT THAO TÁC ĐẦY ĐỦ -->
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <!-- 1. CỘNG / TRỪ TIỀN -->
                                                <button @click="moModalDieuChinh(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>', <?= $u['balance'] ?>)" 
                                                        class="px-2.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition shadow-md shadow-amber-600/30 flex items-center gap-1" title="Cộng / Trừ tiền">
                                                    <i class="fas fa-coins"></i>
                                                </button>

                                                <!-- 2. NÂNG ĐẠI LÝ / CẤU HÌNH VAI TRÒ -->
                                                <button @click="moModalVaiTro(<?= htmlspecialchars(json_encode($u)) ?>)" 
                                                        class="px-2.5 py-1.5 rounded-lg bg-purple-900/70 hover:bg-purple-600 text-purple-300 hover:text-white font-bold text-xs transition border border-purple-700/80 shadow" title="Nâng Đại Lý / Cấu Hình Chiết Khấu / Vai Trò">
                                                    <i class="fas fa-handshake"></i>
                                                </button>

                                                <!-- 3. KHÓA / MỞ KHÓA NHANH -->
                                                <?php if (!$isCurrentAdmin): ?>
                                                    <button @click="doiTrangThaiUser(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)" 
                                                            class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition border <?= $u['is_active'] ? 'bg-yellow-950/60 hover:bg-yellow-600 text-yellow-400 hover:text-white border-yellow-800/80' : 'bg-green-950/60 hover:bg-green-600 text-green-400 hover:text-white border-green-800/80' ?>" 
                                                            title="<?= $u['is_active'] ? 'Khóa tài khoản này' : 'Mở khóa tài khoản' ?>">
                                                        <i class="fas <?= $u['is_active'] ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <!-- 4. XÓA TÀI KHOẢN -->
                                                <?php if (!$isCurrentAdmin): ?>
                                                    <form method="POST" action="" onsubmit="return confirm('Bạn có chắc muốn xóa vĩnh viễn tài khoản \'<?= addslashes($u['name']) ?>\'? Tất cả dữ liệu liên quan sẽ bị xóa!');" class="inline">
                                                        <input type="hidden" name="action" value="xoa_user">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-red-950/60 hover:bg-red-600 text-red-400 hover:text-white font-bold text-xs transition border border-red-800/80" title="Xóa tài khoản này">
                                                            <i class="fas fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MODAL CỘNG / TRỪ TIỀN VÍ THỦ CÔNG -->
                <div x-show="moModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition>
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

                <!-- MODAL NÂNG CẤP ĐẠI LÝ & CẤU HÌNH VAI TRÒ -->
                <div x-show="moModalReseller" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition>
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-md w-full p-6 relative shadow-2xl space-y-4">
                        <button @click="moModalReseller = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                            <i class="fas fa-times text-lg"></i>
                        </button>

                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-handshake text-purple-400"></i> Cấu Hình Đại Lý & Phân Quyền
                        </h3>
                        <p class="text-xs text-gray-400">
                            Thành viên: <strong class="text-white" x-text="resellerForm.name"></strong> (<span class="font-mono text-gray-300" x-text="resellerForm.email"></span>)
                        </p>

                        <div class="space-y-4 text-xs">
                            <!-- Vai trò -->
                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Vai trò hệ thống:</label>
                                <select x-model="resellerForm.role" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                                    <option value="user">Thành viên thường (user)</option>
                                    <option value="admin">Quản trị viên cấp cao (admin)</option>
                                </select>
                            </div>

                            <!-- Bật quyền Đại lý -->
                            <div class="p-3 bg-gray-950 rounded-2xl border border-gray-800 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-white">Quyền Đại Lý Cấp 1</div>
                                        <div class="text-[11px] text-gray-400">Cho phép hưởng chiết khấu sỉ và dùng API Reseller</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" x-model="resellerForm.is_reseller" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    </label>
                                </div>

                                <div x-show="resellerForm.is_reseller" class="space-y-3 pt-2 border-t border-gray-800" x-transition>
                                    <div>
                                        <label class="block text-gray-300 font-semibold mb-1">Mức chiết khấu đại lý (%):</label>
                                        <div class="relative">
                                            <input type="number" step="0.5" min="0" max="90" x-model="resellerForm.reseller_discount" placeholder="5.0" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-purple-300 font-bold font-mono focus:outline-none focus:border-purple-500">
                                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 font-bold">%</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-gray-300 font-semibold mb-1">API Key Riêng của Đại Lý:</label>
                                        <div class="flex items-center gap-2">
                                            <input type="text" readonly :value="resellerForm.reseller_key || 'Chưa cấp key (hệ thống sẽ tự tạo)'" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-[11px] font-mono text-gray-300 truncate">
                                            <button type="button" @click="capLaiKeyDaiLy()" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-purple-300 rounded-xl font-bold text-xs transition shrink-0" title="Cấp lại key mới">
                                                <i class="fas fa-rotate"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 flex items-center justify-end gap-3">
                                <button type="button" @click="moModalReseller = false" class="px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold transition">
                                    Đóng
                                </button>
                                <button type="button" @click="luuVaiTroDaiLy()" :disabled="dangLuuReseller" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold transition shadow-lg shadow-purple-600/30 flex items-center gap-2">
                                    <span x-show="!dangLuuReseller"><i class="fas fa-check"></i> Lưu Cấu Hình</span>
                                    <span x-show="dangLuuReseller"><i class="fas fa-spinner fa-spin"></i> Đang lưu...</span>
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
        function ungDungThanhVien() {
            return {
                danhSachChon: [],
                tatCaIds: <?= json_encode($deletableUserIds) ?>,
                moModal: false,
                userDangChonId: 0,
                tenUserDangChon: '',
                loaiThaoTac: 'CONG',
                soTienDieuChinh: '',
                lyDoDieuChinh: '',
                dangXuLy: false,

                // Quản lý Đại lý & vai trò
                moModalReseller: false,
                dangLuuReseller: false,
                resellerForm: {
                    user_id: 0,
                    name: '',
                    email: '',
                    role: 'user',
                    is_reseller: false,
                    reseller_discount: 5.0,
                    reseller_key: ''
                },

                chonTatCa(e) {
                    if (e.target.checked) {
                        this.danhSachChon = [...this.tatCaIds];
                    } else {
                        this.danhSachChon = [];
                    }
                },

                moModalDieuChinh(id, name, balance) {
                    this.userDangChonId = id;
                    this.tenUserDangChon = name + ' (Hiện có: ' + new Intl.NumberFormat('vi-VN').format(balance) + 'đ)';
                    this.soTienDieuChinh = '';
                    this.lyDoDieuChinh = '';
                    this.loaiThaoTac = 'CONG';
                    this.moModal = true;
                },

                moModalVaiTro(u) {
                    this.resellerForm = {
                        user_id: u.id,
                        name: u.name,
                        email: u.email,
                        role: u.role,
                        is_reseller: parseInt(u.is_reseller) === 1,
                        reseller_discount: parseFloat(u.reseller_discount) || 5.0,
                        reseller_key: u.reseller_key || ''
                    };
                    this.moModalReseller = true;
                },

                async luuVaiTroDaiLy() {
                    this.dangLuuReseller = true;
                    const fd = new FormData();
                    fd.append('action', 'admin_cap_nhat_vai_tro_dai_ly');
                    fd.append('user_id', this.resellerForm.user_id);
                    fd.append('role', this.resellerForm.role);
                    fd.append('is_reseller', this.resellerForm.is_reseller ? 1 : 0);
                    fd.append('reseller_discount', this.resellerForm.reseller_discount);

                    try {
                        const res = await fetch('/backend/api.php', { method: 'POST', body: fd });
                        const d = await res.json();
                        this.dangLuuReseller = false;
                        if (d.thanh_cong) {
                            alert(d.thong_bao);
                            location.reload();
                        } else {
                            alert('Lỗi: ' + (d.thong_bao || 'Không thể cập nhật'));
                        }
                    } catch(e) {
                        this.dangLuuReseller = false;
                        alert('Lỗi kết nối máy chủ');
                    }
                },

                async capLaiKeyDaiLy() {
                    if (!confirm('Cấp lại API Key mới cho đại lý này? Khách hàng sẽ phải cập nhật key mới vào bot/code!')) return;
                    const fd = new FormData();
                    fd.append('action', 'admin_doi_key_dai_ly');
                    fd.append('user_id', this.resellerForm.user_id);

                    try {
                        const res = await fetch('/backend/api.php', { method: 'POST', body: fd });
                        const d = await res.json();
                        if (d.thanh_cong) {
                            this.resellerForm.reseller_key = d.reseller_key;
                            alert('Đã tạo API Key mới thành công!');
                        } else {
                            alert('Lỗi: ' + d.thong_bao);
                        }
                    } catch(e) {
                        alert('Lỗi kết nối máy chủ');
                    }
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
                    const msg = newStatus === 1 ? 'Mở khóa tài khoản này cho phép đăng nhập và mua hàng?' : 'Khóa tài khoản này? Người dùng sẽ không thể đăng nhập hoặc mua hàng.';
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
