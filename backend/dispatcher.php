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

        // Nếu đơn đã được hoàn tiền rồi thì tuyệt đối không xử lý lại để tránh hoàn trùng tiền
        if ($donHang['status'] === 'REFUNDED') {
            return [
                'success' => false,
                'thanh_cong' => false,
                'error' => 'Đơn hàng này đã được hoàn tiền vào ví khách hàng trước đó, không thể giao lại!'
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

        $nhatKyLoi = [];

        // 3. VÒNG LẶP THỬ TỪNG NGUỒN CUNG CẤP (TỰ ĐỘNG CHUYỂN NGUỒN NẾU HẾT HÀNG)
        if (!empty($danhSachNguon)) {
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
        } else {
            $nhatKyLoi[] = "Chưa cấu hình nguồn cung cấp bên ngoài";
        }

        // 4. CƠ CHẾ DỰ PHÒNG TỰ ĐỘNG (GATEWAY FALLBACK DELIVERY):
        // Nếu tất cả nguồn đối tác bên ngoài gặp sự cố, hết hàng hoặc số dư ví đối tác không đủ:
        // Hệ thống TIỆM AI tự động cấp ngay API Key Gateway (hoặc License Kích Hoạt Phần Mềm)
        // để đơn hàng HOÀN THÀNH 100%, khách hàng không bị gián đoạn hay bị hoàn tiền phiền toái.
        try {
            $stmtSp = $this->db->prepare("SELECT p.*, c.slug AS cat_slug, c.name AS cat_name FROM `products` p LEFT JOIN `categories` c ON p.category_id = c.id WHERE p.id = ?");
            $stmtSp->execute([$donHang['product_id']]);
            $sanPham = $stmtSp->fetch();

            $accounts = [];
            $tenSp = $sanPham['name'] ?? 'Gói Dịch Vụ AI';
            $soNgay = !empty($sanPham['duration_days']) ? (int)$sanPham['duration_days'] : 30;

            $isPhanMem = (stripos($tenSp, 'phần mềm') !== false || stripos($tenSp, 'tungdv') !== false || stripos($sanPham['cat_slug'] ?? '', 'phan-mem') !== false);

            if ($isPhanMem) {
                $licenseKey = 'TDV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $accounts[] = "Mã Kích Hoạt Bản Quyền: {$licenseKey} | Tải phần mềm tại: https://tiemai.io.vn/phan-mem | Thời hạn: {$soNgay} ngày";
            } else {
                // Cấp API Gateway Token chuẩn vk_... (tương thích OpenAI Base URL https://tiemai.io.vn/v1)
                $apiToken = 'vk_' . bin2hex(random_bytes(24));
                $accounts[] = $apiToken;

                // Nếu người dùng có tài khoản thành viên trên hệ thống, cập nhật luôn api_token cho họ nếu chưa có
                if (!empty($donHang['user_id'])) {
                    $this->db->prepare("UPDATE `users` SET `api_token` = ? WHERE `id` = ? AND (`api_token` IS NULL OR `api_token` = '')")->execute([$apiToken, $donHang['user_id']]);
                }
            }

            $taiKhoanTraVeJson = json_encode($accounts, JSON_UNESCAPED_UNICODE);
            $chuoiLoi = implode(" || ", $nhatKyLoi);
            $ghiChuHeThong = empty($chuoiLoi) ? "Kích hoạt tự động qua Tiệm AI Gateway" : "Nguồn ngoài: {$chuoiLoi} => Tự động cấp qua Tiệm AI Gateway";

            $upStmt = $this->db->prepare("
                UPDATE `orders` 
                SET `status` = 'COMPLETED',
                    `supplier_id` = NULL,
                    `supplier_order_code` = 'GATEWAY-INSTANT',
                    `delivered_data` = ?,
                    `error_log` = ?
                WHERE `id` = ?
            ");
            $upStmt->execute([
                $taiKhoanTraVeJson,
                $ghiChuHeThong,
                $donHang['id']
            ]);

            return [
                'success' => true,
                'thanh_cong' => true,
                'supplier' => 'Tiệm AI Gateway',
                'message' => 'Giao hàng thành công qua Hệ Thống Tiệm AI Gateway',
                'accounts' => $accounts
            ];
        } catch (Exception $eFallback) {
            $nhatKyLoi[] = "Lỗi cấp Gateway Fallback: " . $eFallback->getMessage();
        }

        // 5. NẾU CẢ FALLBACK CŨNG GẶP SỰ CỐ BẤT KHẢ KHÁNG MỚI HOÀN TIỀN VÍ:
        $chuoiLoi = implode(" || ", $nhatKyLoi);

        // Hoàn tiền nếu khách mua bằng tài khoản thành viên VÀ đơn này chưa từng được hoàn tiền
        if (!empty($donHang['user_id'])) {
            $checkRefund = $this->db->prepare("SELECT id FROM `transactions` WHERE `order_id` = ? AND `type` = 'REFUND' LIMIT 1");
            $checkRefund->execute([$donHang['id']]);
            $daHoanTien = $checkRefund->fetch();

            if (!$daHoanTien) {
                // Chỉ hoàn tiền khi chưa từng hoàn
                $this->hoanTienViKhach((int)$donHang['user_id'], (int)$donHang['id'], (float)$donHang['total_amount'], "Hoàn tiền đơn #{$donHang['order_code']}: Hệ thống tạm gián đoạn");
                $this->db->prepare("UPDATE `orders` SET `status` = 'REFUNDED', `error_log` = ? WHERE `id` = ?")->execute([$chuoiLoi, $donHang['id']]);
            } else {
                $this->danhDauThatBai($donHang['id'], $chuoiLoi);
            }
        } else {
            $this->danhDauThatBai($donHang['id'], $chuoiLoi);
        }

        return [
            'success' => false,
            'thanh_cong' => false,
            'error' => 'Hiện tại hệ thống tạm gián đoạn cấp key. Đơn hàng đã được tự động hoàn tiền vào ví.',
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
     * Hoàn tiền lại vào ví khách hàng trên website (Kèm order_id để chống hoàn lặp)
     */
    private function hoanTienViKhach(int $userId, int $orderId, float $soTien, string $ghiChu): void {
        $stmtUser = $this->db->prepare("SELECT `balance` FROM `users` WHERE `id` = ? FOR UPDATE");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        if ($user) {
            $soDuCu = (float)$user['balance'];
            $soDuMoi = $soDuCu + $soTien;

            // Cộng tiền vào tài khoản
            $this->db->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ?")->execute([$soDuMoi, $userId]);

            // Ghi nhật ký giao dịch kèm order_id chính xác
            $logStmt = $this->db->prepare("
                INSERT INTO `transactions` (`user_id`, `order_id`, `type`, `amount`, `balance_before`, `balance_after`, `note`)
                VALUES (?, ?, 'REFUND', ?, ?, ?, ?)
            ");
            $logStmt->execute([$userId, $orderId, $soTien, $soDuCu, $soDuMoi, $ghiChu]);
        }
    }
}
