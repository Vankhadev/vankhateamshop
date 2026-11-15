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
     * @param bool $forceRetry Bắt buộc gọi lại đối tác (kể cả đơn đã cấp gateway trước đó)
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

        // Nếu đơn đã xong và KHÔNG PHẢI đang forceRetry, và KHÔNG PHẢI key tạm GATEWAY:
        $isTemporaryGatewayKey = (!empty($donHang['supplier_order_code']) && str_starts_with($donHang['supplier_order_code'], 'GATEWAY'));
        if ($donHang['status'] === 'COMPLETED' && !$forceRetry && !$isTemporaryGatewayKey) {
            return [
                'success' => true,
                'thanh_cong' => true,
                'message' => 'Đơn hàng đã hoàn thành trước đó',
                'accounts' => json_decode($donHang['delivered_data'], true)
            ];
        }

        // Nếu đơn đã được hoàn tiền rồi và không phải forceRetry thì không tự chạy lại
        if ($donHang['status'] === 'REFUNDED' && !$forceRetry) {
            return [
                'success' => false,
                'thanh_cong' => false,
                'error' => 'Đơn hàng này đã được hoàn tiền vào ví khách hàng trước đó!'
            ];
        }

        // Cập nhật trạng thái đơn thành: ĐANG XỬ LÝ (PROCESSING) nếu chưa COMPLETED
        if ($donHang['status'] !== 'COMPLETED') {
            $this->db->prepare("UPDATE `orders` SET `status` = 'PROCESSING' WHERE `id` = ?")->execute([$donHang['id']]);
        }

        // 2. Tìm tất cả các nhà cung cấp có bán sản phẩm này
        // Sắp xếp thông minh đa nguồn:
        // 1) Ưu tiên nguồn có số dư ví ĐỦ để mua (>= cost_price * quantity)
        // 2) Ưu tiên cấu hình ưu tiên của Admin (priority)
        // 3) Ưu tiên giá nhập rẻ nhất (cost_price)
        $soLuongMua = max(1, (int)$donHang['quantity']);
        $sql = "SELECT m.*, s.code AS ma_nha_cung_cap, s.name AS ten_nha_cung_cap, s.api_url, s.api_key, s.balance AS supplier_balance
                FROM `supplier_product_mappings` m
                JOIN `suppliers` s ON m.supplier_id = s.id
                WHERE m.product_id = ? AND m.is_active = 1 AND s.is_active = 1
                ORDER BY 
                    CASE 
                        WHEN (s.balance IS NOT NULL AND s.balance >= (m.cost_price * ?)) THEN 0 
                        WHEN (s.balance IS NULL OR s.balance <= 0) THEN 1 
                        ELSE 2 
                    END ASC,
                    m.priority ASC, 
                    m.cost_price ASC";
        
        $stmtMapping = $this->db->prepare($sql);
        $stmtMapping->execute([$donHang['product_id'], $soLuongMua]);
        $danhSachNguon = $stmtMapping->fetchAll();

        $nhatKyLoi = [];

        // 3. VÒNG LẶP THỬ TỪNG NGUỒN CUNG CẤP (TỰ ĐỘNG GỌI LẤY KEY TỪ ĐỐI TÁC)
        if (!empty($danhSachNguon)) {
            foreach ($danhSachNguon as $nguon) {
                // Khởi tạo client kết nối với nhà cung cấp
                $apiClient = new NhaCungCapApi($nguon['api_url'], $nguon['api_key']);

                // Tạo Idempotency Key an toàn (đáp ứng chuẩn [A-Za-z0-9._:-] dài 8..128 ký tự)
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

                // NẾU MUA THÀNH CÔNG TỪ ĐỐI TÁC:
                if ($ketQuaMua && !empty($ketQuaMua['success']) && !empty($ketQuaMua['accounts'])) {
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
                    // NẾU NGUỒN NÀY BỊ LỖI (Hết tiền ví đối tác / Lỗi kết nối / Lỗi đối tác):
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

        // 5. NẾU TOÀN BỘ ĐỐI TÁC THẤT BẠI VÀ KHÔNG PHẢI PHẦN MỀM TỰ PHÁT HÀNH:
        // TUYỆT ĐỐI KHÔNG CẤP KEY GIẢ (sk-tiemai-...) VÌ KHO ĐỐI TÁC HẾT HÀNG/HẾT TIỀN THÌ KHÔNG THỂ DỘI REQUEST!
        $chuoiLoi = implode(" || ", $nhatKyLoi);

        // Kiểm tra xem đơn hàng này được thanh toán bằng VÍ SỐ DƯ hay VIETQR NGÂN HÀNG:
        $isWalletOrder = false;
        if (!empty($donHang['user_id'])) {
            $stmtTx = $this->db->prepare("SELECT id FROM `transactions` WHERE `order_id` = ? AND `type` = 'PURCHASE' LIMIT 1");
            $stmtTx->execute([$donHang['id']]);
            if ($stmtTx->fetchColumn()) {
                $isWalletOrder = true;
            }
        }

        if ($isWalletOrder) {
            // NẾU MUA BẰNG VÍ: Tự động hoàn tiền 100% về ví tài khoản khách ngay lập tức
            $this->hoanTienViKhach((int)$donHang['user_id'], (int)$donHang['id'], (float)$donHang['total_amount'], "Hoàn tiền đơn hàng #{$donHang['order_code']} do kho đối tác lỗi/hết hàng");

            $this->db->prepare("UPDATE `orders` SET `status` = 'REFUNDED', `error_log` = ? WHERE `id` = ?")
                ->execute([$chuoiLoi, $donHang['id']]);

            return [
                'success' => false,
                'thanh_cong' => false,
                'status' => 'REFUNDED',
                'error' => 'Kho hàng đối tác tạm thời hết hàng hoặc đang bảo trì gói này. Số dư đã được hoàn lại 100% vào ví của bạn!',
                'chi_tiet' => $chuoiLoi
            ];
        } else {
            // NẾU MUA QUA VIETQR / NGÂN HÀNG ACB (Tiền thật đã vào tài khoản ngân hàng):
            // Giữ đơn hàng ở trạng thái PROCESSING (Đang xử lý / Chờ xuất kho) để Admin xử lý hoặc thử lại
            $this->db->prepare("UPDATE `orders` SET `status` = 'PROCESSING', `error_log` = ? WHERE `id` = ?")
                ->execute([$chuoiLoi, $donHang['id']]);

            // Bắn cảnh báo Telegram khẩn cấp cho Admin
            try {
                require_once __DIR__ . '/telegram.php';
                $tenGoiMua = $sanPham['name'] ?? ('Sản phẩm #' . $donHang['product_id']);
                $msg = "🚨 <b>ĐƠN HÀNG ĐÃ THANH TOÁN TIỀN NHƯNG CHƯA XUẤT ĐƯỢC KEY!</b>\n";
                $msg .= "📦 Mã đơn: <code>{$donHang['order_code']}</code>\n";
                $msg .= "👤 Khách: <b>{$donHang['customer_email']}</b>\n";
                $msg .= "🛒 Gói: <b>{$tenGoiMua}</b>\n";
                $msg .= "💵 Số tiền: <b>" . number_format($donHang['total_amount'], 0, ',', '.') . " VNĐ</b>\n";
                $msg .= "❌ Lỗi đối tác: <i>{$chuoiLoi}</i>\n";
                $msg .= "👉 Admin vui lòng nạp thêm tiền ví đại lý đối tác hoặc cấp key tay trong Admin!";
                guiThongBaoTelegram($msg, 'order');
            } catch (Exception $eTele) {}

            return [
                'success' => false,
                'thanh_cong' => false,
                'status' => 'PROCESSING',
                'error' => 'Đã nhận thanh toán từ ngân hàng. Do kho đối tác tạm thời chưa xuất được key tự động, Admin đang kiểm tra xử lý để gửi key cho bạn trong giây lát.',
                'chi_tiet' => $chuoiLoi
            ];
        }
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
        // Kiểm tra chống hoàn tiền lặp lại cho cùng một đơn hàng
        $stmtCheck = $this->db->prepare("SELECT id FROM `transactions` WHERE `order_id` = ? AND `type` = 'REFUND' LIMIT 1");
        $stmtCheck->execute([$orderId]);
        if ($stmtCheck->fetchColumn()) {
            return; // Đã hoàn tiền trước đó rồi
        }

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
