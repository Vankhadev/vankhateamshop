<?php
// ========================================================
// FILE WEBHOOK NHẬN TIỀN TỰ ĐỘNG TỪ SEPAY.VN (VIETQR)
// vankhateamshop - Khi khách quét mã chuyển tiền thành công,
// ngân hàng báo về đây -> Tự động mua Key và trả cho khách
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dispatcher.php';

// 1. Nhận dữ liệu JSON do SePay bắn sang
$rawInput = file_get_contents('php://input');
$duLieuSepay = json_decode($rawInput, true);

if (!$duLieuSepay) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

// 2. Lấy các thông số giao dịch ngân hàng
// SePay gửi các trường: gateway, transactionDate, accountNumber, transferType, transferAmount, content, id...
$soTienChuyen = (float)($duLieuSepay['transferAmount'] ?? 0);
$noiDungChuyenKhoan = trim($duLieuSepay['content'] ?? '');
$maGiaoDichNganHang = $duLieuSepay['id'] ?? ('SEPAY_' . time());

// 3. Trích xuất mã đơn hàng hoặc mã nạp tiền từ nội dung chuyển khoản
// Hỗ trợ mã đơn hàng VKxxxxxx (vd: VK839102) hoặc nạp tiền NAPxxxx (vd: NAP2T892, NAPQUY2)
$maDonTimThay = null;
$maNapTimThay = null;
$userIdNap = null;

if (preg_match('/(VK[A-Z0-9]{6,12})/i', $noiDungChuyenKhoan, $matches)) {
    $maDonTimThay = strtoupper($matches[1]);
} elseif (preg_match('/(?:NAPQUY|NAP)(\d+)/i', $noiDungChuyenKhoan, $napMatches)) {
    $userIdNap = (int)$napMatches[1];
}

// TRƯỜNG HỢP 1: NẠP TIỀN VÀO VÍ TÀI KHOẢN (NAP...)
if ($userIdNap) {
    $stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
    $stmtUser->execute([$userIdNap]);
    $user = $stmtUser->fetch();

    if ($user) {
        $pdo->beginTransaction();
        try {
            // Cộng tiền vào ví tài khoản
            $pdo->prepare("UPDATE `users` SET `balance` = `balance` + ? WHERE `id` = ?")->execute([$soTienChuyen, $userIdNap]);

            // Ghi nhận biến động số dư
            $pdo->prepare("
                INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_after`, `description`, `reference_code`)
                VALUES (?, 'DEPOSIT', ?, ?, ?, ?)
            ")->execute([
                $userIdNap,
                $soTienChuyen,
                $user['balance'] + $soTienChuyen,
                "Nạp tiền tự động qua VietQR ACB",
                $maGiaoDichNganHang
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Đã nạp thành công " . number_format($soTienChuyen) . "đ cho tài khoản #{$userIdNap}"
            ]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi nạp tiền: ' . $e->getMessage()]);
            exit;
        }
    }
}

// TRƯỜNG HỢP 2: THANH TOÁN ĐƠN HÀNG TRỰC TIẾP (VK...)
if (!$maDonTimThay) {
    echo json_encode(['success' => false, 'message' => 'Nội dung chuyển khoản không khớp đơn hàng hoặc nạp tiền']);
    exit;
}

// 4. Tìm đơn hàng trong cơ sở dữ liệu
$stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `order_code` = ?");
$stmt->execute([$maDonTimThay]);
$donHang = $stmt->fetch();

if (!$donHang) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng: ' . $maDonTimThay]);
    exit;
}

// Nếu đơn hàng đã hoàn tất rồi thì không mua lại
if ($donHang['status'] === 'COMPLETED') {
    echo json_encode(['success' => true, 'message' => 'Đơn hàng này đã giao key trước đó rồi']);
    exit;
}

// Kiểm tra số tiền chuyển có đủ tiền của đơn hàng không
if ($soTienChuyen < (float)$donHang['total_amount']) {
    echo json_encode(['success' => false, 'message' => 'Số tiền chuyển không đủ cho đơn hàng']);
    exit;
}

// 5. Cập nhật đơn hàng thành ĐÃ THANH TOÁN (PAID)
$pdo->prepare("UPDATE `orders` SET `status` = 'PAID' WHERE `id` = ?")->execute([$donHang['id']]);

// 6. GỌI BỘ ĐIỀU PHỐI ĐỂ MUA KEY TỪ NHÀ CUNG CẤP HOẶC NGUỒN DỰ PHÒNG
$boDieuPhoi = new BoDieuPhoiDonHang($pdo);
$ketQua = $boDieuPhoi->xuLyDonHang($maDonTimThay);

// 7. Trả phản hồi thành công về cho SePay
echo json_encode([
    'success' => true,
    'message' => 'Xử lý đơn hàng tự động thành công',
    'ket_qua' => $ketQua
]);
