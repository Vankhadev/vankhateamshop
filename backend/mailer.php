<?php
// ========================================================
// HỆ THỐNG GỬI EMAIL THÔNG BÁO TỰ ĐỘNG (SMTP MAILER ENGINE)
// vankhateamshop - Gửi email đơn hàng chưa thanh toán & hoàn thành giao key
// Hỗ trợ Gmail SMTP, Brevo, Zoho, Mailgun, Postfix qua Socket SSL/TLS thuần
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class HeThongSmtpMailer {
    private string $host;
    private int $port;
    private string $encryption;
    private string $user;
    private string $pass;
    private int $timeout;
    private string $lastError = '';

    public function __construct(string $host, int $port = 587, string $encryption = 'tls', string $user = '', string $pass = '', int $timeout = 15) {
        $this->host = trim($host);
        $this->port = (int)$port;
        $this->encryption = strtolower(trim($encryption));
        $this->user = trim($user);
        $this->pass = trim($pass);
        $this->timeout = $timeout;
    }

    public function layLoiCuoi(): string {
        return $this->lastError;
    }

    public function gui(string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName = ''): bool {
        if (empty($this->host)) {
            $this->lastError = 'Chưa cấu hình máy chủ SMTP Host';
            return false;
        }

        $remoteSocket = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client($remoteSocket, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            $this->lastError = "Không thể kết nối đến SMTP ({$remoteSocket}): {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        $readResponse = function() use ($socket) {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };

        $sendCommand = function(string $cmd) use ($socket, $readResponse) {
            fputs($socket, $cmd . "\r\n");
            return $readResponse();
        };

        $res = $readResponse();
        if (substr($res, 0, 3) !== '220') {
            $this->lastError = "Lỗi phản hồi SMTP ban đầu: {$res}";
            fclose($socket);
            return false;
        }

        $res = $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'tiemai.io.vn'));

        if ($this->encryption === 'tls') {
            $res = $sendCommand("STARTTLS");
            if (substr($res, 0, 3) !== '220') {
                $this->lastError = "STARTTLS không thành công: {$res}";
                fclose($socket);
                return false;
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $secure = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            if (!$secure) {
                $this->lastError = "Không thể thiết lập mã hóa TLS qua socket";
                fclose($socket);
                return false;
            }

            $res = $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'tiemai.io.vn'));
        }

        if (!empty($this->user)) {
            $res = $sendCommand("AUTH LOGIN");
            if (substr($res, 0, 3) !== '334') {
                $this->lastError = "AUTH LOGIN bị từ chối: {$res}";
                fclose($socket);
                return false;
            }

            $res = $sendCommand(base64_encode($this->user));
            if (substr($res, 0, 3) !== '334') {
                $this->lastError = "SMTP Username bị từ chối: {$res}";
                fclose($socket);
                return false;
            }

            $res = $sendCommand(base64_encode($this->pass));
            if (substr($res, 0, 3) !== '235') {
                $this->lastError = "Mật khẩu SMTP bị từ chối (Sai mật khẩu ứng dụng hoặc username): {$res}";
                fclose($socket);
                return false;
            }
        }

        $res = $sendCommand("MAIL FROM:<{$fromEmail}>");
        if (substr($res, 0, 3) !== '250') {
            $this->lastError = "MAIL FROM bị từ chối: {$res}";
            fclose($socket);
            return false;
        }

        $res = $sendCommand("RCPT TO:<{$to}>");
        if (substr($res, 0, 3) !== '250' && substr($res, 0, 3) !== '251') {
            $this->lastError = "RCPT TO bị từ chối cho địa chỉ ({$to}): {$res}";
            fclose($socket);
            return false;
        }

        $res = $sendCommand("DATA");
        if (substr($res, 0, 3) !== '354') {
            $this->lastError = "DATA bị từ chối: {$res}";
            fclose($socket);
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = !empty($fromName) ? ('=?UTF-8?B?' . base64_encode($fromName) . '?= ') : '';

        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: {$encodedFromName}<{$fromEmail}>";
        $headers[] = "To: <{$to}>";
        $headers[] = "Subject: {$encodedSubject}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";
        $headers[] = "X-Mailer: TiemAIShop/2.0";

        $dataPayload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.";

        fputs($socket, $dataPayload . "\r\n");
        $res = $readResponse();
        if (substr($res, 0, 3) !== '250') {
            $this->lastError = "Gửi nội dung email thất bại: {$res}";
            fclose($socket);
            return false;
        }

        $sendCommand("QUIT");
        fclose($socket);
        return true;
    }
}

