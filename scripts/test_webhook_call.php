<?php
$secretKey = 'whsec_Vrvs8it71ZQQTDSOCMjpGy8hvBrn4P4d';
$timestamp = time();

$payload = json_encode([
    'id' => 999999901,
    'gateway' => 'ACB',
    'transactionDate' => date('Y-m-d H:i:s'),
    'accountNumber' => '22254531',
    'subAccount' => null,
    'code' => null,
    'content' => 'tiemai99999 FT26320129751650 GD 6320IBT1k2H4JCCS',
    'transferType' => 'in',
    'transferAmount' => 50000,
    'accumulated' => 1000000,
    'referenceCode' => 'REFTEST999'
]);

$hash = hash_hmac('sha256', "{$timestamp}.{$payload}", $secretKey);
$signature = "sha256={$hash}";

$ch = curl_init('https://tiemai.io.vn/backend/webhook_sepay.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "X-SePay-Signature: {$signature}",
    "X-SePay-Timestamp: {$timestamp}"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: $httpCode\n";
echo "RESPONSE: $response\n";
