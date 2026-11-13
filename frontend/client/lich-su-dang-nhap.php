<?php
// ========================================================
// TRANG NHẬT KÝ ĐĂNG NHẬP & BẢO MẬT KHÁCH HÀNG
// vankhateamshop - Kiểm tra địa chỉ IP và hoạt động bảo mật
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
    header('Location: ../logout.php');
    exit;
}

// Lấy các bản ghi hoạt động liên quan đến user này hoặc IP hiện tại
$stmtLogs = $pdo->prepare("
    SELECT * FROM `system_logs`
    WHERE `message` LIKE ? OR `ip_address` = ?
    ORDER BY `id` DESC
    LIMIT 30
");
$emailLike = "%" . $user['email'] . "%";
$stmtLogs->execute([$emailLike, layIpNguoiDung()]);
$logs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhật Ký Bảo Mật - <?= SHOP_NAME ?></title>
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
                <a href="/user/ho-tro" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-headset mr-1"></i> Hỗ Trợ
                </a>
                <a href="/user/tai-khoan" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-user-gear mr-1"></i> Cài Đặt Tài Khoản
                </a>
                <a href="/user/lich-su-dang-nhap" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
                    <i class="fas fa-shield-halved mr-1"></i> Nhật Ký Bảo Mật
                </a>
            </nav>
        </div>
    </div>

    <!-- NỘI DUNG CHÍNH -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-white flex items-center gap-3">
                    <i class="fas fa-shield-halved text-blue-500"></i> Nhật Ký Hoạt Động & Bảo Mật
                </h1>
                <p class="text-sm text-gray-400 mt-1">Theo dõi các lượt đăng nhập, đổi mật khẩu và địa chỉ IP kết nối của bạn.</p>
            </div>
            <div class="bg-[#111827] border border-gray-800 px-4 py-2.5 rounded-xl text-xs">
                <span class="text-gray-400">IP hiện tại của bạn:</span>
                <span class="font-mono text-blue-400 font-bold ml-1"><?= htmlspecialchars(layIpNguoiDung()) ?></span>
            </div>
        </div>

        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                        <tr>
                            <th class="py-3 px-4">Thời Gian</th>
                            <th class="py-3 px-4">Hành Động</th>
                            <th class="py-3 px-4">Nội Dung Sự Kiện</th>
                            <th class="py-3 px-4">Địa Chỉ IP</th>
                            <th class="py-3 px-4 text-right">Mức Độ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-500 italic">
                                    <i class="fas fa-shield-check text-2xl block mb-2 text-gray-600"></i>
                                    Không có cảnh báo bảo mật bất thường nào. Tài khoản của bạn an toàn!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $lg): ?>
                                <tr>
                                    <td class="py-3 px-4 font-mono text-gray-400 whitespace-nowrap"><?= $lg['created_at'] ?></td>
                                    <td class="py-3 px-4 font-bold text-white"><?= htmlspecialchars($lg['action']) ?></td>
                                    <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($lg['message']) ?></td>
                                    <td class="py-3 px-4 font-mono text-blue-400 whitespace-nowrap"><?= htmlspecialchars($lg['ip_address'] ?? 'N/A') ?></td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $lg['level'] === 'ERROR' ? 'bg-red-500/10 text-red-400 border border-red-500/30' : ($lg['level'] === 'WARNING' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'bg-blue-500/10 text-blue-400 border border-blue-500/30') ?>">
                                            <?= $lg['level'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
