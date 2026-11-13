<?php
// ========================================================
// TRANG SẢN PHẨM & BẢNG GIÁ MODEL TOÀN DIỆN
// vankhateamshop - Danh sách toàn bộ gói API, lọc danh mục, tìm kiếm, mua nhanh VietQR
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Lấy danh mục sản phẩm đang mở bán
$categories = $pdo->query("SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC")->fetchAll();

// Lấy toàn bộ sản phẩm đang hoạt động kèm tên danh mục
$sqlProducts = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug 
    FROM `products` p
    JOIN `categories` c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.is_hot DESC, p.id ASC
";
$products = $pdo->query($sqlProducts)->fetchAll();

// Chuẩn bị dữ liệu JSON cho Alpine.js xử lý tìm kiếm và lọc tức thì
$sanPhamJs = [];
foreach ($products as $p) {
    $featuresArr = !empty($p['features']) ? json_decode($p['features'], true) : [];
    if (!is_array($featuresArr)) $featuresArr = [];
    $sanPhamJs[] = [
        'id'             => (int)$p['id'],
        'category_id'    => (int)$p['category_id'],
        'name'           => $p['name'],
        'slug'           => $p['slug'],
        'sku'            => $p['sku'],
        'image'          => $p['image'] ?? '',
        'price'          => (float)$p['price'],
        'original_price' => (float)$p['original_price'],
        'badge'          => $p['badge'],
        'request_count'  => $p['request_count'],
        'duration_days'  => (int)$p['duration_days'],
        'features'       => $featuresArr,
        'description'    => $p['description'],
        'is_hot'         => (bool)$p['is_hot']
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Sản Phẩm & Bảng Giá API - <?= SHOP_NAME ?></title>
    
    <!-- TỐI ƯU HÓA SEO CHUẨN GOOGLE CHO TRANG SẢN PHẨM -->
    <meta name="description" content="Bảng giá các gói API AI, Cursor Pro, Claude 3.5 Sonnet, ChatGPT Plus, DeepSeek R1 & V3 giá sỉ đại lý. Tự động cấp key qua VietQR.">
    <meta name="keywords" content="bảng giá api ai, mua cursor pro, mua api claude, chatgpt plus, api rẻ nhất, shop van kha">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://shopvankha.com/san-pham">

    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="product.group">
    <meta property="og:title" content="Bảng Giá Sản Phẩm API AI - <?= SHOP_NAME ?>">
    <meta property="og:description" content="Danh sách đầy đủ các gói API AI lập trình, cấp key tự động sau khi quét VietQR.">
    <meta property="og:url" content="https://shopvankha.com/san-pham">
    <meta property="og:site_name" content="<?= SHOP_NAME ?>">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #0B0F19; 
            color: #E5E7EB; 
        }
    </style>
</head>
<body x-data="ungDungTrangSanPham()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1 space-y-8">
        
        <!-- BANNER ĐẦU TRANG SẢN PHẨM -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-950/60 via-indigo-950/60 to-purple-950/60 border border-gray-800 p-8 sm:p-10 shadow-2xl">
            <div class="max-w-3xl space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/30 uppercase tracking-wider">
                    <i class="fas fa-boxes-stacked"></i> Kho API Bản Quyền Tự Động
                </span>
                <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Tất Cả Sản Phẩm & Bảng Giá Model API
                </h1>
                <p class="text-sm text-gray-300 leading-relaxed">
                    Cung cấp tài khoản và API Key bản quyền cho Cursor Pro, Claude 3.5 Sonnet, DeepSeek R1 & V3, OpenAI GPT-4o. Tự động thanh toán qua VietQR và giao key tức thì 24/7.
                </p>
            </div>
        </div>

        <!-- BỘ LỌC TÌM KIẾM, DANH MỤC & SẮP XẾP -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-5 shadow-xl space-y-4">
            
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                
                <!-- Ô TÌM KIẾM THEO TÊN -->
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-500 pointer-events-none">
                        <i class="fas fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" x-model="tuKhoaTim" placeholder="Tìm theo tên sản phẩm, mã SKU, model..." class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-900 border border-gray-700/80 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-blue-500 transition">
                </div>

                <!-- BỘ CHỌN SẮP XẾP -->
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400 font-semibold hidden sm:inline">Sắp xếp:</span>
                    <select x-model="kieuSapXep" class="bg-gray-900 border border-gray-700/80 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500 font-medium">
                        <option value="mac_dinh">Mặc định (Nổi bật nhất)</option>
                        <option value="gia_tang">Giá: Thấp đến Cao</option>
                        <option value="gia_giam">Giá: Cao đến Thấp</option>
                        <option value="ten_az">Tên: A đến Z</option>
                    </select>
                </div>

            </div>

            <!-- TABS CHỌN DANH MỤC -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-1 text-xs font-semibold scrollbar-none">
                <button @click="danhMucChon = 0" 
                        class="px-4 py-2 rounded-xl transition whitespace-nowrap"
                        :class="danhMucChon === 0 ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'bg-gray-900 text-gray-400 hover:text-white border border-gray-800'">
                    <i class="fas fa-border-all mr-1.5"></i> Tất Cả Sản Phẩm
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button @click="danhMucChon = <?= $cat['id'] ?>" 
                            class="px-4 py-2 rounded-xl transition whitespace-nowrap"
                            :class="danhMucChon === <?= $cat['id'] ?> ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'bg-gray-900 text-gray-400 hover:text-white border border-gray-800'">
                        <i class="fas <?= !empty($cat['icon']) ? htmlspecialchars($cat['icon']) : 'fa-cube' ?> mr-1.5"></i> <?= htmlspecialchars($cat['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- DANH SÁCH SẢN PHẨM HIỂN THỊ DẠNG LƯỚI (GRID) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            
            <template x-for="sp in danhSachHienThi" :key="sp.id">
                <div class="bg-[#111827] border border-gray-800 hover:border-gray-700 rounded-3xl p-5 flex flex-col justify-between transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 relative group overflow-hidden">
                    
                    <div>
                        <!-- Phần ảnh đại diện và badge nổi bật -->
                        <div class="relative w-full h-44 rounded-2xl bg-gray-900/90 border border-gray-800/80 flex items-center justify-center p-3 mb-4 overflow-hidden">
                            <template x-if="sp.image">
                                <img :src="sp.image" :alt="sp.name" class="w-full h-full object-contain rounded-xl group-hover:scale-105 transition-transform duration-300" onerror="this.src='https://cdn-icons-png.flaticon.com/512/8644/8644517.png'">
                            </template>
                            <template x-if="!sp.image">
                                <div class="w-16 h-16 rounded-2xl bg-blue-600/10 border border-blue-500/20 text-blue-400 flex items-center justify-center text-3xl">
                                    <i class="fas fa-cube"></i>
                                </div>
                            </template>

                            <template x-if="sp.is_hot">
                                <span class="absolute top-2.5 left-2.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white shadow-md shadow-rose-500/30 uppercase tracking-wider">
                                    HOT 🔥
                                </span>
                            </template>

                            <template x-if="sp.badge">
                                <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40" x-text="sp.badge">
                                </span>
                            </template>
                        </div>

                        <!-- Tên gói sản phẩm (Liên kết tới trang chi tiết) -->
                        <a :href="'/san-pham/' + sp.slug" class="block">
                            <h3 class="text-sm font-bold text-white group-hover:text-blue-400 transition leading-snug mb-1" x-text="sp.name"></h3>
                        </a>
                        <div class="text-[11px] text-gray-500 font-mono mb-3" x-text="'SKU: ' + sp.sku"></div>

                        <!-- Tính năng nổi bật -->
                        <div class="space-y-1.5 mb-4 text-xs text-gray-400">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-bolt text-amber-400 text-[11px] w-3.5 text-center"></i>
                                <span x-text="sp.request_count || 'Không giới hạn tốc độ'"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock text-blue-400 text-[11px] w-3.5 text-center"></i>
                                <span x-text="'Thời hạn: ' + sp.duration_days + ' ngày sử dụng'"></span>
                            </div>
                            <template x-for="ft in (sp.features || []).slice(0, 2)" :key="ft">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-check-circle text-green-400 text-[11px] w-3.5 text-center"></i>
                                    <span class="truncate" x-text="ft"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Khu vực giá & nút mua -->
                    <div class="border-t border-gray-800/80 pt-4 mt-2">
                        <div class="flex items-baseline justify-between mb-3">
                            <div>
                                <span class="text-lg font-black text-amber-400 font-mono" x-text="dinhDangTien(sp.price)"></span>
                                <template x-if="sp.original_price > sp.price">
                                    <span class="text-xs text-gray-500 line-through ml-1.5" x-text="dinhDangTien(sp.original_price)"></span>
                                </template>
                            </div>
                            <span class="text-[10px] text-green-400 font-semibold bg-green-500/10 px-2 py-0.5 rounded-full border border-green-500/30">
                                Sẵn sàng giao
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <a :href="'/san-pham/' + sp.slug" class="py-2.5 rounded-xl bg-gray-850 hover:bg-gray-700 bg-gray-800 text-gray-200 font-bold text-xs transition flex items-center justify-center gap-1.5 border border-gray-700">
                                <i class="fas fa-eye text-blue-400"></i> Chi Tiết
                            </a>
                            <button @click="moModalThanhToan(sp)" class="py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-md shadow-blue-600/30 flex items-center justify-center gap-1.5">
                                <i class="fas fa-bolt"></i> Mua Ngay
                            </button>
                        </div>
                    </div>

                </div>
            </template>

        </div>

        <!-- THÔNG BÁO NẾU KHÔNG CÓ SẢN PHẨM KHỚP BỘ LỌC -->
        <div x-show="danhSachHienThi.length === 0" class="text-center py-16 bg-[#111827] border border-gray-800 rounded-3xl p-8" style="display: none;">
            <div class="w-16 h-16 rounded-full bg-gray-800 text-gray-500 flex items-center justify-center text-2xl mx-auto mb-3">
                <i class="fas fa-magnifying-glass"></i>
            </div>
            <h3 class="text-base font-bold text-white mb-1">Không tìm thấy sản phẩm nào</h3>
            <p class="text-xs text-gray-400 mb-4">Thử thay đổi từ khóa tìm kiếm hoặc chọn danh mục khác.</p>
            <button @click="tuKhoaTim = ''; danhMucChon = 0" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition">
                Xem Tất Cả Sản Phẩm
            </button>
        </div>

    </main>

    <!-- POPUP THANH TOÁN VIETQR ACB CHUẨN MẪU (MODAL) -->
    <div x-show="moPopupThanhToan" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-3xl max-w-md w-full p-5 sm:p-6 relative shadow-2xl overflow-y-auto max-h-[92vh]">
            
            <button @click="dongThanhToan()" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
                <i class="fas fa-times text-lg"></i>
            </button>

            <!-- BƯỚC 1: ĐIỀN EMAIL & XÁC NHẬN ĐẶT HÀNG -->
            <div x-show="!daTaoDon">
                <div class="text-center mb-5">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Xác Nhận Đơn Hàng</span>
                    <h3 class="text-lg font-bold text-white mt-1" x-text="sanPhamDangMua?.name"></h3>
                    <div class="text-xl font-black text-amber-400 font-mono mt-1" x-text="dinhDangTien(sanPhamDangMua?.price || 0)"></div>
                </div>

                <div x-show="thongBaoLoiPopup" class="p-3 rounded-xl mb-4 bg-red-950/70 border border-red-800 text-red-300 text-xs font-semibold" x-text="thongBaoLoiPopup"></div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Email nhận mã Key / Tài khoản:</label>
                        <input type="email" x-model="emailKhachHang" placeholder="emailcuaban@gmail.com" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Mã giảm giá (nếu có):</label>
                        <input type="text" x-model="maGiamGia" placeholder="Nhập mã ưu đãi..." class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3.5 py-2.5 text-xs text-white uppercase focus:outline-none focus:border-blue-500 font-mono">
                    </div>

                    <button @click="taoDonHang()" :disabled="dangTaoDon" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-200 shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                        <span x-show="dangTaoDon"><i class="fas fa-circle-notch fa-spin"></i> Đang khởi tạo đơn hàng...</span>
                        <span x-show="!dangTaoDon"><i class="fas fa-qrcode mr-1"></i> Tạo Mã VietQR ACB Thanh Toán</span>
                    </button>
                </div>
            </div>

            <!-- BƯỚC 2: QUÉT MÃ VIETQR ĐỂ CHUYỂN KHOẢN (CHUẨN THEO ẢNH GIAO DIỆN) -->
            <div x-show="daTaoDon && !daThanhToan" class="space-y-3">
                
                <!-- Khung Thẻ VietQR nền trắng sang trọng chuẩn mẫu ảnh -->
                <div class="bg-white text-gray-900 rounded-2xl p-4 sm:p-5 shadow-xl border border-gray-100">
                    
                    <!-- Dòng tiêu đề Ngân hàng ACB + Huy hiệu Tự động 24/7 -->
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl font-black text-[#005ba6] tracking-tight">ACB</span>
                            <span class="text-xs font-semibold text-gray-700"><?= defined('BANK_FULL_NAME') ? BANK_FULL_NAME : 'Ngân hàng TMCP Á Châu' ?></span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
                            <i class="fas fa-bolt text-[10px]"></i> Tự động 24/7
                        </span>
                    </div>

                    <!-- Khung ảnh QR code VietQR -->
                    <div class="border border-gray-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center mb-3.5 shadow-sm">
                        <div class="flex items-center gap-1 mb-2 font-black text-sm tracking-wider">
                            <span class="text-rose-600">VIET</span><span class="text-blue-600">QR</span>
                        </div>
                        <img :src="linkVietQr" alt="Mã VietQR" class="w-56 h-auto object-contain mx-auto rounded-lg">
                        <div class="flex items-center justify-center gap-2 text-[11px] text-gray-500 font-semibold mt-2.5">
                            <span class="text-blue-600 font-bold">napas<span class="text-red-500 font-normal">247</span></span>
                            <span>|</span>
                            <span class="text-[#005ba6] font-bold">ACB</span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1">Quét mã VietQR để chuyển tiền nhanh</p>
                    </div>

                    <!-- Thông tin tài khoản & nội dung chuyển khoản -->
                    <div class="space-y-2.5 text-left">
                        
                        <!-- TÊN SẢN PHẨM -->
                        <div class="border border-blue-200 rounded-xl px-3.5 py-2.5 bg-blue-50/50">
                            <span class="block text-[10px] font-bold text-blue-600 uppercase tracking-wider">TÊN SẢN PHẨM</span>
                            <span class="block text-sm font-black text-gray-900 mt-0.5" x-text="sanPhamDangMua?.name"></span>
                        </div>

                        <!-- CHỦ TÀI KHOẢN -->
                        <div class="border border-gray-200 rounded-xl px-3.5 py-2.5 bg-gray-50/50">
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">CHỦ TÀI KHOẢN</span>
                            <span class="block text-sm font-black text-gray-900 mt-0.5"><?= BANK_ACCOUNT_NAME ?></span>
                        </div>

                        <!-- SỐ TÀI KHOẢN + NÚT SAO CHÉP -->
                        <div class="border border-gray-200 rounded-xl px-3.5 py-2.5 bg-gray-50/50 flex items-center justify-between">
                            <div>
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">SỐ TÀI KHOẢN</span>
                                <span class="block text-base font-black text-[#0284c7] mt-0.5 font-mono"><?= BANK_ACCOUNT_NO ?></span>
                            </div>
                            <button @click="saoChep('<?= BANK_ACCOUNT_NO ?>')" class="px-3 py-1.5 rounded-lg bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                        </div>

                        <!-- NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC) - VIỀN NÉT ĐỨT XANH DƯƠNG -->
                        <div class="border-2 border-dashed border-[#0284c7] bg-[#f0f9ff] rounded-xl px-3.5 py-2.5 flex items-center justify-between">
                            <div>
                                <span class="block text-[10px] font-extrabold text-rose-500 uppercase tracking-wider">NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC)</span>
                                <span class="block text-base font-black text-rose-600 mt-0.5 font-mono tracking-wide" x-text="maDonHang"></span>
                            </div>
                            <button @click="saoChep(maDonHang)" class="px-3 py-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                        </div>

                        <!-- SỐ TIỀN THANH TOÁN -->
                        <div class="flex justify-between items-center px-1 pt-1 text-xs">
                            <span class="text-gray-500 font-semibold">Số tiền thanh toán:</span>
                            <span class="text-base font-black text-emerald-600 font-mono" x-text="dinhDangTien(giaTienChon)"></span>
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

                <div class="bg-gray-900 border border-green-500/40 rounded-xl p-4 text-left mb-4">
                    <p class="text-[11px] font-bold text-green-400 uppercase mb-2">Thông tin API Key / Tài khoản của bạn:</p>
                    <div class="bg-black/80 p-2.5 rounded-lg font-mono text-xs text-amber-300 break-all select-all flex justify-between items-center border border-gray-800">
                        <span x-text="keyDaTraVe"></span>
                        <button @click="saoChep(keyDaTraVe)" class="ml-2 text-gray-400 hover:text-white"><i class="fas fa-copy"></i></button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Hệ thống đã gửi bản sao lưu về email: <span class="text-white" x-text="emailKhachHang"></span></p>
                </div>

                <button @click="dongThanhToan()" class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    Hoàn Tất & Đóng
                </button>
            </div>

        </div>
    </div>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungTrangSanPham() {
        return {
            tatCaSanPham: <?= json_encode($sanPhamJs, JSON_UNESCAPED_UNICODE) ?>,
            danhMucChon: 0,
            tuKhoaTim: '',
            kieuSapXep: 'mac_dinh',

            // Quản lý Modal mua hàng
            moPopupThanhToan: false,
            sanPhamDangMua: null,
            emailKhachHang: '<?= !empty($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '' ?>',
            maGiamGia: '',
            dangTaoDon: false,
            daTaoDon: false,
            daThanhToan: false,
            maDonHang: '',
            linkVietQr: '',
            giaTienChon: 0,
            keyDaTraVe: '',
            thongBaoLoiPopup: '',

            // Quản lý đếm ngược 5s & kiểm tra thanh toán
            demNguoc5s: 5,
            dangKiemTraNgay: false,
            timerDemNguoc: null,

            get danhSachHienThi() {
                let ketQua = this.tatCaSanPham;

                // Lọc theo danh mục
                if (this.danhMucChon > 0) {
                    ketQua = ketQua.filter(sp => sp.category_id === this.danhMucChon);
                }

                // Lọc theo từ khóa tìm kiếm
                if (this.tuKhoaTim && this.tuKhoaTim.trim() !== '') {
                    let kw = this.tuKhoaTim.toLowerCase().trim();
                    ketQua = ketQua.filter(sp => 
                        sp.name.toLowerCase().includes(kw) || 
                        sp.sku.toLowerCase().includes(kw) ||
                        (sp.description && sp.description.toLowerCase().includes(kw))
                    );
                }

                // Sắp xếp
                if (this.kieuSapXep === 'gia_tang') {
                    ketQua = [...ketQua].sort((a, b) => a.price - b.price);
                } else if (this.kieuSapXep === 'gia_giam') {
                    ketQua = [...ketQua].sort((a, b) => b.price - a.price);
                } else if (this.kieuSapXep === 'ten_az') {
                    ketQua = [...ketQua].sort((a, b) => a.name.localeCompare(b.name));
                }

                return ketQua;
            },

            dinhDangTien(soTien) {
                return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(soTien);
            },

            moModalThanhToan(sp) {
                this.sanPhamDangMua = sp;
                this.giaTienChon = sp.price;
                this.daTaoDon = false;
                this.daThanhToan = false;
                this.thongBaoLoiPopup = '';
                this.moPopupThanhToan = true;
            },

            taoDonHang() {
                if (!this.emailKhachHang || !this.emailKhachHang.includes('@')) {
                    this.thongBaoLoiPopup = 'Vui lòng nhập địa chỉ email hợp lệ để nhận key!';
                    return;
                }

                this.dangTaoDon = true;
                this.thongBaoLoiPopup = '';

                const formData = new FormData();
                formData.append('action', 'tao_don_hang');
                formData.append('product_id', this.sanPhamDangMua.id);
                formData.append('email', this.emailKhachHang);
                formData.append('coupon_code', this.maGiamGia);

                fetch('/backend/api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    this.dangTaoDon = false;
                    if (data.thanh_cong) {
                        this.daTaoDon = true;
                        this.maDonHang = data.ma_don_hang;
                        this.linkVietQr = data.link_vietqr;
                        this.giaTienChon = data.so_tien;
                        this.batDauKiemTraThanhToan();
                    } else {
                        this.thongBaoLoiPopup = data.thong_bao || 'Không thể tạo đơn hàng!';
                    }
                })
                .catch(() => {
                    this.dangTaoDon = false;
                    this.thongBaoLoiPopup = 'Lỗi kết nối máy chủ!';
                });
            },

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
                        this.kiemTraThanhToanNgay(true);
                    }
                }, 1000);
            },

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

            giaLapThanhToan() {
                this.daThanhToan = true;
                this.keyDaTraVe = 'sk-vankha-' + Math.random().toString(36).substring(2, 12) + Math.random().toString(36).substring(2, 12);
                if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
            },

            dongThanhToan() {
                this.moPopupThanhToan = false;
                if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
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
