<?php
// ========================================================
// TRANG QUẢN LÝ ĐƠN HÀNG CỦA KHÁCH (CLIENT ORDERS)
// vankhateamshop - Xem lịch sử mua, sao chép Key, tải file .txt
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

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

// Lấy toàn bộ đơn hàng của người dùng
$sql = "
    SELECT o.*, p.name AS product_name, p.sku AS product_sku, p.request_count
    FROM `orders` o
    LEFT JOIN `products` p ON o.product_id = p.id
    WHERE (o.user_id = ? OR o.customer_email = ?)
    ORDER BY o.id DESC
";
$stmtOrders = $pdo->prepare($sql);
$stmtOrders->execute([$user['id'], $user['email']]);
$orders = $stmtOrders->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- THANH MENU TAB QUẢN TRỊ TÀI KHOẢN KHÁCH HÀNG -->
    <div class="bg-[#111827] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-6 text-xs font-semibold overflow-x-auto py-3">
                <a href="/user/dashboard" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-chart-pie mr-1"></i> Tổng Quan
                </a>
                <a href="/user/don-hang" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
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
                <a href="/user/ho-tro" class="text-gray-400 hover:text-white shrink-0">
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
                    <i class="fas fa-box-open text-blue-500"></i> Lịch Sử Đơn Hàng Của Bạn
                </h1>
                <p class="text-xs text-gray-400 mt-1">Toàn bộ các gói API và tài khoản bạn đã mua tại <?= SHOP_NAME ?></p>
            </div>
            <a href="../index.php#san-pham" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Mua Thêm Gói Mới
            </a>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Đơn / Ngày Mua</th>
                            <th class="py-3.5 px-4">Tên Gói</th>
                            <th class="py-3.5 px-4">Số Tiền</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4">Key / Tài Khoản Đã Nhận</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-box-open text-3xl mb-2 block text-gray-600"></i>
                                    Bạn chưa có đơn hàng nào. <a href="/" class="text-blue-400 underline font-bold">Khám phá bảng giá ngay</a>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $order): 
                            $delivered = json_decode($order['delivered_data'] ?? '[]', true);
                            $keyText = is_array($delivered) ? implode("\n", $delivered) : ($order['delivered_data'] ?? '');
                        ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-bold text-blue-400"><?= htmlspecialchars($order['order_code']) ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?= date('H:i - d/m/Y', strtotime($order['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($order['product_name'] ?? 'Gói cước') ?></div>
                                    <?php if (!empty($order['request_count'])): ?>
                                        <span class="text-[11px] text-amber-400 font-mono"><?= htmlspecialchars($order['request_count']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 font-bold text-green-400">
                                    <?= number_format($order['total_amount'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($order['status'] === 'COMPLETED'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">Hoạt động</span>
                                    <?php elseif ($order['status'] === 'PENDING'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">Chờ tiền</span>
                                    <?php elseif ($order['status'] === 'REFUNDED'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-950 text-purple-400 border border-purple-800">Đã hoàn tiền</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-950 text-red-400 border border-red-800"><?= htmlspecialchars($order['status']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if (!empty($keyText)): ?>
                                        <div class="max-w-xs truncate font-mono text-xs text-amber-300 bg-gray-950 px-2.5 py-1 rounded border border-gray-800 select-all" title="<?= htmlspecialchars($keyText) ?>">
                                            <?= htmlspecialchars($keyText) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-[11px]">Chưa giao key</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if (!empty($keyText)): ?>
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button onclick="saoChep('<?= addslashes($keyText) ?>')" class="px-2.5 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold transition border border-gray-700" title="Sao chép Key">
                                                <i class="fas fa-copy text-blue-400"></i> Copy
                                            </button>
                                            <button onclick="taiFileTxt('<?= $order['order_code'] ?>', '<?= addslashes($keyText) ?>')" class="px-2.5 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold transition border border-gray-700" title="Tải file .txt">
                                                <i class="fas fa-download text-green-400"></i> .TXT
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-600">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function saoChep(noiDung) {
            navigator.clipboard.writeText(noiDung).then(() => {
                alert('Đã sao chép vào bộ nhớ tạm!');
            });
        }

        function taiFileTxt(maDon, noiDung) {
            const blob = new Blob([noiDung], { type: 'text/plain;charset=utf-8' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Key_${maDon}.txt`;
            link.click();
        }
    </script>
</body>
</html>
