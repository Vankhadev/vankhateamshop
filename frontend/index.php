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

// 2. Lấy toàn bộ sản phẩm đang mở bán từ MySQL (Sắp xếp theo nhóm danh mục chuẩn và giá tăng dần)
$products = $pdo->query("
    SELECT p.*, c.name AS category_name,
           COALESCE((SELECT m.stock FROM `supplier_product_mappings` m WHERE m.product_id = p.id AND m.is_active = 1 ORDER BY m.priority ASC LIMIT 1), 999) AS stock
    FROM `products` p
    JOIN `categories` c ON p.category_id = c.id
     WHERE p.is_active = 1
     HAVING (stock IS NULL OR stock > 0)
     ORDER BY c.sort_order ASC, c.id ASC, p.price ASC, p.id ASC
")->fetchAll();
// Nếu kho đồng bộ tạm thời trả 0 cho toàn bộ sản phẩm, vẫn hiển thị bảng giá
// để khách biết sản phẩm tồn tại; trạng thái mua hàng sẽ được kiểm tra ở backend.
if (empty($products)) {
    $products = $pdo->query("SELECT p.*, c.name AS category_name, 999 AS stock FROM `products` p JOIN `categories` c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY c.sort_order ASC, c.id ASC, p.price ASC, p.id ASC")->fetchAll();
}

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

        /* Marquee 2 chiều đối xứng chạy vô tận không bao giờ hở khoảng trống */
        @keyframes marqueeScrollLeft {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        @keyframes marqueeScrollRight {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(0); }
        }
        .animate-marquee-left {
            display: flex;
            flex-shrink: 0;
            animation: marqueeScrollLeft 35s linear infinite;
        }
        .animate-marquee-right {
            display: flex;
            flex-shrink: 0;
            animation: marqueeScrollRight 35s linear infinite;
        }
        .marquee-container:hover .animate-marquee-left,
        .marquee-container:hover .animate-marquee-right {
            animation-play-state: paused;
        }

        /* Hiệu ứng nhấp nhô bong bóng Pixel */
        @keyframes pixelFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        .animate-bubble-float { animation: pixelFloat 2.6s ease-in-out infinite; }
        .animate-bubble-float-2 { animation: pixelFloat 2.2s ease-in-out infinite 0.5s; }
        .animate-bubble-float-3 { animation: pixelFloat 2.8s ease-in-out infinite 1s; }

        /* Lửa trại nhấp nháy pixel */
        @keyframes fireFlicker {
            0%, 100% { transform: scale(1) translateY(0); filter: brightness(1); }
            50% { transform: scale(1.08, 0.94) translateY(-2px); filter: brightness(1.25); }
        }
        .animate-fire {
            animation: fireFlicker 0.45s ease-in-out infinite;
            transform-origin: bottom center;
        }

        /* Ngôi sao lấp lánh */
        @keyframes starBlink {
            0%, 100% { opacity: 0.2; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.3); }
        }
        .star-blink-1 { animation: starBlink 3.2s infinite ease-in-out; }
        .star-blink-2 { animation: starBlink 2.4s infinite ease-in-out 0.8s; }
        .star-blink-3 { animation: starBlink 2.8s infinite ease-in-out 1.5s; }

        /* Vầng trăng phát sáng tỏa nhiệt */
        @keyframes orbGlow {
            0%, 100% { box-shadow: 0 0 35px rgba(251, 191, 36, 0.4), 0 0 70px rgba(168, 85, 247, 0.25); }
            50% { box-shadow: 0 0 55px rgba(251, 191, 36, 0.65), 0 0 100px rgba(168, 85, 247, 0.45); }
        }
        .animate-orb-glow { animation: orbGlow 3.5s ease-in-out infinite; }

        /* Tia laser năng lượng bắn từ Mascot lên Quả cầu mặt trăng Hero */
        @keyframes laserPulse {
            0% { stroke-dashoffset: 80; opacity: 0.3; }
            50% { opacity: 0.95; }
            100% { stroke-dashoffset: 0; opacity: 0.3; }
        }
        .animate-laser-pulse {
            stroke-dasharray: 8 6;
            animation: laserPulse 1.8s linear infinite;
        }
        .animate-laser-pulse-fast {
            stroke-dasharray: 6 4;
            animation: laserPulse 1.2s linear infinite;
        }

        /* Hạt năng lượng pixel bắn vút lên cao từ Mascot Hero */
        @keyframes shootUpSparkles {
            0% { transform: translateY(0) scale(0.6); opacity: 0; }
            20% { opacity: 1; }
            80% { opacity: 0.9; }
            100% { transform: translateY(-75px) scale(1.2); opacity: 0; }
        }
        .animate-shoot-spark-1 { animation: shootUpSparkles 2s ease-out infinite; }
        .animate-shoot-spark-2 { animation: shootUpSparkles 2.4s ease-out infinite 0.6s; }
        .animate-shoot-spark-3 { animation: shootUpSparkles 1.8s ease-out infinite 1.2s; }
        .animate-shoot-spark-4 { animation: shootUpSparkles 2.2s ease-out infinite 0.9s; }

        /* Hero Mascots di chuyển chạy qua lại trong khu cắm trại */
        @keyframes heroWalkClaude {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(40px); }
        }
        @keyframes heroWalkDeepSeek {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-35px); }
        }
        @keyframes heroWalkPi {
            0%, 100% { transform: translateX(0) translateY(0); }
            25% { transform: translateX(25px) translateY(-2px); }
            50% { transform: translateX(50px) translateY(0); }
            75% { transform: translateX(25px) translateY(-2px); }
        }
        @keyframes heroWalkCodex {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-30px); }
        }
        @keyframes heroWalkOpenCode {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(35px); }
        }
        .animate-hero-claude { animation: heroWalkClaude 7s ease-in-out infinite; }
        .animate-hero-deepseek { animation: heroWalkDeepSeek 8.5s ease-in-out infinite; }
        .animate-hero-pi { animation: heroWalkPi 6s ease-in-out infinite; }
        .animate-hero-codex { animation: heroWalkCodex 9s ease-in-out infinite; }
        .animate-hero-opencode { animation: heroWalkOpenCode 7.5s ease-in-out infinite; }

        /* Mascot đi tuần tra qua lại trong phòng làm việc dưới (KHÔNG DÙNG scaleX ĐỂ TRÁNH LẬT CHỮ) */
        @keyframes roomPatrol1 {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(95px); }
        }
        @keyframes roomPatrol2 {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-85px); }
        }
        @keyframes roomPatrol3 {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(70px); }
        }
        .animate-room-patrol-1 { animation: roomPatrol1 10s ease-in-out infinite; }
        .animate-room-patrol-2 { animation: roomPatrol2 12s ease-in-out infinite 1s; }
        .animate-room-patrol-3 { animation: roomPatrol3 9s ease-in-out infinite 0.5s; }

        /* Hiệu ứng nhún nhảy bước chân Pixel Sprite Animation */
        @keyframes pixelBob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        .animate-pixel-bob {
            animation: pixelBob 0.65s ease-in-out infinite alternate;
        }

        /* Mascot gõ phím / cắm máy tính liên tục */
        @keyframes mascotTyping {
            0%, 100% { transform: translateY(0); }
            25% { transform: translateY(-1.5px); }
            75% { transform: translateY(0.5px); }
        }
        .animate-typing { animation: mascotTyping 0.35s ease-in-out infinite; }

        /* Cáp kết nối / Tia điện cắm máy tính */
        @keyframes cableSpark {
            0%, 100% { opacity: 0.3; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.1); filter: drop-shadow(0 0 5px #38bdf8); }
        }
        .animate-cable-spark { animation: cableSpark 1s ease-in-out infinite; }
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

    <!-- BANNER CHÍNH (HERO SECTION CHUẨN PIXEL NIGHT CAMPSITE THEO ẢNH 2) -->
    <section class="relative overflow-hidden bg-[#060913] border-b border-gray-800/80 pt-4 sm:pt-14 pb-0">
        <!-- Bầu trời đêm & Các ngôi sao lấp lánh (Twinkle Stars) -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-6 left-[12%] w-1.5 h-1.5 bg-white rounded-full star-blink-1"></div>
            <div class="absolute top-14 left-[28%] w-1 h-1 bg-amber-200 rounded-full star-blink-2"></div>
            <div class="absolute top-8 left-[45%] w-1.5 h-1.5 bg-cyan-200 rounded-full star-blink-3"></div>
            <div class="absolute top-20 left-[62%] w-1 h-1 bg-purple-200 rounded-full star-blink-1"></div>
            <div class="absolute top-10 left-[78%] w-1.5 h-1.5 bg-white rounded-full star-blink-2"></div>
            <div class="absolute top-28 left-[90%] w-1 h-1 bg-amber-100 rounded-full star-blink-3"></div>
            <div class="absolute top-36 left-[8%] w-1 h-1 bg-blue-300 rounded-full star-blink-2"></div>
        </div>

        <!-- Quả cầu phát sáng Glowing Orb (Mặt trăng AI) trên góc phải CHỨA LOGO WEB VÀ NHẬN REQUEST BẮN TỚI -->
        <div class="absolute top-4 right-4 sm:top-6 sm:right-20 z-20 hidden md:block pointer-events-none select-none">
            <div class="relative flex flex-col items-center">
                <!-- Vòng tròn hào quang chứa LOGO WEB -->
                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gradient-to-tr from-amber-400 via-amber-200 to-amber-500 animate-orb-glow flex items-center justify-center p-1 shadow-[0_0_55px_rgba(251,191,36,0.65)]">
                    <div class="w-full h-full rounded-full bg-[#0a0f1d] flex flex-col items-center justify-center border-2 border-amber-300/80 shadow-inner overflow-hidden relative">
                        <!-- Logo Icon VK Gradient -->
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-amber-500 flex items-center justify-center text-white font-black text-xs shadow-md shadow-blue-500/50 mb-0.5">
                            VK
                        </div>
                        <span class="text-[11px] font-black tracking-widest text-amber-300 font-mono">TIỆM AI</span>
                        <span class="text-[7.5px] font-bold text-emerald-400 tracking-wider font-mono">200 OK • API</span>
                        <!-- Vòng xung ánh sáng bên trong -->
                        <div class="absolute inset-0 rounded-full border border-amber-400/30 animate-ping pointer-events-none"></div>
                    </div>
                </div>

                <!-- Tia laser & các REQUEST BAY VÚT TỪ MASCOT LÊN VÒNG TRÒN -->
                <svg class="absolute top-14 right-8 w-[680px] h-[360px] pointer-events-none opacity-90" viewBox="0 0 680 360" fill="none">
                    <!-- Các đường ray dẫn hướng -->
                    <path d="M 40 340 L 610 30" stroke="#F59E0B" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.35"/>
                    <path d="M 160 340 L 610 30" stroke="#38BDF8" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.35"/>
                    <path d="M 280 340 L 610 30" stroke="#EC4899" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.35"/>
                    <path d="M 410 340 L 610 30" stroke="#FBBF24" stroke-width="1.5" stroke-dasharray="3 3" opacity="0.45"/>
                    <path d="M 510 340 L 610 30" stroke="#A855F7" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.35"/>
                    <path d="M 600 340 L 610 30" stroke="#4ADE80" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.35"/>

                    <!-- REQUEST 1 TỪ CLAUDE CODE: POST /chat -->
                    <g>
                        <circle r="4" fill="#F59E0B" filter="drop-shadow(0 0 5px #F59E0B)">
                            <animateMotion dur="2.4s" repeatCount="indefinite" path="M 40 340 L 610 30"/>
                        </circle>
                        <rect width="66" height="15" rx="4" fill="#0f172a" stroke="#F59E0B" stroke-width="1" opacity="0.95" x="-33" y="-22">
                            <animateMotion dur="2.4s" repeatCount="indefinite" path="M 40 340 L 610 30"/>
                        </rect>
                        <text font-size="7.5" fill="#FDE68A" font-weight="bold" font-family="monospace" text-anchor="middle" y="-12">
                            POST /chat
                            <animateMotion dur="2.4s" repeatCount="indefinite" path="M 40 340 L 610 30"/>
                        </text>
                    </g>

                    <!-- REQUEST 2 TỪ DEEPSEEK: GET /models -->
                    <g>
                        <circle r="4" fill="#38BDF8" filter="drop-shadow(0 0 5px #38BDF8)">
                            <animateMotion dur="2.8s" repeatCount="indefinite" path="M 160 340 L 610 30" begin="0.8s"/>
                        </circle>
                        <rect width="68" height="15" rx="4" fill="#0f172a" stroke="#38BDF8" stroke-width="1" opacity="0.95" x="-34" y="-22">
                            <animateMotion dur="2.8s" repeatCount="indefinite" path="M 160 340 L 610 30" begin="0.8s"/>
                        </rect>
                        <text font-size="7.5" fill="#BAE6FD" font-weight="bold" font-family="monospace" text-anchor="middle" y="-12">
                            GET /models
                            <animateMotion dur="2.8s" repeatCount="indefinite" path="M 160 340 L 610 30" begin="0.8s"/>
                        </text>
                    </g>

                    <!-- REQUEST 3 TỪ PI: tokens:400 -->
                    <g>
                        <circle r="4" fill="#EC4899" filter="drop-shadow(0 0 5px #EC4899)">
                            <animateMotion dur="2.2s" repeatCount="indefinite" path="M 280 340 L 610 30" begin="1.4s"/>
                        </circle>
                        <rect width="64" height="15" rx="4" fill="#0f172a" stroke="#EC4899" stroke-width="1" opacity="0.95" x="-32" y="-22">
                            <animateMotion dur="2.2s" repeatCount="indefinite" path="M 280 340 L 610 30" begin="1.4s"/>
                        </rect>
                        <text font-size="7.5" fill="#FBCFE8" font-weight="bold" font-family="monospace" text-anchor="middle" y="-12">
                            tokens:400
                            <animateMotion dur="2.2s" repeatCount="indefinite" path="M 280 340 L 610 30" begin="1.4s"/>
                        </text>
                    </g>

                    <!-- REQUEST 4 TỪ CODEX: 200 OK -->
                    <g>
                        <circle r="4" fill="#A855F7" filter="drop-shadow(0 0 5px #A855F7)">
                            <animateMotion dur="2.6s" repeatCount="indefinite" path="M 510 340 L 610 30" begin="0.4s"/>
                        </circle>
                        <rect width="52" height="15" rx="4" fill="#0f172a" stroke="#A855F7" stroke-width="1" opacity="0.95" x="-26" y="-22">
                            <animateMotion dur="2.6s" repeatCount="indefinite" path="M 510 340 L 610 30" begin="0.4s"/>
                        </rect>
                        <text font-size="7.5" fill="#E9D5FF" font-weight="bold" font-family="monospace" text-anchor="middle" y="-12">
                            200 OK
                            <animateMotion dur="2.6s" repeatCount="indefinite" path="M 510 340 L 610 30" begin="0.4s"/>
                        </text>
                    </g>

                    <!-- REQUEST 5 TỪ OPENCODE: Bearer vk_... -->
                    <g>
                        <circle r="4" fill="#4ADE80" filter="drop-shadow(0 0 5px #4ADE80)">
                            <animateMotion dur="2.5s" repeatCount="indefinite" path="M 600 340 L 610 30" begin="1.1s"/>
                        </circle>
                        <rect width="64" height="15" rx="4" fill="#0f172a" stroke="#4ADE80" stroke-width="1" opacity="0.95" x="-32" y="-22">
                            <animateMotion dur="2.5s" repeatCount="indefinite" path="M 600 340 L 610 30" begin="1.1s"/>
                        </rect>
                        <text font-size="7.5" fill="#BBF7D0" font-weight="bold" font-family="monospace" text-anchor="middle" y="-12">
                            Bearer vk
                            <animateMotion dur="2.5s" repeatCount="indefinite" path="M 600 340 L 610 30" begin="1.1s"/>
                        </text>
                    </g>
                </svg>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-20">
            <!-- NỘI DUNG VĂN BẢN HERO SECTION (GIAO DIỆN COMPACT THU NHỎ TRÊN MOBILE) -->
            <div class="max-w-2xl text-left space-y-2 sm:space-y-4">
                <!-- Tag AI API MARKETPLACE -->
                <div class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-full bg-gray-900/90 border border-gray-700/80 text-gray-300 text-[10px] sm:text-[11px] font-bold uppercase tracking-wider shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    <span>AI API MARKETPLACE</span>
                </div>

                <!-- Tiêu đề lớn -->
                <h1 class="text-2xl sm:text-4xl lg:text-6xl font-black text-white tracking-tight leading-[1.15]">
                    API AI chính hãng<br>
                    <span class="text-amber-400">Mua là dùng ngay</span>
                </h1>

                <!-- Mô tả -->
                <p class="text-xs sm:text-sm text-gray-400 max-w-xl leading-relaxed">
                    Chuyên API AI chính hãng Cursor, Claude, Codex, Gemini... giá rẻ, giao ngay.
                </p>

                <!-- Nút hành động gọn gàng trên mobile -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 pt-1">
                    <a href="#san-pham" class="px-3.5 py-2 sm:px-6 sm:py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-950 font-black text-xs sm:text-sm transition duration-200 shadow-lg shadow-amber-500/25 flex items-center gap-1.5 sm:gap-2">
                        <i class="fas fa-cart-shopping text-xs"></i>
                        <span>Xem sản phẩm</span>
                    </a>
                    <a href="/docs" class="px-3 py-2 sm:px-5 sm:py-3 rounded-xl bg-[#111827] hover:bg-gray-800 text-gray-200 hover:text-white font-bold text-xs sm:text-sm border border-gray-700 transition duration-200 flex items-center gap-1.5 sm:gap-2">
                        <i class="fas fa-book-open text-blue-400 text-xs"></i>
                        <span>Hướng dẫn</span>
                    </a>
                    <button type="button" @click="moPopupKiemTraKey = true" class="px-3 py-2 sm:px-5 sm:py-3 rounded-xl bg-[#111827] hover:bg-gray-800 text-gray-300 hover:text-white font-bold text-xs sm:text-sm border border-gray-700 transition duration-200 flex items-center gap-1.5 sm:gap-2">
                        <i class="fas fa-magnifying-glass text-amber-400 text-xs"></i>
                        <span>Kiểm Tra Key</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- DÂY CỜ LỄ HỘI TRANG TRÍ (PENNANT FLAGS STRINGS) -->
        <div class="w-full relative mt-3 sm:mt-10 overflow-hidden select-none pointer-events-none">
            <svg class="w-full h-7 sm:h-9" preserveAspectRatio="none" viewBox="0 0 1200 32" fill="none">
                <path d="M 0 8 Q 300 24, 600 10 T 1200 8" stroke="#475569" stroke-width="1.5" stroke-dasharray="5 3"/>
                <polygon points="40,9 50,9 45,21" fill="#EF4444"/>
                <polygon points="90,11 100,11 95,23" fill="#F59E0B"/>
                <polygon points="140,13 150,13 145,25" fill="#10B981"/>
                <polygon points="190,15 200,15 195,27" fill="#3B82F6"/>
                <polygon points="240,16 250,16 245,28" fill="#EC4899"/>
                <polygon points="290,17 300,17 295,29" fill="#8B5CF6"/>
                <polygon points="340,16 350,16 345,28" fill="#F59E0B"/>
                <polygon points="390,15 400,15 395,27" fill="#06B6D4"/>
                <polygon points="440,14 450,14 445,26" fill="#EF4444"/>
                <polygon points="490,12 500,12 495,24" fill="#10B981"/>
                <polygon points="540,11 550,11 545,23" fill="#F59E0B"/>
                <polygon points="590,10 600,10 595,22" fill="#3B82F6"/>
                <polygon points="640,11 650,11 645,23" fill="#EC4899"/>
                <polygon points="690,12 700,12 695,24" fill="#8B5CF6"/>
                <polygon points="740,14 750,14 745,26" fill="#10B981"/>
                <polygon points="790,15 800,15 795,27" fill="#F59E0B"/>
                <polygon points="840,16 850,16 845,28" fill="#EF4444"/>
                <polygon points="890,16 900,16 895,28" fill="#06B6D4"/>
                <polygon points="940,15 950,15 945,27" fill="#3B82F6"/>
                <polygon points="990,14 1000,14 995,26" fill="#F59E0B"/>
                <polygon points="1040,12 1050,12 1045,24" fill="#10B981"/>
                <polygon points="1090,11 1100,11 1095,23" fill="#EC4899"/>
                <polygon points="1140,9 1150,9 1145,21" fill="#8B5CF6"/>
            </svg>
        </div>

        <!-- PHÂN CẢNH PIXEL TECH CAMP & LỬA TRẠI DƯỚI ĐÁY HERO SECTION (CÁC MASCOT BẮN HIỆU ỨNG LÊN TRỜI) -->
        <div id="heroCampViewport" class="w-full relative overflow-hidden select-none border-b border-gray-800/80 bg-gradient-to-b from-transparent to-[#0a0e1c]">
            <div id="heroCampInner" class="w-[820px] max-w-7xl mx-auto px-4 sm:px-8 pb-3 flex items-end justify-between gap-3 text-xs font-mono origin-top-left transition-transform duration-75">
                
                <!-- 1. CLAUDE CODE (Nhân vật tóc cam + Bàn làm việc laptop + Hạt bắn hiệu ứng + Di chuyển qua lại) -->
                <div class="flex flex-col items-center group relative cursor-pointer animate-hero-claude">
                    <!-- Hạt năng lượng pixel bắn vút lên trên -->
                    <div class="absolute -top-12 left-1/2 -translate-x-1/2 pointer-events-none flex flex-col items-center">
                        <span class="animate-shoot-spark-1 text-amber-400 font-bold text-xs select-none">✦</span>
                        <span class="animate-shoot-spark-3 text-orange-400 font-bold text-[9px] select-none">▲</span>
                    </div>
                    <!-- Bong bóng thoại -->
                    <div class="animate-bubble-float bg-gray-900 border border-gray-700 text-orange-400 font-bold px-2 py-0.5 rounded text-[10px] shadow mb-1.5 flex items-center gap-1">
                        <i class="fas fa-terminal text-[9px]"></i> Bash
                    </div>
                    <!-- Pixel Avatar Claude Code -->
                    <div class="flex items-end gap-1.5">
                        <svg width="42" height="42" viewBox="0 0 32 32" class="drop-shadow">
                            <rect x="10" y="6" width="12" height="6" fill="#ea580c"/>
                            <rect x="8" y="10" width="16" height="6" fill="#ea580c"/>
                            <rect x="6" y="14" width="4" height="10" fill="#f97316"/>
                            <rect x="22" y="14" width="4" height="10" fill="#f97316"/>
                            <rect x="10" y="12" width="12" height="10" fill="#fed7aa"/>
                            <rect x="12" y="15" width="2" height="3" fill="#1e293b"/>
                            <rect x="18" y="15" width="2" height="3" fill="#1e293b"/>
                            <rect x="10" y="18" width="2" height="1" fill="#f43f5e"/>
                            <rect x="20" y="18" width="2" height="1" fill="#f43f5e"/>
                            <rect x="11" y="22" width="10" height="7" fill="#f8fafc"/>
                            <rect x="14" y="22" width="4" height="7" fill="#ea580c"/>
                        </svg>
                        <div class="w-10 h-7 bg-amber-950/80 border border-amber-800 rounded-xs relative flex items-center justify-center p-0.5 shadow-[0_0_8px_rgba(245,158,11,0.5)]">
                            <div class="w-6 h-4 bg-gray-800 border border-gray-600 rounded-xs flex items-center justify-center text-[7px] text-cyan-400 animate-pulse">
                                &gt;_
                            </div>
                        </div>
                    </div>
                    <span class="text-[11px] text-gray-400 font-sans font-bold mt-1">Claude Code</span>
                </div>

                <!-- 2. DEEPSEEK HARNESS (Nhân vật tóc xanh dương + Chồng sách/tài liệu + Hạt bắn hiệu ứng + Di chuyển qua lại) -->
                <div class="flex flex-col items-center group relative cursor-pointer animate-hero-deepseek">
                    <!-- Hạt năng lượng pixel bắn vút lên trên -->
                    <div class="absolute -top-12 left-1/2 -translate-x-1/2 pointer-events-none flex flex-col items-center">
                        <span class="animate-shoot-spark-2 text-cyan-400 font-bold text-xs select-none">✦</span>
                        <span class="animate-shoot-spark-4 text-blue-400 font-bold text-[9px] select-none">⚡</span>
                    </div>
                    <div class="animate-bubble-float-2 bg-gray-900 border border-blue-600/50 text-blue-400 font-bold px-2 py-0.5 rounded text-[10px] shadow mb-1.5 flex items-center gap-1">
                        <i class="fas fa-book text-[9px]"></i> Read
                    </div>
                    <div class="flex items-end gap-1.5">
                        <svg width="42" height="42" viewBox="0 0 32 32" class="drop-shadow">
                            <rect x="9" y="6" width="14" height="6" fill="#1d4ed8"/>
                            <rect x="7" y="10" width="18" height="6" fill="#2563eb"/>
                            <rect x="6" y="14" width="4" height="11" fill="#3b82f6"/>
                            <rect x="22" y="14" width="4" height="11" fill="#3b82f6"/>
                            <rect x="8" y="12" width="2" height="2" fill="#ffffff"/>
                            <rect x="22" y="12" width="2" height="2" fill="#ffffff"/>
                            <rect x="10" y="12" width="12" height="10" fill="#fde68a"/>
                            <rect x="12" y="15" width="2" height="3" fill="#0f172a"/>
                            <rect x="18" y="15" width="2" height="3" fill="#0f172a"/>
                            <rect x="11" y="22" width="10" height="7" fill="#1e3a8a"/>
                        </svg>
                        <div class="w-8 h-8 bg-amber-900/60 border border-amber-700/80 p-0.5 flex flex-col justify-between rounded-xs shadow-[0_0_8px_rgba(56,189,248,0.5)]">
                            <div class="h-1.5 bg-blue-500 rounded-xs"></div>
                            <div class="h-1.5 bg-cyan-400 rounded-xs"></div>
                            <div class="h-1.5 bg-amber-500 rounded-xs"></div>
                        </div>
                    </div>
                    <span class="text-[11px] text-gray-400 font-sans font-bold mt-1">DeepSeek Harness</span>
                </div>

                <!-- 3. PI (Nhân vật kỳ nhông hồng Axolotl + Bàn chế tác + Hạt bắn hiệu ứng + Di chuyển qua lại) -->
                <div class="flex flex-col items-center group relative cursor-pointer animate-hero-pi">
                    <!-- Hạt năng lượng pixel bắn vút lên trên -->
                    <div class="absolute -top-12 left-1/2 -translate-x-1/2 pointer-events-none flex flex-col items-center">
                        <span class="animate-shoot-spark-3 text-pink-400 font-bold text-xs select-none">✦</span>
                        <span class="animate-shoot-spark-1 text-rose-400 font-bold text-[9px] select-none">■</span>
                    </div>
                    <div class="animate-bubble-float-3 bg-gray-900 border border-pink-600/50 text-pink-400 font-bold px-2 py-0.5 rounded text-[10px] shadow mb-1.5 flex items-center gap-1">
                        <i class="fas fa-pen-ruler text-[9px]"></i> Edit
                    </div>
                    <div class="flex items-end gap-1.5">
                        <svg width="44" height="42" viewBox="0 0 34 32" class="drop-shadow">
                            <rect x="4" y="10" width="4" height="6" fill="#ec4899"/>
                            <rect x="2" y="12" width="4" height="3" fill="#f43f5e"/>
                            <rect x="26" y="10" width="4" height="6" fill="#ec4899"/>
                            <rect x="28" y="12" width="4" height="3" fill="#f43f5e"/>
                            <rect x="8" y="8" width="18" height="14" fill="#fbcfe8"/>
                            <rect x="10" y="7" width="14" height="1" fill="#f9a8d4"/>
                            <rect x="11" y="12" width="3" height="3" fill="#0f172a"/>
                            <rect x="12" y="13" width="1" height="1" fill="#ffffff"/>
                            <rect x="20" y="12" width="3" height="3" fill="#0f172a"/>
                            <rect x="21" y="13" width="1" height="1" fill="#ffffff"/>
                            <rect x="15" y="17" width="4" height="1" fill="#be185d"/>
                            <rect x="10" y="22" width="14" height="6" fill="#f472b6"/>
                            <rect x="8" y="26" width="3" height="3" fill="#ec4899"/>
                            <rect x="23" y="26" width="3" height="3" fill="#ec4899"/>
                        </svg>
                        <div class="w-9 h-6 bg-amber-900/80 border border-amber-700/80 rounded-xs flex items-center justify-center shadow-[0_0_8px_rgba(244,114,182,0.5)]">
                            <span class="w-4 h-1.5 bg-gray-400 rounded-xs"></span>
                        </div>
                    </div>
                    <span class="text-[11px] text-gray-400 font-sans font-bold mt-1">Pi</span>
                </div>

                <!-- 4. NGÔI NHÀ GỖ CABIN + KÍNH THIÊN VĂN NHÌN SAO -->
                <div class="hidden lg:flex items-end gap-3 pb-1 opacity-85">
                    <div class="w-14 h-12 bg-amber-950 border-2 border-amber-900 rounded-xs relative p-1 shadow-md">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-0 h-0 border-x-[32px] border-x-transparent border-b-[14px] border-b-amber-800"></div>
                        <div class="w-4 h-4 bg-amber-300 border border-amber-500 rounded-xs mt-3 ml-1 shadow-[0_0_10px_rgba(251,191,36,0.8)]"></div>
                    </div>
                    <div class="w-6 h-10 relative flex flex-col items-center">
                        <div class="w-8 h-2 bg-slate-300 border border-slate-500 -rotate-45 origin-left"></div>
                        <div class="w-1 h-6 bg-slate-600 mt-2"></div>
                    </div>
                </div>

                <!-- 5. LỬA TRẠI PIXEL CAMPFIRE PHÁT SÁNG BẬP BÙNG + TIA LỬA BẮN LÊN -->
                <div class="flex flex-col items-center pb-0.5 relative">
                    <!-- Tia tàn lửa đỏ vàng bắn lên không trung -->
                    <div class="absolute -top-10 left-1/2 -translate-x-1/2 pointer-events-none flex flex-col items-center">
                        <span class="animate-shoot-spark-1 text-yellow-300 font-bold text-xs select-none">🔥</span>
                        <span class="animate-shoot-spark-2 text-amber-400 font-bold text-[10px] select-none">✦</span>
                    </div>
                    <div class="animate-fire relative w-7 h-8 flex items-center justify-center">
                        <div class="w-6 h-7 bg-amber-500 rounded-t-full shadow-[0_0_20px_rgba(245,158,11,1)]"></div>
                        <div class="absolute bottom-0 w-3.5 h-4.5 bg-yellow-200 rounded-t-full"></div>
                    </div>
                    <div class="w-9 h-2 bg-amber-950 border border-amber-800 rounded-full flex items-center justify-center mt-0.5">
                        <span class="w-6 h-0.5 bg-amber-900"></span>
                    </div>
                </div>

                <!-- 6. CODEX (Nhân vật tóc tím + Server trạm thu phát + Hạt bắn hiệu ứng + Di chuyển qua lại) -->
                <div class="flex flex-col items-center group relative cursor-pointer animate-hero-codex">
                    <!-- Hạt năng lượng pixel bắn vút lên trên -->
                    <div class="absolute -top-12 left-1/2 -translate-x-1/2 pointer-events-none flex flex-col items-center">
                        <span class="animate-shoot-spark-4 text-purple-300 font-bold text-xs select-none">✦</span>
                        <span class="animate-shoot-spark-2 text-fuchsia-400 font-bold text-[9px] select-none">⚡</span>
                    </div>
                    <div class="animate-bubble-float bg-gray-900 border border-purple-500/50 text-purple-300 font-bold px-2 py-0.5 rounded text-[10px] shadow mb-1.5 flex items-center gap-1">
                        <i class="fas fa-gauge-high text-[9px]"></i> 95 tok/s
                    </div>
                    <div class="flex items-end gap-1.5">
                        <svg width="42" height="42" viewBox="0 0 32 32" class="drop-shadow">
                            <rect x="9" y="6" width="14" height="6" fill="#7e22ce"/>
                            <rect x="7" y="10" width="18" height="6" fill="#9333ea"/>
                            <rect x="6" y="14" width="4" height="10" fill="#a855f7"/>
                            <rect x="22" y="14" width="4" height="10" fill="#a855f7"/>
                            <rect x="7" y="6" width="3" height="3" fill="#ffffff"/>
                            <rect x="22" y="6" width="3" height="3" fill="#ffffff"/>
                            <rect x="10" y="12" width="12" height="10" fill="#fed7aa"/>
                            <rect x="12" y="15" width="2" height="3" fill="#3b0764"/>
                            <rect x="18" y="15" width="2" height="3" fill="#3b0764"/>
                            <rect x="11" y="22" width="10" height="7" fill="#6b21a8"/>
                        </svg>
                        <div class="w-7 h-10 bg-slate-900 border border-purple-700/80 p-0.5 flex flex-col justify-between rounded-xs shadow-[0_0_8px_rgba(168,85,247,0.5)]">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-ping"></span>
                            <span class="w-1.5 h-1.5 bg-purple-400 rounded-full"></span>
                            <span class="w-1.5 h-1.5 bg-blue-400 rounded-full"></span>
                        </div>
                    </div>
                    <span class="text-[11px] text-gray-400 font-sans font-bold mt-1">Codex</span>
                </div>

                <!-- 7. OPENCODE (Robot terminal đầu vuông xanh lá + Hạt bắn hiệu ứng + Di chuyển qua lại) -->
                <div class="flex flex-col items-center group relative cursor-pointer animate-hero-opencode">
                    <!-- Hạt năng lượng pixel bắn vút lên trên -->
                    <div class="absolute -top-12 left-1/2 -translate-x-1/2 pointer-events-none flex flex-col items-center">
                        <span class="animate-shoot-spark-1 text-green-300 font-bold text-xs select-none">✦</span>
                        <span class="animate-shoot-spark-3 text-emerald-400 font-bold text-[9px] select-none">01</span>
                    </div>
                    <div class="animate-bubble-float-2 bg-gray-900 border border-green-500/50 text-green-400 font-bold px-2 py-0.5 rounded text-[10px] shadow mb-1.5 flex items-center gap-1">
                        <i class="fas fa-bolt text-[9px]"></i> 100 tok/s
                    </div>
                    <div class="flex items-end gap-1.5">
                        <svg width="40" height="42" viewBox="0 0 32 32" class="drop-shadow">
                            <rect x="7" y="8" width="18" height="15" fill="#15803d"/>
                            <rect x="9" y="10" width="14" height="11" fill="#166534"/>
                            <rect x="11" y="13" width="3" height="3" fill="#86efac"/>
                            <rect x="18" y="13" width="3" height="3" fill="#86efac"/>
                            <rect x="13" y="18" width="6" height="1.5" fill="#86efac"/>
                            <rect x="15" y="4" width="2" height="4" fill="#22c55e"/>
                            <rect x="14" y="3" width="4" height="2" fill="#4ade80"/>
                            <rect x="10" y="23" width="12" height="6" fill="#14532d"/>
                        </svg>
                    </div>
                    <span class="text-[11px] text-gray-400 font-sans font-bold mt-1">OpenCode</span>
                </div>

            </div>
        </div>
    </section>

        <!-- DẢI NỀN TẢNG ĐỐI TÁC & CÔNG CỤ (MARQUEE 2 CHIỀU ĐỐI XỨNG CHUẨN VECTOR SVG) -->
    <section class="py-7 bg-[#0B0F19] border-b border-gray-800/80 overflow-hidden relative">
        <div class="max-w-7xl mx-auto px-4 mb-4 text-center">
            <h2 class="text-[11px] sm:text-xs font-bold uppercase tracking-[0.25em] text-gray-400">
                HOẠT ĐỘNG VỚI CÁC NHÀ CUNG CẤP VÀ CÔNG CỤ
            </h2>
        </div>

        <!-- Màng gradient mờ dần 2 bên mép màn hình chống chói -->
        <div class="absolute left-0 top-0 bottom-0 w-12 sm:w-28 bg-gradient-to-r from-[#0B0F19] to-transparent z-10 pointer-events-none"></div>
        <div class="absolute right-0 top-0 bottom-0 w-12 sm:w-28 bg-gradient-to-l from-[#0B0F19] to-transparent z-10 pointer-events-none"></div>

        <div class="space-y-3">
            <!-- HÀNG 1: CHẠY TỪ PHẢI SANG TRÁI (CÁC NHÀ CUNG CẤP AI) -->
            <div class="marquee-container flex overflow-hidden w-full select-none">
                <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 animate-marquee-left">
                    <?php 
                    $nhaCungCapAI = [
                        ['ten' => 'DeepSeek', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M23.748 4.482c-.254-.124-.364.113-.512.234-.051.039-.094.09-.137.136-.372.397-.806.657-1.373.626-.829-.046-1.537.214-2.163.848-.133-.782-.575-1.248-1.247-1.548-.352-.156-.708-.311-.955-.65-.172-.241-.219-.51-.305-.774-.055-.16-.11-.323-.293-.35-.2-.031-.278.136-.356.276-.313.572-.434 1.202-.422 1.84.027 1.436.633 2.58 1.838 3.393.137.093.172.187.129.323-.082.28-.18.552-.266.833-.055.179-.137.217-.329.14a5.526 5.526 0 01-1.736-1.18c-.857-.828-1.631-1.742-2.597-2.458a11.365 11.365 0 00-.689-.471c-.985-.957.13-1.743.388-1.836.27-.098.093-.432-.779-.428-.872.004-1.67.295-2.687.684a3.055 3.055 0 01-.465.137 9.597 9.597 0 00-2.883-.102c-1.885.21-3.39 1.102-4.497 2.623C.082 8.606-.231 10.684.152 12.85c.403 2.284 1.569 4.175 3.36 5.653 1.858 1.533 3.997 2.284 6.438 2.14 1.482-.085 3.133-.284 4.994-1.86.47.234.962.327 1.78.397.63.059 1.236-.03 1.705-.128.735-.156.684-.837.419-.961-2.155-1.004-1.682-.595-2.113-.926 1.096-1.296 2.746-2.642 3.392-7.003.05-.347.007-.565 0-.845-.004-.17.035-.237.23-.256a4.173 4.173 0 001.545-.475c1.396-.763 1.96-2.015 2.093-3.517.02-.23-.004-.467-.247-.588zM11.581 18c-2.089-1.642-3.102-2.183-3.52-2.16-.392.024-.321.471-.235.763.09.288.207.486.371.739.114.167.192.416-.113.603-.673.416-1.842-.14-1.897-.167-1.361-.802-2.5-1.86-3.301-3.307-.774-1.393-1.224-2.887-1.298-4.482-.02-.386.093-.522.477-.592a4.696 4.696 0 011.529-.039c2.132.312 3.946 1.265 5.468 2.774.868.86 1.525 1.887 2.202 2.891.72 1.066 1.494 2.082 2.48 2.914.348.292.625.514.891.677-.802.09-2.14.11-3.054-.614zm1-6.44a.306.306 0 01.415-.287.302.302 0 01.2.288.306.306 0 01-.31.307.303.303 0 01-.304-.308zm3.11 1.596c-.2.081-.399.151-.59.16a1.245 1.245 0 01-.798-.254c-.274-.23-.47-.358-.552-.758a1.73 1.73 0 01.016-.588c.07-.327-.008-.537-.239-.727-.187-.156-.426-.199-.688-.199a.559.559 0 01-.254-.078c-.11-.054-.2-.19-.114-.358.028-.054.16-.186.192-.21.356-.202.767-.136 1.146.016.352.144.618.408 1.001.782.391.451.462.576.685.914.176.265.336.537.445.848.067.195-.019.354-.25.452z" fill="#4D6BFE"></path></svg>'],
                        ['ten' => 'Qwen', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12.604 1.34c.393.69.784 1.382 1.174 2.075a.18.18 0 00.157.091h5.552c.174 0 .322.11.446.327l1.454 2.57c.19.337.24.478.024.837-.26.43-.513.864-.76 1.3l-.367.658c-.106.196-.223.28-.04.512l2.652 4.637c.172.301.111.494-.043.77-.437.785-.882 1.564-1.335 2.34-.159.272-.352.375-.68.37-.777-.016-1.552-.01-2.327.016a.099.099 0 00-.081.05 575.097 575.097 0 01-2.705 4.74c-.169.293-.38.363-.725.364-.997.003-2.002.004-3.017.002a.537.537 0 01-.465-.271l-1.335-2.323a.09.09 0 00-.083-.049H4.982c-.285.03-.553-.001-.805-.092l-1.603-2.77a.543.543 0 01-.002-.54l1.207-2.12a.198.198 0 000-.197 550.951 550.951 0 01-1.875-3.272l-.79-1.395c-.16-.31-.173-.496.095-.965.465-.813.927-1.625 1.387-2.436.132-.234.304-.334.584-.335a338.3 338.3 0 012.589-.001.124.124 0 00.107-.063l2.806-4.895a.488.488 0 01.422-.246c.524-.001 1.053 0 1.583-.006L11.704 1c.341-.003.724.032.9.34zm-3.432.403a.06.06 0 00-.052.03L6.254 6.788a.157.157 0 01-.135.078H3.253c-.056 0-.07.025-.041.074l5.81 10.156c.025.042.013.062-.034.063l-2.795.015a.218.218 0 00-.2.116l-1.32 2.31c-.044.078-.021.118.068.118l5.716.008c.046 0 .08.02.104.061l1.403 2.454c.046.081.092.082.139 0l5.006-8.76.783-1.382a.055.055 0 01.096 0l1.424 2.53a.122.122 0 00.107.062l2.763-.02a.04.04 0 00.035-.02.041.041 0 000-.04l-2.9-5.086a.108.108 0 010-.113l.293-.507 1.12-1.977c.024-.041.012-.062-.035-.062H9.2c-.059 0-.073-.026-.043-.077l1.434-2.505a.107.107 0 000-.114L9.225 1.774a.06.06 0 00-.053-.031zm6.29 8.02c.046 0 .058.02.034.06l-.832 1.465-2.613 4.585a.056.056 0 01-.05.029.058.058 0 01-.05-.029L8.498 9.841c-.02-.034-.01-.052.028-.054l.216-.012 6.722-.012z" fill="url(#lobe-icons-qwen-_R_0_)" fill-rule="nonzero"></path><defs><linearGradient id="lobe-icons-qwen-_R_0_" x1="0%" x2="100%" y1="0%" y2="0%"><stop offset="0%" stop-color="#6336E7" stop-opacity=".84"></stop><stop offset="100%" stop-color="#6F69F7" stop-opacity=".84"></stop></linearGradient></defs></svg>'],
                        ['ten' => 'KIMI', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.846 0a1.923 1.923 0 110 3.846H20.15a.226.226 0 01-.227-.226V1.923C19.923.861 20.784 0 21.846 0z" fill="#1783FF"></path><path d="M11.065 11.199l7.257-7.2c.137-.136.06-.41-.116-.41H14.3a.164.164 0 00-.117.051l-7.82 7.756c-.122.12-.302.013-.302-.179V3.82c0-.127-.083-.23-.185-.23H3.186c-.103 0-.186.103-.186.23V19.77c0 .128.083.23.186.23h2.69c.103 0 .186-.102.186-.23v-3.25c0-.069.025-.135.069-.178l2.424-2.406a.158.158 0 01.205-.023l6.484 4.772a7.677 7.677 0 003.453 1.283c.108.012.2-.095.2-.23v-3.06c0-.117-.07-.212-.164-.227a5.028 5.028 0 01-2.027-.807l-5.613-4.064c-.117-.078-.132-.279-.028-.381z" fill="#fff"></path></svg>'],
                        ['ten' => 'MINIMAX', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="lobe-icons-minimax-_R_0_" x1="0%" x2="100.182%" y1="50.057%" y2="50.057%"><stop offset="0%" stop-color="#E2167E"></stop><stop offset="100%" stop-color="#FE603C"></stop></linearGradient></defs><path d="M16.278 2c1.156 0 2.093.927 2.093 2.07v12.501a.74.74 0 00.744.709.74.74 0 00.743-.709V9.099a2.06 2.06 0 012.071-2.049A2.06 2.06 0 0124 9.1v6.561a.649.649 0 01-.652.645.649.649 0 01-.653-.645V9.1a.762.762 0 00-.766-.758.762.762 0 00-.766.758v7.472a2.037 2.037 0 01-2.048 2.026 2.037 2.037 0 01-2.048-2.026v-12.5a.785.785 0 00-.788-.753.785.785 0 00-.789.752l-.001 15.904A2.037 2.037 0 0113.441 22a2.037 2.037 0 01-2.048-2.026V18.04c0-.356.292-.645.652-.645.36 0 .652.289.652.645v1.934c0 .263.142.506.372.638.23.131.514.131.744 0a.734.734 0 00.372-.638V4.07c0-1.143.937-2.07 2.093-2.07zm-5.674 0c1.156 0 2.093.927 2.093 2.07v11.523a.648.648 0 01-.652.645.648.648 0 01-.652-.645V4.07a.785.785 0 00-.789-.78.785.785 0 00-.789.78v14.013a2.06 2.06 0 01-2.07 2.048 2.06 2.06 0 01-2.071-2.048V9.1a.762.762 0 00-.766-.758.762.762 0 00-.766.758v3.8a2.06 2.06 0 01-2.071 2.049A2.06 2.06 0 010 12.9v-1.378c0-.357.292-.646.652-.646.36 0 .653.29.653.646V12.9c0 .418.343.757.766.757s.766-.339.766-.757V9.099a2.06 2.06 0 012.07-2.048 2.06 2.06 0 012.071 2.048v8.984c0 .419.343.758.767.758.423 0 .766-.339.766-.758V4.07c0-1.143.937-2.07 2.093-2.07z" fill="url(#lobe-icons-minimax-_R_0_)" fill-rule="nonzero"></path></svg>'],
                        ['ten' => '智谱·AI', 'svg' => '<svg class="w-4 h-4 shrink-0 text-sky-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2v1h1a5 5 0 0 1 5 5v7a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V10a5 5 0 0 1 5-5h1V4a2 2 0 0 1 2-2zm-3 9a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm6 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>'],
                        ['ten' => 'Mistral AI', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3.428 3.4h3.429v3.428H3.428V3.4zm13.714 0h3.43v3.428h-3.43V3.4z" fill="gold"></path><path d="M3.428 6.828h6.857v3.429H3.429V6.828zm10.286 0h6.857v3.429h-6.857V6.828z" fill="#FFAF00"></path><path d="M3.428 10.258h17.144v3.428H3.428v-3.428z" fill="#FF8205"></path><path d="M3.428 13.686h3.429v3.428H3.428v-3.428zm6.858 0h3.429v3.428h-3.429v-3.428zm6.856 0h3.43v3.428h-3.43v-3.428z" fill="#FA500F"></path><path d="M0 17.114h10.286v3.429H0v-3.429zm13.714 0H24v3.429H13.714v-3.429z" fill="#E10500"></path></svg>'],
                        ['ten' => 'Grok', 'svg' => '<svg class="w-6 h-3.5 shrink-0 text-white" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 63 24" xmlns="http://www.w3.org/2000/svg"><path d="M47.419 21.645V2.457h3.033V15.12l6.415-7.369h3.678l-5.772 6.316 5.825 7.578h-3.624l-4.717-6.512-1.805-.012v6.524h-3.033zM38.22 21.968c-4.51 0-6.952-3.198-6.952-7.283 0-4.112 2.443-7.283 6.952-7.283 4.537 0 6.952 3.17 6.952 7.283 0 4.085-2.415 7.283-6.952 7.283zm-3.785-7.283c0 3.17 1.718 4.756 3.785 4.756 2.094 0 3.785-1.585 3.785-4.756 0-3.172-1.691-4.784-3.785-4.784-2.067 0-3.785 1.612-3.785 4.784zM22.826 21.645V9.955l2.55-2.204h5.422v2.58H25.86v11.314h-3.033zM11.228 22C5.447 22 2 17.802 2 12.078 2 6.3 5.57 2 11.341 2c4.51 0 7.811 2.311 8.59 6.611h-3.463c-.51-2.445-2.55-3.816-5.127-3.816-4.16 0-5.986 3.601-5.986 7.283 0 3.682 1.826 7.256 5.986 7.256 3.973 0 5.717-2.876 5.852-5.267h-5.986v-2.783h9.046l-.015 1.455c0 5.406-2.203 9.261-9.01 9.261z"></path></svg>'],
                        ['ten' => 'Llama', 'svg' => '<svg class="hp-llama w-4 h-4 shrink-0 text-blue-500" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 6.2c-2.6 0-4.5 2-4.5 5.3s1.9 5.3 4.5 5.3c1 0 1.9-.3 2.7-.9l2.8-2.2 2.8 2.2c.8.6 1.7.9 2.7.9 2.6 0 4.5-2 4.5-5.3S20.1 6.2 17.5 6.2c-1 0-1.9.3-2.7.9L12 9.3 9.2 7.1c-.8-.6-1.7-.9-2.7-.9zm0 2.6c.5 0 1 .2 1.4.5l3.1 2.4 3.1-2.4c.4-.3.9-.5 1.4-.5 1.3 0 2.1 1.1 2.1 2.7s-.8 2.7-2.1 2.7c-.5 0-1-.2-1.4-.5l-3.1-2.4-3.1 2.4c-.4.3-.9.5-1.4.5-1.3 0-2.1-1.1-2.1-2.7s.8-2.7 2.1-2.7z" fill="#5aa2ff"/></svg>'],
                        ['ten' => 'OpenAI', 'svg' => '<svg class="w-4 h-4 shrink-0 text-emerald-400" fill="currentColor" fill-rule="evenodd"  viewBox="1 1 19.5 19.5" xmlns="http://www.w3.org/2000/svg"><path d="M10.703 2C5.916 2 2 5.916 2 10.703c0 4.787 3.916 8.704 8.703 8.704 4.787 0 8.704-3.893 8.704-8.704A8.698 8.698 0 0010.703 2zm0 14.288c-2.973 0-5.367-2.442-5.367-5.585S7.73 5.12 10.703 5.12c2.974 0 5.367 2.441 5.367 5.584s-2.393 5.585-5.367 5.585z"/></svg>'],
                        ['ten' => 'Gemini', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20.616 10.835a14.147 14.147 0 01-4.45-3.001 14.111 14.111 0 01-3.678-6.452.503.503 0 00-.975 0 14.134 14.134 0 01-3.679 6.452 14.155 14.155 0 01-4.45 3.001c-.65.28-1.318.505-2.002.678a.502.502 0 000 .975c.684.172 1.35.397 2.002.677a14.147 14.147 0 014.45 3.001 14.112 14.112 0 013.679 6.453.502.502 0 00.975 0c.172-.685.397-1.351.677-2.003a14.145 14.145 0 013.001-4.45 14.113 14.113 0 016.453-3.678.503.503 0 000-.975 13.245 13.245 0 01-2.003-.678z" fill="#3186FF"></path><path d="M20.616 10.835a14.147 14.147 0 01-4.45-3.001 14.111 14.111 0 01-3.678-6.452.503.503 0 00-.975 0 14.134 14.134 0 01-3.679 6.452 14.155 14.155 0 01-4.45 3.001c-.65.28-1.318.505-2.002.678a.502.502 0 000 .975c.684.172 1.35.397 2.002.677a14.147 14.147 0 014.45 3.001 14.112 14.112 0 013.679 6.453.502.502 0 00.975 0c.172-.685.397-1.351.677-2.003a14.145 14.145 0 013.001-4.45 14.113 14.113 0 016.453-3.678.503.503 0 000-.975 13.245 13.245 0 01-2.003-.678z" fill="url(#lobe-icons-gemini-0-_R_0_)"></path><path d="M20.616 10.835a14.147 14.147 0 01-4.45-3.001 14.111 14.111 0 01-3.678-6.452.503.503 0 00-.975 0 14.134 14.134 0 01-3.679 6.452 14.155 14.155 0 01-4.45 3.001c-.65.28-1.318.505-2.002.678a.502.502 0 000 .975c.684.172 1.35.397 2.002.677a14.147 14.147 0 014.45 3.001 14.112 14.112 0 013.679 6.453.502.502 0 00.975 0c.172-.685.397-1.351.677-2.003a14.145 14.145 0 013.001-4.45 14.113 14.113 0 016.453-3.678.503.503 0 000-.975 13.245 13.245 0 01-2.003-.678z" fill="url(#lobe-icons-gemini-1-_R_0_)"></path><path d="M20.616 10.835a14.147 14.147 0 01-4.45-3.001 14.111 14.111 0 01-3.678-6.452.503.503 0 00-.975 0 14.134 14.134 0 01-3.679 6.452 14.155 14.155 0 01-4.45 3.001c-.65.28-1.318.505-2.002.678a.502.502 0 000 .975c.684.172 1.35.397 2.002.677a14.147 14.147 0 014.45 3.001 14.112 14.112 0 013.679 6.453.502.502 0 00.975 0c.172-.685.397-1.351.677-2.003a14.145 14.145 0 013.001-4.45 14.113 14.113 0 016.453-3.678.503.503 0 000-.975 13.245 13.245 0 01-2.003-.678z" fill="url(#lobe-icons-gemini-2-_R_0_)"></path><defs><linearGradient gradientUnits="userSpaceOnUse" id="lobe-icons-gemini-0-_R_0_" x1="7" x2="11" y1="15.5" y2="12"><stop stop-color="#08B962"></stop><stop offset="1" stop-color="#08B962" stop-opacity="0"></stop></linearGradient><linearGradient gradientUnits="userSpaceOnUse" id="lobe-icons-gemini-1-_R_0_" x1="8" x2="11.5" y1="5.5" y2="11"><stop stop-color="#F94543"></stop><stop offset="1" stop-color="#F94543" stop-opacity="0"></stop></linearGradient><linearGradient gradientUnits="userSpaceOnUse" id="lobe-icons-gemini-2-_R_0_" x1="3.5" x2="17.5" y1="13.5" y2="12"><stop stop-color="#FABC12"></stop><stop offset=".46" stop-color="#FABC12" stop-opacity="0"></stop></linearGradient></defs></svg>']
                    ];
                    for ($k = 0; $k < 3; $k++):
                        foreach ($nhaCungCapAI as $ncc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <?= $ncc['svg'] ?>
                                <span><?= htmlspecialchars($ncc['ten']) ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
                </div>
                <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 animate-marquee-left" aria-hidden="true">
                    <?php 
                    for ($k = 0; $k < 3; $k++):
                        foreach ($nhaCungCapAI as $ncc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <?= $ncc['svg'] ?>
                                <span><?= htmlspecialchars($ncc['ten']) ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
                </div>
            </div>

            <!-- HÀNG 2: CHẠY THEO CHIỀU NGƯỢC LẠI (CÁC CÔNG CỤ / AI AGENT) -->
            <div class="marquee-container flex overflow-hidden w-full select-none">
                <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 animate-marquee-right">
                    <?php 
                    $congCuAI = [
                        ['ten' => 'Codex', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19.503 0H4.496A4.496 4.496 0 000 4.496v15.007A4.496 4.496 0 004.496 24h15.007A4.496 4.496 0 0024 19.503V4.496A4.496 4.496 0 0019.503 0z" fill="#fff"></path><path d="M9.064 3.344a4.578 4.578 0 012.285-.312c1 .115 1.891.54 2.673 1.275.01.01.024.017.037.021a.09.09 0 00.043 0 4.55 4.55 0 013.046.275l.047.022.116.057a4.581 4.581 0 012.188 2.399c.209.51.313 1.041.315 1.595a4.24 4.24 0 01-.134 1.223.123.123 0 00.03.115c.594.607.988 1.33 1.183 2.17.289 1.425-.007 2.71-.887 3.854l-.136.166a4.548 4.548 0 01-2.201 1.388.123.123 0 00-.081.076c-.191.551-.383 1.023-.74 1.494-.9 1.187-2.222 1.846-3.711 1.838-1.187-.006-2.239-.44-3.157-1.302a.107.107 0 00-.105-.024c-.388.125-.78.143-1.204.138a4.441 4.441 0 01-1.945-.466 4.544 4.544 0 01-1.61-1.335c-.152-.202-.303-.392-.414-.617a5.81 5.81 0 01-.37-.961 4.582 4.582 0 01-.014-2.298.124.124 0 00.006-.056.085.085 0 00-.027-.048 4.467 4.467 0 01-1.034-1.651 3.896 3.896 0 01-.251-1.192 5.189 5.189 0 01.141-1.6c.337-1.112.982-1.985 1.933-2.618.212-.141.413-.251.601-.33.215-.089.43-.164.646-.227a.098.098 0 00.065-.066 4.51 4.51 0 01.829-1.615 4.535 4.535 0 011.837-1.388zm3.482 10.565a.637.637 0 000 1.272h3.636a.637.637 0 100-1.272h-3.636zM8.462 9.23a.637.637 0 00-1.106.631l1.272 2.224-1.266 2.136a.636.636 0 101.095.649l1.454-2.455a.636.636 0 00.005-.64L8.462 9.23z" fill="url(#lobe-icons-codex-_R_0_)"></path><defs><linearGradient gradientUnits="userSpaceOnUse" id="lobe-icons-codex-_R_0_" x1="12" x2="12" y1="3" y2="21"><stop stop-color="#B1A7FF"></stop><stop offset=".5" stop-color="#7A9DFF"></stop><stop offset="1" stop-color="#3941FF"></stop></linearGradient></defs></svg>'],
                        ['ten' => 'Claude Code', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path clip-rule="evenodd" d="M20.998 10.949H24v3.102h-3v3.028h-1.487V20H18v-2.921h-1.487V20H15v-2.921H9V20H7.488v-2.921H6V20H4.487v-2.921H3V14.05H0V10.95h3V5h17.998v5.949zM6 10.949h1.488V8.102H6v2.847zm10.51 0H18V8.102h-1.49v2.847z" fill="#D97757" fill-rule="evenodd"></path></svg>'],
                        ['ten' => 'Cline', 'svg' => '<svg class="w-4 h-4 shrink-0 text-sky-400" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.035 3.991c2.75 0 4.98 2.24 4.98 5.003v1.667l1.45 2.896a1.01 1.01 0 01-.002.909l-1.448 2.864v1.668c0 2.762-2.23 5.002-4.98 5.002H7.074c-2.751 0-4.98-2.24-4.98-5.002V17.33l-1.48-2.855a1.01 1.01 0 01-.003-.927l1.482-2.887V8.994c0-2.763 2.23-5.003 4.98-5.003h9.962zM8.265 9.6a2.274 2.274 0 00-2.274 2.274v4.042a2.274 2.274 0 004.547 0v-4.042A2.274 2.274 0 008.265 9.6zm7.326 0a2.274 2.274 0 00-2.274 2.274v4.042a2.274 2.274 0 104.548 0v-4.042A2.274 2.274 0 0015.59 9.6z"></path><path d="M12.054 5.558a2.779 2.779 0 100-5.558 2.779 2.779 0 000 5.558z"></path></svg>'],
                        ['ten' => 'Windsurf', 'svg' => '<svg class="w-4 h-4 shrink-0" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 111 24" xmlns="http://www.w3.org/2000/svg"><path clip-rule="evenodd" d="M33.412 7.182h-1.715a.316.316 0 00-.315.316v13.79c0 .174.14.316.315.316h1.715a.316.316 0 00.316-.317V7.498a.316.316 0 00-.316-.316zm-1.772-5.18h1.828c.174 0 .315.141.315.316V4.57a.316.316 0 01-.315.316H31.64a.316.316 0 01-.315-.316V2.318c0-.175.141-.317.315-.317zM43.676 6.79c-1.927 0-3.324.671-4.218 1.653a.457.457 0 01-.782-.322v-.623a.316.316 0 00-.316-.316h-1.686a.316.316 0 00-.316.316v13.79c0 .174.142.316.316.316h1.687a.316.316 0 00.316-.317v-8.729c0-3.025.587-3.697 3.8-3.697s3.742.533 3.742 3.557v8.868c0 .175.142.317.316.317h1.715a.316.316 0 00.316-.317v-9.513c0-3.445-1.956-4.986-4.889-4.986v.003h-.001zm17.518 1.849a.457.457 0 00.782-.322V2.319c0-.175.142-.316.316-.316h1.687c.174 0 .316.141.316.316v18.97a.316.316 0 01-.316.316h-1.687a.316.316 0 01-.316-.316v-.819c.017-.418-.489-.62-.782-.322-.81 1.008-2.374 1.848-4.47 1.848-3.685 0-6.088-2.632-6.088-7.533 0-5.18 2.68-7.673 6.172-7.673 2.095 0 3.604.84 4.386 1.849zm-8.018 5.767c0 4.733.587 5.434 4.414 5.434 3.8 0 4.386-.701 4.386-5.434 0-4.733-.559-5.461-4.386-5.461s-4.414.728-4.414 5.461zm20.924-.894l-2.763-.476c-2.04-.364-2.404-.98-2.404-2.045.028-1.457.587-2.184 3.296-2.184 2.538 0 3.35.752 3.525 2.376.017.16.152.284.313.284h1.677c.18 0 .326-.151.316-.333-.163-2.788-2.709-4.346-5.746-4.346-3.408 0-5.67 1.82-5.67 4.426 0 2.38 1.844 3.417 4.05 3.837l3.046.56c1.676.31 2.235.87 2.235 1.933 0 1.709-.587 2.437-3.464 2.437s-3.673-.832-3.858-2.518a.315.315 0 00-.313-.282h-1.687a.318.318 0 00-.317.325c.08 3.004 2.797 4.493 6.034 4.493 3.52 0 5.949-1.82 5.949-4.566 0-2.408-1.845-3.501-4.219-3.921zm12.21 6.414c3.128 0 3.715-.672 3.715-3.697V7.502c0-.174.141-.316.316-.316h1.714c.175 0 .316.142.316.316v13.79a.316.316 0 01-.316.316h-1.714a.316.316 0 01-.316-.317v-.6c-.007-.429-.45-.642-.755-.343-.892.98-2.289 1.652-4.161 1.652-2.877 0-4.833-1.54-4.833-4.986V7.501c0-.175.142-.317.316-.317h1.715c.174 0 .316.142.316.317v8.868c0 3.024.558 3.557 3.687 3.557zm19.782-12.744v-1.85c0-.727.588-1.316 1.314-1.316h1.276a.316.316 0 00.315-.317V2.317a.316.316 0 00-.315-.317h-.885c-2.514 0-4.051 1.4-4.051 3.81v1.372h-1.859c-1.647.002-2.94.229-3.803 1.26-.317.302-.755.076-.755-.36V7.5a.316.316 0 00-.315-.317h-1.715a.316.316 0 00-.316.317v13.789c0 .174.141.316.316.316h1.715a.316.316 0 00.315-.316v-8.645c0-2.857.727-3.528 3.855-3.528h2.562V21.29c0 .175.141.317.316.317h1.715a.316.316 0 00.315-.317V9.116h2.593a.316.316 0 00.315-.317V7.501a.316.316 0 00-.315-.317h-2.593v-.002zM21.834 18.008a.561.561 0 001.091.005l3.933-15.772A.318.318 0 0127.166 2h1.963c.21 0 .363.199.31.403l-4.947 18.972a.305.305 0 01-.295.228h-3.502a.31.31 0 01-.3-.234L16.285 5.5a.56.56 0 00-.543-.42h-.036a.562.562 0 00-.542.42l-4.11 15.87a.31.31 0 01-.299.234H7.253a.304.304 0 01-.295-.228L2.011 2.403A.321.321 0 012.32 2h1.963c.145 0 .272.1.308.241l3.932 15.772a.56.56 0 001.09-.005l3.78-15.764A.316.316 0 0113.7 2h4.047c.146 0 .273.1.307.244l3.779 15.764z"></path></svg>'],
                        ['ten' => 'Kilo Code', 'svg' => '<svg class="w-4 h-4 shrink-0" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 147 24" xmlns="http://www.w3.org/2000/svg"><path d="M138.579 21.999c-1.289 0-2.413-.249-3.371-.746-.959-.498-1.705-1.189-2.239-2.073-.516-.903-.774-1.953-.774-3.15v-3.815c0-1.197.258-2.238.774-3.123a5.467 5.467 0 012.239-2.1c.958-.498 2.082-.746 3.371-.746 1.272 0 2.377.248 3.317.746.958.497 1.695 1.197 2.211 2.1.534.885.801 1.926.801 3.123v2.736h-9.369v1.078c0 1.069.258 1.88.774 2.432.516.535 1.281.802 2.294.802.774 0 1.4-.13 1.879-.387.479-.277.783-.673.912-1.189h3.4c-.258 1.308-.958 2.359-2.101 3.151-1.124.774-2.496 1.16-4.118 1.16zm2.985-8.982v-.83c0-1.05-.249-1.851-.746-2.404-.498-.571-1.244-.857-2.239-.857-.995 0-1.75.286-2.266.857s-.774 1.382-.774 2.432v.58l6.274-.055-.249.277zM120.958 22c-1.511 0-2.736-.525-3.676-1.576-.921-1.05-1.382-2.46-1.382-4.228v-4.118c0-1.787.461-3.206 1.382-4.256.921-1.05 2.146-1.575 3.676-1.575 1.253 0 2.247.359 2.984 1.077.737.7 1.106 1.668 1.106 2.902l-.774-.801h.802l-.111-3.62V1.547h3.455v20.176h-3.372v-2.902h-.774l.774-.802c0 1.235-.369 2.211-1.106 2.93-.737.7-1.731 1.05-2.984 1.05zm1.216-2.985c.884 0 1.566-.258 2.045-.774.497-.534.746-1.271.746-2.21v-3.815c0-.94-.249-1.667-.746-2.183-.479-.534-1.161-.802-2.045-.802-.885 0-1.576.258-2.073.774-.498.516-.746 1.253-.746 2.211v3.814c0 .958.248 1.695.746 2.211.497.516 1.188.774 2.073.774zM105.961 21.972c-1.289 0-2.413-.24-3.371-.719a5.573 5.573 0 01-2.211-2.073c-.516-.902-.774-1.962-.774-3.178v-3.758c0-1.216.258-2.267.774-3.151a5.327 5.327 0 012.211-2.073c.958-.497 2.082-.746 3.371-.746 1.309 0 2.433.249 3.372.746a5.155 5.155 0 012.184 2.073c.534.884.801 1.925.801 3.123v3.786c0 1.216-.267 2.276-.801 3.178a5.386 5.386 0 01-2.184 2.073c-.939.48-2.063.719-3.372.719zm0-3.013c.922 0 1.631-.248 2.129-.746.515-.516.773-1.253.773-2.21v-3.76c0-.976-.258-1.713-.773-2.21-.498-.498-1.207-.747-2.129-.747-.902 0-1.612.25-2.128.747-.515.497-.773 1.234-.773 2.21v3.76c0 .957.258 1.694.773 2.21.516.498 1.226.746 2.128.746zM89.777 22c-1.29 0-2.423-.24-3.4-.719-.958-.497-1.704-1.188-2.238-2.072-.516-.903-.774-1.963-.774-3.179V7.242c0-1.235.258-2.294.774-3.179a5.347 5.347 0 012.239-2.045c.976-.497 2.11-.746 3.399-.746 1.308 0 2.432.249 3.372.746a5.347 5.347 0 012.238 2.045c.535.885.802 1.944.802 3.179h-3.483c0-.958-.258-1.686-.773-2.184-.498-.497-1.216-.746-2.156-.746-.94 0-1.668.249-2.183.746-.516.498-.774 1.216-.774 2.156v8.816c0 .94.258 1.668.774 2.183.516.498 1.243.747 2.183.747.94 0 1.658-.249 2.156-.747.515-.515.773-1.243.773-2.183h3.483c0 1.198-.267 2.248-.802 3.15a5.473 5.473 0 01-2.238 2.101c-.94.48-2.064.719-3.372.719zM57.076 21.972c-1.29 0-2.413-.24-3.372-.719a5.576 5.576 0 01-2.21-2.073c-.517-.902-.774-1.962-.774-3.178v-3.758c0-1.216.258-2.267.773-3.151a5.33 5.33 0 012.211-2.073c.959-.497 2.082-.746 3.372-.746 1.308 0 2.432.249 3.372.746a5.155 5.155 0 012.183 2.073c.535.884.802 1.925.802 3.123v3.786c0 1.216-.267 2.276-.802 3.178a5.385 5.385 0 01-2.183 2.073c-.94.48-2.064.719-3.372.719zm0-3.013c.921 0 1.63-.248 2.128-.746.516-.516.774-1.253.774-2.21v-3.76c0-.976-.258-1.713-.774-2.21-.497-.498-1.207-.747-2.128-.747-.903 0-1.612.25-2.128.747s-.774 1.234-.774 2.21v3.76c0 .957.258 1.694.774 2.21.516.498 1.225.746 2.128.746zM43.102 21.724c-.994 0-1.87-.203-2.625-.609a4.446 4.446 0 01-1.741-1.713c-.424-.756-.636-1.621-.636-2.598V4.671h-4.864V1.548h8.319v15.256c0 .553.157.995.47 1.327.331.313.773.47 1.326.47h4.588v3.123h-4.837zM18.295 21.723v-3.15h5.306V9.644h-4.615V6.522h7.932v12.05h4.698v3.15h-13.32zM24.983 3.98c-.7 0-1.253-.175-1.658-.525-.405-.369-.608-.857-.608-1.465 0-.608.203-1.087.608-1.437C23.73.184 24.283 0 24.983 0s1.253.184 1.659.553c.405.35.608.829.608 1.437 0 .608-.203 1.096-.608 1.465-.406.35-.959.525-1.659.525zM2 21.724V1.548h3.455v8.153h2.404l3.786-8.153h3.759l-4.505 9.673 4.726 10.503h-3.841l-3.98-8.872h-2.35v8.872H2z"></path></svg>'],
                        ['ten' => 'Roo Code', 'svg' => '<svg class="w-4 h-4 shrink-0" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 132 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.666 11.013h5.111c1.07 0 1.872-.235 2.407-.707.535-.47.803-1.237.803-2.297 0-1.02-.268-1.757-.803-2.209-.535-.47-1.337-.706-2.407-.706H6.666v5.92zM2 1.5h11.441c.951 0 1.803.158 2.556.472a5.552 5.552 0 011.961 1.237c.555.53.971 1.148 1.248 1.855.298.687.446 1.433.446 2.238 0 1.237-.267 2.307-.802 3.21-.515.904-1.367 1.59-2.556 2.062v.059c.575.157 1.05.403 1.427.736.376.314.683.697.92 1.149.239.432.407.913.506 1.443.119.53.198 1.06.238 1.59.02.334.04.727.06 1.178.019.452.049.913.088 1.385.06.47.139.922.238 1.354.119.413.287.766.505 1.06h-4.665c-.258-.667-.416-1.462-.476-2.385a35.211 35.211 0 00-.267-2.65c-.159-1.1-.496-1.905-1.01-2.415-.516-.511-1.358-.766-2.527-.766H6.666v8.217H2V1.5zM25.26 14.93c0 .61.059 1.208.177 1.797.12.57.317 1.09.595 1.561.297.452.683.815 1.159 1.09.475.275 1.07.412 1.783.412.713 0 1.307-.137 1.783-.412a3.086 3.086 0 001.159-1.09c.297-.471.505-.991.624-1.56a9.049 9.049 0 00.178-1.797c0-.609-.06-1.208-.178-1.797a4.383 4.383 0 00-.624-1.56 3.085 3.085 0 00-1.16-1.09c-.475-.295-1.069-.442-1.782-.442-.713 0-1.308.147-1.783.442a3.408 3.408 0 00-1.16 1.09 4.75 4.75 0 00-.593 1.56 9.056 9.056 0 00-.179 1.797zm-4.22 0c0-1.216.188-2.316.564-3.298.376-1.001.911-1.845 1.605-2.533a6.928 6.928 0 012.496-1.62c.97-.392 2.06-.588 3.269-.588s2.298.196 3.269.589c.99.373 1.832.913 2.526 1.62.693.687 1.228 1.53 1.605 2.532.376.982.564 2.082.564 3.299s-.188 2.317-.564 3.298a7.302 7.302 0 01-1.605 2.533c-.694.687-1.536 1.217-2.526 1.59-.971.374-2.06.56-3.269.56s-2.298-.186-3.27-.56a7.13 7.13 0 01-2.495-1.59 7.298 7.298 0 01-1.605-2.533c-.377-.981-.565-2.081-.565-3.298zM41.94 14.93c0 .61.06 1.208.179 1.797.119.57.317 1.09.594 1.561a3.41 3.41 0 001.16 1.09c.474.275 1.069.412 1.782.412.713 0 1.308-.137 1.783-.412a3.085 3.085 0 001.16-1.09c.296-.471.505-.991.623-1.56a9.063 9.063 0 00.179-1.797c0-.609-.06-1.208-.179-1.797a4.384 4.384 0 00-.624-1.56 3.084 3.084 0 00-1.159-1.09c-.475-.295-1.07-.442-1.783-.442-.713 0-1.308.147-1.783.442a3.408 3.408 0 00-1.159 1.09 4.748 4.748 0 00-.594 1.56 9.056 9.056 0 00-.179 1.797zm-4.22 0c0-1.216.189-2.316.565-3.298.377-1.001.912-1.845 1.605-2.533a6.928 6.928 0 012.496-1.62c.971-.392 2.06-.588 3.27-.588 1.208 0 2.297.196 3.268.589.99.373 1.833.913 2.526 1.62.694.687 1.228 1.53 1.605 2.532.376.982.564 2.082.564 3.299s-.188 2.317-.564 3.298a7.3 7.3 0 01-1.605 2.533c-.693.687-1.535 1.217-2.526 1.59-.97.374-2.06.56-3.269.56-1.208 0-2.298-.186-3.269-.56a7.13 7.13 0 01-2.496-1.59 7.297 7.297 0 01-1.605-2.533c-.376-.981-.564-2.081-.564-3.298zM76.254 8.569a3.632 3.632 0 00-.535-1.443 4.525 4.525 0 00-2.466-1.944 4.55 4.55 0 00-1.635-.294c-1.05 0-1.942.206-2.675.618a4.857 4.857 0 00-1.783 1.62 7.126 7.126 0 00-1.01 2.297 11.954 11.954 0 00-.297 2.68c0 .884.099 1.748.297 2.592.218.824.554 1.57 1.01 2.238a5.22 5.22 0 001.783 1.62c.733.392 1.625.589 2.675.589 1.426 0 2.536-.432 3.328-1.296.812-.864 1.308-2.003 1.486-3.416h4.517c-.119 1.315-.426 2.503-.921 3.563a8.537 8.537 0 01-1.962 2.71 8.349 8.349 0 01-2.853 1.708c-1.089.393-2.288.589-3.595.589-1.625 0-3.09-.275-4.398-.825-1.288-.569-2.378-1.345-3.27-2.326a10.24 10.24 0 01-2.05-3.446c-.475-1.335-.713-2.768-.713-4.3 0-1.57.238-3.024.713-4.359.476-1.354 1.16-2.532 2.05-3.534.892-1.001 1.982-1.787 3.27-2.356C68.527 1.284 69.993 1 71.618 1c1.169 0 2.268.167 3.299.5a8.62 8.62 0 012.793 1.473 7.635 7.635 0 012.05 2.356c.535.943.872 2.023 1.01 3.24h-4.516zM85.948 14.93c0 .61.06 1.208.178 1.797.12.57.317 1.09.594 1.561.298.452.684.815 1.16 1.09.475.275 1.07.412 1.783.412.713 0 1.307-.137 1.783-.412a3.085 3.085 0 001.159-1.09c.297-.471.505-.991.624-1.56a9.063 9.063 0 00.178-1.797c0-.609-.06-1.208-.178-1.797a4.387 4.387 0 00-.624-1.56 3.084 3.084 0 00-1.16-1.09c-.475-.295-1.07-.442-1.782-.442-.714 0-1.308.147-1.784.442a3.408 3.408 0 00-1.159 1.09 4.755 4.755 0 00-.594 1.56 9.063 9.063 0 00-.178 1.797zm-4.22 0c0-1.216.188-2.316.565-3.298.376-1.001.91-1.845 1.604-2.533a6.928 6.928 0 012.497-1.62c.97-.392 2.06-.588 3.269-.588 1.208 0 2.298.196 3.269.589.99.373 1.832.913 2.525 1.62.694.687 1.229 1.53 1.606 2.532.375.982.563 2.082.563 3.299s-.188 2.317-.563 3.298a7.307 7.307 0 01-1.606 2.533c-.693.687-1.535 1.217-2.525 1.59-.971.374-2.06.56-3.27.56-1.208 0-2.298-.186-3.268-.56a7.13 7.13 0 01-2.497-1.59 7.298 7.298 0 01-1.604-2.533c-.377-.981-.565-2.081-.565-3.298zM109.79 14.871c0-.628-.058-1.227-.178-1.796a4.088 4.088 0 00-.623-1.532 3.225 3.225 0 00-1.1-1.09c-.456-.274-1.03-.412-1.724-.412-.693 0-1.277.138-1.753.413a3.425 3.425 0 00-1.16 1.09 5.283 5.283 0 00-.622 1.56 8.696 8.696 0 00-.179 1.797c0 .589.069 1.178.207 1.767.14.589.357 1.119.655 1.59.316.452.703.825 1.158 1.12.476.274 1.04.412 1.694.412.694 0 1.268-.138 1.724-.413a3.15 3.15 0 001.129-1.09 5.06 5.06 0 00.594-1.59c.12-.589.178-1.197.178-1.826zm.061 5.714h-.061c-.494.825-1.147 1.423-1.96 1.796-.793.354-1.695.53-2.705.53-1.149 0-2.159-.216-3.031-.648a6.425 6.425 0 01-2.169-1.796 8.75 8.75 0 01-1.279-2.621 11.264 11.264 0 01-.415-3.063c0-1.021.14-2.003.415-2.945.298-.962.724-1.806 1.28-2.533a6.35 6.35 0 012.138-1.738c.852-.451 1.843-.677 2.972-.677a5.86 5.86 0 012.585.59 4.407 4.407 0 011.962 1.678h.059V1.5h4.219V22.53h-4.01v-1.944zM125.908 13.281c-.199-1.06-.555-1.865-1.07-2.415-.495-.55-1.258-.824-2.289-.824-.673 0-1.237.118-1.693.353-.436.216-.793.491-1.071.825a3.407 3.407 0 00-.563 1.06 5.3 5.3 0 00-.179 1.002h6.865zm-6.865 2.651c.059 1.355.406 2.336 1.04 2.945.635.609 1.545.913 2.733.913.854 0 1.586-.206 2.2-.618.614-.432.991-.884 1.129-1.355h3.714c-.594 1.826-1.504 3.132-2.733 3.917-1.228.785-2.714 1.178-4.458 1.178-1.208 0-2.297-.186-3.268-.56-.971-.392-1.794-.942-2.467-1.649a7.55 7.55 0 01-1.575-2.533c-.357-.981-.536-2.061-.536-3.24 0-1.138.189-2.198.566-3.18a7.269 7.269 0 011.604-2.533 7.483 7.483 0 012.467-1.708c.971-.412 2.041-.618 3.209-.618 1.308 0 2.447.255 3.418.765a6.522 6.522 0 012.377 2.003 8.125 8.125 0 011.368 2.886c.277 1.08.375 2.21.296 3.387h-11.084z"></path></svg>'],
                        ['ten' => 'OpenCode', 'svg' => '<svg class="w-4 h-4 shrink-0 text-green-400" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 6H8v12h8V6zm4 16H4V2h16v20z"></path></svg>'],
                        ['ten' => 'Cherry Studio', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.513 18.419c-1.6 0-3.107-.64-4.247-1.802A6.146 6.146 0 01.5 12.287c0-1.63.626-3.168 1.766-4.33 1.14-1.162 2.647-1.802 4.247-1.802s3.132.655 4.25 1.795c.835.849.835 2.23 0 3.078a2.11 2.11 0 01-3.02 0 1.737 1.737 0 00-1.234-.521c-.945 0-1.744.813-1.744 1.776 0 .964.799 1.777 1.744 1.777.46 0 .907-.19 1.234-.522a2.11 2.11 0 013.02 0c.835.85.835 2.23 0 3.079a5.997 5.997 0 01-4.25 1.794v.008z" fill="#EA5E5D"></path><path d="M12.026 24c-1.6 0-3.107-.64-4.247-1.802a6.146 6.146 0 01-1.766-4.33c0-1.63.644-3.193 1.762-4.337a2.11 2.11 0 013.021 0c.834.849.834 2.23 0 3.078-.324.331-.51.788-.51 1.255 0 .964.798 1.777 1.744 1.777.945 0 1.744-.813 1.744-1.777 0-.341-.083-.83-.475-1.233a6.255 6.255 0 01-1.77-4.348c0-1.615.627-3.168 1.767-4.33s2.646-1.802 4.247-1.802c1.6 0 3.107.64 4.247 1.802a6.146 6.146 0 011.766 4.33c0 1.63-.644 3.194-1.762 4.337a2.11 2.11 0 01-3.021 0 2.206 2.206 0 010-3.078c.323-.331.51-.788.51-1.255 0-.964-.798-1.777-1.744-1.777s-1.744.813-1.744 1.777c0 .47.19.935.521 1.27 1.115 1.136 1.727 2.667 1.727 4.311a6.122 6.122 0 01-1.766 4.33C15.137 23.36 13.63 24 12.03 24h-.004z" fill="#EA5E5D"></path><path d="M12.026 6.867L8.53 3.587a1.336 1.336 0 111.827-1.949l1.4 1.313L13.744.495a1.336 1.336 0 012.075 1.68l-3.798 4.692h.004z" fill="#23AF69"></path></svg>'],
                        ['ten' => 'Obsidian', 'svg' => '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9.643 14.012c.615-.183 1.605-.465 2.745-.534-.684-1.725-.849-3.235-.716-4.579.153-1.552.7-2.847 1.234-3.95.114-.235.223-.454.328-.664.149-.297.289-.577.42-.86.217-.47.378-.885.46-1.27.08-.38.08-.719-.014-1.044-.095-.325-.297-.675-.681-1.06a1.6 1.6 0 00-1.475.36l-4.95 4.453a1.602 1.602 0 00-.512.952l-.427 2.83c.67.592 2.327 2.317 3.335 4.71.09.213.174.432.253.656zM5.855 9.937c-.024.1-.057.197-.099.29L3.14 16.058a1.602 1.602 0 00.313 1.772l4.117 4.24c2.102-3.102 1.795-6.02.835-8.3-.728-1.73-1.832-3.083-2.55-3.833z" fill="#A88BFA"></path><path d="M8.52 22.57c.073.01.146.018.22.02.781.023 2.095.091 3.16.288.87.16 2.593.642 4.011 1.056 1.082.316 2.197-.548 2.354-1.664.115-.814.33-1.735.725-2.58l-.009.004c-.67-1.87-1.523-3.077-2.417-3.847a5.294 5.294 0 00-2.777-1.258c-1.541-.216-2.952.189-3.841.45.532 2.218.368 4.828-1.425 7.53z" fill="#A88BFA"></path><path d="M19.676 18.538a69.072 69.072 0 001.858-2.952.811.811 0 00-.061-.901c-.516-.684-1.504-2.075-2.042-3.362-.554-1.323-.636-3.378-.64-4.378a1.708 1.708 0 00-.359-1.051L15.235 1.83a3.757 3.757 0 01-.076.545c-.107.503-.307 1.004-.536 1.498-.135.29-.29.601-.446.915-.105.21-.21.42-.31.626-.517 1.068-.998 2.227-1.132 3.59-.125 1.262.046 2.73.814 4.484.128.01.257.025.386.043a6.364 6.364 0 013.327 1.506c.916.79 1.743 1.921 2.414 3.5z" fill="#A88BFA"></path></svg>'],
                        ['ten' => 'Cursor', 'svg' => '<svg class="w-3.5 h-3.5 shrink-0 text-blue-500" fill="currentColor" fill-rule="evenodd"  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.106 5.68L12.5.135a.998.998 0 00-.998 0L1.893 5.68a.84.84 0 00-.419.726v11.186c0 .3.16.577.42.727l9.607 5.547a.999.999 0 00.998 0l9.608-5.547a.84.84 0 00.42-.727V6.407a.84.84 0 00-.42-.726zm-.603 1.176L12.228 22.92c-.063.108-.228.064-.228-.061V12.34a.59.59 0 00-.295-.51l-9.11-5.26c-.107-.062-.063-.228.062-.228h18.55c.264 0 .428.286.296.514z"></path></svg>']
                    ];
                    for ($k = 0; $k < 3; $k++):
                        foreach ($congCuAI as $cc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <?= $cc['svg'] ?>
                                <span><?= htmlspecialchars($cc['ten']) ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
                </div>
                <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 animate-marquee-right" aria-hidden="true">
                    <?php 
                    for ($k = 0; $k < 3; $k++):
                        foreach ($congCuAI as $cc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <?= $cc['svg'] ?>
                                <span><?= htmlspecialchars($cc['ten']) ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
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

    <!-- PHÂN ĐOẠN ĐƠN HÀNG TRỰC TIẾP PIXEL SHOWCASE TRÊN FOOTER (THEO ẢNH 1 & CHẠY QUA LẠI CẮM MÁY TÍNH) -->
    <?php
    // Truy vấn các đơn hàng thực tế đang hoạt động / đã hoàn thành từ Database
    try {
        $stmtLive = $pdo->query("
            SELECT o.id, o.order_code, o.status, o.total_amount, o.created_at, p.name AS product_name
            FROM `orders` o
            LEFT JOIN `products` p ON o.product_id = p.id
            WHERE o.status IN ('COMPLETED', 'PAID', 'PROCESSING')
            ORDER BY o.id DESC
            LIMIT 12
        ");
        $danhSachDonHangDangDung = $stmtLive->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $danhSachDonHangDangDung = [];
    }

    // Danh sách 5 đơn hàng mẫu nếu Database mới tạo chưa có nhiều đơn
    $danhSachHienThi = $danhSachDonHangDangDung;
    $mauDonHangMacDinh = [
        ['order_code' => 'VK987346', 'product_name' => 'API Claude 10$ Max'],
        ['order_code' => 'VK982708', 'product_name' => 'API Cursor 1 Ngày'],
        ['order_code' => 'VKTEST9022', 'product_name' => 'API Cursor 1 Ngày'],
        ['order_code' => 'VK229224', 'product_name' => 'API Gemini 10$'],
        ['order_code' => 'VK883190', 'product_name' => 'API Codex Plus'],
    ];
    $idxM = 0;
    while (count($danhSachHienThi) < 5) {
        $danhSachHienThi[] = $mauDonHangMacDinh[$idxM % count($mauDonHangMacDinh)];
        $idxM++;
    }

    // Chuẩn bị dữ liệu 5 slot nhân vật trong phòng
    $slotsMascot = [];
    for ($i = 0; $i < 5; $i++) {
        $dhCur = $danhSachHienThi[$i] ?? $mauDonHangMacDinh[$i];
        $maDon = !empty($dhCur['order_code']) ? $dhCur['order_code'] : ('VK' . rand(100000, 999999));
        $tenGoi = !empty($dhCur['product_name']) ? preg_replace('/^API\s+/i', '', $dhCur['product_name']) : 'API Key';
        $slotsMascot[$i] = [
            'ma' => $maDon,
            'ten' => $tenGoi
        ];
    }
    $soLuongDonDangDung = count($danhSachDonHangDangDung) > 0 ? count($danhSachDonHangDangDung) : 5;
    ?>
        <section class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full border-t border-gray-800/80">
        <!-- Tiêu đề giới thiệu -->
        <div class="space-y-3 mb-6 text-left">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold uppercase tracking-wider">
                <span class="w-2 h-2 bg-cyan-400 rounded-full animate-ping"></span>
                <span>Đơn hàng trực tiếp</span>
            </div>
            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Mỗi đơn đang giao là một nhân vật pixel
            </h2>
            <p class="text-xs sm:text-sm text-gray-400 max-w-2xl leading-relaxed">
                Bong bóng trên mỗi nhân vật cho biết loại API hệ thống đang cấp phát. Các nhân vật pixel chuyển động tuần tra, kết nối máy chủ và xử lý tự động trong 2 giây.
            </p>
        </div>

        <!-- KHUNG PHÒNG LÀM VIỆC PIXEL ROOM SHOWCASE -->
        <div class="border border-gray-800 rounded-3xl bg-[#0e1320] p-3 sm:p-7 shadow-2xl overflow-hidden relative group">
            
            <!-- Huy hiệu trạng thái trên thanh header phòng -->
            <div class="flex items-center justify-between border-b border-gray-800/80 pb-3 mb-3">
                <div class="flex items-center gap-2 text-xs font-bold">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    <span class="text-emerald-400">Đang giao • 6 cổng API AI trực tiếp</span>
                </div>
                <div class="text-[11px] text-gray-400 font-mono flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                    <span>Tự động cấp key trong 2 giây</span>
                </div>
            </div>

            <!-- PHÒNG LÀM VIỆC PIXEL INTERIOR - TỰ ĐỘNG THU PHÓNG VỪA MÀN HÌNH MOBILE -->
            <div id="pixelRoomViewport" class="w-full overflow-hidden relative rounded-2xl border border-amber-200/60 shadow-inner bg-[#f4ecd8]">
                <div id="pixelRoomInner" class="w-[880px] bg-[#f4ecd8] text-gray-900 relative pt-2 pb-0 origin-top-left transition-transform duration-75">
                    
                    <!-- DÂY CỜ LỄ HỘI TREO TRÊN TRẦN PHÒNG -->
                    <div class="w-full h-7 relative select-none pointer-events-none mb-1">
                        <svg class="w-full h-7" preserveAspectRatio="none" viewBox="0 0 1000 28" fill="none">
                            <path d="M 0 6 Q 250 18, 500 8 T 1000 6" stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="4 3"/>
                            <polygon points="30,7 40,7 35,17" fill="#EF4444"/>
                            <polygon points="75,9 85,9 80,19" fill="#F59E0B"/>
                            <polygon points="120,11 130,11 125,21" fill="#10B981"/>
                            <polygon points="165,12 175,12 170,22" fill="#3B82F6"/>
                            <polygon points="210,13 220,13 215,23" fill="#EC4899"/>
                            <polygon points="255,13 265,13 260,23" fill="#8B5CF6"/>
                            <polygon points="300,12 310,12 305,22" fill="#F59E0B"/>
                            <polygon points="345,11 355,11 350,21" fill="#06B6D4"/>
                            <polygon points="390,9 400,9 395,19" fill="#EF4444"/>
                            <polygon points="435,8 445,8 440,18" fill="#10B981"/>
                            <polygon points="480,7 490,7 485,17" fill="#F59E0B"/>
                            <polygon points="525,8 535,8 530,18" fill="#3B82F6"/>
                            <polygon points="570,9 580,9 575,19" fill="#EC4899"/>
                            <polygon points="615,11 625,11 620,21" fill="#8B5CF6"/>
                            <polygon points="660,12 670,12 665,22" fill="#10B981"/>
                            <polygon points="705,13 715,13 710,23" fill="#F59E0B"/>
                            <polygon points="750,13 760,13 755,23" fill="#EF4444"/>
                            <polygon points="795,12 805,12 800,22" fill="#06B6D4"/>
                            <polygon points="840,11 850,11 845,21" fill="#3B82F6"/>
                            <polygon points="885,9 895,9 890,19" fill="#F59E0B"/>
                            <polygon points="930,8 940,8 935,18" fill="#10B981"/>
                            <polygon points="975,7 985,7 980,17" fill="#EC4899"/>
                        </svg>
                    </div>

                    <!-- CẢNH NỀN VĂN PHÒNG (TRANH TREO TƯỜNG, ĐỒNG HỒ, BẢNG TRẮNG, CỬA SỔ) -->
                    <div class="w-full h-11 relative flex items-center justify-between px-6 opacity-80 pointer-events-none select-none">
                        <div class="w-8 h-6 bg-white border border-gray-400 p-0.5 rounded-xs shadow-xs">
                            <div class="w-full h-full bg-emerald-200"></div>
                        </div>
                        <div class="w-18 h-9 bg-sky-100/90 border border-sky-300 rounded-xs p-1 flex items-end justify-between gap-1 shadow-xs">
                            <div class="w-3.5 h-6 bg-sky-300 rounded-xs"></div>
                            <div class="w-3.5 h-4 bg-sky-400 rounded-xs"></div>
                            <div class="w-3.5 h-7 bg-sky-300 rounded-xs"></div>
                        </div>
                        <div class="w-5 h-5 bg-white border-2 border-gray-800 rounded-xs flex items-center justify-center font-bold text-[8px]">
                            10:10
                        </div>
                        <div class="w-8 h-6 bg-white border border-amber-600 p-0.5 rounded-xs shadow-xs">
                            <div class="w-full h-1 bg-amber-500 mb-1"></div>
                            <div class="w-4 h-1 bg-gray-400"></div>
                        </div>
                        <div class="w-18 h-9 bg-sky-100/90 border border-sky-300 rounded-xs p-1 flex items-end justify-between gap-1 shadow-xs">
                            <div class="w-3.5 h-5 bg-sky-400 rounded-xs"></div>
                            <div class="w-3.5 h-7 bg-sky-300 rounded-xs"></div>
                            <div class="w-3.5 h-4 bg-sky-300 rounded-xs"></div>
                        </div>
                        <div class="w-9 h-7 bg-white border border-gray-400 p-1 flex items-end justify-between gap-0.5 rounded-xs shadow-xs">
                            <div class="w-1.5 h-3 bg-red-400"></div>
                            <div class="w-1.5 h-5 bg-blue-500"></div>
                            <div class="w-1.5 h-4 bg-emerald-500"></div>
                        </div>
                        <div class="w-16 h-9 bg-white border border-gray-400 p-1 rounded-xs shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="w-3 h-2 bg-blue-500 rounded-xs"></span>
                                <span class="w-4 h-0.5 bg-gray-400"></span>
                                <span class="w-3 h-2 bg-amber-500 rounded-xs"></span>
                            </div>
                            <div class="w-4 h-2 bg-purple-500 rounded-xs self-center"></div>
                        </div>
                        <div class="w-6 h-8 flex flex-col items-center justify-end">
                            <i class="fas fa-trophy text-amber-500 text-base"></i>
                            <span class="w-4 h-1 bg-amber-800 rounded-xs"></span>
                        </div>
                    </div>

                    <!-- HÀNG CÁC MASCOT PIXEL VÀ TRẠM MÁY TÍNH CHUYỂN ĐỘNG (6 NHÂN VẬT AI CHÍNH HÃNG) -->
                    <div class="w-full flex items-end justify-between px-5 pt-2 pb-1 relative z-10">
                        
                        <!-- 1. GEMINI (Robot Pixel Google Cyan & Xanh Dương - Đi tuần tra & Bàn máy tính) -->
                        <div class="flex items-end gap-2.5">
                            <div class="flex flex-col items-center animate-room-patrol-1 cursor-pointer">
                                <!-- Bong bóng trạng thái không hiện mã đơn hàng -->
                                <div class="animate-bubble-float bg-white/95 border border-sky-400 text-gray-800 font-bold px-2 py-1 rounded-md text-[10px] shadow-sm mb-1 flex flex-col items-center font-mono whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-sky-600 font-bold">
                                        <i class="fas fa-sparkles text-[9px] text-amber-500"></i>
                                        <span>Gemini 2.0</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] text-emerald-600 font-semibold mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>⚡ 110 tok/s</span>
                                    </div>
                                </div>
                                <!-- Avatar Pixel Gemini Robot -->
                                <div class="animate-pixel-bob">
                                    <svg width="38" height="38" viewBox="0 0 32 32" class="drop-shadow-sm">
                                        <rect x="14" y="2" width="4" height="4" fill="#0284c7"/>
                                        <rect x="15" y="0" width="2" height="2" fill="#38bdf8"/>
                                        <rect x="8" y="6" width="16" height="13" fill="#0284c7"/>
                                        <rect x="10" y="8" width="12" height="9" fill="#e0f2fe"/>
                                        <rect x="11" y="10" width="3" height="3" fill="#0369a1"/>
                                        <rect x="18" y="10" width="3" height="3" fill="#0369a1"/>
                                        <rect x="13" y="14" width="6" height="1.5" fill="#0284c7"/>
                                        <rect x="5" y="10" width="3" height="6" fill="#38bdf8"/>
                                        <rect x="24" y="10" width="3" height="6" fill="#38bdf8"/>
                                        <rect x="10" y="20" width="12" height="6" fill="#0369a1"/>
                                        <rect x="11" y="26" width="3" height="4" fill="#0284c7"/>
                                        <rect x="18" y="26" width="3" height="4" fill="#0284c7"/>
                                    </svg>
                                </div>
                            </div>

                            <!-- Trạm máy tính Gemini -->
                            <div class="w-11 h-9 bg-amber-900 border border-amber-950 p-1 flex items-center justify-center rounded-xs relative">
                                <div class="w-7 h-5 bg-gray-900 border border-gray-700 rounded-xs flex items-center justify-center text-[6px] text-sky-400 font-mono animate-typing">
                                    &gt;_gem
                                </div>
                                <span class="absolute -top-3 right-0 animate-cable-spark text-cyan-400 text-xs">✦</span>
                            </div>
                        </div>

                        <!-- 2. CODEX (Nhân vật tóc tím - Đi tuần tra kiểm tra kệ sách & server) -->
                        <div class="flex items-end gap-2.5">
                            <div class="w-9 h-15 bg-amber-900 border border-amber-950 p-1 flex flex-col justify-between rounded-xs">
                                <div class="flex gap-0.5"><span class="w-1.5 h-2.5 bg-blue-600"></span><span class="w-1.5 h-2.5 bg-red-600"></span><span class="w-1.5 h-2.5 bg-yellow-500"></span></div>
                                <div class="flex gap-0.5"><span class="w-1.5 h-2.5 bg-purple-600"></span><span class="w-1.5 h-2.5 bg-pink-600"></span><span class="w-1.5 h-2.5 bg-cyan-600"></span></div>
                                <div class="flex gap-0.5"><span class="w-1.5 h-2.5 bg-emerald-600"></span><span class="w-1.5 h-2.5 bg-blue-500"></span><span class="w-1.5 h-2.5 bg-rose-500"></span></div>
                            </div>

                            <div class="flex flex-col items-center animate-room-patrol-2 cursor-pointer">
                                <div class="animate-bubble-float-2 bg-white/95 border border-purple-400 text-gray-800 font-bold px-2 py-1 rounded-md text-[10px] shadow-sm mb-1 flex flex-col items-center font-mono whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-purple-700 font-bold">
                                        <i class="fas fa-terminal text-[9px]"></i>
                                        <span>Codex Pro</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] text-emerald-600 font-semibold mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>⚡ 95 tok/s</span>
                                    </div>
                                </div>
                                <div class="animate-pixel-bob">
                                    <svg width="38" height="38" viewBox="0 0 32 32" class="drop-shadow-sm">
                                        <rect x="9" y="6" width="14" height="6" fill="#7e22ce"/>
                                        <rect x="7" y="10" width="18" height="6" fill="#9333ea"/>
                                        <rect x="6" y="14" width="4" height="10" fill="#a855f7"/>
                                        <rect x="22" y="14" width="4" height="10" fill="#a855f7"/>
                                        <rect x="7" y="6" width="3" height="3" fill="#ffffff"/>
                                        <rect x="22" y="6" width="3" height="3" fill="#ffffff"/>
                                        <rect x="10" y="12" width="12" height="10" fill="#fed7aa"/>
                                        <rect x="12" y="15" width="2" height="3" fill="#3b0764"/>
                                        <rect x="18" y="15" width="2" height="3" fill="#3b0764"/>
                                        <rect x="11" y="22" width="10" height="7" fill="#6b21a8"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- 3. CLAUDE CODE (Nhân vật tóc cam CẮM VÀO SERVER & GÕ PHÍM) -->
                        <div class="flex items-end gap-2.5">
                            <div class="w-8 h-16 bg-slate-900 border border-slate-950 p-1 flex flex-col justify-between rounded-xs">
                                <span class="w-2 h-1 bg-green-400 rounded-full animate-ping"></span>
                                <span class="w-2 h-1 bg-blue-400 rounded-full"></span>
                                <span class="w-2 h-1 bg-amber-400 rounded-full"></span>
                                <span class="w-2 h-1 bg-cyan-400 rounded-full"></span>
                            </div>

                            <div class="flex flex-col items-center cursor-pointer">
                                <div class="animate-bubble-float bg-white/95 border border-orange-400 text-gray-800 font-bold px-2 py-1 rounded-md text-[10px] shadow-sm mb-1 flex flex-col items-center font-mono whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-orange-600 font-bold">
                                        <i class="fas fa-brain text-[9px]"></i>
                                        <span>Claude Code</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] text-emerald-600 font-semibold mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>⚡ 85 tok/s</span>
                                    </div>
                                </div>
                                <div class="animate-pixel-bob animate-typing">
                                    <svg width="38" height="38" viewBox="0 0 32 32" class="drop-shadow-sm">
                                        <rect x="10" y="6" width="12" height="6" fill="#ea580c"/>
                                        <rect x="8" y="10" width="16" height="6" fill="#ea580c"/>
                                        <rect x="6" y="14" width="4" height="10" fill="#f97316"/>
                                        <rect x="22" y="14" width="4" height="10" fill="#f97316"/>
                                        <rect x="10" y="12" width="12" height="10" fill="#fed7aa"/>
                                        <rect x="12" y="15" width="2" height="3" fill="#1e293b"/>
                                        <rect x="18" y="15" width="2" height="3" fill="#1e293b"/>
                                        <rect x="11" y="22" width="10" height="7" fill="#f8fafc"/>
                                        <rect x="14" y="22" width="4" height="7" fill="#ea580c"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- 4. CURSOR (Mascot Pixel hồng công nghệ - Chạy qua lại kết nối) -->
                        <div class="flex items-end gap-2.5">
                            <div class="flex flex-col items-center animate-room-patrol-3 cursor-pointer">
                                <div class="animate-bubble-float-3 bg-white/95 border border-pink-400 text-gray-800 font-bold px-2 py-1 rounded-md text-[10px] shadow-sm mb-1 flex flex-col items-center font-mono whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-pink-600 font-bold">
                                        <i class="fas fa-code text-[9px]"></i>
                                        <span>Cursor Pro</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] text-emerald-600 font-semibold mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>⚡ 120 tok/s</span>
                                    </div>
                                </div>
                                <div class="animate-pixel-bob">
                                    <svg width="38" height="38" viewBox="0 0 34 32" class="drop-shadow-sm">
                                        <rect x="4" y="10" width="4" height="6" fill="#ec4899"/>
                                        <rect x="2" y="12" width="4" height="3" fill="#f43f5e"/>
                                        <rect x="26" y="10" width="4" height="6" fill="#ec4899"/>
                                        <rect x="28" y="12" width="4" height="3" fill="#f43f5e"/>
                                        <rect x="8" y="8" width="18" height="14" fill="#fbcfe8"/>
                                        <rect x="11" y="12" width="3" height="3" fill="#0f172a"/>
                                        <rect x="20" y="12" width="3" height="3" fill="#0f172a"/>
                                        <rect x="15" y="17" width="4" height="1" fill="#be185d"/>
                                        <rect x="10" y="22" width="14" height="6" fill="#f472b6"/>
                                        <rect x="8" y="26" width="3" height="3" fill="#ec4899"/>
                                        <rect x="23" y="26" width="3" height="3" fill="#ec4899"/>
                                    </svg>
                                </div>
                            </div>

                            <div class="w-10 h-7 bg-amber-900/90 border border-amber-950 flex flex-col justify-between p-1 rounded-xs">
                                <span class="w-3 h-1 bg-amber-700 self-center"></span>
                                <div class="flex justify-between">
                                    <span class="w-1 h-3 bg-amber-950"></span>
                                    <span class="w-1 h-3 bg-amber-950"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 5. DEEPSEEK (Nhân vật tóc xanh dương CẮM MÁY LÀM VIỆC & REASONING) -->
                        <div class="flex items-end gap-2.5">
                            <div class="flex flex-col items-center cursor-pointer">
                                <div class="animate-bubble-float-2 bg-white/95 border border-blue-500 text-gray-800 font-bold px-2 py-1 rounded-md text-[10px] shadow-sm mb-1 flex flex-col items-center font-mono whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-blue-700 font-bold">
                                        <i class="fas fa-lightbulb text-[9px] text-amber-500"></i>
                                        <span>DeepSeek R1</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] text-emerald-600 font-semibold mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Reasoning</span>
                                    </div>
                                </div>
                                <div class="animate-pixel-bob animate-typing">
                                    <svg width="38" height="38" viewBox="0 0 32 32" class="drop-shadow-sm">
                                        <rect x="9" y="6" width="14" height="6" fill="#1d4ed8"/>
                                        <rect x="7" y="10" width="18" height="6" fill="#2563eb"/>
                                        <rect x="6" y="14" width="4" height="11" fill="#3b82f6"/>
                                        <rect x="22" y="14" width="4" height="11" fill="#3b82f6"/>
                                        <rect x="10" y="12" width="12" height="10" fill="#fde68a"/>
                                        <rect x="12" y="15" width="2" height="3" fill="#0f172a"/>
                                        <rect x="18" y="15" width="2" height="3" fill="#0f172a"/>
                                        <rect x="11" y="22" width="10" height="7" fill="#1e3a8a"/>
                                    </svg>
                                </div>
                            </div>

                            <div class="w-12 h-9 bg-indigo-200 border border-indigo-300 rounded-t-sm p-1 flex flex-col justify-between relative">
                                <div class="w-full h-2.5 bg-indigo-300 rounded-xs"></div>
                                <div class="w-5 h-3 bg-gray-800 border border-gray-600 rounded-xs self-center flex items-center justify-center text-[5px] text-cyan-300 animate-typing">
                                    &gt;_r1
                                </div>
                            </div>
                        </div>

                        <!-- 6. OPENCODE (Robot xanh lá CẮM MÁY IN VĂN BẢN) -->
                        <div class="flex items-end gap-2.5">
                            <div class="flex flex-col items-center animate-room-patrol-1 cursor-pointer">
                                <div class="animate-bubble-float bg-white/95 border border-green-500 text-gray-800 font-bold px-2 py-1 rounded-md text-[10px] shadow-sm mb-1 flex flex-col items-center font-mono whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-green-700 font-bold">
                                        <i class="fas fa-robot text-[9px]"></i>
                                        <span>OpenCode</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] text-emerald-600 font-semibold mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>⚡ 100 tok/s</span>
                                    </div>
                                </div>
                                <div class="animate-pixel-bob">
                                    <svg width="36" height="38" viewBox="0 0 32 32" class="drop-shadow-sm">
                                        <rect x="7" y="8" width="18" height="15" fill="#15803d"/>
                                        <rect x="9" y="10" width="14" height="11" fill="#166534"/>
                                        <rect x="11" y="13" width="3" height="3" fill="#86efac"/>
                                        <rect x="18" y="13" width="3" height="3" fill="#86efac"/>
                                        <rect x="13" y="18" width="6" height="1.5" fill="#86efac"/>
                                        <rect x="15" y="4" width="2" height="4" fill="#22c55e"/>
                                        <rect x="14" y="3" width="4" height="2" fill="#4ade80"/>
                                        <rect x="10" y="23" width="12" height="6" fill="#14532d"/>
                                    </svg>
                                </div>
                            </div>

                            <div class="w-9 h-7 bg-slate-200 border border-slate-400 p-0.5 rounded-xs flex flex-col justify-between">
                                <span class="w-5 h-1 bg-white self-center border border-slate-300"></span>
                                <span class="w-6 h-1 bg-slate-400 self-center"></span>
                            </div>
                        </div>

                    </div>

                    <!-- BĂNG SÀN GỖ / PLINTH TÍM NHẠT - HIỆN TÊN CHÍNH XÁC CÁC MASCOT AI (KHÔNG HIỆN MÃ ĐƠN HÀNG) -->
                    <div class="w-full bg-[#e9d5ff]/80 border-t border-purple-300/80 px-4 py-2 flex items-center justify-between text-xs font-bold text-gray-800 font-sans">
                        <div class="w-32 text-center flex flex-col items-center">
                            <span class="text-sky-950 font-extrabold text-[12px]">Gemini</span>
                            <span class="text-[9px] font-mono text-sky-700 font-semibold">Google AI</span>
                        </div>
                        <div class="w-32 text-center flex flex-col items-center">
                            <span class="text-purple-950 font-extrabold text-[12px]">Codex</span>
                            <span class="text-[9px] font-mono text-purple-700 font-semibold">Plus / Pro</span>
                        </div>
                        <div class="w-36 text-center flex flex-col items-center">
                            <span class="text-orange-950 font-extrabold text-[12px]">Claude Code</span>
                            <span class="text-[9px] font-mono text-orange-700 font-semibold">Sonnet 3.5</span>
                        </div>
                        <div class="w-32 text-center flex flex-col items-center">
                            <span class="text-pink-950 font-extrabold text-[12px]">Cursor Pro</span>
                            <span class="text-[9px] font-mono text-pink-700 font-semibold">Tab Code</span>
                        </div>
                        <div class="w-36 text-center flex flex-col items-center">
                            <span class="text-blue-950 font-extrabold text-[12px]">DeepSeek</span>
                            <span class="text-[9px] font-mono text-blue-700 font-semibold">R1 Reason</span>
                        </div>
                        <div class="w-32 text-center flex flex-col items-center">
                            <span class="text-green-950 font-extrabold text-[12px]">OpenCode</span>
                            <span class="text-[9px] font-mono text-green-700 font-semibold">Terminal</span>
                        </div>
                    </div>

                    <!-- CHÂN TƯỜNG SỌC GỖ DỌC -->
                    <div class="w-full h-4 bg-gradient-to-r from-gray-200 via-gray-100 to-gray-200 border-t border-gray-300 flex">
                        <?php for ($i = 0; $i < 44; $i++): ?>
                            <div class="flex-1 border-r border-gray-300/60 h-full"></div>
                        <?php endfor; ?>
                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- SCRIPT TỰ ĐỘNG THU NHỎ CẢ HERO CAMP VÀ PIXEL ROOM VỪA MÀN HÌNH MOBILE -->
    <script>
    function fitPixelCanvasAll() {
        // 1. Thu nhỏ Hero Camp trên Mobile
        var campVp = document.getElementById('heroCampViewport');
        var campInner = document.getElementById('heroCampInner');
        if (campVp && campInner) {
            var w = campVp.clientWidth;
            var baseW = 820;
            if (w < baseW) {
                var s = w / baseW;
                campInner.style.transform = 'scale(' + s + ')';
                campInner.style.transformOrigin = 'top left';
                campVp.style.height = (campInner.offsetHeight * s) + 'px';
            } else {
                campInner.style.transform = 'none';
                campVp.style.height = 'auto';
            }
        }
        // 2. Thu nhỏ Pixel Room trên Mobile
        var roomVp = document.getElementById('pixelRoomViewport');
        var roomInner = document.getElementById('pixelRoomInner');
        if (roomVp && roomInner) {
            var rw = roomVp.clientWidth;
            var rBaseW = 880;
            if (rw < rBaseW) {
                var rs = rw / rBaseW;
                roomInner.style.transform = 'scale(' + rs + ')';
                roomInner.style.transformOrigin = 'top left';
                roomVp.style.height = (roomInner.offsetHeight * rs) + 'px';
            } else {
                roomInner.style.transform = 'none';
                roomVp.style.height = 'auto';
            }
        }
    }
    window.addEventListener('resize', fitPixelCanvasAll);
    window.addEventListener('DOMContentLoaded', fitPixelCanvasAll);
    setTimeout(fitPixelCanvasAll, 100);
    setTimeout(fitPixelCanvasAll, 500);
    setTimeout(fitPixelCanvasAll, 1200);
    </script>

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
                <div class="bg-gray-900 border border-blue-500/40 rounded-xl p-3 text-left mb-4 space-y-1.5 overflow-hidden">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-blue-400 font-bold uppercase text-[10px] flex items-center gap-1">
                            <i class="fas fa-link"></i> Base URL (OpenAI Compatible)
                        </span>
                        <button @click="saoChep('https://tiemai.io.vn/v1')" class="text-blue-400 hover:text-white text-[10px] font-bold flex items-center gap-1">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </div>
                    <code class="text-white font-mono text-xs bg-black/80 px-2.5 py-1.5 rounded-lg border border-gray-800 block select-all break-all">https://tiemai.io.vn/v1</code>
                    <p class="text-[10px] text-gray-400 break-all">Chat Endpoint: <code class="text-gray-300 break-all">https://tiemai.io.vn/v1/chat/completions</code></p>
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
                                this.keyDaTraVe = 'Đơn hàng đã hoàn tất nhưng chưa nhận được dữ liệu giao hàng. Vui lòng liên hệ hỗ trợ.'
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
                                this.keyDaTraVe = 'Đơn hàng đã hoàn tất nhưng chưa nhận được dữ liệu giao hàng. Vui lòng liên hệ hỗ trợ.'
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
