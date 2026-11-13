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

echo "[" . date('Y-m-d H:i:s') . "] HOÀN TẤT CRONJOB THÀNH CÔNG!\n";
