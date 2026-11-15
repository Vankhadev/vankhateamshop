<?php
// ========================================================
// CỔNG PROXY CHUYỂN TIẾP API (UNIVERSAL AI GATEWAY PROXY)
// vankhateamshop - Tương thích 100% mọi IDE & AI Agent:
// Cursor IDE, Cline, Roo Code, Kilo Code, Claude Code, Python SDK, Windsurf, Continue
// Hỗ trợ mọi nguồn Key: GenZShop (Gwai Cloud), Taphoaai, Zplay, TiemAI Direct
// Tự động chuyển đổi giao thức 2 chiều (OpenAI <-> Anthropic)
// Tự động sửa lỗi dán nhầm chuỗi JSON, dán sai Base URL, tự động chuyển đổi Model
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// Thiết lập tiêu đề bảo mật
thietLapBaoMatHeaders();

// === CORS HEADERS: Cho phép mọi IDE & Extension gọi API không bao giờ bị chặn ===
$originKhach = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, x-api-key, anthropic-version, Accept, X-Requested-With, api-key');
header('Access-Control-Max-Age: 86400');

// Trả lời ngay OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$thoiDiemBatDau = microtime(true);
$ipKhach = layIpNguoiDung();
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 1 : 0;

// 1. CHUẨN HÓA ĐƯỜNG DẪN REQUEST (URI NORMALIZATION)
// Tự động xử lý trường hợp khách dán nhầm Base URL như https://tiemai.io.vn/v1/chat/completions khiến IDE gọi thành /chat/completions/chat/completions
$rawUri = $_SERVER['REQUEST_URI'] ?? '';
$uriPath = parse_url($rawUri, PHP_URL_PATH) ?? '';
$uriPath = rtrim($uriPath, '/');
$phuongThuc = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 2. TRÍCH XUẤT VÀ LÀM SẠCH API KEY (UNIVERSAL TOKEN EXTRACTOR)
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
    $tokenKhach = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
}
if (empty($tokenKhach)) {
    $tokenKhach = trim($_SERVER['HTTP_API_KEY'] ?? '');
}
if (empty($tokenKhach)) {
    $tokenKhach = trim($_GET['key'] ?? ($_POST['key'] ?? ''));
}

// Bóc tách API Key sạch từ chuỗi JSON, chuỗi có tiền tố (ví dụ "API Claude | 10$: sk-...", "API Key-15: sk-...")
if (preg_match('/(sk-ant-api03-[a-zA-Z0-9_\-]+|sk-[0-9a-fA-F]{64}|sk-[a-zA-Z0-9_\-]{20,}|gzsk_[a-zA-Z0-9_\-]+)/', $tokenKhach, $mClean)) {
    $tokenKhach = $mClean[1];
} elseif (preg_match('/^(?:#|VK|tiemai)/i', $tokenKhach)) {
    // Khách dán mã đơn hàng thay vì key -> Tự động tìm key thực tế trong đơn
    try {
        $stmtFindOrderKey = $pdo->prepare("SELECT delivered_data FROM `orders` WHERE (order_code = ? OR id = ?) AND status = 'COMPLETED' LIMIT 1");
        $stmtFindOrderKey->execute([ltrim($tokenKhach, '#'), ltrim($tokenKhach, '#')]);
        $rawDelivered = $stmtFindOrderKey->fetchColumn();
        if ($rawDelivered && preg_match('/(sk-ant-api03-[a-zA-Z0-9_\-]+|sk-[0-9a-fA-F]{64}|sk-[a-zA-Z0-9_\-]{20,}|gzsk_[a-zA-Z0-9_\-]+)/', $rawDelivered, $mK)) {
            $tokenKhach = $mK[1];
        }
    } catch (Exception $e) {}
}

