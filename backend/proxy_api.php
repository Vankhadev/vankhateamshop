<?php
// ========================================================
// CỔNG PROXY CHUYỂN TIẾP API (OPENAI & ANTHROPIC COMPATIBLE PROXY GATEWAY)
// vankhateamshop - Tiếp nhận yêu cầu từ Cursor IDE / Cline / Claude Code / Python
// và chuyển tiếp bảo mật sang hệ thống Nhà Cung Cấp đối tác
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// Thiết lập tiêu đề bảo mật
thietLapBaoMatHeaders();

// === CORS HEADERS: Cho phép các IDE & Extension (Cursor, Cline, Kilo, v.v.) gọi API từ mọi origin ===
// Các IDE dùng Electron/webview cần CORS đầy đủ để không bị chặn
$originKhach = $_SERVER['HTTP_ORIGIN'] ?? '*';
// Whitelist domain tin cậy; nếu không khớp thì phản chiếu origin cho extension local
$originChoPhep = ['https://cursor.sh', 'https://www.cursor.sh', 'vscode-webview://', 'vscode-file://'];
$domainChoPhep = false;
foreach ($originChoPhep as $o) {
    if (str_starts_with($originKhach, $o)) { $domainChoPhep = true; break; }
}
// Trả về Access-Control-Allow-Origin: * khi origin không xác định hoặc từ tool CLI
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, x-api-key, anthropic-version, Accept, X-Requested-With, api-key');
header('Access-Control-Max-Age: 86400');

// Trả lời ngay OPTIONS preflight request (trình duyệt / webview gửi trước khi POST thật)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$thoiDiemBatDau = microtime(true);
$ipKhach = layIpNguoiDung();
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 1 : 0;

// Lấy đường dẫn API đang được gọi
$uri = $_SERVER['REQUEST_URI'] ?? '';
$phuongThuc = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Trích xuất Authorization Bearer Token hoặc x-api-key từ Headers / Request
$tokenKhach = '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
}
if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $tokenKhach = trim($matches[1]);
}
if (empty($tokenKhach)) {
    // Hỗ trợ chuẩn Anthropic Claude CLI / Header custom
    $tokenKhach = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
}
if (empty($tokenKhach)) {
    // Hỗ trợ Azure / Custom header
    $tokenKhach = trim($_SERVER['HTTP_API_KEY'] ?? '');
}
if (empty($tokenKhach)) {
    $tokenKhach = trim($_GET['key'] ?? ($_POST['key'] ?? ''));
}

// Tự động làm sạch và trích xuất API Key nếu khách dán nhầm chuỗi JSON hoặc định dạng khác
if (preg_match('/(sk-ant-api03-[a-zA-Z0-9_\-]+|sk-[0-9a-f]{64}|sk-[a-zA-Z0-9_\-]+)/', $tokenKhach, $mClean)) {
    $tokenKhach = $mClean[1];
}

