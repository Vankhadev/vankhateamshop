<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

echo "=== KIỂM TRA MÃ TIEMAI14876 ===\n";
$code = 'tiemai14876';

$stmt = $pdo->prepare("SELECT * FROM wallet_deposit_codes WHERE code = ?");
$stmt->execute([$code]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "1. wallet_deposit_codes:\n";
print_r($rows);

$stmt2 = $pdo->prepare("SELECT * FROM sepay_transactions WHERE content LIKE ?");
$stmt2->execute(["%$code%"]);
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "2. sepay_transactions:\n";
print_r($rows2);

$stmt3 = $pdo->query("SELECT id, name, email, balance FROM users WHERE id = 1");
echo "3. User ID 1:\n";
print_r($stmt3->fetch(PDO::FETCH_ASSOC));
