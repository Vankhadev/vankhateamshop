<?php
// ========================================================
// ĐĂNG XUẤT KHỎI HỆ THỐNG (LOGOUT)
// vankhateamshop - Xóa phiên đăng nhập người dùng an toàn
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa tất cả biến session
$_SESSION = [];

// Xóa cookie phiên nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy hoàn toàn phiên
session_destroy();

// Chuyển hướng về trang chủ
header('Location: /');
exit;
