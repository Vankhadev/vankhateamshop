<?php
// ========================================================
// TRANG QUẢN LÝ VÉ HỖ TRỢ & BẢO HÀNH (ADMIN TICKETS)
// vankhateamshop - Trả lời khiếu nại khách hàng, cấp lại Key mới, đóng vé
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

// Xử lý gửi phản hồi từ Admin hoặc Đóng vé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_tra_loi') {
        $ticketId = (int)$_POST['ticket_id'];
        $message = trim($_POST['message'] ?? '');

        if (!empty($message)) {
            $pdo->prepare("INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `is_admin`, `message`) VALUES (?, ?, 1, ?)")->execute([$ticketId, $_SESSION['user_id'], $message]);
            $pdo->prepare("UPDATE `tickets` SET `status` = 'ANSWERED', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?")->execute([$ticketId]);
            $thongBao = 'Đã gửi câu trả lời cho khách hàng thành công!';
            $loaiThongBao = 'success';
        }
    } elseif ($action === 'dong_ve') {
        $ticketId = (int)$_POST['ticket_id'];
        $pdo->prepare("UPDATE `tickets` SET `status` = 'CLOSED', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?")->execute([$ticketId]);
        $thongBao = 'Đã đóng vé hỗ trợ # ' . $ticketId;
        $loaiThongBao = 'success';
    }
}

$statusFilter = trim($_GET['status'] ?? '');
$sql = "
    SELECT t.*, u.name AS user_name, u.email AS user_email,
           (SELECT COUNT(*) FROM `ticket_replies` r WHERE r.ticket_id = t.id) AS reply_count
    FROM `tickets` t
    JOIN `users` u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];
