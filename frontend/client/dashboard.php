<?php
// ========================================================
// TRANG TỔNG QUAN TÀI KHOẢN KHÁCH HÀNG (CLIENT DASHBOARD)
// vankhateamshop - Quản lý số dư, danh sách API Key đã mua
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

// Áp dụng tiêu đề bảo mật
thietLapBaoMatHeaders();

// Bắt buộc phải đăng nhập (bằng Google hoặc Email)
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Lấy thông tin tài khoản hiện tại từ CSDL
$stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ../../backend/logout.php');
    exit;
}

// Lấy danh sách các đơn hàng đã mua của tài khoản này
$stmtOrders = $pdo->prepare("
    SELECT o.*, p.name AS product_name, p.request_count
    FROM `orders` o
    JOIN `products` p ON o.product_id = p.id
    WHERE (o.user_id = ? OR o.customer_email = ?)
    ORDER BY o.id DESC
    LIMIT 20
");
$stmtOrders->execute([$user['id'], $user['email']]);
$orders = $stmtOrders->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Điều Khiển Khách Hàng - <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
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
                <a href="/user/dashboard" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
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
                <a href="/user/ho-tro" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-headset mr-1"></i> Hỗ Trợ
                </a>
                <a href="/user/tai-khoan" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-user-gear mr-1"></i> Cài Đặt Tài Khoản
                </a>
            </nav>
        </div>
    </div>

    <!-- THÂN TRANG DASHBOARD -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <!-- Khối trên cùng: Chào mừng & Thẻ số dư ví -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <!-- Thẻ 1: Thông tin người dùng -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-xl">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" class="w-14 h-14 rounded-2xl object-cover border border-blue-500/50 shadow">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-xl font-bold">
                        <?= mb_substr($user['name'] ?? 'K', 0, 1) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <span class="text-xs text-gray-400 block font-medium">Xin chào,</span>
                    <h2 class="text-base font-bold text-white"><?= htmlspecialchars($user['name']) ?></h2>
                    <span class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($user['email']) ?></span>
                </div>
            </div>

            <!-- Thẻ 2: Số dư ví -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 flex items-center justify-between shadow-xl">
                <div>
                    <span class="text-xs text-gray-400 block font-medium">Số dư ví của bạn:</span>
                    <span class="text-2xl font-black text-green-400">
                        <?= number_format($user['balance'], 0, ',', '.') ?> VNĐ
                    </span>
                    <span class="text-[11px] text-gray-500 block mt-0.5">Dùng để mua nhanh không cần quét mã lại</span>
                </div>
                <a href="/user/nap-tien" class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition shadow-md shadow-blue-600/30 flex items-center gap-1.5">
                    <i class="fas fa-plus"></i> Nạp Tiền
                </a>
            </div>

            <!-- Thẻ 3: Tổng số key đang sở hữu -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 flex items-center justify-between shadow-xl">
                <div>
                    <span class="text-xs text-gray-400 block font-medium">Đơn hàng đã mua:</span>
                    <span class="text-2xl font-black text-amber-400"><?= count($orders) ?></span>
                    <span class="text-[11px] text-gray-500 block mt-0.5">Tài khoản & Key AI hoạt động</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gray-800 border border-gray-700 text-amber-400 flex items-center justify-center text-xl">
                    <i class="fas fa-key"></i>
                </div>
            </div>
        </div>

        <!-- BẢNG DANH SÁCH CÁC API KEY ĐÃ MUA -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-shield-halved text-blue-400"></i> Danh Sách API Key Của Bạn
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Dán Key này vào Cursor, NextChat hoặc công cụ lập trình của bạn</p>
                </div>
                <a href="../index.php#san-pham" class="text-xs font-bold text-blue-400 hover:text-blue-300">
                    + Mua Thêm Gói Mới
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Đơn / Ngày Mua</th>
                            <th class="py-3.5 px-4">Tên Gói Sản Phẩm</th>
                            <th class="py-3.5 px-4">Thông Số Gói</th>
                            <th class="py-3.5 px-4">Trạng Thái</th>
                            <th class="py-3.5 px-4">Key / Dữ Liệu Bàn Giao</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-500">
                                    Bạn chưa mua gói API nào. <a href="/" class="text-blue-400 underline">Khám phá bảng giá ngay</a>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $o): 
                            $delivered = json_decode($o['delivered_data'] ?? '[]', true);
                            $firstKey = is_array($delivered) && count($delivered) > 0 ? $delivered[0] : ($o['delivered_data'] ?? 'Đang xử lý...');
                        ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-bold text-blue-400"><?= htmlspecialchars($o['order_code']) ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4 font-bold text-white">
                                    <?= htmlspecialchars($o['product_name']) ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded bg-gray-800 border border-gray-700 font-mono text-[11px] text-amber-300">
                                        <?= htmlspecialchars($o['request_count'] ?? 'Tiêu chuẩn') ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if ($o['status'] === 'COMPLETED'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">
                                            Hoạt động
                                        </span>
                                    <?php elseif ($o['status'] === 'PENDING'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">
                                            Chờ tiền
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-950 text-blue-400 border border-blue-800">
                                            <?= htmlspecialchars($o['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-mono text-xs text-amber-300 max-w-xs truncate bg-gray-950 px-2 py-1 rounded border border-gray-800 select-all" title="<?= htmlspecialchars($firstKey) ?>">
                                        <?= htmlspecialchars($firstKey) ?>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <button onclick="saoChepKey('<?= addslashes($firstKey) ?>')" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold transition border border-gray-700">
                                        <i class="fas fa-copy mr-1 text-blue-400"></i> Copy
                                    </button>
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
        function saoChepKey(noiDung) {
            navigator.clipboard.writeText(noiDung).then(() => {
                alert('Đã sao chép API Key vào bộ nhớ tạm!');
            });
        }
    </script>

</body>
</html>
