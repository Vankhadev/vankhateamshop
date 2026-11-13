<?php
// ========================================================
// FILE CẤU HÌNH HỆ THỐNG TRUNG TÂM (CENTRAL CONFIG)
// vankhateamshop - Cấu hình Database, Nhà cung cấp, Google Login, CAPTCHA, SePay
// Hỗ trợ tự động đọc từ tệp .env (Ưu tiên) và sao lưu mặc định an toàn
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

// Khởi động phiên làm việc (Session) nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HÀM TỰ ĐỘNG ĐỌC TỆP .ENV KHÔNG CẦN CÀI ĐẶT THƯ VIỆN NGOÀI
function napBienMoiTruong($duongDanTep) {
    if (!file_exists($duongDanTep)) {
        return;
    }
    $cacDong = file($duongDanTep, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($cacDong as $dong) {
        $dong = trim($dong);
        // Bỏ qua dòng chú thích
        if (empty($dong) || strpos($dong, '#') === 0) {
            continue;
        }
        if (strpos($dong, '=') !== false) {
            list($khoa, $giaTri) = explode('=', $dong, 2);
            $khoa = trim($khoa);
            $giaTri = trim($giaTri);
            // Loại bỏ dấu ngoặc kép hoặc ngoặc đơn nếu có
            $giaTri = trim($giaTri, "'\"");
            if (!array_key_exists($khoa, $_SERVER) && !array_key_exists($khoa, $_ENV)) {
                putenv("{$khoa}={$giaTri}");
                $_ENV[$khoa] = $giaTri;
                $_SERVER[$khoa] = $giaTri;
            }
        }
    }
}

// Nạp file .env từ thư mục gốc
napBienMoiTruong(__DIR__ . '/../.env');

// Hàm lấy giá trị cấu hình theo thứ tự ưu tiên: .env -> Mặc định
function layGiaTriEnv($khoa, $macDinh = '') {
    $val = getenv($khoa);
    if ($val !== false) return $val;
    if (isset($_ENV[$khoa])) return $_ENV[$khoa];
    if (isset($_SERVER[$khoa])) return $_SERVER[$khoa];
    return $macDinh;
}

// 1. CẤU HÌNH CƠ SỞ DỮ LIỆU MYSQL
define('DB_HOST', layGiaTriEnv('DB_HOST', '127.0.0.1'));
define('DB_NAME', layGiaTriEnv('DB_NAME', 'vankhateamshop'));
define('DB_USER', layGiaTriEnv('DB_USER', 'root'));
define('DB_PASS', layGiaTriEnv('DB_PASS', ''));
define('DB_PORT', layGiaTriEnv('DB_PORT', '3306'));

// 2. CẤU HÌNH KẾT NỐI API NHÀ CUNG CẤP (SUPPLIER API)
define('SUPPLIER_API_URL', layGiaTriEnv('SUPPLIER_API_URL', 'https://api.nhacungcap.com/v1'));
define('SUPPLIER_API_KEY', layGiaTriEnv('SUPPLIER_API_KEY', 'YOUR_SUPPLIER_API_KEY_HERE'));

// 3. CẤU HÌNH ĐĂNG NHẬP GOOGLE (GOOGLE OAUTH 2.0)
define('GOOGLE_CLIENT_ID', layGiaTriEnv('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com'));
define('GOOGLE_CLIENT_SECRET', layGiaTriEnv('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE'));
define('GOOGLE_REDIRECT_URI', layGiaTriEnv('GOOGLE_REDIRECT_URI', 'http://127.0.0.1:8000/backend/auth_google.php'));

// 4. CẤU HÌNH BẢO MẬT CAPTCHA (CHỐNG SPAM & BOT)
define('CAPTCHA_PROVIDER', layGiaTriEnv('CAPTCHA_PROVIDER', 'turnstile'));
define('CAPTCHA_SITE_KEY', layGiaTriEnv('CAPTCHA_SITE_KEY', '1x00000000000000000000AA'));
define('CAPTCHA_SECRET_KEY', layGiaTriEnv('CAPTCHA_SECRET_KEY', '1x0000000000000000000000000000000AA'));

// 5. CẤU HÌNH CỔNG THANH TOÁN TỰ ĐỘNG VIETQR (SEPAY.VN)
define('SEPAY_API_KEY', layGiaTriEnv('SEPAY_API_KEY', 'YOUR_SEPAY_API_KEY'));
define('BANK_ACCOUNT_NAME', layGiaTriEnv('BANK_ACCOUNT_NAME', 'VO VAN KHA'));
define('BANK_ACCOUNT_NO', layGiaTriEnv('BANK_ACCOUNT_NO', '22254531'));
define('BANK_NAME', layGiaTriEnv('BANK_NAME', 'ACB'));
define('BANK_FULL_NAME', layGiaTriEnv('BANK_FULL_NAME', 'Ngân hàng TMCP Á Châu'));
define('BANK_BIN', layGiaTriEnv('BANK_BIN', '970416'));

// 6. THÔNG TIN CHUNG CỦA WEBSITE
define('SHOP_NAME', layGiaTriEnv('SHOP_NAME', 'VanKha Team Shop'));
define('SHOP_ZALO', layGiaTriEnv('SHOP_ZALO', '0847154088'));
define('SHOP_TELEGRAM', layGiaTriEnv('SHOP_TELEGRAM', 'https://t.me/vankhateam'));
define('SITE_URL', layGiaTriEnv('SITE_URL', 'http://127.0.0.1:8000'));
