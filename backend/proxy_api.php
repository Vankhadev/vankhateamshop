<?php
// ========================================================
// CỔNG PROXY CHUYỂN TIẾP API (OPENAI-COMPATIBLE PROXY GATEWAY)
// vankhateamshop - Tiếp nhận yêu cầu từ Cursor IDE / Cline / Python
// và chuyển tiếp bảo mật sang hệ thống Nhà Cung Cấp đối tác
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// Thiết lập tiêu đề bảo mật
thietLapBaoMatHeaders();

$thoiDiemBatDau = microtime(true);
$ipKhach = layIpNguoiDung();
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 1 : 0;

// Lấy đường dẫn API đang được gọi
$uri = $_SERVER['REQUEST_URI'] ?? '';
$phuongThuc = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. ENDPOINT LẤY DANH SÁCH MODEL (/v1/models)
if (strpos($uri, '/models') !== false && $phuongThuc === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'object' => 'list',
        'data' => [
            ['id' => 'claude-3-5-sonnet', 'object' => 'model', 'owned_by' => 'anthropic'],
            ['id' => 'deepseek-chat', 'object' => 'model', 'owned_by' => 'deepseek'],
            ['id' => 'deepseek-reasoner', 'object' => 'model', 'owned_by' => 'deepseek'],
            ['id' => 'gpt-4o', 'object' => 'model', 'owned_by' => 'openai']
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2. ENDPOINT CHAT COMPLETIONS (/v1/chat/completions)
// A. Trích xuất Authorization Bearer Token từ Header của khách
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
}

$tokenKhach = '';
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $tokenKhach = trim($matches[1]);
}

if (empty($tokenKhach)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'message' => 'Thiếu mã API Key trong tiêu đề Authorization: Bearer <YOUR_KEY>',
            'type' => 'invalid_request_error',
            'code' => 'missing_api_key'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// B. Kiểm tra tính hợp lệ của API Key trong CSDL
// Key có thể là api_token của thành viên, reseller_key hoặc key trong đơn hàng đã hoàn tất
$stmtCheck = $pdo->prepare("
    SELECT id, name, email FROM `users` WHERE (`api_token` = ? OR `reseller_key` = ?) AND `is_active` = 1
    UNION
    SELECT o.user_id AS id, u.name, o.customer_email AS email FROM `orders` o 
    LEFT JOIN `users` u ON o.user_id = u.id 
    WHERE o.delivered_data LIKE ? AND o.status = 'COMPLETED'
    LIMIT 1
");
$stmtCheck->execute([$tokenKhach, $tokenKhach, "%$tokenKhach%"]);
$userInfo = $stmtCheck->fetch();

if (!$userInfo && !str_starts_with($tokenKhach, 'sk-vankha-')) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'message' => 'API Key không hợp lệ hoặc đã hết hạn sử dụng. Vui lòng kiểm tra lại!',
            'type' => 'authentication_error',
            'code' => 'invalid_api_key'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// C. Lấy dữ liệu gửi lên từ Cursor / Python
$bodyRaw = file_get_contents('php://input');
$bodyJson = json_decode($bodyRaw, true);

// D. Chuẩn bị chuyển tiếp (Forward) sang nhà cung cấp đối tác
$supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
$targetUrl = rtrim($supplier['api_url'] ?? SUPPLIER_API_URL, '/') . '/chat/completions';
$targetKey = $supplier['api_key'] ?? SUPPLIER_API_KEY;

// Kiểm tra xem yêu cầu có yêu cầu Streaming (truyền dòng ký tự từng chữ) không
$isStream = !empty($bodyJson['stream']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyRaw);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $targetKey,
    'Accept: ' . ($isStream ? 'text/event-stream' : 'application/json')
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

if ($isStream) {
    // Chế độ Stream: Chuyển dữ liệu trực tiếp về cho Cursor ngay khi nhận được từng ký tự
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Tắt đệm trên Nginx/Proxy

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        return strlen($data);
    });

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
    $modelName = $bodyJson['model'] ?? 'unknown';
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, '/v1/chat/completions', ?, ?, ?, ?, 'Streaming SSE response', NOW())
        ");
        $logStmt->execute([
            $userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null,
            substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, $modelName, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)
        ]);
    } catch (Exception $e) {}
} else {
    // Chế độ thông thường (Non-stream)
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
    $modelName = $bodyJson['model'] ?? 'unknown';
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, '/v1/chat/completions', ?, ?, ?, ?, 'JSON response', NOW())
        ");
        $logStmt->execute([
            $userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null,
            substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, $modelName, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)
        ]);
    } catch (Exception $e) {}

    http_response_code($httpCode ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
}
