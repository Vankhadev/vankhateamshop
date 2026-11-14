<?php
// ========================================================
// TRANG DÀNH RIÊNG: TRA CỨU KEY & KIỂM TRA SỐ LƯỢT DÙNG (USAGE)
// vankhateamshop - Tra cứu thời gian thực hạn dùng, request còn lại
// Hỗ trợ Base URL đồng bộ: https://tiemai.io.vn/v1
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

$soZaloTraCuu = getSetting('zalo_cskh', '0904045071');
$tenShop = SHOP_NAME;

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
    </style>
</head>
<body class="bg-[#0B0F19] text-gray-100 min-h-screen flex flex-col font-sans antialiased overflow-x-hidden" x-data="ungDungTraCuuKey('<?= addslashes($keyTuUrl) ?>')">

    <!-- HEADER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 w-full flex-1 space-y-8">

        <!-- TIÊU ĐỀ & BANNER TRA CỨU -->
        <div class="text-center space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-chart-line"></i> Kiểm Tra Trạng Thái
            </div>
            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Tra Cứu Key API & Hạn Sử Dụng
            </h1>
            <p class="text-xs sm:text-sm text-gray-400 max-w-2xl mx-auto leading-relaxed">
                Nhập mã Key API hoặc mã đơn hàng để xem số lượt request đã sử dụng, thời hạn còn lại và hướng dẫn kết nối Base URL chuẩn OpenAI.
            </p>
        </div>

        <!-- Ô NHẬP TÌM KIẾM KEY -->
        <div class="bg-[#111827] border border-blue-900/40 rounded-3xl p-4 sm:p-6 shadow-2xl space-y-4 max-w-3xl mx-auto">
            <form @submit.prevent="traCuuNgay()" class="space-y-3">
                <label class="block text-xs font-bold text-gray-300">
                    <i class="fas fa-key text-blue-400 mr-1.5"></i> Nhập Mã Key API / Mã Đơn Hàng / Email Mua Hàng:
                </label>
                <div class="flex flex-col sm:flex-row gap-2.5">
                    <div class="relative flex-1">
                        <input type="text" x-model="oNhapKey" placeholder="Ví dụ: sk-..., vk_... hoặc mã đơn VK..." 
                               class="w-full bg-[#0B0F19] border border-gray-700 focus:border-blue-500 rounded-2xl px-4 py-3 text-sm font-mono text-white placeholder-gray-500 focus:outline-none transition">
                        <button type="button" x-show="oNhapKey" @click="oNhapKey = ''; ketQua = null; loi = ''" 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                    <button type="submit" :disabled="dangTai" 
                            class="px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-lg shadow-blue-600/30 whitespace-nowrap">
                        <i class="fas fa-circle-notch fa-spin" x-show="dangTai" style="display: none;"></i>
                        <i class="fas fa-magnifying-glass" x-show="!dangTai"></i>
                        <span>Tra Cứu Ngay</span>
                    </button>
                </div>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-gray-800 text-[11px] text-gray-400">
                <span class="flex items-center gap-1.5">
                    <i class="fas fa-shield-check text-green-400"></i> Tra cứu bảo mật & an toàn 100%
                </span>
                <span class="text-gray-500">Hỗ trợ các key: Cursor AI, Claude, OpenAI, DeepSeek</span>
            </div>
        </div>

        <!-- THÔNG BÁO LỖI NẾU CÓ -->
        <div x-show="loi" class="max-w-3xl mx-auto bg-red-950/40 border border-red-800/60 rounded-2xl p-4 text-xs text-red-300 flex items-start gap-3" style="display: none;" x-transition>
            <i class="fas fa-circle-exclamation text-base text-red-400 shrink-0 mt-0.5"></i>
            <div>
                <div class="font-bold text-red-200">Không tìm thấy dữ liệu</div>
                <div x-text="loi" class="mt-0.5"></div>
            </div>
        </div>

        <!-- KẾT QUẢ TRA CỨU THÀNH CÔNG -->
        <div x-show="ketQua" class="max-w-3xl mx-auto space-y-6" style="display: none;" x-transition>

            <!-- THẺ THÔNG TIN TỔNG QUAN -->
            <div class="bg-[#111827] border border-gray-800 rounded-3xl p-5 sm:p-7 shadow-xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-800 pb-4">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-gray-400 font-semibold">Tên Gói Sản Phẩm</div>
                        <h2 class="text-lg sm:text-xl font-black text-white mt-0.5" x-text="ketQua?.ten_goi"></h2>
                        <div class="text-xs text-gray-400 font-mono mt-0.5">
                            Mã Key: <span class="text-blue-300 font-bold" x-text="ketQua?.key_masked"></span>
                        </div>
                    </div>
                    <div class="self-start sm:self-auto">
                        <span :class="ketQua?.is_active ? 'bg-green-500/20 text-green-400 border-green-500/30' : 'bg-red-500/20 text-red-400 border-red-500/30'" 
                              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-bold">
                            <span class="w-2 h-2 rounded-full" :class="ketQua?.is_active ? 'bg-green-400 animate-pulse' : 'bg-red-400'"></span>
                            <span x-text="ketQua?.trang_thai"></span>
                        </span>
                    </div>
                </div>

                <!-- THANH TIẾN ĐỘ TIÊU DÙNG REQUEST -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-300 font-semibold flex items-center gap-1.5">
                            <i class="fas fa-bolt text-amber-400"></i> Tiến độ sử dụng Request:
                        </span>
                        <span class="text-amber-400 font-mono font-bold" x-text="ketQua?.da_dung"></span>
                    </div>
                    <div class="w-full h-3 bg-gray-950 rounded-full overflow-hidden border border-gray-800 p-0.5">
                        <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-amber-500 transition-all duration-500" 
                             :style="`width: ${ketQua?.phan_tram || 0}%`"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-gray-400 pt-0.5">
                        <span>Đã sử dụng: <strong class="text-white" x-text="`${ketQua?.phan_tram || 0}%`"></strong></span>
                        <span>Còn lại: <strong class="text-emerald-400 font-bold" x-text="ketQua?.con_lai"></strong></span>
                    </div>
                </div>

                <!-- LƯỚI THÔNG SỐ CHI TIẾT -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-[#0B0F19] p-3.5 rounded-2xl border border-gray-800 space-y-1">
                        <div class="text-gray-400 flex items-center gap-1.5 text-[11px]">
                            <i class="fas fa-chart-pie text-blue-400"></i> Request Còn Lại
                        </div>
                        <div class="text-base font-black text-emerald-400" x-text="ketQua?.con_lai"></div>
                    </div>

                    <div class="bg-[#0B0F19] p-3.5 rounded-2xl border border-gray-800 space-y-1">
                        <div class="text-gray-400 flex items-center gap-1.5 text-[11px]">
                            <i class="fas fa-hourglass-half text-amber-400"></i> Thời Hạn Còn Lại
                        </div>
                        <div class="text-base font-black text-amber-300" x-text="ketQua?.han_dung"></div>
                    </div>

                    <div class="bg-[#0B0F19] p-3.5 rounded-2xl border border-gray-800 space-y-1">
                        <div class="text-gray-400 flex items-center gap-1.5 text-[11px]">
                            <i class="fas fa-calendar-day text-purple-400"></i> Ngày Hết Hạn
                        </div>
                        <div class="text-xs font-bold text-gray-200" x-text="ketQua?.ngay_het_han"></div>
                    </div>
                </div>

                <!-- DANH SÁCH MODEL HỖ TRỢ -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-gray-300 flex items-center gap-1.5">
                        <i class="fas fa-brain text-purple-400"></i> Các Model AI Được Hỗ Trợ:
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="model in (ketQua?.models || [])" :key="model">
                            <span class="px-2.5 py-1 rounded-xl bg-gray-800/80 border border-gray-700 text-xs font-mono text-cyan-300 font-semibold" x-text="model"></span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- HƯỚNG DẪN KẾT NỐI BASE URL & CHAT ENDPOINT -->
            <div class="bg-[#111827] border border-blue-900/50 rounded-3xl p-5 sm:p-7 shadow-xl space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                    <i class="fas fa-plug text-blue-400"></i> Cấu Hình Base URL Kết Nối (OpenAI Compatible)
                </h3>

                <p class="text-xs text-gray-300 leading-relaxed">
                    Sử dụng Base URL chính thức của shop để kết nối vào Cursor IDE, Cline, NextChat, Python hoặc cURL:
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
                        <li>OpenAI API Key: <span class="text-blue-300 font-bold" x-text="oNhapKey"></span></li>
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
            dangTai: false,
            ketQua: null,
            loi: '',

            init() {
                if (this.oNhapKey && this.oNhapKey.trim().length >= 3) {
                    this.traCuuNgay();
                }
            },

            async traCuuNgay() {
                let key = this.oNhapKey.trim();
                if (!key) {
                    this.loi = 'Vui lòng nhập Key hoặc Mã đơn hàng cần tra cứu!';
                    return;
                }
                this.dangTai = true;
                this.loi = '';
                this.ketQua = null;

                try {
                    let res = await fetch('/backend/api.php?action=tra_cuu_key&key=' + encodeURIComponent(key));
                    let data = await res.json();
                    if (data.thanh_cong) {
                        this.ketQua = data;
                    } else {
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
