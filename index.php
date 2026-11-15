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

// 1.5. CỔNG KẾT NỐI API GATEWAY CHUẨN OPENAI (/v1/chat/completions, /v1/models, /v1/usage, /v1/key/check, /chat/completions...)
if (str_starts_with($uri, '/v1') || str_starts_with($uri, '/chat') || str_starts_with($uri, '/messages') || str_starts_with($uri, '/models') || str_starts_with($uri, '/completions')) {
    require __DIR__ . '/backend/proxy_api.php';
    exit;
}

// 2. BẢNG ÁNH XẠ URL ĐẸP -> TỆP XỬ LÝ THỰC TẾ
$routes = [
    '/'                      => __DIR__ . '/frontend/index.php',
    '/san-pham'              => __DIR__ . '/frontend/san-pham.php',
    '/products'              => __DIR__ . '/frontend/san-pham.php',
    '/phan-mem'              => __DIR__ . '/frontend/phan-mem.php',
    '/software'              => __DIR__ . '/frontend/phan-mem.php',
    '/tra-cuu-key'           => __DIR__ . '/frontend/tra-cuu-key.php',
    '/kiem-tra-key'          => __DIR__ . '/frontend/tra-cuu-key.php',
    '/nap-credit'            => __DIR__ . '/frontend/nap-credit.php',
    '/nap-credit-api'        => __DIR__ . '/frontend/nap-credit.php',
    '/bang-xep-hang'         => __DIR__ . '/frontend/bang-xep-hang.php',
    '/xep-hang'              => __DIR__ . '/frontend/bang-xep-hang.php',
    // /docs là tài liệu tích hợp extension; /buyer-api là tài liệu API đại lý lấy sản phẩm.
    '/docs'                  => __DIR__ . '/frontend/api-guide.php',
    '/docs.php'              => __DIR__ . '/frontend/api-guide.php',
    '/api-guide'             => __DIR__ . '/frontend/api-guide.php',
    '/huong-dan-api'         => __DIR__ . '/frontend/api-guide.php',
    '/buyer-api'             => __DIR__ . '/frontend/docs.php',
    '/bang-gia-model'        => __DIR__ . '/frontend/bang-gia-model.php',
    '/models-pricing'        => __DIR__ . '/frontend/bang-gia-model.php',
    // Trung tâm chính sách pháp lý (mỗi chính sách 1 trang riêng)
    '/dieu-khoan-su-dung'    => __DIR__ . '/frontend/dieu-khoan-su-dung.php',
    '/dieu-khoan'            => __DIR__ . '/frontend/dieu-khoan-su-dung.php',
    '/quy-che-hoat-dong'     => __DIR__ . '/frontend/quy-che-hoat-dong.php',
    '/chinh-sach-thanh-toan' => __DIR__ . '/frontend/chinh-sach-thanh-toan.php',
    '/chinh-sach-cung-cap-san-pham' => __DIR__ . '/frontend/chinh-sach-cung-cap-san-pham.php',
    '/chinh-sach-cung-cap'   => __DIR__ . '/frontend/chinh-sach-cung-cap-san-pham.php',
    '/bao-hanh-hoan-tien'    => __DIR__ . '/frontend/bao-hanh-hoan-tien.php',
    '/chinh-sach-hoan-tien'  => __DIR__ . '/frontend/bao-hanh-hoan-tien.php',
    '/dieu-khoan-dai-ly'     => __DIR__ . '/frontend/dieu-khoan-dai-ly.php',
    '/chinh-sach-bao-mat'    => __DIR__ . '/frontend/chinh-sach-bao-mat.php',
    '/chinh-sach'            => __DIR__ . '/frontend/dieu-khoan-su-dung.php',
    '/login'                 => __DIR__ . '/frontend/login.php',
    '/dang-nhap'             => __DIR__ . '/frontend/login.php',
    '/register'              => __DIR__ . '/frontend/register.php',
    '/dang-ky'               => __DIR__ . '/frontend/register.php',
    '/quen-mat-khau'         => __DIR__ . '/frontend/quen-mat-khau.php',
    '/forgot-password'       => __DIR__ . '/frontend/quen-mat-khau.php',
    '/logout'                => __DIR__ . '/backend/logout.php',
    '/dang-xuat'             => __DIR__ . '/backend/logout.php',

    // Sơ đồ trang web & Tệp chỉ mục SEO động theo Domain VPS
    '/sitemap.xml'           => __DIR__ . '/sitemap.php',
    '/robots.txt'            => __DIR__ . '/robots.php',

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
    '/admin/bien-dong-so-du' => __DIR__ . '/frontend/admin/bien-dong-so-du.php',
    '/admin/dai-ly'          => __DIR__ . '/frontend/admin/dai-ly.php',
    '/admin/api-logs'        => __DIR__ . '/frontend/admin/api-logs.php',
    '/admin/thong-ke'        => __DIR__ . '/frontend/admin/thong-ke.php',
    '/admin/danh-muc'        => __DIR__ . '/frontend/admin/danh-muc.php',
    '/admin/cai-dat'         => __DIR__ . '/frontend/admin/cai-dat.php',
    '/admin/ngan-hang'       => __DIR__ . '/frontend/admin/ngan-hang.php',
    '/admin/nha-cung-cap'    => __DIR__ . '/frontend/admin/nha-cung-cap.php',
    '/admin/ma-giam-gia'     => __DIR__ . '/frontend/admin/ma-giam-gia.php',
    '/chi-tiet-san-pham'     => __DIR__ . '/frontend/chi-tiet-san-pham.php',
    '/admin/ho-tro'          => __DIR__ . '/frontend/admin/ho-tro.php',
    '/admin/thong-bao'       => __DIR__ . '/frontend/admin/thong-bao.php',
    '/admin/nhat-ky'         => __DIR__ . '/frontend/admin/nhat-ky.php',
];

