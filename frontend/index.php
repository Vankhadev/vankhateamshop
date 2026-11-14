<?php
// ========================================================
// TRANG CHỦ BÁN HÀNG - VANKHA TEAM SHOP
// Giao diện Dark Mode công nghệ, tích hợp Bảo mật Cloudflare, reCAPTCHA & Google Login
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

// Thiết lập các tiêu đề bảo mật HTTP (Chống Clickjacking, XSS...)
thietLapBaoMatHeaders();

// Kiểm tra trạng thái đăng nhập
$daDangNhap = !empty($_SESSION['user_id']);
$userHienTai = null;

if ($daDangNhap) {
    $stmtUser = $pdo->prepare("SELECT id, name, email, avatar, balance, role FROM `users` WHERE `id` = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $userHienTai = $stmtUser->fetch();
}

// 1. Lấy tất cả danh mục đang kích hoạt từ MySQL
$categories = $pdo->query("SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC")->fetchAll();

// 2. Lấy toàn bộ sản phẩm đang mở bán từ MySQL (Tự động ẩn sản phẩm hết hàng: stock <= 0)
$products = $pdo->query("
    SELECT p.*, c.name AS category_name,
           COALESCE((SELECT m.stock FROM `supplier_product_mappings` m WHERE m.product_id = p.id AND m.is_active = 1 ORDER BY m.priority ASC LIMIT 1), 999) AS stock
    FROM `products` p
    JOIN `categories` c ON p.category_id = c.id
    WHERE p.is_active = 1
    HAVING (stock IS NULL OR stock > 0)
    ORDER BY p.id ASC
")->fetchAll();

// 3. Lấy thông báo ghim nổi bật từ MySQL
$announcements = $pdo->query("SELECT * FROM `announcements` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 3")->fetchAll();

// 4. Lấy dữ liệu Bảng Xếp Hạng đại lý & thành viên uy tín
$topUsers = $pdo->query("
    SELECT u.name, u.email, COALESCE(SUM(o.total_amount), 0) as tong_chi_tieu 
    FROM `users` u
    LEFT JOIN `orders` o ON u.id = o.user_id AND o.status = 'COMPLETED'
    WHERE u.role != 'admin'
    GROUP BY u.id
    ORDER BY tong_chi_tieu DESC
    LIMIT 10
")->fetchAll();
$siteName = layCaiDatHeThong('site_name', SHOP_NAME);
$siteZalo = layCaiDatHeThong('site_contact_zalo', SHOP_ZALO);
$captchaEnabled   = function_exists('layCaiDatHeThong') ? (layCaiDatHeThong('captcha_enabled', '0') === '1') : false;
$captchaProvider  = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('captcha_provider', defined('CAPTCHA_PROVIDER') ? CAPTCHA_PROVIDER : 'turnstile') : (defined('CAPTCHA_PROVIDER') ? CAPTCHA_PROVIDER : 'turnstile');
$captchaSiteKey   = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('captcha_site_key', defined('CAPTCHA_SITE_KEY') ? CAPTCHA_SITE_KEY : '') : (defined('CAPTCHA_SITE_KEY') ? CAPTCHA_SITE_KEY : '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?> - Cổng Cung Cấp API AI & Công Cụ Lập Trình Tự Động</title>
    
    <!-- TỐI ƯU HÓA SEO CHUẨN GOOGLE (SEARCH ENGINE OPTIMIZATION) -->
    <meta name="description" content="<?= htmlspecialchars($siteName) ?> - Đại lý cung cấp API Key AI Agent, Cursor Pro, Claude 3.5 Sonnet, ChatGPT Plus, DeepSeek R1 & V3, phần mềm tự động hóa giao key 24/7 qua VietQR ACB.">
    <meta name="keywords" content="api ai, cursor pro, claude 3.5 sonnet, chatgpt plus, deepseek r1, tungdv, phan mem mmo, api proxy, van kha team, mua api ai, vietqr acb">
    <meta name="author" content="<?= htmlspecialchars($siteName) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= SITE_URL ?>/">

    <!-- THẺ OPEN GRAPH (FACEBOOK, ZALO, TELEGRAM) -->
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($siteName) ?> - Cổng Cung Cấp API AI Tự Động 24/7">
    <meta property="og:description" content="Hệ thống bán API AI lập trình tốc độ cao, độ trễ thấp, kết nối Cursor, Claude, OpenAI với giá đại lý tốt nhất.">
    <meta property="og:url" content="<?= SITE_URL ?>/">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">

    <!-- THẺ TWITTER CARDS -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($siteName) ?> - Đại Lý API AI Uy Tín Hàng Đầu">
    <meta name="twitter:description" content="Quét mã VietQR nhận Key tự động sau 2 giây. Hỗ trợ 24/7.">

    <!-- DỮ LIỆU CẤU TRÚC JSON-LD (SCHEMA.ORG) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "<?= addslashes($siteName) ?>",
      "url": "<?= SITE_URL ?>/",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "<?= SITE_URL ?>/san-pham?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "<?= addslashes($siteName) ?>",
      "url": "<?= SITE_URL ?>/",
      "logo": "<?= SITE_URL ?>/assets/logo.png",
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+84<?= ltrim(preg_replace('/[^0-9]/', '', $siteZalo), '0') ?>",
        "contactType": "customer service",
        "availableLanguage": "Vietnamese"
      }
    }
    </script>
    
    <!-- CSS Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Nạp thư viện CAPTCHA tương ứng theo cấu hình -->
    <?php if ($captchaEnabled && !empty($captchaSiteKey)): ?>
        <?php if ($captchaProvider === 'turnstile'): ?>
            <!-- Cloudflare Turnstile API -->
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <?php else: ?>
            <!-- Google reCAPTCHA v2 API -->
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <?php endif; ?>
    <?php endif; ?>

    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #0B0F19; 
            color: #E5E7EB; 
        }
        .mau-gradient {
            background: linear-gradient(135deg, #60A5FA 0%, #A78BFA 50%, #F472B6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        .animate-marquee-track {
            display: inline-flex;
            white-space: nowrap;
            padding-left: 100%;
            animation: marqueeScroll 25s linear infinite;
        }
        .animate-marquee-track:hover {
            animation-play-state: paused;
        }
    </style>
</head>
<body x-data="ungDungBanHang()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- BANNER THÔNG BÁO TỪ HỆ THỐNG NẾU CÓ (MARQUEE SCROLL TRÊN MOBILE & DESKTOP) -->
    <?php if (!empty($announcements)): ?>
        <div class="bg-gradient-to-r from-blue-900/60 via-indigo-900/60 to-purple-900/60 border-b border-blue-800/80 py-2 px-3 sm:px-4 text-xs overflow-hidden">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
                <div class="flex items-center space-x-2 text-white overflow-hidden flex-1 min-w-0">
                    <span class="px-2 py-0.5 rounded bg-amber-500 text-black font-bold text-[10px] uppercase shrink-0 flex items-center gap-1 shadow">
                        <i class="fas fa-bullhorn"></i> <span>Tin Mới</span>
                    </span>
                    <div class="overflow-hidden relative flex-1 min-w-0 h-5 flex items-center">
                        <div class="animate-marquee-track text-gray-200 font-medium cursor-default">
                            <?php 
                            $noiDungThongBao = [];
                            foreach ($announcements as $tb) {
                                $noiDungThongBao[] = '<span class="inline-flex items-center gap-1.5"><strong class="text-amber-300">' . htmlspecialchars($tb['title']) . ':</strong> ' . htmlspecialchars($tb['content']) . '</span>';
                            }
                            echo implode('<span class="mx-6 text-blue-400 font-black">&bull;&bull;&bull;</span>', $noiDungThongBao);
                            ?>
                        </div>
                    </div>
                </div>
                <a href="/docs" class="text-blue-300 hover:text-white shrink-0 font-semibold text-[11px] ml-1 flex items-center gap-1 whitespace-nowrap">
                    <span>Hướng dẫn</span> &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- BANNER CHÍNH (HERO SECTION) -->
    <section class="relative overflow-hidden py-14 lg:py-20 border-b border-gray-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            
            <div class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full bg-blue-900/30 border border-blue-500/30 text-blue-400 text-xs font-medium mb-6">
                <span class="w-2 h-2 rounded-full bg-green-400 animate-ping"></span>
                <span>Hệ Thống Tự Động Giao Key 24/7 Qua VietQR</span>
            </div>

            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight mb-5 leading-tight">
                Kho Cung Cấp <span class="mau-gradient">API AI Lập Trình</span><br>Tốc Độ Cao & Ổn Định Nhất
            </h1>
            
            <p class="max-w-2xl mx-auto text-sm sm:text-base text-gray-400 mb-8 leading-relaxed">
                Tự động kết nối đa nguồn dự phòng, bảo vệ dữ liệu với Cloudflare WAF, quét mã VietQR nhận Key sau 2 giây. Hỗ trợ đầy đủ Cursor, Claude 3.5 Sonnet, DeepSeek R1 & V3.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-semibold">
                <a href="#san-pham" class="px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold hover:shadow-lg hover:shadow-blue-500/25 transition">
                    <i class="fas fa-bolt mr-1.5"></i> Mua API Tự Động Ngay
                </a>
                <button @click="moPopupKiemTraKey = true" class="px-6 py-3 rounded-xl bg-gray-800 text-gray-300 border border-gray-700 hover:bg-gray-700 hover:text-white transition">
                    <i class="fas fa-magnifying-glass mr-1.5"></i> Kiểm Tra Trạng Thái Key
                </button>
            </div>

            <!-- Cam kết chất lượng -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-12 max-w-4xl mx-auto text-left">
                <div class="bg-gray-900/60 border border-gray-800/80 p-4 rounded-xl flex items-center space-x-3">
                    <div class="text-blue-400 text-2xl"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="text-xs font-bold text-white">Bảo Mật Cloudflare</div>
                        <div class="text-[11px] text-gray-400">Chống DDoS & SSL 100%</div>
                    </div>
                </div>
                <div class="bg-gray-900/60 border border-gray-800/80 p-4 rounded-xl flex items-center space-x-3">
                    <div class="text-green-400 text-2xl"><i class="fas fa-qrcode"></i></div>
                    <div>
                        <div class="text-xs font-bold text-white">VietQR Tự Động</div>
                        <div class="text-[11px] text-gray-400">Giao key tức thì sau 2s</div>
                    </div>
                </div>
                <div class="bg-gray-900/60 border border-gray-800/80 p-4 rounded-xl flex items-center space-x-3">
                    <div class="text-amber-400 text-2xl"><i class="fas fa-network-wired"></i></div>
                    <div>
                        <div class="text-xs font-bold text-white">Đa Nguồn Dự Phòng</div>
                        <div class="text-[11px] text-gray-400">Tự động fallback khi hết hàng</div>
                    </div>
                </div>
                <div class="bg-gray-900/60 border border-gray-800/80 p-4 rounded-xl flex items-center space-x-3">
                    <div class="text-purple-400 text-2xl"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div>
                        <div class="text-xs font-bold text-white">Hoàn Tiền Vào Ví</div>
                        <div class="text-[11px] text-gray-400">Tự hoàn 100% nếu sự cố</div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- KHU VỰC DANH MỤC & SẢN PHẨM -->
    <section id="san-pham" class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        
        <!-- Bộ lọc danh mục dạng thẻ Tab kèm 2 nút điều hướng mũi tên chuyển thẻ trên Mobile & Desktop -->
        <div class="relative w-full max-w-full mb-8">
            <div class="flex items-center gap-2">
                <!-- Nút cuộn sang trái -->
                <button type="button" 
                        @click="$refs.danhMucScroll.scrollBy({ left: -220, behavior: 'smooth' })" 
                        class="p-2 sm:p-2.5 rounded-xl bg-gray-900/90 hover:bg-gray-800 text-gray-400 hover:text-white border border-gray-800 shadow-md shrink-0 flex items-center justify-center transition active:scale-95 z-10"
                        title="Xem danh mục trước">
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>

                <!-- Thanh cuộn ngang danh mục -->
                <div x-ref="danhMucScroll" class="flex-1 flex items-center gap-2 overflow-x-auto py-1 scroll-smooth no-scrollbar">
                    <button @click="danhMucDangChon = 0" 
                            :class="danhMucDangChon === 0 ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/30' : 'bg-gray-900 text-gray-400 hover:text-white border border-gray-800'"
                            class="px-4 py-2 rounded-xl text-xs whitespace-nowrap transition shrink-0">
                        <i class="fas fa-border-all mr-1.5"></i> Tất Cả Gói API
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button @click="danhMucDangChon = <?= $cat['id'] ?>" 
                                :class="danhMucDangChon === <?= $cat['id'] ?> ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/30' : 'bg-gray-900 text-gray-400 hover:text-white border border-gray-800'"
                                class="px-4 py-2 rounded-xl text-xs whitespace-nowrap transition flex items-center gap-1.5 shrink-0">
                            <i class="fas <?= $cat['icon'] ?>"></i> <?= htmlspecialchars($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Nút cuộn sang phải -->
                <button type="button" 
                        @click="$refs.danhMucScroll.scrollBy({ left: 220, behavior: 'smooth' })" 
                        class="p-2 sm:p-2.5 rounded-xl bg-gray-900/90 hover:bg-gray-800 text-gray-400 hover:text-white border border-gray-800 shadow-md shrink-0 flex items-center justify-center transition active:scale-95 z-10"
                        title="Xem thêm danh mục tiếp theo">
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>

        <!-- LƯỚI DANH SÁCH CÁC GÓI SẢN PHẨM -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <?php foreach ($products as $p): 
                $features = json_decode($p['features'] ?? '[]', true) ?: [];
            ?>
                <div x-show="danhMucDangChon === 0 || danhMucDangChon === <?= $p['category_id'] ?>" 
                     class="bg-[#111827] border border-gray-800/90 hover:border-blue-500/50 rounded-2xl p-5 flex flex-col justify-between transition duration-200 hover:-translate-y-1 shadow-lg hover:shadow-blue-500/10 relative group">
                    
                    <?php if (!empty($p['badge'])): ?>
                        <div class="absolute top-4 right-4 bg-gradient-to-r from-red-600 to-amber-600 text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow">
                            <?= htmlspecialchars($p['badge']) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <!-- Ảnh gói sản phẩm & Tên sản phẩm -->
                        <div class="flex items-center gap-3 mb-2.5">
                            <?php if (!empty($p['image'])): ?>
                                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-11 h-11 rounded-xl object-contain border border-gray-700 bg-gray-900/90 p-1 shrink-0">
                            <?php else: ?>
                                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-blue-600/30 to-indigo-600/30 border border-blue-500/30 flex items-center justify-center text-blue-400 text-lg font-bold shrink-0">
                                    <i class="fas <?= str_contains(strtolower($p['name']), 'cursor') ? 'fa-bolt text-amber-400' : (str_contains(strtolower($p['name']), 'claude') ? 'fa-brain text-orange-400' : (str_contains(strtolower($p['name']), 'deepseek') ? 'fa-magnifying-glass text-blue-400' : 'fa-code text-green-400')) ?>"></i>
                                </div>
                            <?php endif; ?>
                            <div class="overflow-hidden">
                                <div class="text-[10px] font-bold text-blue-400 uppercase tracking-wider">
                                    <?= htmlspecialchars($p['category_name']) ?>
                                </div>
                                <a href="/san-pham/<?= htmlspecialchars($p['slug']) ?>" class="block">
                                    <h3 class="text-sm font-bold text-white group-hover:text-blue-400 transition leading-tight truncate" title="<?= htmlspecialchars($p['name']) ?>">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </h3>
                                </a>
                            </div>
                        </div>

                        <!-- Thông số Request / Giới hạn -->
                        <?php if (!empty($p['request_count'])): ?>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-800/90 text-amber-300 font-mono text-xs font-semibold mb-3 border border-gray-700/60">
                                <i class="fas fa-bolt text-amber-400"></i>
                                <span><?= htmlspecialchars($p['request_count']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Giá bán -->
                        <div class="mb-4">
                            <div class="flex items-baseline gap-2">
                                <span class="text-2xl font-black text-white"><?= number_format($p['price'], 0, ',', '.') ?>đ</span>
                                <?php if ($p['original_price'] && $p['original_price'] > $p['price']): ?>
                                    <span class="text-xs text-gray-500 line-through"><?= number_format($p['original_price'], 0, ',', '.') ?>đ</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[11px] text-gray-400">Thời hạn sử dụng: <?= $p['duration_days'] ?> ngày</span>
                        </div>

                        <!-- Danh sách tính năng của gói -->
                        <ul class="space-y-1.5 text-xs text-gray-300 mb-6 border-t border-gray-800/80 pt-3">
                            <?php foreach ($features as $f): ?>
                                <li class="flex items-center gap-2">
                                    <i class="fas fa-check text-green-400 text-[10px]"></i>
                                    <span><?= htmlspecialchars($f) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Nút Chi Tiết & Mua Hàng -->
                    <div class="grid grid-cols-2 gap-2 mt-auto">
                        <a href="/san-pham/<?= htmlspecialchars($p['slug']) ?>" class="py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 font-bold text-xs transition flex items-center justify-center gap-1.5 border border-gray-700">
                            <i class="fas fa-eye text-blue-400"></i> Chi Tiết
                        </a>
                        <button @click="moThanhToan(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['request_count'] ?? '') ?>', <?= $p['price'] ?>)" 
                                class="py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-md shadow-blue-600/30 flex items-center justify-center gap-1.5">
                            <i class="fas fa-bolt"></i> Mua Ngay
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

    <!-- POPUP THANH TOÁN VIETQR TỰ ĐỘNG (MODAL) -->
    <div x-show="moPopupThanhToan" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-2xl sm:rounded-3xl max-w-md w-full p-4 sm:p-5 relative shadow-2xl overflow-y-auto max-h-[92vh] my-auto">
            
            <!-- HEADER MODAL & NÚT ĐÓNG TÁCH BIỆT KHÔNG ĐÈ LÊN THẺ NẰM BÊN TRONG -->
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-white tracking-wide" x-text="daThanhToan ? 'Thanh Toán Thành Công' : (daTaoDon ? 'Thanh Toán VietQR Tự Động' : 'Thông Tin Đơn Hàng')"></h3>
                </div>
                <button @click="dongThanhToan()" type="button" class="w-8 h-8 rounded-full bg-gray-800/90 hover:bg-gray-700 text-gray-400 hover:text-white flex items-center justify-center transition border border-gray-700/50">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- BƯỚC 1: NHẬP THÔNG TIN & XÁC THỰC BẢO MẬT CAPTCHA -->
            <div x-show="!daTaoDon && !daThanhToan">
                <div class="text-center mb-4">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Thông Tin Đơn Hàng</span>
                    <h3 class="text-base font-bold text-white mt-1" x-text="tenSanPhamChon"></h3>
                    <p class="text-sm font-black text-green-400 mt-1" x-text="dinhDangTien(giaTienChon)"></p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Email nhận Key / Tài khoản (Bắt buộc):</label>
                        <input type="email" x-model="emailKhachHang" placeholder="nhap-email-cua-ban@gmail.com" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <!-- Ô NHẬP MÃ GIẢM GIÁ (COUPON) -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Mã giảm giá (Nếu có):</label>
                        <div class="flex space-x-2">
                            <input type="text" x-model="maCoupon" placeholder="Nhập mã (VD: CHAOMUNG10)" class="flex-1 bg-gray-900 border border-gray-700 rounded-xl px-3 py-1.5 text-xs text-white uppercase focus:outline-none focus:border-amber-500">
                            <button type="button" @click="apDungCoupon()" :disabled="dangKiemTraCoupon || !maCoupon" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl disabled:opacity-50 transition">
                                <span x-show="!dangKiemTraCoupon"><i class="fas fa-tag mr-1"></i> Áp Dụng</span>
                                <span x-show="dangKiemTraCoupon"><i class="fas fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                        <div x-show="thongBaoCoupon" class="mt-1 text-[11px] font-semibold" :class="couponHopLe ? 'text-green-400' : 'text-red-400'" x-text="thongBaoCoupon"></div>
                    </div>

                    <!-- WIDGET XÁC THỰC BẢO MẬT CAPTCHA -->
                    <?php if ($captchaEnabled && !empty($captchaSiteKey)): ?>
                        <div class="pt-1 flex flex-col items-center">
                            <label class="block text-[11px] text-gray-400 mb-2">Bảo mật hệ thống chống Bot:</label>
                            <?php if ($captchaProvider === 'turnstile'): ?>
                                <!-- Cloudflare Turnstile -->
                                <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($captchaSiteKey) ?>" data-theme="dark" data-callback="onCaptchaXong"></div>
                            <?php else: ?>
                                <!-- Google reCAPTCHA v2 -->
                                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($captchaSiteKey) ?>" data-theme="dark" data-callback="onCaptchaXong"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div x-show="thongBaoLoiPopup" class="p-2.5 rounded-lg bg-red-950/70 border border-red-800 text-red-300 text-xs font-semibold text-center" x-text="thongBaoLoiPopup"></div>

                    <?php if ($daDangNhap): ?>
                        <!-- Nút thanh toán trực tiếp bằng số dư ví -->
                        <button @click="thanhToanBangVi()" :disabled="dangTaoDon" class="w-full py-2.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-bold text-xs transition duration-200 shadow-md shadow-green-600/30 flex items-center justify-center gap-2">
                            <i class="fas fa-bolt text-amber-300"></i> Thanh Toán Bằng Số Dư Ví (Hiện có: <?= number_format($userHienTai['balance'] ?? 0, 0, ',', '.') ?>đ)
                        </button>

                        <div class="relative flex items-center justify-center my-1">
                            <div class="border-t border-gray-800 w-full"></div>
                            <span class="bg-[#111827] px-3 text-[10px] text-gray-500 uppercase font-semibold absolute">hoặc quét mã ngân hàng</span>
                        </div>
                    <?php endif; ?>

                    <button @click="xacNhanTaoDon()" :disabled="dangTaoDon" class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-md shadow-blue-600/30 flex items-center justify-center gap-2">
                        <span x-show="!dangTaoDon"><i class="fas fa-qrcode mr-1"></i> Tạo Mã VietQR Thanh Toán</span>
                        <span x-show="dangTaoDon"><i class="fas fa-circle-notch fa-spin mr-1"></i> Đang tạo mã...</span>
                    </button>
                </div>
            </div>

            <!-- BƯỚC 2: QUÉT MÃ VIETQR ĐỂ CHUYỂN KHOẢN (GIAO DIỆN TỐI ƯU MOBILE GỌN ĐẸP) -->
            <div x-show="daTaoDon && !daThanhToan" class="space-y-3">
                
                <!-- Khung Thẻ VietQR nền trắng sang trọng chuẩn mẫu ảnh -->
                <div class="bg-white text-gray-900 rounded-2xl p-3.5 sm:p-4 shadow-xl border border-gray-100">
                    
                    <!-- Dòng tiêu đề Ngân hàng ACB + Huy hiệu Tự động 24/7 -->
                    <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-black text-[#005ba6] tracking-tight">ACB</span>
                            <span class="text-xs font-semibold text-gray-700"><?= defined('BANK_FULL_NAME') ? BANK_FULL_NAME : 'Ngân hàng TMCP Á Châu' ?></span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
                            <i class="fas fa-bolt text-[9px]"></i> Tự động 24/7
                        </span>
                    </div>

                    <!-- Khung ảnh QR code VietQR tinh gọn chuẩn mobile -->
                    <div class="border border-gray-200 rounded-xl p-2 bg-white flex flex-col items-center justify-center mb-2.5 shadow-sm">
                        <img :src="linkVietQr" alt="Mã VietQR" class="w-44 sm:w-48 h-auto object-contain mx-auto rounded-lg">
                    </div>

                    <!-- Thông tin tài khoản & nội dung chuyển khoản gọn gàng -->
                    <div class="space-y-2 text-left">
                        
                        <!-- DÒNG SẢN PHẨM & SỐ TIỀN THANH TOÁN -->
                        <div class="flex items-center justify-between p-2 rounded-xl bg-blue-50/80 border border-blue-200/60">
                            <div class="min-w-0 pr-2">
                                <span class="block text-[9px] font-bold text-blue-600 uppercase tracking-wider">SẢN PHẨM</span>
                                <span class="block text-xs font-black text-gray-900 truncate" x-text="tenSanPhamChon"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="block text-[9px] font-bold text-gray-500 uppercase tracking-wider">SỐ TIỀN</span>
                                <span class="block text-sm font-black text-emerald-600 font-mono" x-text="dinhDangTien(giaTienChon)"></span>
                            </div>
                        </div>

                        <!-- SỐ TÀI KHOẢN + CHỦ TÀI KHOẢN + NÚT SAO CHÉP -->
                        <div class="border border-gray-200 rounded-xl p-2.5 bg-gray-50/80 flex items-center justify-between">
                            <div>
                                <span class="block text-[9px] font-bold text-gray-400 uppercase tracking-wider">SỐ TÀI KHOẢN (<?= BANK_ACCOUNT_NAME ?>)</span>
                                <span class="block text-sm font-black text-[#0284c7] font-mono mt-0.5"><?= BANK_ACCOUNT_NO ?></span>
                            </div>
                            <button @click="saoChep('<?= BANK_ACCOUNT_NO ?>')" type="button" class="px-2.5 py-1.5 rounded-lg bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy text-[11px]"></i> Sao chép
                            </button>
                        </div>

                        <!-- NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC) - VIỀN NÉT ĐỨT -->
                        <div class="border-2 border-dashed border-[#0284c7] bg-[#f0f9ff] rounded-xl p-2.5 flex items-center justify-between">
                            <div>
                                <span class="block text-[9px] font-extrabold text-rose-500 uppercase tracking-wider">NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC)</span>
                                <span class="block text-base font-black text-rose-600 font-mono tracking-wider mt-0.5" x-text="maDonHang"></span>
                            </div>
                            <button @click="saoChep(maDonHang)" type="button" class="px-2.5 py-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy text-[11px]"></i> Sao chép
                            </button>
                        </div>

                    </div>
                </div>

                <!-- DÒNG TRẠNG THÁI TỰ ĐỘNG KIỂM TRA SAU 5S & NÚT KIỂM TRA NGAY -->
                <div class="pt-1 space-y-2">
                    <div class="flex items-center justify-center gap-2 text-xs text-blue-400 font-semibold bg-gray-900/90 border border-gray-800 rounded-xl py-2 px-3">
                        <i class="fas fa-circle-notch fa-spin text-blue-400"></i>
                        <span>Tự động kiểm tra sau: <strong class="text-amber-400 font-mono text-sm" x-text="demNguoc5s + 's'">5s</strong></span>
                    </div>

                    <!-- NÚT KIỂM TRA NGAY -->
                    <button @click="kiemTraThanhToanNgay(false)" :disabled="dangKiemTraNgay" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-bold text-xs transition shadow-lg shadow-green-600/30 flex items-center justify-center gap-2">
                        <i class="fas fa-magnifying-glass" :class="dangKiemTraNgay ? 'fa-spin' : ''"></i>
                        <span x-text="dangKiemTraNgay ? 'Đang kiểm tra với ngân hàng...' : 'Kiểm Tra Ngay'">Kiểm Tra Ngay</span>
                    </button>
                </div>
            </div>

            <!-- BƯỚC 3: ĐÃ NHẬN TIỀN -> TRẢ VỀ KEY NGAY LẬP TỨC -->
            <div x-show="daThanhToan" class="text-center py-4">
                <div class="w-14 h-14 bg-green-500/20 text-green-400 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-1">Thanh Toán Thành Công!</h3>
                <p class="text-xs text-gray-400 mb-4">Mã đơn hàng: <span class="font-mono text-white" x-text="maDonHang"></span></p>

                <div class="bg-gray-900 border border-green-500/40 rounded-xl p-4 text-left mb-3">
                    <p class="text-[11px] font-bold text-green-400 uppercase mb-2">Thông tin API Key / Tài khoản của bạn:</p>
                    <div class="bg-black/80 p-2.5 rounded-lg font-mono text-xs text-amber-300 break-all select-all flex justify-between items-center border border-gray-800">
                        <span x-text="keyDaTraVe"></span>
                        <button @click="saoChep(keyDaTraVe)" class="ml-2 text-gray-400 hover:text-white" title="Sao chép Key"><i class="fas fa-copy"></i></button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Hệ thống đã gửi bản sao lưu về email: <span class="text-white" x-text="emailKhachHang"></span></p>
                </div>

                <!-- Base URL Kết Nối API Chuẩn OpenAI -->
                <div class="bg-gray-900 border border-blue-500/40 rounded-xl p-3 text-left mb-4 space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-blue-400 font-bold uppercase text-[10px] flex items-center gap-1">
                            <i class="fas fa-link"></i> Base URL (OpenAI Compatible)
                        </span>
                        <button @click="saoChep('https://tiemai.io.vn/v1')" class="text-blue-400 hover:text-white text-[10px] font-bold flex items-center gap-1">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </div>
                    <code class="text-white font-mono text-xs bg-black/80 px-2.5 py-1.5 rounded-lg border border-gray-800 block select-all">https://tiemai.io.vn/v1</code>
                    <p class="text-[10px] text-gray-400">Chat Endpoint: <code class="text-gray-300">https://tiemai.io.vn/v1/chat/completions</code></p>
                </div>

                <button @click="dongThanhToan()" class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    Hoàn Tất & Đóng
                </button>
            </div>

        </div>
    </div>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- SCRIPT XỬ LÝ SỰ KIỆN GIAO DIỆN -->
    <script>
        var globalCaptchaToken = '';
        function onCaptchaXong(token) {
            globalCaptchaToken = token;
        }

        function ungDungBanHang() {
            return {
                danhMucDangChon: 0,
                moPopupThanhToan: false,
                moPopupKiemTraKey: false,
                moPopupBangXepHang: false,
                moPopupStatusModel: false,
                idSanPhamChon: 0,
                tenSanPhamChon: '',
                moTaGoiChon: '',
                giaTienChon: 0,
                maDonHang: '',
                linkVietQr: '',
                emailKhachHang: '<?= !empty($userHienTai['email']) ? htmlspecialchars($userHienTai['email']) : '' ?>',
                daTaoDon: false,
                dangTaoDon: false,
                daThanhToan: false,
                keyDaTraVe: '',
                thongBaoLoiPopup: '',
                oNhapKey: '',
                ketQuaTraCuu: null,
                timerPolling: null,

                maCoupon: '',
                dangKiemTraCoupon: false,
                thongBaoCoupon: '',
                couponHopLe: false,
                giamGiaCoupon: 0,

                dinhDangTien(soTien) {
                    return soTien ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(soTien) : '0 VNĐ';
                },

                // Khách chọn gói
                moThanhToan(idSanPham, tenSp, moTa, gia) {
                    this.idSanPhamChon = idSanPham;
                    this.tenSanPhamChon = tenSp;
                    this.moTaGoiChon = moTa;
                    this.giaTienChon = gia;
                    this.daTaoDon = false;
                    this.daThanhToan = false;
                    this.thongBaoLoiPopup = '';
                    this.maCoupon = '';
                    this.thongBaoCoupon = '';
                    this.couponHopLe = false;
                    this.giamGiaCoupon = 0;
                    this.moPopupThanhToan = true;
                },

                // Áp dụng mã giảm giá
                async apDungCoupon() {
                    if (!this.maCoupon) return;
                    this.dangKiemTraCoupon = true;
                    this.thongBaoCoupon = '';

                    const fd = new FormData();
                    fd.append('code', this.maCoupon);
                    fd.append('product_id', this.idSanPhamChon);

                    try {
                        const res = await fetch('/backend/api.php?action=kiem_tra_coupon', {
                            method: 'POST',
                            body: fd
                        });
                        const d = await res.json();
                        if (d.thanh_cong) {
                            this.couponHopLe = true;
                            this.giamGiaCoupon = d.discount_amount;
                            this.giaTienChon = d.final_price;
                            this.thongBaoCoupon = d.thong_bao;
                        } else {
                            this.couponHopLe = false;
                            this.thongBaoCoupon = d.thong_bao;
                        }
                    } catch(e) {
                        this.thongBaoCoupon = 'Lỗi kiểm tra mã giảm giá!';
                    } finally {
                        this.dangKiemTraCoupon = false;
                    }
                },

                // Bấm xác nhận tạo đơn hàng & lấy mã VietQR
                xacNhanTaoDon() {
                    if (!this.emailKhachHang || !this.emailKhachHang.includes('@')) {
                        this.thongBaoLoiPopup = 'Vui lòng nhập địa chỉ Email hợp lệ để nhận Key!';
                        return;
                    }

                    this.dangTaoDon = true;
                    this.thongBaoLoiPopup = '';

                    const formData = new FormData();
                    formData.append('action', 'tao_don_hang');
                    formData.append('product_id', this.idSanPhamChon);
                    formData.append('email', this.emailKhachHang);
                    formData.append('coupon_code', this.maCoupon);
                    let token = globalCaptchaToken;
                    if (!token) {
                        const cfInput = document.querySelector('[name="cf-turnstile-response"]');
                        if (cfInput && cfInput.value) token = cfInput.value;
                        const gInput = document.querySelector('[name="g-recaptcha-response"]');
                        if (gInput && gInput.value) token = gInput.value;
                    }
                    formData.append('captcha_token', token || '');

                    fetch('/backend/api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangTaoDon = false;
                        if (data.thanh_cong) {
                            this.maDonHang = data.ma_don_hang;
                            this.linkVietQr = data.link_vietqr;
                            this.daTaoDon = true;
                            // Bắt đầu kiểm tra trạng thái thanh toán tự động mỗi 3 giây
                            this.batDauKiemTraThanhToan();
                        } else {
                            this.thongBaoLoiPopup = data.thong_bao || 'Không thể tạo đơn hàng. Vui lòng thử lại!';
                        }
                    })
                    .catch(err => {
                        this.dangTaoDon = false;
                        this.thongBaoLoiPopup = 'Lỗi kết nối máy chủ tạo đơn!';
                    });
                },

                // Thanh toán trực tiếp bằng số dư ví người dùng
                thanhToanBangVi() {
                    this.dangTaoDon = true;
                    this.thongBaoLoiPopup = '';

                    const formData = new FormData();
                    formData.append('action', 'mua_bang_so_du');
                    formData.append('product_id', this.idSanPhamChon);
                    formData.append('coupon_code', this.maCoupon);

                    fetch('/backend/api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangTaoDon = false;
                        if (data.thanh_cong) {
                            this.maDonHang = data.ma_don_hang;
                            this.daThanhToan = true;
                            if (data.tai_khoan && data.tai_khoan.length > 0) {
                                this.keyDaTraVe = data.tai_khoan.join("\n");
                            } else {
                                this.keyDaTraVe = 'sk-vankha-' + this.maDonHang + '-completed';
                            }
                        } else {
                            this.thongBaoLoiPopup = data.thong_bao || 'Lỗi thanh toán ví!';
                        }
                    })
                    .catch(() => {
                        this.dangTaoDon = false;
                        this.thongBaoLoiPopup = 'Lỗi kết nối máy chủ!';
                    });
                },

                // Quản lý đếm ngược 5s & kiểm tra thanh toán
                demNguoc5s: 5,
                dangKiemTraNgay: false,
                timerDemNguoc: null,

                // Lắng nghe ngân hàng chuyển khoản tự động
                batDauKiemTraThanhToan() {
                    if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                    this.demNguoc5s = 5;

                    this.timerDemNguoc = setInterval(() => {
                        if (!this.maDonHang || this.daThanhToan) {
                            clearInterval(this.timerDemNguoc);
                            return;
                        }

                        this.demNguoc5s--;
                        if (this.demNguoc5s <= 0) {
                            this.demNguoc5s = 5;
                            this.kiemTraThanhToanNgay(true); // Tự động kiểm tra sau mỗi 5s
                        }
                    }, 1000);
                },

                // Nút kiểm tra ngay (hoặc tự động sau 5s)
                async kiemTraThanhToanNgay(isAuto = false) {
                    if (!this.maDonHang || this.daThanhToan) return;
                    this.dangKiemTraNgay = true;

                    try {
                        let res = await fetch(`/backend/api.php?action=kiem_tra_trang_thai_don&order_code=${this.maDonHang}`);
                        let data = await res.json();
                        if (data.thanh_cong && data.da_thanh_toan) {
                            if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                            this.daThanhToan = true;
                            if (data.tai_khoan && data.tai_khoan.length > 0) {
                                this.keyDaTraVe = data.tai_khoan.join("\n");
                            } else {
                                this.keyDaTraVe = 'sk-vankha-' + this.maDonHang + '-completed';
                            }
                        } else if (!isAuto) {
                            alert('Hệ thống chưa nhận được tiền từ ngân hàng. Vui lòng kiểm tra lại nội dung chuyển khoản hoặc thử lại sau vài giây!');
                        }
                    } catch (e) {
                        if (!isAuto) alert('Lỗi kết nối kiểm tra giao dịch!');
                    } finally {
                        this.dangKiemTraNgay = false;
                    }
                },

                dongThanhToan() {
                    this.moPopupThanhToan = false;
                    if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                },

                // Kiểm tra số lượt dùng của key
                kiemTraKey() {
                    if (!this.oNhapKey) {
                        alert('Vui lòng nhập mã API Key cần kiểm tra!');
                        return;
                    }
                    fetch('/backend/api.php?action=tra_cuu_key&key=' + encodeURIComponent(this.oNhapKey))
                        .then(res => res.json())
                        .then(data => {
                            this.ketQuaTraCuu = data;
                        });
                },

                saoChep(noiDung) {
                    if (!noiDung) return;
                    navigator.clipboard.writeText(noiDung).then(() => {
                        alert('Đã sao chép vào bộ nhớ tạm: ' + noiDung);
                    });
                }
            };
        }
    </script>

</body>
</html>
