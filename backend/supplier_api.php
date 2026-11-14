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

        $ketQua['http_code'] = $httpCode;
        return $ketQua;
    }

    /**
     * 1. Lấy số dư ví hiện tại trên hệ thống nhà cung cấp
     */
    public function laySoDuVi(): array {
        // 1. Thử endpoint /balance.php (chuẩn Genzshop)
        $ketQua = $this->guiYeuCau('/balance.php', 'GET');
        
        // 2. Nếu không có số dư hoặc trả về 404, thử /balance (chuẩn Taphoaai, RESTful)
        if (!isset($ketQua['balance']) && !isset($ketQua['data']['balance'])) {
            $ketQua2 = $this->guiYeuCau('/balance', 'GET');
            if (isset($ketQua2['balance']) || isset($ketQua2['data']['balance'])) {
                $ketQua = $ketQua2;
            }
        }

        // Cấu trúc trả về trực tiếp balance (Genzshop: {"balance":24000} hoặc Taphoaai: {"ok":true,"balance":48000})
        if (isset($ketQua['balance'])) {
            return [
                'success'  => true,
                'balance'  => (float)$ketQua['balance'],
                'currency' => $ketQua['currency'] ?? ($ketQua['walletCurrency'] ?? 'VND'),
                'shop_id'  => $ketQua['shopId'] ?? null,
                'raw'      => $ketQua
            ];
        }

        // Cấu trúc bọc trong data.balance
        if (isset($ketQua['data']['balance'])) {
            return [
                'success'  => true,
                'balance'  => (float)$ketQua['data']['balance'],
                'currency' => $ketQua['data']['currency'] ?? ($ketQua['data']['walletCurrency'] ?? 'VND'),
                'raw'      => $ketQua
            ];
        }

        return [
            'success' => false,
            'message' => $ketQua['error'] ?? ($ketQua['message'] ?? 'Không đọc được số dư từ nhà cung cấp'),
            'raw'     => $ketQua
        ];
    }

    /**
     * 2. Lấy danh sách sản phẩm và tồn kho thời gian thực
     */
    public function layDanhSachSanPham(): array {
        // 1. Thử /products.php (Genzshop)
        $raw = $this->guiYeuCau('/products.php', 'GET');

        // 2. Nếu không có products, thử /products (Taphoaai)
        if (empty($raw['products']) && empty($raw['data'])) {
            $raw2 = $this->guiYeuCau('/products', 'GET');
            if (!empty($raw2['products']) || !empty($raw2['data'])) {
                $raw = $raw2;
            }
        }

        $items = $raw['products'] ?? ($raw['data'] ?? []);
        $normalized = [];

        foreach ($items as $it) {
            $sku = trim($it['productId'] ?? ($it['product_id'] ?? ($it['code'] ?? '')));
            if (empty($sku)) continue;

            $normalized[] = [
                'product_id'     => $sku,
                'productId'      => $sku,
                'name'           => trim($it['title'] ?? ($it['name'] ?? '')),
                'description'    => trim($it['description'] ?? ($it['title'] ?? '')),
                'type'           => $it['type'] ?? 'other',
                'walletPricing'  => (float)($it['walletPricing'] ?? ($it['price'] ?? 0)),
                'price'          => (float)($it['price'] ?? ($it['walletPricing'] ?? 0)),
                'available'      => (int)($it['available'] ?? ($it['stock'] ?? 0)),
                'stock'          => (int)($it['stock'] ?? ($it['available'] ?? 0)),
                'walletCurrency' => $it['walletCurrency'] ?? 'VND'
            ];
        }

        return [
            'success'  => !empty($normalized) || !empty($raw['ok']) || !empty($raw['success']),
            'products' => $normalized,
            'raw'      => $raw
        ];
    }

    /**
     * 3. Mua tài khoản/gói cước tự động
     * 
     * @param string $supplierSku Mã SKU của nhà cung cấp (vd: CUR-0013)
     * @param int $soLuong Số lượng cần mua (mặc định 1)
     * @param string $maDonHangChongTrung Mã chống trừ tiền 2 lần (Idempotency Key / shopOrderId)
     * @param string $emailKhach Email của khách hàng để nhận bảo hành
     * @return array Kết quả trả về gồm tài khoản hoặc token đã giao
     */
    public function muaHangTuDong(string $supplierSku, int $soLuong, string $maDonHangChongTrung, string $emailKhach = ''): array {
        $payload = [
            'product_id'      => $supplierSku,
            'productId'       => $supplierSku,
            'quantity'        => $soLuong,
            'qty'             => $soLuong,
            'idempotency_key' => $maDonHangChongTrung,
            'shopOrderId'     => $maDonHangChongTrung,
            'customer_email'  => $emailKhach
        ];

        // 1. Thử gửi tới /purchase.php (chuẩn Genzshop)
        $phanHoi = $this->guiYeuCau('/purchase.php', 'POST', $payload);

        // 2. Nếu trả về 404 (chuẩn RESTful như Taphoaai), thử gửi tới /orders
        if (!empty($phanHoi['http_code']) && $phanHoi['http_code'] === 404) {
            $phanHoi = $this->guiYeuCau('/orders', 'POST', $payload);
        }

        // Kiểm tra xem đơn hàng đã được giao thành công hay chưa
        $thanhCong = false;
        $danhSachTaiKhoan = [];

        if (!empty($phanHoi['success']) && $phanHoi['success'] === true) {
            $thanhCong = true;
            $danhSachTaiKhoan = $phanHoi['deliveredAccounts'] ?? ($phanHoi['data']['accounts'] ?? []);
        } elseif (!empty($phanHoi['ok']) && $phanHoi['ok'] === true) {
            $thanhCong = true;
            $danhSachTaiKhoan = $phanHoi['order']['accounts'] ?? ($phanHoi['accounts'] ?? []);
        } elseif (!empty($phanHoi['status']) && in_array(strtolower($phanHoi['status']), ['success', 'completed'])) {
            $thanhCong = true;
            $danhSachTaiKhoan = $phanHoi['deliveredAccounts'] ?? ($phanHoi['data']['accounts'] ?? ($phanHoi['accounts'] ?? []));
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
            'success'    => $thanhCong,
            'accounts'   => !empty($formattedAccounts) ? $formattedAccounts : $danhSachTaiKhoan,
            'order_code' => $phanHoi['orderCode'] ?? ($phanHoi['order_id'] ?? ($phanHoi['order']['shopOrderId'] ?? null)),
            'error_code' => $phanHoi['errorCode'] ?? ($phanHoi['error'] ?? null),
            'message'    => $phanHoi['detail'] ?? ($phanHoi['message'] ?? ($phanHoi['error'] ?? ($thanhCong ? 'Thành công' : 'Thất bại'))),
            'raw'        => $phanHoi
        ];
    }
}
