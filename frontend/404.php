<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();
$tenWebsite = htmlspecialchars(defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, follow">
    <title>Không tìm thấy trang - <?= $tenWebsite ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0B0F19; }</style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 text-gray-100">
    <main class="w-full max-w-lg text-center">
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-8 sm:p-10 shadow-2xl">
            <div class="text-6xl font-black text-amber-400 mb-3">404</div>
            <h1 class="text-xl sm:text-2xl font-bold text-white mb-2">Không tìm thấy trang</h1>
            <p class="text-sm text-gray-400 leading-relaxed mb-7">Đường dẫn bạn truy cập không tồn tại hoặc đã được thay đổi. Hãy quay về trang chủ để tiếp tục.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="/" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300">
                    <i class="fas fa-house mr-1.5" aria-hidden="true"></i> Về trang chủ
                </a>
                <a href="/san-pham" class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 font-bold text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-300">
                    <i class="fas fa-box-open mr-1.5" aria-hidden="true"></i> Xem sản phẩm
                </a>
            </div>
        </div>
    </main>
</body>
</html>
