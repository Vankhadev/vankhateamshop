<?php
// ========================================================
// API ĐỐI TÁC: MUA HÀNG TỰ ĐỘNG & NHẬN TÀI KHOẢN/KEY NGAY
// vankhateamshop - POST /api/partner/v1/purchase.php
// Hỗ trợ Idempotency Key, chống trừ tiền 2 lần, tự động Fallback
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/config.php';
require_once __DIR__ . '/../../../backend/db.php';
require_once __DIR__ . '/../../../backend/security.php';
require_once __DIR__ . '/../../../backend/dispatcher.php';
require_once __DIR__ . '/../../../backend/telegram.php';

$thoiDiemBatDau = microtime(true);
$ipKhach = layIpNguoiDung();
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 1 : 0;

// 1. Kiểm tra phương thức
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errorCode' => 'METHOD_NOT_ALLOWED', 'detail' => 'Chỉ hỗ trợ phương thức POST']);
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

$stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `reseller_key` = ? AND `is_reseller` = 1 AND `is_active` = 1 LIMIT 1");
$stmtUser->execute([$apiKey]);
$reseller = $stmtUser->fetch();

if (!$reseller) {
    http_response_code(401);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_API_KEY', 'detail' => 'Key sai hoặc đã bị thu hồi']);
    exit;
}

// 3. Đọc dữ liệu JSON từ Body
$inputJson = file_get_contents('php://input');
$body = json_decode($inputJson, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_BODY', 'detail' => 'Body không phải JSON hợp lệ']);
    exit;
}

$productIdSku = trim($body['product_id'] ?? '');
$quantity = (int)($body['quantity'] ?? 1);
$idempotencyKey = trim($body['idempotency_key'] ?? '');
$customerEmail = trim($body['customer_email'] ?? $reseller['email']);

// 4. Kiểm tra hợp lệ tham số
if (empty($productIdSku)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_PRODUCT_ID', 'detail' => 'Thiếu mã SKU product_id']);
    exit;
}

if ($quantity < 1 || $quantity > 1000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_QUANTITY', 'detail' => 'Số lượng phải từ 1 đến 1000']);
    exit;
}

if (empty($idempotencyKey) || strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 128) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errorCode' => 'INVALID_IDEMPOTENCY_KEY', 'detail' => 'idempotency_key phải từ 8 đến 128 ký tự']);
    exit;
}

