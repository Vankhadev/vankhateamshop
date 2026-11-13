<?php
// ========================================================
// MODULE BẮN THÔNG BÁO TỰ ĐỘNG QUA TELEGRAM BOT
// vankhateamshop - Báo đơn hàng mới, nạp tiền thành công, lỗi cạn số dư ví
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/db.php';

/**
 * Gửi tin nhắn thông báo về kênh/chat Telegram của Admin
 * @param string $tinNhan Nội dung tin nhắn (Hỗ trợ định dạng HTML)
 * @return bool Thành công hay thất bại
 */
function guiThongBaoTelegram(string $tinNhan): bool {
    global $pdo;

    try {
        // Lấy cấu hình Telegram bot từ bảng settings
        $settings = $pdo->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN ('telegram_bot_token', 'telegram_chat_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $botToken = trim($settings['telegram_bot_token'] ?? '');
        $chatId = trim($settings['telegram_chat_id'] ?? '');

        // Nếu admin chưa điền Token hoặc Chat ID thì bỏ qua
        if (empty($botToken) || empty($chatId)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $tinNhan,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $resJson = json_decode($response, true);
        return !empty($resJson['ok']);

    } catch (Exception $e) {
        return false;
    }
}
