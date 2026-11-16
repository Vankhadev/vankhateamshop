<?php
$secretKey = 'whsec_Vrvs8it71ZQQTDSOCMjpGy8hvBrn4P4d';
$timestamp = time();

$payload = json_encode([
    'id' => 3476,
    'gateway' => 'ACB',
    'transactionDate' => '2026-11-16 12:20:40',
    'accountNumber' => '22254531',
    'subAccount' => null,
    'code' => null,
    'content' => 'tiemai14876 FT26320129751650 GD 6320IBT1k2H4JCCS 161126-12:20:39',
    'transferType' => 'in',
    'transferAmount' => 5000,
    'accumulated' => null,
    'referenceCode' => '3476'
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
