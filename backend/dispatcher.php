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
    public function xuLyDonHang($maHoacIdDonHang, bool $forceRetry = false): array {
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

        // Nếu đơn đã xong và KHÔNG PHẢI đang forceRetry, và KHÔNG PHẢI key tạm GATEWAY-INSTANT:
        $isTemporaryGatewayKey = (!empty($donHang['supplier_order_code']) && $donHang['supplier_order_code'] === 'GATEWAY-INSTANT');
        if ($donHang['status'] === 'COMPLETED' && !$forceRetry && !$isTemporaryGatewayKey) {
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

        // 3. VÒNG LẶP THỬ TỪNG NGUỒN CUNG CẤP (TỰ ĐỘNG MUA KEY TỪ ĐỐI TÁC)
        if (!empty($danhSachNguon)) {
            foreach ($danhSachNguon as $nguon) {
                // Khởi tạo client kết nối với nhà cung cấp
                $apiClient = new NhaCungCapApi($nguon['api_url'], $nguon['api_key']);

                // Tạo Idempotency Key an toàn (đáp ứng chuẩn [A-Za-z0-9._:-] dài 8..128)
                $cleanCode = preg_replace('/[^A-Za-z0-9._:-]/', '', $donHang['order_code']);
                if (strlen($cleanCode) < 8) {
                    $cleanCode = 'VK_' . $cleanCode . '_' . substr(md5($donHang['id']), 0, 6);
                }
                $idempotencyKey = $cleanCode;
                if ($forceRetry) {
                    $idempotencyKey .= '_' . time();
                }

                $ketQuaMua = $apiClient->muaHangTuDong(
                    $nguon['supplier_sku'],
                    (int)$donHang['quantity'],
                    $idempotencyKey,
                    $donHang['customer_email']
                );

                // NẾU MUA THÀNH CÔNG TỪ NGUỒN NÀY:
                if ($ketQuaMua && !empty($ketQuaMua['success'])) {
                    $taiKhoanTraVeJson = json_encode($ketQuaMua['accounts'], JSON_UNESCAPED_UNICODE);

                    // Cập nhật trạng thái đơn thành HOÀN THÀNH (COMPLETED) và lưu lại Key thật từ đối tác
                    $upStmt = $this->db->prepare("
                        UPDATE `orders` 
                        SET `status` = 'COMPLETED',
                            `supplier_id` = ?,
                            `supplier_order_code` = ?,
                            `delivered_data` = ?,
                            `error_log` = NULL
                        WHERE `id` = ?
                    ");
                    $upStmt->execute([
                        $nguon['supplier_id'],
                        $ketQuaMua['order_code'] ?? ('NCC-' . date('YmdHis')),
                        $taiKhoanTraVeJson,
                        $donHang['id']
                    ]);

                    return [
                        'success' => true,
                        'thanh_cong' => true,
                        'supplier' => $nguon['ten_nha_cung_cap'],
                        'message' => 'Lấy key thành công từ đối tác ' . $nguon['ten_nha_cung_cap'],
                        'accounts' => $ketQuaMua['accounts']
                    ];
                } else {
                    // NẾU NGUỒN NÀY BỊ LỖI (Hết hàng / Lỗi kết nối / Hết tiền ví):
                    $chiTietLoi = $ketQuaMua['message'] ?? $ketQuaMua['error_code'] ?? 'Lỗi không xác định';
                    $nhatKyLoi[] = "Nguồn [{$nguon['ten_nha_cung_cap']}] lỗi: {$chiTietLoi}";
                }
            }
        } else {
            $nhatKyLoi[] = "Sản phẩm này chưa được liên kết với mã SKU của đối tác nào";
        }

        // 4. KIỂM TRA NẾU LÀ PHẦN MỀM TỰ PHÁT HÀNH (TungDV Studio Pro, TungDV Voice...):
        $stmtSp = $this->db->prepare("SELECT p.*, c.slug AS cat_slug, c.name AS cat_name FROM `products` p LEFT JOIN `categories` c ON p.category_id = c.id WHERE p.id = ?");
        $stmtSp->execute([$donHang['product_id']]);
        $sanPham = $stmtSp->fetch();
        $tenSp = $sanPham['name'] ?? '';
        $isPhanMem = (stripos($tenSp, 'phần mềm') !== false || stripos($tenSp, 'tungdv') !== false || stripos($sanPham['cat_slug'] ?? '', 'phan-mem') !== false);

        if ($isPhanMem) {
            $soNgay = !empty($sanPham['duration_days']) ? (int)$sanPham['duration_days'] : 30;
            $licenseKey = 'TDV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $accounts = ["Mã Kích Hoạt Bản Quyền: {$licenseKey} | Tải phần mềm tại: https://tiemai.io.vn/phan-mem | Thời hạn: {$soNgay} ngày"];
            $taiKhoanTraVeJson = json_encode($accounts, JSON_UNESCAPED_UNICODE);

            $this->db->prepare("
                UPDATE `orders` 
                SET `status` = 'COMPLETED',
                    `supplier_id` = NULL,
                    `supplier_order_code` = 'LICENSE-SOFTWARE',
                    `delivered_data` = ?,
                    `error_log` = NULL
                WHERE `id` = ?
            ")->execute([$taiKhoanTraVeJson, $donHang['id']]);

            return [
                'success' => true,
                'thanh_cong' => true,
                'supplier' => 'Bản Quyền Phần Mềm',
                'message' => 'Cấp mã bản quyền phần mềm thành công',
                'accounts' => $accounts
            ];
        }

        // 5. NẾU LÀ GÓI API ĐỐI TÁC MÀ ĐỐI TÁC TỪ CHỐI / LỖI / THIẾU TIỀN:
        // TUYỆT ĐỐI KHÔNG TỰ CẤP KEY GIẢ vk_ ĐỂ GIAO CHO KHÁCH (tránh lỗi khách không dùng được)!
        // Lưu trạng thái lỗi chi tiết vào error_log.
        $thongBaoLoi = implode(" || ", $nhatKyLoi);

        // Nếu khách mua bằng tài khoản thành viên (ví), hoàn tiền lại ví an toàn:
        if (!empty($donHang['user_id'])) {
            $checkRefund = $this->db->prepare("SELECT id FROM `transactions` WHERE `order_id` = ? AND `type` = 'REFUND' LIMIT 1");
            $checkRefund->execute([$donHang['id']]);
            $daHoanTien = $checkRefund->fetch();

            if (!$daHoanTien) {
                $this->hoanTienViKhach((int)$donHang['user_id'], (int)$donHang['id'], (float)$donHang['total_amount'], "Hoàn tiền đơn #{$donHang['order_code']}: Đối tác tạm gián đoạn cấp key");
                $this->db->prepare("UPDATE `orders` SET `status` = 'REFUNDED', `error_log` = ? WHERE `id` = ?")->execute([$thongBaoLoi, $donHang['id']]);
            } else {
                $this->danhDauThatBai($donHang['id'], $thongBaoLoi);
            }
        } else {
            // Khách mua chuyển khoản trực tiếp VietQR: Giữ ở PROCESSING để Admin xử lý / nạp ví đối tác / bấm thử lại
            $this->db->prepare("UPDATE `orders` SET `status` = 'PROCESSING', `error_log` = ? WHERE `id` = ?")->execute([$thongBaoLoi, $donHang['id']]);
        }

        return [
            'success' => false,
            'thanh_cong' => false,
            'error' => $thongBaoLoi,
            'message' => 'Đối tác chưa cấp được key: ' . $thongBaoLoi
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
