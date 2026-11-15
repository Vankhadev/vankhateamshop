<?php
// ========================================================
// TRANG PHẦN MỀM AI & TỰ ĐỘNG HÓA TÙNG DV (TungDV Software Hub)
// vankhateamshop - Phân phối chính hãng các bộ công cụ AI làm video của TungDV
// TungDV Studio Pro, AutoFlow, Voice, AutoEdit, Motion
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Lấy toàn bộ sản phẩm Phần mềm AI (Category ID 1000 hoặc chứa SWF hoặc thuộc danh mục software)
$sqlSoftwares = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug 
    FROM `products` p
    JOIN `categories` c ON p.category_id = c.id
    WHERE p.is_active = 1 AND (p.category_id = 1000 OR p.sku LIKE '%SWF%' OR c.slug = 'software' OR p.name LIKE '%Studio Pro%' OR p.name LIKE '%AutoFlow%' OR p.name LIKE '%TungDV%')
    ORDER BY p.price DESC, p.id ASC
";
$softwares = $pdo->query($sqlSoftwares)->fetchAll();

// Nếu chưa có sản phẩm nào thuộc lọc, lấy danh sách category software
if (empty($softwares)) {
    $softwares = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM `products` p JOIN `categories` c ON p.category_id = c.id WHERE p.is_active = 1 AND c.slug = 'software'")->fetchAll();
}

$siteNameDisplay = layCaiDatHeThong('site_name', SHOP_NAME);
$tenNganSite = trim(explode(' - ', $siteNameDisplay)[0]);
$soZaloDisplay = layCaiDatHeThong('site_contact_zalo', SHOP_ZALO);
$teleDisplay = layCaiDatHeThong('site_contact_telegram', SHOP_TELEGRAM);
$daDangNhap = !empty($_SESSION['user_id']);
$userHienTai = null;
if ($daDangNhap) {
    $stmtMe = $pdo->prepare("SELECT id, name, email, avatar, balance, role, is_reseller, reseller_discount, reseller_key FROM users WHERE id = ?");
    $stmtMe->execute([$_SESSION['user_id']]);
    $userHienTai = $stmtMe->fetch();
}

