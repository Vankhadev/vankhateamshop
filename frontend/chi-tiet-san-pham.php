<?php
// ========================================================
// TRANG CHI TIẾT SẢN PHẨM (PRODUCT DETAIL PAGE)
// vankhateamshop - Đầy đủ Breadcrumb, Nhãn dán tùy chỉnh, 4 Thẻ cam kết,
// 3 Tab thông tin (Chi tiết, Bảo hành, FAQ), Sản phẩm liên quan & Popup VietQR ACB
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Lấy slug hoặc id sản phẩm từ URL
$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

$product = null;
if (!empty($slug)) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, c.color AS category_color,
               COALESCE((SELECT m.stock FROM `supplier_product_mappings` m WHERE m.product_id = p.id AND m.is_active = 1 ORDER BY m.priority ASC LIMIT 1), 999) AS stock
        FROM `products` p 
        JOIN `categories` c ON p.category_id = c.id 
        WHERE (p.slug = ? OR p.sku = ?) AND p.is_active = 1 
        LIMIT 1
    ");
    $stmt->execute([$slug, $slug]);
    $product = $stmt->fetch();
}

if (!$product && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, c.color AS category_color,
               COALESCE((SELECT m.stock FROM `supplier_product_mappings` m WHERE m.product_id = p.id AND m.is_active = 1 ORDER BY m.priority ASC LIMIT 1), 999) AS stock
        FROM `products` p 
        JOIN `categories` c ON p.category_id = c.id 
        WHERE p.id = ? AND p.is_active = 1 
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

// Nếu không tìm thấy sản phẩm, chuyển về trang danh sách sản phẩm
if (!$product) {
    header('Location: /san-pham');
    exit;
}

// Giải mã mảng tính năng JSON
$features = [];
if (!empty($product['features'])) {
    $decoded = json_decode($product['features'], true);
    if (is_array($decoded)) {
        $features = $decoded;
    }
}
if (empty($features)) {
    $features = [
        'Tốc độ cao, đường truyền riêng biệt',
        'Sử dụng All model và Max',
        'Có giới hạn request: ' . ($product['request_count'] ?? 'Tiêu chuẩn'),
        'Bảo hành trong thời gian sử dụng ' . ($product['duration_days'] ?? 30) . ' ngày'
    ];
}

// Lấy danh sách các sản phẩm liên quan cùng danh mục hoặc hot
$stmtRelated = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug 
    FROM `products` p 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.id != ? AND p.is_active = 1 AND (p.category_id = ? OR p.is_hot = 1)
    ORDER BY p.price ASC 
    LIMIT 6
");
$stmtRelated->execute([$product['id'], $product['category_id']]);
$relatedProducts = $stmtRelated->fetchAll();

// Thông tin người dùng hiện tại (nếu có đăng nhập)
$userHienTai = null;
if (!empty($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT id, name, email, balance, role FROM `users` WHERE `id` = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $userHienTai = $stmtUser->fetch();
}

// Tính % giảm giá nếu có
$ptGiam = 0;
if (!empty($product['original_price']) && $product['original_price'] > $product['price']) {
    $ptGiam = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);
}

$siteNameDisplay = layCaiDatHeThong('site_name', SHOP_NAME);
$soZaloDisplay = layCaiDatHeThong('site_contact_zalo', SHOP_ZALO);
$teleDisplay = layCaiDatHeThong('site_contact_telegram', SHOP_TELEGRAM);
$isOutOfStock = (isset($product['stock']) && $product['stock'] !== null && (int)$product['stock'] <= 0);

// Phân loại danh sách Model hỗ trợ theo gói
$catSlug = strtolower($product['category_slug'] ?? '');
$prodName = strtolower($product['name'] ?? '');

$supportedModels = [];

// 1. ƯU TIÊN SỐ 1: NẾU SẢN PHẨM ĐÃ ĐƯỢC ADMIN CẤU HÌNH MODEL RIÊNG BIỆT
if (!empty($product['supported_models'])) {
    $rawModels = trim($product['supported_models']);
    $decodedJson = json_decode($rawModels, true);
    if (is_array($decodedJson)) {
        foreach ($decodedJson as $item) {
            if (is_array($item) && !empty($item['name'])) {
                $supportedModels[] = [
                    'name'   => trim($item['name']),
                    'desc'   => trim($item['desc'] ?? ('Mô hình ' . $item['name'] . ' định tuyến tốc độ cao')),
                    'status' => trim($item['status'] ?? 'Online'),
                    'tag'    => trim($item['tag'] ?? 'Hỗ trợ')
                ];
            } elseif (is_string($item) && trim($item) !== '') {
                $mName = trim($item);
                $tag = 'Active';
                if (stripos($mName, 'claude') !== false) $tag = 'Claude AI';
                elseif (stripos($mName, 'gpt') !== false) $tag = 'OpenAI';
                elseif (stripos($mName, 'deepseek') !== false) $tag = 'Reasoning';
                elseif (stripos($mName, 'cursor') !== false) $tag = 'Cursor';
                elseif (stripos($mName, 'gemini') !== false) $tag = 'Google';
                elseif (stripos($mName, 'grok') !== false) $tag = 'xAI';

                $supportedModels[] = [
                    'name'   => $mName,
                    'desc'   => 'Mô hình ' . $mName . ' tối ưu lập trình và logic AI',
                    'status' => 'Online',
                    'tag'    => $tag
                ];
            }
        }
    } else {
        // Tách chuỗi theo dấu phẩy hoặc dòng
        $listItems = preg_split('/[,\r\n]+/', $rawModels);
        foreach ($listItems as $it) {
            $mName = trim($it);
            if (!empty($mName)) {
                $tag = 'Active';
                if (stripos($mName, 'claude') !== false) $tag = 'Claude AI';
                elseif (stripos($mName, 'gpt') !== false) $tag = 'OpenAI';
                elseif (stripos($mName, 'deepseek') !== false) $tag = 'Reasoning';
                elseif (stripos($mName, 'cursor') !== false) $tag = 'Cursor';
                elseif (stripos($mName, 'gemini') !== false) $tag = 'Google';
                elseif (stripos($mName, 'grok') !== false) $tag = 'xAI';

                $supportedModels[] = [
                    'name'   => $mName,
                    'desc'   => 'Mô hình ' . $mName . ' định tuyến Gateway tốc độ cao',
                    'status' => 'Online',
                    'tag'    => $tag
                ];
            }
        }
    }
}

// 2. NẾU CHƯA CẤU HÌNH MODEL RIÊNG THÌ FALLBACK MẶC ĐỊNH THEO DANH MỤC
if (empty($supportedModels)) {
    if (strpos($catSlug, 'cursor') !== false || strpos($prodName, 'cursor') !== false) {
        $supportedModels = [
            ['name' => 'claude-3-5-sonnet', 'desc' => 'Claude 3.5 Sonnet đỉnh cao lập trình & refactor code', 'status' => 'Online', 'tag' => 'Khuyên dùng'],
            ['name' => 'gpt-4o', 'desc' => 'GPT-4o đa năng giải quyết logic phức tạp', 'status' => 'Online', 'tag' => 'Max Speed'],
            ['name' => 'deepseek-reasoner', 'desc' => 'DeepSeek R1 suy luận chuỗi tư duy (Chain of Thought)', 'status' => 'Online', 'tag' => 'R1 Reasoning'],
            ['name' => 'deepseek-chat', 'desc' => 'DeepSeek V3 phản hồi siêu tốc, độ trễ thấp', 'status' => 'Online', 'tag' => 'V3 Ultra Fast'],
            ['name' => 'cursor-small', 'desc' => 'Mô hình gợi ý inline tab completion tức thì trong Cursor', 'status' => 'Online', 'tag' => 'Tab Autocomplete'],
        ];
    } elseif (strpos($catSlug, 'claude') !== false || strpos($prodName, 'claude') !== false) {
        $supportedModels = [
            ['name' => 'claude-3-5-sonnet', 'desc' => 'Claude 3.5 Sonnet đỉnh cao trí tuệ lập trình', 'status' => 'Online', 'tag' => 'Flagship'],
            ['name' => 'claude-3-opus', 'desc' => 'Claude 3 Opus suy luận văn bản và ngữ cảnh lớn', 'status' => 'Online', 'tag' => 'Deep Thinking'],
            ['name' => 'claude-3-5-haiku', 'desc' => 'Claude 3.5 Haiku tốc độ cực cao, tiết kiệm chi phí', 'status' => 'Online', 'tag' => 'Fastest'],
        ];
    } elseif (strpos($catSlug, 'deepseek') !== false || strpos($prodName, 'deepseek') !== false) {
        $supportedModels = [
            ['name' => 'deepseek-reasoner', 'desc' => 'DeepSeek R1 mô hình suy luận chuỗi bước đột phá', 'status' => 'Online', 'tag' => 'R1 Reasoner'],
            ['name' => 'deepseek-chat', 'desc' => 'DeepSeek V3 671B phản hồi siêu tốc cho chat & code', 'status' => 'Online', 'tag' => 'V3 Chat'],
        ];
    } elseif (strpos($catSlug, 'gemini') !== false || strpos($prodName, 'gemini') !== false) {
        $supportedModels = [
            ['name' => 'gemini-1.5-pro', 'desc' => 'Gemini 1.5 Pro ngữ cảnh 2 triệu token siêu khổng lồ', 'status' => 'Online', 'tag' => '2M Context'],
            ['name' => 'gemini-2.0-flash', 'desc' => 'Gemini 2.0 Flash thế hệ mới nhất siêu nhanh', 'status' => 'Online', 'tag' => 'Next Gen'],
            ['name' => 'gemini-1.5-flash', 'desc' => 'Gemini 1.5 Flash tối ưu tốc độ và độ phản hồi', 'status' => 'Online', 'tag' => 'Ultra Low Latency'],
        ];
    } elseif (strpos($catSlug, 'grok') !== false || strpos($prodName, 'grok') !== false) {
        $supportedModels = [
            ['name' => 'grok-2', 'desc' => 'Grok 2 của xAI xử lý thông tin thông minh và sắc sảo', 'status' => 'Online', 'tag' => 'xAI Core'],
            ['name' => 'grok-2-mini', 'desc' => 'Grok 2 Mini tốc độ cao và tối ưu độ trễ', 'status' => 'Online', 'tag' => 'Fast'],
        ];
    } elseif (strpos($catSlug, 'software') !== false || strpos($prodName, 'tungdv') !== false || strpos(strtolower($product['sku'] ?? ''), 'swf') !== false || strpos($prodName, 'studio pro') !== false || strpos($prodName, 'autoflow') !== false) {
        $supportedModels = [
            ['name' => 'Windows 10/11 64-bit', 'desc' => 'Hệ điều hành tương thích tối ưu', 'status' => 'Sẵn sàng', 'tag' => 'OS Support'],
            ['name' => 'AI Engine Offline/Cloud', 'desc' => 'Chạy kết hợp trình duyệt miễn phí, không tốn API', 'status' => 'Hoạt động', 'tag' => 'Engine'],
            ['name' => 'Auto Update 24/7', 'desc' => 'Cập nhật bản mới miễn phí trong thời gian sử dụng', 'status' => 'Bảo đảm', 'tag' => 'Lifetime'],
        ];
    } else {
        $supportedModels = [
            ['name' => 'Full AI Model Gateway', 'desc' => 'Hỗ trợ các model tương thích chuẩn OpenAI API', 'status' => 'Online', 'tag' => 'Multi-Model'],
            ['name' => 'High-Speed Proxy', 'desc' => 'Định tuyến máy chủ Việt Nam tốc độ cao', 'status' => 'Online', 'tag' => 'Low Latency'],
        ];
    }
}

$canonicalUrl = (defined('SITE_URL') ? SITE_URL : 'https://tiemai.io.vn') . '/san-pham/' . htmlspecialchars($product['slug'] ?? $product['sku']);
$seoKeywords = htmlspecialchars($product['name']) . ", mua " . htmlspecialchars($product['name']) . ", api " . htmlspecialchars($product['category_name']) . ", key " . htmlspecialchars($product['name']) . " gia re, dai ly api ai, mua api cursor, mua api claude";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> Giá Rẻ - Tự Động Giao Key 24/7 | <?= htmlspecialchars($siteNameDisplay) ?></title>
    
    <!-- Meta SEO Chuyên Nghiệp -->
    <meta name="description" content="Mua <?= htmlspecialchars($product['name']) ?> chính hãng giá rẻ: <?= htmlspecialchars(mb_substr(strip_tags($product['description'] ?? 'API AI Agent, Cursor Pro, Claude 3.5 Sonnet tự động giao key 24/7 qua VietQR ACB.'), 0, 160)) ?>">
    <meta name="keywords" content="<?= $seoKeywords ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph (Facebook, Zalo) -->
    <meta property="og:type" content="product">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($siteNameDisplay) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(mb_substr(strip_tags($product['description'] ?? 'Cung cấp API AI Agent, Cursor Pro, Claude 3.5 Sonnet tự động giao key 24/7.'), 0, 200)) ?>">
    <meta property="og:image" content="<?= !empty($product['image']) ? $product['image'] : ((defined('SITE_URL') ? SITE_URL : 'https://tiemai.io.vn') . '/assets/images/banner.png') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteNameDisplay) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($siteNameDisplay) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars(mb_substr(strip_tags($product['description'] ?? 'Cung cấp API AI Agent, Cursor Pro, Claude 3.5 Sonnet tự động giao key 24/7.'), 0, 200)) ?>">

    <!-- Schema.org JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org/",
      "@type": "Product",
      "name": "<?= htmlspecialchars($product['name']) ?>",
      "image": "<?= !empty($product['image']) ? $product['image'] : ((defined('SITE_URL') ? SITE_URL : 'https://tiemai.io.vn') . '/assets/images/banner.png') ?>",
      "description": "<?= htmlspecialchars(strip_tags($product['description'] ?? '')) ?>",
      "sku": "<?= htmlspecialchars($product['sku']) ?>",
      "offers": {
        "@type": "Offer",
        "url": "<?= $canonicalUrl ?>",
        "priceCurrency": "VND",
        "price": "<?= (float)$product['price'] ?>",
        "availability": "https://schema.org/InStock",
        "seller": {
          "@type": "Organization",
          "name": "<?= htmlspecialchars($siteNameDisplay) ?>"
        }
      }
    }
    </script>

    <!-- CSS & Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        .gradient-border {
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.9) 100%);
        }
    </style>
