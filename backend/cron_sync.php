<?php
// ========================================================
// CRONJOB TỰ ĐỘNG ĐỒNG BỘ KHO, GIÁ VÀ NHẬP SẢN PHẨM MỚI (CRON SYNC)
// vankhateamshop - Đồng bộ tự động với nhà cung cấp (GenzShop...)
// Tự cập nhật tồn kho, tự cập nhật giá vốn, tự nhập sản phẩm mới, tự ẩn khi hết hàng
// Tương thích cPanel Cron Jobs (* * * * *) & Linux Crontab & Web cURL
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/config.php';

// Thiết lập chạy qua CLI hoặc Web có kèm secret token
$secretKey = layGiaTriEnv('CRON_SECRET', 'VK_CRON_SECRET_2026');
if (!defined('IN_CRON_MASTER') && php_sapi_name() !== 'cli') {
    $tokenGet = $_GET['secret'] ?? '';
    if ($tokenGet !== $secretKey) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'thanh_cong' => false,
            'thong_bao'  => 'Truy cập bị từ chối: Sai secret token. Vui lòng thêm ?secret=' . $secretKey
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/supplier_api.php';
require_once __DIR__ . '/telegram.php';

// Hàm xóa dấu tiếng việt tạo slug
function taoSlugVnCron($str) {
    $unicode = [
        'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd'=>'đ',
        'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i'=>'í|ì|ỉ|ĩ|ị',
        'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y'=>'ý|ỳ|ỷ|ỹ|ỵ'
    ];
    foreach($unicode as $nonAccent=>$accent){
        $str = preg_replace("/($accent)/iu", $nonAccent, $str);
    }
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $str));
    return trim($slug, '-');
}

// Hàm xác định các model AI hỗ trợ theo từng gói sản phẩm từ đối tác
function tinhGiaBanTuGiaVonCron(float $cost): float {
    // Tối ưu lợi nhuận chuẩn: Mỗi đơn lời từ 5.000đ đến 10.000đ
    if ($cost <= 30000) {
        $profit = 5000; // Gói vốn nhỏ dưới 30k (10k, 11k, 15k, 25k): Lời đúng 5.000đ
    } elseif ($cost <= 60000) {
        $profit = 7000; // Gói vốn vừa 30k - 60k (45k, 50k, 55k): Lời đúng 7.000đ
    } else {
        $profit = 10000; // Gói vốn từ 60k trở lên (75k, 100k, 150k, 290k...): Lời đúng 10.000đ
    }
    return ceil(($cost + $profit) / 1000) * 1000;
}

/**
 * Tự động tải ảnh từ đối tác về lưu tại thư mục assets/images/products/
 * Giúp tải trang siêu nhanh, không bị lỗi CORS, không phụ thuộc máy chủ đối tác
 */
function luuAnhSanPhamTuDoiTac(string $url, string $sku): ?string {
    if (empty($url) || strpos($url, 'http') !== 0) {
        return null;
    }

    $dir = __DIR__ . '/../assets/images/products';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $skuClean = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($sku));
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
        $ext = 'png';
    }

    $localFile = $dir . '/product_' . $skuClean . '.' . $ext;
    $webPath = '/assets/images/products/product_' . $skuClean . '.' . $ext;

    // Tải về nếu file chưa có hoặc đã quá 3 ngày để tự động cập nhật nếu đối tác đổi ảnh
    if (!file_exists($localFile) || (time() - filemtime($localFile) > 86400 * 3)) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
            ]
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data && strlen($data) > 1000) {
            @file_put_contents($localFile, $data);
            return $webPath;
        }
    }

    return file_exists($localFile) ? $webPath : $url;
}

/**
 * Tự động quét và lấy bản đồ ảnh sản phẩm từ website đối tác (hỗ trợ khi API đối tác chưa trả trường image)
 */
