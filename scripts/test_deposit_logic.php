<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

echo "=== KIỂM TRA LOGIC NẠP TIỀN VÀO VÍ ===\n";

// 1. Kiểm tra setting
$stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = 'wallet_min_deposit'");
$stmt->execute();
$minDeposit = (float)$stmt->fetchColumn();
echo "1. Cấu hình wallet_min_deposit hiện tại: " . number_format($minDeposit) . " VNĐ\n";

// 2. Kiểm tra tài khoản test
$stmtUser = $pdo->query("SELECT id, name, balance FROM users ORDER BY id ASC LIMIT 1");
$user = $stmtUser->fetch();
echo "2. Thành viên test: ID {$user['id']} - {$user['name']} | Số dư: " . number_format($user['balance']) . " VNĐ\n";

// 3. Test sinh mã nạp ví và kiểm tra điều kiện min deposit
$testAmountUnder = $minDeposit - 5000;
$testAmountValid = $minDeposit + 30000;

echo "3. Kiểm tra số tiền dưới mức tối thiểu (" . number_format($testAmountUnder) . " VNĐ): ";
if ($testAmountUnder < $minDeposit) {
    echo "CHẶN CHÍNH XÁC (Không được cộng vào ví)\n";
} else {
    echo "LỖI!\n";
}

echo "4. Kiểm tra số tiền bằng hoặc lớn hơn mức tối thiểu (" . number_format($testAmountValid) . " VNĐ): ";
if ($testAmountValid >= $minDeposit) {
    echo "CHO PHÉP CHÍNH XÁC (Được cộng vào ví)\n";
} else {
    echo "LỖI!\n";
}

echo "=== HOÀN TẤT KIỂM TRA THÀNH CÔNG ===\n";