/**
 * Hàm gửi email chung của toàn hệ thống
 */
function guiEmailHeThong(string $toEmail, string $subject, string $htmlBody): array {
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['thanh_cong' => false, 'thong_bao' => "Địa chỉ email nhận không hợp lệ: {$toEmail}"];
    }

    $smtpEnabled = (layCaiDatHeThong('smtp_enabled', '1') === '1');
    if (!$smtpEnabled) {
        return ['thanh_cong' => false, 'thong_bao' => 'Chức năng gửi email qua SMTP đang bị tắt'];
    }

    $host = layCaiDatHeThong('smtp_host', 'smtp.gmail.com');
    $port = (int)layCaiDatHeThong('smtp_port', '587');
    $encryption = layCaiDatHeThong('smtp_encryption', 'tls');
    $user = trim(layCaiDatHeThong('smtp_username', ''));
    $pass = trim(layCaiDatHeThong('smtp_password', ''));
    $fromEmail = trim(layCaiDatHeThong('smtp_from_email', ''));
    if (empty($fromEmail)) {
        $fromEmail = $user ?: 'no-reply@tiemai.io.vn';
    }
    $fromName = layCaiDatHeThong('smtp_from_name', SHOP_NAME ?: 'Tiệm AI - VanKha Team');

    if (empty($host) || empty($user) || empty($pass)) {
        return ['thanh_cong' => false, 'thong_bao' => 'Chưa cấu hình tài khoản SMTP (Host/Username/Password) trong /admin/cai-dat'];
    }

    $mailer = new HeThongSmtpMailer($host, $port, $encryption, $user, $pass);
    $ok = $mailer->gui($toEmail, $subject, $htmlBody, $fromEmail, $fromName);

    if ($ok) {
        return ['thanh_cong' => true, 'thong_bao' => 'Đã gửi email thành công'];
    } else {
        $err = $mailer->layLoiCuoi();
        error_log("Lỗi gửi Email SMTP ({$toEmail}): {$err}");
        return ['thanh_cong' => false, 'thong_bao' => $err];
    }
}

/**
 * Gửi email đơn hàng cho khách (PENDING / COMPLETED)
 */
