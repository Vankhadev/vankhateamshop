<?php
// ========================================================
// TRANG DÀNH RIÊNG: TRA CỨU KEY & KIỂM TRA SỐ LƯỢT DÙNG (USAGE)
// vankhateamshop - Tra cứu thời gian thực hạn dùng, request còn lại
// Hỗ trợ Base URL đồng bộ: https://tiemai.io.vn/v1
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

$soZaloTraCuu = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_zalo', SHOP_ZALO) : SHOP_ZALO;
$tenShop = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_name', SHOP_NAME) : SHOP_NAME;

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'tiemai.io.vn';
$baseUrlWebsite = $protocol . '://' . $host . '/v1';
$chatEndpoint = $baseUrlWebsite . '/chat/completions';

// Lấy key truyền từ URL nếu có (?key=...)
$keyTuUrl = htmlspecialchars(trim($_GET['key'] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Tra Cứu Key API & Kiểm Tra Số Lượt Request - <?= $tenShop ?></title>
    <meta name="description" content="Tra cứu số lượt request còn lại, hạn sử dụng, danh sách model AI và Base URL kết nối cho API Cursor, Claude, OpenAI tại <?= $tenShop ?>.">
    <meta name="keywords" content="tra cuu key, kiem tra key cursor, check api cursor, usage cursor, <?= $tenShop ?>">

    <!-- Open Graph SEO -->
    <meta property="og:title" content="Tra Cứu Key API & Kiểm Tra Usage - <?= $tenShop ?>">
    <meta property="og:description" content="Kiểm tra hạn dùng và số lượng request còn lại của Key API tự động 24/7.">
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
                            50: '#eef2ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
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
    </style>
</head>
<body class="bg-[#0B0F19] text-gray-100 min-h-screen flex flex-col font-sans antialiased overflow-x-hidden" x-data="ungDungTraCuuKey('<?= addslashes($keyTuUrl) ?>')">

    <!-- HEADER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 w-full flex-1 space-y-8">

        <!-- TIÊU ĐỀ & BANNER TRA CỨU -->
        <div class="text-center space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-chart-line"></i> Kiểm Tra Trạng Thái Usage
            </div>
            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Tra Cứu Key API & Hạn Sử Dụng
            </h1>
            <p class="text-xs sm:text-sm text-gray-400 max-w-2xl mx-auto leading-relaxed">
                Nhập mã API Key hoặc mã đơn hàng (#VK...) để kiểm tra số request đã dùng, hạn sử dụng, thông tin chu kỳ reset và kết nối Base URL OpenAI.
            </p>
        </div>

        <!-- KHỐI CHÍNH: Ô TÌM KIẾM + TAB NAVIGATION + BẢNG THÔNG TIN USAGE -->
        <div class="bg-[#111827] border border-amber-500/20 rounded-3xl p-4 sm:p-7 shadow-2xl space-y-6 max-w-4xl mx-auto">
            
            <!-- Ô NHẬP TÌM KIẾM KEY -->
            <form @submit.prevent="traCuuNgay()" class="flex flex-col sm:flex-row gap-2.5">
                <div class="relative flex-1">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 text-sm">
                        <i class="fas fa-magnifying-glass"></i>
                    </span>
                    <input type="text" x-model="oNhapKey" placeholder="Nhập API Key hoặc Mã đơn hàng (#VK...)" 
                           class="w-full bg-[#0B0F19] border border-gray-700 focus:border-amber-500 rounded-2xl pl-10 pr-10 py-3 text-sm font-mono text-white placeholder-gray-500 focus:outline-none transition">
                    <button type="button" x-show="oNhapKey" @click="oNhapKey = ''; ketQua = null; loi = ''" 
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
                <button type="submit" :disabled="dangTai" 
                        class="px-7 py-3 rounded-2xl bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-lg shadow-amber-600/30 whitespace-nowrap">
                    <i class="fas fa-circle-notch fa-spin" x-show="dangTai" style="display: none;"></i>
                    <i class="fas fa-magnifying-glass" x-show="!dangTai"></i>
                    <span>Kiểm tra</span>
                </button>
            </form>

            <!-- THÔNG BÁO LỖI NẾU CÓ -->
            <div x-show="loi" class="bg-red-950/40 border border-red-800/60 rounded-2xl p-4 text-xs text-red-300 flex items-start gap-3" style="display: none;" x-transition>
                <i class="fas fa-circle-exclamation text-base text-red-400 shrink-0 mt-0.5"></i>
                <div>
                    <div class="font-bold text-red-200">Không tìm thấy dữ liệu</div>
                    <div x-text="loi" class="mt-0.5"></div>
                </div>
            </div>

            <!-- THANH TABS NAVIGATION (4 TABS CHUẨN GIAO DIỆN) -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-gray-800 no-scrollbar">
                <button type="button" @click="tabHienTai = 1"
                        :class="tabHienTai === 1 ? 'bg-amber-600 text-white font-bold shadow-md shadow-amber-600/30' : 'bg-gray-800/80 text-gray-400 hover:text-gray-200 hover:bg-gray-800 border border-gray-700/60'"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap">
                    <i class="fas fa-check-circle"></i>
                    <span>Thông tin Usage</span>
                </button>
                <button type="button" @click="tabHienTai = 2"
                        :class="tabHienTai === 2 ? 'bg-amber-600 text-white font-bold shadow-md shadow-amber-600/30' : 'bg-gray-800/80 text-gray-400 hover:text-gray-200 hover:bg-gray-800 border border-gray-700/60'"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap">
                    <i class="fas fa-calendar-days"></i>
                    <span>Daily Usage</span>
                </button>
                <button type="button" @click="tabHienTai = 3"
                        :class="tabHienTai === 3 ? 'bg-amber-600 text-white font-bold shadow-md shadow-amber-600/30' : 'bg-gray-800/80 text-gray-400 hover:text-gray-200 hover:bg-gray-800 border border-gray-700/60'"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap">
                    <i class="fas fa-bolt"></i>
                    <span>Overage</span>
                </button>
                <button type="button" @click="tabHienTai = 4"
                        :class="tabHienTai === 4 ? 'bg-amber-600 text-white font-bold shadow-md shadow-amber-600/30' : 'bg-gray-800/80 text-gray-400 hover:text-gray-200 hover:bg-gray-800 border border-gray-700/60'"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Lịch sử request</span>
                </button>
            </div>

            <!-- NỘI DUNG TỪNG TAB -->

            <!-- TAB 1: THÔNG TIN USAGE -->
            <div x-show="tabHienTai === 1" class="border border-blue-900/40 bg-[#0B0F19]/90 rounded-2xl p-4 sm:p-6 space-y-5" x-transition>
                
                <!-- Hàng 1: API Key & Trạng thái -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-800/80 pb-3">
                    <div class="flex items-center gap-2 text-xs sm:text-sm">
                        <span class="text-gray-400 font-semibold">API Key:</span>
                        <span class="font-mono font-bold text-amber-300 break-all" x-text="ketQua ? ketQua.key_masked : '--'"></span>
                        <button type="button" x-show="ketQua" @click="saoChep(ketQua.key_goc)" 
                                class="text-gray-400 hover:text-white p-1 transition" title="Sao chép Key">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div x-show="ketQua">
                        <span :class="ketQua?.is_active ? 'bg-green-500/20 text-green-400 border-green-500/30' : 'bg-red-500/20 text-red-400 border-red-500/30'" 
                              class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-bold">
                            <span class="w-2 h-2 rounded-full" :class="ketQua?.is_active ? 'bg-green-400 animate-pulse' : 'bg-red-400'"></span>
                            <span x-text="ketQua?.trang_thai"></span>
                        </span>
                    </div>
                </div>

                <!-- Hàng 2: Credit Usage + Badge Gateway -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-table-cells text-amber-400 text-sm"></i>
                        <span class="text-sm font-bold text-white">Credit Usage</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-[11px] font-bold">Gateway</span>
                    </div>
                    <div class="text-xs text-gray-400 font-mono">
                        <span x-text="ketQua ? ketQua.ten_goi : '--'"></span>
                    </div>
                </div>

                <!-- Hàng 3: 3 Cột chỉ số chính (REQUESTS, TOKENS, COST) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-left">
                    <div class="bg-[#111827] p-3.5 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">REQUESTS</div>
                        <div class="text-base sm:text-lg font-black text-white font-mono" x-text="ketQua ? ketQua.requests_val : '--'"></div>
                        <div class="text-[11px] text-gray-400 truncate" x-text="ketQua ? ketQua.requests_sub : '--'"></div>
                    </div>
                    <div class="bg-[#111827] p-3.5 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">TOKENS</div>
                        <div class="text-base sm:text-lg font-black text-cyan-400 font-mono" x-text="ketQua ? ketQua.tokens_val : '--'"></div>
                        <div class="text-[11px] text-gray-400 truncate" x-text="ketQua ? ketQua.tokens_sub : '--'"></div>
                    </div>
                    <div class="bg-[#111827] p-3.5 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">COST</div>
                        <div class="text-base sm:text-lg font-black text-emerald-400 font-mono" x-text="ketQua ? ketQua.cost_val : '--'"></div>
                        <div class="text-[11px] text-gray-400 truncate" x-text="ketQua ? ketQua.cost_sub : '--'"></div>
                    </div>
                </div>

                <!-- Hàng 4: Thanh tiến độ sử dụng & Hạn hết hạn -->
                <div class="space-y-2 pt-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-400 font-medium">Tiến độ tiêu thụ:</span>
                        <div class="flex items-center gap-1.5 font-mono text-amber-400 font-bold">
                            <span x-text="ketQua ? ketQua.progress_text : '-- / --'"></span>
                            <button type="button" @click="traCuuNgay()" class="hover:text-amber-300 text-gray-400 p-0.5" title="Làm mới">
                                <i class="fas fa-arrows-rotate text-[11px]" :class="dangTai ? 'fa-spin' : ''"></i>
                            </button>
                        </div>
                    </div>
                    <div class="w-full h-2.5 bg-gray-950 rounded-full overflow-hidden border border-gray-800 p-0.5">
                        <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-amber-500 transition-all duration-500" 
                             :style="`width: ${ketQua?.phan_tram || 0}%`"></div>
                    </div>
                    <div class="flex items-center justify-end text-[11px] text-gray-400">
                        <span>Hết hạn: <strong class="text-gray-200" x-text="ketQua ? (ketQua.ngay_het_han + ' (' + ketQua.han_dung + ')') : '--'"></strong></span>
                    </div>
                </div>

                <!-- Hàng 5: 2 Thẻ lớn: CÒN LẠI & TUẦN (Chu kỳ) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="bg-[#111827] p-3.5 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">CÒN LẠI</div>
                        <div class="text-base sm:text-lg font-black text-emerald-400" x-text="ketQua ? ketQua.con_lai : '—'"></div>
                    </div>
                    <div class="bg-[#111827] p-3.5 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">TUẦN / CHU KỲ</div>
                        <div class="text-base sm:text-lg font-black text-amber-300" x-text="ketQua ? ketQua.tuan_chu_ky : '—'"></div>
                    </div>
                </div>

                <!-- Hàng 6: Reset lúc & Cập nhật -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-gray-400 pt-1">
                    <div>
                        Reset lúc: <strong class="text-gray-200 font-mono" x-text="ketQua ? ketQua.reset_luc : '--'"></strong>
                    </div>
                    <div class="flex items-center gap-1.5 text-gray-500">
                        <i class="far fa-clock"></i> Cập nhật: <span class="text-gray-400" x-text="ketQua ? ketQua.cap_nhat : '--'"></span>
                    </div>
                </div>

                <!-- Hàng 7: Token Limit & Cost Limit -->
                <div class="border-t border-gray-800 pt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-400">
                    <div>
                        Token Limit: <strong class="text-cyan-300 font-mono" x-text="ketQua ? ketQua.token_limit : '--'"></strong>
                    </div>
                    <div>
                        Cost Limit: <strong class="text-emerald-300 font-mono" x-text="ketQua ? ketQua.cost_limit : '--'"></strong>
                    </div>
                </div>

            </div>

            <!-- TAB 2: DAILY USAGE -->
            <div x-show="tabHienTai === 2" class="border border-blue-900/40 bg-[#0B0F19]/90 rounded-2xl p-4 sm:p-6 space-y-4" style="display: none;" x-transition>
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-calendar-days text-amber-400"></i> Lịch Sử Tiêu Dùng 7 Ngày Gần Nhất
                    </h3>
                    <span class="text-xs text-gray-500">Thống kê tự động</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-300">
                        <thead class="text-[11px] uppercase tracking-wider text-gray-400 bg-gray-900/80 border-b border-gray-800">
                            <tr>
                                <th class="py-2.5 px-3">Ngày</th>
                                <th class="py-2.5 px-3">Số Request</th>
                                <th class="py-2.5 px-3">Tokens Ước Tính</th>
                                <th class="py-2.5 px-3">Chi Phí</th>
                                <th class="py-2.5 px-3 text-right">Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60 font-mono">
                            <template x-for="item in (ketQua?.daily_usage || [])" :key="item.date">
                                <tr class="hover:bg-gray-800/30 transition">
                                    <td class="py-2.5 px-3 text-gray-200 font-sans font-medium" x-text="item.date"></td>
                                    <td class="py-2.5 px-3 text-amber-300 font-bold" x-text="item.requests"></td>
                                    <td class="py-2.5 px-3 text-cyan-300" x-text="item.tokens"></td>
                                    <td class="py-2.5 px-3 text-gray-400" x-text="item.cost"></td>
                                    <td class="py-2.5 px-3 text-right">
                                        <span class="px-2 py-0.5 rounded-full bg-green-500/20 text-green-400 text-[10px] font-sans font-bold" x-text="item.status"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!ketQua">
                                <td colspan="5" class="py-6 text-center text-gray-500 font-sans">
                                    Vui lòng nhập API Key để xem chi tiết lịch sử tiêu dùng theo ngày
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: OVERAGE -->
            <div x-show="tabHienTai === 3" class="border border-blue-900/40 bg-[#0B0F19]/90 rounded-2xl p-4 sm:p-6 space-y-4" style="display: none;" x-transition>
                <div class="border-b border-gray-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-bolt text-amber-400"></i> Quản Lý Overage & Giới Hạn Tốc Độ (Rate Limit)
                    </h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-[#111827] p-4 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Tốc Độ Giới Hạn (Rate Limit)</div>
                        <div class="text-base font-black text-amber-300" x-text="ketQua?.overage?.rate_limit || '60 requests / phút'"></div>
                        <div class="text-[11px] text-gray-500">Áp dụng cho mỗi IP / Client gọi tới Base URL</div>
                    </div>
                    <div class="bg-[#111827] p-4 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Phụ Phí Vượt Mức (Overage Fee)</div>
                        <div class="text-base font-black text-emerald-400" x-text="ketQua?.overage?.overage_fee || '0 đ (Miễn phí)'"></div>
                        <div class="text-[11px] text-gray-500">Hoàn toàn không phát sinh phụ phí ẩn ngoài gói</div>
                    </div>
                    <div class="bg-[#111827] p-4 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Trạng Thái Cổng Gateway</div>
                        <div class="text-base font-black text-green-400 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse"></span>
                            <span>Online 24/7 (Sẵn sàng)</span>
                        </div>
                        <div class="text-[11px] text-gray-500">Hạ tầng Cloudflare Reverse Proxy tốc độ cao</div>
                    </div>
                    <div class="bg-[#111827] p-4 rounded-xl border border-gray-800 space-y-1">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Chu Kỳ Điều Tiết</div>
                        <div class="text-base font-black text-cyan-300" x-text="ketQua?.tuan_chu_ky || 'Reset 07:00 AM mỗi ngày'"></div>
                        <div class="text-[11px] text-gray-500">Tự động làm mới số lượng request theo lịch trình</div>
                    </div>
                </div>
                <div class="bg-blue-950/30 border border-blue-900/50 rounded-xl p-3.5 text-xs text-blue-200 flex items-start gap-2.5">
                    <i class="fas fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
                    <span><strong class="text-white">Chính sách Overage:</strong> <span x-text="ketQua?.overage?.chinh_sach || 'Hệ thống tự cân bằng tải và bảo vệ tài khoản, hạn chế spam request và tự động hồi phục chu kỳ.'"></span></span>
                </div>
            </div>

            <!-- TAB 4: LỊCH SỬ REQUEST -->
            <div x-show="tabHienTai === 4" class="border border-blue-900/40 bg-[#0B0F19]/90 rounded-2xl p-4 sm:p-6 space-y-4" style="display: none;" x-transition>
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-amber-400"></i> Lịch Sử Request Gần Nhất
                    </h3>
                    <span class="text-xs text-gray-500">10 lượt gọi mới nhất</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-300">
                        <thead class="text-[11px] uppercase tracking-wider text-gray-400 bg-gray-900/80 border-b border-gray-800">
                            <tr>
                                <th class="py-2.5 px-3">Thời Gian</th>
                                <th class="py-2.5 px-3">Model AI</th>
                                <th class="py-2.5 px-3">Endpoint</th>
                                <th class="py-2.5 px-3">Mã HTTP</th>
                                <th class="py-2.5 px-3 text-right">Độ Trễ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60 font-mono">
                            <template x-for="log in (ketQua?.logs || [])" :key="log.id">
                                <tr class="hover:bg-gray-800/30 transition">
                                    <td class="py-2.5 px-3 text-gray-400" x-text="log.created_at"></td>
                                    <td class="py-2.5 px-3 text-cyan-300 font-bold" x-text="log.model || 'claude-3-5-sonnet'"></td>
                                    <td class="py-2.5 px-3 text-gray-300 truncate max-w-[150px]" x-text="log.endpoint"></td>
                                    <td class="py-2.5 px-3">
                                        <span :class="log.http_status === 200 ? 'text-green-400 font-bold' : 'text-red-400 font-bold'" x-text="log.http_status"></span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-400" x-text="(log.latency_ms || 120) + ' ms'"></td>
                                </tr>
                            </template>
                            <tr x-show="!ketQua || !ketQua.logs || ketQua.logs.length === 0">
                                <td colspan="5" class="py-8 text-center text-gray-500 font-sans space-y-1">
                                    <i class="fas fa-inbox text-2xl text-gray-600 block mb-1"></i>
                                    <div>Chưa có lượt gọi API nào qua Gateway gần đây.</div>
                                    <div class="text-xs text-gray-600">Hãy cấu hình Base URL dưới đây để bắt đầu trải nghiệm!</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- DANH SÁCH MODEL HỖ TRỢ & HƯỚNG DẪN KẾT NỐI BASE URL -->
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- DANH SÁCH MODEL HỖ TRỢ -->
            <div class="bg-[#111827] border border-gray-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-3">
                <div class="text-xs font-bold text-gray-300 flex items-center gap-1.5">
                    <i class="fas fa-brain text-purple-400"></i> Các Model AI Được Hỗ Trợ:
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="model in (ketQua?.models || ['claude-3-5-sonnet', 'deepseek-reasoner', 'deepseek-chat', 'gpt-4o', 'cursor-small'])" :key="model">
                        <span class="px-2.5 py-1 rounded-xl bg-gray-800/80 border border-gray-700 text-xs font-mono text-cyan-300 font-semibold" x-text="model"></span>
                    </template>
                </div>
            </div>

            <!-- HƯỚNG DẪN KẾT NỐI BASE URL & CHAT ENDPOINT -->
            <div class="bg-[#111827] border border-blue-900/50 rounded-3xl p-5 sm:p-7 shadow-xl space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                    <i class="fas fa-plug text-blue-400"></i> Cấu Hình Base URL Kết Nối (OpenAI Compatible)
                </h3>

                <p class="text-xs text-gray-300 leading-relaxed">
                    Sử dụng Base URL chính thức của shop để kết nối vào Cursor IDE, Cline, Roo Code, VS Code và các AI Agent khác:
                </p>

                <div class="space-y-3">
                    <!-- Base URL -->
                    <div class="bg-[#0B0F19] p-3.5 rounded-2xl border border-gray-800 space-y-1.5 overflow-hidden">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400 font-semibold">1. Override OpenAI Base URL:</span>
                            <button type="button" @click="saoChep(ketQua?.base_url || '<?= $baseUrlWebsite ?>')" 
                                    class="text-xs text-blue-400 hover:text-white px-2.5 py-1 rounded-lg bg-gray-800 transition flex items-center gap-1">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <code class="block font-mono text-xs text-amber-300 break-all select-all font-bold" x-text="ketQua?.base_url || '<?= $baseUrlWebsite ?>'"></code>
                    </div>

                    <!-- Chat Endpoint -->
                    <div class="bg-[#0B0F19] p-3.5 rounded-2xl border border-gray-800 space-y-1.5 overflow-hidden">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400 font-semibold">2. Chat Completions Endpoint:</span>
                            <button type="button" @click="saoChep(ketQua?.chat_endpoint || '<?= $chatEndpoint ?>')" 
                                    class="text-xs text-blue-400 hover:text-white px-2.5 py-1 rounded-lg bg-gray-800 transition flex items-center gap-1">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <code class="block font-mono text-xs text-emerald-400 break-all select-all font-bold" x-text="ketQua?.chat_endpoint || '<?= $chatEndpoint ?>'"></code>
                    </div>
                </div>

                <!-- Code mẫu Cursor -->
                <div class="bg-[#0B0F19] p-3.5 rounded-2xl border border-gray-800 space-y-2 text-xs">
                    <div class="font-bold text-gray-300 flex items-center gap-1.5">
                        <i class="fas fa-laptop-code text-blue-400"></i> Cấu hình nhanh trong Cursor Settings (Ctrl + Shift + J › Models):
                    </div>
                    <ol class="list-decimal list-inside space-y-1 text-gray-400 font-mono text-[11px]">
                        <li>Override OpenAI Base URL: <span class="text-amber-300 font-bold"><?= $baseUrlWebsite ?></span></li>
                        <li>OpenAI API Key: <span class="text-blue-300 font-bold" x-text="oNhapKey || 'dán key của bạn vào đây'"></span></li>
                        <li>Bật các Model: <span class="text-green-300 font-bold">claude-3-5-sonnet, deepseek-reasoner, cursor-small</span></li>
                    </ol>
                </div>
            </div>

            <!-- CẦN MUA THÊM HOẶC GIA HẠN? -->
            <div class="bg-gradient-to-r from-blue-900/30 to-indigo-900/30 border border-blue-800/40 rounded-3xl p-5 text-center space-y-3">
                <div class="text-sm font-bold text-white">Bạn cần mua thêm Request hoặc gia hạn gói mới?</div>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <a href="/san-pham" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-2">
                        <i class="fas fa-cart-shopping"></i> Mua Thêm Key Mới
                    </a>
                    <a href="https://zalo.me/<?= htmlspecialchars($soZaloTraCuu) ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 font-bold text-xs transition flex items-center gap-2">
                        <i class="fas fa-headset text-blue-400"></i> Hỗ Trợ Zalo: <?= htmlspecialchars($soZaloTraCuu) ?>
                    </a>
                </div>
            </div>

        </div>

    </main>

    <!-- FOOTER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungTraCuuKey(initialKey) {
        return {
            oNhapKey: initialKey || '',
            tabHienTai: 1, // 1: Usage, 2: Daily, 3: Overage, 4: History
            dangTai: false,
            ketQua: null,
            loi: '',

            init() {
                if (this.oNhapKey && this.oNhapKey.trim().length >= 2) {
                    this.traCuuNgay();
                }
            },

            async traCuuNgay() {
                let key = this.oNhapKey.trim();
                if (!key) {
                    this.loi = 'Vui lòng nhập API Key hoặc Mã đơn hàng (#VK...) cần tra cứu!';
                    return;
                }
                this.dangTai = true;
                this.loi = '';

                try {
                    let res = await fetch('/backend/api.php?action=tra_cuu_key&key=' + encodeURIComponent(key));
                    let data = await res.json();
                    if (data.thanh_cong) {
                        this.ketQua = data;
                    } else {
                        this.ketQua = null;
                        this.loi = data.thong_bao || 'Không tìm thấy thông tin của mã Key này trong hệ thống!';
                    }
                } catch (e) {
                    this.loi = 'Lỗi kết nối tới máy chủ. Vui lòng thử lại sau giây lát!';
                } finally {
                    this.dangTai = false;
                }
            },

            saoChep(text) {
                if (!text) return;
                navigator.clipboard.writeText(text);
                alert('Đã sao chép vào bộ nhớ tạm:\n' + text);
            }
        }
    }
    </script>
</body>
</html>
