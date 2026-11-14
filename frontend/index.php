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
    <section class="relative overflow-hidden bg-[#060913] border-b border-gray-800/80 pt-8 sm:pt-14 pb-0">
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
            <!-- NỘI DUNG VĂN BẢN HERO SECTION -->
            <div class="max-w-2xl text-left space-y-3.5 sm:space-y-4">
                <!-- Tag AI API MARKETPLACE -->
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-900/90 border border-gray-700/80 text-gray-300 text-[11px] font-bold uppercase tracking-wider shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    <span>AI API MARKETPLACE</span>
                </div>

                <!-- Tiêu đề lớn -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.12]">
                    API AI chính hãng<br>
                    <span class="text-amber-400">Mua là dùng ngay</span>
                </h1>

                <!-- Mô tả -->
                <p class="text-xs sm:text-sm text-gray-400 max-w-xl leading-relaxed">
                    Chuyên API AI chính hãng Cursor, Claude, Codex, Gemini... giá rẻ, giao ngay.
                </p>

                <!-- Nút hành động -->
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <a href="#san-pham" class="px-6 py-2.5 sm:py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-950 font-black text-xs sm:text-sm transition duration-200 shadow-lg shadow-amber-500/25 flex items-center gap-2">
                        <i class="fas fa-cart-shopping"></i>
                        <span>Xem sản phẩm</span>
                    </a>
                    <a href="/docs" class="px-5 py-2.5 sm:py-3 rounded-xl bg-[#111827] hover:bg-gray-800 text-gray-200 hover:text-white font-bold text-xs sm:text-sm border border-gray-700 transition duration-200 flex items-center gap-2">
                        <i class="fas fa-book-open text-blue-400"></i>
                        <span>Hướng dẫn</span>
                    </a>
                    <button type="button" @click="moPopupKiemTraKey = true" class="px-5 py-2.5 sm:py-3 rounded-xl bg-[#111827] hover:bg-gray-800 text-gray-300 hover:text-white font-bold text-xs sm:text-sm border border-gray-700 transition duration-200 flex items-center gap-2">
                        <i class="fas fa-magnifying-glass text-amber-400"></i>
                        <span>Kiểm Tra Key</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- DÂY CỜ LỄ HỘI TRANG TRÍ (PENNANT FLAGS STRINGS) -->
        <div class="w-full relative mt-8 sm:mt-12 overflow-hidden select-none pointer-events-none">
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

    <!-- DẢI NỀN TẢNG ĐỐI TÁC & CÔNG CỤ (MARQUEE 2 CHIỀU ĐỐI XỨNG CHUẨN ẢNH 3 - VÒNG LẶP VÔ TẬN KHÔNG KHOẢNG TRỐNG) -->
    <section class="py-9 bg-[#0B0F19] border-b border-gray-800/80 overflow-hidden relative">
        <div class="max-w-7xl mx-auto px-4 mb-5 text-center">
            <h2 class="text-[11px] sm:text-xs font-bold uppercase tracking-[0.25em] text-gray-400">
                HOẠT ĐỘNG VỚI CÁC NHÀ CUNG CẤP VÀ CÔNG CỤ
            </h2>
        </div>

        <!-- Màng gradient mờ dần 2 bên mép màn hình chống chói -->
        <div class="absolute left-0 top-0 bottom-0 w-14 sm:w-28 bg-gradient-to-r from-[#0B0F19] to-transparent z-10 pointer-events-none"></div>
        <div class="absolute right-0 top-0 bottom-0 w-14 sm:w-28 bg-gradient-to-l from-[#0B0F19] to-transparent z-10 pointer-events-none"></div>

        <div class="space-y-3">
            <!-- HÀNG 1: CHẠY TỪ PHẢI SANG TRÁI (CÁC NHÀ CUNG CẤP AI) - 2 TRACK ANH EM CHẠY VÔ TẬN KHÔNG GIÁN ĐOẠN -->
            <div class="marquee-container flex overflow-hidden w-full select-none">
                <div class="flex items-center gap-3 shrink-0 animate-marquee-left">
                    <?php 
                    $nhaCungCapAI = [
                        ['ten' => 'DeepSeek',   'icon' => 'fa-solid fa-brain',          'color' => '#3B82F6'],
                        ['ten' => 'Qwen',       'icon' => 'fa-solid fa-microchip',      'color' => '#8B5CF6'],
                        ['ten' => 'KIMI',       'icon' => 'fa-solid fa-moon',           'color' => '#06B6D4'],
                        ['ten' => 'MINIMAX',    'icon' => 'fa-solid fa-wave-square',    'color' => '#F43F5E'],
                        ['ten' => '智谱·AI',     'icon' => 'fa-solid fa-robot',          'color' => '#38BDF8'],
                        ['ten' => 'Mistral AI', 'icon' => 'fa-solid fa-fire-flame-curved','color' => '#F97316'],
                        ['ten' => 'Grok',       'icon' => 'fa-solid fa-slash',          'color' => '#E2E8F0'],
                        ['ten' => 'Llama',      'icon' => 'fa-solid fa-infinity',       'color' => '#3B82F6'],
                        ['ten' => 'OpenAI',     'icon' => 'fa-solid fa-circle-notch',   'color' => '#10B981'],
                        ['ten' => 'Gemini',     'icon' => 'fa-solid fa-sparkles',       'color' => '#F59E0B'],
                    ];
                    // Lặp 3 lần trong mỗi track để track rộng hơn 3500px, không bao giờ hở khoảng trống trên mọi màn hình
                    for ($k = 0; $k < 3; $k++):
                        foreach ($nhaCungCapAI as $ncc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <i class="<?= $ncc['icon'] ?>" style="color: <?= $ncc['color'] ?>"></i>
                                <span><?= $ncc['ten'] ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
                </div>
                <div class="flex items-center gap-3 shrink-0 animate-marquee-left" aria-hidden="true">
                    <?php 
                    for ($k = 0; $k < 3; $k++):
                        foreach ($nhaCungCapAI as $ncc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <i class="<?= $ncc['icon'] ?>" style="color: <?= $ncc['color'] ?>"></i>
                                <span><?= $ncc['ten'] ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
                </div>
            </div>

            <!-- HÀNG 2: CHẠY THEO CHIỀU NGƯỢC LẠI (CÁC CÔNG CỤ / AI AGENT) - 2 TRACK ANH EM CHẠY VÔ TẬN -->
            <div class="marquee-container flex overflow-hidden w-full select-none">
                <div class="flex items-center gap-3 shrink-0 animate-marquee-right">
                    <?php 
                    $congCuAI = [
                        ['ten' => 'Codex',         'icon' => 'fa-solid fa-code',             'color' => '#818CF8'],
                        ['ten' => 'Claude Code',   'icon' => 'fa-solid fa-terminal',         'color' => '#FB923C'],
                        ['ten' => 'Cline',         'icon' => 'fa-solid fa-headset',          'color' => '#38BDF8'],
                        ['ten' => 'Windsurf',      'icon' => 'fa-solid fa-wind',             'color' => '#2DD4BF'],
                        ['ten' => 'Kilo Code',     'icon' => 'fa-solid fa-layer-group',      'color' => '#FACC15'],
                        ['ten' => 'Roo Code',      'icon' => 'fa-solid fa-compass',          'color' => '#F472B6'],
                        ['ten' => 'OpenCode',      'icon' => 'fa-solid fa-laptop-code',      'color' => '#4ADE80'],
                        ['ten' => 'Cherry Studio', 'icon' => 'fa-solid fa-cubes',            'color' => '#F43F5E'],
                        ['ten' => 'Obsidian',      'icon' => 'fa-solid fa-gem',              'color' => '#C084FC'],
                        ['ten' => 'Cursor',        'icon' => 'fa-solid fa-square-full',      'color' => '#60A5FA'],
                    ];
                    for ($k = 0; $k < 3; $k++):
                        foreach ($congCuAI as $cc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <i class="<?= $cc['icon'] ?>" style="color: <?= $cc['color'] ?>"></i>
                                <span><?= $cc['ten'] ?></span>
                            </div>
                        <?php endforeach; 
                    endfor; ?>
                </div>
                <div class="flex items-center gap-3 shrink-0 animate-marquee-right" aria-hidden="true">
                    <?php 
                    for ($k = 0; $k < 3; $k++):
                        foreach ($congCuAI as $cc): ?>
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-900/80 border border-gray-800 hover:border-gray-700 text-gray-200 text-xs font-semibold whitespace-nowrap shadow-sm transition">
                                <i class="<?= $cc['icon'] ?>" style="color: <?= $cc['color'] ?>"></i>
                                <span><?= $cc['ten'] ?></span>
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
