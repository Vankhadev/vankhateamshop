<?php
// ========================================================
// FILE ĐIỀU HƯỚNG TRUNG TÂM (UNIVERSAL ROUTER & ENTRY POINT)
// vankhateamshop - Cho phép chạy đường link ngắn đẹp chuyên nghiệp
// Ví dụ: shopvankha.com/user/dashboard thay vì /frontend/client/dashboard.php
// Tự động chuyển hướng 301 các link cũ sang link mới
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
    '/'                      => __DIR__ . '/user/index.php',
    '/san-pham'              => __DIR__ . '/user/san-pham.php',
    '/products'              => __DIR__ . '/user/san-pham.php',
    '/docs'                  => __DIR__ . '/user/docs.php',
    '/buyer-api'             => __DIR__ . '/user/docs.php',
    '/chinh-sach'            => __DIR__ . '/user/chinh-sach.php',
    '/login'                 => __DIR__ . '/user/login.php',
    '/dang-nhap'             => __DIR__ . '/user/login.php',
    '/register'              => __DIR__ . '/user/register.php',
    '/dang-ky'               => __DIR__ . '/user/register.php',
    '/quen-mat-khau'         => __DIR__ . '/user/quen-mat-khau.php',
    '/forgot-password'       => __DIR__ . '/user/quen-mat-khau.php',
    '/logout'                => __DIR__ . '/service/logout.php',
    '/dang-xuat'             => __DIR__ . '/service/logout.php',

    // Đường dẫn khách hàng (User Portal - shopvankha.com/user/dashboard)
    '/user'                  => __DIR__ . '/user/client/dashboard.php',
    '/user/dashboard'        => __DIR__ . '/user/client/dashboard.php',
    '/user/nap-tien'         => __DIR__ . '/user/client/nap-tien.php',
    '/user/don-hang'         => __DIR__ . '/user/client/don-hang.php',
    '/user/api-docs'         => __DIR__ . '/user/client/api-docs.php',
    '/user/api'              => __DIR__ . '/user/client/api-docs.php',
    '/user/bien-dong-so-du'  => __DIR__ . '/user/client/bien-dong-so-du.php',
    '/user/ho-tro'           => __DIR__ . '/user/client/ho-tro.php',
    '/user/tai-khoan'        => __DIR__ . '/user/client/tai-khoan.php',
    '/user/lich-su-dang-nhap'=> __DIR__ . '/user/client/lich-su-dang-nhap.php',

    // Đường dẫn quản trị (Admin Portal - shopvankha.com/admin/dashboard)
    '/admin'                 => __DIR__ . '/user/admin/dashboard.php',
    '/admin/dashboard'       => __DIR__ . '/user/admin/dashboard.php',
    '/admin/san-pham'        => __DIR__ . '/user/admin/san-pham.php',
    '/admin/don-hang'        => __DIR__ . '/user/admin/don-hang.php',
    '/admin/thanh-vien'      => __DIR__ . '/user/admin/thanh-vien.php',
    '/admin/dai-ly'          => __DIR__ . '/user/admin/dai-ly.php',
    '/admin/api-logs'        => __DIR__ . '/user/admin/api-logs.php',
    '/admin/thong-ke'        => __DIR__ . '/user/admin/thong-ke.php',
    '/admin/danh-muc'        => __DIR__ . '/user/admin/danh-muc.php',
    '/admin/cai-dat'         => __DIR__ . '/user/admin/cai-dat.php',
    '/admin/ngan-hang'       => __DIR__ . '/user/admin/ngan-hang.php',
    '/admin/nha-cung-cap'    => __DIR__ . '/user/admin/nha-cung-cap.php',
    '/admin/ma-giam-gia'     => __DIR__ . '/user/admin/ma-giam-gia.php',
    '/admin/ho-tro'          => __DIR__ . '/user/admin/ho-tro.php',
    '/admin/thong-bao'       => __DIR__ . '/user/admin/thong-bao.php',
    '/admin/nhat-ky'         => __DIR__ . '/user/admin/nhat-ky.php',
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
require __DIR__ . '/user/index.php';
