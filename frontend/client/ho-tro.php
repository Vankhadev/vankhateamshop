<?php
// ========================================================
// TRANG TRUNG TÂM HỖ TRỢ & BẢO HÀNH (CLIENT TICKETS)
// vankhateamshop - Gửi yêu cầu đổi Key lỗi, bảo hành 1-1, trao đổi trực tiếp với Admin
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';
require_once __DIR__ . '/../../backend/telegram.php';

thietLapBaoMatHeaders();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ../../backend/logout.php');
    exit;
}

$thongBao = '';
$loaiThongBao = '';

// 1. Xử lý tạo vé hỗ trợ mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'tao_ve_ho_tro') {
        $title = trim($_POST['title'] ?? '');
        $orderCode = trim($_POST['order_code'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['LOW', 'MEDIUM', 'HIGH']) ? $_POST['priority'] : 'MEDIUM';

        if (empty($title) || empty($content)) {
            $thongBao = 'Vui lòng nhập tiêu đề và nội dung cần hỗ trợ!';
            $loaiThongBao = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $stmtTicket = $pdo->prepare("
                    INSERT INTO `tickets` (`user_id`, `order_code`, `title`, `status`, `priority`)
                    VALUES (?, ?, ?, 'OPEN', ?)
                ");
                $stmtTicket->execute([$user['id'], $orderCode, $title, $priority]);
                $ticketId = $pdo->lastInsertId();

                $stmtReply = $pdo->prepare("
                    INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `is_admin`, `message`)
                    VALUES (?, ?, 0, ?)
                ");
                $stmtReply->execute([$ticketId, $user['id'], $content]);
                $pdo->commit();

                // Bắn thông báo Telegram cho Admin ngay lập tức!
                $tbTelegram = "🎫 <b>VÉ HỖ TRỢ MỚI #{$ticketId}</b>\n"
                            . "👤 Khách: <b>{$user['name']}</b> ({$user['email']})\n"
                            . "📌 Tiêu đề: <b>{$title}</b>\n"
                            . "📦 Mã đơn: <code>" . ($orderCode ?: 'Hỏi chung') . "</code>\n"
                            . "💬 Nội dung: <i>{$content}</i>";
                guiThongBaoTelegram($tbTelegram, 'support');

                $thongBao = 'Đã gửi yêu cầu hỗ trợ thành công! Kỹ thuật viên sẽ kiểm tra và phản hồi sớm nhất.';
                $loaiThongBao = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $thongBao = 'Lỗi tạo vé hỗ trợ: ' . $e->getMessage();
                $loaiThongBao = 'error';
            }
        }
    } elseif ($action === 'tra_loi_ve') {
        $ticketId = (int)$_POST['ticket_id'];
        $message = trim($_POST['message'] ?? '');

        if (!empty($message)) {
            // Kiểm tra vé có thuộc về user này không
            $chk = $pdo->prepare("SELECT id FROM `tickets` WHERE `id` = ? AND `user_id` = ?");
            $chk->execute([$ticketId, $user['id']]);
            if ($chk->fetch()) {
                $pdo->prepare("INSERT INTO `ticket_replies` (`ticket_id`, `user_id`, `is_admin`, `message`) VALUES (?, ?, 0, ?)")->execute([$ticketId, $user['id'], $message]);
                $pdo->prepare("UPDATE `tickets` SET `status` = 'OPEN', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?")->execute([$ticketId]);
                $thongBao = 'Đã gửi phản hồi thành công!';
                $loaiThongBao = 'success';
            }
        }
    }
}

// 2. Lấy danh sách các đơn hàng đã mua của khách để chọn bảo hành
$userOrders = $pdo->prepare("SELECT `order_code`, `total_amount` FROM `orders` WHERE `user_id` = ? OR `customer_email` = ? ORDER BY `id` DESC LIMIT 20");
$userOrders->execute([$user['id'], $user['email']]);
$danhSachDon = $userOrders->fetchAll();

// 3. Lấy danh sách vé hỗ trợ của khách
$stmtList = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM `ticket_replies` r WHERE r.ticket_id = t.id) AS reply_count
    FROM `tickets` t 
    WHERE t.user_id = ? 
    ORDER BY t.id DESC
");
$stmtList->execute([$user['id']]);
$tickets = $stmtList->fetchAll();

