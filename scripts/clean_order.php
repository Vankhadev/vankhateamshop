<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

$cleanKey = 'sk-ant-api03-YE8052XxNdDyd712NtJrMTKE-4rZVdxzLfkHKDe-8D9R6byGzFZTJY3noI_B4DyMeXhjykPkF2u3H2n5B7hbtw';
$cleanJson = json_encode([$cleanKey], JSON_UNESCAPED_UNICODE);

$stmt = $pdo->prepare("UPDATE orders SET delivered_data = ? WHERE id = 10042");
$stmt->execute([$cleanJson]);

echo "Updated rows: " . $stmt->rowCount() . "\n";
