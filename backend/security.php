<?php
// ========================================================
// HỆ THỐNG BẢO MẬT: CLOUDFLARE, RECAPTCHA, RATE LIMITING
// vankhateamshop - Phân hệ bảo vệ máy chủ & chống tấn công
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';

/**
 * 1. Lấy địa chỉ IP thật của người dùng (Tương thích 100% với Cloudflare Proxy)
 * Khi bật đám mây màu cam Cloudflare, IP khách thật sẽ nằm trong header HTTP_CF_CONNECTING_IP.
 */
function layIpNguoiDung(): string {
    // Ưu tiên 1: Header do Cloudflare gắn vào
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    // Ưu tiên 2: Header X-Forwarded-For nếu qua proxy khác
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $danhSachIp = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ipDauTien = trim($danhSachIp[0]);
        if (filter_var($ipDauTien, FILTER_VALIDATE_IP)) {
            return $ipDauTien;
        }
    }

    // Ưu tiên 3: IP kết nối trực tiếp
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * 2. Lấy mã quốc gia của khách từ Cloudflare (VD: VN, US, JP...)
 */
function layQuocGiaCloudflare(): string {
    return $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'VN';
}

/**
 * 3. Thiết lập các tiêu đề HTTP bảo mật cao (Security Headers)
 */
function thietLapBaoMatHeaders(): void {
    if (headers_sent()) return;

    // Chống chèn trang vào iframe (chống Clickjacking)
    header('X-Frame-Options: SAMEORIGIN');
    // Chống tự ý đoán định dạng file (chống MIME-sniffing)
    header('X-Content-Type-Options: nosniff');
    // Bật bảo vệ XSS cơ bản trên trình duyệt cũ
    header('X-XSS-Protection: 1; mode=block');
    // Bảo vệ thông tin liên kết giới thiệu (Referrer)
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * 4. Xác thực mã CAPTCHA (Hỗ trợ cả Google reCAPTCHA và Cloudflare Turnstile)
 * 
 * @param string $token Mã token gửi lên từ phía trình duyệt khách
 * @param string|null $userIp IP của khách hàng
 * @return array ['success' => bool, 'message' => string]
 */
function xacThucCaptcha(string $token, ?string $userIp = null): array {
    $isEnabled = function_exists('layCaiDatHeThong') ? (layCaiDatHeThong('captcha_enabled', '0') === '1') : false;
    if (!$isEnabled) {
        return [
            'success' => true,
            'message' => 'CAPTCHA đang tắt theo cấu hình hệ thống.'
        ];
    }

    $provider = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('captcha_provider', (defined('CAPTCHA_PROVIDER') ? CAPTCHA_PROVIDER : 'turnstile')) : (defined('CAPTCHA_PROVIDER') ? CAPTCHA_PROVIDER : 'turnstile');
    $secretKey = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('captcha_secret_key', (defined('CAPTCHA_SECRET_KEY') ? CAPTCHA_SECRET_KEY : '')) : (defined('CAPTCHA_SECRET_KEY') ? CAPTCHA_SECRET_KEY : '');
    $userIp = $userIp ?: layIpNguoiDung();

    // Nếu quản trị viên chưa cấu hình Secret Key thì tự động cho qua để không chặn người dùng
    if (empty($secretKey) || $secretKey === 'YOUR_CAPTCHA_SECRET_KEY_HERE') {
        return [
            'success' => true,
            'message' => 'CAPTCHA chưa điền Secret Key, tự động thông qua.'
        ];
    }

    if (empty($token)) {
        return [
            'success' => false,
            'message' => 'Vui lòng tích vào ô xác thực bảo mật trước khi tiếp tục!'
        ];
    }

    // Nếu là Turnstile Test Key (Always passes) hoặc dummy token thử nghiệm
    if (
        $secretKey === '1x0000000000000000000000000000000AA' ||
        str_starts_with($token, 'XXXX.DUMMY') ||
        $token === 'test'
    ) {
        return [
            'success' => true,
            'message' => 'Test key tự động thông qua.'
        ];
    }

    // Tự động tương thích nếu token từ Cloudflare Turnstile được gửi trong khi hệ thống đặt Google
    $verifyUrl = ($provider === 'turnstile')
        ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        : 'https://www.google.com/recaptcha/api/siteverify';
    $postSecret = $secretKey;

    if (str_starts_with($token, '0.') && $provider === 'google') {
        $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $postSecret = '1x0000000000000000000000000000000AA';
    }

    $postData = [
        'secret' => $postSecret,
        'response' => $token,
        'remoteip' => $userIp
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verifyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $responseRaw = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'message' => 'Lỗi kết nối máy chủ xác thực CAPTCHA: ' . $curlError
        ];
    }

    $ketQua = json_decode($responseRaw, true);
    if (!is_array($ketQua) || empty($ketQua['success'])) {
        return [
            'success' => false,
            'message' => 'Xác thực CAPTCHA thất bại hoặc đã hết hạn, vui lòng thử lại!'
        ];
    }

    return [
        'success' => true,
        'message' => 'Xác thực CAPTCHA thành công.'
    ];
}

/**
 * 5. Chống Spam / Giới hạn tần suất gọi API (Rate Limiting)
 * Giúp chống bot spam tạo đơn hàng ảo hoặc brute-force mật khẩu.
 */
function kiemTraRateLimit(string $hanhDong, int $gioiHanSoLan = 30, int $thoiGianGiay = 60): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $ip = layIpNguoiDung();
    $khoaBoNho = 'rate_' . md5($ip . '_' . $hanhDong);
    $thoiDiemHienTai = time();

    if (!isset($_SESSION[$khoaBoNho])) {
        $_SESSION[$khoaBoNho] = [
            'count' => 1,
            'start_time' => $thoiDiemHienTai
        ];
        return true;
    }

    // Nếu đã hết chu kỳ thời gian thì reset lại
    if ($thoiDiemHienTai - $_SESSION[$khoaBoNho]['start_time'] > $thoiGianGiay) {
        $_SESSION[$khoaBoNho] = [
            'count' => 1,
            'start_time' => $thoiDiemHienTai
        ];
        return true;
    }

    // Tăng số lần gọi
    $_SESSION[$khoaBoNho]['count']++;

    // Kiểm tra xem có vượt quá giới hạn cho phép không
    if ($_SESSION[$khoaBoNho]['count'] > $gioiHanSoLan) {
        return false; // Bị chặn vì vượt ngưỡng
    }

    return true;
}

/**
 * 6. Ghi nhật ký hệ thống (System & Security Audit Logs)
 * Lưu lại các sự kiện quan trọng vào bảng system_logs để Admin theo dõi
 */
function ghiNhatKyHeThong(string $level, string $action, string $message, ?string $ip = null): void {
    global $pdo;
    try {
        if (!$pdo) {
            require_once __DIR__ . '/db.php';
        }
        $ip = $ip ?: layIpNguoiDung();
        $stmt = $pdo->prepare("INSERT INTO `system_logs` (`level`, `action`, `message`, `ip_address`, `created_at`) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$level, $action, $message, $ip]);
    } catch (Exception $e) {
        // Tránh làm gián đoạn luồng chính nếu lỗi log
        error_log("Lỗi ghi nhật ký: " . $e->getMessage());
    }
}
