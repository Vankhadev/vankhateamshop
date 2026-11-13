<?php
// ========================================================
// TỰ ĐỘNG TẠO ROBOTS.TXT ĐỘNG THEO TÊN MIỀN MÁY CHỦ (VPS / HOSTING)
// vankhateamshop - Tự động nhận diện Domain thực tế của website khi đưa lên VPS
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/backend/config.php';

$protocol = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)) {
    $protocol = 'https';
}

$host = $_SERVER['HTTP_HOST'] ?? 'shopvankha.com';
$baseUrl = "{$protocol}://{$host}";

if (defined('SITE_URL') && !empty(SITE_URL) && strpos(SITE_URL, '127.0.0.1') === false && strpos(SITE_URL, 'localhost') === false) {
    $baseUrl = rtrim(SITE_URL, '/');
}

$robotsContent = <<<TXT
# ========================================================
# TỆP ĐIỀU KHIỂN CÔNG CỤ TÌM KIẾM (ROBOTS.TXT)
# Tự động đồng bộ theo tên miền máy chủ: {$baseUrl}
# ========================================================

User-agent: *
Allow: /
Allow: /san-pham
Allow: /docs
Allow: /chinh-sach
Allow: /login
Allow: /register
Allow: /quen-mat-khau

# Chặn thu thập dữ liệu các khu vực quản trị & API nhạy cảm
Disallow: /admin/
Disallow: /user/
Disallow: /backend/
Disallow: /api/
Disallow: /*.sql$
Disallow: /*.env$

# Đường dẫn tệp Sơ đồ trang web (Sitemap)
Sitemap: {$baseUrl}/sitemap.xml
TXT;

// Tự động cập nhật file vật lý robots.txt trên ổ đĩa
@file_put_contents(__DIR__ . '/robots.txt', $robotsContent);

header('Content-Type: text/plain; charset=utf-8');
echo $robotsContent;
