<?php
// ========================================================
// FILE ĐIỀU HƯỚNG TRUNG TÂM (UNIVERSAL ROUTER & ENTRY POINT)
// vankhateamshop - Cho phép chạy đường link ngắn đẹp chuyên nghiệp
// Ví dụ: shopvankha.com/user/dashboard thay vì /frontend/client/dashboard.php
// Tự động chuyển hướng 301 các link cũ sang link mới
// Hỗ trợ Webhook SePay (/webhook/sepay, /webhook.php)
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rtrim($uri, '/');
if (empty($uri)) {
    $uri = '/';
}

// 1. TỰ ĐỘNG CHUYỂN HƯỚNG 301 CÁC ĐƯỜNG LINK CŨ / THÔ SANG ĐƯỜNG DẪN ĐẸP
$redirectsLegacy = [
    '/frontend/index.php'                  => '/',
    '/frontend'                            => '/',
    '/index.php'                           => '/',
    '/frontend/san-pham.php'               => '/san-pham',
    '/san-pham.php'                        => '/san-pham',
    '/frontend/login.php'                  => '/login',
    '/login.php'                           => '/login',
    '/frontend/register.php'               => '/register',
    '/register.php'                        => '/register',
    '/frontend/docs.php'                   => '/docs',
    '/docs.php'                            => '/docs',
    '/frontend/chinh-sach.php'             => '/chinh-sach',
    '/chinh-sach.php'                      => '/chinh-sach',
    '/frontend/quen-mat-khau.php'          => '/quen-mat-khau',
    '/quen-mat-khau.php'                   => '/quen-mat-khau',
    '/frontend/client/dashboard.php'       => '/user/dashboard',
    '/client/dashboard.php'                => '/user/dashboard',
    '/frontend/client/nap-tien.php'        => '/user/nap-tien',
    '/client/nap-tien.php'                 => '/user/nap-tien',
    '/frontend/client/don-hang.php'        => '/user/don-hang',
    '/client/don-hang.php'                 => '/user/don-hang',
    '/frontend/client/api-docs.php'        => '/user/api-docs',
    '/client/api-docs.php'                 => '/user/api-docs',
    '/frontend/client/bien-dong-so-du.php' => '/user/bien-dong-so-du',
    '/client/bien-dong-so-du.php'          => '/user/bien-dong-so-du',
    '/frontend/client/ho-tro.php'          => '/user/ho-tro',
    '/client/ho-tro.php'                   => '/user/ho-tro',
    '/frontend/client/tai-khoan.php'       => '/user/tai-khoan',
    '/client/tai-khoan.php'                => '/user/tai-khoan',
    '/frontend/client/lich-su-dang-nhap.php'=> '/user/lich-su-dang-nhap',
    '/client/lich-su-dang-nhap.php'        => '/user/lich-su-dang-nhap',
    '/frontend/admin/dashboard.php'        => '/admin/dashboard',
    '/admin/dashboard.php'                 => '/admin/dashboard',
    '/frontend/admin/san-pham.php'         => '/admin/san-pham',
    '/admin/san-pham.php'                  => '/admin/san-pham',
    '/frontend/admin/dai-ly.php'           => '/admin/dai-ly',
    '/admin/dai-ly.php'                    => '/admin/dai-ly',
];

if (isset($redirectsLegacy[$uri])) {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
    header('Location: ' . $redirectsLegacy[$uri] . $queryString, true, 301);
    exit;
}

