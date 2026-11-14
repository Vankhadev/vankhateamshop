<?php
// ========================================================
// TỰ ĐỘNG TẠO SITEMAP XML ĐỘNG THEO TÊN MIỀN MÁY CHỦ (VPS / HOSTING)
// vankhateamshop - Tự động nhận diện Domain thực tế của website khi đưa lên VPS
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/db.php';

// 1. Tự động nhận diện Domain hiện tại của website khi chạy trên VPS/Hosting
$protocol = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)) {
    $protocol = 'https';
}

$host = $_SERVER['HTTP_HOST'] ?? 'shopvankha.com';
$baseUrl = "{$protocol}://{$host}";

// Nếu trong file .env có cấu hình SITE_URL và không phải là localhost thì ưu tiên SITE_URL
if (defined('SITE_URL') && !empty(SITE_URL) && strpos(SITE_URL, '127.0.0.1') === false && strpos(SITE_URL, 'localhost') === false) {
    $baseUrl = rtrim(SITE_URL, '/');
}

// 2. Lấy danh sách sản phẩm từ cơ sở dữ liệu để thêm vào Sitemap
$danhSachSanPham = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT id, name, slug, updated_at, created_at FROM `products` WHERE `is_active` = 1 ORDER BY id DESC");
        $danhSachSanPham = $stmt->fetchAll();
    } catch (Exception $e) {
        $danhSachSanPham = [];
    }
}

$ngayHomNay = date('Y-m-d');

// 3. Xây dựng nội dung XML chuẩn Google Sitemap
ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

    <!-- Trang chủ -->
    <url>
        <loc><?= $baseUrl ?>/</loc>
        <lastmod><?= $ngayHomNay ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Danh mục sản phẩm & Bảng giá Model API -->
    <url>
        <loc><?= $baseUrl ?>/san-pham</loc>
        <lastmod><?= $ngayHomNay ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Chi tiết từng sản phẩm API -->
    <?php foreach ($danhSachSanPham as $sp): ?>
        <url>
            <loc><?= $baseUrl ?>/san-pham/<?= htmlspecialchars($sp['slug']) ?></loc>
            <lastmod><?= !empty($sp['updated_at']) ? date('Y-m-d', strtotime($sp['updated_at'])) : $ngayHomNay ?></lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>

    <!-- Tài liệu kết nối Buyer API -->
    <url>
        <loc><?= $baseUrl ?>/docs</loc>
        <lastmod><?= $ngayHomNay ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Trung tâm chính sách pháp lý -->
    <?php foreach (['dieu-khoan-su-dung', 'quy-che-hoat-dong', 'chinh-sach-thanh-toan', 'chinh-sach-cung-cap-san-pham', 'bao-hanh-hoan-tien', 'dieu-khoan-dai-ly', 'chinh-sach-bao-mat'] as $duongDanChinhSach): ?>
        <url>
            <loc><?= $baseUrl ?>/<?= $duongDanChinhSach ?></loc>
            <lastmod><?= $ngayHomNay ?></lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.7</priority>
        </url>
    <?php endforeach; ?>

    <!-- Trang Đăng nhập -->
    <url>
        <loc><?= $baseUrl ?>/login</loc>
        <lastmod><?= $ngayHomNay ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Trang Đăng ký -->
    <url>
        <loc><?= $baseUrl ?>/register</loc>
        <lastmod><?= $ngayHomNay ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Trang Quên mật khẩu -->
    <url>
        <loc><?= $baseUrl ?>/quen-mat-khau</loc>
        <lastmod><?= $ngayHomNay ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>

</urlset>
<?php
$xmlNoiDung = ob_get_clean();

// 4. Tự động cập nhật file vật lý sitemap.xml trên ổ đĩa
@file_put_contents(__DIR__ . '/sitemap.xml', $xmlNoiDung);

// 5. Xuất nội dung XML ra trình duyệt / Bot công cụ tìm kiếm
header('Content-Type: application/xml; charset=utf-8');
echo $xmlNoiDung;
