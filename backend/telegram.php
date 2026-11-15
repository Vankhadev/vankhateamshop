<?php
// ========================================================
// HỆ THỐNG THÔNG BÁO TELEGRAM BOT ĐA NHÓM (MULTI-CHANNEL)
// Tiệm AI - tiemai.io.vn
// 1 Bot Token duy nhất gửi thông báo phân luồng vào nhiều nhóm riêng biệt:
// - Nhóm Báo Đơn Hàng Mới
// - Nhóm Cập Nhật Sản Phẩm Mới
// - Nhóm Cảnh Báo Sản Phẩm Hết Hàng
// - Nhóm Cảnh Báo Biến Động Giá (Tăng / Giảm)
// - Nhóm Nạp Tiền & Tài Chính
// - Nhóm Hỗ Trợ Khách Hàng
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/db.php';

/**
 * Hàm lọc an toàn ký tự đặc biệt khi format tin nhắn HTML Telegram
 */
if (!function_exists('telegramEscape')) {
    function telegramEscape($giaTri): string {
        return htmlspecialchars((string)$giaTri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Lấy Chat ID tương ứng theo loại sự kiện.
 * Nếu nhóm chuyên biệt chưa cài, tự động fallback về nhóm chung (telegram_chat_id).
 */
function layTelegramChatId(PDO $pdo, string $eventType): string {
    $map = [
        'order'         => 'telegram_chat_order_id',
        'product_new'   => 'telegram_chat_product_new_id',
        'product_out'   => 'telegram_chat_product_out_id',
        'product_price' => 'telegram_chat_product_price_id',
        'catalog'       => 'telegram_chat_catalog_id',
        'finance'       => 'telegram_chat_finance_id',
        'support'       => 'telegram_chat_support_id',
        'general'       => 'telegram_chat_id',
    ];
    $key = $map[$eventType] ?? 'telegram_chat_id';

    try {
        $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` IN (?, 'telegram_chat_id') ORDER BY CASE WHEN `key` = ? THEN 0 ELSE 1 END LIMIT 1");
        $stmt->execute([$key, $key]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Gửi tin nhắn Telegram tới nhóm tương ứng (Hàm cốt lõi)
 * @param string $tinNhan Nội dung tin nhắn (Hỗ trợ HTML: <b>, <i>, <code>, <a>...)
 * @param string $eventType Loại sự kiện ('order', 'product_new', 'product_out', 'product_price', 'finance', 'support', 'general')
 * @return bool Thành công hay thất bại
 */
function guiThongBaoTelegram(string $tinNhan, string $eventType = 'general'): bool {
    global $pdo;
    try {
        if (!isset($pdo)) {
            require_once __DIR__ . '/db.php';
        }

        // Lấy cấu hình Token và các Chat ID từ Database
        $keys = [
            'telegram_bot_token', 
            'telegram_chat_id', 
            'telegram_chat_order_id', 
            'telegram_chat_product_new_id', 
            'telegram_chat_product_out_id', 
            'telegram_chat_product_price_id', 
            'telegram_chat_catalog_id', 
            'telegram_chat_finance_id', 
            'telegram_chat_support_id'
        ];
        $inQuery = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($inQuery)");
        $stmt->execute($keys);
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $botToken = trim($settings['telegram_bot_token'] ?? '');
        if (empty($botToken) && defined('TELEGRAM_BOT_TOKEN')) {
            $botToken = trim(TELEGRAM_BOT_TOKEN);
        }

        if (empty($botToken)) {
            return false;
        }

        // Map Chat ID theo eventType, fallback về nhóm chung telegram_chat_id
        $chatKeyMap = [
            'order'         => 'telegram_chat_order_id',
            'product_new'   => 'telegram_chat_product_new_id',
            'product_out'   => 'telegram_chat_product_out_id',
            'product_price' => 'telegram_chat_product_price_id',
            'catalog'       => 'telegram_chat_catalog_id',
            'finance'       => 'telegram_chat_finance_id',
            'support'       => 'telegram_chat_support_id',
            'general'       => 'telegram_chat_id'
        ];
        $chatKey = $chatKeyMap[$eventType] ?? 'telegram_chat_id';
        $chatId = trim($settings[$chatKey] ?? '');
        if (empty($chatId)) {
            $chatId = trim($settings['telegram_chat_id'] ?? '');
        }

        if (empty($chatId)) {
            return false;
        }

        // Gửi qua Telegram Bot API bằng cURL
        $url = "https://api.telegram.org/bot" . rawurlencode($botToken) . "/sendMessage";
        $payload = [
            'chat_id'                  => $chatId,
            'text'                     => $tinNhan,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode((string)$response, true);
        return ($httpCode >= 200 && $httpCode < 300 && !empty($json['ok']));

    } catch (Throwable $e) {
        error_log("Lỗi gửi Telegram ({$eventType}): " . $e->getMessage());
        return false;
    }
}

// ========================================================
// CÁC HÀM TIỆN ÍCH CHUYÊN BIỆT THEO TỪNG NHÓM
// ========================================================

/**
 * 1. Báo Đơn Hàng Mới (Gửi vào nhóm Order)
 * @param array $donHang [ma_don, ten_san_pham, so_luong, tong_tien, email_khach, phuong_thuc, trang_thai_key]
 */
function guiThongBaoDonHang(array $donHang): bool {
    $maDon      = $donHang['ma_don'] ?? $donHang['order_code'] ?? 'N/A';
    $tenSp      = $donHang['ten_san_pham'] ?? $donHang['product_name'] ?? 'Gói API AI';
    $soLuong    = (int)($donHang['so_luong'] ?? $donHang['quantity'] ?? 1);
    $tongTien   = (float)($donHang['tong_tien'] ?? $donHang['total_amount'] ?? 0);
    $email      = $donHang['email_khach'] ?? $donHang['customer_email'] ?? 'Khách vãng lai';
    $phuongThuc = $donHang['phuong_thuc'] ?? $donHang['payment_method'] ?? 'VietQR';
    $trangThai  = $donHang['trang_thai_key'] ?? 'Đã giao key tự động';

    $msg  = "📦 <b>CÓ ĐƠN HÀNG MỚI!</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🔖 Mã đơn: <code>" . telegramEscape($maDon) . "</code>\n";
    $msg .= "🤖 Sản phẩm: <b>" . telegramEscape($tenSp) . "</b>\n";
    $msg .= "🔢 Số lượng: <b>{$soLuong}</b>\n";
    $msg .= "💰 Tổng tiền: <b>" . number_format($tongTien, 0, ',', '.') . " VNĐ</b>\n";
    $msg .= "👤 Khách hàng: <code>" . telegramEscape($email) . "</code>\n";
    $msg .= "💳 Thanh toán: <b>" . telegramEscape($phuongThuc) . "</b>\n";
    $msg .= "🚀 Trạng thái: <b>" . telegramEscape($trangThai) . "</b>\n";
    $msg .= "🕒 Thời gian: <i>" . date('d/m/Y H:i:s') . "</i>\n";
    $msg .= "🌐 Hệ thống: <b>tiemai.io.vn</b>";

    return guiThongBaoTelegram($msg, 'order');
}

/**
 * 2. Cập Nhật Sản Phẩm Mới (Gửi vào nhóm Product New)
 * @param array $sanPham [name, price, category_name, supplier, url]
 */
function guiThongBaoSanPhamMoi(array $sanPham): bool {
    $tenSp    = $sanPham['name'] ?? 'Sản phẩm mới';
    $giaBan   = (float)($sanPham['price'] ?? 0);
    $danhMuc  = $sanPham['category_name'] ?? $sanPham['danh_muc'] ?? 'API AI';
    $nguon    = $sanPham['supplier'] ?? $sanPham['nha_cung_cap'] ?? 'Chính hãng';
    $linkWeb  = $sanPham['url'] ?? ('https://tiemai.io.vn/#san-pham');

    $msg  = "✨ <b>SẢN PHẨM MỚI VỪA CẬP NHẬT!</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🤖 Tên sản phẩm: <b>" . telegramEscape($tenSp) . "</b>\n";
    $msg .= "📁 Danh mục: <b>" . telegramEscape($danhMuc) . "</b>\n";
    $msg .= "💵 Giá niêm yết: <b>" . number_format($giaBan, 0, ',', '.') . " VNĐ</b>\n";
    $msg .= "🔌 Nguồn kết nối: <b>" . telegramEscape($nguon) . "</b>\n";
    $msg .= "🕒 Thời gian: <i>" . date('d/m/Y H:i:s') . "</i>\n";
    $msg .= "👉 Xem ngay: <a href=\"" . telegramEscape($linkWeb) . "\">tiemai.io.vn</a>";

    return guiThongBaoTelegram($msg, 'product_new');
}

/**
 * 3. Cảnh Báo Sản Phẩm Hết Hàng (Gửi vào nhóm Product Out)
 * @param array $sanPham [name, sku, supplier, note]
 */
function guiThongBaoHetHang(array $sanPham): bool {
    $tenSp   = $sanPham['name'] ?? $sanPham['ten_san_pham'] ?? 'Sản phẩm';
    $sku     = $sanPham['sku'] ?? 'N/A';
    $nguon   = $sanPham['supplier'] ?? 'Đối tác';
    $ghiChu  = $sanPham['note'] ?? 'Kho nguồn đã cạn stock, cần bổ sung gấp!';

    $msg  = "⚠️ <b>CẢNH BÁO: SẢN PHẨM HẾT HÀNG!</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🚨 Sản phẩm: <b>" . telegramEscape($tenSp) . "</b>\n";
    $msg .= "🔖 Mã SKU: <code>" . telegramEscape($sku) . "</code>\n";
    $msg .= "🔌 Kho nguồn: <b>" . telegramEscape($nguon) . "</b>\n";
    $msg .= "📊 Tồn kho: <b style=\"color:red;\">0 (HẾT HÀNG)</b>\n";
    $msg .= "📝 Ghi chú: <i>" . telegramEscape($ghiChu) . "</i>\n";
    $msg .= "🕒 Thời gian: <i>" . date('d/m/Y H:i:s') . "</i>\n";
    $msg .= "👉 Admin vui lòng kiểm tra bổ sung kho nguồn ngay!";

    return guiThongBaoTelegram($msg, 'product_out');
}

/**
 * 4. Báo Giá Sản Phẩm Tăng / Giảm (Gửi vào nhóm Product Price)
 * @param array $sanPham [name, sku, supplier]
 * @param float $giaCu Giá trước khi đổi
 * @param float $giaMoi Giá sau khi đổi
 */
function guiThongBaoBienDongGia(array $sanPham, float $giaCu, float $giaMoi): bool {
    if ($giaCu <= 0 || $giaMoi <= 0 || $giaCu == $giaMoi) {
        return false;
    }

    $tenSp   = $sanPham['name'] ?? $sanPham['ten_san_pham'] ?? 'Sản phẩm';
    $chenhLech = $giaMoi - $giaCu;
    $phanTram  = round(abs($chenhLech) / $giaCu * 100, 1);
    
    if ($chenhLech > 0) {
        $icon = "📈 <b>BIẾN ĐỘNG: GIÁ TĂNG +{$phanTram}%</b>";
        $huongDoi = "Tăng: +" . number_format($chenhLech, 0, ',', '.') . " VNĐ";
    } else {
        $icon = "📉 <b>BIẾN ĐỘNG: GIÁ GIẢM -{$phanTram}%</b>";
        $huongDoi = "Giảm: -" . number_format(abs($chenhLech), 0, ',', '.') . " VNĐ";
    }

    $msg  = "{$icon}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🤖 Sản phẩm: <b>" . telegramEscape($tenSp) . "</b>\n";
    $msg .= "💸 Giá cũ: <s>" . number_format($giaCu, 0, ',', '.') . " VNĐ</s>\n";
    $msg .= "💵 Giá mới: <b>" . number_format($giaMoi, 0, ',', '.') . " VNĐ</b>\n";
    $msg .= "📊 Mức đổi: <b>{$huongDoi}</b>\n";
    $msg .= "🕒 Thời gian: <i>" . date('d/m/Y H:i:s') . "</i>\n";
    $msg .= "🌐 Hệ thống: <b>tiemai.io.vn</b>";

    return guiThongBaoTelegram($msg, 'product_price');
}

/**
 * 5. Báo Nạp Tiền & Biến Động Tài Chính (Gửi vào nhóm Finance)
 * @param array $giaoDich [user_email, amount, gateway, balance_after, note]
 */
function guiThongBaoNapTien(array $giaoDich): bool {
    $email     = $giaoDich['user_email'] ?? $giaoDich['email'] ?? 'Thành viên';
    $soTien    = (float)($giaoDich['amount'] ?? 0);
    $nganHang  = $giaoDich['gateway'] ?? 'Ngân hàng / SePay';
    $soDuMoi   = (float)($giaoDich['balance_after'] ?? 0);
    $maGiaoDich= $giaoDich['transaction_code'] ?? ('TX' . time());

    $msg  = "💰 <b>NẠP TIỀN THÀNH CÔNG!</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 Tài khoản: <code>" . telegramEscape($email) . "</code>\n";
    $msg .= "💵 Số tiền: <b>+" . number_format($soTien, 0, ',', '.') . " VNĐ</b>\n";
    $msg .= "🏦 Cổng nạp: <b>" . telegramEscape($nganHang) . "</b>\n";
    $msg .= "🔖 Mã GD: <code>" . telegramEscape($maGiaoDich) . "</code>\n";
    $msg .= "📈 Số dư ví mới: <b>" . number_format($soDuMoi, 0, ',', '.') . " VNĐ</b>\n";
    $msg .= "🕒 Thời gian: <i>" . date('d/m/Y H:i:s') . "</i>";

    return guiThongBaoTelegram($msg, 'finance');
}
