<?php
// ========================================================
// API ĐỐI TÁC: TRA CỨU SỐ DƯ VÍ ĐẠI LÝ
// vankhateamshop - GET /api/partner/v1/balance.php
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/config.php';
require_once __DIR__ . '/../../../backend/db.php';
require_once __DIR__ . '/../../../backend/security.php';

$thoiDiemBatDau = microtime(true);
$ipKhach = layIpNguoiDung();
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errorCode' => 'METHOD_NOT_ALLOWED', 'detail' => 'Chỉ hỗ trợ phương thức GET']);
    exit;
}

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

$stmt = $pdo->prepare("SELECT * FROM `users` WHERE `reseller_key` = ? AND `is_reseller` = 1 AND `is_active` = 1 LIMIT 1");
$stmt->execute([$apiKey]);
$reseller = $stmt->fetch();

if (!$reseller) {
    http_response_code(401);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_API_KEY', 'detail' => 'Key sai hoặc đã bị thu hồi']);
    exit;
}

$soDu = (float)$reseller['balance'];

// Ghi log kiểm soát
$doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
$logStmt = $pdo->prepare("
    INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `response_summary`, `created_at`)
    VALUES (?, ?, ?, ?, ?, ?, '/api/partner/v1/balance.php', 'balance', 200, ?, 'Kiểm tra số dư', NOW())
");
$logStmt->execute([$reseller['id'], $reseller['email'], $reseller['name'], $apiKey, $ipKhach, $isHttps, $doTreMs]);

echo json_encode([
    'success'        => true,
    'walletCurrency' => 'VND',
    'balance'        => (int)$soDu,
    'balanceText'    => number_format($soDu, 0, ',', '.') . ' VND'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