function layBanDoAnhDoiTac(string $partnerApiUrl): array {
    static $cacheAnhDoiTac = [];

    $parsed = parse_url($partnerApiUrl);
    $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'genzshop.vn');

    if (isset($cacheAnhDoiTac[$baseUrl])) {
        return $cacheAnhDoiTac[$baseUrl];
    }

    $map = [];

    try {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 4,
                'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
            ]
        ]);

        // 1. Quét trang chủ đối tác để lấy các ảnh featured chính thức
        $htmlHome = @file_get_contents($baseUrl . '/', false, $ctx);
        if ($htmlHome) {
            if (preg_match_all('#<img[^>]+src="([^"]*assets/images/(?:products|featured)/[^"]+)"[^>]*>[\s\S]*?<h[1-6][^>]*>([^<]+)</h[1-6]>#i', $htmlHome, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $imgUrl = $m[1];
                    if (strpos($imgUrl, 'http') !== 0) $imgUrl = $baseUrl . '/' . ltrim($imgUrl, '/');
                    $map[mb_strtolower(trim($m[2]))] = $imgUrl;
                }
            }
        }

        // 2. Quét các danh mục AI chính của đối tác để lấy ảnh từng gói
        $cats = ['grok', 'cursor', 'claude', 'codex', 'deepseek', 'gemini', 'kimi', 'zhipu', 'muse'];
        foreach ($cats as $cat) {
            $catUrl = $baseUrl . '/vi/san-pham/' . $cat;
            $catHtml = @file_get_contents($catUrl, false, $ctx);
            if ($catHtml) {
                if (preg_match_all('#<img[^>]+src="([^"]*assets/images/products/[^"]+)"[^>]*>[\s\S]*?(?:<h[1-6][^>]*>([^<]+)</h[1-6]>|<p[^>]*>([^<]+)</p>)#i', $catHtml, $cMatches, PREG_SET_ORDER)) {
                    foreach ($cMatches as $cm) {
                        $imgUrl = $cm[1];
                        if (strpos($imgUrl, 'http') !== 0) $imgUrl = $baseUrl . '/' . ltrim($imgUrl, '/');
                        $name = mb_strtolower(trim($cm[2] ?? ($cm[3] ?? '')));
                        if (!empty($name)) $map[$name] = $imgUrl;
                        $map[$cat] = $imgUrl;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Dự phòng an toàn nếu quét gặp lỗi
    }

    $cacheAnhDoiTac[$baseUrl] = $map;
    return $map;
}

function xacDinhSupportedModelsCron($name, $sku, $catName = '', $desc = '') {
    $combined = mb_strtolower($name . ' ' . $sku . ' ' . $catName . ' ' . $desc);

    // 1. CODEX (Taphoaai & Zplay: RPM, Token, 189, 27, 28)
    if (str_contains($combined, 'codex') && (str_contains($combined, 'zplay') || str_contains($combined, 'rpm') || str_contains($combined, 'token') || str_contains($combined, 'gpt-6.1') || str_contains($combined, 'vk-27') || str_contains($combined, 'vk-28') || str_contains($combined, 'vk-189'))) {
        return 'gpt-5.6-sol, gpt-6.1-sol, gpt-5.6-luna, gpt-5.6-terra, gpt-5.4-mini, claude-opus-5.5, claude-sonnet-5, claude-opus-5, claude-opus-4.8';
    }

    // 2. CODEX API (Genzshop Credit: CDX-0055...CDX-0122)
    if (str_contains($combined, 'codex')) {
        return 'gpt-4o, gpt-4o-mini, gpt-4-turbo, gpt-3.5-turbo, o1, o1-mini, o3-mini';
    }

    // 3. CLAUDE_CODE (Taphoaai: 30M, 900M, 10$, 300$)
    if (str_contains($combined, 'claude_code')) {
        return 'claude-opus-5.5, claude-sonnet-5, claude-opus-5, claude-opus-4.8, claude-opus-4.7, claude-opus-4.6, claude-3-5-sonnet';
    }

    // 4. CLAUDE API (Genzshop Claude Max, Claude Credit)
    if (str_contains($combined, 'claude')) {
        return 'claude-3-5-sonnet, claude-3-5-haiku, claude-3-opus, claude-3-7-sonnet, claude-3-haiku';
    }

    // 5. CURSOR API (Tất cả gói Cursor 1 ngày, 3 ngày, 7 ngày, 30 ngày)
    if (str_contains($combined, 'cursor')) {
        return 'claude-3-5-sonnet, gpt-4o, deepseek-reasoner, deepseek-chat, cursor-small, claude-3-7-sonnet';
    }

    // 6. DEEPSEEK API
    if (str_contains($combined, 'deepseek')) {
        return 'deepseek-reasoner, deepseek-chat, deepseek-coder, deepseek-r1, deepseek-v3';
    }

    // 7. GEMINI API (Theo bảng giá model gốc 1gw.gwai.cloud của đối tác)
    if (str_contains($combined, 'gemini')) {
        // Gói nâng tài khoản Google One / Gemini Pixel Pro bằng thẻ Visa không phải API key
        if (str_contains($combined, 'pixel pro') || str_contains($combined, 'thẻ visa') || str_contains($combined, 'nâng')) {
            return null;
        }
        // 7 model chuẩn theo bảng giá đối tác 1gw.gwai.cloud
        return 'gemini-3.8-flash, gemini-4-argon, gemini-4-carbon, gemini-4-hydrogen, nano-banana-2, nano-banana-2-2k, nano-banana-2-4k';
    }

    // 8. GLM (Zhipu AI)
    if (str_contains($combined, 'glm') || str_contains($combined, 'zhipu')) {
        return 'glm-4-plus, glm-4, glm-4-air, glm-4-flash, glm-4-long';
    }

    // 9. KIMI (Moonshot AI)
    if (str_contains($combined, 'kimi') || str_contains($combined, 'moonshot')) {
        return 'moonshot-v1-8k, moonshot-v1-32k, moonshot-v1-128k, kimi-k1.5';
    }

    // 10. GROK (xAI)
    if (str_contains($combined, 'grok')) {
        return 'grok-2, grok-2-mini, grok-beta, grok-vision-beta';
    }

    // 11. MUSE AI
    if (str_contains($combined, 'muse')) {
        return 'muse-chat, muse-code, gpt-4o, claude-3-5-sonnet';
    }

    // 12. KIRO AI
    if (str_contains($combined, 'kiro')) {
        return 'gpt-4o, claude-3-5-sonnet, deepseek-reasoner, gemini-1.5-pro, grok-2';
    }

    // 13. GPT Plus (Tài khoản)
    if (str_contains($combined, 'gpt plus')) {
        return 'gpt-4o, gpt-4o-mini, o1, o1-mini, canvas, voice-mode';
    }

    // 14. Antigravity Pro
    if (str_contains($combined, 'antigravity')) {
        return 'claude-3-5-sonnet, gpt-4o, deepseek-reasoner, gemini-1.5-pro';
    }

    return null;
}

$thoiGianBatDau = microtime(true);
$ketQuaTong = [
    'thanh_cong'        => true,
    'thoi_gian'         => date('Y-m-d H:i:s'),
    'nha_cung_cap'      => [],
    'tong_cap_nhat'     => 0,
    'tong_them_moi'     => 0,
    'tong_tam_het_hang' => 0
];
// Gom thay đổi theo nhóm để mỗi lần cron chỉ gửi một tin cho từng nhóm.
$telegramCatalogEvents = [
    'new' => [], 'out' => [], 'price' => []
];

try {
    // 1. LẤY TẤT CẢ NHÀ CUNG CẤP ĐANG HOẠT ĐỘNG
    $suppliers = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1")->fetchAll();
    if (empty($suppliers)) {
        throw new Exception("Không có nhà cung cấp nào đang kích hoạt trong hệ thống");
    }

    // Danh mục chuẩn và icon tương ứng, sắp xếp theo thứ tự ưu tiên hiển thị
    $danhMucChuan = [
        'claude'   => ['ten' => 'Claude API',       'slug' => 'claude',   'icon' => 'fa-brain',   'sort_order' => 1],
        'codex'    => ['ten' => 'Codex API',        'slug' => 'codex',    'icon' => 'fa-code',    'sort_order' => 2],
        'cursor'   => ['ten' => 'Cursor API',       'slug' => 'cursor',   'icon' => 'fa-bolt',    'sort_order' => 3],
        'deepseek' => ['ten' => 'DeepSeek API',     'slug' => 'deepseek', 'icon' => 'fa-robot',   'sort_order' => 4],
        'gemini'   => ['ten' => 'Gemini API',       'slug' => 'gemini',   'icon' => 'fa-gem',     'sort_order' => 5],
        'grok'     => ['ten' => 'Grok API',         'slug' => 'grok',     'icon' => 'fa-rocket',  'sort_order' => 6],
        'kimi'     => ['ten' => 'Kimi API',         'slug' => 'kimi',     'icon' => 'fa-moon',    'sort_order' => 7],
        'zhipu'    => ['ten' => 'GLM (Zhipu) API',  'slug' => 'zhipu',    'icon' => 'fa-feather', 'sort_order' => 8],
        'muse'     => ['ten' => 'Muse AI API',      'slug' => 'muse',     'icon' => 'fa-music',   'sort_order' => 9],
        'software' => ['ten' => 'Phần Mềm AI',      'slug' => 'software', 'icon' => 'fa-desktop', 'sort_order' => 10],
        'other'    => ['ten' => 'Sản Phẩm Khác',    'slug' => 'other',    'icon' => 'fa-cube',    'sort_order' => 99]
    ];

    // Cập nhật thứ tự sắp xếp danh mục chuẩn trong CSDL
    foreach ($danhMucChuan as $dm) {
        $pdo->prepare("UPDATE `categories` SET `sort_order` = ? WHERE `slug` = ?")->execute([$dm['sort_order'], $dm['slug']]);
    }

    // Lấy bảng categories hiện tại: slug => id
    $cats = $pdo->query("SELECT `slug`, `id` FROM `categories`")->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($suppliers as $sup) {
        $supInfo = [
            'id'             => $sup['id'],
            'ten'            => $sup['name'],
            'so_du_vi'       => 0,
            'da_cap_nhat'    => 0,
            'da_them_moi'    => 0,
            'tam_het_hang'   => 0
        ];

        $apiClient = new NhaCungCapApi($sup['api_url'], $sup['api_key']);

        // A. CẬP NHẬT SỐ DƯ VÍ NHÀ CUNG CẤP
        $kqVi = $apiClient->laySoDuVi();
        if (!empty($kqVi['success'])) {
            $soDu = (float)$kqVi['balance'];
            $supInfo['so_du_vi'] = $soDu;
            $pdo->prepare("UPDATE `suppliers` SET `balance` = ? WHERE `id` = ?")->execute([$soDu, $sup['id']]);

            // Cảnh báo nếu số dư dưới ngưỡng
            if ($soDu <= (float)$sup['min_balance_alert']) {
                $thongDiep = "⚠️ <b>CẢNH BÁO SỐ DƯ VÍ NGUỒN THẤP!</b>\n";
                $thongDiep .= "🏢 Đối tác: <b>{$sup['name']}</b>\n";
                $thongDiep .= "💰 Số dư: <b>" . number_format($soDu, 0, ',', '.') . " VNĐ</b>\n";
                $thongDiep .= "🚨 Ngưỡng báo động: <b>" . number_format($sup['min_balance_alert'], 0, ',', '.') . " VNĐ</b>";
                guiThongBaoTelegram($thongDiep, 'finance');
            }
        }

        // B. LẤY DANH SÁCH SẢN PHẨM TỪ ĐỐI TÁC
        $rawProducts = $apiClient->layDanhSachSanPham();
        $nccItems = $rawProducts['products'] ?? [];

        if (empty($nccItems)) {
            $supInfo['loi'] = 'Không lấy được danh sách sản phẩm từ API đối tác';
            $ketQuaTong['nha_cung_cap'][] = $supInfo;
            continue;
        }

        $partnerImagesMap = layBanDoAnhDoiTac($sup['api_url']);
        $parsedUrl = parse_url($sup['api_url']);
        $partnerBaseUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? 'genzshop.vn');

        foreach ($nccItems as $it) {
            $sku = trim($it['product_id'] ?? '');
            if (empty($sku)) continue;

            $cost = (float)($it['walletPricing'] ?? 0);
            $stock = (int)($it['available'] ?? 0);
            $name = trim($it['name'] ?? '');
            $type = strtolower($it['type'] ?? '');

            // Phần mềm AI (TungDV / SWF): Bản quyền cấp tự động 24/7, luôn sẵn hàng
            $isSoftware = ($type === 'software' || str_contains($sku, 'SWF') || str_contains($name, 'TungDV') || str_contains($name, 'Studio Pro'));
            if ($isSoftware) {
                $stock = 999;
            }

            if ($stock <= 0) {
                $supInfo['tam_het_hang']++;
                $ketQuaTong['tong_tam_het_hang']++;
            }

            // Kiểm tra xem sản phẩm đối tác này đã map với sản phẩm nào trên web chưa
            $stmtMap = $pdo->prepare("SELECT product_id FROM `supplier_product_mappings` WHERE `supplier_id` = ? AND `supplier_sku` = ? LIMIT 1");
            $stmtMap->execute([$sup['id'], $sku]);
            $existingPid = $stmtMap->fetchColumn();

            $ourSku = 'VK-' . strtoupper(str_replace(['-', ' '], '', $sku));

            // Nếu chưa map với đối tác này, kiểm tra xem trong bảng products đã có sản phẩm này từ đối tác khác chưa
            if (!$existingPid) {
                $stmtP = $pdo->prepare("SELECT id FROM `products` WHERE `sku` = ? OR `sku` = ? LIMIT 1");
                $stmtP->execute([$ourSku, 'VK-' . $sku]);
                $foundPid = $stmtP->fetchColumn();
                if ($foundPid) {
                    $existingPid = $foundPid;
                    // Tự động liên kết đối tác này vào sản phẩm sẵn có (Đa nguồn cung cấp)
                    $pdo->prepare("
                        INSERT INTO `supplier_product_mappings` 
                        (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, 1, 1)
                        ON DUPLICATE KEY UPDATE `cost_price` = VALUES(`cost_price`), `stock` = VALUES(`stock`)
                    ")->execute([$existingPid, $sup['id'], $sku, $cost, $stock]);
                }
            }

            if ($existingPid) {
                // 1. NẾU ĐÃ CÓ: Cập nhật tồn kho (stock) và giá vốn (cost_price)
                 $stmtOldMap = $pdo->prepare("SELECT `stock`, `cost_price` FROM `supplier_product_mappings` WHERE `product_id` = ? AND `supplier_id` = ? LIMIT 1");
                 $stmtOldMap->execute([$existingPid, $sup['id']]);
                 $oldMap = $stmtOldMap->fetch() ?: ['stock' => 0, 'cost_price' => 0];
                 $upMap = $pdo->prepare("UPDATE `supplier_product_mappings` SET `stock` = ?, `cost_price` = ? WHERE `product_id` = ? AND `supplier_id` = ?");
                 $upMap->execute([$stock, $cost, $existingPid, $sup['id']]);

                 // Tự động kiểm tra giá bán: nếu giá vốn tăng cao hơn giá bán thì tự động điều chỉnh giá bán để không bị lỗ
                 $curProd = $pdo->query("SELECT `name`, `price`, `category_id`, `is_active` FROM `products` WHERE `id` = " . (int)$existingPid)->fetch();
                 if ($curProd) {
                     if ($isSoftware && empty($curProd['is_active'])) {
                         $pdo->prepare("UPDATE `products` SET `is_active` = 1 WHERE `id` = ?")->execute([$existingPid]);
                     }
                     $giaBanCu = (float)$curProd['price'];
                     $giaBanMoi = tinhGiaBanTuGiaVonCron($cost);
                     // Chỉ cập nhật khi công thức mới thực sự thay đổi giá; có thể tăng hoặc giảm.
                     if ($giaBanCu !== $giaBanMoi) {
                         $origPrice = ceil(($giaBanMoi * 1.3) / 1000) * 1000;
                         $pdo->prepare("UPDATE `products` SET `price` = ?, `original_price` = ? WHERE `id` = ?")->execute([$giaBanMoi, $origPrice, $existingPid]);
                         $telegramCatalogEvents['price'][] = ['name' => $curProd['name'], 'old' => $giaBanCu, 'new' => $giaBanMoi];
                     }
                 }
                 if ((int)$oldMap['stock'] > 0 && $stock <= 0 && !$isSoftware) {
                     $telegramCatalogEvents['out'][] = ['name' => $curProd['name'] ?? ('Sản phẩm #' . $existingPid), 'stock' => $stock];
                 }


                // Cập nhật lại danh mục chuẩn xác nếu sản phẩm trước đó đang nằm ở danh mục Khác (1009)
                $type = strtolower($it['type'] ?? 'other');
                $catInfo = $danhMucChuan[$type] ?? $danhMucChuan['other'];
                $catSlug = $catInfo['slug'];
                if (isset($cats[$catSlug]) && (!empty($curProd['category_id']) && (int)$curProd['category_id'] === 1009)) {
                    $targetCatId = (int)$cats[$catSlug];
                    $pdo->prepare("UPDATE `products` SET `category_id` = ? WHERE `id` = ?")->execute([$targetCatId, $existingPid]);
                }

                // Cập nhật ảnh đại diện: Chỉ ưu tiên nhà cung cấp chính (GenzShop) hoặc khi sản phẩm hoàn toàn chưa có ảnh
                $isPrimarySupplier = ($sup['code'] === 'supplier_primary' || (int)$sup['id'] === 1);
                $curImg = $curProd['image'] ?? '';

                if ($isPrimarySupplier || empty($curImg) || strpos($curImg, 'placehold') !== false) {
                    $prodImg = trim($it['image'] ?? ($it['thumbnail'] ?? ($it['avatar'] ?? '')));
                    if (empty($prodImg) && !empty($partnerImagesMap)) {
                        $cleanName = mb_strtolower(trim($name));
                        $type = strtolower($it['type'] ?? '');
                        if (!empty($partnerImagesMap[$cleanName])) {
                            $prodImg = $partnerImagesMap[$cleanName];
                        } elseif (!empty($partnerImagesMap[$type])) {
                            $prodImg = $partnerImagesMap[$type];
                        }
                    }

                    if (!empty($prodImg)) {
                        // Không để đối tác phụ ghi đè ảnh webp chất lượng thấp lên sản phẩm chính
                        $isLowResWebp = (str_ends_with(strtolower($prodImg), '.webp') && !$isPrimarySupplier);
                        if (!$isLowResWebp) {
                            if (strpos($prodImg, 'http') !== 0 && strpos($prodImg, '/') === 0) {
                                $prodImg = $partnerBaseUrl . $prodImg;
                            }
                            $savedImg = luuAnhSanPhamTuDoiTac($prodImg, $sku);
                            $finalImg = $savedImg ?: $prodImg;

                            if (empty($curImg) || strpos($curImg, 'placehold') !== false || ($isPrimarySupplier && $curImg !== $finalImg && !str_contains($curImg, 'featured_cursor'))) {
                                $pdo->prepare("UPDATE `products` SET `image` = ? WHERE `id` = ?")->execute([$finalImg, $existingPid]);
                            }
                        }
                    }
                }

                // Tự động đồng bộ supported_models nếu sản phẩm chưa có model
                $syncModels = xacDinhSupportedModelsCron($name, $ourSku, $catInfo['ten'] ?? '', $it['description'] ?? '');
                if ($syncModels !== null) {
                    $pdo->prepare("UPDATE `products` SET `supported_models` = ? WHERE `id` = ? AND (`supported_models` IS NULL OR `supported_models` = '')")->execute([$syncModels, $existingPid]);
                }

                // TỰ ĐỘNG CẬP NHẬT TRẠNG THÁI: Nếu BẤT KỲ đối tác nào còn hàng thì sản phẩm mở bán, tất cả hết thì ẩn
                $stmtStockAny = $pdo->prepare("SELECT COUNT(*) FROM `supplier_product_mappings` WHERE `product_id` = ? AND `is_active` = 1 AND `stock` > 0");
                $stmtStockAny->execute([$existingPid]);
                $hasStock = ((int)$stmtStockAny->fetchColumn() > 0);
                $pdo->prepare("UPDATE `products` SET `is_active` = ? WHERE `id` = ?")->execute([$hasStock ? 1 : 0, $existingPid]);

                $supInfo['da_cap_nhat']++;
                $ketQuaTong['tong_cap_nhat']++;
            } else {
                // 2. NẾU CHƯA CÓ: TỰ ĐỘNG THÊM SẢN PHẨM MỚI VÀO WEB!
                $type = strtolower($it['type'] ?? 'other');
                $catInfo = $danhMucChuan[$type] ?? $danhMucChuan['other'];
                $catSlug = $catInfo['slug'];

                // Tạo danh mục nếu chưa có
                if (!isset($cats[$catSlug])) {
                    $stmtC = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `icon`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `sort_order`=VALUES(`sort_order`)");
                    $stmtC->execute([$catInfo['ten'], $catSlug, $catInfo['icon'], $catInfo['sort_order'] ?? 10]);
                    $catId = (int)$pdo->lastInsertId();
                    $cats[$catSlug] = $catId;
                } else {
                    $catId = (int)$cats[$catSlug];
                }

                // Tính giá bán lẻ tự động theo công thức lợi nhuận chuẩn
                $price = tinhGiaBanTuGiaVonCron($cost);
                $originalPrice = ceil(($price * 1.3) / 1000) * 1000;

                // Tính thời hạn (duration_days) từ tên
                $duration = 30;
                if (stripos($name, '1 Ngày') !== false || stripos($name, '1 ngày') !== false) {
                    $duration = 1;
                } elseif (stripos($name, '3 Ngày') !== false || stripos($name, '3 ngày') !== false) {
                    $duration = 3;
                } elseif (stripos($name, '7 Ngày') !== false || stripos($name, '1 tuần') !== false) {
                    $duration = 7;
                } elseif (stripos($name, '6 tháng') !== false) {
                    $duration = 180;
                } elseif (stripos($name, '12 tháng') !== false || stripos($name, '1 năm') !== false) {
                    $duration = 365;
                }

                // Badge & Request count
                $badge = null;
                $requestCount = 'Tốc độ cao 24/7';
                if (preg_match('/(\d+)\s*Request/i', $name, $matches)) {
                    $requestCount = $matches[1] . ' Request';
                    $badge = $matches[1] . ' Req';
                } elseif (preg_match('/(\d+)\$/i', $name, $matches)) {
                    $requestCount = $matches[1] . '$ Credit';
                    $badge = $matches[1] . '$';
                }

                $ourSku = 'VK-' . strtoupper(str_replace(['-', ' '], '', $sku));
                $slug = taoSlugVnCron($name) . '-' . strtolower(str_replace(['-', ' '], '', $sku));

                // Tính năng mặc định
                $features = json_encode([
                    "Sử dụng full model AI theo hệ thống",
                    "Thời hạn bản quyền {$duration} ngày",
                    "Giao key tự động ngay sau thanh toán",
                    "Bảo hành 100% trong thời gian sử dụng"
                ], JSON_UNESCAPED_UNICODE);

                // Thêm vào bảng products (tự ẩn nếu tồn kho = 0, phần mềm luôn bật)
                $isActiveNew = ($stock > 0 || $isSoftware) ? 1 : 0;
                $prodImg = trim($it['image'] ?? ($it['thumbnail'] ?? ($it['avatar'] ?? '')));
                if (empty($prodImg) && !empty($partnerImagesMap)) {
                    $cleanName = mb_strtolower(trim($name));
                    $type = strtolower($it['type'] ?? '');
                    if (!empty($partnerImagesMap[$cleanName])) {
                        $prodImg = $partnerImagesMap[$cleanName];
                    } elseif (!empty($partnerImagesMap[$type])) {
                        $prodImg = $partnerImagesMap[$type];
                    }
                }
                if (!empty($prodImg)) {
                    if (strpos($prodImg, 'http') !== 0 && strpos($prodImg, '/') === 0) {
                        $prodImg = $partnerBaseUrl . $prodImg;
                    }
                    $savedImg = luuAnhSanPhamTuDoiTac($prodImg, $sku);
                    if (!empty($savedImg)) {
                        $prodImg = $savedImg;
                    }
                }
                if (empty($prodImg)) {
                    $prodImg = '/assets/images/featured/featured_' . $catSlug . '.png';
                }
                $syncModels = xacDinhSupportedModelsCron($name, $ourSku, $catInfo['ten'] ?? '', $it['description'] ?? '');
                try {
                    $stmtInsP = $pdo->prepare("
                        INSERT INTO `products` 
                        (`category_id`, `sku`, `name`, `slug`, `image`, `badge`, `description`, `price`, `original_price`, `request_count`, `duration_days`, `features`, `supported_models`, `is_hot`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
                    ");
                    $stmtInsP->execute([
                        $catId, $ourSku, $name, $slug, $prodImg, $badge, $it['description'] ?? '', $price, $originalPrice, $requestCount, $duration, $features, $syncModels, $isActiveNew
                    ]);
                    $newPid = (int)$pdo->lastInsertId();
                } catch (PDOException $eDup) {
                    $stmtFindExist = $pdo->prepare("SELECT id FROM `products` WHERE `sku` = ? OR `slug` = ? LIMIT 1");
                    $stmtFindExist->execute([$ourSku, $slug]);
                    $newPid = (int)$stmtFindExist->fetchColumn();
                }

                if ($newPid > 0) {
                    // Thêm mapping
                    $stmtInsMap = $pdo->prepare("
                        INSERT INTO `supplier_product_mappings`
                        (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, 1, 1)
                        ON DUPLICATE KEY UPDATE `cost_price` = VALUES(`cost_price`), `stock` = VALUES(`stock`)
                    ");
                    $stmtInsMap->execute([$newPid, $sup['id'], $sku, $cost, $stock]);

                     $supInfo['da_them_moi']++;
                     $ketQuaTong['tong_them_moi']++;
                     $telegramCatalogEvents['new'][] = ['name' => $name, 'price' => $price, 'stock' => $stock];

                }
            }
        }

        $ketQuaTong['nha_cung_cap'][] = $supInfo;
    }

    $ketQuaTong['thoi_gian_xu_ly'] = round(microtime(true) - $thoiGianBatDau, 3) . 's';
    $ketQuaTong['thong_bao'] = "Đồng bộ thành công! Cập nhật kho/giá: {$ketQuaTong['tong_cap_nhat']} sản phẩm. Thêm mới từ đối tác: {$ketQuaTong['tong_them_moi']} sản phẩm. Tạm hết hàng: {$ketQuaTong['tong_tam_het_hang']} sản phẩm.";

    // Gửi tổng hợp theo từng nhóm, tránh spam một tin cho mỗi sản phẩm.
    if (!empty($telegramCatalogEvents['new'])) {
        $msg = "🆕 <b>SẢN PHẨM MỚI TỪ HỆ THỐNG ĐỒNG BỘ</b>\n";
        foreach (array_slice($telegramCatalogEvents['new'], 0, 30) as $item) {
            $msg .= "• <b>" . telegramEscape($item['name']) . "</b> — " . number_format($item['price'], 0, ',', '.') . "đ — kho: " . (int)$item['stock'] . "\n";
        }
        if (count($telegramCatalogEvents['new']) > 30) $msg .= "… và " . (count($telegramCatalogEvents['new']) - 30) . " sản phẩm khác.\n";
        guiThongBaoTelegram($msg, 'product_new');
    }
    if (!empty($telegramCatalogEvents['out'])) {
        $msg = "📭 <b>SẢN PHẨM VỪA HẾT HÀNG</b>\n";
        foreach (array_slice($telegramCatalogEvents['out'], 0, 30) as $item) $msg .= "• <b>" . telegramEscape($item['name']) . "</b>\n";
        if (count($telegramCatalogEvents['out']) > 30) $msg .= "… và " . (count($telegramCatalogEvents['out']) - 30) . " sản phẩm khác.\n";
        guiThongBaoTelegram($msg, 'product_out');
    }
    if (!empty($telegramCatalogEvents['price'])) {
        $msg = "💸 <b>SẢN PHẨM CÓ THAY ĐỔI GIÁ</b>\n";
        foreach (array_slice($telegramCatalogEvents['price'], 0, 30) as $item) $msg .= "• <b>" . telegramEscape($item['name']) . "</b>: " . number_format($item['old'], 0, ',', '.') . "đ → <b>" . number_format($item['new'], 0, ',', '.') . "đ</b>\n";
        if (count($telegramCatalogEvents['price']) > 30) $msg .= "… và " . (count($telegramCatalogEvents['price']) - 30) . " sản phẩm khác.\n";
        guiThongBaoTelegram($msg, 'product_price');
    }

    // Ghi log hệ thống
    ghiNhatKyHeThong('INFO', 'CRON_SYNC_SUCCESS', $ketQuaTong['thong_bao']);

} catch (Exception $e) {
    $ketQuaTong['thanh_cong'] = false;
    $ketQuaTong['thong_bao'] = "Lỗi trong quá trình chạy Cron: " . $e->getMessage();
    ghiNhatKyHeThong('ERROR', 'CRON_SYNC_ERROR', $e->getMessage());
}

// Trả về JSON nếu chạy qua Web, in ra text nếu chạy qua CLI
if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] " . $ketQuaTong['thong_bao'] . "\n";
    if (!empty($ketQuaTong['nha_cung_cap'])) {
        print_r($ketQuaTong['nha_cung_cap']);
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($ketQuaTong, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
