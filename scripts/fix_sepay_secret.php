<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

$secretKey = 'whsec_Vrvs8it71ZQQTDSOCMjpGy8hvBrn4P4d';

// 1. Cập nhật bảng settings
$stmt = $pdo->prepare("
    INSERT INTO `settings` (`key`, `value`) 
    VALUES ('sepay_webhook_secret', ?) 
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
");
$stmt->execute([$secretKey]);
echo "Updated settings.sepay_webhook_secret = $secretKey\n";

// 2. Cập nhật .env nếu có
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $content = file_get_contents($envFile);
    $content = preg_replace('/^SEPAY_WEBHOOK_SECRET=.*$/m', "SEPAY_WEBHOOK_SECRET=$secretKey", $content);
    $content = preg_replace('/^SEPAY_API_KEY=.*$/m', "SEPAY_API_KEY=$secretKey", $content);
    file_put_contents($envFile, $content);
    echo "Updated .env file with secret key\n";
}

echo "=== FIX SEPAY SECRET COMPLETED ===\n";
