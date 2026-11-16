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
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // Thu thập tất cả các khóa bí mật SePay từ DB và .env (chống lỗi dán nhầm ô)
    $candidateKeys = [];
    $blacklistedValues = [
        '',
        'your_sepay_webhook_secret_here',
        'your_sepay_api_key_here',
        'YOUR_SEPAY_WEBHOOK_SECRET',
        'YOUR_SEPAY_API_KEY',
        'YOUR_SEPAY_API_TOKEN'
    ];

    // 1. Đọc từ .env
    $envSecret = trim((string)layGiaTriEnv('SEPAY_WEBHOOK_SECRET', ''));
    if (!in_array($envSecret, $blacklistedValues, true)) {
        $candidateKeys[] = $envSecret;
    }
    $envApiKey = trim((string)layGiaTriEnv('SEPAY_API_KEY', ''));
    if (!in_array($envApiKey, $blacklistedValues, true)) {
        $candidateKeys[] = $envApiKey;
    }

    // 2. Đọc từ bảng settings trong DB (Admin Cài Đặt)
    try {
        $stmtSec = $pdo->query("SELECT `value` FROM `settings` WHERE `key` IN ('sepay_webhook_secret', 'sepay_api_key')");
        while ($val = $stmtSec->fetchColumn()) {
            $val = trim((string)$val);
            if (!in_array($val, $blacklistedValues, true) && !in_array($val, $candidateKeys, true)) {
                $candidateKeys[] = $val;
            }
        }
    } catch (Exception $e) {}

    // Nếu hệ thống hoàn toàn chưa thiết lập bất kỳ khóa bí mật nào
    if (empty($candidateKeys)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Webhook authentication is not configured']);
        exit;
    }

    $xacThucThanhCong = false;

    // CÁCH 1: Xác thực bằng HMAC-SHA256 (Header X-SePay-Signature & X-SePay-Timestamp)
    if (!empty($signature)) {
        // Chống replay attack: Timestamp lệch quá 5 phút (300 giây) sẽ bị từ chối
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Request expired']);
            exit;
        }

        foreach ($candidateKeys as $key) {
            // Chuẩn 1 của SePay: sha256=hash_hmac('sha256', timestamp.raw_body, key)
            $expected1 = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $key);
            if (hash_equals($expected1, $signature)) {
                $xacThucThanhCong = true;
                break;
            }

            // Chuẩn 2 (dự phòng không tiền tố sha256=)
            $expected2 = hash_hmac('sha256', $timestamp . '.' . $body, $key);
            if (hash_equals($expected2, $signature)) {
                $xacThucThanhCong = true;
                break;
            }

            // Chuẩn 3 (dự phòng hash raw_body trực tiếp)
            $expected3 = 'sha256=' . hash_hmac('sha256', $body, $key);
            if (hash_equals($expected3, $signature)) {
                $xacThucThanhCong = true;
                break;
            }
        }

        if (!$xacThucThanhCong) {
            error_log("Webhook SePay: Invalid signature [Sig: {$signature}, Time: {$timestamp}]");
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid signature']);
            exit;
        }
    } 
    // CÁCH 2: Xác thực bằng API Key (Header Authorization: Apikey YOUR_KEY)
    elseif (!empty($authHeader)) {
        $prefix = 'Apikey ';
        if (str_starts_with($authHeader, $prefix)) {
            $clientKey = substr($authHeader, strlen($prefix));
            foreach ($candidateKeys as $key) {
                if (hash_equals($key, $clientKey)) {
                    $xacThucThanhCong = true;
                    break;
                }
            }
        }
        if (!$xacThucThanhCong) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Missing signature or authorization header']);
        exit;
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

        // Trích xuất mã đơn hàng (tiemai... hoặc VK...) hoặc mã nạp ví (tiemai + 5 số hoặc NAPQUY...)
        $maDonTimThay = null;
        $userIdNap = null;
        $codeNapViUsed = null;

        // 1. Kiểm tra mã nạp ví chuẩn mới: tiemai + đúng 5 số ngẫu nhiên (VD: tiemai58291)
        if (preg_match('/(?:tiemai)(\d{5})(?!\d)/i', $noiDungChuyenKhoan, $mNap) || preg_match('/^(?:tiemai)(\d{5})$/i', $maThanhToanSepay, $mNap)) {
            $codeNapViUsed = 'tiemai' . $mNap[1];
            $stmtFind = $pdo->prepare("SELECT `user_id` FROM `wallet_deposit_codes` WHERE LOWER(`code`) = LOWER(?) ORDER BY `id` DESC LIMIT 1");
            $stmtFind->execute([$codeNapViUsed]);
            $foundUid = $stmtFind->fetchColumn();
            if ($foundUid) {
                $userIdNap = (int)$foundUid;
            }
        }

        // 2. Fallback kiểm tra mã nạp ví kiểu cũ (NAPQUYxxxx hoặc NAPxxxx)
        if (!$userIdNap) {
            if (!empty($maThanhToanSepay) && preg_match('/^(?:NAPQUY|NAP)(\d+)$/i', $maThanhToanSepay, $m)) {
                $userIdNap = (int)$m[1];
            } elseif (preg_match('/(?:NAPQUY|NAP)(\d+)/i', $noiDungChuyenKhoan, $napMatches)) {
                $userIdNap = (int)$napMatches[1];
            }
        }

        // 3. Nếu không phải nạp ví, kiểm tra mã đơn hàng: tiemai + 16 ký tự/số HOẶC mã đơn cũ VKxxxxxx
        if (!$userIdNap) {
            if (!empty($maThanhToanSepay)) {
                if (preg_match('/^(tiemai\d{16}|tiemai[a-z0-9]{16}|VK[A-Z0-9]{4,16})$/i', $maThanhToanSepay, $m)) {
                    $maDonTimThay = $m[1];
                }
            }
            if (!$maDonTimThay) {
                if (preg_match('/(tiemai\d{16}|tiemai[a-z0-9]{16}|VK[A-Z0-9]{4,16})/i', $noiDungChuyenKhoan, $matches)) {
                    $maDonTimThay = $matches[1];
                }
            }
        }

        // ========================================================
        // TRƯỜNG HỢP A: NẠP TIỀN VÀO SỐ DƯ VÍ TÀI KHOẢN (NAPQUY...)
        // ========================================================
        if ($userIdNap && $soTienChuyen > 0) {
            // Lấy cấu hình số tiền nạp tối thiểu vào ví từ bảng settings
            $walletMinDeposit = 5000;
            try {
                $stmtMin = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = 'wallet_min_deposit' LIMIT 1");
                $stmtMin->execute();
                $valMin = $stmtMin->fetchColumn();
                if ($valMin !== false && $valMin !== null && is_numeric($valMin)) {
                    $walletMinDeposit = (float)$valMin;
                }
            } catch (Exception $e) {}

            // KIỂM TRA MỨC NẠP TỐI THIỂU: DƯỚI TỐI THIỂU -> KHÔNG CỘNG TIỀN VÀO VÍ
            if ($soTienChuyen < $walletMinDeposit) {
                try {
                    if ($codeNapViUsed) {
                        $pdo->prepare("UPDATE `wallet_deposit_codes` SET `status` = 'REJECTED_UNDER_MIN', `amount` = ? WHERE LOWER(`code`) = LOWER(?)")
                            ->execute([$soTienChuyen, $codeNapViUsed]);
                    }

                    // Lấy tên người dùng để bắn thông báo Telegram
                    $stmtUser = $pdo->prepare("SELECT `id`, `name`, `balance` FROM `users` WHERE `id` = ?");
                    $stmtUser->execute([$userIdNap]);
                    $user = $stmtUser->fetch();
                    $userName = $user ? $user['name'] : "User #{$userIdNap}";

                    // Bắn cảnh báo Telegram cho Admin về việc nạp dưới hạn mức tối thiểu
                    $noiDungTele = "⚠️ <b>GIAO DỊCH NẠP VÍ DƯỚI MỨC TỐI THIỂU!</b>\n";
                    $noiDungTele .= "👤 Thành viên: <b>{$userName}</b> (#{$userIdNap})\n";
                    $noiDungTele .= "💵 Số tiền chuyển: <b>" . number_format($soTienChuyen, 0, ',', '.') . " VNĐ</b>\n";
                    $noiDungTele .= "⛔ Hạn mức tối thiểu: <b>" . number_format($walletMinDeposit, 0, ',', '.') . " VNĐ</b>\n";
                    $noiDungTele .= "❌ Trạng thái: <b>HỆ THỐNG KHÔNG CỘNG TIỀN VÀO VÍ</b>\n";
                    $noiDungTele .= "🏦 Ngân hàng: <b>{$data['gateway']}</b> (Mã GD SePay: {$data['id']})\n";
                    $noiDungTele .= "📝 Nội dung: <i>{$noiDungChuyenKhoan}</i>";
                    guiThongBaoTelegram($noiDungTele, 'finance');
                } catch (Exception $e) {
                    error_log("Lỗi ghi nhận nạp ví dưới tối thiểu: " . $e->getMessage());
                }
            } else {
                // SỐ TIỀN BẰNG HOẶC LỚN HƠN TỐI THIỂU -> CỘNG TIỀN VÀO VÍ
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

                        // 3. Nếu dùng mã nạp ví ngẫu nhiên tiemai + 5 số, cập nhật trạng thái đã nạp
                        if ($codeNapViUsed) {
                            $pdo->prepare("UPDATE `wallet_deposit_codes` SET `status` = 'COMPLETED', `amount` = ? WHERE LOWER(`code`) = LOWER(?)")
                                ->execute([$soTienChuyen, $codeNapViUsed]);
                        }

                        $pdo->commit();

                        // Bắn thông báo Telegram cho Admin
                        $noiDungTele = "💰 <b>NẠP TIỀN TỰ ĐỘNG THÀNH CÔNG!</b>\n";
                        $noiDungTele .= "👤 Thành viên: <b>{$user['name']}</b> (#{$userIdNap})\n";
                        $noiDungTele .= "💵 Số tiền nạp: <b>" . number_format($soTienChuyen, 0, ',', '.') . " VNĐ</b>\n";
                        $noiDungTele .= "🏦 Ngân hàng: <b>{$data['gateway']}</b>\n";
                        $noiDungTele .= "📈 Số dư mới: <b>" . number_format($soDuMoi, 0, ',', '.') . " VNĐ</b>";
                        guiThongBaoTelegram($noiDungTele, 'finance');
                    } else {
                        $pdo->rollBack();
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("Lỗi nạp tiền ví SePay: " . $e->getMessage());
                }
            }
        }

        // ========================================================
        // TRƯỜNG HỢP B: THANH TOÁN ĐƠN HÀNG TRỰC TIẾP (tiemai... hoặc VK...)
        // ========================================================
        if ($maDonTimThay && $soTienChuyen > 0) {
            // Câu lệnh UPDATE ATOMIC: Chỉ đổi status từ PENDING sang PAID nếu số tiền nhận đủ hoặc thừa
            // Đảm bảo an toàn tuyệt đối khi 2 request đến cùng lúc (chống race condition)
            $stmtUpdateDon = $pdo->prepare("
                UPDATE `orders` 
                SET `status` = 'PAID', `paid_at` = NOW() 
                WHERE (LOWER(`order_code`) = LOWER(:code) OR `order_code` = :code)
                  AND `status` = 'PENDING' 
                  AND `total_amount` <= :amount
            ");
            $stmtUpdateDon->execute([
                'code'   => $maDonTimThay,
                'amount' => $soTienChuyen
            ]);

            // Nếu cập nhật thành công (rowCount > 0), gọi bộ điều phối để mua/giao Key ngay
            if ($stmtUpdateDon->rowCount() > 0) {
                // Lấy lại order_code chính xác trong DB
                $stmtGetRealCode = $pdo->prepare("SELECT `order_code` FROM `orders` WHERE LOWER(`order_code`) = LOWER(?) LIMIT 1");
                $stmtGetRealCode->execute([$maDonTimThay]);
                $realCode = $stmtGetRealCode->fetchColumn() ?: $maDonTimThay;

                $boDieuPhoi = new BoDieuPhoiDonHang($pdo);
                $ketQuaGiaoKey = $boDieuPhoi->xuLyDonHang($realCode);

                // Bắn thông báo Telegram cho Admin
                $noiDungTele = "⚡ <b>ĐƠN HÀNG MỚI ĐÃ THANH TOÁN VIETQR!</b>\n";
                $noiDungTele .= "📦 Mã đơn: <code>{$maDonTimThay}</code>\n";
                $noiDungTele .= "💵 Số tiền: <b>" . number_format($soTienChuyen, 0, ',', '.') . " VNĐ</b>\n";
                $noiDungTele .= "🏦 Cổng: <b>{$data['gateway']}</b> (ID: {$data['id']})\n";
                $noiDungTele .= "🚀 Trạng thái giao key: " . (!empty($ketQuaGiaoKey['thanh_cong']) ? '<b>ĐÃ GIAO THÀNH CÔNG</b>' : '<b>LỖI NGUỒN / ĐÃ HOÀN TIỀN</b>');
guiThongBaoTelegram($noiDungTele, 'order');
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
