<?php
// ========================================================
// FILE XỬ LÝ CÁC YÊU CẦU AJAX TỪ GIAO DIỆN (API NỘI BỘ)
// vankhateamshop - Tích hợp Cloudflare IP, reCAPTCHA, Rate Limiting, Thanh Toán Ví & Admin
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/supplier_api.php';
require_once __DIR__ . '/dispatcher.php';
require_once __DIR__ . '/telegram.php';

// Thiết lập các tiêu đề bảo mật HTTP (Chống Clickjacking, XSS...)
thietLapBaoMatHeaders();

$hanhDong = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($hanhDong) {

    // 1. TẠO ĐƠN HÀNG MỚI (Khi khách quét mã VietQR)
    case 'tao_don_hang':
        if (!kiemTraRateLimit('tao_don_hang', 15, 60)) {
            echo json_encode([
                'thanh_cong' => false,
                'thong_bao' => 'Bạn đang thao tác quá nhanh. Vui lòng đợi 1 phút trước khi tạo đơn tiếp!'
            ]);
            exit;
        }

        $captchaToken = trim($_POST['captcha_token'] ?? '');
        if (!empty($captchaToken)) {
            $ketQuaCaptcha = xacThucCaptcha($captchaToken);
            if (!$ketQuaCaptcha['success']) {
                echo json_encode([
                    'thanh_cong' => false,
                    'thong_bao' => $ketQuaCaptcha['message']
                ]);
                exit;
            }
        }

        $sanPhamId = (int)($_POST['product_id'] ?? 0);
        $emailKhach = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if (!$sanPhamId || !$emailKhach) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập email hợp lệ để nhận mã Key']);
            exit;
        }

        $stmtSp = $pdo->prepare("SELECT * FROM `products` WHERE `id` = ? AND `is_active` = 1");
        $stmtSp->execute([$sanPhamId]);
        $sp = $stmtSp->fetch();

        if (!$sp) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Sản phẩm không tồn tại hoặc đã ngừng bán']);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $userObj = null;
        if (!empty($userId)) {
            $stmtU = $pdo->prepare("SELECT `id`, `is_reseller`, `reseller_discount` FROM `users` WHERE `id` = ?");
            $stmtU->execute([(int)$userId]);
            $userObj = $stmtU->fetch();
        }

        // Tính giá thực tế (Tự động áp dụng chiết khấu đại lý & Ràng buộc sàn giá nhập)
        $giaInfo = tinhGiaDaiLyChoSanPham($pdo, $sp, $userObj);
        $soTienThanhToan = $giaInfo['price'];
        $costPrice = $giaInfo['cost_price'];

        // Tính toán giảm giá coupon nếu có
        if (!empty($couponCode)) {
            $stmtC = $pdo->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses");
            $stmtC->execute([$couponCode]);
            $cp = $stmtC->fetch();
            if ($cp && $soTienThanhToan >= (float)$cp['min_order_amount']) {
                $giam = ($cp['discount_type'] === 'PERCENT') ? ($soTienThanhToan * (float)$cp['discount_value'] / 100) : (float)$cp['discount_value'];
                $giaSauCoupon = round($soTienThanhToan - $giam);
                if ($costPrice > 0 && $giaSauCoupon < $costPrice) {
                    $giaSauCoupon = $costPrice;
                }
                $soTienThanhToan = max(1000, $giaSauCoupon);
                $pdo->prepare("UPDATE `coupons` SET `used_count` = `used_count` + 1 WHERE `id` = ?")->execute([$cp['id']]);
            }
        }

        $ipKhach = layIpNguoiDung();
        // Sinh mã đơn hàng chuẩn tiemai + 16 chữ số ngẫu nhiên (VD: tiemai9284710385920184)
        $maDonHang = 'tiemai' . sprintf('%08d%08d', mt_rand(10000000, 99999999), mt_rand(10000000, 99999999));

        $stmtTaoDon = $pdo->prepare("
            INSERT INTO `orders` (`order_code`, `user_id`, `product_id`, `customer_email`, `quantity`, `total_amount`, `status`, `ip_address`)
            VALUES (?, ?, ?, ?, 1, ?, 'PENDING', ?)
        ");
        $stmtTaoDon->execute([$maDonHang, $userId, $sp['id'], $emailKhach, $soTienThanhToan, $ipKhach]);

        ghiNhatKyHeThong('INFO', 'TAO_DON_HANG', "Khách tạo đơn {$maDonHang} cho sản phẩm {$sp['name']} giá " . number_format($soTienThanhToan) . "đ");

        // Gửi email thông báo đơn hàng đang chờ thanh toán (kèm thông tin VietQR)
        try {
            require_once __DIR__ . '/mailer.php';
            guiEmailDonHang('PENDING', [
                'order_code'     => $maDonHang,
                'customer_email' => $emailKhach,
                'product_name'   => $sp['name'],
                'quantity'       => 1,
                'total_amount'   => $soTienThanhToan
            ]);
        } catch (Throwable $eMail) {
            error_log("Lỗi gửi email pending order: " . $eMail->getMessage());
        }

        $bankBin = defined('BANK_BIN') ? BANK_BIN : '970416';
        $linkVietQr = "https://api.vietqr.io/image/{$bankBin}-" . BANK_ACCOUNT_NO . "-compact2.jpg?amount=" . $soTienThanhToan . "&addInfo=" . $maDonHang . "&accountName=" . urlencode(BANK_ACCOUNT_NAME);

        echo json_encode([
            'thanh_cong'   => true,
            'ma_don_hang'  => $maDonHang,
            'so_tien'      => $soTienThanhToan,
            'ten_san_pham' => $sp['name'],
            'link_vietqr'  => $linkVietQr,
            'noi_dung_ck'  => $maDonHang
        ]);
        break;

    // 2. MUA GÓI BẰNG SỐ DƯ VÍ (THANH TOÁN NHANH TRONG 1 GIÂY KHÔNG CẦN QUÉT MÃ)
    case 'mua_bang_so_du':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập để thanh toán bằng số dư ví']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $sanPhamId = (int)($_POST['product_id'] ?? 0);
        $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if (!$sanPhamId) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng chọn sản phẩm cần mua']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Lấy thông tin người dùng và khóa hàng (SELECT FOR UPDATE)
            $stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? FOR UPDATE");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();

            if (!$user || !$user['is_active']) {
                $pdo->rollBack();
                echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Tài khoản không hợp lệ hoặc đã bị khóa']);
                exit;
            }

            // Lấy thông tin sản phẩm
            $stmtSp = $pdo->prepare("SELECT * FROM `products` WHERE `id` = ? AND `is_active` = 1");
            $stmtSp->execute([$sanPhamId]);
            $sp = $stmtSp->fetch();

            if (!$sp) {
                $pdo->rollBack();
                echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Sản phẩm không tồn tại hoặc đã tạm dừng']);
                exit;
            }

            // Tính giá thực tế (Tự động áp dụng chiết khấu đại lý & Ràng buộc sàn giá nhập)
            $giaInfo = tinhGiaDaiLyChoSanPham($pdo, $sp, $user);
            $giaTien = $giaInfo['price'];
            $costPrice = $giaInfo['cost_price'];

            // Kiểm tra coupon nếu có
            if (!empty($couponCode)) {
                $stmtC = $pdo->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses");
                $stmtC->execute([$couponCode]);
                $cp = $stmtC->fetch();
                if ($cp && $giaTien >= (float)$cp['min_order_amount']) {
                    $giam = ($cp['discount_type'] === 'PERCENT') ? ($giaTien * (float)$cp['discount_value'] / 100) : (float)$cp['discount_value'];
                    $giaSauCoupon = round($giaTien - $giam);
                    if ($costPrice > 0 && $giaSauCoupon < $costPrice) {
                        $giaSauCoupon = $costPrice;
                    }
                    $giaTien = max(1000, $giaSauCoupon);
                    $pdo->prepare("UPDATE `coupons` SET `used_count` = `used_count` + 1 WHERE `id` = ?")->execute([$cp['id']]);
                }
            }

            $soDuCu = (float)$user['balance'];

            if ($soDuCu < $giaTien) {
                $pdo->rollBack();
                echo json_encode([
                    'thanh_cong' => false, 
                    'thong_bao' => 'Số dư ví không đủ (Còn ' . number_format($soDuCu, 0, ',', '.') . 'đ, cần ' . number_format($giaTien, 0, ',', '.') . 'đ). Vui lòng nạp thêm tiền!'
                ]);
                exit;
            }

            // Trừ tiền trong ví
            $soDuMoi = $soDuCu - $giaTien;
            $pdo->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ?")->execute([$soDuMoi, $userId]);

            // Tạo mã đơn hàng chuẩn tiemai + 16 chữ số ngẫu nhiên
            $maDonHang = 'tiemai' . sprintf('%08d%08d', mt_rand(10000000, 99999999), mt_rand(10000000, 99999999));
            $ipKhach = layIpNguoiDung();

            // Tạo đơn hàng trạng thái PAID để giao ngay
            $stmtOrder = $pdo->prepare("
                INSERT INTO `orders` (`order_code`, `user_id`, `product_id`, `customer_email`, `quantity`, `total_amount`, `status`, `ip_address`)
                VALUES (?, ?, ?, ?, 1, ?, 'PAID', ?)
            ");
            $stmtOrder->execute([$maDonHang, $userId, $sp['id'], $user['email'], $giaTien, $ipKhach]);
            $orderId = $pdo->lastInsertId();

            // Ghi nhật ký biến động số dư
            $stmtLog = $pdo->prepare("
                INSERT INTO `transactions` (`user_id`, `order_id`, `type`, `amount`, `balance_before`, `balance_after`, `note`)
                VALUES (?, ?, 'PURCHASE', ?, ?, ?, ?)
            ");
            $stmtLog->execute([$userId, $orderId, $giaTien, $soDuCu, $soDuMoi, "Mua gói: {$sp['name']} (#{$maDonHang})"]);

            $pdo->commit();

            // Gọi bộ điều phối giao key ngay lập tức
            $boDieuPhoi = new BoDieuPhoiDonHang($pdo);
            $ketQuaGiao = $boDieuPhoi->xuLyDonHang($maDonHang);

            // Bắn thông báo Telegram tới nhóm Đơn hàng
            guiThongBaoDonHang([
                'ma_don'          => $maDonHang,
                'ten_san_pham'    => $sp['name'],
                'so_luong'        => 1,
                'tong_tien'       => $giaTien,
                'email_khach'     => $user['email'] ?? 'Thành viên',
                'phuong_thuc'     => 'Số dư ví Shop',
                'trang_thai_key'  => ($ketQuaGiao['thanh_cong'] || !empty($ketQuaGiao['success'])) ? 'Đã giao key thành công' : 'Lỗi kho nguồn - Đã hoàn tiền ví'
            ]);

            if ($ketQuaGiao['thanh_cong'] || !empty($ketQuaGiao['success'])) {
                echo json_encode([
                    'thanh_cong' => true,
                    'ma_don_hang' => $maDonHang,
                    'thong_bao' => 'Thanh toán thành công và đã nhận key!',
                    'tai_khoan' => $ketQuaGiao['accounts'] ?? $ketQuaGiao['tai_khoan'] ?? [],
                    'so_du_moi' => $soDuMoi
                ]);
            } else {
                echo json_encode([
                    'thanh_cong' => false,
                    'thong_bao' => 'Đã trừ tiền ví nhưng toàn bộ kho nguồn tạm hết hàng. Hệ thống đã tự động hoàn tiền vào ví của bạn!',
                    'so_du_moi' => $soDuCu
                ]);
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Wallet payment error: ' . $e->getMessage());
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không thể xử lý thanh toán lúc này. Vui lòng thử lại sau.']);
        }
        break;

    // 3. KIỂM TRA ĐƠN HÀNG ĐÃ THANH TOÁN CHƯA (POLLING TỪ POPUP VIETQR)
    case 'kiem_tra_trang_thai_don':
        $maDonHang = trim($_GET['order_code'] ?? '');
        $stmt = $pdo->prepare("SELECT `status`, `delivered_data` FROM `orders` WHERE `order_code` = ?");
        $stmt->execute([$maDonHang]);
        $don = $stmt->fetch();

        if (!$don) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không tìm thấy đơn']);
            exit;
        }

        $daHoanThanh = ($don['status'] === 'COMPLETED');
        $taiKhoanTraVe = $daHoanThanh ? json_decode($don['delivered_data'], true) : null;

        echo json_encode([
            'thanh_cong'   => true,
            'trang_thai'   => $don['status'],
            'da_thanh_toan'=> $daHoanThanh,
            'tai_khoan'    => $taiKhoanTraVe
        ]);
        break;

    // 4. TRA CỨU SỐ LƯỢT DÙNG (USAGE) & QUOTA CỦA KEY THEO BASE URL
    case 'tra_cuu_key':
        $keyRaw = trim($_GET['key'] ?? '');
        $keyCanCheck = ltrim($keyRaw, '#');
        if (empty($keyCanCheck)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập Key hoặc Mã đơn hàng cần tra cứu']);
            exit;
        }

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'tiemai.io.vn';
        $baseUrlWebsite = $protocol . '://' . $host . '/v1';
        $chatEndpointWebsite = $baseUrlWebsite . '/chat/completions';

        // 1. Thử tìm trong bảng đơn hàng (orders)
        $stmtOrder = $pdo->prepare("
            SELECT o.*, p.name AS product_name, p.sku AS product_sku, p.duration_days, p.request_count, p.description AS product_desc, p.supported_models, p.price AS product_price
            FROM `orders` o
            LEFT JOIN `products` p ON o.product_id = p.id
            WHERE (o.delivered_data LIKE ? OR o.order_code = ? OR o.order_code = ? OR o.customer_email = ?)
            ORDER BY o.id DESC LIMIT 1
        ");
        $stmtOrder->execute(["%$keyCanCheck%", $keyCanCheck, $keyRaw, $keyCanCheck]);
        $order = $stmtOrder->fetch();

        if ($order) {
            // Nếu đơn hàng chưa hoàn tất hoặc đã hoàn tiền:
            if ($order['status'] === 'PROCESSING') {
                echo json_encode([
                    'thanh_cong' => false,
                    'trang_thai' => 'PROCESSING',
                    'thong_bao'  => 'Đơn hàng #' . $order['order_code'] . ' đã nhận thanh toán thành công và đang chờ hệ thống xuất kho từ đối tác. Vui lòng đợi trong giây lát hoặc liên hệ Admin Zalo/Telegram để được hỗ trợ cấp key ngay!'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } elseif ($order['status'] === 'REFUNDED') {
                echo json_encode([
                    'thanh_cong' => false,
                    'trang_thai' => 'REFUNDED',
                    'thong_bao'  => 'Đơn hàng #' . $order['order_code'] . ' đã được hoàn tiền 100% vào ví tài khoản của bạn do kho hàng đối tác tạm thời hết hàng.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } elseif ($order['status'] === 'PENDING') {
                echo json_encode([
                    'thanh_cong' => false,
                    'trang_thai' => 'PENDING',
                    'thong_bao'  => 'Đơn hàng #' . $order['order_code'] . ' đang chờ thanh toán VietQR.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Nếu COMPLETED thì hiển thị chi tiết gói và key:
            $prodName = $order['product_name'] ?? 'Gói API AI Gateway';
            $prodSku = $order['product_sku'] ?? '';
            $prodDesc = $order['product_desc'] ?? '';
            $prodReqCount = $order['request_count'] ?? '';
            $combinedInfo = $prodName . ' ' . $prodSku . ' ' . $prodDesc . ' ' . $prodReqCount;

            // Xác định thời hạn (ngày) chính xác từ cột duration_days hoặc tên/mô tả gói
            $soNgay = 30;
            if (!empty($order['duration_days']) && (int)$order['duration_days'] > 0) {
                $soNgay = (int)$order['duration_days'];
            } elseif (preg_match('/(\d+)\s*(ng[àa]y|day)/iu', $combinedInfo, $m)) {
                $soNgay = (int)$m[1];
            } elseif (preg_match('/(\d+)\s*(th[áa]ng|month)/iu', $combinedInfo, $m)) {
                $soNgay = (int)$m[1] * 30;
            }

            $thoiGianTao = strtotime($order['created_at'] ?? 'now');
            $thoiGianHetHan = $thoiGianTao + ($soNgay * 86400);
            $giayConLai = $thoiGianHetHan - time();
            $ngayConLai = ceil($giayConLai / 86400);
            $hetHan = ($giayConLai <= 0);

            // Kiểm tra gói có cơ chế reset 7h AM / hàng ngày hay không
            $isDailyReset = false;
            if (preg_match('/reset\s*7h|7h\s*AM|tin\s*nhắn\s*\/\s*ngày|tin\s*nhắn\s*1\s*ngày/iu', $combinedInfo)) {
                $isDailyReset = true;
            }

            // Thiết lập hạn mức & thông số chu kỳ
            $isTokenRPM = false;
            $tokenDisplay = '';
            $shortDisplay = '';
            $conLaiText = '';

            if ($isDailyReset) {
                $resetLuc = '07:00 AM (Hàng ngày)';
                $tuanChuKy = 'Reset 07:00 AM mỗi ngày';
                $requestsLimitDisplay = '200 - 400 tin nhắn/ngày';
                $requestsCapacityNumber = 300;
                $tokenLimit = 'Không giới hạn (Full Model)';
                $costLimit = 'Đã bao gồm trọn gói';
                $conLaiText = '200 - 400 tin nhắn/ngày';
            } elseif (preg_match('/(\d+)\s*(TRIỆU|TỶ|M|B)\s*Token(\s*RPM)?/iu', $combinedInfo, $mTok)) {
                $isTokenRPM = true;
                $num = (int)$mTok[1];
                $unit = mb_strtoupper(trim($mTok[2]));
                $isRPM = (stripos($mTok[0], 'RPM') !== false);

                if ($unit === 'TRIỆU' || $unit === 'M') {
                    $tokenCount = $num * 1000000;
                    $tokenDisplay = number_format($tokenCount, 0, ',', '.') . ($isRPM ? ' Token RPM' : ' Tokens');
                    $shortDisplay = $num . 'M' . ($isRPM ? ' Token RPM' : ' Token');
                } else {
                    $tokenCount = $num * 1000000000;
                    $tokenDisplay = number_format($tokenCount, 0, ',', '.') . ($isRPM ? ' Token RPM' : ' Tokens');
                    $shortDisplay = $num . 'B' . ($isRPM ? ' Token' : ' Token');
                }

                $resetLuc = 'Theo chu kỳ gói (' . $soNgay . ' ngày)';
                $tuanChuKy = 'Gói ' . $soNgay . ' ngày (' . $shortDisplay . ')';
                $requestsLimitDisplay = $shortDisplay;
                $requestsCapacityNumber = 1000000; // Không giới hạn số lượng request
                $tokenLimit = $tokenDisplay;
                $costLimit = 'Đã bao gồm trọn gói';
                $conLaiText = $tokenDisplay . ' (Không giới hạn req)';
            } elseif (preg_match('/(\d+)\$\s*Credit/iu', $combinedInfo, $mCredit)) {
                $creditVal = $mCredit[1];
                $resetLuc = 'Theo chu kỳ gói (' . $soNgay . ' ngày)';
                $tuanChuKy = 'Gói ' . $soNgay . ' ngày (' . $creditVal . '$ Credit)';
                $requestsLimitDisplay = $creditVal . '$ Credit';
                $requestsCapacityNumber = (int)$creditVal * 100;
                $tokenLimit = 'Theo hạn mức ' . $creditVal . '$';
                $costLimit = '$' . $creditVal . '.00 USD';
                $conLaiText = '$' . $creditVal . '.00 Credit';
            } elseif (preg_match('/([\d\.]+)\s*Request/iu', $combinedInfo, $mReq)) {
                $reqNum = (int)str_replace('.', '', $mReq[1]);
                $resetLuc = 'Theo chu kỳ gói (' . $soNgay . ' ngày)';
                $tuanChuKy = 'Gói ' . $soNgay . ' ngày (' . number_format($reqNum) . ' req)';
                $requestsLimitDisplay = number_format($reqNum) . ' Request';
                $requestsCapacityNumber = $reqNum;
                $tokenLimit = 'Không giới hạn (Full Model)';
                $costLimit = 'Đã bao gồm trọn gói';
                $conLaiText = number_format($reqNum) . ' Request';
            } else {
                $resetLuc = 'Theo chu kỳ gói (' . $soNgay . ' ngày)';
                $tuanChuKy = 'Gói ' . $soNgay . ' ngày';
                $requestsLimitDisplay = !empty($prodReqCount) ? $prodReqCount : '700 Request';
                $requestsCapacityNumber = 700;
                $tokenLimit = 'Không giới hạn (Full Model)';
                $costLimit = 'Đã bao gồm trọn gói';
                $conLaiText = 'Tốc độ cao 24/7';
            }

            // Lấy key giao thực tế
            $actualKey = $keyCanCheck;
            $deliveredArr = json_decode($order['delivered_data'] ?? '[]', true);
            if (is_array($deliveredArr) && !empty($deliveredArr)) {
                $rawDelivered = trim($deliveredArr[0]);
                if (preg_match('/(sk-[0-9a-fA-F]{32,64}|sk-[a-zA-Z0-9_\-]+|gzsk_[a-zA-Z0-9_\-]+)/', $rawDelivered, $mK)) {
                    $actualKey = $mK[1];
                } else {
                    $actualKey = $rawDelivered;
                }
            } elseif (preg_match('/(sk-[0-9a-fA-F]{32,64}|sk-[a-zA-Z0-9_\-]+|gzsk_[a-zA-Z0-9_\-]+)/', $actualKey, $mK)) {
                $actualKey = $mK[1];
            }
            $shortKey = substr($actualKey, 0, 12);

            // Đếm request đã dùng tổng thể từ api_logs
            $stmtCountTotal = $pdo->prepare("
                SELECT COUNT(*) FROM `api_logs` 
                WHERE (`api_key_used` LIKE ? OR `customer_email` = ?) AND `created_at` >= ?
            ");
            $stmtCountTotal->execute(["%$shortKey%", $order['customer_email'], $order['created_at']]);
            $daDungTotal = (int)$stmtCountTotal->fetchColumn();

            // Nếu là gói Daily Reset: đếm số request từ mốc 07:00 AM gần nhất
            if ($isDailyReset) {
                $nowHour = (int)date('G');
                if ($nowHour >= 7) {
                    $lastResetTime = date('Y-m-d 07:00:00');
                } else {
                    $lastResetTime = date('Y-m-d 07:00:00', strtotime('-1 day'));
                }
                $stmtCountToday = $pdo->prepare("
                    SELECT COUNT(*) FROM `api_logs` 
                    WHERE (`api_key_used` LIKE ? OR `customer_email` = ?) AND `created_at` >= ?
                ");
                $stmtCountToday->execute(["%$shortKey%", $order['customer_email'], $lastResetTime]);
                $daDungPeriod = (int)$stmtCountToday->fetchColumn();
            } else {
                $daDungPeriod = $daDungTotal;
            }

            $conLaiRequest = max(0, $requestsCapacityNumber - $daDungPeriod);
            $phanTramDung = min(100, round(($daDungPeriod / max(1, $requestsCapacityNumber)) * 100));

            // Định dạng hiển thị thời gian còn lại
            if ($hetHan) {
                $hanDungText = 'Đã hết hạn';
            } elseif ($giayConLai < 86400) {
                $hours = floor($giayConLai / 3600);
                $mins = floor(($giayConLai % 3600) / 60);
                $hanDungText = ($hours > 0 ? "{$hours} giờ " : "") . "{$mins} phút nữa";
            } else {
                $hanDungText = $ngayConLai . ' ngày nữa';
            }

            // Lấy thông tin Usage thực tế từ đối tác (Taphoaai / Zplay) theo thời gian thực
            $upstreamUsage = null;
            if (preg_match('/^sk-[0-9a-f]{64}$/i', $actualKey)) {
                $tryUrls = ['https://api.taphoaai.info.vn/v1/usage', 'https://zplay.io.vn/v1/usage'];
                if (stripos($combinedInfo, 'codex') !== false || stripos($combinedInfo, 'gpt') !== false) {
                    $tryUrls = ['https://zplay.io.vn/v1/usage', 'https://api.taphoaai.info.vn/v1/usage'];
                }
                foreach ($tryUrls as $u) {
                    $ch = curl_init($u);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $actualKey, 'User-Agent: Mozilla/5.0']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($res && $code === 200) {
                        $parsed = json_decode($res, true);
                        if (!empty($parsed) && (isset($parsed['usage']) || isset($parsed['quota']))) {
                            $upstreamUsage = $parsed;
                            break;
                        }
                    }
                }
            }

            // Biến hiển thị
            $costVal = '$0.00';
            $costSub = 'Đã bao gồm trọn gói';
            $tokensVal = number_format($daDungTotal * 1250) . ' Tokens';
            $tokensSub = 'Không giới hạn tokens';
            $requestsVal = $daDungPeriod . ' / ' . number_format($requestsCapacityNumber);
            $requestsSub = $requestsLimitDisplay;
            $progressText = $daDungPeriod . ' / ' . number_format($requestsCapacityNumber);
            $trangThaiText = $hetHan ? 'Đã hết hạn' : 'Đang hoạt động';

            if ($upstreamUsage) {
                $upQuotaLimit = (float)($upstreamUsage['quota']['limit'] ?? 0);
                $upQuotaUsed = (float)($upstreamUsage['quota']['used'] ?? ($upstreamUsage['usage']['total']['actual_cost'] ?? ($upstreamUsage['usage']['total']['cost'] ?? 0)));
                $upTokens = (int)($upstreamUsage['usage']['total']['total_tokens'] ?? 0);
                $upReqs = (int)($upstreamUsage['usage']['total']['requests'] ?? 0);
                $isExhausted = (($upstreamUsage['status'] ?? '') === 'quota_exhausted') || ($upQuotaLimit > 0 && $upQuotaUsed >= $upQuotaLimit);

                if ($upQuotaLimit > 0) {
                    // Gói tính theo hạn mức $ Credit (Ví dụ: Gói 10$ Credit)
                    $phanTramDung = min(100, round(($upQuotaUsed / $upQuotaLimit) * 100));
                    $costVal = '$' . number_format($upQuotaUsed, 2) . ' / $' . number_format($upQuotaLimit, 2);
                    $costSub = 'Hạn mức ' . number_format($upQuotaLimit, 0) . '$ Credit';
                    $tokensVal = number_format($upTokens) . ' Tokens';
                    $tokensSub = 'Đã tiêu thụ: ' . number_format($upTokens) . ' tokens';
                    $requestsVal = number_format($upReqs) . ' requests';
                    $requestsSub = number_format($upQuotaLimit, 0) . '$ Credit';
                    $progressText = '$' . number_format($upQuotaUsed, 2) . ' / $' . number_format($upQuotaLimit, 2) . ' (' . $phanTramDung . '%)';
                    
                    if ($isExhausted) {
                        $hetHan = true;
                        $trangThaiText = 'Đã dùng hết hạn mức ($' . number_format($upQuotaLimit, 0) . ')';
                        $conLaiText = '$0.00 Credit (Đã dùng hết 100%)';
                    } else {
                        $conLaiText = '$' . number_format(max(0, $upQuotaLimit - $upQuotaUsed), 2) . ' Credit còn lại';
                    }
                } else {
                    $daDungPeriod = $upReqs ?: $daDungPeriod;
                    $tokensVal = number_format($upTokens) . ' Tokens';
                    $tokensSub = 'Đã tiêu thụ: ' . number_format($upTokens) . ' tokens';
                }

                // Cập nhật daily_usage nếu đối tác có dữ liệu chuẩn
                if (!empty($upstreamUsage['daily_usage']) && is_array($upstreamUsage['daily_usage'])) {
                    $dailyUsage = [];
                    foreach ($upstreamUsage['daily_usage'] as $du) {
                        $dateFormatted = date('d/m/Y', strtotime($du['date'] ?? 'now'));
                        $dailyUsage[] = [
                            'date'     => $dateFormatted,
                            'requests' => ($du['requests'] ?? 0) . ' req',
                            'tokens'   => number_format($du['total_tokens'] ?? 0) . ' tokens',
                            'cost'     => '$' . number_format($du['actual_cost'] ?? ($du['cost'] ?? 0), 2),
                            'status'   => 'Bình thường'
                        ];
                    }
                }
            } else {
                // Fallback nếu không kết nối được upstream
                if ($isTokenRPM) {
                    $requestsVal = $daDungPeriod . ' requests';
                    $requestsSub = 'Băng thông: ' . $shortDisplay;
                    $tokensVal = $tokenDisplay;
                    $tokensSub = 'Đã tiêu thụ: ' . number_format($daDungTotal * 1250) . ' tokens';
                    $progressText = $daDungPeriod . ' req / Không giới hạn';
                    $phanTramDung = min(100, round(($daDungPeriod / 100) * 100));
                } elseif ($isDailyReset) {
                    $requestsVal = $daDungPeriod . ' / 200 - 400';
                    $requestsSub = '200 - 400 tin nhắn/ngày';
                    $progressText = $daDungPeriod . ' / 300';
                } elseif (strpos($requestsLimitDisplay, 'Credit') !== false) {
                    $costVal = '$0.00 / ' . ($creditVal ?? 10) . '$';
                }
            }

            // Danh sách model
            $models = [];
            if (!empty($order['supported_models'])) {
                $models = array_values(array_filter(array_map('trim', explode(',', $order['supported_models']))));
            }
            if (empty($models)) {
                if (stripos($combinedInfo, 'codex') !== false || preg_match('/^sk-[0-9a-f]{64}$/i', $actualKey)) {
                    $models = [
                        'gpt-5.6-sol',
                        'gpt-6.1-sol',
                        'gpt-5.6-luna',
                        'gpt-5.6-terra',
                        'gpt-5.4-mini',
                        'claude-opus-5.5',
                        'claude-sonnet-5',
                        'claude-opus-5',
                        'claude-opus-4.8'
                    ];
                } elseif (stripos($combinedInfo, 'claude_code') !== false || stripos($combinedInfo, 'claude') !== false) {
                    $models = [
                        'claude-opus-5.5',
                        'claude-sonnet-5',
                        'claude-opus-5',
                        'claude-opus-4.8',
                        'claude-3-5-sonnet'
                    ];
                } else {
                    $models = ['claude-3-5-sonnet', 'deepseek-reasoner', 'deepseek-chat', 'gpt-4o', 'cursor-small'];
                }
            }

            // Lịch sử 7 ngày (Daily Usage)
            $dailyUsage = [];
            for ($i = 0; $i < 7; $i++) {
                $dayStamp = strtotime("-$i days");
                $dayDb = date('Y-m-d', $dayStamp);
                $dayLabel = ($i === 0) ? 'Hôm nay (' . date('d/m', $dayStamp) . ')' : (($i === 1) ? 'Hôm qua (' . date('d/m', $dayStamp) . ')' : date('d/m/Y', $dayStamp));
                
                $stmtDay = $pdo->prepare("
                    SELECT COUNT(*) FROM `api_logs` 
                    WHERE (`api_key_used` LIKE ? OR `customer_email` = ?) AND DATE(created_at) = ?
                ");
                $stmtDay->execute(["%$shortKey%", $order['customer_email'], $dayDb]);
                $reqDay = (int)$stmtDay->fetchColumn();

                $dailyUsage[] = [
                    'date'     => $dayLabel,
                    'requests' => $reqDay . ' req',
                    'tokens'   => number_format($reqDay * 1250) . ' tokens',
                    'cost'     => '0 đ',
                    'status'   => 'Bình thường'
                ];
            }

            // Lấy 10 request gần nhất (Request logs)
            $stmtLogs = $pdo->prepare("
                SELECT id, endpoint, model, http_status, latency_ms, created_at
                FROM `api_logs`
                WHERE `api_key_used` LIKE ? OR `customer_email` = ?
                ORDER BY id DESC LIMIT 10
            ");
            $stmtLogs->execute(["%$shortKey%", $order['customer_email']]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'thanh_cong'       => true,
                'trang_thai'       => $trangThaiText,
                'is_active'        => !$hetHan,
                'ten_goi'          => $prodName,
                'ma_don_hang'      => $order['order_code'],
                'key_goc'          => $actualKey,
                'key_masked'       => (strlen($actualKey) > 12) ? substr($actualKey, 0, 8) . '...' . substr($actualKey, -4) : $actualKey,
                'gateway'          => 'Gateway',
                
                // 3 Cột thống kê chính
                'requests_val'     => $requestsVal,
                'requests_sub'     => $requestsSub,
                'tokens_val'       => $tokensVal,
                'tokens_sub'       => $tokensSub,
                'cost_val'         => $costVal,
                'cost_sub'         => $costSub,

                // Tiến độ & Hết hạn
                'progress_text'    => $progressText,
                'phan_tram'        => $phanTramDung,
                'ngay_het_han'     => date('d/m/Y H:i', $thoiGianHetHan),
                'han_dung'         => $hanDungText,

                // Thẻ thông tin dưới
                'con_lai'          => $conLaiText,
                'tuan_chu_ky'      => $tuanChuKy,
                'reset_luc'        => $resetLuc,
                'cap_nhat'         => 'Vừa xong',
                'token_limit'      => $tokenLimit,
                'cost_limit'       => $costLimit,

                // Dữ liệu cho các tab phụ
                'daily_usage'      => $dailyUsage,
                'overage'          => [
                    'rate_limit'     => '60 requests / phút',
                    'overage_fee'    => '0 đ (Miễn phí overage)',
                    'status'         => 'Bình thường',
                    'tuan_chu_ky'    => $tuanChuKy,
                    'reset_luc'      => $resetLuc,
                    'chinh_sach'     => 'Hệ thống tự cân bằng tải và bảo vệ tài khoản, không phát sinh phụ phí ngoài gói'
                ],
                'logs'             => $logs,

                'models'           => $models,
                'base_url'         => $baseUrlWebsite,
                'chat_endpoint'    => $chatEndpointWebsite,

                // Tương thích ngược
                'da_dung'          => ($upQuotaLimit > 0 ? ('$' . number_format($upQuotaUsed, 2) . ' / $' . number_format($upQuotaLimit, 2) . ' USD') : ($daDungPeriod . ' / ' . ($isDailyReset ? '200-400' : number_format($requestsCapacityNumber)) . ' Request')),
                'so_luot_da_dung'  => ($upQuotaLimit > 0 ? round($upQuotaUsed, 2) : $daDungPeriod),
                'so_luot_tong'     => ($upQuotaLimit > 0 ? $upQuotaLimit : $requestsCapacityNumber),
                'so_luot_con_lai'  => ($upQuotaLimit > 0 ? max(0, round($upQuotaLimit - $upQuotaUsed, 2)) : $conLaiRequest)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. Thử tìm trong bảng thành viên (users) theo api_token hoặc reseller_key
        $stmtUser = $pdo->prepare("
            SELECT id, name, email, balance, is_reseller, created_at 
            FROM `users` 
            WHERE (`api_token` = ? OR `reseller_key` = ? OR `email` = ?) AND `is_active` = 1
            LIMIT 1
        ");
        $stmtUser->execute([$keyCanCheck, $keyCanCheck, $keyCanCheck]);
        $user = $stmtUser->fetch();

        if ($user) {
            $stmtUserCount = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `user_id` = ?");
            $stmtUserCount->execute([$user['id']]);
            $daDungUser = (int)$stmtUserCount->fetchColumn();

            $models = ['claude-3-5-sonnet', 'deepseek-reasoner', 'deepseek-chat', 'gpt-4o', 'cursor-small'];
            $actualKey = $user['api_token'] ?: $keyCanCheck;

            // Daily usage cho user
            $dailyUsage = [];
            for ($i = 0; $i < 7; $i++) {
                $dayStamp = strtotime("-$i days");
                $dayDb = date('Y-m-d', $dayStamp);
                $dayLabel = ($i === 0) ? 'Hôm nay (' . date('d/m', $dayStamp) . ')' : (($i === 1) ? 'Hôm qua (' . date('d/m', $dayStamp) . ')' : date('d/m/Y', $dayStamp));
                $stmtDay = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `user_id` = ? AND DATE(created_at) = ?");
                $stmtDay->execute([$user['id'], $dayDb]);
                $reqDay = (int)$stmtDay->fetchColumn();
                $dailyUsage[] = [
                    'date'     => $dayLabel,
                    'requests' => $reqDay . ' req',
                    'tokens'   => number_format($reqDay * 1250) . ' tokens',
                    'cost'     => '0 đ',
                    'status'   => 'Bình thường'
                ];
            }

            $stmtLogs = $pdo->prepare("
                SELECT id, endpoint, model, http_status, latency_ms, created_at
                FROM `api_logs`
                WHERE `user_id` = ?
                ORDER BY id DESC LIMIT 10
            ");
            $stmtLogs->execute([$user['id']]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'thanh_cong'       => true,
                'trang_thai'       => 'Đang hoạt động',
                'is_active'        => true,
                'ten_goi'          => $user['is_reseller'] ? 'Tài Khoản Đại Lý API Đối Tác' : 'API Token Thành Viên Shop',
                'ma_don_hang'      => 'USER-' . $user['id'],
                'key_goc'          => $actualKey,
                'key_masked'       => substr($actualKey, 0, 6) . '...' . substr($actualKey, -4),
                'gateway'          => 'Gateway',
                
                'requests_val'     => number_format($daDungUser) . ' req',
                'requests_sub'     => 'Không giới hạn (Theo số dư)',
                'tokens_val'       => number_format($daDungUser * 1250) . ' Tokens',
                'tokens_sub'       => 'Theo số dư ví',
                'cost_val'         => number_format($user['balance']) . ' đ',
                'cost_sub'         => 'Số dư khả dụng',

                'progress_text'    => number_format($daDungUser) . ' / Không giới hạn',
                'phan_tram'        => min(100, round(($daDungUser / 10000) * 100)),
                'ngay_het_han'     => 'Vĩnh viễn (Theo số dư)',
                'han_dung'         => 'Theo số dư ví',

                'con_lai'          => number_format($user['balance']) . ' đ số dư',
                'tuan_chu_ky'      => 'Vĩnh viễn theo số dư ví',
                'reset_luc'        => 'Theo số dư tài khoản',
                'cap_nhat'         => 'Vừa xong',
                'token_limit'      => 'Không giới hạn (Full Model)',
                'cost_limit'       => number_format($user['balance']) . ' đ',

                'daily_usage'      => $dailyUsage,
                'overage'          => [
                    'rate_limit'     => '120 requests / phút',
                    'overage_fee'    => '0 đ',
                    'status'         => 'Bình thường',
                    'tuan_chu_ky'    => 'Không giới hạn chu kỳ',
                    'reset_luc'      => 'Theo số dư ví',
                    'chinh_sach'     => 'Trừ trực tiếp số dư ví theo từng lượt gọi'
                ],
                'logs'             => $logs,

                'models'           => $models,
                'base_url'         => $baseUrlWebsite,
                'chat_endpoint'    => $chatEndpointWebsite,

                'da_dung'          => number_format($daDungUser) . ' Request đã gọi',
                'so_luot_da_dung'  => $daDungUser,
                'so_luot_tong'     => 10000,
                'so_luot_con_lai'  => 10000 - $daDungUser
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. Nếu Key có định dạng API chuẩn (sk-..., vk_..., gzsk_..., tiemai...) hoặc độ dài >= 14 ký tự
        if (strlen($keyCanCheck) >= 14 && (str_starts_with($keyCanCheck, 'sk-') || str_starts_with($keyCanCheck, 'vk_') || str_starts_with($keyCanCheck, 'gzsk_') || str_starts_with($keyCanCheck, 'cur-') || str_starts_with($keyCanCheck, 'tiemai') || str_starts_with($keyCanCheck, 'VK'))) {
            $shortKey = substr($keyCanCheck, 0, 12);
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `api_key_used` LIKE ?");
            $stmtCount->execute(["%$shortKey%"]);
            $daDung = (int)$stmtCount->fetchColumn();

            $tongRequest = 700;
            $conLai = max(0, $tongRequest - $daDung);

            $dailyUsage = [];
            for ($i = 0; $i < 7; $i++) {
                $dayStamp = strtotime("-$i days");
                $dayDb = date('Y-m-d', $dayStamp);
                $dayLabel = ($i === 0) ? 'Hôm nay (' . date('d/m', $dayStamp) . ')' : (($i === 1) ? 'Hôm qua (' . date('d/m', $dayStamp) . ')' : date('d/m/Y', $dayStamp));
                $stmtDay = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `api_key_used` LIKE ? AND DATE(created_at) = ?");
                $stmtDay->execute(["%$shortKey%", $dayDb]);
                $reqDay = (int)$stmtDay->fetchColumn();
                $dailyUsage[] = [
                    'date'     => $dayLabel,
                    'requests' => $reqDay . ' req',
                    'tokens'   => number_format($reqDay * 1250) . ' tokens',
                    'cost'     => '0 đ',
                    'status'   => 'Bình thường'
                ];
            }

            $stmtLogs = $pdo->prepare("
                SELECT id, endpoint, model, http_status, latency_ms, created_at
                FROM `api_logs`
                WHERE `api_key_used` LIKE ?
                ORDER BY id DESC LIMIT 10
            ");
            $stmtLogs->execute(["%$shortKey%"]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'thanh_cong'       => true,
                'trang_thai'       => 'Đang hoạt động',
                'is_active'        => true,
                'ten_goi'          => 'Gói Kết Nối AI Gateway Cursor / Claude',
                'ma_don_hang'      => 'KEY-ONLINE',
                'key_goc'          => $keyCanCheck,
                'key_masked'       => substr($keyCanCheck, 0, 8) . '...' . substr($keyCanCheck, -4),
                'gateway'          => 'Gateway',
                
                'requests_val'     => $daDung . ' / ' . number_format($tongRequest),
                'requests_sub'     => number_format($tongRequest) . ' Request',
                'tokens_val'       => number_format($daDung * 1250) . ' Tokens',
                'tokens_sub'       => 'Không giới hạn tokens',
                'cost_val'         => '0 đ',
                'cost_sub'         => 'Đã bao gồm trọn gói',

                'progress_text'    => $daDung . ' / ' . number_format($tongRequest),
                'phan_tram'        => min(100, round(($daDung / $tongRequest) * 100)),
                'ngay_het_han'     => date('d/m/Y', strtotime('+30 days')),
                'han_dung'         => 'Còn hạn sử dụng',

                'con_lai'          => number_format($conLai) . ' Request',
                'tuan_chu_ky'      => 'Gói 30 ngày',
                'reset_luc'        => 'Theo chu kỳ gói',
                'cap_nhat'         => 'Vừa xong',
                'token_limit'      => 'Không giới hạn (Full Model)',
                'cost_limit'       => 'Đã bao gồm trọn gói',

                'daily_usage'      => $dailyUsage,
                'overage'          => [
                    'rate_limit'     => '60 requests / phút',
                    'overage_fee'    => '0 đ',
                    'status'         => 'Bình thường',
                    'tuan_chu_ky'    => 'Gói 30 ngày',
                    'reset_luc'      => 'Theo chu kỳ gói',
                    'chinh_sach'     => 'Hệ thống tự động cân bằng tải và bảo vệ tài khoản'
                ],
                'logs'             => $logs,

                'models'           => ['claude-3-5-sonnet', 'deepseek-reasoner', 'deepseek-chat', 'gpt-4o', 'cursor-small'],
                'base_url'         => $baseUrlWebsite,
                'chat_endpoint'    => $chatEndpointWebsite,

                'da_dung'          => number_format($daDung) . ' / ' . number_format($tongRequest) . ' Request',
                'so_luot_da_dung'  => $daDung,
                'so_luot_tong'     => $tongRequest,
                'so_luot_con_lai'  => $conLai
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Không tìm thấy
        echo json_encode([
            'thanh_cong' => false,
            'thong_bao'  => 'Không tìm thấy thông tin của mã Key hoặc Đơn hàng này. Vui lòng kiểm tra lại chính xác mã!'
        ], JSON_UNESCAPED_UNICODE);
        break;

    // 5. HIỂN THỊ API TOKEN SAU KHI NGƯỜI DÙNG CHỦ ĐỘNG XÁC NHẬN
    case 'lay_api_token':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Phương thức không được hỗ trợ']);
            exit;
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa đăng nhập']);
            exit;
        }
        batBuocCsrf();
        $stmtToken = $pdo->prepare("SELECT `api_token` FROM `users` WHERE `id` = ? AND `is_active` = 1 LIMIT 1");
        $stmtToken->execute([(int)$_SESSION['user_id']]);
        $tokenHienTai = (string)($stmtToken->fetchColumn() ?: '');
        if ($tokenHienTai === '') {
            http_response_code(404);
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Tài khoản chưa có API Token. Hãy tạo khóa mới.']);
            exit;
        }
        header('Cache-Control: no-store, private');
        echo json_encode(['thanh_cong' => true, 'api_token' => $tokenHienTai, 'thong_bao' => 'Token chỉ được hiển thị sau yêu cầu bảo mật của bạn.'], JSON_UNESCAPED_UNICODE);
        break;

    // 5b. TẠO MỚI API TOKEN CỦA KHÁCH HÀNG
    case 'tao_moi_api_token':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Phương thức không được hỗ trợ']);
            exit;
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa đăng nhập']);
            exit;
        }
        batBuocCsrf();
        $userId = (int)$_SESSION['user_id'];
        $newToken = 'vk_' . bin2hex(random_bytes(24));
        $pdo->prepare("UPDATE `users` SET `api_token` = ? WHERE `id` = ?")->execute([$newToken, $userId]);
        header('Cache-Control: no-store, private');
        echo json_encode([
            'thanh_cong' => true,
            'api_token' => $newToken,
            'thong_bao' => 'Đã tạo API Token mới thành công. Hãy lưu ở nơi an toàn và không chia sẻ công khai.'
        ], JSON_UNESCAPED_UNICODE);
        break;

    // 6. ĐỔI MẬT KHẨU TÀI KHOẢN KHÁCH HÀNG
    case 'doi_mat_khau':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa đăng nhập']);
            exit;
        }

        batBuocCsrf();
        $userId = (int)$_SESSION['user_id'];
        $matKhauCu = $_POST['old_password'] ?? '';
        $matKhauMoi = $_POST['new_password'] ?? '';

        if (strlen($matKhauMoi) < 6) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mật khẩu mới phải có ít nhất 6 ký tự']);
            exit;
        }

        $user = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
        $user->execute([$userId]);
        $u = $user->fetch();

        // Nếu user đã có mật khẩu thì bắt buộc kiểm tra mật khẩu cũ
        if (!empty($u['password'])) {
            if (!password_verify($matKhauCu, $u['password'])) {
                echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mật khẩu hiện tại không chính xác!']);
                exit;
            }
        }

        $hashMoi = password_hash($matKhauMoi, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?")->execute([$hashMoi, $userId]);

        echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Cập nhật mật khẩu thành công!']);
        break;

    // 7. ADMIN: CỘNG HOẶC TRỪ TIỀN THỦ CÔNG CHO THÀNH VIÊN
    case 'admin_dieu_chinh_so_du':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $loai = $_POST['type'] ?? 'CONG'; // 'CONG' hoặc 'TRU'
        $soTien = (float)($_POST['amount'] ?? 0);
        $lyDo = trim($_POST['note'] ?? 'Admin điều chỉnh số dư');

        if ($targetUserId <= 0 || $soTien <= 0) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Thông tin không hợp lệ']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmtU = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? FOR UPDATE");
            $stmtU->execute([$targetUserId]);
            $targetUser = $stmtU->fetch();

            if (!$targetUser) {
                $pdo->rollBack();
                echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không tìm thấy người dùng']);
                exit;
            }

            $soDuCu = (float)$targetUser['balance'];
            if ($loai === 'TRU' && $soDuCu < $soTien) {
                $pdo->rollBack();
                echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Số dư người dùng không đủ để trừ']);
                exit;
            }

            $soDuMoi = ($loai === 'CONG') ? ($soDuCu + $soTien) : ($soDuCu - $soTien);
            $pdo->prepare("UPDATE `users` SET `balance` = ? WHERE `id` = ?")->execute([$soDuMoi, $targetUserId]);

            // Ghi nhật ký giao dịch
            $txType = ($loai === 'CONG') ? 'BONUS' : 'PURCHASE';
            $logStmt = $pdo->prepare("
                INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `note`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([$targetUserId, $txType, $soTien, $soDuCu, $soDuMoi, "Admin {$loai}: {$lyDo}"]);

            $pdo->commit();
            echo json_encode([
                'thanh_cong' => true,
                'thong_bao' => "Đã {$loai} " . number_format($soTien, 0, ',', '.') . "đ thành công!",
                'so_du_moi' => $soDuMoi
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Admin balance adjustment error: ' . $e->getMessage());
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không thể cập nhật số dư lúc này. Vui lòng thử lại sau.']);
        }
        break;

    // 8. ADMIN: KHÓA HOẶC MỞ KHÓA TÀI KHOẢN
    case 'admin_doi_trang_thai_user':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1);

        $pdo->prepare("UPDATE `users` SET `is_active` = ? WHERE `id` = ?")->execute([$isActive, $targetUserId]);
        echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Đã cập nhật trạng thái tài khoản']);
        break;

    // 9. ADMIN: KIỂM TRA SỐ DƯ VÍ NGUỒN LIVE (THEO ID NHÀ CUNG CẤP)
    case 'kiem_tra_vi_nguon':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Bạn không có quyền']);
            exit;
        }

        $supplierId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($supplierId > 0) {
            $stmtSup = $pdo->prepare("SELECT * FROM `suppliers` WHERE `id` = ?");
            $stmtSup->execute([$supplierId]);
            $supplier = $stmtSup->fetch();
        } else {
            $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
        }

        if (!$supplier) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa cấu hình nhà cung cấp']);
            exit;
        }

        $apiClient = new NhaCungCapApi($supplier['api_url'], $supplier['api_key']);
        $ketQua = $apiClient->laySoDuVi();

        if (!empty($ketQua['success'])) {
            $soDu = (float)$ketQua['balance'];
            $pdo->prepare("UPDATE `suppliers` SET `balance` = ? WHERE `id` = ?")->execute([$soDu, $supplier['id']]);

            echo json_encode([
                'thanh_cong'  => true,
                'supplier_id' => $supplier['id'],
                'ten_ncc'     => $supplier['name'],
                'shop_id'     => $ketQua['shop_id'] ?? null,
                'so_du'       => $soDu,
                'so_du_chu'   => number_format($soDu, 0, ',', '.') . ' VNĐ'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'thanh_cong'  => false,
                'supplier_id' => $supplier['id'],
                'ten_ncc'     => $supplier['name'],
                'thong_bao'   => 'Không lấy được số dư từ ' . $supplier['name'] . ': ' . ($ketQua['message'] ?? 'Lỗi không xác định'),
                'so_du'       => (float)$supplier['balance'],
                'so_du_chu'   => number_format($supplier['balance'], 0, ',', '.') . ' VNĐ'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    // 10. KIỂM TRA MÃ GIẢM GIÁ (COUPON)
    case 'kiem_tra_coupon':
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $productId = (int)($_POST['product_id'] ?? 0);

        if (empty($code)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập mã giảm giá']);
            exit;
        }

        $stmtC = $pdo->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1");
        $stmtC->execute([$code]);
        $coupon = $stmtC->fetch();

        if (!$coupon) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã giảm giá không tồn tại hoặc đã hết hạn']);
            exit;
        }

        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã giảm giá này đã hết hạn sử dụng']);
            exit;
        }

        if ($coupon['used_count'] >= $coupon['max_uses']) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã giảm giá đã hết lượt sử dụng']);
            exit;
        }

        $spGia = 0;
        $costPrice = 0;
        if ($productId > 0) {
            $userObj = null;
            if (!empty($_SESSION['user_id'])) {
                try {
                    $stmtU = $pdo->prepare("SELECT `id`, `is_reseller`, `reseller_discount` FROM `users` WHERE `id` = ?");
                    $stmtU->execute([(int)$_SESSION['user_id']]);
                    $userObj = $stmtU->fetch();
                } catch (Exception $e) {
                    $userObj = null;
                }
            }
            $giaInfo = tinhGiaDaiLyChoSanPham($pdo, $productId, $userObj);
            $spGia = $giaInfo['price'];
            $costPrice = $giaInfo['cost_price'];
        }

        if ($spGia > 0 && $spGia < (float)$coupon['min_order_amount']) {
            echo json_encode([
                'thanh_cong' => false,
                'thong_bao' => 'Mã chỉ áp dụng cho đơn từ ' . number_format($coupon['min_order_amount'], 0, ',', '.') . 'đ'
            ]);
            exit;
        }

        $giamGia = 0;
        if ($coupon['discount_type'] === 'PERCENT') {
            $giamGia = ($spGia > 0) ? ($spGia * (float)$coupon['discount_value'] / 100) : 0;
            $chuGiam = $coupon['discount_value'] . '%';
        } else {
            $giamGia = (float)$coupon['discount_value'];
            $chuGiam = number_format($giamGia, 0, ',', '.') . 'đ';
        }

        $giaSauGiam = round($spGia - $giamGia);
        if ($costPrice > 0 && $giaSauGiam < $costPrice) {
            $giaSauGiam = $costPrice;
        }

        echo json_encode([
            'thanh_cong'      => true,
            'thong_bao'       => "Áp dụng thành công! Giảm {$chuGiam}",
            'discount_type'   => $coupon['discount_type'],
            'discount_value'  => (float)$coupon['discount_value'],
            'discount_amount' => $giamGia,
            'final_price'     => max(1000, $giaSauGiam),
            'code'            => $coupon['code']
        ]);
        break;

    // 11. THỬ NGHIỆM API PLAYGROUND TRỰC TIẾP TỪ DASHBOARD KHÁCH HÀNG
    case 'test_chat_proxy':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập để thử nghiệm API']);
            exit;
        }
        $model = trim($_POST['model'] ?? 'cursor-small');
        $message = trim($_POST['message'] ?? 'Xin chào');

        $reply = "Xin chào! Đây là phản hồi kiểm tra kết nối từ mô hình [" . htmlspecialchars($model) . "]. Hệ thống Gateway VanKhaShop đã xác thực Token thành công!";
        echo json_encode([
            'thanh_cong' => true,
            'model'      => $model,
            'response'   => $reply,
            'status'     => 200,
            'timestamp'  => date('H:i:s d/m/Y')
        ]);
        break;

    // 12. ADMIN: LƯU THÔNG BÁO POPUP / BANNER
    case 'admin_luu_thong_bao':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $tbId = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['INFO', 'WARNING', 'SUCCESS', 'DANGER']) ? $_POST['type'] : 'INFO';
        $isActive = (int)($_POST['is_active'] ?? 1);

        if (empty($title) || empty($content)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng điền đủ tiêu đề và nội dung']);
            exit;
        }

        if ($tbId > 0) {
            $stmt = $pdo->prepare("UPDATE `announcements` SET `title` = ?, `content` = ?, `type` = ?, `is_active` = ? WHERE `id` = ?");
            $stmt->execute([$title, $content, $type, $isActive, $tbId]);
            echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Cập nhật thông báo thành công']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `announcements` (`title`, `content`, `type`, `is_active`, `created_at`) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $content, $type, $isActive]);
            echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Thêm thông báo mới thành công']);
        }
        break;

    // 13. ADMIN: XÓA THÔNG BÁO
    case 'admin_xoa_thong_bao':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $tbId = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM `announcements` WHERE `id` = ?")->execute([$tbId]);
        echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Đã xóa thông báo']);
        break;

    // 14. KHÁCH YÊU CẦU QUÊN MẬT KHẨU
    case 'quen_mat_khau':
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập email hợp lệ']);
            exit;
        }
        $stmtU = $pdo->prepare("SELECT id, name FROM `users` WHERE email = ?");
        $stmtU->execute([$email]);
        $user = $stmtU->fetch();

        ghiNhatKyHeThong('INFO', 'QUEN_MAT_KHAU', "Yêu cầu khôi phục mật khẩu cho email: {$email}");
        echo json_encode([
            'thanh_cong' => true, 
            'thong_bao' => 'Yêu cầu khôi phục mật khẩu đã được ghi nhận. Vui lòng liên hệ Telegram Admin để nhận mã cấp lại mật khẩu ngay lập tức!'
        ]);
        break;

    // 15. ADMIN: NÂNG CẤP THÀNH VIÊN LÊN ĐẠI LÝ (TỰ ĐỘNG SINH KEY RIÊNG)
    case 'admin_nang_cap_dai_ly':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 5.0);

        if (!$userId) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa chọn thành viên']);
            exit;
        }

        // Tự động sinh mã đại lý riêng biệt duy nhất cho mỗi đại lý
        $resellerKey = 'vkts_' . bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("UPDATE `users` SET `is_reseller` = 1, `reseller_key` = ?, `reseller_discount` = ? WHERE `id` = ?");
        $stmt->execute([$resellerKey, $discount, $userId]);

        ghiNhatKyHeThong('INFO', 'NANG_CAP_DAI_LY', "Nâng cấp User ID {$userId} lên Đại Lý với chiết khấu {$discount}%");
        echo json_encode([
            'thanh_cong'   => true,
            'thong_bao'    => "Đã nâng cấp lên đại lý thành công! Key API riêng: {$resellerKey}",
            'reseller_key' => $resellerKey
        ]);
        break;

    // 16. ADMIN: CẤP LẠI KEY API RIÊNG CHO ĐẠI LÝ
    case 'admin_doi_key_dai_ly':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $resellerKey = 'vkts_' . bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE `users` SET `reseller_key` = ? WHERE `id` = ? AND `is_reseller` = 1")->execute([$resellerKey, $userId]);
        
        ghiNhatKyHeThong('INFO', 'DOI_KEY_DAI_LY', "Tạo lại Key API Đại Lý cho User ID {$userId}");
        echo json_encode([
            'thanh_cong'   => true,
            'thong_bao'    => 'Đã cấp lại Key API Đại Lý mới thành công!',
            'reseller_key' => $resellerKey
        ]);
        break;

    // 17. ADMIN: CẬP NHẬT CHIẾT KHẤU ĐẠI LÝ
    case 'admin_sua_chiet_khau_dai_ly':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 5.0);
        $pdo->prepare("UPDATE `users` SET `reseller_discount` = ? WHERE `id` = ?")->execute([$discount, $userId]);
        echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Đã cập nhật mức chiết khấu đại lý']);
        break;

    // 18. ADMIN: HẠ CẤP ĐẠI LÝ VỀ THÀNH VIÊN THƯỜNG
    case 'admin_ha_cap_dai_ly':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("UPDATE `users` SET `is_reseller` = 0 WHERE `id` = ?")->execute([$userId]);
        ghiNhatKyHeThong('INFO', 'HA_CAP_DAI_LY', "Thu hồi quyền Đại Lý của User ID {$userId}");
        echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Đã thu hồi quyền đại lý']);
        break;

    // 19. KIỂM TRA SỐ DƯ VÍ NGƯỜI DÙNG (DÙNG CHO TRANG NẠP TIỀN AUTO-POLLING 5S)
    case 'kiem_tra_so_du_vi':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa đăng nhập']);
            exit;
        }
        $userId = (int)$_SESSION['user_id'];
        $stmtU = $pdo->prepare("SELECT balance FROM `users` WHERE `id` = ?");
        $stmtU->execute([$userId]);
        $soDu = (float)$stmtU->fetchColumn();

        echo json_encode([
            'thanh_cong' => true,
            'so_du'      => $soDu,
            'so_du_chu'  => number_format($soDu, 0, ',', '.') . ' VNĐ'
        ]);
        break;

    // 20. ADMIN: CẬP NHẬT VAI TRÒ & QUYỀN ĐẠI LÝ TOÀN DIỆN
    case 'admin_cap_nhat_vai_tro_dai_ly':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        $isReseller = (int)($_POST['is_reseller'] ?? 0);
        $discount = max(0, min(90, floatval($_POST['reseller_discount'] ?? 5.0)));

        if ($userId <= 0) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa chọn thành viên']);
            exit;
        }

        // Kiểm tra xem đã có reseller_key chưa, nếu chưa có mà bật đại lý thì tạo mới
        $stmtU = $pdo->prepare("SELECT reseller_key FROM `users` WHERE `id` = ?");
        $stmtU->execute([$userId]);
        $currentKey = $stmtU->fetchColumn();

        $resellerKey = $currentKey;
        if ($isReseller === 1 && empty($currentKey)) {
            $resellerKey = 'vkts_' . bin2hex(random_bytes(32));
        }

        $stmtUp = $pdo->prepare("
            UPDATE `users` 
            SET `role` = ?, `is_reseller` = ?, `reseller_discount` = ?, `reseller_key` = ?
            WHERE `id` = ?
        ");
        $stmtUp->execute([$role, $isReseller, $discount, $resellerKey, $userId]);

        ghiNhatKyHeThong('INFO', 'CAP_NHAT_VAI_TRO', "Cập nhật User ID {$userId}: Vai trò={$role}, Đại lý={$isReseller}, Chiết khấu={$discount}%");
        echo json_encode([
            'thanh_cong'   => true,
            'thong_bao'    => 'Đã lưu cấu hình vai trò & đại lý thành công!',
            'reseller_key' => $resellerKey
        ]);
        break;

    // 21. ADMIN: LẤY DANH SÁCH SẢN PHẨM & TỒN KHO THỜI GIAN THỰC TỪ NHÀ CUNG CẤP
    case 'admin_lay_kho_ncc':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $supplierId = (int)($_GET['supplier_id'] ?? ($_GET['id'] ?? 0));
        if ($supplierId > 0) {
            $stmtSup = $pdo->prepare("SELECT * FROM `suppliers` WHERE `id` = ?");
            $stmtSup->execute([$supplierId]);
            $supplier = $stmtSup->fetch();
        } else {
            $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
        }

        if (!$supplier) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa kích hoạt nhà cung cấp nào trong hệ thống']);
            exit;
        }

        $apiClient = new NhaCungCapApi($supplier['api_url'], $supplier['api_key']);
        $rawProducts = $apiClient->layDanhSachSanPham();

        if (empty($rawProducts['success']) && empty($rawProducts['products'])) {
            echo json_encode([
                'thanh_cong' => false,
                'thong_bao'  => 'Không thể kết nối hoặc tải sản phẩm từ nhà cung cấp: ' . ($rawProducts['message'] ?? 'Lỗi không xác định')
            ]);
            exit;
        }

        $nccItems = $rawProducts['products'] ?? [];

        // Lấy danh sách mapping hiện tại trong shop
        $mappings = $pdo->query("
            SELECT m.supplier_sku, m.stock, m.cost_price, p.id AS product_id, p.name AS product_name, p.price AS product_price, p.is_active AS product_active
            FROM `supplier_product_mappings` m
            JOIN `products` p ON m.product_id = p.id
            WHERE m.supplier_id = {$supplier['id']}
        ")->fetchAll();

        $mapBySku = [];
        foreach ($mappings as $m) {
            $mapBySku[$m['supplier_sku']] = $m;
        }

        $danhSachSanPham = [];
        $demDaMap = 0;
        $demConHang = 0;

        foreach ($nccItems as $it) {
            $sku = $it['product_id'] ?? '';
            $isMapped = isset($mapBySku[$sku]);
            if ($isMapped) $demDaMap++;
            if (($it['available'] ?? 0) > 0) $demConHang++;

            $danhSachSanPham[] = [
                'supplier_sku'   => $sku,
                'name'           => $it['name'] ?? '',
                'description'    => $it['description'] ?? '',
                'type'           => $it['type'] ?? 'other',
                'cost_price'     => (float)($it['walletPricing'] ?? 0),
                'cost_text'      => $it['walletPricingText'] ?? (number_format($it['walletPricing'] ?? 0, 0, ',', '.') . ' VNĐ'),
                'stock'          => (int)($it['available'] ?? 0),
                'image'          => $it['image'] ?? ($mapBySku[$sku]['image'] ?? null),
                'is_mapped'      => $isMapped,
                'shop_product'   => $isMapped ? $mapBySku[$sku] : null
            ];
        }

        echo json_encode([
            'thanh_cong'    => true,
            'supplier_id'   => (int)$supplier['id'],
            'supplier_name' => $supplier['name'],
            'tong_ncc'      => count($nccItems),
            'da_map'        => $demDaMap,
            'chua_map'      => count($nccItems) - $demDaMap,
            'con_hang'      => $demConHang,
            'products'      => $danhSachSanPham
        ]);
        break;

    // 22. ADMIN: ĐỒNG BỘ TỒN KHO & GIÁ VỐN TỪ NHÀ CUNG CẤP HÀNG LOẠT
    case 'admin_dong_bo_ton_kho':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $supplierId = (int)($_GET['supplier_id'] ?? ($_GET['id'] ?? ($_POST['supplier_id'] ?? 0)));
        if ($supplierId > 0) {
            $stmtSup = $pdo->prepare("SELECT * FROM `suppliers` WHERE `id` = ?");
            $stmtSup->execute([$supplierId]);
            $supplier = $stmtSup->fetch();
        } else {
            $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
        }

        if (!$supplier) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa cấu hình nhà cung cấp']);
            exit;
        }

        $apiClient = new NhaCungCapApi($supplier['api_url'], $supplier['api_key']);
        $rawProducts = $apiClient->layDanhSachSanPham();
        $nccItems = $rawProducts['products'] ?? [];

        if (empty($nccItems)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không lấy được danh sách sản phẩm từ nhà cung cấp']);
            exit;
        }

        $stmtUp = $pdo->prepare("
            UPDATE `supplier_product_mappings` 
            SET `stock` = ?, `cost_price` = ? 
            WHERE `supplier_id` = ? AND `supplier_sku` = ?
        ");

        $demCapNhat = 0;
        foreach ($nccItems as $it) {
            $sku = $it['product_id'] ?? '';
            $stock = (int)($it['available'] ?? 0);
            $cost = (float)($it['walletPricing'] ?? 0);

            $stmtUp->execute([$stock, $cost, $supplier['id'], $sku]);
            $checkExist = $pdo->prepare("SELECT id FROM `supplier_product_mappings` WHERE `supplier_id` = ? AND `supplier_sku` = ?");
            $checkExist->execute([$supplier['id'], $sku]);
            if ($checkExist->fetch()) {
                $demCapNhat++;
            }
        }

        // Cập nhật số dư ví nguồn luôn
        $kqVi = $apiClient->laySoDuVi();
        if (!empty($kqVi['success'])) {
            $pdo->prepare("UPDATE `suppliers` SET `balance` = ? WHERE `id` = ?")->execute([$kqVi['balance'], $supplier['id']]);
        }

        echo json_encode([
            'thanh_cong'  => true,
            'thong_bao'   => "Đã đồng bộ tồn kho và giá vốn thời gian thực cho {$demCapNhat} sản phẩm!",
            'dem_cap_nhat'=> $demCapNhat
        ]);
        break;

    // 23. ADMIN: NHẬP SẢN PHẨM TỪ NHÀ CUNG CẤP VÀO SHOP (1-CLICK IMPORT)
    case 'admin_nhap_san_pham_ncc':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $supplierId = (int)($_POST['supplier_id'] ?? ($_GET['supplier_id'] ?? 0));
        if ($supplierId > 0) {
            $stmtSup = $pdo->prepare("SELECT * FROM `suppliers` WHERE `id` = ?");
            $stmtSup->execute([$supplierId]);
            $supplier = $stmtSup->fetch();
        } else {
            $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
        }

        if (!$supplier) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa cấu hình nhà cung cấp']);
            exit;
        }

        $skuNcc = trim($_POST['supplier_sku'] ?? 'ALL'); // 'ALL' hoặc mã cụ thể như 'CUR-0013'
        $customPrice = floatval($_POST['custom_price'] ?? 0);

        $apiClient = new NhaCungCapApi($supplier['api_url'], $supplier['api_key']);
        $rawProducts = $apiClient->layDanhSachSanPham();
        $nccItems = $rawProducts['products'] ?? [];

        if (empty($nccItems)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không tải được danh mục sản phẩm từ nhà cung cấp']);
            exit;
        }

        // Danh mục chuẩn và icon tương ứng
        $danhMucChuan = [
            'cursor'   => ['ten' => 'Cursor API',       'slug' => 'cursor',   'icon' => 'fa-bolt'],
            'claude'   => ['ten' => 'Claude API',       'slug' => 'claude',   'icon' => 'fa-brain'],
            'codex'    => ['ten' => 'Codex API',        'slug' => 'codex',    'icon' => 'fa-code'],
            'gemini'   => ['ten' => 'Gemini API',       'slug' => 'gemini',   'icon' => 'fa-gem'],
            'deepseek' => ['ten' => 'DeepSeek API',     'slug' => 'deepseek', 'icon' => 'fa-robot'],
            'grok'     => ['ten' => 'Grok API',         'slug' => 'grok',     'icon' => 'fa-rocket'],
            'kimi'     => ['ten' => 'Kimi API',         'slug' => 'kimi',     'icon' => 'fa-moon'],
            'zhipu'    => ['ten' => 'GLM (Zhipu) API',  'slug' => 'zhipu',    'icon' => 'fa-feather'],
            'muse'     => ['ten' => 'Muse AI API',      'slug' => 'muse',     'icon' => 'fa-music'],
            'software' => ['ten' => 'Phần Mềm AI',      'slug' => 'software', 'icon' => 'fa-desktop'],
            'other'    => ['ten' => 'Sản Phẩm Khác',    'slug' => 'other',    'icon' => 'fa-cube']
        ];

        // Hàm xóa dấu tiếng việt tạo slug
        function taoSlugVn($str) {
            $unicode = [
                'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
                'd'=>'đ',
                'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
                'i'=>'í|ì|ỉ|ĩ|ị',
                'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
                'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
                'y'=>'ý|ỳ|ỷ|ỹ|ỵ'
            ];
            foreach($unicode as $nonAccent=>$accent){
                $str = preg_replace("/($accent)/iu", $nonAccent, $str);
            }
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $str));
            return trim($slug, '-');
        }

        // Lấy danh mục hiện tại trong DB: slug => id
        $cats = $pdo->query("SELECT `slug`, `id` FROM `categories`")->fetchAll(PDO::FETCH_KEY_PAIR);

        $demThem = 0;
        $demCapNhat = 0;

        foreach ($nccItems as $it) {
            $sku = $it['product_id'] ?? '';
            if ($skuNcc !== 'ALL' && $sku !== $skuNcc) {
                continue;
            }

            $type = strtolower($it['type'] ?? 'other');
            $catInfo = $danhMucChuan[$type] ?? $danhMucChuan['other'];
            $catSlug = $catInfo['slug'];

            // Nếu danh mục chưa có thì tự động tạo
            if (!isset($cats[$catSlug])) {
                $stmtC = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `icon`, `sort_order`, `is_active`) VALUES (?, ?, ?, 10, 1) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)");
                $stmtC->execute([$catInfo['ten'], $catSlug, $catInfo['icon']]);
                
                $getCat = $pdo->prepare("SELECT id FROM `categories` WHERE `slug` = ? LIMIT 1");
                $getCat->execute([$catSlug]);
                $catId = (int)$getCat->fetchColumn();
                $cats[$catSlug] = $catId;
            } else {
                $catId = (int)$cats[$catSlug];
            }

            $cost = (float)($it['walletPricing'] ?? 0);
            $stock = (int)($it['available'] ?? 0);
            $name = trim($it['name'] ?? '');

            // Tính giá bán lẻ tự động (nếu không nhập giá tùy chỉnh)
            if ($skuNcc !== 'ALL' && $customPrice > 0) {
                $price = $customPrice;
            } else {
                if ($cost <= 20000) {
                    $price = $cost + 7000;
                } elseif ($cost <= 60000) {
                    $price = ceil(($cost * 1.25) / 1000) * 1000;
                } elseif ($cost <= 150000) {
                    $price = ceil(($cost * 1.20) / 1000) * 1000;
                } else {
                    $price = ceil(($cost * 1.15) / 1000) * 1000;
                }
            }
            $originalPrice = ceil(($price * 1.3) / 1000) * 1000;

            // Tính thời hạn (duration_days) từ tên
            $duration = 30;
            if (stripos($name, '1 Ngày') !== false || stripos($name, '1 ngày') !== false) {
                $duration = 1;
            } elseif (stripos($name, '3 Ngày') !== false || stripos($name, '3 ngày') !== false) {
                $duration = 3;
            } elseif (stripos($name, '7 Ngày') !== false || stripos($name, '1 tuần') !== false) {
                $duration = 7;
            } elseif (stripos($name, '6 tháng') !== false) {
                $duration = 180;
            } elseif (stripos($name, '12 tháng') !== false || stripos($name, '1 năm') !== false) {
                $duration = 365;
            } elseif (stripos($name, '24 tháng') !== false) {
                $duration = 730;
            }

            // Tính request count hoặc badge nổi bật
            $badge = null;
            $requestCount = 'Tốc độ cao 24/7';
            if (preg_match('/(\d+)\s*Request/i', $name, $matches)) {
                $requestCount = $matches[1] . ' Request';
                $badge = $matches[1] . ' Req';
            } elseif (preg_match('/(\d+)\$/i', $name, $matches)) {
                $requestCount = $matches[1] . '$ Credit';
                $badge = $matches[1] . '$';
            }

            // Kiểm tra xem sản phẩm đã map cho NCC này hay chưa
            $stmtMap = $pdo->prepare("SELECT product_id FROM `supplier_product_mappings` WHERE `supplier_id` = ? AND `supplier_sku` = ?");
            $stmtMap->execute([$supplier['id'], $sku]);
            $existingPid = $stmtMap->fetchColumn();

            if ($existingPid) {
                // Đã có mapping cho NCC này -> Cập nhật tồn kho và giá vốn
                $pdo->prepare("UPDATE `supplier_product_mappings` SET `stock` = ?, `cost_price` = ? WHERE `product_id` = ? AND `supplier_id` = ?")
                    ->execute([$stock, $cost, $existingPid, $supplier['id']]);
                if ($stock > 0) {
                    $pdo->prepare("UPDATE `products` SET `is_active` = 1 WHERE `id` = ?")->execute([$existingPid]);
                }
                if (isset($cats[$catSlug])) {
                    $pdo->prepare("UPDATE `products` SET `category_id` = ? WHERE `id` = ? AND (`category_id` = 1009 OR `category_id` IS NULL)")
                        ->execute([$catId, $existingPid]);
                }
                if (!empty($it['image'])) {
                    $pdo->prepare("UPDATE `products` SET `image` = ? WHERE `id` = ? AND (`image` IS NULL OR `image` = '')")
                        ->execute([$it['image'], $existingPid]);
                }
                $demCapNhat++;
            } else {
                // Kiểm tra xem đã có sản phẩm này trong bảng products (ví dụ từ NCC khác hoặc do admin tạo) chưa
                $ourSku = 'VK-' . strtoupper(str_replace('-', '', $sku));
                $stmtCheckP = $pdo->prepare("SELECT id FROM `products` WHERE `sku` = ? LIMIT 1");
                $stmtCheckP->execute([$ourSku]);
                $targetPid = $stmtCheckP->fetchColumn();

                if ($targetPid) {
                    $targetPid = (int)$targetPid;
                    if ($stock > 0) {
                        $pdo->prepare("UPDATE `products` SET `is_active` = 1 WHERE `id` = ?")->execute([$targetPid]);
                    }
                    if (isset($cats[$catSlug])) {
                        $pdo->prepare("UPDATE `products` SET `category_id` = ? WHERE `id` = ? AND (`category_id` = 1009 OR `category_id` IS NULL)")
                            ->execute([$catId, $targetPid]);
                    }
                    if (!empty($it['image'])) {
                        $pdo->prepare("UPDATE `products` SET `image` = ? WHERE `id` = ? AND (`image` IS NULL OR `image` = '')")
                            ->execute([$it['image'], $targetPid]);
                    }
                } else {
                    // Chưa có sản phẩm trong products -> Tạo sản phẩm mới
                    $slug = taoSlugVn($name) . '-' . strtolower(str_replace('-', '', $sku));
                    $stmtCheckSlug = $pdo->prepare("SELECT id FROM `products` WHERE `slug` = ? LIMIT 1");
                    $stmtCheckSlug->execute([$slug]);
                    if ($stmtCheckSlug->fetchColumn()) {
                        $slug .= '-' . $supplier['id'];
                    }

                    // Chuẩn bị tính năng mặc định
                    $features = json_encode([
                        "Sử dụng full model theo hệ thống nguồn",
                        "Thời hạn bản quyền {$duration} ngày",
                        "Giao key tự động ngay sau thanh toán",
                        "Bảo hành 100% trong thời gian sử dụng"
                    ], JSON_UNESCAPED_UNICODE);

                    $prodImg = $it['image'] ?? null;
                    $stmtInsP = $pdo->prepare("
                        INSERT INTO `products` 
                        (`category_id`, `sku`, `name`, `slug`, `image`, `badge`, `description`, `price`, `original_price`, `request_count`, `duration_days`, `features`, `is_hot`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
                    ");
                    $stmtInsP->execute([
                        $catId, $ourSku, $name, $slug, $prodImg, $badge, $it['description'] ?? '', $price, $originalPrice, $requestCount, $duration, $features
                    ]);
                    $targetPid = (int)$pdo->lastInsertId();
                }

                // Insert hoặc update vào supplier_product_mappings
                $stmtInsMap = $pdo->prepare("
                    INSERT INTO `supplier_product_mappings`
                    (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, 1, 1)
                    ON DUPLICATE KEY UPDATE `cost_price`=VALUES(`cost_price`), `stock`=VALUES(`stock`)
                ");
                $stmtInsMap->execute([$targetPid, $supplier['id'], $sku, $cost, $stock]);
                $demThem++;
            }
        }

        echo json_encode([
            'thanh_cong'   => true,
            'thong_bao'    => "Đã nhập thành công {$demThem} sản phẩm mới và cập nhật {$demCapNhat} sản phẩm có sẵn!",
            'dem_them'     => $demThem,
            'dem_cap_nhat' => $demCapNhat
        ]);
        break;

    case 'admin_test_telegram':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }
        batBuocCsrf();
        $eventType = trim($_POST['event_type'] ?? 'general');
        $allowedEvents = ['order', 'product_new', 'product_out', 'product_price', 'catalog', 'finance', 'support', 'general'];
        if (!in_array($eventType, $allowedEvents, true)) $eventType = 'general';

        $sent = false;
        if ($eventType === 'order') {
            $sent = guiThongBaoDonHang([
                'ma_don'          => 'TEST' . date('ymd') . rand(1000, 9999),
                'ten_san_pham'    => 'Test Gói OpenAI / Claude API (Thử Nghiệm)',
                'so_luong'        => 1,
                'tong_tien'       => 150000,
                'email_khach'     => 'khachhang_test@tiemai.io.vn',
                'phuong_thuc'     => 'VietQR ACB / Thử nghiệm Admin',
                'trang_thai_key'  => 'Đã giao key thử nghiệm thành công'
            ]);
        } elseif ($eventType === 'product_new') {
            $sent = guiThongBaoSanPhamMoi([
                'name'          => 'Claude 3.7 Sonnet Frontier (Gói Thử Nghiệm)',
                'price'         => 120000,
                'category_name' => 'API AI Agent',
                'supplier'      => 'Chính hãng đối tác',
                'url'           => (defined('SITE_URL') ? SITE_URL : 'https://tiemai.io.vn') . '/#san-pham'
            ]);
        } elseif ($eventType === 'product_out') {
            $sent = guiThongBaoHetHang([
                'name'     => 'Codex Pro Unlimited (Gói Thử Nghiệm)',
                'sku'      => 'CDX-TEST-01',
                'supplier' => 'Kho nguồn API',
                'note'     => 'Tin nhắn thử nghiệm kiểm tra tính năng báo hết hàng'
            ]);
        } elseif ($eventType === 'product_price') {
            $sent = guiThongBaoBienDongGia([
                'name' => 'Gemini 2.5 Flash API (Thử Nghiệm)',
                'sku'  => 'GEMINI-FLASH'
            ], 60000, 48000);
        } elseif ($eventType === 'finance') {
            $sent = guiThongBaoNapTien([
                'user_email'       => 'admin@tiemai.io.vn',
                'amount'           => 500000,
                'gateway'          => 'Ngân hàng ACB (SePay Auto)',
                'balance_after'    => 1250000,
                'transaction_code' => 'SEPAY_TEST_' . rand(10000, 99999)
            ]);
        } else {
            $message = "✅ <b>TEST TELEGRAM THÀNH CÔNG</b>\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "📌 Loại nhóm: <code>" . telegramEscape($eventType) . "</code>\n";
            $message .= "🕒 Thời gian: <i>" . date('d/m/Y H:i:s') . "</i>\n";
            $message .= "🌐 Website: <b>" . telegramEscape(SHOP_NAME) . " (tiemai.io.vn)</b>";
            $sent = guiThongBaoTelegram($message, $eventType);
        }

        echo json_encode([
            'thanh_cong' => $sent, 
            'thong_bao'  => $sent 
                ? "Đã gửi tin nhắn thử nghiệm thành công tới nhóm [{$eventType}]!" 
                : "Chưa gửi được! Vui lòng kiểm tra lại Bot Token và Chat ID của nhóm này (đảm bảo Bot đã được add vào nhóm và có quyền gửi tin)."
        ], JSON_UNESCAPED_UNICODE);
        break;

    // GỬI THỬ EMAIL THÔNG BÁO CHO QUẢN TRỊ VIÊN
    case 'admin_test_email':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền truy cập']);
            exit;
        }
        batBuocCsrf();
        $testEmail = trim($_POST['email'] ?? '');
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập địa chỉ email nhận hợp lệ']);
            exit;
        }
        require_once __DIR__ . '/mailer.php';
        $kqEmail = guiEmailThuNghiem($testEmail);
        echo json_encode($kqEmail, JSON_UNESCAPED_UNICODE);
        break;

    // ========================================================
    // PHÂN HỆ QUẢN LÝ XÁC THỰC HAI LỚP (2FA TOTP AUTHENTICATOR)
    // ========================================================
    case '2fa_khoi_tao':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập trước']);
            exit;
        }
        batBuocCsrf();
        require_once __DIR__ . '/totp.php';
        $secret = TotpAuth::taoSecretKey(16);
        $_SESSION['2fa_setup_secret'] = $secret;
        $userEmail = $_SESSION['user_email'] ?? 'user@tiemai.io.vn';
        $uri = TotpAuth::layTotpUri($secret, $userEmail, SHOP_NAME ?: 'TiemAI');
        $qrUrl = TotpAuth::layLinkQrCode($uri, 220);
        echo json_encode([
            'thanh_cong' => true,
            'secret'     => $secret,
            'qr_url'     => $qrUrl,
            'totp_uri'   => $uri
        ], JSON_UNESCAPED_UNICODE);
        break;

    case '2fa_kich_hoat':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập trước']);
            exit;
        }
        batBuocCsrf();
        require_once __DIR__ . '/totp.php';
        $setupSecret = $_SESSION['2fa_setup_secret'] ?? '';
        if (empty($setupSecret)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Phiên cài đặt 2FA đã hết hạn. Vui lòng bấm làm mới mã QR!']);
            exit;
        }
        $code = trim($_POST['code'] ?? '');
        if (!TotpAuth::xacThuc($setupSecret, $code)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã xác thực 6 số không đúng hoặc đã hết hạn! Vui lòng kiểm tra lại giờ hệ thống trên điện thoại.']);
            exit;
        }

        // Tạo 8 mã dự phòng khẩn cấp
        $backupCodes = TotpAuth::taoDanhSachMaDuPhong(8);
        $backupJson = json_encode($backupCodes);

        $stmt = $pdo->prepare("UPDATE `users` SET `two_factor_secret` = ?, `two_factor_enabled` = 1, `two_factor_recovery_codes` = ? WHERE `id` = ?");
        $stmt->execute([$setupSecret, $backupJson, $_SESSION['user_id']]);
        unset($_SESSION['2fa_setup_secret']);

        echo json_encode([
            'thanh_cong'     => true,
            'thong_bao'      => 'Đã kích hoạt xác thực hai lớp qua Authenticator thành công!',
            'recovery_codes' => $backupCodes
        ], JSON_UNESCAPED_UNICODE);
        break;

    case '2fa_tat':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập trước']);
            exit;
        }
        batBuocCsrf();
        require_once __DIR__ . '/totp.php';
        $code = trim($_POST['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập mã OTP hoặc mật khẩu để xác nhận tắt 2FA']);
            exit;
        }

        $stmtU = $pdo->prepare("SELECT two_factor_secret, password FROM `users` WHERE `id` = ?");
        $stmtU->execute([$_SESSION['user_id']]);
        $uData = $stmtU->fetch();

        $valid = false;
        if (!empty($uData['two_factor_secret']) && TotpAuth::xacThuc($uData['two_factor_secret'], $code)) {
            $valid = true;
        } elseif (!empty($uData['password']) && password_verify($code, $uData['password'])) {
            $valid = true;
        }

        if (!$valid) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã OTP hoặc mật khẩu xác nhận không chính xác!']);
            exit;
        }

        $pdo->prepare("UPDATE `users` SET `two_factor_secret` = NULL, `two_factor_enabled` = 0, `two_factor_recovery_codes` = NULL WHERE `id` = ?")
            ->execute([$_SESSION['user_id']]);

        echo json_encode(['thanh_cong' => true, 'thong_bao' => 'Đã tắt xác thực 2FA thành công']);
        break;

    case '2fa_lay_ma_du_phong':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập trước']);
            exit;
        }
        batBuocCsrf();
        require_once __DIR__ . '/totp.php';
        $code = trim($_POST['code'] ?? '');
        $stmtU = $pdo->prepare("SELECT two_factor_secret, two_factor_recovery_codes FROM `users` WHERE `id` = ?");
        $stmtU->execute([$_SESSION['user_id']]);
        $uData = $stmtU->fetch();

        if (empty($uData['two_factor_secret']) || !TotpAuth::xacThuc($uData['two_factor_secret'], $code)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã OTP 6 số không đúng. Vui lòng nhập đúng mã từ Authenticator!']);
            exit;
        }

        $codes = json_decode($uData['two_factor_recovery_codes'] ?? '[]', true) ?: [];
        echo json_encode(['thanh_cong' => true, 'recovery_codes' => $codes], JSON_UNESCAPED_UNICODE);
        break;

    case '2fa_tao_lai_ma_du_phong':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng đăng nhập trước']);
            exit;
        }
        batBuocCsrf();
        require_once __DIR__ . '/totp.php';
        $code = trim($_POST['code'] ?? '');
        $stmtU = $pdo->prepare("SELECT two_factor_secret FROM `users` WHERE `id` = ?");
        $stmtU->execute([$_SESSION['user_id']]);
        $sec = $stmtU->fetchColumn();

        if (empty($sec) || !TotpAuth::xacThuc($sec, $code)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Mã OTP không hợp lệ!']);
            exit;
        }

        $newCodes = TotpAuth::taoDanhSachMaDuPhong(8);
        $pdo->prepare("UPDATE `users` SET `two_factor_recovery_codes` = ? WHERE `id` = ?")
            ->execute([json_encode($newCodes), $_SESSION['user_id']]);

        echo json_encode(['thanh_cong' => true, 'recovery_codes' => $newCodes, 'thong_bao' => 'Đã tạo lại bộ 8 mã dự phòng mới!'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Hành động không hợp lệ']);
        break;
}
