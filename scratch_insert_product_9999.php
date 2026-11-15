<?php
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/db.php';

try {
    $sql = "INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `price`, `description`, `is_active`)
            VALUES (9999, 1008, 'Nạp Credit API 1gw.gwai.cloud', 'nap-credit-api-1gw-gwai-cloud', 'VK-CREDIT-TOPUP', 1500, 'Dịch vụ nạp Credit API trực tiếp cho Key 1gw.gwai.cloud', 1)
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price` = VALUES(`price`);";
    $pdo->exec($sql);
    echo "Product 9999 OK\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
