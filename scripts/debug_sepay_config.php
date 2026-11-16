<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

echo "ENV SEPAY_WEBHOOK_SECRET: " . layGiaTriEnv('SEPAY_WEBHOOK_SECRET', '(empty)') . "\n";
echo "ENV SEPAY_API_KEY: " . layGiaTriEnv('SEPAY_API_KEY', '(empty)') . "\n";

$rows = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE '%sepay%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "DB setting: " . $r['key'] . " = " . $r['value'] . "\n";
}
