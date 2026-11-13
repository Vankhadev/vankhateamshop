<?php
// ========================================================
// CÔNG CỤ XUẤT DỮ LIỆU EXCEL / CSV CHO QUẢN TRỊ VIÊN
// vankhateamshop - Xuất đơn hàng, biến động số dư, danh sách thành viên
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// Kiểm tra quyền Admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Bạn không có quyền truy cập chức năng này.");
}

$loaiXuat = $_GET['type'] ?? 'don_hang';
$thoiGian = date('Ymd_His');

switch ($loaiXuat) {
    case 'don_hang':
        $tenFile = "DonHang_{$thoiGian}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tenFile . '"');

        $output = fopen('php://output', 'w');
        // Thêm BOM để Microsoft Excel mở tiếng Việt không bị lỗi font
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Tiêu đề cột
        fputcsv($output, ['ID', 'Mã Đơn', 'Email Khách', 'Sản Phẩm', 'Số Tiền (VNĐ)', 'Trạng Thái', 'Nhà Cung Cấp', 'IP Khách', 'Thời Gian']);

        $stmt = $pdo->query("
            SELECT o.id, o.order_code, o.customer_email, p.name AS product_name, o.total_amount, o.status, s.name AS supplier_name, o.ip_address, o.created_at
            FROM `orders` o
            LEFT JOIN `products` p ON o.product_id = p.id
            LEFT JOIN `suppliers` s ON o.supplier_id = s.id
            ORDER BY o.id DESC
        ");

        while ($dong = $stmt->fetch()) {
            fputcsv($output, [
                $dong['id'],
                $dong['order_code'],
                $dong['customer_email'],
                $dong['product_name'] ?? 'N/A',
                $dong['total_amount'],
                $dong['status'],
                $dong['supplier_name'] ?? 'N/A',
                $dong['ip_address'] ?? 'N/A',
                $dong['created_at']
            ]);
        }
        fclose($output);
        exit;

    case 'giao_dich':
        $tenFile = "BienDongSoDu_{$thoiGian}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tenFile . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['ID', 'Email Thành Viên', 'Mã Đơn', 'Loại Giao Dịch', 'Số Tiền (VNĐ)', 'Số Dư Trước', 'Số Dư Sau', 'Ghi Chú', 'Thời Gian']);

        $stmt = $pdo->query("
            SELECT t.id, u.email, o.order_code, t.type, t.amount, t.balance_before, t.balance_after, t.note, t.created_at
            FROM `transactions` t
            JOIN `users` u ON t.user_id = u.id
            LEFT JOIN `orders` o ON t.order_id = o.id
            ORDER BY t.id DESC
        ");

        while ($dong = $stmt->fetch()) {
            fputcsv($output, [
                $dong['id'],
                $dong['email'],
                $dong['order_code'] ?? 'N/A',
                $dong['type'],
                $dong['amount'],
                $dong['balance_before'],
                $dong['balance_after'],
                $dong['note'],
                $dong['created_at']
            ]);
        }
        fclose($output);
        exit;

    case 'thanh_vien':
        $tenFile = "DanhSachThanhVien_{$thoiGian}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tenFile . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['ID', 'Họ Tên', 'Email', 'Số Điện Thoại', 'Số Dư Ví', 'Vai Trò', 'Trạng Thái', 'Ngày Đăng Ký']);

        $stmt = $pdo->query("SELECT id, name, email, phone, balance, role, is_active, created_at FROM `users` ORDER BY id DESC");

        while ($dong = $stmt->fetch()) {
            fputcsv($output, [
                $dong['id'],
                $dong['name'],
                $dong['email'],
                $dong['phone'] ?? '',
                $dong['balance'],
                $dong['role'],
                $dong['is_active'] ? 'Hoạt động' : 'Bị khóa',
                $dong['created_at']
            ]);
        }
        fclose($output);
        exit;

    default:
        die("Loại xuất dữ liệu không hợp lệ.");
}
