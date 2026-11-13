<?php
// ========================================================
// FILE BỘ ĐIỀU PHỐI ĐƠN HÀNG ĐA NGUỒN (ORDER DISPATCHER)
// vankhateamshop - Tự động mua từ nguồn rẻ nhất, tự nhảy sang nguồn 2 nếu nguồn 1 hết hàng
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/supplier_api.php';

class BoDieuPhoiDonHang {
    private PDO $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    /**
     * Bắt đầu xử lý mua hàng cho một đơn hàng (chấp nhận mã đơn hoặc ID)
     * @param string|int $maHoacIdDonHang Ví dụ: 'VK261113001' hoặc 1
     * @return array Kết quả xử lý
     */
    public function xuLyDonHang($maHoacIdDonHang): array {
        // 1. Tìm đơn hàng trong cơ sở dữ liệu
        if (is_numeric($maHoacIdDonHang)) {
            $stmt = $this->db->prepare("SELECT * FROM `orders` WHERE `id` = ?");
            $stmt->execute([(int)$maHoacIdDonHang]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM `orders` WHERE `order_code` = ?");
            $stmt->execute([(string)$maHoacIdDonHang]);
        }
        $donHang = $stmt->fetch();

        if (!$donHang) {
            return ['success' => false, 'thanh_cong' => false, 'error' => 'Không tìm thấy đơn hàng trong hệ thống'];
        }

        // Nếu đơn đã xong hoặc đã giao rồi thì không chạy lại
        if ($donHang['status'] === 'COMPLETED') {
            return [
                'success' => true,
                'thanh_cong' => true,
                'message' => 'Đơn hàng đã hoàn thành trước đó',
                'accounts' => json_decode($donHang['delivered_data'], true)
            ];
        }

        // Cập nhật trạng thái đơn thành: ĐANG XỬ LÝ (PROCESSING)
        $this->db->prepare("UPDATE `orders` SET `status` = 'PROCESSING' WHERE `id` = ?")->execute([$donHang['id']]);

        // 2. Tìm tất cả các nhà cung cấp có bán sản phẩm này
        // Sắp xếp: Ưu tiên số 1 (priority nhỏ nhất) và Giá nhập rẻ nhất (cost_price)
        $sql = "SELECT m.*, s.code AS ma_nha_cung_cap, s.name AS ten_nha_cung_cap, s.api_url, s.api_key 
                FROM `supplier_product_mappings` m
                JOIN `suppliers` s ON m.supplier_id = s.id
                WHERE m.product_id = ? AND m.is_active = 1 AND s.is_active = 1
                ORDER BY m.priority ASC, m.cost_price ASC";
        
        $stmtMapping = $this->db->prepare($sql);
        $stmtMapping->execute([$donHang['product_id']]);
        $danhSachNguon = $stmtMapping->fetchAll();

        if (empty($danhSachNguon)) {
            $this->danhDauThatBai($donHang['id'], 'Sản phẩm này chưa được liên kết với nhà cung cấp nào');
            return ['success' => false, 'thanh_cong' => false, 'error' => 'Chưa cấu hình nguồn hàng'];
        }

        $nhatKyLoi = [];

        // 3. VÒNG LẶP THỬ TỪNG NGUỒN CUNG CẤP (TỰ ĐỘNG CHUYỂN NGUỒN NẾU HẾT HÀNG)
        foreach ($danhSachNguon as $nguon) {
            // Khởi tạo client kết nối với nhà cung cấp
            $apiClient = new NhaCungCapApi($nguon['api_url'], $nguon['api_key']);
            $ketQuaMua = $apiClient->muaHangTuDong(
                $nguon['supplier_sku'],
                (int)$donHang['quantity'],
                $donHang['order_code'],
                $donHang['customer_email']
            );

            // NẾU MUA THÀNH CÔNG TỪ NGUỒN NÀY:
            if ($ketQuaMua && !empty($ketQuaMua['success'])) {
                $taiKhoanTraVeJson = json_encode($ketQuaMua['accounts'], JSON_UNESCAPED_UNICODE);

                // Cập nhật trạng thái đơn thành HOÀN THÀNH (COMPLETED) và lưu lại Key
                $upStmt = $this->db->prepare("
                    UPDATE `orders` 
                    SET `status` = 'COMPLETED',
                        `supplier_id` = ?,
                        `supplier_order_code` = ?,
                        `delivered_data` = ?,
                        `error_log` = ?
                    WHERE `id` = ?
                ");
                $upStmt->execute([
                    $nguon['supplier_id'],
                    $ketQuaMua['order_code'] ?? null,
                    $taiKhoanTraVeJson,
                    empty($nhatKyLoi) ? null : implode(" | ", $nhatKyLoi),
                    $donHang['id']
                ]);

                return [
                    'success' => true,
                    'thanh_cong' => true,
                    'supplier' => $nguon['ten_nha_cung_cap'],
                    'message' => 'Giao hàng thành công',
                    'accounts' => $ketQuaMua['accounts']
                ];
            } else {
                // NẾU NGUỒN NÀY BỊ LỖI (Hết hàng / Lỗi kết nối / Hết tiền ví):
                // Ghi lại lỗi và vòng lặp TỰ ĐỘNG CHUYỂN SANG NGUỒN TIẾP THEO (FALLBACK)
                $chiTietLoi = $ketQuaMua['message'] ?? $ketQuaMua['error_code'] ?? 'Lỗi không xác định';
                $nhatKyLoi[] = "Nguồn [{$nguon['ten_nha_cung_cap']}] lỗi: {$chiTietLoi}";
            }
        }

        // 4. NẾU TẤT CẢ CÁC NGUỒN ĐỀU THẤT BẠI:
        // Đánh dấu đơn là FAILED và tự động hoàn lại tiền vào ví cho khách
        $chuoiLoi = implode(" || ", $nhatKyLoi);
        $this->danhDauThatBai($donHang['id'], $chuoiLoi);

        // Hoàn tiền nếu khách mua bằng tài khoản thành viên
        if (!empty($donHang['user_id'])) {
            $this->hoanTienViKhach((int)$donHang['user_id'], (float)$donHang['total_amount'], "Hoàn tiền đơn #{$donHang['order_code']}: Toàn bộ nguồn cung cấp tạm hết hàng");
        }

        return [
            'success' => false,
            'thanh_cong' => false,
            'error' => 'Hiện tại tất cả các nguồn cung cấp đều tạm hết hàng hoặc gặp sự cố.',
            'chi_tiet' => $chuoiLoi
        ];
    }

    /**
     * Cập nhật trạng thái đơn thành Thất Bại
     */
    private function danhDauThatBai(int $donHangId, string $lyDoLoi): void {
        $stmt = $this->db->prepare("UPDATE `orders` SET `status` = 'FAILED', `error_log` = ? WHERE `id` = ?");
        $stmt->execute([$lyDoLoi, $donHangId]);
    }

    /**
     * Hoàn tiền lại vào ví khách hàng trên website
     */
    private function hoanTienViKhach(int $userId, float $soTien, string $ghiChu): void {
        $stmtUser = $this->db->prepare("SELECT `balance` FROM `users` WHERE `id` = ? FOR UPDATE");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        if ($user) {
            $soDuCu = (float)$user['balance'];
            $soDuMoi = $soDuCu + $soTien;

            // Cộng tiền vào tài khoản
            $this->db->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ?")->execute([$soDuMoi, $userId]);

            // Ghi nhật ký giao dịch
            $logStmt = $this->db->prepare("
                INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `note`)
                VALUES (?, 'REFUND', ?, ?, ?, ?)
            ");
            $logStmt->execute([$userId, $soTien, $soDuCu, $soDuMoi, $ghiChu]);
        }
    }
}