if (isset($routes[$uri]) && file_exists($routes[$uri])) {
    require $routes[$uri];
    exit;
}

// 3. ĐƯỜNG DẪN ĐỘNG CHI TIẾT SẢN PHẨM HOẶC DANH MỤC: /san-pham/{slug} HOẶC /san-pham/{category}/{slug}
if (preg_match('#^/san-pham/([^/]+)(?:/([^/]+))?$#', $uri, $matches)) {
    require_once __DIR__ . '/backend/config.php';
    require_once __DIR__ . '/backend/db.php';

    if (!empty($matches[2])) {
        // Có cả 2 phần: /san-pham/{category_slug}/{product_slug}
        $_GET['category_slug'] = $matches[1];
        $_GET['slug'] = $matches[2];
        require __DIR__ . '/frontend/chi-tiet-san-pham.php';
        exit;
    } else {
        $singleSlug = $matches[1];

        // 1. Kiểm tra xem có phải là Sản phẩm không (ưu tiên sản phẩm)
        $stmtP = $pdo->prepare("SELECT id FROM `products` WHERE (`slug` = ? OR `sku` = ?) AND `is_active` = 1 LIMIT 1");
        $stmtP->execute([$singleSlug, $singleSlug]);
        if ($stmtP->fetch()) {
            $_GET['slug'] = $singleSlug;
            require __DIR__ . '/frontend/chi-tiet-san-pham.php';
            exit;
        }

        // 2. Kiểm tra xem có phải là Danh mục không (ví dụ /san-pham/cursor, /san-pham/claude, /san-pham/glm)
        $lookupCatSlug = $singleSlug;
        if ($lookupCatSlug === 'glm') {
            $lookupCatSlug = 'zhipu';
        }
        $stmtC = $pdo->prepare("SELECT id FROM `categories` WHERE `slug` = ? AND `is_active` = 1 LIMIT 1");
        $stmtC->execute([$lookupCatSlug]);
        $catId = $stmtC->fetchColumn();
        if ($catId) {
            $_GET['category_id'] = (int)$catId;
            $_GET['category_slug'] = $lookupCatSlug;
            require __DIR__ . '/frontend/san-pham.php';
            exit;
        }

        // 3. Nếu không tìm thấy, để chi-tiet-san-pham xử lý chuyển tiếp an toàn
        $_GET['slug'] = $singleSlug;
        require __DIR__ . '/frontend/chi-tiet-san-pham.php';
        exit;
    }
}

// 4. NẾU TRUY CẬP FILE TRỰC TIẾP TRONG BACKEND HOẶC API (VÍ DỤ BACKEND/API.PHP HOẶC API/PARTNER/V1/...)
$directFile = __DIR__ . $uri;
if (file_exists($directFile) && !is_dir($directFile)) {
    if (pathinfo($directFile, PATHINFO_EXTENSION) === 'php') {
        require $directFile;
        exit;
    }
    return false;
}

// Không nuốt route sai thành trang chủ: trả HTTP 404 để tránh soft-404 và index URL rác.
http_response_code(404);
require __DIR__ . '/frontend/404.php';
