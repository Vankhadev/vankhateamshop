<?php
// ========================================================
// API ĐỐI TÁC: LẤY DANH SÁCH SẢN PHẨM & GIÁ ĐẠI LÝ
// vankhateamshop - GET /api/partner/v1/products.php
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/config.php';
require_once __DIR__ . '/../../../backend/db.php';
require_once __DIR__ . '/../../../backend/security.php';

$thoiDiemBatDau = microtime(true);
$ipKhach = layIpNguoiDung();
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 1 : 0;

// 1. Kiểm tra phương thức HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errorCode' => 'METHOD_NOT_ALLOWED', 'detail' => 'Chỉ hỗ trợ phương thức GET']);
    exit;
}

// 2. Xác thực API Key đại lý từ Header
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $apiKey = $matches[1];
    }
}

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'errorCode' => 'MISSING_API_KEY', 'detail' => 'Thiếu header X-API-Key hoặc Authorization: Bearer']);
    exit;
}

// 3. Tìm đại lý theo reseller_key trong CSDL
$stmt = $pdo->prepare("SELECT * FROM `users` WHERE `reseller_key` = ? AND `is_reseller` = 1 AND `is_active` = 1 LIMIT 1");
$stmt->execute([$apiKey]);
$reseller = $stmt->fetch();

if (!$reseller) {
    http_response_code(401);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_API_KEY', 'detail' => 'Key sai hoặc đã bị thu hồi']);
    exit;
}

$chietKhauPhanTram = (float)$reseller['reseller_discount']; // vd: 5.0%

// 4. Lấy danh sách sản phẩm đang bán
$stmtSp = $pdo->query("
    SELECT p.*, c.slug AS cat_slug
    FROM `products` p
    JOIN `categories` c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.id ASC
");
$dsSanPham = $stmtSp->fetchAll();

$danhSachTraVe = [];
foreach ($dsSanPham as $sp) {
    // Giá đại lý = Giá bán lẻ trừ đi % chiết khấu của đại lý (Ràng buộc sàn giá nhập không bán lỗ)
    $giaInfo = tinhGiaDaiLyChoSanPham($pdo, $sp, $reseller);
    $giaDaiLy = (float)$giaInfo['price'];

    $danhSachTraVe[] = [
        'product_id'        => $sp['sku'],
        'name'              => $sp['name'],
        'description'       => $sp['description'] ?? '',
        'type'              => $sp['cat_slug'],
        'walletCurrency'    => 'VND',
        'walletPricing'     => (int)$giaDaiLy,
        'walletPricingText' => number_format($giaDaiLy, 0, ',', '.') . ' VND',
        'available'         => 999
    ];
}

// Ghi log giám sát
$doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
$logStmt = $pdo->prepare("
    INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `response_summary`, `created_at`)
    VALUES (?, ?, ?, ?, ?, ?, '/api/partner/v1/products.php', 'products', 200, ?, 'Lấy danh sách sản phẩm', NOW())
");
$logStmt->execute([$reseller['id'], $reseller['email'], $reseller['name'], $apiKey, $ipKhach, $isHttps, $doTreMs]);

echo json_encode([
    'success'        => true,
    'walletCurrency' => 'VND',
    'products'       => $danhSachTraVe
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
