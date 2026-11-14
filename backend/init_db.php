<?php
// Không cho phép chạy công cụ khởi tạo CSDL qua web sau khi triển khai.
// Chỉ cho phép chạy thủ công từ CLI trong môi trường quản trị.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}

// ========================================================
// CÔNG CỤ TỰ ĐỘNG KHỞI TẠO CƠ SỞ DỮ LIỆU (DATABASE INITIALIZER)
// vankhateamshop - 1 Click tự động tạo database & nạp dữ liệu từ vankhateamshop.sql
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';

$thongBao = '';
$daThanhCong = false;

if (isset($_POST['khoi_tao'])) {
    try {
        // 1. Kết nối tới MySQL Server (chưa chọn database để tạo database trước nếu chưa có)
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 2. Tạo Database nếu chưa tồn tại
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // 3. Đọc nội dung file vankhateamshop.sql
        $sqlFile = __DIR__ . '/../vankhateamshop.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Không tìm thấy tệp vankhateamshop.sql tại thư mục gốc!");
        }

        $sqlContent = file_get_contents($sqlFile);

        // 4. Thực thi từng câu lệnh SQL
        $pdo->exec($sqlContent);

        $daThanhCong = true;
        $thongBao = "Khởi tạo Cơ Sở Dữ Liệu '" . DB_NAME . "' và nạp toàn bộ dữ liệu thành công 100%!";
    } catch (Exception $e) {
        $thongBao = "Lỗi khởi tạo: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khởi Tạo CSDL Tự Động - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 antialiased">
    <div class="max-w-md w-full bg-gray-900 border border-gray-800 rounded-3xl p-8 shadow-2xl text-center">
        <div class="w-14 h-14 rounded-2xl bg-amber-600/20 text-amber-500 flex items-center justify-center text-2xl mx-auto mb-4 border border-amber-600/40">
            <i class="fas fa-database"></i>
        </div>
        <h1 class="text-xl font-bold text-white mb-1">Khởi Tạo Cơ Sở Dữ Liệu</h1>
        <p class="text-xs text-gray-400 mb-6">Tự động kết nối MySQL và nhập bảng từ tệp <code class="text-amber-400">vankhateamshop.sql</code></p>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 text-xs font-bold <?= $daThanhCong ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                <i class="fas <?= $daThanhCong ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> mr-1.5"></i>
                <?= htmlspecialchars($thongBao) ?>
            </div>
        <?php endif; ?>

        <?php if ($daThanhCong): ?>
            <div class="space-y-3">
                <a href="../frontend/index.php" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition block shadow-lg shadow-blue-600/30">
                    <i class="fas fa-store mr-1.5"></i> Vào Trang Chủ Bán Hàng
                </a>
                <a href="../frontend/login.php" class="w-full py-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition block">
                    <i class="fas fa-right-to-bracket mr-1.5"></i> Đến Trang Đăng Nhập
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <button type="submit" name="khoi_tao" value="1" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs transition duration-200 shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                    <i class="fas fa-bolt"></i> Bấm Để Bắt Đầu Khởi Tạo Tự Động
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-6 text-[11px] text-gray-500">
            Cấu hình hiện tại: Host <code class="text-gray-400"><?= DB_HOST ?></code> | CSDL <code class="text-gray-400"><?= DB_NAME ?></code>
        </div>
    </div>
</body>
</html>
