<?php
// ========================================================
// CRONJOB TỰ ĐỘNG ĐỒNG BỘ KHO, GIÁ VÀ NHẬP SẢN PHẨM MỚI (CRON SYNC)
// vankhateamshop - Đồng bộ tự động với nhà cung cấp (GenzShop...)
// Tự cập nhật tồn kho, tự cập nhật giá vốn, tự nhập sản phẩm mới, tự ẩn khi hết hàng
// Tương thích cPanel Cron Jobs (* * * * *) & Linux Crontab & Web cURL
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

// Thiết lập chạy qua CLI hoặc Web có kèm secret token
$secretKey = 'VK_CRON_SECRET_2026';
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

require_once __DIR__ . '/config.php';
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

$thoiGianBatDau = microtime(true);
$ketQuaTong = [
    'thanh_cong'        => true,
    'thoi_gian'         => date('Y-m-d H:i:s'),
    'nha_cung_cap'      => [],
    'tong_cap_nhat'     => 0,
    'tong_them_moi'     => 0,
    'tong_tam_het_hang' => 0
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
                guiThongBaoTelegram($thongDiep);
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

        foreach ($nccItems as $it) {
            $sku = trim($it['product_id'] ?? '');
            if (empty($sku)) continue;

            $cost = (float)($it['walletPricing'] ?? 0);
            $stock = (int)($it['available'] ?? 0);
            $name = trim($it['name'] ?? '');

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
                $upMap = $pdo->prepare("UPDATE `supplier_product_mappings` SET `stock` = ?, `cost_price` = ? WHERE `product_id` = ? AND `supplier_id` = ?");
                $upMap->execute([$stock, $cost, $existingPid, $sup['id']]);

                // Tự động kiểm tra giá bán: nếu giá vốn tăng cao hơn giá bán thì tự động điều chỉnh giá bán để không bị lỗ
                $curProd = $pdo->query("SELECT price, category_id FROM `products` WHERE id = {$existingPid}")->fetch();
                if ($curProd && (float)$curProd['price'] < ($cost + 2000)) {
                    $giaBanMoi = ceil(($cost * 1.15) / 1000) * 1000;
                    $pdo->prepare("UPDATE `products` SET `price` = ? WHERE `id` = ?")->execute([$giaBanMoi, $existingPid]);
                }

                // Cập nhật lại danh mục chuẩn xác nếu sản phẩm trước đó đang nằm ở danh mục Khác (1009)
                $type = strtolower($it['type'] ?? 'other');
                $catInfo = $danhMucChuan[$type] ?? $danhMucChuan['other'];
                $catSlug = $catInfo['slug'];
                if (isset($cats[$catSlug]) && (!empty($curProd['category_id']) && (int)$curProd['category_id'] === 1009)) {
                    $targetCatId = (int)$cats[$catSlug];
                    $pdo->prepare("UPDATE `products` SET `category_id` = ? WHERE `id` = ?")->execute([$targetCatId, $existingPid]);
                }

                // Cập nhật ảnh đại diện nếu sản phẩm chưa có ảnh
                $prodImg = $it['image'] ?? null;
                if (!empty($prodImg)) {
                    $pdo->prepare("UPDATE `products` SET `image` = ? WHERE `id` = ? AND (`image` IS NULL OR `image` = '')")->execute([$prodImg, $existingPid]);
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
                if ($cost <= 20000) {
                    $price = $cost + 7000;
                } elseif ($cost <= 60000) {
                    $price = ceil(($cost * 1.25) / 1000) * 1000;
                } elseif ($cost <= 150000) {
                    $price = ceil(($cost * 1.20) / 1000) * 1000;
                } else {
                    $price = ceil(($cost * 1.15) / 1000) * 1000;
                }
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

                // Thêm vào bảng products (tự ẩn nếu tồn kho = 0)
                $isActiveNew = ($stock > 0) ? 1 : 0;
                $prodImg = $it['image'] ?? null;
                try {
                    $stmtInsP = $pdo->prepare("
                        INSERT INTO `products` 
                        (`category_id`, `sku`, `name`, `slug`, `image`, `badge`, `description`, `price`, `original_price`, `request_count`, `duration_days`, `features`, `is_hot`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
                    ");
                    $stmtInsP->execute([
                        $catId, $ourSku, $name, $slug, $prodImg, $badge, $it['description'] ?? '', $price, $originalPrice, $requestCount, $duration, $features, $isActiveNew
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
                }
            }
        }

        $ketQuaTong['nha_cung_cap'][] = $supInfo;
    }

    $ketQuaTong['thoi_gian_xu_ly'] = round(microtime(true) - $thoiGianBatDau, 3) . 's';
    $ketQuaTong['thong_bao'] = "Đồng bộ thành công! Cập nhật kho/giá: {$ketQuaTong['tong_cap_nhat']} sản phẩm. Thêm mới từ đối tác: {$ketQuaTong['tong_them_moi']} sản phẩm. Tạm hết hàng: {$ketQuaTong['tong_tam_het_hang']} sản phẩm.";

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
