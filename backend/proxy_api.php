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

    // Trích xuất Authorization Bearer Token nếu có
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    }
    $tokenKhach = '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $tokenKhach = trim($matches[1]);
    }

    // Nếu là key của Zplay / Taphoaai (64 ký tự hex) thì forward sang Zplay
    if (empty($tokenKhach)) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'Thiếu API Key trong Authorization Bearer', 'code' => 'missing_api_key']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // /models cũng phải xác thực key thật, không cho truy cập công khai.
    $stmtModelAuth = $pdo->prepare("\n        SELECT id FROM `users` WHERE (`api_token` = ? OR `reseller_key` = ?) AND `is_active` = 1\n        UNION\n        SELECT o.user_id AS id FROM `orders` o WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED'\n        LIMIT 1\n    ");
    $stmtModelAuth->execute([$tokenKhach, $tokenKhach, "%$tokenKhach%", $tokenKhach]);
    if (!$stmtModelAuth->fetch()) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'API Key không hợp lệ hoặc đã hết hạn.', 'code' => 'invalid_api_key']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (preg_match('/^sk-[0-9a-f]{64}$/i', $tokenKhach)) {
        $ch = curl_init('https://zplay.io.vn/v1/models');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenKhach]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res && $code === 200) {
            echo $res;
            exit;
        }
    }
    
    // Thu thập danh sách model mặc định & từ các sản phẩm đang bán
    $modelMap = [
        'gpt-5.6-sol'       => 'openai',
        'gpt-6.1-sol'       => 'openai',
        'gpt-5.6-luna'      => 'openai',
        'gpt-5.6-terra'     => 'openai',
        'gpt-5.4-mini'      => 'openai',
        'claude-opus-5.5'   => 'anthropic',
        'claude-sonnet-5'   => 'anthropic',
        'claude-3-5-sonnet' => 'anthropic',
        'deepseek-reasoner' => 'deepseek',
        'deepseek-chat'     => 'deepseek',
        'gpt-4o'            => 'openai',
        'cursor-small'      => 'cursor'
    ];

    try {
        $stmtM = $pdo->query("SELECT DISTINCT supported_models FROM `products` WHERE `is_active` = 1 AND supported_models IS NOT NULL AND supported_models != ''");
        while ($row = $stmtM->fetch()) {
            $parts = array_map('trim', explode(',', $row['supported_models']));
            foreach ($parts as $p) {
                if (!empty($p) && !isset($modelMap[$p])) {
                    $owner = 'openai';
                    if (str_contains($p, 'claude')) $owner = 'anthropic';
                    elseif (str_contains($p, 'deepseek')) $owner = 'deepseek';
                    elseif (str_contains($p, 'cursor')) $owner = 'cursor';
                    elseif (str_contains($p, 'gemini')) $owner = 'google';
                    $modelMap[$p] = $owner;
                }
            }
        }
    } catch (Exception $e) {}

    $data = [];
    foreach ($modelMap as $modelId => $owner) {
        $data[] = [
            'id' => $modelId,
            'object' => 'model',
            'created' => 1700000000,
            'owned_by' => $owner,
            'permission' => []
        ];
    }

    echo json_encode([
        'object' => 'list',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2. ENDPOINT TRA CỨU QUOTA & HẠN DÙNG QUA API (/v1/usage hoặc /v1/key/check)
if ((strpos($uri, '/usage') !== false || strpos($uri, '/key/check') !== false) && in_array($phuongThuc, ['GET', 'POST'])) {
    header('Content-Type: application/json; charset=utf-8');
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
        $tokenKhach = trim($_GET['key'] ?? ($_POST['key'] ?? ''));
    }

    if (empty($tokenKhach)) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Missing API Key in Authorization Bearer or ?key= parameter', 'code' => 'missing_key']]);
        exit;
    }

    // Không trả quota giả cho token chưa tồn tại trong hệ thống.
    $stmtUsageAuth = $pdo->prepare("\n        SELECT id FROM `users` WHERE (`api_token` = ? OR `reseller_key` = ?) AND `is_active` = 1\n        UNION\n        SELECT o.user_id AS id FROM `orders` o WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED'\n        LIMIT 1\n    ");
    $stmtUsageAuth->execute([$tokenKhach, $tokenKhach, "%$tokenKhach%", $tokenKhach]);
    if (!$stmtUsageAuth->fetch()) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'API Key không hợp lệ hoặc đã hết hạn.', 'code' => 'invalid_api_key']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Tra cứu đơn hàng hoặc user
    $stmtO = $pdo->prepare("SELECT o.*, p.name as product_name FROM `orders` o LEFT JOIN `products` p ON o.product_id = p.id WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED' LIMIT 1");
    $stmtO->execute(["%$tokenKhach%", $tokenKhach]);
    $order = $stmtO->fetch();

    $shortKey = substr($tokenKhach, 0, 10);
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `api_key_used` LIKE ?");
    $stmtCount->execute(["%$shortKey%"]);
    $usedRequests = (int)$stmtCount->fetchColumn();

    $totalRequests = 700;
    if ($order && preg_match('/1500/i', $order['product_name'])) $totalRequests = 1500;
    $remaining = max(0, $totalRequests - $usedRequests);

    echo json_encode([
        'object' => 'usage',
        'key' => substr($tokenKhach, 0, 8) . '...' . substr($tokenKhach, -4),
        'status' => 'active',
        'total_requests' => $totalRequests,
        'used_requests' => $usedRequests,
        'remaining_requests' => $remaining,
        'package' => $order['product_name'] ?? 'AI Gateway Standard Key',
        'gateway_base_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'tiemai.io.vn') . '/v1'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 3. ENDPOINT CHAT COMPLETIONS (/v1/chat/completions)
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
    WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED'
    LIMIT 1
");
$stmtCheck->execute([$tokenKhach, $tokenKhach, "%$tokenKhach%", $tokenKhach]);
$userInfo = $stmtCheck->fetch();

// Không dùng prefix làm bằng chứng xác thực: token phải tồn tại trong CSDL.
if (!$userInfo) {
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

// D. Chuẩn bị chuyển tiếp (Forward) sang nhà cung cấp đối tác tương ứng với Key
$targetUrl = '';
$forwardKey = $tokenKhach;

// 1. Kiểm tra xem key này được mua từ đơn hàng nào để biết chính xác đối tác cấp key
$stmtFindSupplier = $pdo->prepare("
    SELECT s.* 
    FROM `orders` o 
    JOIN `suppliers` s ON o.supplier_id = s.id 
    WHERE o.delivered_data LIKE ? 
    ORDER BY o.id DESC LIMIT 1
");
$stmtFindSupplier->execute(["%$tokenKhach%"]);
$matchedSup = $stmtFindSupplier->fetch();

if ($matchedSup && stripos($matchedSup['api_url'], 'taphoaai') !== false) {
    // Key từ Taphoaai / Zplay: Cổng Chat Completions chuẩn là https://zplay.io.vn/v1/chat/completions
    $targetUrl = 'https://zplay.io.vn/v1/chat/completions';
} elseif (preg_match('/^sk-[0-9a-f]{64}$/i', $tokenKhach)) {
    // Key 64 ký tự hex của hệ thống Zplay / Taphoaai
    $targetUrl = 'https://zplay.io.vn/v1/chat/completions';
} elseif ($matchedSup) {
    $targetUrl = rtrim($matchedSup['api_url'], '/') . '/chat/completions';
} else {
    // Mặc định lấy nhà cung cấp số 1 (Genzshop)
    $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
    $targetUrl = rtrim($supplier['api_url'] ?? SUPPLIER_API_URL, '/') . '/chat/completions';
    if (!str_starts_with($tokenKhach, 'sk-') && !str_starts_with($tokenKhach, 'gzsk_')) {
        $forwardKey = $supplier['api_key'] ?? SUPPLIER_API_KEY;
    }
}

// 2. Tự động tương thích và chuyển đổi Model nếu cổng Zplay không hỗ trợ trực tiếp model yêu cầu
if ($targetUrl === 'https://zplay.io.vn/v1/chat/completions') {
    $zplaySupported = [
        'gpt-5.6-sol', 'gpt-6.1-sol', 'gpt-6-sol', 'gpt-5.6-luna', 'gpt-6-luna',
        'gpt-5.6-terra', 'gpt-5.4-mini', 'claude-opus-5.5', 'claude-opus-5-5',
        'claude-sonnet-5', 'claude-opus-5', 'claude-opus-4.8', 'claude-opus-4.7', 'claude-opus-4.6'
    ];
    $rawReqModel = trim($bodyJson['model'] ?? '');
    $reqModelLower = strtolower($rawReqModel);

    if (!empty($rawReqModel) && !in_array($reqModelLower, $zplaySupported, true)) {
        if (in_array($reqModelLower, ['gpt-4o', 'gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo', 'chatgpt-4o-latest', 'o1', 'o3-mini'], true)) {
            $bodyJson['model'] = 'gpt-5.6-sol';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        } elseif (in_array($reqModelLower, ['claude-3-5-sonnet', 'claude-3-7-sonnet', 'claude-3-opus'], true)) {
            $bodyJson['model'] = 'claude-opus-5.5';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        } elseif (in_array($reqModelLower, ['cursor-small', 'gpt-4o-mini'], true)) {
            $bodyJson['model'] = 'gpt-5.4-mini';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        } else {
            // Model không thuộc gói Codex (ví dụ: deepseek-reasoner, deepseek-chat...)
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => [
                    'message' => "Model '{$rawReqModel}' không có trong gói Codex này. Gói này chỉ hỗ trợ các model: gpt-5.6-sol, gpt-6.1-sol, claude-opus-5.5, claude-sonnet-5. Vui lòng chọn đúng model trong Kilo Code / Cursor!",
                    'type' => 'invalid_request_error',
                    'param' => 'model',
                    'code' => 'model_not_supported'
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Kiểm tra xem yêu cầu có yêu cầu Streaming (truyền dòng ký tự từng chữ) không
$isStream = !empty($bodyJson['stream']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyRaw);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $forwardKey,
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
