<?php
// Module gửi thông báo Telegram: một bot, nhiều nhóm theo từng loại sự kiện.
require_once __DIR__ . '/db.php';

/**
 * Đọc chat ID theo event. Bot token dùng chung, chat ID tách theo nhóm.
 * Các key được lưu trong settings để không cần hard-code ID nhóm trong mã nguồn.
 */
function layTelegramChatId(PDO $pdo, string $eventType): string {
    $map = [
        'order' => 'telegram_chat_order_id',
        'product_new' => 'telegram_chat_product_new_id',
        'product_out' => 'telegram_chat_product_out_id',
        'product_price' => 'telegram_chat_product_price_id',
        'catalog' => 'telegram_chat_catalog_id',
        'finance' => 'telegram_chat_finance_id',
        'support' => 'telegram_chat_support_id',
        'general' => 'telegram_chat_id',
    ];
    $key = $map[$eventType] ?? $map['general'];
    $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` IN (?, 'telegram_chat_id') ORDER BY CASE WHEN `key` = ? THEN 0 ELSE 1 END LIMIT 1");
    $stmt->execute([$key, $key]);
    return trim((string)($stmt->fetchColumn() ?: ''));
}

/** Gửi thông báo tới nhóm được map với loại sự kiện. */
function guiThongBaoTelegram(string $tinNhan, string $eventType = 'general'): bool {
    global $pdo;
    try {
        $settings = $pdo->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN ('telegram_bot_token', 'telegram_chat_id', 'telegram_chat_order_id', 'telegram_chat_product_new_id', 'telegram_chat_product_out_id', 'telegram_chat_product_price_id', 'telegram_chat_catalog_id', 'telegram_chat_finance_id', 'telegram_chat_support_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $botToken = trim($settings['telegram_bot_token'] ?? '');
        $chatKey = [
            'order' => 'telegram_chat_order_id', 'product_new' => 'telegram_chat_product_new_id',
            'product_out' => 'telegram_chat_product_out_id', 'product_price' => 'telegram_chat_product_price_id',
            'catalog' => 'telegram_chat_catalog_id', 'finance' => 'telegram_chat_finance_id',
            'support' => 'telegram_chat_support_id', 'general' => 'telegram_chat_id'
        ][$eventType] ?? 'telegram_chat_id';
        $chatId = trim($settings[$chatKey] ?? '') ?: trim($settings['telegram_chat_id'] ?? '');
        if ($botToken === '' || $chatId === '') return false;

        $ch = curl_init("https://api.telegram.org/bot" . rawurlencode($botToken) . "/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'chat_id' => $chatId,
                'text' => $tinNhan,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$response, true);
        return $httpCode >= 200 && $httpCode < 300 && !empty($json['ok']);
    } catch (Throwable $e) {
        error_log('Telegram notification error: ' . $e->getMessage());
        return false;
    }
}
