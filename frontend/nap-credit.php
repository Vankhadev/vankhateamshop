<?php
// ========================================================
// TRANG NẠP CREDIT API GATEWAY (1gw.gwai.cloud)
// vankhateamshop - Tự động nạp Credit cho API Key 24/7
// Tỷ giá: 1 USD credit = 1.500 VNĐ. Tối thiểu 10 USD.
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

$soZaloTraCuu = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_zalo', SHOP_ZALO) : SHOP_ZALO;
$tenShop = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_name', SHOP_NAME) : SHOP_NAME;

$bankBin = defined('BANK_BIN') ? BANK_BIN : '970416';
$bankAccountNo = defined('BANK_ACCOUNT_NO') ? BANK_ACCOUNT_NO : '22254531';
$bankAccountName = defined('BANK_ACCOUNT_NAME') ? BANK_ACCOUNT_NAME : 'VO VAN KHA';
$bankFullName = defined('BANK_FULL_NAME') ? BANK_FULL_NAME : 'Ngân hàng TMCP Á Châu (ACB)';

$keyTuUrl = htmlspecialchars(trim($_GET['key'] ?? ''));
$usdTuUrl = (int)($_GET['usd'] ?? 10);
if ($usdTuUrl < 10) $usdTuUrl = 10;
?>
<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Nạp Credit API - 1 USD = 1.500 VNĐ - <?= $tenShop ?></title>
    <meta name="description" content="Nạp credit cho API Key hạ tầng 1gw.gwai.cloud. 1 USD credit = 1.500 VNĐ. Tối thiểu 10 USD. Thanh toán VietQR tự động 24/7.">
    <meta name="keywords" content="nap credit, nap credit api, credit 1gw gwai cloud, claude api credit, <?= $tenShop ?>">

    <!-- Open Graph SEO -->
    <meta property="og:title" content="Nạp Credit API 1gw.gwai.cloud - <?= $tenShop ?>">
    <meta property="og:description" content="1 USD credit = 1.500 VNĐ. Tối thiểu 10 USD. Tự động cộng credit 24/7.">
    <meta property="og:type" content="website">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fffbeb',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .9; transform: scale(1.02); }
        }
        .animate-pulse-slow { animation: pulse-slow 3s infinite ease-in-out; }
    </style>
