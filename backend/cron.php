<?php
// ========================================================
// TIẾN TRÌNH TỰ ĐỘNG HÓA CHẠY ĐỊNH KỲ (CRONJOB)
// vankhateamshop - Quét đơn chờ, kiểm tra ví nguồn & cảnh báo Telegram
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

// Thiết lập chạy qua CLI hoặc Web có kèm token bí mật
if (php_sapi_name() !== 'cli') {
    $tokenSecret = $_GET['secret'] ?? '';
    // Mặc định bảo vệ cronjob bằng secret key
    if ($tokenSecret !== 'VK_CRON_SECRET_2026') {
        http_response_code(403);
        die("Truy cập bị từ chối: Sai secret token.");
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/supplier_api.php';
require_once __DIR__ . '/telegram.php';

echo "[" . date('Y-m-d H:i:s') . "] BẮT ĐẦU CHẠY CRONJOB TỰ ĐỘNG...\n";

// 1. KIỂM TRA SỐ DƯ TẤT CẢ CÁC NHÀ CUNG CẤP VÀ CẢNH BÁO NẾU SẮP HẾT TIỀN
$suppliers = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1")->fetchAll();
foreach ($suppliers as $sup) {
    echo "-> Đang kiểm tra số dư nhà cung cấp: {$sup['name']}... ";
    try {
        $api = new NhaCungCapApi($sup['api_url'], $sup['api_key']);
        $res = $api->laySoDuVi();
        if ($res['success']) {
            $soDu = (float)$res['balance'];
            $pdo->prepare("UPDATE `suppliers` SET `balance` = ? WHERE `id` = ?")->execute([$soDu, $sup['id']]);
            echo "Số dư: " . number_format($soDu) . "đ.\n";

            // Kiểm tra nếu dưới ngưỡng cảnh báo
            if ($soDu <= (float)$sup['min_balance_alert']) {
                $thongDiep = "⚠️ <b>CẢNH BÁO SỐ DƯ VÍ NGUỒN THẤP!</b>\n";
                $thongDiep .= "🏢 Nhà Cung Cấp: <b>{$sup['name']}</b>\n";
                $thongDiep .= "💰 Số dư hiện tại: <b>" . number_format($soDu, 0, ',', '.') . " VNĐ</b>\n";
                $thongDiep .= "🚨 Ngưỡng cảnh báo: <b>" . number_format($sup['min_balance_alert'], 0, ',', '.') . " VNĐ</b>\n";
                $thongDiep .= "👉 Vui lòng nạp thêm tiền ngay để tránh gián đoạn đơn hàng!";
                guiThongBaoTelegram($thongDiep);
                ghiNhatKyHeThong('WARNING', 'CRON_BALANCE_LOW', "Số dư ví {$sup['name']} thấp: " . number_format($soDu) . "đ");
            }
        } else {
            echo "Lỗi: " . ($res['message'] ?? 'Không rõ') . "\n";
        }
    } catch (Exception $e) {
        echo "Lỗi ngoại lệ: " . $e->getMessage() . "\n";
    }
}

// 2. HỦY CÁC ĐƠN HÀNG PENDING QUÁ 2 GIỜ KHÔNG THANH TOÁN
$stmtHuyDon = $pdo->prepare("
    UPDATE `orders` 
    SET `status` = 'FAILED', `error_log` = 'Hết thời gian thanh toán (Quá 2 giờ)'
    WHERE `status` = 'PENDING' AND `created_at` < DATE_SUB(NOW(), INTERVAL 2 HOUR)
");
$stmtHuyDon->execute();
$soDonHuy = $stmtHuyDon->rowCount();
if ($soDonHuy > 0) {
    echo "-> Đã hủy {$soDonHuy} đơn hàng treo quá hạn.\n";
    ghiNhatKyHeThong('INFO', 'CRON_CANCEL_ORDERS', "Đã tự động hủy {$soDonHuy} đơn hàng hết hạn");
}

// 3. DỌN DẸP NHẬT KÝ HỆ THỐNG CŨ QUÁ 30 NGÀY
$stmtDonLog = $pdo->prepare("DELETE FROM `system_logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmtDonLog->execute();
$soLogXoa = $stmtDonLog->rowCount();
if ($soLogXoa > 0) {
    echo "-> Đã dọn dẹp {$soLogXoa} bản ghi log cũ hơn 30 ngày.\n";
}

// 4. TỰ ĐỘNG ĐỐI SOÁT GIAO DỊCH SEPAY (RECONCILIATION - CHỐNG SÓT WEBHOOK)
$sepayToken = layGiaTriEnv('SEPAY_API_TOKEN', '');
if (!empty($sepayToken) && $sepayToken !== 'YOUR_SEPAY_API_TOKEN' && $sepayToken !== 'your_sepay_api_token_for_reconciliation_here') {
    echo "-> Đang chạy đối soát giao dịch SePay 24 giờ gần nhất... ";
    try {
        $dateFrom = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $dateTo = date('Y-m-d H:i:s');
        $urlSepay = 'https://userapi.sepay.vn/v2/transactions?' . http_build_query([
            'transaction_date_from' => $dateFrom,
            'transaction_date_to'   => $dateTo,
            'per_page'              => 100,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'header'  => "Authorization: Bearer {$sepayToken}\r\nContent-Type: application/json\r\n",
                'timeout' => 15
            ]
        ]);
        $resp = @file_get_contents($urlSepay, false, $ctx);
        if ($resp) {
            $jsonRes = json_decode($resp, true);
            $listTx = $jsonRes['data'] ?? ($jsonRes['transactions'] ?? []);
            $soGiaoDichBu = 0;

            $insertStmt = $pdo->prepare("
                INSERT IGNORE INTO `sepay_transactions`
                (`sepay_id`, `gateway`, `transaction_date`, `account_number`, `sub_account`,
                 `code`, `amount_in`, `amount_out`, `accumulated`, `content`, `reference_code`, `body`)
                VALUES (:id, :gateway, :date, :account, :sub, :code, :in, :out, :accumulated, :content, :ref, :body)
            ");

            foreach ($listTx as $tx) {
                $txId = $tx['id'] ?? null;
                if (!$txId) continue;

                $insertStmt->execute([
                    'id'          => $txId,
                    'gateway'     => $tx['bank_brand_name'] ?? ($tx['gateway'] ?? 'ACB'),
                    'date'        => $tx['transaction_date'] ?? date('Y-m-d H:i:s'),
                    'account'     => $tx['account_number'] ?? '',
                    'sub'         => $tx['va'] ?? ($tx['sub_account'] ?? ''),
                    'code'        => $tx['code'] ?? null,
                    'in'          => (int)($tx['amount_in'] ?? 0),
                    'out'         => (int)($tx['amount_out'] ?? 0),
                    'accumulated' => (int)($tx['accumulated'] ?? 0),
                    'content'     => $tx['transaction_content'] ?? ($tx['content'] ?? ''),
                    'ref'         => $tx['reference_number'] ?? ($tx['referenceCode'] ?? ''),
                    'body'        => json_encode($tx, JSON_UNESCAPED_UNICODE)
                ]);

                // Nếu là giao dịch mới bổ sung thành công -> Kích hoạt xử lý đơn
                if ($insertStmt->rowCount() > 0 && ((int)($tx['amount_in'] ?? 0)) > 0) {
                    $noiDung = $tx['transaction_content'] ?? ($tx['content'] ?? '');
                    if (preg_match('/(VK[A-Z0-9]{4,16})/i', $noiDung, $mDon)) {
                        $maDon = strtoupper($mDon[1]);
                        $tienNhan = (float)($tx['amount_in'] ?? 0);
                        $upd = $pdo->prepare("UPDATE `orders` SET `status` = 'PAID', `paid_at` = NOW() WHERE `order_code` = ? AND `status` = 'PENDING' AND `total_amount` <= ?");
                        $upd->execute([$maDon, $tienNhan]);
                        if ($upd->rowCount() > 0) {
                            require_once __DIR__ . '/dispatcher.php';
                            $boDieuPhoi = new BoDieuPhoiDonHang($pdo);
                            $boDieuPhoi->xuLyDonHang($maDon);
                        }
                    }
                    $soGiaoDichBu++;
                }
            }
            echo "Hoàn tất! Bổ sung {$soGiaoDichBu} giao dịch thiếu.\n";
        } else {
            echo "Không có phản hồi từ SePay API.\n";
        }
    } catch (Exception $e) {
        echo "Lỗi đối soát: " . $e->getMessage() . "\n";
    }
}

// 5. TỰ ĐỘNG ĐỒNG BỘ TỒN KHO & GIÁ TỪ NHÀ CUNG CẤP (CRON SYNC)
echo "-> Đang đồng bộ kho, giá và nhập sản phẩm mới từ nhà cung cấp... ";
try {
    define('IN_CRON_MASTER', true);
    require_once __DIR__ . '/cron_sync.php';
} catch (Exception $e) {
    echo "Lỗi đồng bộ kho: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] HOÀN TẤT CRONJOB THÀNH CÔNG!\n";
