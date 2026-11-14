<?php
// ========================================================
// CRON / TỰ ĐỘNG ĐỒNG BỘ BẢNG GIÁ MODEL TỪ NGUỒN CUNG CẤP
// Tiệm AI - tiemai.io.vn
// Cập nhật giá theo nhà cung cấp kết nối thực tế
// ========================================================

require_once __DIR__ . '/config.php';

function dongBoBangGiaModelTuNguon() {
    $dataFile = __DIR__ . '/data/models_pricing.json';
    
    // Nguồn cấp chính: GenzShop / 1gw.gwai.cloud
    $url = 'https://genzshop.vn/vi/models-pricing';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($html)) {
        return [
            'thanh_cong' => false,
            'thong_bao'  => "Không thể kết nối nguồn upstream ($httpCode)"
        ];
    }
    
    // Tìm đoạn khai báo var rows = [...]; var providers = {...}; var platforms = {...};
    if (preg_match('/var\s+rows\s*=\s*(\[.*?\]);\s*var\s+providers\s*=\s*(\{.*?\});\s*var\s+platforms\s*=\s*(\{.*?\});/s', $html, $m)) {
        $rows = json_decode($m[1], true);
        $providers = json_decode($m[2], true);
        $platforms = json_decode($m[3], true);
        
        if (is_array($rows) && count($rows) > 0) {
            $dataToSave = [
                'rows'      => $rows,
                'providers' => $providers,
                'platforms' => $platforms,
                'synced_at' => date('Y-m-d H:i:s'),
                'total'     => count($rows)
            ];
            
            if (!is_dir(dirname($dataFile))) {
                mkdir(dirname($dataFile), 0755, true);
            }
            
            file_put_contents($dataFile, json_encode($dataToSave, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            return [
                'thanh_cong' => true,
                'so_luong'   => count($rows),
                'thong_bao'  => "Đã đồng bộ thành công " . count($rows) . " model từ nguồn cung cấp."
            ];
        }
    }
    
    return [
        'thanh_cong' => false,
        'thong_bao'  => 'Không tìm thấy cấu trúc bảng giá trong phản hồi nguồn.'
    ];
}

// Nếu gọi trực tiếp từ CLI
if (php_sapi_name() === 'cli') {
    $kq = dongBoBangGiaModelTuNguon();
    echo ($kq['thanh_cong'] ? "THÀNH CÔNG: " : "THẤT BẠI: ") . $kq['thong_bao'] . PHP_EOL;
}