</head>
<body class="bg-[#0B0F19] text-gray-100 min-h-screen flex flex-col font-sans antialiased overflow-x-hidden" 
      x-data="ungDungNapCredit('<?= addslashes($keyTuUrl) ?>', <?= $usdTuUrl ?>)">

    <!-- HEADER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 w-full flex-1 space-y-8">

        <!-- TIÊU ĐỀ & BANNER -->
        <div class="text-center space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-coins text-amber-400"></i> Cổng Nạp Credit Tự Động 24/7
            </div>
            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Nạp Credit API Gateway
            </h1>
            <p class="text-xs sm:text-base font-semibold text-amber-300">
                1 USD credit = 1.500 VNĐ &bull; Tối thiểu 10 USD
            </p>
        </div>

        <!-- KHỐI CẢNH BÁO & LƯU Ý QUAN TRỌNG THEO YÊU CẦU -->
        <div class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-l-4 border-amber-500 rounded-2xl p-4 sm:p-5 space-y-2 text-xs sm:text-sm text-gray-300">
            <div class="flex items-center gap-2 text-amber-400 font-bold text-sm sm:text-base">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Lưu Ý & Quy Định Nạp Credit:</span>
            </div>
            <ul class="space-y-1.5 list-disc list-inside text-gray-300 leading-relaxed font-medium">
                <li><strong class="text-white">Chỉ nạp cho key</strong> thuộc nhà cung cấp <strong class="text-cyan-400 font-mono">1gw.gwai.cloud</strong> (Key có định dạng bắt đầu bằng <code class="px-1.5 py-0.5 rounded bg-gray-800 text-amber-300 font-mono">sk-ant-api03-...</code>).</li>
                <li class="text-rose-300"><strong class="text-rose-400">API Key hết $Credit trong 24h không nạp thêm sẽ bị xóa khỏi hệ thống.</strong></li>
                <li><strong class="text-white">Đơn chờ thanh toán trong 5 phút.</strong> Chuyển đúng số tiền và nội dung thì credit được nạp tự động vào key sau 1-3 giây.</li>
            </ul>
        </div>

        <!-- FORM NẠP CREDIT CHÍNH -->
        <div class="bg-[#111827] border border-amber-500/30 rounded-3xl p-5 sm:p-8 shadow-2xl space-y-6">
            <form @submit.prevent="taoDonNap()" class="space-y-6">

                <!-- 1. TRƯỜNG NHẬP API KEY -->
                <div class="space-y-2">
                    <label class="block text-xs sm:text-sm font-bold text-white flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <i class="fas fa-key text-amber-400"></i> API Key Cần Nạp Credit:
                        </span>
                        <span class="text-[11px] text-gray-400 font-normal">Chỉ nhận key 1gw.gwai.cloud</span>
                    </label>
                    <div class="relative">
                        <input type="text" 
                               x-model="apiKey" 
                               @input="kiemTraDinhDangKey()" 
                               placeholder="sk-ant-api03-..." 
                               class="w-full bg-[#0B0F19] border border-gray-700 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 rounded-2xl px-4 py-3.5 text-xs sm:text-sm text-white font-mono placeholder-gray-600 transition outline-none">
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                            <button type="button" 
                                    @click="danTuClipboard()" 
                                    class="px-2.5 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold transition flex items-center gap-1">
                                <i class="fas fa-paste text-blue-400"></i> Dán
                            </button>
                        </div>
                    </div>
                    <div x-show="thongBaoKey" class="text-xs font-semibold" :class="keyHopLe ? 'text-emerald-400' : 'text-rose-400'" x-text="thongBaoKey"></div>
                </div>

                <!-- 2. TRƯỜNG SỐ USD MUỐN NẠP -->
                <div class="space-y-3">
                    <label class="block text-xs sm:text-sm font-bold text-white flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <i class="fas fa-dollar-sign text-emerald-400"></i> Số USD Muốn Nạp:
                        </span>
                        <span class="text-[11px] text-amber-300 font-semibold">Tối thiểu: 10 USD</span>
                    </label>

                    <div class="relative">
                        <input type="number" 
                               min="10" 
                               step="1" 
                               x-model.number="soUsd" 
                               @input="tinhThanhTien()" 
                               class="w-full bg-[#0B0F19] border border-gray-700 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 rounded-2xl px-4 py-3.5 text-base sm:text-lg text-white font-bold transition outline-none">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">USD</span>
                    </div>

                    <!-- CÁC NÚT CHỌN NHANH SỐ TIỀN -->
                    <div class="flex flex-wrap gap-2">
                        <template x-for="muc in [10, 20, 30, 50, 100, 200, 500]" :key="muc">
                            <button type="button" 
                                    @click="chonMucUsd(muc)" 
                                    :class="soUsd === muc ? 'bg-amber-500 text-black font-black border-amber-400 shadow-lg shadow-amber-500/20' : 'bg-gray-800/80 hover:bg-gray-700 text-gray-300 border-gray-700 font-semibold'" 
                                    class="px-3 py-1.5 rounded-xl border text-xs transition">
                                <span x-text="muc + '$'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- 3. EMAIL NHẬN BIÊN LAI (TÙY CHỌN) -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-300 flex items-center gap-1.5">
                        <i class="fas fa-envelope text-gray-400"></i> Email Nhận Biên Lai Xác Nhận (Không bắt buộc):
                    </label>
                    <input type="email" 
                           x-model="emailNhan" 
                           placeholder="your-email@gmail.com" 
                           class="w-full bg-[#0B0F19] border border-gray-700 focus:border-amber-500 rounded-2xl px-4 py-2.5 text-xs text-white placeholder-gray-600 transition outline-none">
                </div>

                <!-- 4. KHỐI TÍNH TOÁN THÀNH TIỀN NỔI BẬT -->
                <div class="bg-[#0B0F19] border border-amber-500/40 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="space-y-1 text-center sm:text-left">
                        <span class="text-xs text-gray-400 font-semibold block">Tỷ giá quy đổi cố định:</span>
                        <div class="text-xs sm:text-sm text-gray-300 font-bold">
                            <span class="text-amber-400">1 USD Credit</span> = <span class="text-white">1.500 VNĐ</span>
                        </div>
                    </div>

                    <div class="text-center sm:text-right">
                        <span class="text-xs text-gray-400 font-semibold block">Thành tiền cần thanh toán:</span>
                        <div class="text-xl sm:text-3xl font-black text-amber-400 tracking-tight" x-text="dinhDangTien(thanhTienVnd)"></div>
                    </div>
                </div>

                <!-- THÔNG BÁO LỖI NẾU CÓ -->
                <div x-show="thongBaoLoi" class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center gap-2">
                    <i class="fas fa-circle-exclamation text-sm shrink-0"></i>
                    <span x-text="thongBaoLoi"></span>
                </div>

                <!-- 5. NÚT BẤM TẠO ĐƠN NẠP -->
                <button type="submit" 
                        :disabled="dangTaoDon" 
                        class="w-full py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-600 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-black font-black text-sm sm:text-base tracking-wide transition shadow-xl shadow-amber-500/20 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <template x-if="!dangTaoDon">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-bolt text-lg"></i>
                            <span>Tạo đơn nạp (<span x-text="soUsd + '$'"></span> &bull; <span x-text="dinhDangTien(thanhTienVnd)"></span>)</span>
                        </div>
                    </template>
                    <template x-if="dangTaoDon">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-spinner fa-spin text-lg"></i>
                            <span>Đang khởi tạo mã VietQR...</span>
                        </div>
                    </template>
                </button>

            </form>
        </div>

        <!-- BẢNG SO SÁNH CÁC MỐC NẠP PHỔ BIẾN -->
        <div class="bg-[#111827] border border-gray-800 rounded-3xl p-5 sm:p-7 space-y-4 shadow-xl">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-table-list text-amber-400"></i> Bảng Quy Đổi Các Mốc Nạp Thông Dụng:
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5">
                <template x-for="item in bangQuyDoi" :key="item.usd">
                    <div @click="chonMucUsd(item.usd)" 
                         class="cursor-pointer bg-[#0B0F19] hover:border-amber-500/60 border border-gray-800 rounded-2xl p-3 text-center space-y-1 transition group">
                        <div class="text-sm font-black text-amber-300 group-hover:text-amber-400" x-text="item.usd + '$ Credit'"></div>
                        <div class="text-[11px] text-gray-400 font-bold" x-text="item.vnd"></div>
                    </div>
                </template>
            </div>
        </div>

    </main>

    <!-- ======================================================== -->
    <!-- MODAL POPUP THANH TOÁN VIETQR TỰ ĐỘNG CHỜ 5 PHÚT -->
    <!-- ======================================================== -->
    <div x-show="moPopupThanhToan" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto" 
         style="display: none;">

        <div class="relative w-full max-w-lg bg-[#111827] border border-amber-500/40 rounded-3xl p-5 sm:p-7 shadow-2xl space-y-5 my-8"
             @click.away="dongPopupThanhToan()">

            <!-- NÚT ĐÓNG -->
            <button @click="dongPopupThanhToan()" class="absolute top-4 right-4 text-gray-400 hover:text-white w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center transition">
                <i class="fas fa-times text-sm"></i>
            </button>

            <!-- TRƯỜNG HỢP 1: CHỜ THANH TOÁN VIETQR (ĐẾM NGƯỢC 5 PHÚT) -->
            <template x-if="!daThanhToan">
                <div class="space-y-4">
                    <!-- TIÊU ĐỀ POPUP -->
                    <div class="text-center space-y-1">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase">
                            <i class="fas fa-qrcode"></i> Quét Mã VietQR Thanh Toán
                        </div>
                        <h3 class="text-lg sm:text-xl font-black text-white">
                            Nạp <span class="text-amber-400" x-text="donHangData?.so_usd + '$ Credit'"></span>
                        </h3>
                        <p class="text-xs text-gray-400">
                            Chuyển đúng số tiền và nội dung để hệ thống nạp credit tự động sau 1-3 giây
                        </p>
                    </div>

                    <!-- ĐỒNG HỒ ĐẾM NGƯỢC 5 PHÚT -->
                    <div class="flex items-center justify-center gap-2 text-xs font-bold py-1.5 px-3 rounded-xl bg-gray-900 border border-amber-500/20 text-amber-300">
                        <i class="fas fa-clock text-amber-400 animate-spin" style="animation-duration: 4s;"></i>
                        <span>Đơn chờ thanh toán trong:</span>
                        <span class="font-mono text-sm text-white font-black" x-text="dinhDangDemNguoc(giayConLai)"></span>
                    </div>

                    <!-- ẢNH MÃ VIETQR -->
                    <div class="bg-white rounded-2xl p-3 sm:p-4 text-center shadow-lg max-w-[260px] mx-auto border-2 border-amber-500/50 animate-pulse-slow">
                        <img :src="donHangData?.link_vietqr" alt="Mã VietQR Nạp Credit" class="w-full h-auto object-contain rounded-lg mx-auto">
                        <div class="text-[10px] text-gray-600 font-bold mt-1.5 flex items-center justify-center gap-1">
                            <i class="fas fa-shield-check text-green-600"></i> Quét mã từ App Ngân Hàng bất kỳ
                        </div>
                    </div>

                    <!-- THÔNG TIN CHUYỂN KHOẢN CHI TIẾT KÈM NÚT COPY -->
                    <div class="space-y-2 text-xs">
                        <!-- Ngân hàng -->
                        <div class="bg-[#0B0F19] p-2.5 rounded-xl border border-gray-800 flex items-center justify-between">
                            <span class="text-gray-400 font-semibold">Ngân hàng:</span>
                            <span class="text-white font-bold" x-text="donHangData?.bank_info?.bank_name || 'ACB - Á Châu'"></span>
                        </div>

                        <!-- Số tài khoản -->
                        <div class="bg-[#0B0F19] p-2.5 rounded-xl border border-gray-800 flex items-center justify-between">
                            <div>
                                <span class="text-gray-400 font-semibold block text-[11px]">Số tài khoản:</span>
                                <span class="text-cyan-400 font-mono font-black text-sm" x-text="donHangData?.bank_info?.account_no"></span>
                            </div>
                            <button type="button" @click="saoChep(donHangData?.bank_info?.account_no)" class="px-2.5 py-1 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-bold transition flex items-center gap-1">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>

                        <!-- Tên chủ tài khoản -->
                        <div class="bg-[#0B0F19] p-2.5 rounded-xl border border-gray-800 flex items-center justify-between">
                            <span class="text-gray-400 font-semibold">Chủ tài khoản:</span>
                            <span class="text-white font-bold uppercase" x-text="donHangData?.bank_info?.account_name"></span>
                        </div>

                        <!-- Số tiền chính xác -->
                        <div class="bg-[#0B0F19] p-2.5 rounded-xl border border-amber-500/30 flex items-center justify-between">
                            <div>
                                <span class="text-gray-400 font-semibold block text-[11px]">Số tiền cần chuyển:</span>
                                <span class="text-amber-400 font-black text-base" x-text="donHangData?.so_tien_dinh_dang"></span>
                            </div>
                            <button type="button" @click="saoChep(donHangData?.bank_info?.amount)" class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-black text-xs font-black transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>

                        <!-- Nội dung chuyển khoản BẮT BUỘC -->
                        <div class="bg-gradient-to-r from-amber-500/10 to-transparent p-2.5 rounded-xl border border-amber-500/40 flex items-center justify-between">
                            <div>
                                <span class="text-amber-300 font-bold block text-[11px]">Nội dung CK (Bắt buộc ghi đúng):</span>
                                <span class="text-yellow-300 font-mono font-black text-sm select-all" x-text="donHangData?.bank_info?.content"></span>
                            </div>
                            <button type="button" @click="saoChep(donHangData?.bank_info?.content)" class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-black text-xs font-black transition flex items-center gap-1 shadow-sm">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>

                    <!-- THÔNG BÁO ĐANG KIỂM TRA GIAO DỊCH -->
                    <div class="text-center text-xs text-gray-400 flex items-center justify-center gap-2 pt-1">
                        <i class="fas fa-spinner fa-spin text-amber-400"></i>
                        <span>Đang chờ nhận tiền qua SePay (tự động kiểm tra mỗi 3s)...</span>
                    </div>
                </div>
            </template>

            <!-- TRƯỜNG HỢP 2: NẠP THÀNH CÔNG (CHÚC MỪNG) -->
            <template x-if="daThanhToan">
                <div class="text-center space-y-4 py-2">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 border-2 border-emerald-500 flex items-center justify-center mx-auto text-emerald-400 text-3xl shadow-xl shadow-emerald-500/20">
                        <i class="fas fa-check"></i>
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-xl sm:text-2xl font-black text-white">Nạp Credit Thành Công!</h3>
                        <p class="text-xs sm:text-sm text-emerald-400 font-semibold">
                            Đã cộng thành công <span class="text-white font-black" x-text="donHangData?.so_usd + '$ Credit'"></span> vào API Key của bạn.
                        </p>
                    </div>

                    <!-- HỘP THÔNG TIN KEY VÀ CHI TIẾT GIAO DỊCH -->
                    <div class="bg-[#0B0F19] border border-emerald-500/30 rounded-2xl p-4 text-left space-y-2 text-xs">
                        <div class="flex items-center justify-between border-b border-gray-800 pb-2">
                            <span class="text-gray-400">Mã đơn hàng:</span>
                            <span class="font-mono text-white font-bold" x-text="donHangData?.ma_don_hang"></span>
                        </div>
                        <div class="flex items-center justify-between border-b border-gray-800 pb-2">
                            <span class="text-gray-400">Số USD đã nạp:</span>
                            <span class="text-emerald-400 font-black text-sm" x-text="donHangData?.so_usd + '$ Credit'"></span>
                        </div>
                        <div class="flex items-center justify-between border-b border-gray-800 pb-2">
                            <span class="text-gray-400">Số tiền thanh toán:</span>
                            <span class="text-white font-bold" x-text="donHangData?.so_tien_dinh_dang"></span>
                        </div>
                        <div class="space-y-1 pt-1">
                            <span class="text-gray-400 block">Key đã nạp:</span>
                            <code class="block font-mono text-[11px] text-cyan-300 break-all select-all bg-gray-900 p-2 rounded-xl border border-gray-800" x-text="donHangData?.target_key"></code>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 pt-2">
                        <a href="/tra-cuu-key" class="flex-1 py-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-white font-bold text-xs transition text-center flex items-center justify-center gap-1.5">
                            <i class="fas fa-chart-line text-green-400"></i> Kiểm Tra Lại Usage
                        </a>
                        <button type="button" @click="dongPopupThanhToan()" class="flex-1 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-black text-xs transition text-center flex items-center justify-center gap-1.5">
                            <i class="fas fa-arrow-left"></i> Tiếp Tục Nạp Thêm
                        </button>
                    </div>
                </div>
            </template>

        </div>
    </div>

    <!-- FOOTER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungNapCredit(initialKey, initialUsd) {
        return {
            apiKey: initialKey || '',
            soUsd: initialUsd || 10,
            thanhTienVnd: (initialUsd || 10) * 1500,
            emailNhan: '',
            keyHopLe: false,
            thongBaoKey: '',
            thongBaoLoi: '',
            dangTaoDon: false,
            moPopupThanhToan: false,
            donHangData: null,
            giayConLai: 300, // 5 phút
            timerDemNguoc: null,
            timerPolling: null,
            daThanhToan: false,

            bangQuyDoi: [
                { usd: 10,  vnd: '15.000đ' },
                { usd: 20,  vnd: '30.000đ' },
                { usd: 50,  vnd: '75.000đ' },
                { usd: 100, vnd: '150.000đ' },
                { usd: 200, vnd: '300.000đ' },
                { usd: 500, vnd: '750.000đ' }
            ],

            init() {
                this.tinhThanhTien();
                if (this.apiKey) {
                    this.kiemTraDinhDangKey();
                }
            },

            tinhThanhTien() {
                if (this.soUsd < 10) this.soUsd = 10;
                this.thanhTienVnd = Math.round(this.soUsd * 1500);
            },

            chonMucUsd(muc) {
                this.soUsd = muc;
                this.tinhThanhTien();
            },

            kiemTraDinhDangKey() {
                let k = this.apiKey.trim();
                if (!k) {
                    this.keyHopLe = false;
                    this.thongBaoKey = '';
                    return;
                }
                if (k.startsWith('sk-ant-api03-') || k.startsWith('sk-ant-')) {
                    this.keyHopLe = true;
                    this.thongBaoKey = '✓ Định dạng Key hợp lệ (chuẩn nhà cung cấp 1gw.gwai.cloud)';
                } else {
                    this.keyHopLe = false;
                    this.thongBaoKey = '⚠️ Lưu ý: Chỉ hỗ trợ nạp cho Key hạ tầng 1gw.gwai.cloud (bắt đầu bằng sk-ant-api03-)';
                }
            },

            async danTuClipboard() {
                try {
                    const text = await navigator.clipboard.readText();
                    if (text) {
                        this.apiKey = text.trim();
                        this.kiemTraDinhDangKey();
                    }
                } catch (e) {
                    alert('Vui lòng cấp quyền truy cập clipboard hoặc dán thủ công!');
                }
            },

            dinhDangTien(tien) {
                return new Intl.NumberFormat('vi-VN').format(tien) + ' VNĐ';
            },

            dinhDangDemNguoc(giay) {
                let m = Math.floor(giay / 60);
                let s = giay % 60;
                return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            },

            saoChep(vanBan) {
                if (!vanBan) return;
                navigator.clipboard.writeText(vanBan.toString()).then(() => {
                    alert('Đã sao chép: ' + vanBan);
                }).catch(() => {
                    const temp = document.createElement('textarea');
                    temp.value = vanBan;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                    alert('Đã sao chép: ' + vanBan);
                });
            },

            async taoDonNap() {
                this.thongBaoLoi = '';
                let k = this.apiKey.trim();
                if (!k) {
                    this.thongBaoLoi = 'Vui lòng nhập API Key cần nạp credit!';
                    return;
                }
                if (!k.startsWith('sk-ant-')) {
                    this.thongBaoLoi = 'Chỉ nạp cho API Key nhà cung cấp 1gw.gwai.cloud (Key bắt đầu bằng sk-ant-api03-...)!';
                    return;
                }
                if (this.soUsd < 10) {
                    this.thongBaoLoi = 'Số USD muốn nạp tối thiểu là 10 USD!';
                    return;
                }

                this.dangTaoDon = true;
                try {
                    const fd = new FormData();
                    fd.append('action', 'tao_don_nap_credit');
                    fd.append('api_key', k);
                    fd.append('usd_amount', this.soUsd);
                    fd.append('email', this.emailNhan);

                    const res = await fetch('/backend/api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    this.dangTaoDon = false;

                    if (data.thanh_cong) {
                        this.donHangData = data;
                        this.moPopupThanhToan = true;
                        this.daThanhToan = false;
                        this.batDauDemNguocVaPolling();
                    } else {
                        this.thongBaoLoi = data.thong_bao || 'Không thể tạo đơn nạp, vui lòng thử lại!';
                    }
                } catch (e) {
                    this.dangTaoDon = false;
                    this.thongBaoLoi = 'Lỗi kết nối máy chủ, vui lòng thử lại!';
                }
            },

            batDauDemNguocVaPolling() {
                this.giayConLai = 300; // 5 phút
                if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                if (this.timerPolling) clearInterval(this.timerPolling);

                // Đếm ngược 5 phút
                this.timerDemNguoc = setInterval(() => {
                    if (this.daThanhToan) {
                        clearInterval(this.timerDemNguoc);
                        return;
                    }
                    this.giayConLai--;
                    if (this.giayConLai <= 0) {
                        clearInterval(this.timerDemNguoc);
                        clearInterval(this.timerPolling);
                        alert('Đơn nạp đã hết thời gian chờ 5 phút! Vui lòng tạo đơn mới.');
                        this.dongPopupThanhToan();
                    }
                }, 1000);

                // Polling kiểm tra trạng thái thanh toán mỗi 3 giây
                this.timerPolling = setInterval(() => {
                    if (this.daThanhToan || !this.donHangData?.ma_don_hang) {
                        clearInterval(this.timerPolling);
                        return;
                    }
                    fetch('/backend/api.php?action=kiem_tra_trang_thai_don&order_code=' + encodeURIComponent(this.donHangData.ma_don_hang))
                        .then(r => r.json())
                        .then(res => {
                            if (res.thanh_cong && (res.trang_thai === 'COMPLETED' || res.trang_thai === 'PAID')) {
                                this.daThanhToan = true;
                                clearInterval(this.timerPolling);
                                clearInterval(this.timerDemNguoc);
                            }
                        })
                        .catch(() => {});
                }, 3000);
            },

            dongPopupThanhToan() {
                if (this.timerDemNguoc) clearInterval(this.timerDemNguoc);
                if (this.timerPolling) clearInterval(this.timerPolling);
                this.moPopupThanhToan = false;
            }
        };
    }
    </script>
</body>
</html>
