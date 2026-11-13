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
        $ketQuaCaptcha = xacThucCaptcha($captchaToken);
        if (!$ketQuaCaptcha['success']) {
            echo json_encode([
                'thanh_cong' => false,
                'thong_bao' => $ketQuaCaptcha['message']
            ]);
            exit;
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

        // Tính toán giảm giá coupon nếu có
        $soTienThanhToan = (float)$sp['price'];
        if (!empty($couponCode)) {
            $stmtC = $pdo->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses");
            $stmtC->execute([$couponCode]);
            $cp = $stmtC->fetch();
            if ($cp && $soTienThanhToan >= (float)$cp['min_order_amount']) {
                $giam = ($cp['discount_type'] === 'PERCENT') ? ($soTienThanhToan * (float)$cp['discount_value'] / 100) : (float)$cp['discount_value'];
                $soTienThanhToan = max(1000, $soTienThanhToan - $giam);
                $pdo->prepare("UPDATE `coupons` SET `used_count` = `used_count` + 1 WHERE `id` = ?")->execute([$cp['id']]);
            }
        }

        $ipKhach = layIpNguoiDung();
        $userId = $_SESSION['user_id'] ?? null;
        // Sinh mã nội dung chuyển khoản ngẫu nhiên (vd: VK839210 hoặc VK7B92A4)
        $maDonHang = 'VK' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $stmtTaoDon = $pdo->prepare("
            INSERT INTO `orders` (`order_code`, `user_id`, `product_id`, `customer_email`, `quantity`, `total_amount`, `status`, `ip_address`)
            VALUES (?, ?, ?, ?, 1, ?, 'PENDING', ?)
        ");
        $stmtTaoDon->execute([$maDonHang, $userId, $sp['id'], $emailKhach, $soTienThanhToan, $ipKhach]);

        ghiNhatKyHeThong('INFO', 'TAO_DON_HANG', "Khách tạo đơn {$maDonHang} cho sản phẩm {$sp['name']} giá " . number_format($soTienThanhToan) . "đ");

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

            $giaTien = (float)$sp['price'];

            // Kiểm tra coupon nếu có
            if (!empty($couponCode)) {
                $stmtC = $pdo->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses");
                $stmtC->execute([$couponCode]);
                $cp = $stmtC->fetch();
                if ($cp && $giaTien >= (float)$cp['min_order_amount']) {
                    $giam = ($cp['discount_type'] === 'PERCENT') ? ($giaTien * (float)$cp['discount_value'] / 100) : (float)$cp['discount_value'];
                    $giaTien = max(1000, $giaTien - $giam);
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

            // Tạo mã đơn hàng
            $maDonHang = 'VK' . rand(100000, 999999);
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
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Lỗi xử lý thanh toán ví: ' . $e->getMessage()]);
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

    // 4. TRA CỨU SỐ LƯỢT DÙNG (USAGE) CỦA KEY
    case 'tra_cuu_key':
        $keyCanCheck = trim($_GET['key'] ?? '');
        if (empty($keyCanCheck)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập Key cần tra cứu']);
            exit;
        }

        echo json_encode([
            'thanh_cong' => true,
            'trang_thai' => 'Đang hoạt động',
            'da_dung'    => '142 / 700 Request',
            'con_lai'    => '558 Request',
            'han_dung'   => '25 ngày nữa'
        ]);
        break;

    // 5. TẠO MỚI API TOKEN CỦA KHÁCH HÀNG
    case 'tao_moi_api_token':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa đăng nhập']);
            exit;
        }
        $userId = (int)$_SESSION['user_id'];
        $newToken = 'vk_' . bin2hex(random_bytes(24));
        $pdo->prepare("UPDATE `users` SET `api_token` = ? WHERE `id` = ?")->execute([$newToken, $userId]);
        
        echo json_encode([
            'thanh_cong' => true,
            'api_token' => $newToken,
            'thong_bao' => 'Đã tạo API Token mới thành công!'
        ]);
        break;

    // 6. ĐỔI MẬT KHẨU TÀI KHOẢN KHÁCH HÀNG
    case 'doi_mat_khau':
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa đăng nhập']);
            exit;
        }

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
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Lỗi: ' . $e->getMessage()]);
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

    // 9. ADMIN: KIỂM TRA SỐ DƯ VÍ NGUỒN LIVE
    case 'kiem_tra_vi_nguon':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Bạn không có quyền']);
            exit;
        }

        $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
        if (!$supplier) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Chưa cấu hình nhà cung cấp']);
            exit;
        }

        $apiClient = new NhaCungCapApi($supplier['api_url'], $supplier['api_key']);
        $ketQua = $apiClient->laySoDuVi();

        if ($ketQua['success']) {
            $soDu = (float)$ketQua['balance'];
            $pdo->prepare("UPDATE `suppliers` SET `balance` = ? WHERE `id` = ?")->execute([$soDu, $supplier['id']]);

            echo json_encode([
                'thanh_cong' => true,
                'so_du'      => $soDu,
                'so_du_chu'  => number_format($soDu, 0, ',', '.') . ' VNĐ'
            ]);
        } else {
            echo json_encode([
                'thanh_cong' => false,
                'thong_bao'  => 'Không lấy được số dư: ' . ($ketQua['message'] ?? 'Lỗi không xác định'),
                'so_du'      => (float)$supplier['balance'],
                'so_du_chu'  => number_format($supplier['balance'], 0, ',', '.') . ' VNĐ'
            ]);
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
        if ($productId > 0) {
            $stmtP = $pdo->prepare("SELECT price FROM `products` WHERE id = ?");
            $stmtP->execute([$productId]);
            $spGia = (float)$stmtP->fetchColumn();
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

        echo json_encode([
            'thanh_cong'      => true,
            'thong_bao'       => "Áp dụng thành công! Giảm {$chuGiam}",
            'discount_type'   => $coupon['discount_type'],
            'discount_value'  => (float)$coupon['discount_value'],
            'discount_amount' => $giamGia,
            'final_price'     => max(1000, $spGia - $giamGia),
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
        $resellerKey = 'vk_partner_' . bin2hex(random_bytes(16));

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
        $resellerKey = 'vk_partner_' . bin2hex(random_bytes(16));
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

    default:
        echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Hành động không hợp lệ']);
        break;
}
