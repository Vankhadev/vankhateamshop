<?php
// ========================================================
// FILE XỬ LÝ WEBHOOK SEPAY ĐẠT CHUẨN PRODUCTION (PHP 8 + PDO + MYSQL)
// vankhateamshop - Xác thực HMAC-SHA256 / API Key, chống trùng giao dịch Race-Safe với INSERT IGNORE
// Tự động kích hoạt giao Key cho đơn hàng và cộng tiền nạp ví tự động 24/7
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu theo tài liệu SePay Developer
// ========================================================

// 1. Cấu hình nhật ký lỗi và tiêu đề phản hồi chuẩn JSON
ini_set('log_errors', '1');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dispatcher.php';
require_once __DIR__ . '/telegram.php';

try {
    // 2. Đọc raw body (bytes gốc chưa parse) để bảo đảm chữ ký HMAC khớp chính xác
    $body = file_get_contents('php://input');
    if ($body === false || $body === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Empty body']);
        exit;
    }

    // 3. XÁC THỰC BẢO MẬT (Hỗ trợ cả HMAC-SHA256 chuẩn cao nhất và API Key)
    $signature = $_SERVER['HTTP_X_SEPAY_SIGNATURE'] ?? '';
    $timestamp = (int)($_SERVER['HTTP_X_SEPAY_TIMESTAMP'] ?? 0);
    $webhookSecret = layGiaTriEnv('SEPAY_WEBHOOK_SECRET', '');
    // Nếu trong .env chưa có, kiểm tra bảng settings được cấu hình trong Admin
    if (empty($webhookSecret) || $webhookSecret === 'your_sepay_webhook_secret_here') {
        try {
            $stmtSec = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = 'sepay_webhook_secret' LIMIT 1");
            $stmtSec->execute();
            $val = $stmtSec->fetchColumn();
            if (!empty($val)) {
                $webhookSecret = trim($val);
            }
        } catch (Exception $e) {}
    }
    $apiKey = layGiaTriEnv('SEPAY_API_KEY', '');
    if (empty($apiKey) || $apiKey === 'YOUR_SEPAY_API_KEY') {
        try {
            $stmtKey = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = 'sepay_api_key' LIMIT 1");
            $stmtKey->execute();
            $valKey = $stmtKey->fetchColumn();
            if (!empty($valKey)) {
                $apiKey = trim($valKey);
            }
        } catch (Exception $e) {}
    }
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // CÁCH 1: Xác thực bằng HMAC-SHA256 (Khuyến nghị chuẩn Production)
    if (!empty($signature) && !empty($webhookSecret) && $webhookSecret !== 'your_sepay_webhook_secret_here') {
        // Chống replay attack: Timestamp lệch quá 5 phút (300 giây) sẽ bị từ chối
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Request expired']);
            exit;
        }

        // Tái tạo chữ ký và so sánh thời gian không đổi (Constant-time hash_equals)
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $webhookSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid signature']);
            exit;
        }
    } 
    // CÁCH 2: Xác thực bằng API Key (Header Authorization: Apikey YOUR_KEY)
    elseif (!empty($authHeader) && !empty($apiKey) && $apiKey !== 'YOUR_SEPAY_API_KEY') {
        $prefix = 'Apikey ';
        if (!str_starts_with($authHeader, $prefix) || !hash_equals($apiKey, substr($authHeader, strlen($prefix)))) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }

    // 4. Giải mã dữ liệu JSON từ SePay
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payload']);
        exit;
    }

    // 5. CHỐNG TRÙNG GIAO DỊCH Ở TẦNG CƠ SỞ DỮ LIỆU: INSERT IGNORE (Race-Safe)
    // Cột sepay_id là UNIQUE, nếu SePay retry hoặc gửi lại cùng 1 giao dịch,
    // INSERT IGNORE sẽ lặng lẽ bỏ qua bản ghi trùng mà không gây lỗi hoặc cộng tiền lặp lại.
    $sql = 'INSERT IGNORE INTO `sepay_transactions`
        (`sepay_id`, `gateway`, `transaction_date`, `account_number`, `sub_account`,
         `code`, `amount_in`, `amount_out`, `accumulated`, `content`, `reference_code`, `body`)
        VALUES (:id, :gateway, :date, :account, :sub,
         :code, :in, :out, :accumulated, :content, :ref, :body)';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id'          => $data['id'],
        'gateway'     => $data['gateway'] ?? 'ACB',
        'date'        => !empty($data['transactionDate']) ? $data['transactionDate'] : date('Y-m-d H:i:s'),
        'account'     => $data['accountNumber'] ?? '',
        'sub'         => $data['subAccount'] ?? '',
        'code'        => $data['code'] ?? null,
        'in'          => (($data['transferType'] ?? 'in') === 'in') ? (int)($data['transferAmount'] ?? 0) : 0,
        'out'         => (($data['transferType'] ?? '') === 'out') ? (int)($data['transferAmount'] ?? 0) : 0,
        'accumulated' => (int)($data['accumulated'] ?? 0),
        'content'     => $data['content'] ?? '',
        'ref'         => $data['referenceCode'] ?? '',
        'body'        => $body
    ]);

    // Nếu rowCount === 0 nghĩa là giao dịch này đã được ghi nhận và xử lý trước đó.
    // Trả về ngay HTTP 200 {"success": true} để SePay không tiếp tục retry.
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => true]);
        exit;
    }

    // 6. XỬ LÝ NGHIỆP VỤ BÁN HÀNG & NẠP VÍ (Chỉ chạy khi giao dịch lần đầu được lưu thành công)
    if (($data['transferType'] ?? 'in') === 'in') {
        $soTienChuyen = (float)($data['transferAmount'] ?? 0);
        $noiDungChuyenKhoan = trim($data['content'] ?? '');
        $maThanhToanSepay = trim($data['code'] ?? '');

        // Trích xuất mã đơn hàng VKxxxxxx hoặc mã nạp ví NAPQUYxxxx / NAPxxxx
        $maDonTimThay = null;
        $userIdNap = null;

        // Ưu tiên kiểm tra trường code do SePay trích xuất tự động
        if (!empty($maThanhToanSepay)) {
            if (preg_match('/^(VK[A-Z0-9]{4,16})$/i', $maThanhToanSepay, $m)) {
                $maDonTimThay = strtoupper($m[1]);
            } elseif (preg_match('/^(?:NAPQUY|NAP)(\d+)$/i', $maThanhToanSepay, $m)) {
                $userIdNap = (int)$m[1];
            }
        }

        // Nếu trường code không khớp, quét tìm trực tiếp trong nội dung chuyển khoản gốc (content)
        if (!$maDonTimThay && !$userIdNap) {
            if (preg_match('/(VK[A-Z0-9]{4,16})/i', $noiDungChuyenKhoan, $matches)) {
                $maDonTimThay = strtoupper($matches[1]);
            } elseif (preg_match('/(?:NAPQUY|NAP)(\d+)/i', $noiDungChuyenKhoan, $napMatches)) {
                $userIdNap = (int)$napMatches[1];
            }
        }

        // ========================================================
        // TRƯỜNG HỢP A: NẠP TIỀN VÀO SỐ DƯ VÍ TÀI KHOẢN (NAPQUY...)
        // ========================================================
        if ($userIdNap && $soTienChuyen > 0) {
            $pdo->beginTransaction();
            try {
                $stmtUser = $pdo->prepare("SELECT `id`, `name`, `email`, `balance` FROM `users` WHERE `id` = ? FOR UPDATE");
                $stmtUser->execute([$userIdNap]);
                $user = $stmtUser->fetch();

                if ($user) {
                    $soDuCu = (float)$user['balance'];
                    $soDuMoi = $soDuCu + $soTienChuyen;

                    // 1. Cập nhật số dư ví
                    $pdo->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ?")->execute([$soDuMoi, $userIdNap]);

                    // 2. Ghi nhật ký biến động số dư nội bộ
                    $stmtTx = $pdo->prepare("
                        INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `note`)
                        VALUES (?, 'DEPOSIT', ?, ?, ?, ?)
                    ");
                    $stmtTx->execute([
                        $userIdNap,
                        $soTienChuyen,
                        $soDuCu,
                        $soDuMoi,
                        "Nạp tiền tự động VietQR {$data['gateway']} (Mã GD SePay: {$data['id']})"
                    ]);

                    $pdo->commit();

                    // Bắn thông báo Telegram cho Admin
                    $noiDungTele = "💰 <b>NẠP TIỀN TỰ ĐỘNG THÀNH CÔNG!</b>\n";
                    $noiDungTele .= "👤 Thành viên: <b>{$user['name']}</b> (#{$userIdNap})\n";
                    $noiDungTele .= "💵 Số tiền nạp: <b>" . number_format($soTienChuyen, 0, ',', '.') . " VNĐ</b>\n";
                    $noiDungTele .= "🏦 Ngân hàng: <b>{$data['gateway']}</b>\n";
                    $noiDungTele .= "📈 Số dư mới: <b>" . number_format($soDuMoi, 0, ',', '.') . " VNĐ</b>";
                    guiThongBaoTelegram($noiDungTele);
                } else {
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Lỗi nạp tiền ví SePay: " . $e->getMessage());
            }
        }

        // ========================================================
        // TRƯỜNG HỢP B: THANH TOÁN ĐƠN HÀNG TRỰC TIẾP (VK...)
        // ========================================================
        if ($maDonTimThay && $soTienChuyen > 0) {
            // Câu lệnh UPDATE ATOMIC: Chỉ đổi status từ PENDING sang PAID nếu số tiền nhận đủ hoặc thừa
            // Đảm bảo an toàn tuyệt đối khi 2 request đến cùng lúc (chống race condition)
            $stmtUpdateDon = $pdo->prepare("
                UPDATE `orders` 
                SET `status` = 'PAID', `paid_at` = NOW() 
                WHERE `order_code` = :code 
                  AND `status` = 'PENDING' 
                  AND `total_amount` <= :amount
            ");
            $stmtUpdateDon->execute([
                'code'   => $maDonTimThay,
                'amount' => $soTienChuyen
            ]);

            // Nếu cập nhật thành công (rowCount > 0), gọi bộ điều phối để mua/giao Key ngay
            if ($stmtUpdateDon->rowCount() > 0) {
                $boDieuPhoi = new BoDieuPhoiDonHang($pdo);
                $ketQuaGiaoKey = $boDieuPhoi->xuLyDonHang($maDonTimThay);

                // Bắn thông báo Telegram cho Admin
                $noiDungTele = "⚡ <b>ĐƠN HÀNG MỚI ĐÃ THANH TOÁN VIETQR!</b>\n";
                $noiDungTele .= "📦 Mã đơn: <code>{$maDonTimThay}</code>\n";
                $noiDungTele .= "💵 Số tiền: <b>" . number_format($soTienChuyen, 0, ',', '.') . " VNĐ</b>\n";
                $noiDungTele .= "🏦 Cổng: <b>{$data['gateway']}</b> (ID: {$data['id']})\n";
                $noiDungTele .= "🚀 Trạng thái giao key: " . (!empty($ketQuaGiaoKey['thanh_cong']) ? '<b>ĐÃ GIAO THÀNH CÔNG</b>' : '<b>LỖI NGUỒN / ĐÃ HOÀN TIỀN</b>');
                guiThongBaoTelegram($noiDungTele);
            }
        }
    }

    // 7. Hoàn tất phản hồi hợp lệ cho SePay: HTTP 200 kèm {"success": true}
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    error_log('SePay webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal error']);
}