function guiEmailDonHang(string $loai, array $donHang, array $extra = []): array {
    $toEmail = trim($donHang['customer_email'] ?? '');
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['thanh_cong' => false, 'thong_bao' => 'Email khách hàng không hợp lệ'];
    }

    $orderCode = $donHang['order_code'] ?? 'DH-' . time();
    $tenSanPham = $donHang['product_name'] ?? ($extra['product_name'] ?? 'Dịch vụ AI');
    $soLuong = max(1, (int)($donHang['quantity'] ?? 1));
    $tongTien = (float)($donHang['total_amount'] ?? 0);
    $formattedGia = number_format($tongTien, 0, ',', '.') . ' VNĐ';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://tiemai.io.vn';
    $shopName = defined('SHOP_NAME') ? SHOP_NAME : 'Tiệm AI - VanKha Team';
    $shopZalo = defined('SHOP_ZALO') ? SHOP_ZALO : '0904045075';
    $shopTele = defined('SHOP_TELEGRAM') ? SHOP_TELEGRAM : 'https://t.me/vankhateam';
    $linkTraCuu = "{$siteUrl}/tra-cuu-key?order=" . urlencode($orderCode);

    // ========================================================
    // 1. ĐƠN HÀNG MỚI TẠO - CHỜ THANH TOÁN (PENDING)
    // ========================================================
    if (strtoupper($loai) === 'PENDING') {
        $enablePending = (layCaiDatHeThong('email_order_pending_enable', '1') === '1');
        if (!$enablePending) {
            return ['thanh_cong' => false, 'thong_bao' => 'Tính năng gửi email đơn chờ thanh toán đang tắt'];
        }

        $bankBin = defined('BANK_BIN') ? BANK_BIN : '970416';
        $bankNo = defined('BANK_ACCOUNT_NO') ? BANK_ACCOUNT_NO : '22254531';
        $bankName = defined('BANK_NAME') ? BANK_NAME : 'ACB';
        $accountName = defined('BANK_ACCOUNT_NAME') ? BANK_ACCOUNT_NAME : 'VO VAN KHA';
        $linkVietQr = "https://api.vietqr.io/image/{$bankBin}-{$bankNo}-compact2.jpg?amount={$tongTien}&addInfo=" . urlencode($orderCode) . "&accountName=" . urlencode($accountName);

        $subject = "[$shopName] Đơn hàng #{$orderCode} đang chờ thanh toán ({$formattedGia})";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
body { margin: 0; padding: 0; background-color: #0b0f19; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #e5e7eb; }
.card { max-width: 600px; margin: 20px auto; background-color: #111827; border: 1px solid #1f2937; border-radius: 18px; overflow: hidden; }
.header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 26px 24px; text-align: center; }
.header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
.header p { margin: 6px 0 0 0; color: #dbeafe; font-size: 13px; }
.body { padding: 24px; }
.badge-pending { display: inline-block; background-color: #78350f; color: #fde68a; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; border: 1px solid #b45309; }
.table-info { width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 20px; }
.table-info td { padding: 10px 12px; border-bottom: 1px solid #1f2937; font-size: 13px; }
.table-info td:first-child { color: #9ca3af; width: 38%; }
.table-info td:last-child { color: #f3f4f6; font-weight: 600; text-align: right; }
.box-qr { background-color: #0d1322; border: 1px dashed #3b82f6; border-radius: 14px; padding: 20px; text-align: center; margin: 20px 0; }
.box-qr img { width: 230px; height: 230px; border-radius: 10px; border: 1px solid #374151; background: #fff; padding: 6px; }
.memo-highlight { background-color: #1e293b; color: #60a5fa; font-family: monospace; font-size: 15px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #3b82f6; display: inline-block; }
.btn-pay { display: block; width: 85%; margin: 20px auto 10px auto; background: linear-gradient(135deg, #f59e0b, #d97706); color: #ffffff; text-align: center; padding: 13px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); }
.footer { background-color: #0d121f; padding: 18px 24px; text-align: center; font-size: 11px; color: #6b7280; border-top: 1px solid #1f2937; }
.footer a { color: #60a5fa; text-decoration: none; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>⚡ {$shopName}</h1>
        <p>Cảm ơn quý khách đã đặt hàng trên hệ thống!</p>
    </div>
    <div class="body">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <span style="font-size:14px; color:#9ca3af;">Mã đơn: <b style="color:#ffffff; font-family:monospace;">{$orderCode}</b></span>
            <span class="badge-pending">⏳ Chờ chuyển khoản</span>
        </div>
        
        <table class="table-info">
            <tr>
                <td>Sản phẩm</td>
                <td><b style="color:#60a5fa;">{$tenSanPham}</b></td>
            </tr>
            <tr>
                <td>Số lượng</td>
                <td>{$soLuong}</td>
            </tr>
            <tr>
                <td>Tổng tiền thanh toán</td>
                <td><b style="color:#fbbf24; font-size:16px;">{$formattedGia}</b></td>
            </tr>
        </table>

        <div class="box-qr">
            <p style="margin:0 0 12px 0; font-size:13px; color:#e5e7eb; font-weight:700;">
                Quét mã VietQR chuyển khoản nhanh 24/7 (Tự động kích hoạt sau 3 giây):
            </p>
            <img src="{$linkVietQr}" alt="VietQR Thanh Toan" />
            <p style="font-size:12px; color:#9ca3af; margin:12px 0 6px 0;">Hoặc chuyển khoản thủ công tới:</p>
            <p style="margin:4px 0; font-size:13px; color:#ffffff;">Ngân hàng: <b>{$bankName}</b> - STK: <b>{$bankNo}</b> ({$accountName})</p>
            <p style="margin:8px 0 0 0; font-size:13px; color:#f87171;">
                Nội dung chuyển khoản (bắt buộc đúng): <span class="memo-highlight">{$orderCode}</span>
            </p>
        </div>

        <a href="{$linkTraCuu}" class="btn-pay">🔍 Kiểm Tra Trạng Thái & Nhận Key Ngay</a>
    </div>
    <div class="footer">
        Hệ thống tự động kích hoạt và giao Key ngay khi nhận được tiền.<br>
        Hỗ trợ 24/7 qua Zalo: <b>{$shopZalo}</b> | Telegram: <a href="{$shopTele}">{$shopTele}</a><br>
        &copy; 2026 {$shopName}. All rights reserved.
    </div>
</div>
</body>
</html>
HTML;

        return guiEmailHeThong($toEmail, $subject, $htmlBody);
    }

    // ========================================================
    // 2. ĐƠN HÀNG ĐÃ THANH TOÁN THÀNH CÔNG & GIAO KEY (COMPLETED)
    // ========================================================
    if (strtoupper($loai) === 'COMPLETED') {
        $enableSuccess = (layCaiDatHeThong('email_order_success_enable', '1') === '1');
        if (!$enableSuccess) {
            return ['thanh_cong' => false, 'thong_bao' => 'Tính năng gửi email đơn hoàn thành đang tắt'];
        }

        // Lấy dữ liệu key/tài khoản đã giao
        $deliveredData = $donHang['delivered_data'] ?? ($extra['delivered_data'] ?? '');
        $keysList = [];
        if (!empty($deliveredData)) {
            $parsed = json_decode($deliveredData, true);
            if (is_array($parsed)) {
                $keysList = $parsed;
            } elseif (is_string($deliveredData)) {
                $keysList = [$deliveredData];
            }
        }

        $keysHtml = '';
        if (!empty($keysList)) {
            foreach ($keysList as $kItem) {
                $sanitizedKey = htmlspecialchars((string)$kItem);
                $keysHtml .= "<div style=\"background:#0f172a; border:1px solid #10b981; border-radius:10px; padding:12px 14px; margin-bottom:8px; font-family:monospace; font-size:14px; color:#34d399; word-break:break-all; user-select:all;\">{$sanitizedKey}</div>";
            }
        } else {
            $keysHtml = "<div style=\"background:#0f172a; border:1px solid #374151; border-radius:10px; padding:12px; font-size:13px; color:#9ca3af;\">Vui lòng bấm vào nút tra cứu bên dưới để xem chi tiết mã kích hoạt hoặc tài khoản của bạn.</div>";
        }

        $subject = "[$shopName] 🎉 Đơn hàng #{$orderCode} giao thành công - Nhận Key & Hướng dẫn";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
body { margin: 0; padding: 0; background-color: #0b0f19; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #e5e7eb; }
.card { max-width: 600px; margin: 20px auto; background-color: #111827; border: 1px solid #1f2937; border-radius: 18px; overflow: hidden; }
.header { background: linear-gradient(135deg, #065f46 0%, #10b981 100%); padding: 26px 24px; text-align: center; }
.header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
.header p { margin: 6px 0 0 0; color: #d1fae5; font-size: 13px; }
.body { padding: 24px; }
.badge-success { display: inline-block; background-color: #064e3b; color: #6ee7b7; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; border: 1px solid #059669; }
.table-info { width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 20px; }
.table-info td { padding: 10px 12px; border-bottom: 1px solid #1f2937; font-size: 13px; }
.table-info td:first-child { color: #9ca3af; width: 38%; }
.table-info td:last-child { color: #f3f4f6; font-weight: 600; text-align: right; }
.box-keys { background-color: #0d1322; border: 1px solid #059669; border-radius: 14px; padding: 18px; margin: 20px 0; }
.btn-view { display: block; width: 85%; margin: 20px auto 10px auto; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-align: center; padding: 13px 20px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); }
.footer { background-color: #0d121f; padding: 18px 24px; text-align: center; font-size: 11px; color: #6b7280; border-top: 1px solid #1f2937; }
.footer a { color: #60a5fa; text-decoration: none; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>✅ GIAO HÀNG THÀNH CÔNG!</h1>
        <p>{$shopName} cảm ơn quý khách đã tin tưởng và sử dụng dịch vụ.</p>
    </div>
    <div class="body">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <span style="font-size:14px; color:#9ca3af;">Mã đơn: <b style="color:#ffffff; font-family:monospace;">{$orderCode}</b></span>
            <span class="badge-success">✔ Đã Hoàn Thành</span>
        </div>
        
        <table class="table-info">
            <tr>
                <td>Sản phẩm</td>
                <td><b style="color:#34d399;">{$tenSanPham}</b></td>
            </tr>
            <tr>
                <td>Số lượng</td>
                <td>{$soLuong}</td>
            </tr>
            <tr>
                <td>Tổng thanh toán</td>
                <td><b style="color:#fbbf24; font-size:16px;">{$formattedGia}</b></td>
            </tr>
            <tr>
                <td>Thời gian hoàn thành</td>
                <td><span style="color:#9ca3af;">' . date('H:i:s d/m/Y') . '</span></td>
            </tr>
        </table>

        <div class="box-keys">
            <h3 style="margin:0 0 10px 0; color:#34d399; font-size:14px; font-weight:700;">
                🔑 THÔNG TIN KEY / BẢN QUYỀN ĐÃ GIAO:
            </h3>
            {$keysHtml}
            <p style="margin:8px 0 0 0; font-size:12px; color:#9ca3af;">
                * Quý khách vui lòng sao chép và lưu trữ mã này an toàn. Bạn cũng có thể tra cứu lại bất cứ lúc nào bằng mã đơn hàng.
            </p>
        </div>

        <a href="{$linkTraCuu}" class="btn-view">🚀 Tra Cứu Đơn & Hướng Dẫn Kích Hoạt</a>
    </div>
    <div class="footer">
        Đơn hàng được bảo hành theo đúng chính sách niêm yết tại {$siteUrl}.<br>
        Cần trợ giúp kích hoạt? Nhắn Zalo: <b>{$shopZalo}</b> | Telegram: <a href="{$shopTele}">{$shopTele}</a><br>
        &copy; 2026 {$shopName}. Trân trọng cảm ơn quý khách!
    </div>
</div>
</body>
</html>
HTML;

        return guiEmailHeThong($toEmail, $subject, $htmlBody);
    }

    return ['thanh_cong' => false, 'thong_bao' => "Loại thông báo không hỗ trợ: {$loai}"];
}

/**
 * Gửi email thử nghiệm (Test SMTP)
 */
function guiEmailThuNghiem(string $toEmail): array {
    $shopName = defined('SHOP_NAME') ? SHOP_NAME : 'Tiệm AI';
    $subject = "[$shopName] Thử nghiệm gửi Email thành công qua SMTP";
    $htmlBody = <<<HTML
<div style="background-color:#0b0f19; color:#e5e7eb; font-family:sans-serif; padding:30px; border-radius:14px; border:1px solid #1f2937; max-width:550px; margin:0 auto;">
    <h2 style="color:#10b981; margin-top:0;">🎉 Chúc mừng! Cấu hình SMTP hoạt động hoàn hảo!</h2>
    <p style="font-size:13px; color:#d1d5db;">Email này được gửi từ tính năng <b>Gửi thử Email</b> trên hệ thống <b>{$shopName}</b>.</p>
    <div style="background:#111827; border:1px solid #374151; border-radius:8px; padding:12px 16px; margin:16px 0; font-size:12px; color:#9ca3af;">
        Thời gian gửi: <b>' . date('H:i:s d/m/Y') . '</b><br>
        Máy chủ SMTP: <b>' . htmlspecialchars(layCaiDatHeThong('smtp_host', '')) . '</b> (Cổng ' . htmlspecialchars(layCaiDatHeThong('smtp_port', '')) . ')<br>
        Email gửi: <b>' . htmlspecialchars(layCaiDatHeThong('smtp_from_email', '')) . '</b>
    </div>
    <p style="font-size:12px; color:#6b7280; margin-bottom:0;">Bây giờ hệ thống sẽ tự động gửi email cho khách khi có đơn hàng mới và khi thanh toán thành công!</p>
</div>
HTML;

    return guiEmailHeThong($toEmail, $subject, $htmlBody);
}
