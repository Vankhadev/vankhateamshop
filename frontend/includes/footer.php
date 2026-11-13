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
?>
<footer class="border-t border-gray-800 bg-[#0F172A] py-8 text-center text-xs text-gray-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
        <div class="flex flex-wrap items-center justify-center gap-4 text-xs">
            <a href="https://zalo.me/<?= htmlspecialchars($soZaloFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:text-blue-300 transition flex items-center gap-1.5 font-semibold">
                <i class="fas fa-comment-dots"></i> Hotline Zalo: <?= htmlspecialchars($soZaloFooter) ?>
            </a>
            <span class="text-gray-700">|</span>
            <a href="<?= htmlspecialchars($teleFooter) ?>" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:text-sky-300 transition flex items-center gap-1.5 font-semibold">
                <i class="fab fa-telegram"></i> Hỗ Trợ Telegram
            </a>
            <span class="text-gray-700">|</span>
            <a href="/chinh-sach" class="text-gray-400 hover:text-white transition">Chính Sách Bảo Hành</a>
            <span class="text-gray-700">|</span>
            <a href="/docs" class="text-gray-400 hover:text-white transition">Buyer API Docs</a>
        </div>
        <p class="font-medium text-gray-400 text-[11px]">
            &copy; 2026 <span class="text-white font-bold"><?= htmlspecialchars($tenWebsiteFooter) ?></span> - Hệ Thống Cung Cấp API AI & Phần Mềm Bản Quyền 24/7. All rights reserved.
        </p>
    </div>
</footer>
