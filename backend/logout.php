<?php
// ========================================================
// ĐĂNG XUẤT KHỎI HỆ THỐNG (LOGOUT)
// vankhateamshop - Xóa phiên đăng nhập người dùng an toàn
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Các liên kết cũ dùng GET không được tự động đăng xuất; yêu cầu xác nhận bằng POST + CSRF.
    $csrfToken = htmlspecialchars(taoCsrfToken(), ENT_QUOTES, 'UTF-8');
    http_response_code(200);
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Xác nhận đăng xuất</title>
        <style>body{font-family:system-ui,sans-serif;background:#0b0f19;color:#e5e7eb;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px}.box{max-width:420px;width:100%;background:#111827;border:1px solid #374151;border-radius:18px;padding:28px;text-align:center}.actions{display:flex;gap:10px;justify-content:center;margin-top:20px}a,button{border:0;border-radius:10px;padding:10px 16px;font-weight:700;cursor:pointer;text-decoration:none;font-size:14px}a{background:#374151;color:#e5e7eb}button{background:#dc2626;color:#fff}</style>
    </head>
    <body><main class="box"><h1>Xác nhận đăng xuất</h1><p>Bạn có chắc chắn muốn đăng xuất khỏi tài khoản?</p><div class="actions"><a href="/">Hủy</a><form method="post" action="/logout"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><button type="submit">Đăng xuất</button></form></div></main></body>
    </html>
    <?php
    exit;
}

batBuocCsrf();

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