// 1. ENDPOINT LẤY DANH SÁCH MODEL (/v1/models hoặc /v1/chat/completions/models)
if (strpos($uri, '/models') !== false && $phuongThuc === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($tokenKhach)) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'Thiếu API Key trong Authorization: Bearer <YOUR_KEY>', 'code' => 'missing_api_key']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // /models cũng phải xác thực key thật, không cho truy cập công khai.
    $stmtModelAuth = $pdo->prepare("
        SELECT id FROM `users` WHERE (`api_token` = ? OR `reseller_key` = ?) AND `is_active` = 1
        UNION
        SELECT o.user_id AS id FROM `orders` o WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED'
        LIMIT 1
    ");
    $stmtModelAuth->execute([$tokenKhach, $tokenKhach, "%$tokenKhach%", $tokenKhach]);
    if (!$stmtModelAuth->fetch()) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'API Key không hợp lệ hoặc đã hết hạn.', 'code' => 'invalid_api_key']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (str_starts_with($tokenKhach, 'sk-ant-')) {
        // Lấy danh sách model từ Gwai Cloud (GenZShop)
        $ch = curl_init('https://1gw.gwai.cloud/v1/models');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenKhach]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res && $code === 200) {
            echo $res;
            exit;
        }
    }

    if (preg_match('/^sk-[0-9a-f]{64}$/i', $tokenKhach)) {
        // Thử lấy danh sách model trực tiếp từ Taphoaai Claude gateway trước
        $ch = curl_init('https://api.taphoaai.info.vn/v1/models');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenKhach]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res && $code === 200) {
            echo $res;
            exit;
        }

        // Nếu không được, thử Zplay
        $ch = curl_init('https://zplay.io.vn/v1/models');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenKhach]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
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

    if (empty($tokenKhach)) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Missing API Key in Authorization Bearer or ?key= parameter', 'code' => 'missing_key']]);
        exit;
    }

    // Không trả quota giả cho token chưa tồn tại trong hệ thống.
    $stmtUsageAuth = $pdo->prepare("
        SELECT id FROM `users` WHERE (`api_token` = ? OR `reseller_key` = ?) AND `is_active` = 1
        UNION
        SELECT o.user_id AS id FROM `orders` o WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED'
        LIMIT 1
    ");
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

    $pkgName = $order['product_name'] ?? 'AI Gateway Standard Key';
    $pkgLower = mb_strtolower($pkgName, 'UTF-8');
    $recommendedModel = 'claude-sonnet-5';
    $packageType = 'claude';
    $supportedModelsList = ['claude-sonnet-5', 'claude-opus-5.5', 'claude-fable-5'];

    if (str_contains($pkgLower, 'codex') || str_contains($pkgLower, 'gpt')) {
        $recommendedModel = 'gpt-5.6-sol';
        $packageType = 'codex';
        $supportedModelsList = ['gpt-5.6-sol', 'gpt-6.1-sol', 'gpt-5.6-terra', 'gpt-5.4-mini'];
    } elseif (str_contains($pkgLower, 'cursor')) {
        $recommendedModel = 'claude-sonnet-5';
        $packageType = 'cursor';
        $supportedModelsList = ['claude-sonnet-5', 'gpt-5.6-sol', 'cursor-small'];
    }

    // Lấy thông tin từ Upstream Taphoaai / Zplay nếu là key 64 hex
    $upstreamUsage = null;
    if (str_starts_with($tokenKhach, 'sk-ant-')) {
        // Lấy danh sách model từ Gwai Cloud (GenZShop)
        $ch = curl_init('https://1gw.gwai.cloud/v1/models');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenKhach]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res && $code === 200) {
            echo $res;
            exit;
        }
    }

    if (preg_match('/^sk-[0-9a-f]{64}$/i', $tokenKhach)) {
        $tryUrls = ['https://api.taphoaai.info.vn/v1/usage', 'https://zplay.io.vn/v1/usage'];
        if ($packageType === 'codex') {
            $tryUrls = ['https://zplay.io.vn/v1/usage', 'https://api.taphoaai.info.vn/v1/usage'];
        }
        foreach ($tryUrls as $u) {
            $ch = curl_init($u);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenKhach, 'User-Agent: Mozilla/5.0']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res && $code === 200) {
                $parsed = json_decode($res, true);
                if (!empty($parsed) && (isset($parsed['usage']) || isset($parsed['quota']) || isset($parsed['request_quota']))) {
                    $upstreamUsage = $parsed;
                    break;
                }
            }
        }
    }

    $upQuotaLimit = 0;
    $upQuotaUsed = 0;
    $statusText = 'active';
    $tokenQuotaLimit = null;
    $tokenQuotaDisplay = null;

    if ($upstreamUsage) {
        $upStatus = strtolower(trim($upstreamUsage['status'] ?? ''));
        $isQuotaExhausted = ($upStatus === 'quota_exhausted');

        // USD Quota
        $upQuotaLimit = (float)($upstreamUsage['quota']['limit'] ?? 0);
        $upQuotaUsed = (float)($upstreamUsage['quota']['used'] ?? ($upstreamUsage['usage']['total']['actual_cost'] ?? 0));
        $upQuotaRemaining = isset($upstreamUsage['quota']['remaining']) ? (float)$upstreamUsage['quota']['remaining'] : null;

        // Request Quota
        $upReqLimit = (int)($upstreamUsage['request_quota']['limit'] ?? 0);
        $upReqUsed = (int)($upstreamUsage['request_quota']['used'] ?? 0);
        $upReqRemaining = isset($upstreamUsage['request_quota']['remaining']) ? (int)$upstreamUsage['request_quota']['remaining'] : null;

        // Token RPM Quota
        $upTokenLimit = (int)($upstreamUsage['request_token_quota']['limit'] ?? 0);
        if ($upTokenLimit > 0) {
            $tokenQuotaLimit = $upTokenLimit;
            $tokenQuotaDisplay = round($upTokenLimit / 1000000) . 'M Token RPM';
        }

        if ($upReqLimit > 0) {
            $totalRequests = $upReqLimit;
            $usedRequests = $upReqUsed;
            $remaining = ($upReqRemaining !== null) ? $upReqRemaining : max(0, $upReqLimit - $upReqUsed);
        } else {
            $usedRequests = (int)($upstreamUsage['usage']['total']['requests'] ?? $usedRequests);
            $remaining = max(0, $totalRequests - $usedRequests);
        }

        $isExhausted = $isQuotaExhausted 
            || ($upQuotaLimit > 0 && $upQuotaUsed >= $upQuotaLimit) 
            || ($upReqLimit > 0 && $usedRequests >= $totalRequests) 
            || ($upQuotaRemaining !== null && $upQuotaRemaining <= 0)
            || ($upReqRemaining !== null && $upReqRemaining <= 0);

        if ($isExhausted) {
            $statusText = 'quota_exhausted';
            $remaining = 0;
        }
    }

    echo json_encode([
        'object' => 'usage',
        'key' => substr($tokenKhach, 0, 8) . '...' . substr($tokenKhach, -4),
        'status' => $statusText,
        'quota_limit' => $upQuotaLimit > 0 ? $upQuotaLimit : null,
        'quota_used' => $upQuotaLimit > 0 ? round($upQuotaUsed, 2) : null,
        'quota_remaining' => $upQuotaLimit > 0 ? max(0, round($upQuotaLimit - $upQuotaUsed, 2)) : null,
        'cost_display' => $upQuotaLimit > 0 ? ('$' . number_format($upQuotaUsed, 2) . ' / $' . number_format($upQuotaLimit, 2) . ' USD') : null,
        'total_requests' => $totalRequests,
        'used_requests' => $usedRequests,
        'remaining_requests' => $remaining,
        'token_quota_limit' => $tokenQuotaLimit,
        'token_quota_display' => $tokenQuotaDisplay,
        'package' => $pkgName,
        'package_type' => $packageType,
        'recommended_model' => $recommendedModel,
        'supported_models' => $supportedModelsList,
        'gateway_base_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'tiemai.io.vn') . '/v1'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 3b. ENDPOINT LEGACY TEXT COMPLETIONS (/v1/completions) — Chuyển tiếp sang chat/completions
// Một số tool cũ (aider, OpenAI v0.x SDK) vẫn gọi /v1/completions thay vì /v1/chat/completions
if (strpos($uri, '/completions') !== false && strpos($uri, '/chat/') === false && $phuongThuc === 'POST') {
    // Lấy body raw để chuyển đổi format nếu cần
    $bodyRawLegacy = file_get_contents('php://input');
    $bodyJsonLegacy = json_decode($bodyRawLegacy, true) ?: [];

    // Chuyển đổi format text completion sang chat completion nếu cần
    if (!isset($bodyJsonLegacy['messages']) && isset($bodyJsonLegacy['prompt'])) {
        $bodyJsonLegacy['messages'] = [
            ['role' => 'user', 'content' => $bodyJsonLegacy['prompt']]
        ];
        unset($bodyJsonLegacy['prompt']);
        $bodyRawLegacy = json_encode($bodyJsonLegacy, JSON_UNESCAPED_UNICODE);
    }

    // Rewrite URI sang /chat/completions rồi xử lý tiếp bình thường
    $_SERVER['REQUEST_URI'] = str_replace('/completions', '/chat/completions', $uri);
    // Đẩy lại body vào stream (không thể đổi php://input, nhưng ta sẽ dùng biến tạm)
    // Forward ngay tới supplier mặc định
    if (empty($tokenKhach)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['message' => 'Missing API Key', 'code' => 'missing_api_key']]);
        exit;
    }

    $supplierLegacy = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
    $urlLegacy = rtrim($supplierLegacy['api_url'] ?? SUPPLIER_API_URL, '/') . '/chat/completions';
    $keyLegacy = $tokenKhach;

    $isStreamLegacy = !empty($bodyJsonLegacy['stream']);
    $headersLegacy = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $keyLegacy,
        'Accept: ' . ($isStreamLegacy ? 'text/event-stream' : 'application/json')
    ];

    $chL = curl_init($urlLegacy);
    curl_setopt($chL, CURLOPT_POST, true);
    curl_setopt($chL, CURLOPT_POSTFIELDS, $bodyRawLegacy);
    curl_setopt($chL, CURLOPT_HTTPHEADER, $headersLegacy);
    curl_setopt($chL, CURLOPT_TIMEOUT, 120);
    curl_setopt($chL, CURLOPT_RETURNTRANSFER, true);
    $respL = curl_exec($chL);
    $codeL = curl_getinfo($chL, CURLINFO_HTTP_CODE);
    curl_close($chL);

    http_response_code($codeL ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo $respL;
    exit;
}

