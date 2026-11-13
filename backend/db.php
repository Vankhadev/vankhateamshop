<?php
// ========================================================
// FILE KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL (PDO)
// vankhateamshop - An toàn, chống hack SQL Injection 100%
// ========================================================

require_once __DIR__ . '/config.php';

try {
    // Chuỗi kết nối PDO
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    // Tùy chọn bảo mật và hiệu năng
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Bật chế độ báo lỗi chi tiết
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Trả về dữ liệu dạng mảng kết hợp
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Chống SQL Injection tuyệt đối
    ];

    // Tạo biến kết nối $pdo dùng chung cho toàn bộ website
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Nếu kết nối lỗi thì báo ra màn hình dễ hiểu
    die("<div style='background:#fee2e2;color:#991b1b;padding:20px;border-radius:10px;font-family:sans-serif;'>
        <h3>⚠️ Lỗi kết nối Cơ sở dữ liệu!</h3>
        <p>Vui lòng kiểm tra lại cấu hình trong file <b>backend/config.php</b> và đảm bảo bạn đã import file <b>vankhateamshop.sql</b> vào MySQL.</p>
        <p>Chi tiết lỗi kỹ thuật: " . $e->getMessage() . "</p>
    </div>");
}
