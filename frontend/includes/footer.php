<?php
// ========================================================
// FOOTER DÙNG CHUNG CHO TOÀN BỘ WEBSITE TIỆM AI
// tiemai.io.vn - Thiết kế hiện đại 5 cột chuẩn theo yêu cầu
// ========================================================

$tenWebsiteFooter = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_name', defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn') : (defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn');
$soZaloFooter = '0904045075';
$teleFooter = 'https://t.me/vankhateam';
$tiktokFooter = 'https://www.tiktok.com/@vankhateam';
$emailFooter = 'vankhaqc@gmail.com';

$daDangNhapFooter = !empty($_SESSION['user_id']);
$curUri = $_SERVER['REQUEST_URI'] ?? '/';

// 1. Danh sách sản phẩm API - Click mở trực tiếp trang sản phẩm và lọc đúng API đó
$danhSachSanPhamFooter = [
    ['ten' => 'API Cursor',    'link' => '/san-pham/cursor'],
    ['ten' => 'API Gemini',    'link' => '/san-pham/gemini'],
    ['ten' => 'API GLM',       'link' => '/san-pham/glm'],
    ['ten' => 'API Kimi',      'link' => '/san-pham/kimi'],
    ['ten' => 'API Grok',      'link' => '/san-pham/grok'],
    ['ten' => 'API DeepSeek',  'link' => '/san-pham/deepseek'],
    ['ten' => 'API Codex',     'link' => '/san-pham/codex'],
    ['ten' => 'API Claude',    'link' => '/san-pham/claude'],
];
?>
<footer class="border-t border-gray-800/80 bg-[#0B0F19] pt-12 pb-28 lg:pb-12 text-xs text-gray-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Bố cục 5 cột chuẩn xác theo thiết kế -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8 lg:gap-6 text-left">
            
            <!-- Cột 1: Thương hiệu & Giới thiệu -->
            <div class="space-y-3 sm:col-span-2 md:col-span-3 lg:col-span-1">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20 flex-shrink-0">
                        <i class="fas fa-brain text-lg"></i>
                    </div>
                    <div>
                        <div class="text-base font-black text-white tracking-wide">Tiệm AI</div>
                        <div class="text-[9px] uppercase tracking-wider text-blue-400 font-extrabold">AI API & PHÁT TRIỂN PHẦN MỀM</div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed pt-1">
                    Nền tảng cung cấp AI API & phần mềm tại Việt Nam.
                </p>
            </div>

            <!-- Cột 2: SẢN PHẨM -->
            <div>
                <h3 class="text-xs font-black tracking-wider text-white uppercase mb-4">SẢN PHẨM</h3>
                <ul class="space-y-2.5 text-xs">
                    <?php foreach ($danhSachSanPhamFooter as $sp): ?>
                        <li>
                            <a href="<?= htmlspecialchars($sp['link']) ?>" class="text-gray-400 hover:text-white transition block truncate" title="<?= htmlspecialchars($sp['ten']) ?>">
                                <?= htmlspecialchars($sp['ten']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Cột 3: TÀI NGUYÊN -->
            <div>
                <h3 class="text-xs font-black tracking-wider text-white uppercase mb-4">TÀI NGUYÊN</h3>
                <ul class="space-y-2.5 text-xs">
                    <li><a href="/docs" class="text-gray-400 hover:text-white transition">API Docs</a></li>
                    <li><a href="/bang-gia-model" class="text-gray-400 hover:text-white transition">Bảng giá Model</a></li>
                    <li><a href="/docs" class="text-gray-400 hover:text-white transition">Blog</a></li>
                    <li><a href="/xep-hang" class="text-gray-400 hover:text-white transition">Bảng Xếp Hạng</a></li>
                    <li><a href="/phan-mem" class="text-gray-400 hover:text-white transition">Phần mềm</a></li>
                </ul>
            </div>

            <!-- Cột 4: LIÊN HỆ -->
            <div>
                <h3 class="text-xs font-black tracking-wider text-white uppercase mb-4">LIÊN HỆ</h3>
                <ul class="space-y-2.5 text-xs">
                    <li>
                        <a href="mailto:<?= htmlspecialchars($emailFooter) ?>" class="text-gray-400 hover:text-white transition flex items-center gap-2">
                            <i class="far fa-envelope text-gray-400 w-4 text-center"></i>
                            <span class="truncate"><?= htmlspecialchars($emailFooter) ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= htmlspecialchars($teleFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition flex items-center gap-2">
                            <i class="fab fa-telegram text-gray-400 w-4 text-center"></i>
                            <span>vankhateam</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= htmlspecialchars($tiktokFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition flex items-center gap-2">
                            <i class="fab fa-tiktok text-gray-400 w-4 text-center"></i>
                            <span>TikTok</span>
                        </a>
                    </li>

                    <li>
                        <a href="https://zalo.me/<?= htmlspecialchars($soZaloFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition flex items-center gap-2">
                            <i class="fas fa-comment-dots text-gray-400 w-4 text-center"></i>
                            <span>Zalo: <?= htmlspecialchars($soZaloFooter) ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Cột 5: PHÁP LÝ -->
            <div>
                <h3 class="text-xs font-black tracking-wider text-white uppercase mb-4">PHÁP LÝ</h3>
                <ul class="space-y-2.5 text-xs">
                    <li><a href="/dieu-khoan-su-dung" class="text-gray-400 hover:text-white transition">Điều khoản sử dụng</a></li>
                    <li><a href="/quy-che-hoat-dong" class="text-gray-400 hover:text-white transition">Quy chế hoạt động</a></li>
                    <li><a href="/chinh-sach-thanh-toan" class="text-gray-400 hover:text-white transition">Chính sách thanh toán</a></li>
                    <li><a href="/chinh-sach-cung-cap-san-pham" class="text-gray-400 hover:text-white transition">Chính sách cung cấp sản phẩm</a></li>
                    <li><a href="/bao-hanh-hoan-tien" class="text-gray-400 hover:text-white transition">Bảo hành & hoàn tiền</a></li>
                    <li><a href="/dieu-khoan-dai-ly" class="text-gray-400 hover:text-white transition">Điều khoản đại lý</a></li>
                    <li><a href="/chinh-sach-bao-mat" class="text-gray-400 hover:text-white transition">Chính sách bảo mật</a></li>
                </ul>
            </div>

        </div>

        <!-- Dòng bản quyền chân trang (Giữ nguyên theo đúng yêu cầu) -->
        <div class="mt-12 pt-6 border-t border-gray-800/80 flex flex-col md:flex-row items-center justify-between gap-4 text-gray-400 text-[11px]">
            <p class="text-center md:text-left leading-relaxed">
                © 2026 Tiemai.io.vn - Cung Cấp Api Ai Agent - Hệ Thống Cung Cấp API AI & Phần Mềm Bản Quyền 24/7. All rights reserved.
            </p>
            <div class="flex items-center gap-4 text-xs font-medium">
                <a href="/chinh-sach-bao-mat" class="text-gray-400 hover:text-white transition">Chính sách</a>
                <span class="text-gray-700">|</span>
                <a href="/dieu-khoan-su-dung" class="text-gray-400 hover:text-white transition">Điều khoản</a>
            </div>
        </div>
    </div>
</footer>

<!-- THANH ĐIỀU HƯỚNG DƯỚI ĐÁY DÀNH CHO MOBILE (APP-LIKE BOTTOM BAR) -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#0B0F19]/95 backdrop-blur-xl border-t border-gray-800/90 px-2 py-1.5 shadow-[0_-5px_25px_rgba(0,0,0,0.6)]">
    <div class="grid grid-cols-5 text-center">
        <!-- 1. Trang Chủ -->
        <a href="/" class="flex flex-col items-center py-1 <?= ($curUri === '/' || $curUri === '') ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?> transition">
            <i class="fas fa-home text-base mb-0.5"></i>
            <span class="text-[10px] tracking-tight">Trang Chủ</span>
        </a>

        <!-- 2. Sản Phẩm API -->
        <a href="/san-pham" class="flex flex-col items-center py-1 <?= str_starts_with($curUri, '/san-pham') ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white' ?> transition">
            <i class="fas fa-cubes text-base mb-0.5"></i>
            <span class="text-[10px] tracking-tight">Sản Phẩm</span>
        </a>

        <!-- 3. Phần Mềm -->
        <a href="/phan-mem" class="flex flex-col items-center py-1 <?= str_starts_with($curUri, '/phan-mem') ? 'text-emerald-400 font-bold' : 'text-gray-400 hover:text-white' ?> transition">
            <i class="fas fa-laptop-code text-base mb-0.5"></i>
            <span class="text-[10px] tracking-tight">Phần Mềm</span>
        </a>

        <!-- 4. Nạp Tiền -->
        <a href="<?= $daDangNhapFooter ? '/user/nap-tien' : '/login' ?>" class="flex flex-col items-center py-1 <?= str_starts_with($curUri, '/user/nap-tien') ? 'text-green-400 font-bold' : 'text-gray-400 hover:text-white' ?> transition">
            <i class="fas fa-wallet text-base mb-0.5"></i>
            <span class="text-[10px] tracking-tight">Nạp Tiền</span>
        </a>

        <!-- 5. Tài Khoản / Đăng Nhập -->
        <a href="<?= $daDangNhapFooter ? '/user/dashboard' : '/login' ?>" class="flex flex-col items-center py-1 <?= str_starts_with($curUri, '/user/') ? 'text-purple-400 font-bold' : 'text-gray-400 hover:text-white' ?> transition">
            <i class="fas fa-circle-user text-base mb-0.5"></i>
            <span class="text-[10px] tracking-tight"><?= $daDangNhapFooter ? 'Cá Nhân' : 'Đăng Nhập' ?></span>
        </a>
    </div>
</nav>
