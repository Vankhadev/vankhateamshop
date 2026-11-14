<?php
// ========================================================
// FOOTER DÙNG CHUNG CHO TOÀN BỘ WEBSITE
// vankhateamshop - Đồng bộ chân trang trên tất cả các trang
// Tự động nhận diện tên website & Zalo từ cài đặt hệ thống / .env
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

$tenWebsiteFooter = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_name', defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn') : (defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn');
$soZaloFooter = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_zalo', defined('SHOP_ZALO') ? SHOP_ZALO : '0904045075') : (defined('SHOP_ZALO') ? SHOP_ZALO : '0904045075');
$teleFooter = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_telegram', defined('SHOP_TELEGRAM') ? SHOP_TELEGRAM : 'https://t.me/vankhashop') : (defined('SHOP_TELEGRAM') ? SHOP_TELEGRAM : 'https://t.me/vankhashop');

$daDangNhapFooter = !empty($_SESSION['user_id']);
$curUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<footer class="border-t border-gray-800 bg-[#0F172A] py-8 pb-24 lg:pb-8 text-center text-xs text-gray-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 text-left">
            <div>
                <h2 class="text-sm font-black tracking-wider text-white uppercase mb-3">Hỗ trợ</h2>
                <div class="space-y-2 text-xs">
                    <a href="https://zalo.me/<?= htmlspecialchars($soZaloFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:text-blue-300 transition flex items-center gap-1.5 font-semibold">
                        <i class="fas fa-comment-dots w-4"></i> Hotline Zalo: <?= htmlspecialchars($soZaloFooter) ?>
                    </a>
                    <a href="<?= htmlspecialchars($teleFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:text-sky-300 transition flex items-center gap-1.5 font-semibold">
                        <i class="fab fa-telegram w-4"></i> Hỗ Trợ Telegram
                    </a>
                    <a href="/docs" class="text-gray-400 hover:text-white transition flex items-center gap-1.5"><i class="fas fa-book w-4"></i> Buyer API Docs</a>
                </div>
            </div>
            <div>
                <h2 class="text-sm font-black tracking-wider text-white uppercase mb-3">Pháp lý</h2>
                <div class="grid grid-cols-1 gap-2 text-xs">
                    <a href="/dieu-khoan-su-dung" class="text-gray-400 hover:text-white transition">Điều khoản sử dụng</a>
                    <a href="/quy-che-hoat-dong" class="text-gray-400 hover:text-white transition">Quy chế hoạt động</a>
                    <a href="/chinh-sach-thanh-toan" class="text-gray-400 hover:text-white transition">Chính sách thanh toán</a>
                    <a href="/chinh-sach-cung-cap-san-pham" class="text-gray-400 hover:text-white transition">Chính sách cung cấp sản phẩm</a>
                    <a href="/bao-hanh-hoan-tien" class="text-gray-400 hover:text-white transition">Bảo hành & hoàn tiền</a>
                    <a href="/dieu-khoan-dai-ly" class="text-gray-400 hover:text-white transition">Điều khoản đại lý</a>
                    <a href="/chinh-sach-bao-mat" class="text-gray-400 hover:text-white transition">Chính sách bảo mật</a>
                </div>
            </div>
            <div class="lg:text-right">
                <h2 class="text-sm font-black tracking-wider text-white uppercase mb-3">Thông tin</h2>
                <p class="text-xs leading-relaxed text-gray-400">Hệ thống cung cấp API AI và phần mềm bản quyền 24/7. Vui lòng đọc kỹ chính sách trước khi sử dụng dịch vụ.</p>
            </div>
        </div>
        <p class="font-medium text-gray-400 text-[11px] text-center mt-8 pt-4 border-t border-gray-800">
            &copy; 2026 <span class="text-white font-bold"><?= htmlspecialchars($tenWebsiteFooter) ?></span> - All rights reserved.
        </p>
    </div>
</footer>

<!-- THANH ĐIỀU HƯỚNG DƯỚI ĐÁY DÀNH CHO MOBILE (APP-LIKE BOTTOM BAR) -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#0F172A]/95 backdrop-blur-xl border-t border-gray-800/90 px-2 py-1.5 shadow-[0_-5px_25px_rgba(0,0,0,0.6)]">
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