// 2. BẢNG ÁNH XẠ URL ĐẸP -> TỆP XỬ LÝ THỰC TẾ
$routes = [
    '/'                      => __DIR__ . '/frontend/index.php',
    '/san-pham'              => __DIR__ . '/frontend/san-pham.php',
    '/products'              => __DIR__ . '/frontend/san-pham.php',
    '/docs'                  => __DIR__ . '/frontend/docs.php',
    '/buyer-api'             => __DIR__ . '/frontend/docs.php',
    '/chinh-sach'            => __DIR__ . '/frontend/chinh-sach.php',
    '/login'                 => __DIR__ . '/frontend/login.php',
    '/dang-nhap'             => __DIR__ . '/frontend/login.php',
    '/register'              => __DIR__ . '/frontend/register.php',
    '/dang-ky'               => __DIR__ . '/frontend/register.php',
    '/quen-mat-khau'         => __DIR__ . '/frontend/quen-mat-khau.php',
    '/forgot-password'       => __DIR__ . '/frontend/quen-mat-khau.php',
    '/logout'                => __DIR__ . '/backend/logout.php',
    '/dang-xuat'             => __DIR__ . '/backend/logout.php',

    // Webhooks SePay VietQR (Tự động nhận tiền 24/7)
    '/webhook/sepay'         => __DIR__ . '/backend/webhook_sepay.php',
    '/webhook.php'           => __DIR__ . '/backend/webhook_sepay.php',
    '/api/webhook/sepay'     => __DIR__ . '/backend/webhook_sepay.php',

    // Đường dẫn khách hàng (User Portal - shopvankha.com/user/dashboard)
    '/user'                  => __DIR__ . '/frontend/client/dashboard.php',
    '/user/dashboard'        => __DIR__ . '/frontend/client/dashboard.php',
    '/user/nap-tien'         => __DIR__ . '/frontend/client/nap-tien.php',
    '/user/don-hang'         => __DIR__ . '/frontend/client/don-hang.php',
    '/user/api-docs'         => __DIR__ . '/frontend/client/api-docs.php',
    '/user/api'              => __DIR__ . '/frontend/client/api-docs.php',
    '/user/bien-dong-so-du'  => __DIR__ . '/frontend/client/bien-dong-so-du.php',
    '/user/ho-tro'           => __DIR__ . '/frontend/client/ho-tro.php',
    '/user/tai-khoan'        => __DIR__ . '/frontend/client/tai-khoan.php',
    '/user/lich-su-dang-nhap'=> __DIR__ . '/frontend/client/lich-su-dang-nhap.php',

    // Đường dẫn quản trị (Admin Portal - shopvankha.com/admin/dashboard)
    '/admin'                 => __DIR__ . '/frontend/admin/dashboard.php',
    '/admin/dashboard'       => __DIR__ . '/frontend/admin/dashboard.php',
    '/admin/san-pham'        => __DIR__ . '/frontend/admin/san-pham.php',
    '/admin/don-hang'        => __DIR__ . '/frontend/admin/don-hang.php',
    '/admin/thanh-vien'      => __DIR__ . '/frontend/admin/thanh-vien.php',
    '/admin/dai-ly'          => __DIR__ . '/frontend/admin/dai-ly.php',
    '/admin/api-logs'        => __DIR__ . '/frontend/admin/api-logs.php',
    '/admin/thong-ke'        => __DIR__ . '/frontend/admin/thong-ke.php',
    '/admin/danh-muc'        => __DIR__ . '/frontend/admin/danh-muc.php',
    '/admin/cai-dat'         => __DIR__ . '/frontend/admin/cai-dat.php',
    '/admin/ngan-hang'       => __DIR__ . '/frontend/admin/ngan-hang.php',
    '/admin/nha-cung-cap'    => __DIR__ . '/frontend/admin/nha-cung-cap.php',
    '/admin/ma-giam-gia'     => __DIR__ . '/frontend/admin/ma-giam-gia.php',
    '/admin/ho-tro'          => __DIR__ . '/frontend/admin/ho-tro.php',
    '/admin/thong-bao'       => __DIR__ . '/frontend/admin/thong-bao.php',
    '/admin/nhat-ky'         => __DIR__ . '/frontend/admin/nhat-ky.php',
];

if (isset($routes[$uri]) && file_exists($routes[$uri])) {
    require $routes[$uri];
    exit;
}

// 3. NẾU TRUY CẬP FILE TRỰC TIẾP TRONG BACKEND HOẶC API (VÍ DỤ BACKEND/API.PHP HOẶC API/PARTNER/V1/...)
$directFile = __DIR__ . $uri;
if (file_exists($directFile) && !is_dir($directFile)) {
    if (pathinfo($directFile, PATHINFO_EXTENSION) === 'php') {
        require $directFile;
        exit;
    }
    return false;
}

// Mặc định tải trang chủ
require __DIR__ . '/frontend/index.php';
