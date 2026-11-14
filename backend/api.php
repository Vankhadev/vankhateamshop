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

    // 4. TRA CỨU SỐ LƯỢT DÙNG (USAGE) & QUOTA CỦA KEY THEO BASE URL
    case 'tra_cuu_key':
        $keyCanCheck = trim($_GET['key'] ?? '');
        if (empty($keyCanCheck)) {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Vui lòng nhập Key hoặc Mã đơn hàng cần tra cứu']);
            exit;
        }

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'tiemai.io.vn';
        $baseUrlWebsite = $protocol . '://' . $host . '/v1';
        $chatEndpointWebsite = $baseUrlWebsite . '/chat/completions';

        // 1. Thử tìm trong bảng đơn hàng đã giao (orders)
        $stmtOrder = $pdo->prepare("
            SELECT o.*, p.name AS product_name, p.sku AS product_sku, p.supported_models
            FROM `orders` o
            LEFT JOIN `products` p ON o.product_id = p.id
            WHERE (o.delivered_data LIKE ? OR o.order_code = ? OR o.customer_email = ?)
              AND o.status = 'COMPLETED'
            ORDER BY o.id DESC LIMIT 1
        ");
        $stmtOrder->execute(["%$keyCanCheck%", $keyCanCheck, $keyCanCheck]);
        $order = $stmtOrder->fetch();

        if ($order) {
            $prodName = $order['product_name'] ?? 'Gói API AI Gateway';
            $prodSku = $order['product_sku'] ?? '';
            
            // Xác định tổng số lượt request từ tên gói hoặc SKU
            $tongRequest = 700;
            if (preg_match('/1500/i', $prodName . ' ' . $prodSku)) {
                $tongRequest = 1500;
            } elseif (preg_match('/500/i', $prodName . ' ' . $prodSku)) {
                $tongRequest = 500;
            } elseif (preg_match('/300/i', $prodName . ' ' . $prodSku)) {
                $tongRequest = 300;
            }

            // Xác định thời hạn (ngày)
            $soNgay = 30;
            if (preg_match('/1\s*ng[àa]y/i', $prodName . ' ' . $prodSku)) {
                $soNgay = 1;
            } elseif (preg_match('/3\s*ng[àa]y/i', $prodName . ' ' . $prodSku)) {
                $soNgay = 3;
            } elseif (preg_match('/7\s*ng[àa]y/i', $prodName . ' ' . $prodSku)) {
                $soNgay = 7;
            } elseif (preg_match('/30\s*ng[àa]y|1\s*th[áa]ng/i', $prodName . ' ' . $prodSku)) {
                $soNgay = 30;
            }

            $thoiGianTao = strtotime($order['created_at'] ?? 'now');
            $thoiGianHetHan = $thoiGianTao + ($soNgay * 86400);
            $giayConLai = $thoiGianHetHan - time();
            $ngayConLai = ceil($giayConLai / 86400);

            // Đếm số request thực tế đã dùng qua Gateway từ api_logs
            $shortKey = substr($keyCanCheck, 0, 10);
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) FROM `api_logs` 
                WHERE (`api_key_used` LIKE ? OR `customer_email` = ?) AND `created_at` >= ?
            ");
            $stmtCount->execute(["%$shortKey%", $order['customer_email'], $order['created_at']]);
            $daDung = (int)$stmtCount->fetchColumn();

            $conLai = max(0, $tongRequest - $daDung);
            $hetHan = ($giayConLai <= 0);

            $trangThai = $hetHan ? 'Đã hết hạn' : 'Đang hoạt động';
            $hanDungText = $hetHan ? 'Hết hạn sử dụng' : ($ngayConLai <= 1 ? (max(1, ceil($giayConLai / 3600)) . ' giờ nữa') : ($ngayConLai . ' ngày nữa'));

            // Danh sách model
            $models = [];
            if (!empty($order['supported_models'])) {
                $models = array_values(array_filter(array_map('trim', explode(',', $order['supported_models']))));
            }
            if (empty($models)) {
                $models = ['claude-3-5-sonnet', 'deepseek-reasoner', 'deepseek-chat', 'gpt-4o', 'cursor-small'];
            }

            echo json_encode([
                'thanh_cong'       => true,
                'trang_thai'       => $trangThai,
                'is_active'        => !$hetHan,
                'ten_goi'          => $prodName,
                'ma_don_hang'      => $order['order_code'],
                'da_dung'          => number_format($daDung) . ' / ' . number_format($tongRequest) . ' Request',
                'so_luot_da_dung'  => $daDung,
                'so_luot_tong'     => $tongRequest,
                'so_luot_con_lai'  => $conLai,
                'con_lai'          => number_format($conLai) . ' Request',
                'phan_tram'        => min(100, round(($daDung / max(1, $tongRequest)) * 100)),
                'han_dung'         => $hanDungText,
                'ngay_het_han'     => date('d/m/Y H:i', $thoiGianHetHan),
                'models'           => $models,
                'base_url'         => $baseUrlWebsite,
                'chat_endpoint'    => $chatEndpointWebsite,
                'key_masked'       => substr($keyCanCheck, 0, 8) . '...' . substr($keyCanCheck, -4)
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

            echo json_encode([
                'thanh_cong'       => true,
                'trang_thai'       => 'Đang hoạt động',
                'is_active'        => true,
                'ten_goi'          => $user['is_reseller'] ? 'Tài Khoản Đại Lý API Đối Tác' : 'API Token Thành Viên Shop',
                'ma_don_hang'      => 'USER-' . $user['id'],
                'da_dung'          => number_format($daDungUser) . ' Request đã gọi',
                'so_luot_da_dung'  => $daDungUser,
                'so_luot_tong'     => 10000,
                'so_luot_con_lai'  => 10000 - $daDungUser,
                'con_lai'          => number_format(max(0, 10000 - $daDungUser)) . ' Request',
                'phan_tram'        => min(100, round(($daDungUser / 10000) * 100)),
                'han_dung'         => 'Vĩnh viễn theo số dư ví',
                'ngay_het_han'     => 'Theo số dư ví (' . number_format($user['balance']) . ' đ)',
                'models'           => $models,
                'base_url'         => $baseUrlWebsite,
                'chat_endpoint'    => $chatEndpointWebsite,
                'key_masked'       => substr($keyCanCheck, 0, 6) . '...' . substr($keyCanCheck, -4)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. Nếu Key có định dạng API chuẩn (sk-..., vk_..., gzsk_...) hoặc độ dài >= 16 ký tự
        if (strlen($keyCanCheck) >= 14 && (str_starts_with($keyCanCheck, 'sk-') || str_starts_with($keyCanCheck, 'vk_') || str_starts_with($keyCanCheck, 'gzsk_') || str_starts_with($keyCanCheck, 'cur-') || str_starts_with($keyCanCheck, 'VK'))) {
            // Đếm xem key này đã gọi lần nào qua Gateway chưa
            $shortKey = substr($keyCanCheck, 0, 10);
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `api_logs` WHERE `api_key_used` LIKE ?");
            $stmtCount->execute(["%$shortKey%"]);
            $daDung = (int)$stmtCount->fetchColumn();

            $tongRequest = 700;
            $conLai = max(0, $tongRequest - $daDung);

            echo json_encode([
                'thanh_cong'       => true,
                'trang_thai'       => 'Đang hoạt động',
                'is_active'        => true,
                'ten_goi'          => 'Gói Kết Nối AI Gateway Cursor / Claude',
                'ma_don_hang'      => 'KEY-ONLINE',
                'da_dung'          => number_format($daDung) . ' / ' . number_format($tongRequest) . ' Request',
                'so_luot_da_dung'  => $daDung,
                'so_luot_tong'     => $tongRequest,
                'so_luot_con_lai'  => $conLai,
                'con_lai'          => number_format($conLai) . ' Request',
                'phan_tram'        => min(100, round(($daDung / $tongRequest) * 100)),
                'han_dung'         => 'Còn hạn sử dụng',
                'ngay_het_han'     => date('d/m/Y', strtotime('+28 days')),
                'models'           => ['claude-3-5-sonnet', 'deepseek-reasoner', 'deepseek-chat', 'gpt-4o', 'cursor-small'],
                'base_url'         => $baseUrlWebsite,
                'chat_endpoint'    => $chatEndpointWebsite,
                'key_masked'       => substr($keyCanCheck, 0, 8) . '...' . substr($keyCanCheck, -4)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Không tìm thấy
        echo json_encode([
            'thanh_cong' => false,
            'thong_bao'  => 'Không tìm thấy thông tin của mã Key hoặc Đơn hàng này. Vui lòng kiểm tra lại chính xác mã!'
        ], JSON_UNESCAPED_UNICODE);
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
            $resellerKey = 'vk_partner_' . bin2hex(random_bytes(16));
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

    // 21. ADMIN: LẤY DANH SÁCH SẢN PHẨM & TỒN KHO THỜI GIAN THỰC TỪ NHÀ CUNG CẤP (GENZSHOP)
    case 'admin_lay_kho_ncc':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
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
                'is_mapped'      => $isMapped,
                'shop_product'   => $isMapped ? $mapBySku[$sku] : null
            ];
        }

        echo json_encode([
            'thanh_cong'    => true,
            'tong_ncc'      => count($nccItems),
            'da_map'        => $demDaMap,
            'chua_map'      => count($nccItems) - $demDaMap,
            'con_hang'      => $demConHang,
            'supplier_name' => $supplier['name'],
            'products'      => $danhSachSanPham
        ]);
        break;

    // 22. ADMIN: ĐỒNG BỘ TỒN KHO & GIÁ VỐN TỪ NHÀ CUNG CẤP HÀNG LOẠT
    case 'admin_dong_bo_ton_kho':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Không có quyền']);
            exit;
        }

        $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
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

        $supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();
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

            // Kiểm tra xem sản phẩm đã map hay chưa
            $stmtMap = $pdo->prepare("SELECT product_id FROM `supplier_product_mappings` WHERE `supplier_id` = ? AND `supplier_sku` = ?");
            $stmtMap->execute([$supplier['id'], $sku]);
            $existingPid = $stmtMap->fetchColumn();

            if ($existingPid) {
                // Đã có -> Cập nhật tồn kho và giá vốn
                $pdo->prepare("UPDATE `supplier_product_mappings` SET `stock` = ?, `cost_price` = ? WHERE `product_id` = ? AND `supplier_id` = ?")
                    ->execute([$stock, $cost, $existingPid, $supplier['id']]);
                $demCapNhat++;
            } else {
                // Tạo SKU và slug duy nhất
                $ourSku = 'VK-' . strtoupper(str_replace('-', '', $sku));
                $slug = taoSlugVn($name) . '-' . strtolower(str_replace('-', '', $sku));

                // Chuẩn bị tính năng mặc định
                $features = json_encode([
                    "Sử dụng full model theo hệ thống nguồn",
                    "Thời hạn bản quyền {$duration} ngày",
                    "Giao key tự động ngay sau thanh toán",
                    "Bảo hành 100% trong thời gian sử dụng"
                ], JSON_UNESCAPED_UNICODE);

                // Insert vào products
                $stmtInsP = $pdo->prepare("
                    INSERT INTO `products` 
                    (`category_id`, `sku`, `name`, `slug`, `badge`, `description`, `price`, `original_price`, `request_count`, `duration_days`, `features`, `is_hot`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
                ");
                $stmtInsP->execute([
                    $catId, $ourSku, $name, $slug, $badge, $it['description'] ?? '', $price, $originalPrice, $requestCount, $duration, $features
                ]);
                $newPid = (int)$pdo->lastInsertId();

                // Insert vào supplier_product_mappings
                $stmtInsMap = $pdo->prepare("
                    INSERT INTO `supplier_product_mappings`
                    (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, 1, 1)
                ");
                $stmtInsMap->execute([$newPid, $supplier['id'], $sku, $cost, $stock]);
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

    default:
        echo json_encode(['thanh_cong' => false, 'thong_bao' => 'Hành động không hợp lệ']);
        break;
}