// 5. Chống trừ tiền lần hai (Kiểm tra Idempotency Key)
$stmtIdem = $pdo->prepare("
    SELECT o.*, p.slug AS product_slug, c.slug AS cat_slug 
    FROM `orders` o
    JOIN `products` p ON o.product_id = p.id
    JOIN `categories` c ON p.category_id = c.id
    WHERE o.user_id = ? AND o.order_code = ?
");
$maDonIdem = 'IDEM_' . md5($reseller['id'] . '_' . $idempotencyKey);
$stmtIdem->execute([$reseller['id'], $maDonIdem]);
$donCu = $stmtIdem->fetch();

if ($donCu) {
    // Trả lại đơn cũ kèm replay: true
    $taiKhoanTraVe = !empty($donCu['delivered_data']) ? json_decode($donCu['delivered_data'], true) : [];
    echo json_encode([
        'success'           => true,
        'replay'            => true,
        'walletCurrency'    => 'VND',
        'orderCode'         => $donCu['order_code'],
        'productType'       => $donCu['cat_slug'] ?? 'api',
        'quantity'          => (int)$donCu['quantity'],
        'amount'            => (int)$donCu['total_amount'],
        'amountText'        => number_format($donCu['total_amount'], 0, ',', '.') . ' VND',
        'balance'           => (int)$reseller['balance'],
        'balanceText'       => number_format($reseller['balance'], 0, ',', '.') . ' VND',
        'deliveredAccounts' => $taiKhoanTraVe
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 6. Tìm sản phẩm theo SKU
$stmtSp = $pdo->prepare("SELECT p.*, c.slug AS cat_slug FROM `products` p JOIN `categories` c ON p.category_id = c.id WHERE p.sku = ?");
$stmtSp->execute([$productIdSku]);
$product = $stmtSp->fetch();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errorCode' => 'PRODUCT_NOT_FOUND', 'detail' => 'Không tìm thấy sản phẩm với SKU ' . $productIdSku]);
    exit;
}

if (!$product['is_active']) {
    http_response_code(409);
    echo json_encode(['success' => false, 'errorCode' => 'PRODUCT_INACTIVE', 'detail' => 'Sản phẩm hiện đang tạm dừng bán']);
    exit;
}

// 7. Tính tiền theo chiết khấu đại lý
$chietKhau = (float)$reseller['reseller_discount'];
$donGiaDaiLy = round((float)$product['price'] * (1 - ($chietKhau / 100)));
$tongTienPhaiTra = $donGiaDaiLy * $quantity;

try {
    $pdo->beginTransaction();

    // Khóa dòng user để kiểm tra số dư an toàn
    $stmtLock = $pdo->prepare("SELECT balance FROM `users` WHERE `id` = ? FOR UPDATE");
    $stmtLock->execute([$reseller['id']]);
    $soDuHienTai = (float)$stmtLock->fetchColumn();

    if ($soDuHienTai < $tongTienPhaiTra) {
        $pdo->rollBack();
        http_response_code(402);
        echo json_encode([
            'success'   => false,
            'errorCode' => 'INSUFFICIENT_BALANCE',
            'detail'    => 'Số dư ví không đủ. Cần ' . number_format($tongTienPhaiTra, 0, ',', '.') . ' VND, số dư hiện tại ' . number_format($soDuHienTai, 0, ',', '.') . ' VND.'
        ]);
        exit;
    }

    // Trừ số dư ví đại lý
    $soDuMoi = $soDuHienTai - $tongTienPhaiTra;
    $pdo->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ?")->execute([$soDuMoi, $reseller['id']]);

    // Tạo đơn hàng lưu mã Idempotency
    $maDonHang = 'VK' . date('ymd') . strtoupper(substr(md5(uniqid()), 0, 6));
    $stmtOrder = $pdo->prepare("
        INSERT INTO `orders` (`order_code`, `user_id`, `product_id`, `customer_email`, `quantity`, `total_amount`, `status`, `ip_address`)
        VALUES (?, ?, ?, ?, ?, ?, 'PAID', ?)
    ");
    $stmtOrder->execute([$maDonHang, $reseller['id'], $product['id'], $customerEmail, $quantity, $tongTienPhaiTra, $ipKhach]);
    $orderId = $pdo->lastInsertId();

    // Lưu mã phụ để kiểm tra replay idempotency
    $pdo->prepare("
        INSERT INTO `orders` (`order_code`, `user_id`, `product_id`, `customer_email`, `quantity`, `total_amount`, `status`, `ip_address`)
        VALUES (?, ?, ?, ?, ?, ?, 'PAID', ?)
    ")->execute([$maDonIdem, $reseller['id'], $product['id'], $customerEmail, $quantity, $tongTienPhaiTra, $ipKhach]);

    // Ghi nhật ký giao dịch ví
    $pdo->prepare("
        INSERT INTO `transactions` (`user_id`, `order_id`, `type`, `amount`, `balance_before`, `balance_after`, `note`)
        VALUES (?, ?, 'PURCHASE', ?, ?, ?, ?)
    ")->execute([$reseller['id'], $orderId, $tongTienPhaiTra, $soDuHienTai, $soDuMoi, "Đại lý mua SKU {$productIdSku} (#{$maDonHang})"]);

    $pdo->commit();

    // 8. Kích hoạt bộ điều phối giao hàng tự động
    $boDieuPhoi = new BoDieuPhoiDonHang($pdo);
    $ketQuaGiao = $boDieuPhoi->xuLyDonHang($maDonHang);

    $deliveredAccounts = [];
    if (!empty($ketQuaGiao['delivered_data'])) {
        $dataParsed = json_decode($ketQuaGiao['delivered_data'], true);
        if (is_array($dataParsed)) {
            $deliveredAccounts = $dataParsed;
        } else {
            $deliveredAccounts = [['key' => $ketQuaGiao['delivered_data']]];
        }
    }

    // Không tự sinh key giả và không đánh dấu hoàn tất nếu nhà cung cấp chưa giao hàng.
    if (empty($deliveredAccounts)) {
        $pdo->prepare("UPDATE `orders` SET `status` = 'PROCESSING' WHERE `order_code` IN (?, ?)")->execute([$maDonHang, $maDonIdem]);
        http_response_code(502);
        echo json_encode(['success' => false, 'errorCode' => 'DELIVERY_PENDING', 'detail' => 'Đã nhận đơn và trừ tiền, nhưng sản phẩm đang chờ nhà cung cấp giao.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Chỉ đánh dấu hoàn tất khi có dữ liệu giao hàng thực tế.
    $jsonDelivered = json_encode($deliveredAccounts, JSON_UNESCAPED_UNICODE);
    $pdo->prepare("UPDATE `orders` SET `delivered_data` = ?, `status` = 'COMPLETED' WHERE `order_code` IN (?, ?)")->execute([$jsonDelivered, $maDonHang, $maDonIdem]);

    $doTreMs = round((microtime(true) - $thoiDiemBatDau) * 1000);
    $logStmt = $pdo->prepare("
        INSERT INTO `api_logs` (`user_id`, `customer_email`, `customer_name`, `api_key_used`, `ip_address`, `is_https`, `endpoint`, `model`, `http_status`, `latency_ms`, `request_body`, `response_summary`, `created_at`)
        VALUES (?, ?, ?, ?, ?, ?, '/api/partner/v1/purchase.php', ?, 200, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $reseller['id'], $reseller['email'], $reseller['name'], hash('sha256', $apiKey), $ipKhach, $isHttps,
        $productIdSku, $doTreMs, $inputJson, "Mua thành công đơn {$maDonHang}"
    ]);

    // Gửi thông báo Telegram tới nhóm Đơn hàng
    guiThongBaoDonHang([
        'ma_don'         => $maDonHang,
        'ten_san_pham'   => $product['name'],
        'so_luong'       => $quantity,
        'tong_tien'      => $tongTienPhaiTra,
        'email_khach'    => "Đại lý: {$reseller['email']}" . ($customerEmail ? " (Khách: {$customerEmail})" : ''),
        'phuong_thuc'    => 'API Đại Lý',
        'trang_thai_key' => 'Đã giao key qua API'
    ]);

    echo json_encode([
        'success'           => true,
        'walletCurrency'    => 'VND',
        'orderCode'         => $maDonHang,
        'productType'       => $product['cat_slug'] ?? 'api',
        'quantity'          => $quantity,
        'amount'            => (int)$tongTienPhaiTra,
        'amountText'        => number_format($tongTienPhaiTra, 0, ',', '.') . ' VND',
        'balance'           => (int)$soDuMoi,
        'balanceText'       => number_format($soDuMoi, 0, ',', '.') . ' VND',
        'deliveredAccounts' => $deliveredAccounts
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('Partner purchase error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'errorCode' => 'INTERNAL_ERROR', 'detail' => 'Lỗi hệ thống tạm thời. Vui lòng thử lại sau.']);
}
