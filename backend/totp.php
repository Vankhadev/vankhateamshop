<?php
// ========================================================
// HỆ THỐNG XÁC THỰC HAI LỚP 2FA QUA AUTHENTICATOR (RFC 6238 TOTP)
// vankhateamshop - Tương thích Google Authenticator, Microsoft Authenticator, Authy, Apple Passwords
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

class TotpAuth {
    private static array $base32Chars = [
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
        'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
    ];

    /**
     * Tạo Secret Key ngẫu nhiên chuẩn Base32 (16 ký tự = 80-bit entropy)
     */
    public static function taoSecretKey(int $length = 16): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[ord($bytes[$i]) % 32];
        }
        return $secret;
    }

    /**
     * Giải mã chuỗi Base32 thành chuỗi byte nhị phân
     */
    public static function base32GiaiMa(string $str): string {
        $str = strtoupper(trim($str));
        $n = 0;
        $j = 0;
        $binary = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $c = $str[$i];
            if ($c === '=') break;
            if (!isset(self::$base32Chars[$c])) continue;
            $n = ($n << 5) | self::$base32Chars[$c];
            $j += 5;
            if ($j >= 8) {
                $j -= 8;
                $binary .= chr(($n >> $j) & 0xFF);
            }
        }
        return $binary;
    }

    /**
     * Tính toán mã OTP 6 chữ số tại thời điểm cụ thể (chu kỳ 30 giây)
     */
    public static function tinhMaOtp(string $secret, ?int $timestamp = null): string {
        if ($timestamp === null) {
            $timestamp = time();
        }
        $timeSlice = floor($timestamp / 30);
        $binarySecret = self::base32GiaiMa($secret);
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $binaryTime, $binarySecret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $otp = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Xác thực mã OTP người dùng nhập (cho phép lệch ±1 chu kỳ 30s để bù sai lệch đồng hồ)
     */
    public static function xacThuc(string $secret, string $code, int $discrepancy = 1): bool {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calced = self::tinhMaOtp($secret, $currentTime + ($i * 30));
            if (hash_equals($calced, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sinh URI chuẩn otpauth://totp/... cho ứng dụng Authenticator
     */
    public static function layTotpUri(string $secret, string $accountEmail, string $issuer = 'TiemAI'): string {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountEmail);
        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Sinh link ảnh QR code để khách quét bằng điện thoại
     */
    public static function layLinkQrCode(string $totpUri, int $size = 200): string {
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($totpUri);
    }

    /**
     * Tạo danh sách 8 mã dự phòng (Recovery Codes)
     */
    public static function taoDanhSachMaDuPhong(int $count = 8): array {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $part1 = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $part2 = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $codes[] = "{$part1}-{$part2}";
        }
        return $codes;
    }

    /**
     * Kiểm tra và tiêu hao một mã dự phòng (dùng 1 lần)
     */
    public static function kiemTraVaDungMaDuPhong(string $inputCode, ?string &$recoveryCodesJson): bool {
        if (empty($recoveryCodesJson)) {
            return false;
        }
        $codes = json_decode($recoveryCodesJson, true);
        if (!is_array($codes) || empty($codes)) {
            return false;
        }

        $cleanInput = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $inputCode));
        foreach ($codes as $idx => $code) {
            $cleanStored = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
            if (hash_equals($cleanStored, $cleanInput)) {
                // Tiêu hao mã: xóa khỏi mảng
                array_splice($codes, $idx, 1);
                $recoveryCodesJson = json_encode(array_values($codes));
                return true;
            }
        }
        return false;
    }
}