// 4. Nếu đang xem chi tiết một vé cụ thể
$ticketChiTiet = null;
$replies = [];
$xemId = (int)($_GET['id'] ?? 0);
if ($xemId > 0) {
    $stmtC = $pdo->prepare("SELECT * FROM `tickets` WHERE `id` = ? AND `user_id` = ?");
    $stmtC->execute([$xemId, $user['id']]);
    $ticketChiTiet = $stmtC->fetch();

    if ($ticketChiTiet) {
        $stmtR = $pdo->prepare("
            SELECT r.*, u.name AS user_name, u.avatar 
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
    <title>Trung Tâm Hỗ Trợ & Bảo Hành - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="{ moModalTao: false }" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- THANH MENU TAB QUẢN TRỊ TÀI KHOẢN KHÁCH HÀNG -->
    <div class="bg-[#111827] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-6 text-xs font-semibold overflow-x-auto py-3">
                <a href="/user/dashboard" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-chart-pie mr-1"></i> Tổng Quan
                </a>
                <a href="/user/don-hang" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-box-open mr-1"></i> Đơn Hàng Của Tôi
                </a>
                <a href="/user/nap-tien" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-wallet mr-1 text-green-400"></i> Nạp Tiền Ví
                </a>
                <a href="/user/api-docs" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-code mr-1"></i> Kết Nối API & Cursor
                </a>
                <a href="/user/bien-dong-so-du" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-clock-rotate-left mr-1"></i> Biến Động Số Dư
                </a>
                <a href="/user/ho-tro" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
                    <i class="fas fa-headset mr-1"></i> Hỗ Trợ
                </a>
                <a href="/user/tai-khoan" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-user-gear mr-1"></i> Cài Đặt Tài Khoản
                </a>
            </nav>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-headset text-blue-500"></i> Trung Tâm Khiếu Nại & Bảo Hành 1 Đổi 1
                </h1>
                <p class="text-xs text-gray-400 mt-1">Gửi yêu cầu đổi Key mới, báo cáo lỗi kết nối, kỹ thuật viên phản hồi trong 5-15 phút</p>
            </div>
            
            <button @click="moModalTao = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Tạo Yêu Cầu Hỗ Trợ Mới
            </button>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> mr-1.5"></i>
                <?= htmlspecialchars($thongBao) ?>
            </div>
        <?php endif; ?>

        <?php if ($ticketChiTiet): ?>
            <!-- KHUNG CHI TIẾT VÉ VÀ TRAO ĐỔI VỚI ADMIN -->
            <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-6 mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-800 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $ticketChiTiet['status'] === 'OPEN' ? 'bg-amber-950 text-amber-400 border border-amber-800' : ($ticketChiTiet['status'] === 'ANSWERED' ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-gray-800 text-gray-400') ?>">
                                <?= $ticketChiTiet['status'] === 'OPEN' ? 'Chờ hỗ trợ' : ($ticketChiTiet['status'] === 'ANSWERED' ? 'Đã phản hồi' : 'Đã đóng') ?>
                            </span>
                            <span class="text-xs text-gray-500 font-mono">Vé #<?= $ticketChiTiet['id'] ?></span>
                        </div>
                        <h2 class="text-lg font-bold text-white mt-1"><?= htmlspecialchars($ticketChiTiet['title']) ?></h2>
                        <?php if (!empty($ticketChiTiet['order_code'])): ?>
                            <span class="text-xs text-blue-400 font-mono">Mã đơn liên quan: <?= htmlspecialchars($ticketChiTiet['order_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="/user/ho-tro" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold self-start sm:self-auto transition">
                        <i class="fas fa-arrow-left mr-1"></i> Quay Lại Danh Sách
                    </a>
                </div>

                <!-- DÒNG THỜI GIAN TIN NHẮN (THREAD) -->
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    <?php foreach ($replies as $r): ?>
                        <div class="flex items-start gap-3 <?= $r['is_admin'] ? 'bg-blue-950/30 border border-blue-800/50' : 'bg-gray-950 border border-gray-800' ?> p-4 rounded-2xl">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0 <?= $r['is_admin'] ? 'bg-amber-600 text-white' : 'bg-blue-600 text-white' ?>">
                                <?= $r['is_admin'] ? 'AD' : mb_substr($r['user_name'], 0, 1) ?>
                            </div>
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-xs <?= $r['is_admin'] ? 'text-amber-400' : 'text-white' ?>">
                                        <?= $r['is_admin'] ? 'Hỗ Trợ Viên (Admin)' : htmlspecialchars($r['user_name']) ?>
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

                <!-- FORM PHẢN HỒI THÊM -->
                <?php if ($ticketChiTiet['status'] !== 'CLOSED'): ?>
                    <form method="POST" action="" class="space-y-3 border-t border-gray-800 pt-4">
                        <input type="hidden" name="action" value="tra_loi_ve">
                        <input type="hidden" name="ticket_id" value="<?= $ticketChiTiet['id'] ?>">
                        <label class="block text-xs font-semibold text-gray-300">Gửi thêm phản hồi hoặc thông tin bổ sung:</label>
                        <textarea name="message" required rows="3" placeholder="Nhập nội dung phản hồi của bạn..." class="w-full bg-gray-950 border border-gray-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-blue-500"></textarea>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                            <i class="fas fa-paper-plane"></i> Gửi Tin Nhắn
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- DANH SÁCH CÁC VÉ HỖ TRỢ ĐÃ GỬI -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                <span class="text-xs font-bold text-white uppercase tracking-wider">Danh Sách Yêu Cầu Bảo Hành Đã Gửi</span>
                <span class="text-xs text-gray-400">Tổng cộng: <?= count($tickets) ?> vé</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Vé / Thời Gian</th>
                            <th class="py-3.5 px-4">Tiêu Đề Yêu Cầu</th>
                            <th class="py-3.5 px-4">Mã Đơn Bảo Hành</th>
                            <th class="py-3.5 px-4 text-center">Mức Ưu Tiên</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-headset text-3xl mb-2 block text-gray-600"></i>
                                    Bạn chưa có yêu cầu hỗ trợ nào. Mọi gói API đều hoạt động ổn định!
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($tickets as $t): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <span class="font-mono font-bold text-blue-400">#<?= $t['id'] ?></span>
                                    <div class="text-[10px] text-gray-500 mt-0.5"><?= date('H:i - d/m/Y', strtotime($t['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <a href="/user/ho-tro?id=<?= $t['id'] ?>" class="font-bold text-white hover:text-blue-400 transition">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </a>
                                    <span class="text-[10px] text-gray-400 block mt-0.5"><?= $t['reply_count'] ?> tin nhắn trao đổi</span>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-gray-300">
                                    <?= htmlspecialchars($t['order_code'] ?: 'Hỏi chung') ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $t['priority'] === 'HIGH' ? 'bg-red-950 text-red-400' : 'bg-gray-800 text-gray-400' ?>">
                                        <?= $t['priority'] === 'HIGH' ? 'Khẩn cấp' : 'Bình thường' ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $t['status'] === 'OPEN' ? 'bg-amber-950 text-amber-400 border border-amber-800' : ($t['status'] === 'ANSWERED' ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-gray-800 text-gray-400') ?>">
                                        <?= $t['status'] === 'OPEN' ? 'Chờ hỗ trợ' : ($t['status'] === 'ANSWERED' ? 'Đã phản hồi' : 'Đã đóng') ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <a href="/user/ho-tro?id=<?= $t['id'] ?>" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-blue-600 hover:text-white text-gray-300 text-xs font-bold transition border border-gray-700">
                                        Xem Chi Tiết
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL TẠO VÉ HỖ TRỢ MỚI -->
        <div x-show="moModalTao" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
            <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-lg w-full p-6 relative shadow-2xl">
                <button @click="moModalTao = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>

                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <i class="fas fa-headset text-blue-500"></i> Gửi Yêu Cầu Hỗ Trợ / Bảo Hành
                </h3>
                <p class="text-xs text-gray-400 mb-4">Chúng tôi sẽ kiểm tra và đổi Key mới cho bạn nếu Key gặp lỗi</p>

                <form method="POST" action="" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="tao_ve_ho_tro">

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Mã đơn hàng cần bảo hành:</label>
                        <select name="order_code" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-blue-500">
                            <option value="">-- Không chọn (Hỏi đáp chung) --</option>
                            <?php foreach ($danhSachDon as $od): ?>
                                <option value="<?= htmlspecialchars($od['order_code']) ?>">Đơn <?= htmlspecialchars($od['order_code']) ?> (<?= number_format($od['total_amount'], 0, ',', '.') ?>đ)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Tiêu đề yêu cầu (Vắn tắt vấn đề):</label>
                        <input type="text" name="title" required placeholder="Ví dụ: Key Cursor báo lỗi 401, xin đổi key mới..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Mức độ ưu tiên:</label>
                        <select name="priority" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="MEDIUM">Bình thường</option>
                            <option value="HIGH">Khẩn cấp (Key bị ngắt giữa giờ làm việc)</option>
                            <option value="LOW">Thắc mắc hướng dẫn</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-semibold mb-1">Mô tả chi tiết vấn đề:</label>
                        <textarea name="content" required rows="4" placeholder="Mô tả chi tiết lỗi bạn gặp phải hoặc dán hình ảnh/thông báo lỗi..." class="w-full bg-gray-950 border border-gray-700 rounded-xl p-3 text-white focus:outline-none focus:border-blue-500"></textarea>
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-1.5">
                        <i class="fas fa-paper-plane"></i> Gửi Yêu Cầu Cho Kỹ Thuật Viên
                    </button>
                </form>
            </div>
        </div>

    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