</head>
<body x-data="chiTietSanPhamApp()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">
        
        <!-- 1. BREADCRUMB ĐIỀU HƯỚNG CHUẨN SEO -->
        <nav class="flex items-center space-x-2 text-xs text-gray-400 mb-6 overflow-x-auto whitespace-nowrap pb-2">
            <a href="/" class="hover:text-white transition flex items-center gap-1.5">
                <i class="fas fa-house"></i>
                <span>Trang Chủ</span>
            </a>
            <span class="text-gray-600">›</span>
            <a href="/san-pham" class="hover:text-white transition">Sản Phẩm</a>
            <span class="text-gray-600">›</span>
            <a href="/san-pham?category=<?= htmlspecialchars($product['category_slug']) ?>" class="hover:text-blue-400 transition font-medium">
                <?= htmlspecialchars($product['category_name']) ?>
            </a>
            <span class="text-gray-600">›</span>
            <span class="text-gray-200 font-bold truncate max-w-[200px] sm:max-w-none">
                <?= htmlspecialchars($product['name']) ?>
            </span>
        </nav>

        <!-- 2. KHỐI TRÌNH BÀY CHÍNH CỦA SẢN PHẨM (HERO PRODUCT SECTION) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            
            <!-- CỘT TRÁI: ẢNH MINH HỌA / CARD THƯƠNG HIỆU (4 CỘT) -->
            <div class="lg:col-span-5 space-y-4">
                <div class="gradient-border rounded-3xl p-6 sm:p-8 flex flex-col items-center justify-center text-center relative overflow-hidden shadow-2xl min-h-[380px]">
                    <!-- Hiệu ứng vầng sáng phía sau -->
                    <div class="absolute -top-20 -left-20 w-48 h-48 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-20 -right-20 w-48 h-48 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>

                    <!-- Badge Trạng thái & Giảm giá -->
                    <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Hoạt động
                        </span>
                        <?php if ($ptGiam > 0): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-rose-600 text-white shadow-md">
                                -<?= $ptGiam ?>%
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="absolute top-4 right-4">
                        <span class="px-2.5 py-1 rounded-xl text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/30">
                            SKU: <?= htmlspecialchars($product['sku']) ?>
                        </span>
                    </div>

                    <!-- Biểu tượng chính hoặc ảnh gói -->
                    <div class="my-6 relative">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-36 h-36 object-contain rounded-2xl shadow-xl">
                        <?php else: ?>
                            <div class="w-28 h-28 rounded-3xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-purple-600 flex items-center justify-center text-white text-5xl shadow-2xl shadow-blue-600/30">
                                <i class="fas <?= !empty($product['category_icon']) ? htmlspecialchars($product['category_icon']) : 'fa-robot' ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tên danh mục & Nhãn bảo đảm -->
                    <h3 class="text-sm font-bold text-gray-300 mb-1"><?= htmlspecialchars($product['category_name']) ?></h3>
                    <div class="flex items-center justify-center gap-2 text-xs text-gray-400">
                        <span class="flex items-center gap-1 text-emerald-400 font-semibold">
                            <i class="fas fa-shield-check"></i> API Chính Hãng
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1 text-amber-400 font-semibold">
                            <i class="fas fa-bolt"></i> Giao Tự Động
                        </span>
                    </div>
                </div>

                <!-- 8 NHÃN DÁN ĐẶC TRƯNG CỦA GÓI SẢN PHẨM (CUSTOM BADGES) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                    <div class="p-2.5 rounded-xl bg-gray-900/90 border border-gray-800">
                        <div class="text-blue-400 font-black text-sm"><?= htmlspecialchars($product['request_count'] ?? 'Chuẩn') ?></div>
                        <div class="text-[10px] text-gray-400 mt-0.5">Dung Lượng</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-gray-900/90 border border-gray-800">
                        <div class="text-emerald-400 font-black text-sm"><?= count($supportedModels) ?>+ Model</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">Hỗ Trợ Gói</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-gray-900/90 border border-gray-800">
                        <div class="text-purple-400 font-black text-sm">24/7</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">Hỗ Trợ Zalo</div>
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI: THÔNG TIN CHI TIẾT, GIÁ BÁN & HÀNH ĐỘNG (7 CỘT) -->
            <div class="lg:col-span-7 flex flex-col justify-between space-y-6">
                
                <div>
                    <!-- Nhãn tag sản phẩm -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
                            API AI
                        </span>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            API Chính Hãng
                        </span>
                        <?php if (!empty($product['badge'])): ?>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                <?= htmlspecialchars($product['badge']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Tên sản phẩm chính -->
                    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-snug">
                        <?= htmlspecialchars($product['name']) ?>
                    </h1>

                    <p class="text-xs sm:text-sm text-gray-400 mt-2 leading-relaxed">
                        <?= htmlspecialchars($product['description'] ?? 'Giải pháp tích hợp API AI cao cấp cho lập trình viên, sinh viên và doanh nghiệp với tốc độ phản hồi cao và độ trễ thấp.') ?>
                    </p>

                    <!-- Khung Giá Bán -->
                    <div class="my-5 p-4 sm:p-5 rounded-2xl bg-gray-900/90 border border-gray-800 flex flex-wrap items-baseline gap-3 sm:gap-4">
                        <div class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono">
                            <?= number_format($product['price'], 0, ',', '.') ?> VNĐ
                        </div>

                        <span class="ml-auto inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-blue-900/40 text-blue-300 border border-blue-700/50">
                            <i class="fas fa-tag"></i> Giá Đại Lý
                        </span>
                    </div>

                    <!-- Checklist tính năng của gói -->
                    <div class="space-y-2 mb-6">
                        <span class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">Đặc điểm nổi bật của gói:</span>
                        <?php foreach ($features as $f): ?>
                            <div class="flex items-center gap-2.5 text-xs sm:text-sm text-gray-300">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs shrink-0">
                                    <i class="fas fa-check"></i>
                                </span>
                                <span><?= htmlspecialchars($f) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 4 THẺ CAM KẾT DỊCH VỤ (SERVICE HIGHLIGHTS) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                        <div class="p-3.5 rounded-xl bg-gray-900/70 border border-gray-800/80 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center text-sm shrink-0">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Bảo hành </div>
                                <div class="text-[11px] text-gray-400">Khi lỗi từ nhà cung cấp</div>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-gray-900/70 border border-gray-800/80 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-sm shrink-0">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Giao ngay lập tức</div>
                                <div class="text-[11px] text-gray-400">Tự động sau thanh toán</div>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-gray-900/70 border border-gray-800/80 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-amber-600/20 text-amber-400 flex items-center justify-center text-sm shrink-0">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Hỗ trợ 24/7</div>
                                <div class="text-[11px] text-gray-400">Zalo & Telegram chuyên viên</div>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-gray-900/70 border border-gray-800/80 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center text-sm shrink-0">
                                <i class="fas fa-rotate"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Đổi mới nếu lỗi</div>
                                <div class="text-[11px] text-gray-400">Thuộc phạm vi bảo hành</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- NÚT MUA HÀNG & HÀNH ĐỘNG CHÍNH -->
                <div class="pt-2 border-t border-gray-800/80 space-y-3">
                    <?php if ($isOutOfStock): ?>
                        <div class="p-3.5 rounded-2xl bg-red-950/70 border border-red-800 text-red-300 text-xs font-bold text-center flex items-center justify-center gap-2 shadow-lg">
                            <i class="fas fa-circle-exclamation text-base text-red-400"></i>
                            <span>Sản phẩm này hiện đang TẠM HẾT HÀNG tại toàn bộ kho nguồn đối tác. Hệ thống sẽ tự động mở lại khi có hàng mới!</span>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <!-- Nút Mua ngay (Mở popup VietQR ACB) -->
                        <?php if ($isOutOfStock): ?>
                            <button type="button" disabled class="flex-1 py-3.5 px-6 rounded-2xl bg-gray-800 text-gray-500 font-extrabold text-sm cursor-not-allowed flex items-center justify-center gap-2 border border-gray-700">
                                <i class="fas fa-ban"></i>
                                <span>Tạm Hết Hàng</span>
                            </button>
                        <?php else: ?>
                            <button @click="moThanhToan(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', <?= $product['price'] ?>)" class="flex-1 py-3.5 px-6 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-extrabold text-sm transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                                <i class="fas fa-qrcode text-base"></i>
                                <span>Mua Ngay (VietQR Tự Động)</span>
                            </button>
                        <?php endif; ?>

                        <!-- Nút Mua bằng số dư ví (nếu có tài khoản) -->
                        <?php if (!empty($userHienTai)): ?>
                            <button @click="<?= $isOutOfStock ? "alert('Gói này đang tạm hết hàng!')" : "muaBangVi({$product['id']})" ?>" <?= $isOutOfStock ? 'disabled' : '' ?> :disabled="dangXuLyVi" class="py-3.5 px-5 rounded-2xl <?= $isOutOfStock ? 'bg-gray-800 text-gray-500 cursor-not-allowed border border-gray-700' : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-green-600/30' ?> font-bold text-xs transition flex items-center justify-center gap-2">
                                <i class="fas fa-wallet" :class="dangXuLyVi ? 'fa-spin' : ''"></i>
                                <span>Mua Bằng Ví (<?= number_format($userHienTai['balance'], 0, ',', '.') ?>đ)</span>
                            </button>
                        <?php else: ?>
                            <a href="/login" class="py-3.5 px-5 rounded-2xl bg-gray-800 hover:bg-gray-700 text-gray-200 font-bold text-xs transition border border-gray-700 flex items-center justify-center gap-2">
                                <i class="fas fa-user"></i>
                                <span>Đăng Nhập Ví</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Hotline hỗ trợ -->
                    <div class="flex items-center justify-between text-xs text-gray-400 pt-1">
                        <span>Cần tư vấn hỗ trợ kỹ thuật?</span>
                        <div class="flex items-center gap-3">
                            <a href="https://zalo.me/<?= htmlspecialchars($soZaloDisplay) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:underline font-semibold flex items-center gap-1">
                                <i class="fas fa-comment-dots"></i> Zalo CSKH (<?= htmlspecialchars($soZaloDisplay) ?>)
                            </a>
                            <span>•</span>
                            <a href="<?= htmlspecialchars($teleDisplay) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:underline font-semibold flex items-center gap-1">
                                <i class="fab fa-telegram"></i> Telegram
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- 3. KHỐI CÁC TABS NỘI DUNG (THÔNG TIN SẢN PHẨM / BẢO HÀNH / FAQ) -->
        <div class="gradient-border rounded-3xl p-6 sm:p-8 mb-12 shadow-xl" x-data="{ tabHienTai: 'thong_tin' }">
            
            <!-- Điều hướng chuyển tab -->
            <div class="flex items-center gap-2 border-b border-gray-800 pb-4 mb-6 overflow-x-auto whitespace-nowrap">
                <button @click="tabHienTai = 'thong_tin'" :class="tabHienTai === 'thong_tin' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'bg-gray-900/80 text-gray-400 hover:text-white'" class="px-5 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2">
                    <i class="fas fa-circle-info"></i>
                    <span>Thông Tin Sản Phẩm & Hướng Dẫn</span>
                </button>

                <button @click="tabHienTai = 'bao_hanh'" :class="tabHienTai === 'bao_hanh' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'bg-gray-900/80 text-gray-400 hover:text-white'" class="px-5 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2">
                    <i class="fas fa-shield-halved"></i>
                    <span>Chính Sách Bảo Hành</span>
                </button>

                <button @click="tabHienTai = 'faq'" :class="tabHienTai === 'faq' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'bg-gray-900/80 text-gray-400 hover:text-white'" class="px-5 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2">
                    <i class="fas fa-circle-question"></i>
                    <span>Câu Hỏi Thường Gặp (FAQ)</span>
                </button>
            </div>

            <!-- NỘI DUNG TAB 1: THÔNG TIN SẢN PHẨM & TÍNH NĂNG -->
            <div x-show="tabHienTai === 'thong_tin'" class="space-y-6 text-xs sm:text-sm text-gray-300 leading-relaxed">
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-white mb-2 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center text-xs">1</span>
                        <span>Giới Thiệu Sản Phẩm</span>
                    </h3>
                    <p>
                        Sản phẩm <strong><?= htmlspecialchars($product['name']) ?></strong> là giải pháp kết nối AI thế hệ mới được cấu hình và phân phối bởi <strong><?= htmlspecialchars($siteNameDisplay) ?></strong>, giúp lập trình viên và người dùng khai thác tối đa sức mạnh của các mô hình AI tiên tiến mà không cần phải chi trả mức phí thuê bao hàng tháng đắt đỏ.
                    </p>
                    <p class="mt-2">
                        Sau khi kích hoạt thành công, bạn có thể gọi API trực tiếp từ công cụ lập trình yêu thích (như Cursor IDE, VS Code, Next.js, Python hoặc Bot tự động) để thực hiện các tác vụ sinh mã nguồn, tối ưu hóa thuật toán, giải thích code và xử lý logic phức tạp.
                    </p>
                </div>

                <div>
                    <h3 class="text-base sm:text-lg font-bold text-white mb-2 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center text-xs">2</span>
                        <span>Tính Năng Nổi Bật & Model Hỗ Trợ Theo Gói</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                        <div class="p-4 rounded-2xl bg-gray-900/60 border border-gray-800">
                            <h4 class="font-bold text-white text-xs mb-1 flex items-center gap-1.5 text-blue-400">
                                <i class="fas fa-code"></i> Hỗ trợ lập trình toàn diện
                            </h4>
                            <p class="text-xs text-gray-400">Viết mã nguồn từ yêu cầu ngôn ngữ tự nhiên tiếng Việt và tiếng Anh. Tự động phát hiện lỗi cú pháp, gợi ý refactor code và nâng cao cấu trúc dự án.</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-gray-900/60 border border-gray-800">
                            <h4 class="font-bold text-white text-xs mb-1 flex items-center gap-1.5 text-emerald-400">
                                <i class="fas fa-layer-group"></i> Danh sách Model định tuyến của gói
                            </h4>
                            <p class="text-xs text-gray-400">Được cấu hình sẵn hạ tầng định tuyến chịu tải cao, kiểm tra trạng thái live tại <a href="https://status.gwai.cloud/" target="_blank" class="text-blue-400 hover:underline">status.gwai.cloud</a>.</p>
                        </div>
                    </div>

                    <!-- BẢNG MODEL HỖ TRỢ THEO TỪNG GÓI -->
                    <div class="mt-4 bg-gray-950 border border-gray-800 rounded-2xl p-4 overflow-hidden">
                        <div class="flex items-center justify-between mb-3 border-b border-gray-800/80 pb-2.5">
                            <span class="font-bold text-xs text-white uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fas fa-list-check text-emerald-400"></i> Bảng Danh Sách Model / Tính Năng Của Gói Này:
                            </span>
                            <span class="text-[10px] text-gray-400 font-mono">Đồng bộ tự động</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="text-gray-400 border-b border-gray-800 text-[11px] uppercase">
                                    <tr>
                                        <th class="py-2 px-2.5">Tên Model</th>
                                        <th class="py-2 px-2.5">Đặc Điểm </th>
                                        <th class="py-2 px-2.5 text-center">Trạng Thái</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/60 font-medium">
                                    <?php foreach ($supportedModels as $sm): ?>
                                        <tr class="hover:bg-gray-900/40 transition">
                                            <td class="py-2.5 px-2.5">
                                                <div class="font-mono font-bold text-blue-400"><?= htmlspecialchars($sm['name']) ?></div>
                                                <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[9px] font-bold bg-gray-800 text-amber-300"><?= htmlspecialchars($sm['tag']) ?></span>
                                            </td>
                                            <td class="py-2.5 px-2.5 text-gray-300 text-[11px]">
                                                <?= htmlspecialchars($sm['desc']) ?>
                                            </td>
                                            <td class="py-2.5 px-2.5 text-center">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-950 text-green-400 border border-green-800/60">
                                                    <?= htmlspecialchars($sm['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-base sm:text-lg font-bold text-white mb-2 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center text-xs">3</span>
                        <span>Hình Thức Kích Hoạt & Sử Dụng</span>
                    </h3>
                    <ol class="list-decimal pl-5 space-y-1.5 text-xs sm:text-sm text-gray-300">
                        <li>Sau khi chuyển khoản VietQR thành công, hệ thống sẽ cấp ngay cho bạn mã <strong>API Key</strong> hoặc thông tin tài khoản trên màn hình và gửi qua Email.</li>
                        <li>Dán thông tin API Key vào mục Cài đặt (Settings) của Cursor IDE hoặc ứng dụng lập trình của bạn.</li>
                        <li>Kiểm tra trạng thái kết nối và bắt đầu trải nghiệm hỗ trợ AI coding không giới hạn.</li>
                    </ol>
                </div>

                <div>
                    <h3 class="text-base sm:text-lg font-bold text-white mb-2 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center text-xs">4</span>
                        <span>Cơ Chế Tính Lượt & Thời Hạn Sử Dụng</span>
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-300">
                        Định mức của gói: <strong><?= htmlspecialchars($product['request_count'] ?? 'Theo gói tiêu chuẩn') ?></strong>, thời hạn sử dụng là <strong><?= (int)$product['duration_days'] ?> ngày</strong> kể từ thời điểm kích hoạt. Bạn có thể tra cứu số lượt sử dụng còn lại bất kỳ lúc nào tại mục <strong>Quản lý API › Kiểm tra usage</strong> trên thanh Menu.
                    </p>
                </div>

                <div>
                    <h3 class="text-base sm:text-lg font-bold text-white mb-2 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center text-xs">5</span>
                        <span>Sản Phẩm Phù Hợp Với Ai?</span>
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-300">
                        Gói sản phẩm đặc biệt phù hợp cho các lập trình viên Full-stack, Frontend, Backend, sinh viên ngành Công nghệ Thông tin, các team phát triển phần mềm và các nhà sáng tạo nội dung muốn đẩy nhanh tiến độ hoàn thiện dự án với chi phí tiết kiệm nhất.
                    </p>
                </div>
            </div>

            <!-- NỘI DUNG TAB 2: CHÍNH SÁCH BẢO HÀNH -->
            <div x-show="tabHienTai === 'bao_hanh'" class="space-y-6 text-xs sm:text-sm text-gray-300 leading-relaxed" style="display: none;">
                
                <!-- Nhóm Được bảo hành -->
                <div class="p-5 rounded-2xl bg-emerald-950/30 border border-emerald-800/60">
                    <h3 class="text-sm sm:text-base font-bold text-emerald-400 mb-3 flex items-center gap-2">
                        <i class="fas fa-circle-check text-emerald-400"></i>
                        <span>Phạm Vi Được Bảo Hành & Đổi Mới</span>
                    </h3>
                    <ul class="space-y-2 text-xs sm:text-sm text-gray-300">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">•</span>
                            <span>Mã Key lỗi ngay từ đầu khi kích hoạt sẽ được đổi mã mới hoặc hoàn tiền ngay 100%.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">•</span>
                            <span>Giao nhầm loại gói, ngân hàng trừ tiền 2 lần hoặc lỗi nghẽn đơn: Shop hỗ trợ xử lý tức thời 24/7.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">•</span>
                            <span>Đổi ý trong 24 giờ đầu tiên nếu chưa xem mã và chưa phát sinh request sử dụng.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">•</span>
                            <span>Bảo hành xuyên suốt thời hạn sử dụng <strong><?= (int)$product['duration_days'] ?> ngày</strong> đối với các lỗi kỹ thuật từ hệ thống nhà cung cấp.</span>
                        </li>
                    </ul>
                </div>

                <!-- Nhóm Từ chối bảo hành -->
                <div class="p-5 rounded-2xl bg-rose-950/30 border border-rose-800/60">
                    <h3 class="text-sm sm:text-base font-bold text-rose-400 mb-3 flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation text-rose-400"></i>
                        <span>Trường Hợp Từ Chối Bảo Hành</span>
                    </h3>
                    <ul class="space-y-2 text-xs sm:text-sm text-gray-300">
                        <li class="flex items-start gap-2">
                            <span class="text-rose-400 font-bold">•</span>
                            <span>Mã Key đã xem và đã kích hoạt tiêu hao lượt request thì không hỗ trợ hoàn tiền vì lý do cá nhân.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-rose-400 font-bold">•</span>
                            <span>Mã bị khóa do người dùng chia sẻ công khai lên mạng xã hội, spam bot hoặc vi phạm điều khoản nhà cung cấp AI.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-rose-400 font-bold">•</span>
                            <span>Các đơn hàng áp dụng mã khuyến mãi giảm sâu trên 50% hoặc đơn hàng tặng 0đ.</span>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- NỘI DUNG TAB 3: CÂU HỎI THƯỜNG GẶP (FAQ) -->
            <div x-show="tabHienTai === 'faq'" class="space-y-3" style="display: none;" x-data="{ faqMo: null }">
                
                <div class="border border-gray-800 rounded-2xl overflow-hidden bg-gray-900/60">
                    <button @click="faqMo = (faqMo === 1 ? null : 1)" class="w-full p-4 text-left font-bold text-xs sm:text-sm text-white flex items-center justify-between">
                        <span>Sau khi quét mã VietQR bao lâu thì nhận được Key?</span>
                        <i class="fas fa-chevron-down text-xs transition-transform" :class="faqMo === 1 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="faqMo === 1" class="px-4 pb-4 text-xs text-gray-400 border-t border-gray-800 pt-3" style="display: none;">
                        Hệ thống hoạt động tự động 24/7 qua cổng VietQR ACB. Ngay khi ngân hàng báo nhận tiền thành công (thường chỉ mất 1-3 giây), màn hình sẽ tự động hiển thị Key và đồng thời gửi thông tin vào Email bạn đã nhập.
                    </div>
                </div>

                <div class="border border-gray-800 rounded-2xl overflow-hidden bg-gray-900/60">
                    <button @click="faqMo = (faqMo === 2 ? null : 2)" class="w-full p-4 text-left font-bold text-xs sm:text-sm text-white flex items-center justify-between">
                        <span>Làm sao để kiểm tra số request còn lại và hạn dùng?</span>
                        <i class="fas fa-chevron-down text-xs transition-transform" :class="faqMo === 2 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="faqMo === 2" class="px-4 pb-4 text-xs text-gray-400 border-t border-gray-800 pt-3" style="display: none;">
                        Bạn chỉ cần bấm vào nút <strong>"Tra Cứu Key"</strong> trên thanh Header trên cùng hoặc truy cập menu <strong>Quản lý API › Kiểm tra usage</strong>, dán mã Key vào để xem chi tiết số lượt request đã dùng và ngày hết hạn.
                    </div>
                </div>

                <div class="border border-gray-800 rounded-2xl overflow-hidden bg-gray-900/60">
                    <button @click="faqMo = (faqMo === 3 ? null : 3)" class="w-full p-4 text-left font-bold text-xs sm:text-sm text-white flex items-center justify-between">
                        <span>Tôi có thể nâng cấp thành đại lý để có giá sỉ không?</span>
                        <i class="fas fa-chevron-down text-xs transition-transform" :class="faqMo === 3 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="faqMo === 3" class="px-4 pb-4 text-xs text-gray-400 border-t border-gray-800 pt-3" style="display: none;">
                        Có! Nếu bạn là chủ website, chủ bot hoặc lập trình viên muốn tích hợp Buyer API bán lại cho khách, hãy liên hệ ngay Admin qua Zalo/Telegram để được cấp quyền Đại lý và nhận chiết khấu tốt nhất.
                    </div>
                </div>

            </div>

        </div>

        <!-- 4. KHÁM PHÁ CÁC SẢN PHẨM KHÁC (RELATED PRODUCTS) -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <i class="fas fa-cubes text-blue-500"></i>
                            <span>Sản Phẩm Khác Bạn Có Thể Thích</span>
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Khám phá thêm các gói API AI cao cấp khác tại <?= SHOP_NAME ?></p>
                    </div>
                    <a href="/san-pham" class="text-xs font-bold text-blue-400 hover:text-blue-300 transition flex items-center gap-1">
                        <span>Xem Tất Cả</span>
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($relatedProducts as $rp): ?>
                        <div class="gradient-border rounded-2xl p-5 flex flex-col justify-between hover:border-blue-500/50 transition group shadow-lg">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        <?= htmlspecialchars($rp['category_name']) ?>
                                    </span>
                                    <span class="text-xs font-semibold text-gray-400">
                                        <?= htmlspecialchars($rp['request_count'] ?? '30 Ngày') ?>
                                    </span>
                                </div>

                                <a href="/san-pham/<?= htmlspecialchars($rp['slug']) ?>" class="block font-bold text-white group-hover:text-blue-400 transition text-sm mb-1.5">
                                    <?= htmlspecialchars($rp['name']) ?>
                                </a>

                                <p class="text-xs text-gray-400 line-clamp-2 mb-4">
                                    <?= htmlspecialchars($rp['description'] ?? 'Tốc độ cao, hỗ trợ All model, bảo hành trong thời gian sử dụng.') ?>
                                </p>
                            </div>

                            <div class="pt-3 border-t border-gray-800 flex items-center justify-between">
                                <div>
                                    <span class="text-xs text-gray-400 block text-[10px]">Giá bán:</span>
                                    <span class="text-base font-black text-emerald-400 font-mono">
                                        <?= number_format($rp['price'], 0, ',', '.') ?>đ
                                    </span>
                                </div>
                                <a href="/san-pham/<?= htmlspecialchars($rp['slug']) ?>" class="px-3.5 py-1.5 rounded-xl bg-gray-800 hover:bg-blue-600 text-gray-200 hover:text-white text-xs font-bold transition flex items-center gap-1">
                                    <span>Xem Chi Tiết</span>
                                    <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- POPUP THANH TOÁN VIETQR ACB CHUẨN MẪU (MODAL) -->
    <div x-show="moPopupThanhToan" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-2xl sm:rounded-3xl max-w-md w-full p-4 sm:p-5 relative shadow-2xl overflow-y-auto max-h-[92vh] my-auto">
            
            <!-- HEADER MODAL & NÚT ĐÓNG TÁCH BIỆT RÕ RÀNG -->
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-white tracking-wide" x-text="daThanhToan ? 'Thanh Toán Thành Công' : (daTaoDon ? 'Thanh Toán VietQR Tự Động' : 'Xác Nhận Đơn Hàng')"></h3>
                </div>
                <button @click="dongThanhToan()" type="button" class="w-8 h-8 rounded-full bg-gray-800/90 hover:bg-gray-700 text-gray-400 hover:text-white flex items-center justify-center transition border border-gray-700/50">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- BƯỚC 1: ĐIỀN EMAIL & XÁC NHẬN ĐẶT HÀNG -->
            <div x-show="!daTaoDon && !daThanhToan">
                <div class="text-center mb-4">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Thông Tin Sản Phẩm</span>
                    <h3 class="text-base font-bold text-white mt-1" x-text="tenGoiMua"></h3>
                    <div class="text-lg font-black text-emerald-400 font-mono mt-1" x-text="dinhDangTien(giaGoiMua)"></div>
                </div>

                <div x-show="thongBaoLoiPopup" class="p-2.5 rounded-xl mb-3 bg-red-950/70 border border-red-800 text-red-300 text-xs font-semibold text-center" x-text="thongBaoLoiPopup"></div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Email nhận mã Key / Tài khoản (Bắt buộc):</label>
                        <input type="email" x-model="emailKhachHang" placeholder="emailcuaban@gmail.com" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Mã giảm giá (nếu có):</label>
                        <input type="text" x-model="maGiamGia" placeholder="Nhập mã ưu đãi..." class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2 text-xs text-white uppercase focus:outline-none focus:border-blue-500 font-mono">
                    </div>

                    <?php if (!empty($userHienTai)): ?>
                        <!-- Nút thanh toán trực tiếp bằng số dư ví -->
                        <button @click="thanhToanBangVi()" :disabled="dangTaoDon" class="w-full py-2.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-bold text-xs transition duration-200 shadow-md shadow-green-600/30 flex items-center justify-center gap-2">
                            <i class="fas fa-bolt text-amber-300"></i> Thanh Toán Bằng Số Dư Ví (Hiện có: <?= number_format($userHienTai['balance'] ?? 0, 0, ',', '.') ?>đ)
                        </button>

                        <div class="relative flex items-center justify-center my-1">
                            <div class="border-t border-gray-800 w-full"></div>
                            <span class="bg-[#111827] px-3 text-[10px] text-gray-500 uppercase font-semibold absolute">hoặc quét mã ngân hàng</span>
                        </div>
                    <?php endif; ?>

                    <button @click="taoDonHang()" :disabled="dangTaoDon" class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                        <span x-show="dangTaoDon"><i class="fas fa-circle-notch fa-spin"></i> Đang khởi tạo đơn hàng...</span>
                        <span x-show="!dangTaoDon"><i class="fas fa-qrcode mr-1"></i> Tạo Mã VietQR ACB Thanh Toán</span>
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
                                <span class="block text-xs font-black text-gray-900 truncate" x-text="tenGoiMua"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="block text-[9px] font-bold text-gray-500 uppercase tracking-wider">SỐ TIỀN</span>
                                <span class="block text-sm font-black text-emerald-600 font-mono" x-text="dinhDangTien(giaGoiMua)"></span>
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
                    <textarea readonly class="w-full h-24 bg-gray-950 border border-gray-700 rounded-lg p-2.5 font-mono text-xs text-white focus:outline-none resize-none" x-text="thongTinKeyNhanDuoc"></textarea>
                    <button @click="saoChep(thongTinKeyNhanDuoc)" class="mt-2.5 w-full py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5">
                        <i class="fas fa-copy"></i> Sao chép Key vào bộ nhớ tạm
                    </button>
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

                <button @click="dongThanhToan()" class="w-full py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold transition">
                    Đóng cửa sổ
                </button>
            </div>

        </div>
    </div>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- JAVASCRIPT XỬ LÝ THANH TOÁN & TIỆN ÍCH TRÊN TRANG -->
    <script>
    function chiTietSanPhamApp() {
        return {
            idGoiMua: <?= (int)$product['id'] ?>,
            tenGoiMua: '<?= addslashes($product['name']) ?>',
            giaGoiMua: <?= (float)$product['price'] ?>,
            emailKhachHang: '<?= !empty($userHienTai['email']) ? addslashes($userHienTai['email']) : '' ?>',
            maGiamGia: '',

            moPopupThanhToan: false,
            dangTaoDon: false,
            daTaoDon: false,
            daThanhToan: false,
            dangXuLyVi: false,

            maDonHang: '',
            linkVietQr: '',
            thongBaoLoiPopup: '',
            thongTinKeyNhanDuoc: '',

            demNguoc5s: 5,
            dangKiemTraNgay: false,
            timerDemNguoc: null,

            moThanhToan(id, ten, gia) {
                this.idGoiMua = id;
                this.tenGoiMua = ten;
                this.giaGoiMua = gia;
                this.thongBaoLoiPopup = '';
                this.daTaoDon = false;
                this.daThanhToan = false;
                this.moPopupThanhToan = true;
            },

            dongThanhToan() {
                this.moPopupThanhToan = false;
                if (this.timerDemNguoc) {
                    clearInterval(this.timerDemNguoc);
                    this.timerDemNguoc = null;
                }
            },

            saoChep(vanBan) {
                if (!vanBan) return;
                navigator.clipboard.writeText(vanBan).then(() => {
                    alert('Đã sao chép thành công!');
                }).catch(() => {
                    const temp = document.createElement('textarea');
                    temp.value = vanBan;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                    alert('Đã sao chép thành công!');
                });
            },

            dinhDangTien(soTien) {
                return new Intl.NumberFormat('vi-VN').format(soTien) + ' VNĐ';
            },

            taoDonHang() {
                if (!this.emailKhachHang || !this.emailKhachHang.includes('@')) {
                    this.thongBaoLoiPopup = 'Vui lòng nhập địa chỉ Email hợp lệ để nhận Key!';
                    return;
                }
                this.thongBaoLoiPopup = '';
                this.dangTaoDon = true;

                const formData = new FormData();
                formData.append('action', 'tao_don_hang');
                formData.append('product_id', this.idGoiMua);
                formData.append('email', this.emailKhachHang);
                formData.append('coupon_code', this.maGiamGia);

                fetch('/backend/api.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        this.dangTaoDon = false;
                        if (res.thanh_cong) {
                            this.daTaoDon = true;
                            this.maDonHang = res.ma_don_hang;
                            this.giaGoiMua = res.so_tien;
                            this.linkVietQr = res.link_vietqr;
                            this.batDauDemNguocVaKiemTra();
                        } else {
                            this.thongBaoLoiPopup = res.thong_bao || 'Không thể tạo đơn hàng, vui lòng thử lại!';
                        }
                    })
                    .catch(() => {
                        this.dangTaoDon = false;
                        this.thongBaoLoiPopup = 'Lỗi kết nối máy chủ, vui lòng thử lại sau!';
                    });
            },

            // Thanh toán trực tiếp bằng số dư ví người dùng
            thanhToanBangVi() {
                this.dangTaoDon = true;
                this.thongBaoLoiPopup = '';

                const formData = new FormData();
                formData.append('action', 'mua_bang_so_du');
                formData.append('product_id', <?= (int)$product['id'] ?>);
                formData.append('coupon_code', this.maGiamGia);

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
                            this.thongTinKeyNhanDuoc = data.tai_khoan.join("\n");
                        } else {
                            this.thongTinKeyNhanDuoc = 'sk-vankha-' + this.maDonHang + '-completed';
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

            batDauDemNguocVaKiemTra() {
                if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                this.demNguoc5s = 5;

                this.timerDemNguoc = setInterval(() => {
                    if (this.daThanhToan) {
                        clearInterval(this.timerDemNguoc);
                        return;
                    }
                    this.demNguoc5s--;
                    if (this.demNguoc5s <= 0) {
                        this.demNguoc5s = 5;
                        this.kiemTraThanhToanNgay(true);
                    }
                }, 1000);
            },

            kiemTraThanhToanNgay(laTuDong = false) {
                if (!this.maDonHang || this.daThanhToan) return;
                if (!laTuDong) this.dangKiemTraNgay = true;

                fetch('/backend/api.php?action=kiem_tra_trang_thai_don&order_code=' + encodeURIComponent(this.maDonHang))
                    .then(r => r.json())
                    .then(res => {
                        this.dangKiemTraNgay = false;
                        if (res.thanh_cong && res.trang_thai === 'COMPLETED') {
                            this.daThanhToan = true;
                            this.thongTinKeyNhanDuoc = res.account_data || 'Key đã kích hoạt thành công!';
                            if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                        } else if (!laTuDong) {
                            alert('Hệ thống chưa nhận được tiền cho mã ' + this.maDonHang + '. Vui lòng đợi trong giây lát!');
                        }
                    })
                    .catch(() => {
                        this.dangKiemTraNgay = false;
                    });
            },

            muaBangVi(productId) {
                if (!confirm('Bạn có chắc chắn muốn dùng số dư ví để mua sản phẩm này ngay không?')) return;
                this.dangXuLyVi = true;

                const fd = new FormData();
                fd.append('action', 'mua_bang_so_du');
                fd.append('product_id', productId);

                fetch('/backend/api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        this.dangXuLyVi = false;
                        if (res.thanh_cong) {
                            alert('Mua hàng thành công! Key đã được thêm vào đơn hàng của bạn.');
                            window.location.href = '/user/don-hang';
                        } else {
                            alert('Lỗi: ' + (res.thong_bao || 'Số dư không đủ!'));
                        }
                    })
                    .catch(() => {
                        this.dangXuLyVi = false;
                        alert('Lỗi kết nối máy chủ!');
                    });
            }
        }
    }
    </script>
</body>
</html>