// ========================================================
// ENDPOINT 1: LẤY DANH SÁCH MODEL (/v1/models hoặc bất kỳ path nào kết thúc bằng /models)
// ========================================================
if (str_ends_with($uriPath, '/models') && $phuongThuc === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    // Danh sách Model đầy đủ nhất hỗ trợ tất cả các IDE & Client
    $modelsList = [
        // Anthropic Claude Flagships
        ['id' => 'claude-sonnet-5',   'owned_by' => 'anthropic'],
        ['id' => 'claude-opus-5.5',   'owned_by' => 'anthropic'],
        ['id' => 'claude-fable-5',    'owned_by' => 'anthropic'],
        ['id' => 'claude-3-5-sonnet', 'owned_by' => 'anthropic'],
        ['id' => 'claude-3-7-sonnet', 'owned_by' => 'anthropic'],
        ['id' => 'claude-sonnet-4-6', 'owned_by' => 'anthropic'],
        ['id' => 'claude-3-opus',     'owned_by' => 'anthropic'],
        ['id' => 'claude-3-5-haiku',  'owned_by' => 'anthropic'],
        // OpenAI / Codex Flagships
        ['id' => 'gpt-5.6-sol',       'owned_by' => 'openai'],
        ['id' => 'gpt-6.1-sol',       'owned_by' => 'openai'],
        ['id' => 'gpt-5.6-terra',     'owned_by' => 'openai'],
        ['id' => 'gpt-5.6-luna',      'owned_by' => 'openai'],
        ['id' => 'gpt-5.4-mini',      'owned_by' => 'openai'],
        ['id' => 'gpt-4o',            'owned_by' => 'openai'],
        ['id' => 'gpt-4o-mini',       'owned_by' => 'openai'],
        ['id' => 'o1',                'owned_by' => 'openai'],
        ['id' => 'o3-mini',           'owned_by' => 'openai'],
        // DeepSeek Flagships
        ['id' => 'deepseek-reasoner', 'owned_by' => 'deepseek'],
        ['id' => 'deepseek-chat',     'owned_by' => 'deepseek'],
        // Cursor
        ['id' => 'cursor-small',      'owned_by' => 'cursor'],
    ];

    $data = [];
    foreach ($modelsList as $m) {
        $data[] = [
            'id' => $m['id'],
            'object' => 'model',
            'created' => 1700000000,
            'owned_by' => $m['owned_by'],
            'permission' => []
        ];
    }

    echo json_encode([
        'object' => 'list',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ========================================================
// ENDPOINT 2: TRA CỨU USAGE & HẠN MỨC (/v1/usage hoặc /v1/key/check)
// ========================================================
if ((str_contains($uriPath, '/usage') || str_contains($uriPath, '/key/check')) && in_array($phuongThuc, ['GET', 'POST'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($tokenKhach)) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Thiếu API Key trong Authorization: Bearer hoặc ?key=', 'code' => 'missing_key']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Tra cứu đơn hàng hoặc user
    $order = null;
    try {
        $stmtO = $pdo->prepare("SELECT o.*, p.name as product_name FROM `orders` o LEFT JOIN `products` p ON o.product_id = p.id WHERE (o.delivered_data LIKE ? OR o.order_code = ?) AND o.status = 'COMPLETED' ORDER BY o.id DESC LIMIT 1");
        $stmtO->execute(["%$tokenKhach%", $tokenKhach]);
        $order = $stmtO->fetch();
    } catch (Exception $e) {}

    $shortKey = substr($tokenKhach, 0, 10);
    $usedRequests = 0;
    try {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `api_key_used` LIKE ?");
        $stmtCount->execute(["%$shortKey%"]);
        $usedRequests = (int)$stmtCount->fetchColumn();
    } catch (Exception $e) {}

    $totalRequests = 700;
    if ($order && preg_match('/1500/i', $order['product_name'])) $totalRequests = 1500;
    $remaining = max(0, $totalRequests - $usedRequests);

    $pkgName = $order['product_name'] ?? (str_starts_with($tokenKhach, 'sk-ant-') ? 'Claude API Gateway Key (GenZShop)' : 'AI Gateway Standard Key');
    $pkgLower = mb_strtolower($pkgName, 'UTF-8');
    $recommendedModel = 'claude-sonnet-5';
    $packageType = 'claude';
    $supportedModelsList = ['claude-sonnet-5', 'claude-opus-5.5', 'claude-fable-5'];

    if (str_contains($pkgLower, 'codex') || str_contains($pkgLower, 'gpt')) {
        $recommendedModel = 'gpt-5.6-sol';
        $packageType = 'codex';
        $supportedModelsList = ['gpt-5.6-sol', 'gpt-6.1-sol', 'gpt-5.6-terra', 'gpt-5.4-mini'];
    }

    // Lấy thông tin từ upstream nếu có
    $statusText = 'active';
    $upQuotaLimit = 0;
    $upQuotaUsed = 0;

    if (preg_match('/^sk-[0-9a-fA-F]{64}$/i', $tokenKhach)) {
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
                if (!empty($parsed)) {
                    if (($parsed['status'] ?? '') === 'quota_exhausted') $statusText = 'quota_exhausted';
                    $upQuotaLimit = (float)($parsed['quota']['limit'] ?? 0);
                    $upQuotaUsed = (float)($parsed['quota']['used'] ?? ($parsed['usage']['total']['actual_cost'] ?? 0));
                    $reqLimit = (int)($parsed['request_quota']['limit'] ?? 0);
                    $reqUsed = (int)($parsed['request_quota']['used'] ?? 0);
                    if ($reqLimit > 0) {
                        $totalRequests = $reqLimit;
                        $usedRequests = $reqUsed;
                        $remaining = max(0, $reqLimit - $reqUsed);
                    }
                    break;
                }
            }
        }
    }

    echo json_encode([
        'object' => 'usage',
        'key' => substr($tokenKhach, 0, 8) . '...' . substr($tokenKhach, -4),
        'status' => $statusText,
        'quota_limit' => $upQuotaLimit > 0 ? $upQuotaLimit : null,
        'quota_used' => $upQuotaLimit > 0 ? round($upQuotaUsed, 2) : null,
        'total_requests' => $totalRequests,
        'used_requests' => $usedRequests,
        'remaining_requests' => $remaining,
        'package' => $pkgName,
        'package_type' => $packageType,
        'recommended_model' => $recommendedModel,
        'supported_models' => $supportedModelsList,
        'gateway_base_url' => 'https://tiemai.io.vn/v1'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ========================================================
// ENDPOINT 3: CHAT COMPLETIONS & NATIVE ANTHROPIC MESSAGES
// (/v1/chat/completions, /v1/messages, hoặc legacy /v1/completions)
// ========================================================

if (empty($tokenKhach)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'message' => 'Thiếu API Key trong Authorization: Bearer <YOUR_KEY> hoặc x-api-key',
            'type' => 'invalid_request_error',
            'code' => 'missing_api_key'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra Key trong CSDL TiemAI (users hoặc orders)
$userInfo = null;
$matchedSup = null;
try {
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

    $stmtFindSupplier = $pdo->prepare("
        SELECT s.*, o.supplier_id as order_sup_id, o.product_id, p.name as product_name, p.supported_models 
        FROM `orders` o 
        LEFT JOIN `suppliers` s ON o.supplier_id = s.id 
        LEFT JOIN `products` p ON o.product_id = p.id
        WHERE o.delivered_data LIKE ? 
        ORDER BY o.id DESC LIMIT 1
    ");
    $stmtFindSupplier->execute(["%$tokenKhach%"]);
    $matchedSup = $stmtFindSupplier->fetch();
} catch (Exception $e) {}

// Key hợp lệ nếu có trong DB HOẶC là key chuẩn từ đối tác (sk-ant-*, sk-64hex, gzsk_*)
$isValidFormat = preg_match('/^(sk-ant-api03-[a-zA-Z0-9_\-]+|sk-[0-9a-fA-F]{64}|gzsk_[a-zA-Z0-9_\-]+)$/', $tokenKhach);
if (!$userInfo && !$isValidFormat) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'message' => 'API Key không hợp lệ hoặc đã hết hạn sử dụng. Vui lòng kiểm tra lại tại https://tiemai.io.vn/tra-cuu-key',
            'type' => 'authentication_error',
            'code' => 'invalid_api_key'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đọc payload gửi lên từ IDE / Client
$bodyRaw = file_get_contents('php://input');
$bodyJson = json_decode($bodyRaw, true) ?: [];

// Nếu là legacy text completions (/v1/completions mà không có messages), tự động chuyển đổi thành chat completion
if (str_ends_with($uriPath, '/completions') && !str_contains($uriPath, '/chat/') && isset($bodyJson['prompt']) && !isset($bodyJson['messages'])) {
    $bodyJson['messages'] = [['role' => 'user', 'content' => $bodyJson['prompt']]];
    unset($bodyJson['prompt']);
    $bodyRaw = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
}

$isAnthropicMessages = str_contains($uriPath, '/messages');
$isStream = !empty($bodyJson['stream']);
$rawModel = trim($bodyJson['model'] ?? '');
$modelLower = mb_strtolower($rawModel, 'UTF-8');
$prodNameLower = mb_strtolower($matchedSup['product_name'] ?? '', 'UTF-8');
$orderSupId = (int)($matchedSup['order_sup_id'] ?? 0);

// Phân loại nguồn Gateway của Key
$isGwaiKey = str_starts_with($tokenKhach, 'sk-ant-') || ($orderSupId === 1 && !str_starts_with($tokenKhach, 'sk-'));
$isHexKey  = (bool)preg_match('/^sk-[0-9a-fA-F]{64}$/i', $tokenKhach);

// =========================================================================
// NHÁNH 1: KEY TỪ GENZSHOP / GWAI CLOUD (sk-ant-api03-*)
// Cổng đích: https://1gw.gwai.cloud
// Upstream chỉ chấp nhận giao thức Anthropic Native (/v1/messages).
// TiemAI tự động chuyển đổi 2 chiều OpenAI <-> Anthropic cho Cline, Kilo, Cursor...
// =========================================================================
if ($isGwaiKey) {
    // Ánh xạ Model an toàn cho Gwai Cloud (chỉ hỗ trợ danh sách model claude-* chuẩn của Gwai)
    if (str_contains($modelLower, 'opus')) {
        $gwaiModel = 'claude-opus-5-5';
    } elseif (str_contains($modelLower, 'haiku') || str_contains($modelLower, 'fable')) {
        $gwaiModel = 'claude-fable-5';
    } else {
        // Tự động map claude-3-5-sonnet, gpt-4o, gpt-5.6-terra, deepseek, cursor-small... sang claude-sonnet-5
        $gwaiModel = 'claude-sonnet-5';
    }

    if ($isAnthropicMessages) {
        // Khách gọi bằng chuẩn Anthropic Native (/v1/messages - Claude CLI / Anthropic SDK)
        $bodyJson['model'] = $gwaiModel;
        $anthropicPayloadJson = json_encode($bodyJson, JSON_UNESCAPED_UNICODE);
        $gwaiHeaders = [
            'Content-Type: application/json',
            'x-api-key: ' . $tokenKhach,
            'anthropic-version: 2023-06-01',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ];

        $ch = curl_init('https://1gw.gwai.cloud/v1/messages');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $anthropicPayloadJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $gwaiHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode ?: 200);
        echo $res;
        exit;
    }

    // Khách gọi bằng chuẩn OpenAI (/v1/chat/completions) -> CẦN ADAPTER CHUYỂN ĐỔI SANG ANTHROPIC
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
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ];

    if ($isStream) {
        // Chế độ SSE Streaming: Chuyển đổi Anthropic SSE events sang OpenAI SSE chunks trong thời gian thực
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $streamId = 'chatcmpl-' . bin2hex(random_bytes(12));
        $doneSent = false;
        $lineBuffer = '';

        $chG = curl_init('https://1gw.gwai.cloud/v1/messages');
        curl_setopt($chG, CURLOPT_POST, true);
        curl_setopt($chG, CURLOPT_POSTFIELDS, json_encode($anthropicPayload, JSON_UNESCAPED_UNICODE));
        curl_setopt($chG, CURLOPT_HTTPHEADER, $gwaiHeaders);
        curl_setopt($chG, CURLOPT_TIMEOUT, 300);

        curl_setopt($chG, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$lineBuffer, &$doneSent, $streamId, $gwaiModel) {
            $lineBuffer .= $chunk;
            $lines = explode("\n", $lineBuffer);
            $lineBuffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, 'event:') || str_starts_with($line, ':')) {
                    continue;
                }

                // Nếu upstream trả về lỗi JSON thay vì SSE
                if (str_starts_with($line, '{') && str_contains($line, '"error"')) {
                    $errObj = json_decode($line, true);
                    $errMsg = $errObj['error']['message'] ?? 'Lỗi từ Gateway đối tác Gwai Cloud';
                    $errChunk = [
                        'id' => $streamId,
                        'object' => 'chat.completion.chunk',
                        'created' => time(),
                        'model' => $gwaiModel,
                        'choices' => [['index' => 0, 'delta' => ['content' => "\n⚠️ [TiemAI Gateway] " . $errMsg . "\n"], 'finish_reason' => null]]
                    ];
                    echo "data: " . json_encode($errChunk, JSON_UNESCAPED_UNICODE) . "\n\n";
                    echo "data: " . json_encode(['id' => $streamId, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $gwaiModel, 'choices' => [['index' => 0, 'delta' => new stdClass(), 'finish_reason' => 'stop']]]) . "\n\n";
                    echo "data: [DONE]\n\n";
                    $doneSent = true;
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $jsonStr = trim(substr($line, 5));
                    if ($jsonStr === '[DONE]') {
                        echo "data: [DONE]\n\n";
                        $doneSent = true;
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
                                'choices' => [['index' => 0, 'delta' => ['content' => $text], 'finish_reason' => null]]
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
                            'choices' => [['index' => 0, 'delta' => new stdClass(), 'finish_reason' => 'stop']]
                        ];
                        echo "data: " . json_encode($chunkObj, JSON_UNESCAPED_UNICODE) . "\n\n";
                        echo "data: [DONE]\n\n";
                        $doneSent = true;
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

        // Bảo đảm Cline / Cursor luôn nhận được finish_reason: "stop" và data: [DONE]
        if (!$doneSent) {
            $finalChunk = [
                'id' => $streamId,
                'object' => 'chat.completion.chunk',
                'created' => time(),
                'model' => $gwaiModel,
                'choices' => [['index' => 0, 'delta' => new stdClass(), 'finish_reason' => 'stop']]
            ];
            echo "data: " . json_encode($finalChunk, JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }

        // Ghi nhật ký api_logs
        $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
        try {
            $logStmt = $pdo->prepare("INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Streaming SSE response', NOW())");
            $logStmt->execute([$userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null, substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, '/v1/chat/completions', $gwaiModel, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)]);
        } catch (Exception $e) {}
        exit;
    } else {
        // Chế độ Non-Streaming
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
        if ($httpCode >= 400 || empty($jsonG['content'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpCode ?: 400);
            echo $resG ?: json_encode(['error' => ['message' => 'Lỗi kết nối từ gateway Gwai Cloud', 'code' => $httpCode]]);
            exit;
        }

        $textOut = '';
        foreach ($jsonG['content'] as $b) {
            if (($b['type'] ?? '') === 'text') {
                $textOut .= $b['text'] ?? '';
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
                    'message' => ['role' => 'assistant', 'content' => $textOut],
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
        http_response_code(200);
        echo json_encode($openAiRes, JSON_UNESCAPED_UNICODE);

        $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
        try {
            $logStmt = $pdo->prepare("INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'JSON response', NOW())");
            $logStmt->execute([$userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null, substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, '/v1/chat/completions', $gwaiModel, $httpCode ?: 200, $doTreMs, mb_substr($bodyRaw, 0, 300)]);
        } catch (Exception $e) {}
        exit;
    }
}

// =========================================================================
// NHÁNH 2: KEY 64 KÝ TỰ HEX (sk-[0-9a-f]{64} - Taphoaai & Zplay)
// Taphoaai: Chuyên Claude models
// Zplay: Chuyên Codex / GPT models
// CƠ CHẾ CASCADE FAILOVER: Nếu gửi sang Gateway chính bị 401 (Invalid API Key),
// Proxy tự động chuyển tiếp ngay sang Gateway phụ! Không bao giờ để khách bị lỗi 401 oan!
// =========================================================================
if ($isHexKey) {
    $isCodexOrGpt = str_contains($prodNameLower, 'codex') || str_contains($prodNameLower, 'gpt') || str_contains($modelLower, 'gpt') || str_contains($modelLower, 'sol') || str_contains($modelLower, 'terra') || str_contains($modelLower, 'mini');

    if ($isCodexOrGpt) {
        $primaryUrl = 'https://zplay.io.vn/v1/chat/completions';
        $secondaryUrl = 'https://api.taphoaai.info.vn/v1/chat/completions';
        $primaryType = 'zplay';
    } else {
        $primaryUrl = $isAnthropicMessages ? 'https://api.taphoaai.info.vn/v1/messages' : 'https://api.taphoaai.info.vn/v1/chat/completions';
        $secondaryUrl = 'https://zplay.io.vn/v1/chat/completions';
        $primaryType = 'taphoaai';
    }

    // Hàm chuẩn hóa model theo từng Gateway
    $fnMapModel = function($rawM, $gatewayType) {
        $mLow = mb_strtolower($rawM, 'UTF-8');
        if ($gatewayType === 'taphoaai') {
            if (str_contains($mLow, 'opus')) return 'claude-opus-5.5';
            if (str_contains($mLow, 'haiku') || str_contains($mLow, 'fable')) return 'claude-fable-5';
            return 'claude-sonnet-5';
        } else {
            // Zplay
            if (str_contains($mLow, 'mini') || str_contains($mLow, 'cursor')) return 'gpt-5.4-mini';
            if (str_contains($mLow, 'luna')) return 'gpt-5.6-luna';
            if (str_contains($mLow, 'terra')) return 'gpt-5.6-terra';
            if (str_contains($mLow, '6.1') || str_contains($mLow, '6-sol')) return 'gpt-6.1-sol';
            if (str_contains($mLow, 'opus')) return 'claude-opus-5.5';
            if (str_contains($mLow, 'sonnet')) return 'claude-sonnet-5';
            return 'gpt-5.6-sol';
        }
    };

    // Hàm thực hiện gửi request tới một URL cụ thể
    $fnSendRequest = function($targetUrl, $gwType) use ($tokenKhach, $bodyJson, $isStream, $fnMapModel, $rawModel) {
        $bodyCopy = $bodyJson;
        $bodyCopy['model'] = $fnMapModel($rawModel, $gwType);
        $payload = json_encode($bodyCopy, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $tokenKhach,
            'x-api-key: ' . $tokenKhach,
            'anthropic-version: 2023-06-01',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept: ' . ($isStream ? 'text/event-stream' : 'application/json')
        ];

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $isStream ? 300 : 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        return ['ch' => $ch, 'model' => $bodyCopy['model']];
    };

    if ($isStream) {
        // STREAMING CHO 64-HEX KEY VỚI CASCADE FALLBACK
        $req1 = $fnSendRequest($primaryUrl, $primaryType);
        $ch1 = $req1['ch'];
        $modelDung = $req1['model'];

        $httpCode = 0;
        $streamHeadersSent = false;
        $doneSent = false;
        $headerBuffer = '';
        $bodyBuffer = '';

        curl_setopt($ch1, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$httpCode, &$headerBuffer) {
            $headerBuffer .= $line;
            if (preg_match('/^HTTP\/[12](?:\.[01])?\s+(\d{3})/i', $line, $m)) {
                $httpCode = (int)$m[1];
            }
            return strlen($line);
        });

        curl_setopt($ch1, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$streamHeadersSent, &$httpCode, &$bodyBuffer, &$doneSent) {
            // Nếu phát hiện lỗi (401 hoặc 429), gom lại để xử lý hoặc failover
            if ($httpCode >= 400) {
                $bodyBuffer .= $chunk;
                return strlen($chunk);
            }

            if (!$streamHeadersSent) {
                header('Content-Type: text/event-stream; charset=utf-8');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
                $streamHeadersSent = true;
            }

            echo $chunk;
            if (str_contains($chunk, '[DONE]')) {
                $doneSent = true;
            }
            if (ob_get_level() > 0) ob_flush();
            flush();
            return strlen($chunk);
        });

        curl_exec($ch1);
        $finalCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        curl_close($ch1);

        // NẾU GATEWAY 1 BỊ 401 INVALID KEY -> CASCADE SANG GATEWAY 2
        if ($finalCode1 === 401 && !$streamHeadersSent) {
            $secondaryType = ($primaryType === 'zplay') ? 'taphoaai' : 'zplay';
            $req2 = $fnSendRequest($secondaryUrl, $secondaryType);
            $ch2 = $req2['ch'];
            $modelDung = $req2['model'];
            $httpCode2 = 0;
            $bodyBuffer2 = '';

            curl_setopt($ch2, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$httpCode2) {
                if (preg_match('/^HTTP\/[12](?:\.[01])?\s+(\d{3})/i', $line, $m)) {
                    $httpCode2 = (int)$m[1];
                }
                return strlen($line);
            });

            curl_setopt($ch2, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$streamHeadersSent, &$httpCode2, &$bodyBuffer2, &$doneSent) {
                if ($httpCode2 >= 400) {
                    $bodyBuffer2 .= $chunk;
                    return strlen($chunk);
                }

                if (!$streamHeadersSent) {
                    header('Content-Type: text/event-stream; charset=utf-8');
                    header('Cache-Control: no-cache');
                    header('Connection: keep-alive');
                    header('X-Accel-Buffering: no');
                    $streamHeadersSent = true;
                }

                echo $chunk;
                if (str_contains($chunk, '[DONE]')) {
                    $doneSent = true;
                }
                if (ob_get_level() > 0) ob_flush();
                flush();
                return strlen($chunk);
            });

            curl_exec($ch2);
            $finalCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            $finalCode1 = $finalCode2;
            $bodyBuffer = $bodyBuffer2;
        }

        // Nếu cả hai gateway đều trả về lỗi (401 hoặc 429 Quota Exhausted)
        if ($finalCode1 >= 400) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            $streamId = 'chatcmpl-err-' . bin2hex(random_bytes(6));
            
            $thongBaoLoi = "⚠️ [TiemAI Gateway] ";
            if ($finalCode1 === 429 || str_contains($bodyBuffer, 'QUOTA_EXHAUSTED')) {
                $thongBaoLoi .= "Hạn mức (Quota) của Key này đã dùng hết. Vui lòng kiểm tra lại tại https://tiemai.io.vn/tra-cuu-key hoặc mua thêm gói mới.";
            } elseif ($finalCode1 === 401) {
                $thongBaoLoi .= "API Key không hợp lệ trên hệ thống. Vui lòng kiểm tra lại key của bạn tại https://tiemai.io.vn/tra-cuu-key.";
            } else {
                $thongBaoLoi .= "Lỗi từ Gateway đối tác (HTTP {$finalCode1}). Chi tiết: " . mb_substr($bodyBuffer, 0, 150);
            }

            $errChunk = [
                'id' => $streamId,
                'object' => 'chat.completion.chunk',
                'created' => time(),
                'model' => $modelDung,
                'choices' => [['index' => 0, 'delta' => ['content' => "\n{$thongBaoLoi}\n"], 'finish_reason' => null]]
            ];
            echo "data: " . json_encode($errChunk, JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: " . json_encode(['id' => $streamId, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $modelDung, 'choices' => [['index' => 0, 'delta' => new stdClass(), 'finish_reason' => 'stop']]]) . "\n\n";
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
            exit;
        }

        if (!$doneSent) {
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }

        $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
        try {
            $logStmt = $pdo->prepare("INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Streaming SSE response', NOW())");
            $logStmt->execute([$userInfo['id'] ?? null, $userInfo['email'] ?? null, $userInfo['name'] ?? null, substr($tokenKhach, 0, 10) . '***', $ipKhach, $isHttps, '/v1/chat/completions', $modelDung, 200, $doTreMs, mb_substr($bodyRaw, 0, 300)]);
        } catch (Exception $e) {}
        exit;
    } else {
        // NON-STREAMING CHO 64-HEX KEY VỚI CASCADE FALLBACK
        $req1 = $fnSendRequest($primaryUrl, $primaryType);
        $ch1 = $req1['ch'];
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        $res1 = curl_exec($ch1);
        $code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        curl_close($ch1);

        if ($code1 === 401) {
            $secondaryType = ($primaryType === 'zplay') ? 'taphoaai' : 'zplay';
            $req2 = $fnSendRequest($secondaryUrl, $secondaryType);
            $ch2 = $req2['ch'];
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            $res2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            $code1 = $code2;
            $res1 = $res2;
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code1 ?: 200);
        echo $res1;
        exit;
    }
}

// =========================================================================
// NHÁNH 3: CÁC KEY KHÁC HOẶC KEY MẶC ĐỊNH
// Chuyển tiếp tới nhà cung cấp mặc định hoặc cấu hình SUPPLIER_API_URL
// =========================================================================
$supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
$targetUrl = rtrim($supplier['api_url'] ?? SUPPLIER_API_URL, '/') . '/chat/completions';
$forwardKey = $tokenKhach;
if (!str_starts_with($tokenKhach, 'sk-') && !str_starts_with($tokenKhach, 'gzsk_')) {
    $forwardKey = $supplier['api_key'] ?? SUPPLIER_API_KEY;
}

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyRaw);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $forwardKey,
    'Accept: ' . ($isStream ? 'text/event-stream' : 'application/json')
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');
http_response_code($code ?: 200);
echo $resp;
exit;