// 3. ENDPOINT CHAT COMPLETIONS (/v1/chat/completions) & NATIVE ANTHROPIC MESSAGES (/v1/messages)
if (empty($tokenKhach)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'message' => 'Thiếu mã API Key trong tiêu đề Authorization: Bearer <YOUR_KEY> hoặc x-api-key',
            'type' => 'invalid_request_error',
            'code' => 'missing_api_key'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra tính hợp lệ của API Key trong CSDL
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

// Lấy dữ liệu gửi lên từ Cline / Cursor / Kilo Code / Python
$bodyRaw = file_get_contents('php://input');
$bodyJson = json_decode($bodyRaw, true) ?: [];

// Chuẩn bị chuyển tiếp (Forward) sang nhà cung cấp đối tác tương ứng với Key
$targetUrl = '';
$forwardKey = $tokenKhach;
$isClaudeGateway = false;
$isAnthropicMessages = (strpos($uri, '/messages') !== false);

// Kiểm tra xem key này được mua từ đơn hàng nào để biết chính xác đối tác và gói dịch vụ
$stmtFindSupplier = $pdo->prepare("
    SELECT s.*, o.product_id, p.name as product_name, p.supported_models 
    FROM `orders` o 
    LEFT JOIN `suppliers` s ON o.supplier_id = s.id 
    LEFT JOIN `products` p ON o.product_id = p.id
    WHERE o.delivered_data LIKE ? 
    ORDER BY o.id DESC LIMIT 1
");
$stmtFindSupplier->execute(["%$tokenKhach%"]);
$matchedSup = $stmtFindSupplier->fetch();

$prodNameLower = mb_strtolower($matchedSup['product_name'] ?? '', 'UTF-8');

// ROUTING THÔNG MINH: Dùng ai_api_url từ supplier nếu có, fallback theo key format
if ($matchedSup && !empty($matchedSup['ai_api_url'])) {
    // ƯU TIÊN 1: Dùng AI endpoint của supplier (GenzShop, Taphoaai, v.v.)
    $supplierAiBase = rtrim($matchedSup['ai_api_url'], '/');
    $supName = mb_strtolower($matchedSup['name'] ?? '', 'UTF-8');
    
    // GenzShop/Gwai Cloud keys: sk-ant-* format, cần convert sang /messages nếu gọi /chat/completions
    if (str_contains($supName, 'genz') || str_contains($supName, 'gwai') || str_contains($supplierAiBase, 'gwai')) {
        $isGwaiGateway = true;
        $targetUrl = $isAnthropicMessages ? "$supplierAiBase/messages" : "$supplierAiBase/chat/completions";
    } else {
        // Taphoaai và các supplier khác
        $targetUrl = $isAnthropicMessages ? "$supplierAiBase/messages" : "$supplierAiBase/chat/completions";
        $isClaudeGateway = str_contains($prodNameLower, 'claude');
    }
} elseif (str_starts_with($tokenKhach, 'sk-ant-') && empty($matchedSup)) {
    // ƯU TIÊN 2: Key sk-ant-* không có trong database → Gwai Cloud trực tiếp (key ngoài)
    $isGwaiGateway = true;
    $targetUrl = 'https://1gw.gwai.cloud/v1/messages';
} elseif ($isAnthropicMessages) {
    // ƯU TIÊN 3: Anthropic Native Protocol /v1/messages
    $targetUrl = 'https://api.taphoaai.info.vn/v1/messages';
    $isClaudeGateway = true;
} elseif ($matchedSup && str_contains($prodNameLower, 'claude')) {
    // ƯU TIÊN 4: Có đơn hàng, product chứa 'claude' → Taphoaai
    $targetUrl = 'https://api.taphoaai.info.vn/v1/chat/completions';
    $isClaudeGateway = true;
} elseif ($matchedSup && (str_contains($prodNameLower, 'codex') || str_contains($prodNameLower, 'gpt'))) {
    // ƯU TIÊN 5: Có đơn hàng, product chứa 'codex' hoặc 'gpt' → Zplay
    $targetUrl = 'https://zplay.io.vn/v1/chat/completions';
} elseif (preg_match('/^sk-[0-9a-f]{64}$/i', $tokenKhach)) {
    // Key 64 ký tự hex nhưng không khớp đơn, phân biệt thông minh theo model khách gọi
    $rawModelCheck = mb_strtolower(trim($bodyJson['model'] ?? ''), 'UTF-8');
    if (str_contains($rawModelCheck, 'claude')) {
        $targetUrl = 'https://api.taphoaai.info.vn/v1/chat/completions';
        $isClaudeGateway = true;
    } else {
        $targetUrl = 'https://zplay.io.vn/v1/chat/completions';
    }
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

// Tự động tương thích và chuyển đổi Model (Model Aliases & Auto Fallback)
if ($isClaudeGateway) {
    $taphoaaiSupported = [
        'claude-sonnet-5', 'claude-opus-5.5', 'claude-opus-5', 'claude-fable-5',
        'claude-sonnet-4-6', 'claude-sonnet-4-5-20250929', 'claude-haiku-4-5-20251001',
        'claude-opus-4-8', 'claude-opus-4-7', 'claude-opus-4-6', 'claude-opus-4-5-20251101',
        'claude-fable-5-1', 'claude-fable-5.1', 'claude-opus-5-5', 'claude-haiku-5-5', 'claude-sonnet-5-5'
    ];
    $rawReqModel = trim($bodyJson['model'] ?? '');
    $reqModelLower = mb_strtolower($rawReqModel, 'UTF-8');

    if (!empty($rawReqModel) && !in_array($reqModelLower, $taphoaaiSupported, true)) {
        // Tự động ánh xạ alias Claude phổ biến trên Cursor / Cline
        if (str_starts_with($reqModelLower, 'claude-3-5-sonnet') || str_starts_with($reqModelLower, 'claude-3-7-sonnet') || str_contains($reqModelLower, 'sonnet')) {
            $bodyJson['model'] = 'claude-sonnet-5';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        } elseif (str_starts_with($reqModelLower, 'claude-3-opus') || str_contains($reqModelLower, 'opus')) {
            $bodyJson['model'] = 'claude-opus-5.5';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        } elseif (str_contains($reqModelLower, 'haiku') || str_contains($reqModelLower, 'fable')) {
            $bodyJson['model'] = 'claude-fable-5';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        } else {
            // Khách chọn nhầm model OpenAI (như gpt-5.6-terra, gpt-4o) trên key Claude -> Tự động chuyển mượt sang claude-sonnet-5 để không bị crash
            $bodyJson['model'] = 'claude-sonnet-5';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        }
    }
} elseif ($targetUrl === 'https://zplay.io.vn/v1/chat/completions') {
    $zplaySupported = [
        'gpt-5.6-sol', 'gpt-6.1-sol', 'gpt-6-sol', 'gpt-5.6-luna', 'gpt-6-luna',
        'gpt-5.6-terra', 'gpt-5.4-mini', 'claude-opus-5.5', 'claude-opus-5-5',
        'claude-sonnet-5', 'claude-opus-5', 'claude-opus-4.8', 'claude-opus-4.7', 'claude-opus-4.6'
    ];
    $rawReqModel = trim($bodyJson['model'] ?? '');
    $reqModelLower = mb_strtolower($rawReqModel, 'UTF-8');

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
            $bodyJson['model'] = 'gpt-5.6-sol';
            $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        }
    }
}

// NẾU LÀ KEY CỦA GWAI CLOUD (GENZSHOP) NHƯNG KHÁCH GỌI BẰNG CHUẨN OPENAI (/v1/chat/completions)
// Gwai Cloud chặn /chat/completions và chỉ nhận /messages, gateway TiemAI sẽ tự động chuyển đổi 2 chiều
if ($isGwaiGateway && !$isAnthropicMessages) {
    $systemPrompt = '';
    $anthropicMessages = [];
    foreach ($bodyJson['messages'] ?? [] as $m) {
        $role = $m['role'] ?? 'user';
        $content = $m['content'] ?? '';
        if ($role === 'system') {
            $systemPrompt .= ($systemPrompt ? "\n\n" : "") . (is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE));
        } else {
            $anthropicMessages[] = [
                'role' => ($role === 'assistant' ? 'assistant' : 'user'),
                'content' => $content
            ];
        }
    }

    if (empty($anthropicMessages)) {
        $anthropicMessages[] = ['role' => 'user', 'content' => 'Hello'];
    } elseif ($anthropicMessages[0]['role'] === 'assistant') {
        array_unshift($anthropicMessages, ['role' => 'user', 'content' => 'Continue']);
    }

    $reqModel = $bodyJson['model'] ?? 'claude-sonnet-5';
    if (str_contains(strtolower($reqModel), 'opus')) {
        $gwaiModel = 'claude-opus-5-5';
    } elseif (str_contains(strtolower($reqModel), 'haiku') || str_contains(strtolower($reqModel), 'fable')) {
        $gwaiModel = 'claude-fable-5';
    } else {
        $gwaiModel = 'claude-sonnet-5';
    }

    $isStream = !empty($bodyJson['stream']);
    $anthropicPayload = [
        'model' => $gwaiModel,
        'messages' => $anthropicMessages,
        'max_tokens' => (int)($bodyJson['max_tokens'] ?? 4096),
        'stream' => $isStream
    ];
    if (!empty($systemPrompt)) {
        $anthropicPayload['system'] = $systemPrompt;
    }

    $gwaiHeaders = [
        'Content-Type: application/json',
        'x-api-key: ' . $tokenKhach,
        'anthropic-version: 2023-06-01',
        'Accept: ' . ($isStream ? 'text/event-stream' : 'application/json'),
        'User-Agent: Mozilla/5.0'
    ];

    if ($isStream) {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $streamId = 'chatcmpl-' . bin2hex(random_bytes(12));
        $chG = curl_init('https://1gw.gwai.cloud/v1/messages');
        curl_setopt($chG, CURLOPT_POST, true);
        curl_setopt($chG, CURLOPT_POSTFIELDS, json_encode($anthropicPayload, JSON_UNESCAPED_UNICODE));
        curl_setopt($chG, CURLOPT_HTTPHEADER, $gwaiHeaders);
        curl_setopt($chG, CURLOPT_TIMEOUT, 300);

        $lineBuffer = '';
        curl_setopt($chG, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$lineBuffer, $streamId, $gwaiModel) {
            $lineBuffer .= $chunk;
            $lines = explode("\n", $lineBuffer);
            $lineBuffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, 'event:') || str_starts_with($line, ':')) {
                    continue;
                }
                if (str_starts_with($line, 'data:')) {
                    $jsonStr = trim(substr($line, 5));
                    if ($jsonStr === '[DONE]') {
                        echo "data: [DONE]\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                        continue;
                    }
                    $d = json_decode($jsonStr, true);
                    if (!$d) continue;

                    $type = $d['type'] ?? '';
                    if ($type === 'content_block_delta') {
                        $delta = $d['delta'] ?? [];
                        if (($delta['type'] ?? '') === 'text_delta') {
                            $text = $delta['text'] ?? '';
                            $chunkObj = [
                                'id' => $streamId,
                                'object' => 'chat.completion.chunk',
                                'created' => time(),
                                'model' => $gwaiModel,
                                'choices' => [
                                    [
                                        'index' => 0,
                                        'delta' => ['content' => $text],
                                        'finish_reason' => null
                                    ]
                                ]
                            ];
                            echo "data: " . json_encode($chunkObj, JSON_UNESCAPED_UNICODE) . "\n\n";
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }
                    } elseif ($type === 'message_stop') {
                        $chunkObj = [
                            'id' => $streamId,
                            'object' => 'chat.completion.chunk',
                            'created' => time(),
                            'model' => $gwaiModel,
                            'choices' => [
                                [
                                    'index' => 0,
                                    'delta' => new stdClass(),
                                    'finish_reason' => 'stop'
                                ]
                            ]
                        ];
                        echo "data: " . json_encode($chunkObj, JSON_UNESCAPED_UNICODE) . "\n\n";
                        echo "data: [DONE]\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    }
                }
            }
            return strlen($chunk);
        });

        curl_exec($chG);
        $httpCode = curl_getinfo($chG, CURLINFO_HTTP_CODE);
        curl_close($chG);

        $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Streaming SSE response', NOW())
            ");
            $logStmt->execute([
                $userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null,
                substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, '/v1/chat/completions', $gwaiModel, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)
            ]);
        } catch (Exception $e) {}
        exit;
    } else {
        $chG = curl_init('https://1gw.gwai.cloud/v1/messages');
        curl_setopt($chG, CURLOPT_POST, true);
        curl_setopt($chG, CURLOPT_POSTFIELDS, json_encode($anthropicPayload, JSON_UNESCAPED_UNICODE));
        curl_setopt($chG, CURLOPT_HTTPHEADER, $gwaiHeaders);
        curl_setopt($chG, CURLOPT_TIMEOUT, 120);
        curl_setopt($chG, CURLOPT_RETURNTRANSFER, true);
        $resG = curl_exec($chG);
        $httpCode = curl_getinfo($chG, CURLINFO_HTTP_CODE);
        curl_close($chG);

        $jsonG = json_decode($resG, true);
        $textOut = '';
        if (!empty($jsonG['content'])) {
            foreach ($jsonG['content'] as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $textOut .= $b['text'] ?? '';
                }
            }
        }

        $openAiRes = [
            'id' => 'chatcmpl-' . ($jsonG['id'] ?? uniqid()),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $gwaiModel,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $textOut
                    ],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => $jsonG['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $jsonG['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($jsonG['usage']['input_tokens'] ?? 0) + ($jsonG['usage']['output_tokens'] ?? 0)
            ]
        ];

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode ?: 200);
        echo json_encode($openAiRes, JSON_UNESCAPED_UNICODE);

        $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'JSON response', NOW())
            ");
            $logStmt->execute([
                $userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null,
                substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, '/v1/chat/completions', $gwaiModel, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)
            ]);
        } catch (Exception $e) {}
        exit;
    }
}