if (!empty($statusFilter)) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY t.id DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Nếu xem chi tiết một vé cụ thể
$ticketChiTiet = null;
$replies = [];
$xemId = (int)($_GET['id'] ?? 0);
if ($xemId > 0) {
    $stmtC = $pdo->prepare("
        SELECT t.*, u.name AS user_name, u.email AS user_email 
        FROM `tickets` t 
        JOIN `users` u ON t.user_id = u.id 
        WHERE t.id = ?
    ");
    $stmtC->execute([$xemId]);
    $ticketChiTiet = $stmtC->fetch();

    if ($ticketChiTiet) {
        $stmtR = $pdo->prepare("
            SELECT r.*, u.name AS user_name 
            FROM `ticket_replies` r
            JOIN `users` u ON r.user_id = u.id
            WHERE r.ticket_id = ? 
            ORDER BY r.id ASC
        ");
        $stmtR->execute([$xemId]);
        $replies = $stmtR->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Vé Hỗ Trợ - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-headset text-amber-500"></i>
                        <span>Hỗ Trợ & Khiếu Nại Khách Hàng</span>
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
                    <i class="fas fa-headset text-amber-500"></i> Quản Lý Yêu Cầu Hỗ Trợ & Khiếu Nại
                </h1>
                <p class="text-xs text-gray-400 mt-1">Xử lý khiếu nại key lỗi, đổi key mới hoặc trả lời thắc mắc của khách hàng</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="/admin/ho-tro" class="px-3 py-1.5 rounded-xl bg-gray-800 text-gray-300 text-xs font-bold <?= empty($statusFilter) ? 'border border-amber-500 text-amber-400' : '' ?>">Tất cả</a>
                <a href="ho-tro.php?status=OPEN" class="px-3 py-1.5 rounded-xl bg-gray-800 text-gray-300 text-xs font-bold <?= $statusFilter === 'OPEN' ? 'border border-amber-500 text-amber-400' : '' ?>">Chờ xử lý</a>
                <a href="ho-tro.php?status=ANSWERED" class="px-3 py-1.5 rounded-xl bg-gray-800 text-gray-300 text-xs font-bold <?= $statusFilter === 'ANSWERED' ? 'border border-green-500 text-green-400' : '' ?>">Đã trả lời</a>
            </div>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> mr-1.5"></i>
                <?= htmlspecialchars($thongBao) ?>
            </div>
        <?php endif; ?>

        <?php if ($ticketChiTiet): ?>
            <!-- KHUNG TRẢ LỜI VÉ CHI TIẾT -->
            <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-6 mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-800 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $ticketChiTiet['status'] === 'OPEN' ? 'bg-amber-950 text-amber-400 border border-amber-800' : 'bg-green-950 text-green-400 border border-green-800' ?>">
                                <?= $ticketChiTiet['status'] ?>
                            </span>
                            <span class="text-xs text-gray-500 font-mono">Vé #<?= $ticketChiTiet['id'] ?></span>
                            <span class="text-xs text-gray-400">Khách: <strong><?= htmlspecialchars($ticketChiTiet['user_name']) ?></strong> (<?= htmlspecialchars($ticketChiTiet['user_email']) ?>)</span>
                        </div>
                        <h2 class="text-base font-bold text-white mt-1"><?= htmlspecialchars($ticketChiTiet['title']) ?></h2>
                        <?php if (!empty($ticketChiTiet['order_code'])): ?>
                            <span class="text-xs text-blue-400 font-mono">Mã đơn liên quan: <?= htmlspecialchars($ticketChiTiet['order_code']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php if ($ticketChiTiet['status'] !== 'CLOSED'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Đóng vé hỗ trợ này?');">
                                <input type="hidden" name="action" value="dong_ve">
                                <input type="hidden" name="ticket_id" value="<?= $ticketChiTiet['id'] ?>">
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white text-xs font-bold transition">
                                    <i class="fas fa-lock mr-1"></i> Đóng Vé
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="/admin/ho-tro" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold transition">
                            <i class="fas fa-arrow-left mr-1"></i> Danh Sách
                        </a>
                    </div>
                </div>

                <!-- DÒNG THỜI GIAN TIN NHẮN -->
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    <?php foreach ($replies as $r): ?>
                        <div class="flex items-start gap-3 <?= $r['is_admin'] ? 'bg-amber-950/20 border border-amber-800/40' : 'bg-gray-950 border border-gray-800' ?> p-4 rounded-2xl">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0 <?= $r['is_admin'] ? 'bg-amber-600 text-white' : 'bg-blue-600 text-white' ?>">
                                <?= $r['is_admin'] ? 'AD' : mb_substr($r['user_name'], 0, 1) ?>
                            </div>
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-xs <?= $r['is_admin'] ? 'text-amber-400' : 'text-blue-400' ?>">
                                        <?= $r['is_admin'] ? 'Admin Phản Hồi' : htmlspecialchars($r['user_name']) ?>
                                    </span>
                                    <span class="text-[10px] text-gray-500"><?= date('H:i - d/m/Y', strtotime($r['created_at'])) ?></span>
                                </div>
                                <div class="text-xs text-gray-300 whitespace-pre-wrap leading-relaxed select-all">
                                    <?= htmlspecialchars($r['message']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- FORM PHẢN HỒI CỦA ADMIN -->
                <form method="POST" action="" class="space-y-3 border-t border-gray-800 pt-4">
                    <input type="hidden" name="action" value="admin_tra_loi">
                    <input type="hidden" name="ticket_id" value="<?= $ticketChiTiet['id'] ?>">
                    <label class="block text-xs font-semibold text-gray-300">Viết nội dung phản hồi / Gửi Key thay thế cho khách:</label>
                    <textarea name="message" required rows="3" placeholder="Nhập câu trả lời hoặc dán mã Key đổi mới cho khách tại đây..." class="w-full bg-gray-950 border border-gray-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-amber-500"></textarea>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-amber-600/30">
                        <i class="fas fa-paper-plane"></i> Gửi Câu Trả Lời Cho Khách
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- BẢNG TẤT CẢ CÁC VÉ HỖ TRỢ -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Vé / Thời Gian</th>
                            <th class="py-3.5 px-4">Khách Hàng</th>
                            <th class="py-3.5 px-4">Tiêu Đề Yêu Cầu</th>
                            <th class="py-3.5 px-4">Mã Đơn</th>
                            <th class="py-3.5 px-4 text-center">Ưu Tiên</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-gray-500">Chưa có vé khiếu nại hoặc hỗ trợ nào.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($tickets as $t): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <span class="font-mono font-bold text-amber-400">#<?= $t['id'] ?></span>
                                    <div class="text-[10px] text-gray-500 mt-0.5"><?= date('H:i d/m', strtotime($t['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($t['user_name']) ?></div>
                                    <div class="text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($t['user_email']) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <a href="ho-tro.php?id=<?= $t['id'] ?>" class="font-bold text-white hover:text-amber-400 transition">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </a>
                                    <span class="text-[10px] text-gray-500 block"><?= $t['reply_count'] ?> tin nhắn</span>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-blue-400">
                                    <?= htmlspecialchars($t['order_code'] ?: '-') ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $t['priority'] === 'HIGH' ? 'bg-red-950 text-red-400' : 'bg-gray-800 text-gray-400' ?>">
                                        <?= $t['priority'] ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $t['status'] === 'OPEN' ? 'bg-amber-950 text-amber-400 border border-amber-800' : ($t['status'] === 'ANSWERED' ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-gray-800 text-gray-400') ?>">
                                        <?= $t['status'] === 'OPEN' ? 'Chờ xử lý' : ($t['status'] === 'ANSWERED' ? 'Đã trả lời' : 'Đã đóng') ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <a href="ho-tro.php?id=<?= $t['id'] ?>" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition shadow-md shadow-amber-600/30">
                                        Xử Lý Ngay
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
    </body>
</html>
