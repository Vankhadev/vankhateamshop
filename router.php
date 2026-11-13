<?php
// ========================================================
// ROUTER DÀNH CHO PHP BUILT-IN SERVER (LOCAL TESTING)
// vankhateamshop - Chạy lệnh: php -S 127.0.0.1:8000 router.php
// Cho phép kiểm tra URL đẹp như shopvankha.com/user/dashboard tại máy cá nhân
// Tự động chặn và chuyển hướng các đường link cũ .php sang URL chuẩn đẹp
// ========================================================

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// 1. Phục vụ trực tiếp CÁC TỆP TĨNH (CSS, JS, Hình ảnh, Font chữ, Robots, Sitemap)
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$cacLoaiTepTinh = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'txt', 'xml', 'json'];

if (in_array($ext, $cacLoaiTepTinh) && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Trả về false để PHP built-in server tự phục vụ file tĩnh
}

// 2. Chuyển toàn bộ yêu cầu còn lại vào file index.php điều hướng trung tâm
require __DIR__ . '/index.php';