// Xử lý Chuyển tiếp Request (Streaming SSE & Non-streaming)
$isStream = !empty($bodyJson['stream']);

$requestHeaders = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $forwardKey,
    'Accept: ' . ($isStream ? 'text/event-stream' : 'application/json')
];
if ($isAnthropicMessages || $isGwaiGateway) {
    $requestHeaders[] = 'x-api-key: ' . $forwardKey;
    $requestHeaders[] = 'anthropic-version: 2023-06-01';
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyRaw);
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

if ($isStream) {
    $upstreamStatus = 200;
    $headersSent = false;
    $errorBuffer = '';

    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (&$upstreamStatus) {
        if (preg_match('/^HTTP\/[12](?:\.[01])?\s+(\d{3})/i', $headerLine, $m)) {
            $upstreamStatus = (int)$m[1];
        }
        return strlen($headerLine);
    });

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$headersSent, &$upstreamStatus, &$errorBuffer) {
        if ($upstreamStatus >= 400) {
            $errorBuffer .= $data;
            return strlen($data);
        }

        if (!$headersSent) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            $headersSent = true;
        }

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

    $finalCode = ($upstreamStatus >= 400) ? $upstreamStatus : ($httpCode ?: 200);

    if ($finalCode >= 400) {
        http_response_code($finalCode);
        header('Content-Type: application/json; charset=utf-8');
        if (!empty($errorBuffer)) {
            echo $errorBuffer;
        } else {
            echo json_encode([
                'error' => [
                    'message' => 'Lỗi kết nối từ máy chủ AI đối tác (HTTP ' . $finalCode . ')',
                    'type' => 'upstream_error',
                    'code' => $finalCode
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
    $modelName = $bodyJson['model'] ?? 'unknown';
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Streaming SSE response', NOW())
        ");
        $logStmt->execute([
            $userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null,
            substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, ($isAnthropicMessages ? '/v1/messages' : '/v1/chat/completions'), $modelName, $finalCode, $doTreMs, mb_substr($bodyRaw, 0, 300)
        ]);
    } catch (Exception $e) {}
} else {
    // Chế độ Non-stream
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
    $modelName = $bodyJson['model'] ?? 'unknown';
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'JSON response', NOW())
        ");
        $logStmt->execute([
            $userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null,
            substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, ($isAnthropicMessages ? '/v1/messages' : '/v1/chat/completions'), $modelName, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)
        ]);
    } catch (Exception $e) {}

    http_response_code($httpCode ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
}