$sanPhamJs = [];
foreach ($softwares as $p) {
    $featuresArr = !empty($p['features']) ? json_decode($p['features'], true) : [];
    if (!is_array($featuresArr)) $featuresArr = [];
    $giaInfo = tinhGiaDaiLyChoSanPham($pdo, $p, $userHienTai);
    // Tự động thay thế TungDV thành Tên Website trên toàn bộ giao diện
    $tenHienThi = preg_replace('/TungDV\s*/i', $tenNganSite . ' ', $p['name']);
    $moTaHienThi = preg_replace('/TungDV\s*/i', $tenNganSite . ' ', $p['description'] ?? '');
    $sanPhamJs[] = [
        'id'                => (int)$p['id'],
        'category_id'       => (int)$p['category_id'],
        'name'              => $tenHienThi,
        'slug'              => $p['slug'],
        'sku'               => $p['sku'],
        'image'             => $p['image'] ?? '',
        'price'             => (float)$giaInfo['price'],
        'original_price'    => $giaInfo['is_discounted'] ? (float)$giaInfo['retail_price'] : (float)$p['original_price'],
        'is_reseller_price' => (bool)$giaInfo['is_discounted'],
        'discount_percent'  => (float)$giaInfo['discount_percent'],
        'badge'             => $p['badge'] ?? 'Bản quyền AI',
        'request_count'     => $p['request_count'],
        'features'          => $featuresArr,
        'description'       => $moTaHienThi,
        'is_hot'            => (bool)$p['is_hot']
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO TIÊU ĐỀ & TỪ KHÓA TÌM KIẾM ĐỈNH CAO -->
    <title>Hệ Thống Phần Mềm AI & Tự Động Hóa - Studio Pro, AutoFlow, Voice | <?= htmlspecialchars($siteNameDisplay) ?></title>
    <meta name="description" content="Kho phần mềm AI và công cụ làm video tự động hóa chính hãng tại <?= htmlspecialchars($siteNameDisplay) ?>: Studio Pro dịch lồng tiếng video, AutoFlow tạo video tự động, Voice clone giọng đọc, AutoEdit. Giao key tự động 24/7.">
    <meta name="keywords" content="phan mem ai, studio pro, autoflow, voice, autoedit, motion, tool ai lam video, phan mem video <?= htmlspecialchars($siteNameDisplay) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="<?= SITE_URL ?>/phan-mem">

    <!-- OPEN GRAPH CHO ZALO, FACEBOOK -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= SITE_URL ?>/phan-mem">
    <meta property="og:title" content="Hệ Thống Phần Mềm AI & Tự Động Hóa - <?= htmlspecialchars($siteNameDisplay) ?>">
    <meta property="og:description" content="Tự động hóa sản xuất video AI, dịch lồng tiếng, clone giọng đọc và edit video tự động trên Windows. Kích hoạt nhận key ngay lập tức.">
    <meta property="og:image" content="<?= !empty($softwares[0]['image']) ? $softwares[0]['image'] : (SITE_URL . '/assets/images/banner.png') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteNameDisplay) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- SCHEMA.ORG STRUCTURED DATA CHO GOOGLE SEARCH -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "name": "Bộ Phần Mềm AI <?= addslashes($siteNameDisplay) ?> Chính Hãng",
      "description": "Kho phần mềm công cụ hỗ trợ người làm video, dịch lồng tiếng và tự động hóa AI phát hành bởi <?= addslashes($siteNameDisplay) ?>.",
      "url": "<?= SITE_URL ?>/phan-mem",
      "publisher": {
        "@type": "Organization",
        "name": "<?= htmlspecialchars($siteNameDisplay) ?>",
        "url": "<?= SITE_URL ?>"
      }
    }
    </script>

    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        .glass-card { background: rgba(17, 24, 39, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .glow-effect { box-shadow: 0 0 35px -5px rgba(16, 185, 129, 0.25); }
    </style>
</head>
<body x-data="khoPhanMemApp()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-12">

        <!-- HERO BANNER GIỚI THIỆU PHẦN MỀM -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-950/60 via-gray-900 to-indigo-950/60 border border-emerald-500/20 p-8 sm:p-12 shadow-2xl">
            <div class="relative z-10 max-w-3xl space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold tracking-wide uppercase">
                    <i class="fas fa-cubes text-emerald-400"></i>
                    <span><?= htmlspecialchars($tenNganSite) ?> AI Software Suite</span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
                    Hệ Thống Phần Mềm AI & Tự Động Hóa <span class="bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-200"><?= htmlspecialchars($tenNganSite) ?></span>
                </h1>
                <p class="text-sm sm:text-base text-gray-300 leading-relaxed">
                    Trọn bộ giải pháp thông minh dành cho Content Creator, Youtuber, Tiktoker: Dịch lồng tiếng video đa ngôn ngữ, tạo video AI từ kịch bản, clone giọng đọc siêu thực và cắt dựng video tự động.
                </p>
                <div class="flex flex-wrap items-center gap-4 pt-2 text-xs">
                    <div class="flex items-center gap-2 text-emerald-400 font-semibold bg-black/40 px-3 py-1.5 rounded-xl border border-emerald-500/20">
                        <i class="fab fa-windows"></i> Hỗ trợ Windows 10 & 11 (64-bit)
                    </div>
                    <div class="flex items-center gap-2 text-blue-400 font-semibold bg-black/40 px-3 py-1.5 rounded-xl border border-blue-500/20">
                        <i class="fas fa-bolt"></i> Không phụ thuộc API trả phí đắt đỏ
                    </div>
                    <div class="flex items-center gap-2 text-amber-400 font-semibold bg-black/40 px-3 py-1.5 rounded-xl border border-amber-500/20">
                        <i class="fas fa-shield-check"></i> Bảo hành 100% khi có lỗi
                    </div>
                </div>
            </div>
        </div>

        <!-- DANH SÁCH PHẦN MỀM -->
        <div>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 border-b border-gray-800 pb-4">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="fas fa-microchip text-emerald-400"></i> Các Phần Mềm AI <?= htmlspecialchars($tenNganSite) ?> Đang Mở Bán
                    </h2>
                    <p class="text-xs text-gray-400 mt-1">Chọn phần mềm cần dùng để kích hoạt ngay mã bản quyền và tài liệu hướng dẫn</p>
                </div>
                <span class="text-xs text-emerald-400 font-bold bg-emerald-500/10 px-3 py-1.5 rounded-xl border border-emerald-500/30">
                    <?= count($softwares) ?> Phần mềm có sẵn
                </span>
            </div>

            <!-- GRID CÁC PHẦN MỀM -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="sp in danhSachSanPham" :key="sp.id">
                    <div class="glass-card rounded-3xl p-6 flex flex-col justify-between hover:border-emerald-500/40 hover:shadow-xl hover:shadow-emerald-500/10 transition-all duration-300 group">
                        
                        <div>
                            <!-- Header Card: Icon & Badge -->
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div class="w-16 h-16 rounded-2xl bg-gray-900 border border-gray-800 p-2 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform overflow-hidden shadow-inner">
                                    <template x-if="sp.image">
                                        <img :src="sp.image" :alt="sp.name" class="w-full h-full object-contain rounded-xl" onerror="this.src='https://placehold.co/120x120/111827/10b981?text=Software'">
                                    </template>
                                    <template x-if="!sp.image">
                                        <i class="fas fa-cube text-2xl text-emerald-400"></i>
                                    </template>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/30" x-text="sp.badge || 'Bản quyền AI'"></span>
                                    <span class="block text-[10px] font-mono text-gray-500 mt-1" x-text="'SKU: ' + sp.sku"></span>
                                </div>
                            </div>

                            <!-- Tên phần mềm -->
                            <h3 class="text-lg font-bold text-white group-hover:text-emerald-400 transition" x-text="sp.name"></h3>
                            
                            <!-- Mô tả -->
                            <p class="text-xs text-gray-400 mt-2 line-clamp-3 leading-relaxed" x-text="sp.description"></p>

                            <!-- Điểm nổi bật -->
                            <div class="mt-4 pt-3 border-t border-gray-800/80 space-y-1.5 text-xs text-gray-300">
                                <div class="flex items-center gap-2 text-[11px] text-gray-300">
                                    <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
                                    <span>Tương thích Windows 10/11 64-bit</span>
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-gray-300">
                                    <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
                                    <span>Giao diện tiếng Việt 100% dễ dùng</span>
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-gray-300">
                                    <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
                                    <span>Tự động nhận key ngay sau thanh toán</span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Card: Giá & Nút mua -->
                        <div class="mt-6 pt-4 border-t border-gray-800">
                            <div class="flex items-baseline justify-between mb-4">
                                <div>
                                    <span class="text-xs text-gray-400 block">Giá thanh toán:</span>
                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                        <div class="text-xl font-black text-emerald-400" x-text="dinhDangTien(sp.price)"></div>
                                        <template x-if="sp.is_reseller_price">
                                            <span class="text-[10px] font-bold text-purple-300 bg-purple-500/20 px-1.5 py-0.5 rounded border border-purple-500/30">
                                                Đại lý -<span x-text="sp.discount_percent"></span>%
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <a :href="'/san-pham/' + (sp.slug || sp.sku)" class="py-2.5 px-3 rounded-xl bg-gray-800/80 hover:bg-gray-700 text-gray-200 text-xs font-bold text-center transition border border-gray-700">
                                    Chi Tiết
                                </a>
                                <button @click="moModalThanhToan(sp)" class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-1.5">
                                    <i class="fas fa-bolt"></i> Mua Ngay
                                </button>
                            </div>
                        </div>

                    </div>
                </template>
            </div>
        </div>

        <!-- 3 KHỐI CAM KẾT CHẤT LƯỢNG -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6">
            <div class="bg-gray-900/60 border border-gray-800 rounded-3xl p-6 space-y-2">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg">
                    <i class="fas fa-rotate"></i>
                </div>
                <h3 class="text-sm font-bold text-white">Cập Nhật & Nâng Cấp</h3>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Hỗ trợ cập nhật các phiên bản mới của phần mềm khi có tính năng và model AI mới phát hành.
                </p>
            </div>

            <div class="bg-gray-900/60 border border-gray-800 rounded-3xl p-6 space-y-2">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-lg">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3 class="text-sm font-bold text-white">Giao Key 24/7 Tự Động</h3>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Hệ thống quét VietQR ACB tự động nhận diện thanh toán và gửi bản quyền phần mềm kèm link tải sau 3-5 giây.
                </p>
            </div>

            <div class="bg-gray-900/60 border border-gray-800 rounded-3xl p-6 space-y-2">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-lg">
                    <i class="fas fa-headset"></i>
                </div>
                <h3 class="text-sm font-bold text-white">Hỗ Trợ Kỹ Thuật Zalo</h3>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Đội ngũ kỹ thuật hỗ trợ cài đặt, kích hoạt bản quyền và hướng dẫn sử dụng chi tiết qua Zalo: <strong><?= htmlspecialchars($soZaloDisplay) ?></strong>.
                </p>
            </div>
        </div>

    </main>

    <!-- MODAL THANH TOÁN VIETQR TỰ ĐỘNG CHUẨN ĐỒNG BỘ -->
    <div x-show="moPopupThanhToan" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-2xl sm:rounded-3xl max-w-md w-full p-4 sm:p-5 relative shadow-2xl space-y-3.5 max-h-[92vh] overflow-y-auto my-auto">
            
            <!-- HEADER MODAL & NÚT ĐÓNG TÁCH BIỆT RÕ RÀNG -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-white tracking-wide" x-text="daThanhToan ? 'Kích Hoạt Thành Công' : (daTaoDon ? 'Thanh Toán VietQR Tự Động' : 'Thanh Toán Phần Mềm')"></h3>
                </div>
                <button @click="dongThanhToan()" type="button" class="w-8 h-8 rounded-full bg-gray-800/90 hover:bg-gray-700 text-gray-400 hover:text-white flex items-center justify-center transition border border-gray-700/50">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- BƯỚC 1: NHẬP EMAIL & MÃ GIẢM GIÁ -->
            <div x-show="!daTaoDon && !daThanhToan" class="space-y-3.5">
                <div class="text-center">
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Thông Tin Bản Quyền</span>
                    <h3 class="text-base font-bold text-white mt-1" x-text="sanPhamDangMua.name"></h3>
                    <div class="text-xl font-black text-white font-mono mt-1" x-text="dinhDangTien(giaTienChon)"></div>
                </div>

                <div x-show="thongBaoLoiPopup" class="p-2.5 bg-red-950/70 border border-red-800 rounded-xl text-xs text-red-300 text-center font-semibold" x-text="thongBaoLoiPopup"></div>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1">Email Nhận Bản Quyền (Bắt buộc):</label>
                    <input type="email" x-model="emailKhachHang" placeholder="emailcuaban@gmail.com" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1">Mã Giảm Giá (Nếu có):</label>
                    <input type="text" x-model="maGiamGia" placeholder="Mã ưu đãi..." class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white uppercase focus:outline-none focus:border-emerald-500 font-mono">
                </div>

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

                <button @click="taoDonHang()" :disabled="dangTaoDon" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                    <i class="fas fa-circle-notch fa-spin" x-show="dangTaoDon" style="display: none;"></i>
                    <i class="fas fa-qrcode" x-show="!dangTaoDon"></i>
                    <span>Tạo Mã VietQR Thanh Toán</span>
                </button>
            </div>

            <!-- BƯỚC 2: QUÉT MÃ VIETQR (GIAO DIỆN TỐI ƯU MOBILE GỌN ĐẸP) -->
            <div x-show="daTaoDon && !daThanhToan" class="space-y-3">
                
                <!-- Khung Thẻ VietQR nền trắng sang trọng -->
                <div class="bg-white text-gray-900 rounded-2xl p-3.5 sm:p-4 shadow-xl border border-gray-100">
                    
                    <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-black text-[#005ba6] tracking-tight">ACB</span>
                            <span class="text-xs font-semibold text-gray-700"><?= defined('BANK_FULL_NAME') ? BANK_FULL_NAME : 'Ngân hàng TMCP Á Châu' ?></span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
                            <i class="fas fa-bolt text-[9px]"></i> Tự động 24/7
                        </span>
                    </div>

                    <!-- Khung ảnh QR code VietQR -->
                    <div class="border border-gray-200 rounded-xl p-2 bg-white flex flex-col items-center justify-center mb-2.5 shadow-sm">
                        <img :src="linkVietQr" alt="Mã VietQR" class="w-44 sm:w-48 h-auto object-contain mx-auto rounded-lg">
                    </div>

                    <div class="space-y-2 text-left">
                        <!-- DÒNG SẢN PHẨM & SỐ TIỀN -->
                        <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-50/80 border border-emerald-200/60">
                            <div class="min-w-0 pr-2">
                                <span class="block text-[9px] font-bold text-emerald-600 uppercase tracking-wider">PHẦN MỀM</span>
                                <span class="block text-xs font-black text-gray-900 truncate" x-text="sanPhamDangMua?.name"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="block text-[9px] font-bold text-gray-500 uppercase tracking-wider">SỐ TIỀN</span>
                                <span class="block text-sm font-black text-emerald-600 font-mono" x-text="dinhDangTien(giaTienChon)"></span>
                            </div>
                        </div>

                        <!-- SỐ TÀI KHOẢN + NÚT SAO CHÉP -->
                        <div class="border border-gray-200 rounded-xl p-2.5 bg-gray-50/80 flex items-center justify-between">
                            <div>
                                <span class="block text-[9px] font-bold text-gray-400 uppercase tracking-wider">SỐ TÀI KHOẢN (<?= BANK_ACCOUNT_NAME ?>)</span>
                                <span class="block text-sm font-black text-[#0284c7] font-mono mt-0.5"><?= BANK_ACCOUNT_NO ?></span>
                            </div>
                            <button @click="saoChep('<?= BANK_ACCOUNT_NO ?>')" type="button" class="px-2.5 py-1.5 rounded-lg bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy text-[11px]"></i> Sao chép
                            </button>
                        </div>

                        <!-- NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC) -->
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

                <div class="pt-1 space-y-2">
                    <div class="flex items-center justify-center gap-2 text-xs text-blue-400 font-semibold bg-gray-900/90 border border-gray-800 rounded-xl py-2 px-3">
                        <i class="fas fa-circle-notch fa-spin text-blue-400"></i>
                        <span>Tự động kiểm tra sau: <strong class="text-amber-400 font-mono text-sm" x-text="demNguoc5s + 's'">5s</strong></span>
                    </div>

                    <button @click="kiemTraThanhToanNgay(false)" :disabled="dangKiemTraNgay" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-bold text-xs transition shadow-lg shadow-green-600/30 flex items-center justify-center gap-2">
                        <i class="fas fa-magnifying-glass" :class="dangKiemTraNgay ? 'fa-spin' : ''"></i>
                        <span x-text="dangKiemTraNgay ? 'Đang kiểm tra với ngân hàng...' : 'Kiểm Tra Ngay'">Kiểm Tra Ngay</span>
                    </button>
                </div>
            </div>

            <!-- BƯỚC 3: NHẬN BẢN QUYỀN THÀNH CÔNG -->
            <div x-show="daThanhToan" class="text-center py-4 space-y-4">
                <div class="w-14 h-14 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto text-2xl">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="text-lg font-bold text-white">Kích Hoạt Bản Quyền Thành Công!</h3>
                <p class="text-xs text-gray-400">Mã đơn: <span class="font-mono text-white" x-text="maDonHang"></span></p>

                <div class="bg-gray-950 border border-emerald-500/40 rounded-xl p-4 text-left">
                    <p class="text-[11px] font-bold text-emerald-400 uppercase mb-2">Thông tin Bản quyền & Hướng dẫn cài đặt:</p>
                    <textarea readonly class="w-full h-28 bg-gray-900 border border-gray-700 rounded-lg p-2.5 font-mono text-xs text-white select-all focus:outline-none resize-none" x-text="thongTinKeyNhanDuoc"></textarea>
                    <button @click="saoChep(thongTinKeyNhanDuoc)" class="mt-2.5 w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5">
                        <i class="fas fa-copy"></i> Sao chép Bản Quyền
                    </button>
                </div>

                <button @click="dongThanhToan()" class="w-full py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold transition">
                    Hoàn Tất & Đóng
                </button>
            </div>

        </div>
    </div>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function khoPhanMemApp() {
        return {
            danhSachSanPham: <?= json_encode($sanPhamJs, JSON_UNESCAPED_UNICODE) ?>,
            sanPhamDangMua: {},
            giaTienChon: 0,
            emailKhachHang: '<?= !empty($_SESSION["user_email"]) ? htmlspecialchars($_SESSION["user_email"]) : "" ?>',
            maGiamGia: '',
            moPopupThanhToan: false,
            dangTaoDon: false,
            daTaoDon: false,
            daThanhToan: false,
            thongBaoLoiPopup: '',
            maDonHang: '',
            linkVietQr: '',
            thongTinKeyNhanDuoc: '',
            demNguoc5s: 5,
            timerDemNguoc: null,
            dangKiemTraNgay: false,

            dinhDangTien(soTien) {
                return new Intl.NumberFormat('vi-VN').format(soTien) + ' VNĐ';
            },

            moModalThanhToan(sp) {
                this.sanPhamDangMua = sp;
                this.giaTienChon = sp.price;
                this.daTaoDon = false;
                this.daThanhToan = false;
                this.thongBaoLoiPopup = '';
                this.moPopupThanhToan = true;
            },

            dongThanhToan() {
                this.moPopupThanhToan = false;
                if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
            },

            saoChep(noiDung) {
                if (!noiDung) return;
                navigator.clipboard.writeText(noiDung).then(() => {
                    alert('Đã sao chép vào bộ nhớ tạm!');
                });
            },

            taoDonHang() {
                if (!this.emailKhachHang || !this.emailKhachHang.includes('@')) {
                    this.thongBaoLoiPopup = 'Vui lòng nhập địa chỉ Email hợp lệ để nhận bản quyền!';
                    return;
                }
                this.thongBaoLoiPopup = '';
                this.dangTaoDon = true;

                const formData = new FormData();
                formData.append('action', 'tao_don_hang');
                formData.append('product_id', this.sanPhamDangMua.id);
                formData.append('email', this.emailKhachHang);
                formData.append('coupon_code', this.maGiamGia);

                fetch('/backend/api.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        this.dangTaoDon = false;
                        if (res.thanh_cong) {
                            this.daTaoDon = true;
                            this.maDonHang = res.ma_don_hang;
                            this.giaTienChon = res.so_tien;
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
                if (!this.sanPhamDangMua) return;
                this.dangTaoDon = true;
                this.thongBaoLoiPopup = '';

                const formData = new FormData();
                formData.append('action', 'mua_bang_so_du');
                formData.append('product_id', this.sanPhamDangMua.id);
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
                            this.thongTinKeyNhanDuoc = 'Đơn hàng đã hoàn tất nhưng chưa nhận được dữ liệu giao hàng. Vui lòng liên hệ hỗ trợ.'
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
                            this.thongTinKeyNhanDuoc = res.tai_khoan ? res.tai_khoan.join("\n") : 'Bản quyền đã kích hoạt thành công!';
                            if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                        } else if (!laTuDong) {
                            alert('Hệ thống chưa nhận được thanh toán cho mã ' + this.maDonHang + '. Vui lòng đợi trong giây lát!');
                        }
                    })
                    .catch(() => {
                        this.dangKiemTraNgay = false;
                    });
            }
        };
    }
    </script>
</body>
</html>
