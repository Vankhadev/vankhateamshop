<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

try {
    // Đảm bảo cột status trong wallet_deposit_codes chấp nhận VARCHAR(30)
    $pdo->exec("ALTER TABLE `wallet_deposit_codes` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'PENDING'");
    echo "DONE: status modified to VARCHAR(30)\n";
} catch (Exception $e) {
    echo "Notice on alter status: " . $e->getMessage() . "\n";
}

try {
    // Kiểm tra và thêm cột wallet_min_deposit trong settings nếu chưa có
    $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = 'wallet_min_deposit' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val === false) {
        $pdo->prepare("INSERT INTO `settings` (`key`, `value`) VALUES ('wallet_min_deposit', '20000')")->execute();
        echo "ADDED: wallet_min_deposit = 20000 in settings\n";
    } else {
        echo "EXISTED: wallet_min_deposit = " . $val . "\n";
    }
} catch (Exception $e) {
    echo "Error settings: " . $e->getMessage() . "\n";
}
