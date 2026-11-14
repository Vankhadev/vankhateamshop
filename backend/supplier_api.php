<?php
// ========================================================
// MODULE KẾT NỐI API NHÀ CUNG CẤP (SUPPLIER API CLIENT)
// vankhateamshop - Gọi API lấy số dư ví, kho hàng, đặt hàng tự động
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';

class NhaCungCapApi {
    private string $baseUrl;
    private string $apiKey;

    /**
     * Khởi tạo kết nối với Nhà Cung Cấp
     * @param string|null $apiUrl URL gốc API (mặc định lấy từ cấu hình SUPPLIER_API_URL)
     * @param string|null $apiKey Khóa API bí mật (mặc định lấy từ cấu hình SUPPLIER_API_KEY)
     */
    public function __construct(?string $apiUrl = null, ?string $apiKey = null) {
        $this->baseUrl = rtrim($apiUrl ?: SUPPLIER_API_URL, '/');
        $this->apiKey = $apiKey ?: SUPPLIER_API_KEY;
    }

    /**
     * Hàm dùng chung để gửi yêu cầu HTTP (cURL) sang hệ thống đối tác
     */
    private function guiYeuCau(string $endpoint, string $phuongThuc = 'GET', array $duLieu = []): array {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'X-API-Key: ' . $this->apiKey,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) VanKhaShop/1.0'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($phuongThuc === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($duLieu, JSON_UNESCAPED_UNICODE));
        }

        $phanHoiRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Trường hợp lỗi mạng cURL
        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Lỗi kết nối mạng tới máy chủ nhà cung cấp: ' . $curlError,
                'http_code' => $httpCode
            ];
        }

        // Giải mã phản hồi dạng JSON
        $ketQua = json_decode($phanHoiRaw, true);
        if (!is_array($ketQua)) {
            return [
                'success' => false,
                'message' => 'Phản hồi từ nhà cung cấp không hợp lệ: ' . substr($phanHoiRaw, 0, 200),
                'http_code' => $httpCode,
                'raw' => $phanHoiRaw
            ];
        }

        return $ketQua;
    }

    /**
     * 1. Lấy số dư ví hiện tại trên hệ thống nhà cung cấp
     */
    public function laySoDuVi(): array {
        // Đa số các hệ thống dùng endpoint /balance hoặc /balance.php
        $ketQua = $this->guiYeuCau('/balance.php', 'GET');
        
        // Nếu hệ thống trả về số dư theo cấu trúc chuẩn
        if (isset($ketQua['balance'])) {
            return [
                'success' => true,
                'balance' => (float)$ketQua['balance'],
                'currency' => $ketQua['currency'] ?? 'VND',
                'raw' => $ketQua
            ];
        }

        // Nếu hệ thống bọc trong data.balance
        if (isset($ketQua['data']['balance'])) {
            return [
                'success' => true,
                'balance' => (float)$ketQua['data']['balance'],
                'currency' => $ketQua['data']['currency'] ?? 'VND',
                'raw' => $ketQua
            ];
        }

        return [
            'success' => false,
            'message' => $ketQua['message'] ?? 'Không đọc được số dư từ nhà cung cấp',
            'raw' => $ketQua
        ];
    }

    /**
     * 2. Lấy danh sách sản phẩm và tồn kho thời gian thực
     */
    public function layDanhSachSanPham(): array {
        return $this->guiYeuCau('/products.php', 'GET');
    }

    /**
     * 3. Mua tài khoản/gói cước tự động
     * 
     * @param string $supplierSku Mã SKU của nhà cung cấp (vd: CUR-30D-700)
     * @param int $soLuong Số lượng cần mua (mặc định 1)
     * @param string $maDonHangChongTrung Mã chống trừ tiền 2 lần (Idempotency Key)
     * @param string $emailKhach Email của khách hàng để nhận bảo hành
     * @return array Kết quả trả về gồm tài khoản hoặc token đã giao
     */
    public function muaHangTuDong(string $supplierSku, int $soLuong, string $maDonHangChongTrung, string $emailKhach = ''): array {
        $payload = [
            'product_id' => $supplierSku,
            'quantity' => $soLuong,
            'idempotency_key' => $maDonHangChongTrung,
            'customer_email' => $emailKhach
        ];

        $phanHoi = $this->guiYeuCau('/purchase.php', 'POST', $payload);

        // Kiểm tra xem đơn hàng đã được giao thành công hay chưa
        $thanhCong = false;
        $danhSachTaiKhoan = [];

        if (isset($phanHoi['success']) && $phanHoi['success'] === true) {
            $thanhCong = true;
            $danhSachTaiKhoan = $phanHoi['deliveredAccounts'] ?? $phanHoi['data']['accounts'] ?? [];
        } elseif (isset($phanHoi['status']) && $phanHoi['status'] === 'success') {
            $thanhCong = true;
            $danhSachTaiKhoan = $phanHoi['deliveredAccounts'] ?? $phanHoi['data']['accounts'] ?? [];
        }

        // Chuẩn hóa danh sách tài khoản / token / key trả về để khách hàng dễ sao chép
        $formattedAccounts = [];
        if (!empty($danhSachTaiKhoan) && is_array($danhSachTaiKhoan)) {
            foreach ($danhSachTaiKhoan as $item) {
                if (is_array($item)) {
                    if (!empty($item['key'])) {
                        $formattedAccounts[] = $item['key'];
                    } elseif (!empty($item['token'])) {
                        $formattedAccounts[] = $item['token'];
                    } elseif (!empty($item['apiKey'])) {
                        $formattedAccounts[] = $item['apiKey'];
                    } elseif (!empty($item['user']) && !empty($item['password'])) {
                        $accStr = $item['user'] . '|' . $item['password'];
                        if (!empty($item['verifyEmail'])) $accStr .= '|' . $item['verifyEmail'];
                        $formattedAccounts[] = $accStr;
                    } else {
                        $formattedAccounts[] = json_encode($item, JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $formattedAccounts[] = (string)$item;
                }
            }
        }

        return [
            'success' => $thanhCong,
            'accounts' => !empty($formattedAccounts) ? $formattedAccounts : $danhSachTaiKhoan,
            'order_code' => $phanHoi['orderCode'] ?? $phanHoi['order_id'] ?? null,
            'error_code' => $phanHoi['errorCode'] ?? null,
            'message' => $phanHoi['detail'] ?? $phanHoi['message'] ?? ($thanhCong ? 'Thành công' : 'Thất bại'),
            'raw' => $phanHoi
        ];
    }
}
