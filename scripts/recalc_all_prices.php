<?php
// ========================================================
// SCRIPT CẬP NHẬT LẠI GIÁ TOÀN BỘ SẢN PHẨM THEO LÃI 5K - 10K
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

function tinhGiaBanMoi(float $cost): float {
    if ($cost <= 30000) {
        $profit = 5000;
    } elseif ($cost <= 60000) {
        $profit = 7000;
    } else {
        $profit = 10000;
    }
    return ceil(($cost + $profit) / 1000) * 1000;
}

$stmt = $pdo->query("
    SELECT 
        p.id, 
        p.name, 
        p.price as old_price, 
        p.original_price as old_orig,
        MAX(m.cost_price) as cost
    FROM `products` p
    JOIN `supplier_product_mappings` m ON p.id = m.product_id
    WHERE p.is_active = 1 AND m.cost_price > 0
    GROUP BY p.id, p.name, p.price, p.original_price
    ORDER BY cost ASC
");

$products = $stmt->fetchAll();
$count = 0;

echo "=== BẮT ĐẦU CẬP NHẬT GIÁ SẢN PHẨM (LÃI 5.000đ - 10.000đ) ===\n\n";

foreach ($products as $p) {
    $cost = (float)$p['cost'];
    $oldPrice = (float)$p['old_price'];
    $newPrice = tinhGiaBanMoi($cost);
    $newOrig = ceil(($newPrice * 1.3) / 1000) * 1000;
    $profit = $newPrice - $cost;

    $stmtUp = $pdo->prepare("UPDATE `products` SET `price` = ?, `original_price` = ? WHERE `id` = ?");
    $stmtUp->execute([$newPrice, $newOrig, $p['id']]);
    $count++;

    $diff = $newPrice - $oldPrice;
    $sign = $diff > 0 ? "+{$diff}đ" : "{$diff}đ";
    echo sprintf(
        "ID %-5d | %-38s | Vốn: %7dđ | Cũ: %7dđ -> Mới: %7dđ (%s) | Lời: %5dđ\n",
        $p['id'],
        mb_substr($p['name'], 0, 36),
        $cost,
        $oldPrice,
        $newPrice,
        $sign,
        $profit
    );
}

echo "\n=> ĐÃ HOÀN TẤT CẬP NHẬT THÀNH CÔNG {$count} SẢN PHẨM!\n";
