<?php
// ========================================================
// FOOTER DÙNG CHUNG CHO TOÀN BỘ WEBSITE
// vankhateamshop - Đồng bộ chân trang trên tất cả các trang
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================
?>
<footer class="border-t border-gray-800 bg-[#0F172A] py-8 text-center text-xs text-gray-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
        <div class="flex flex-wrap justify-center gap-6 text-xs text-gray-400 font-medium">
            <a href="/" class="hover:text-white transition">Trang Chủ</a>
            <a href="/san-pham" class="hover:text-white transition">Sản Phẩm</a>
            <a href="/docs" class="hover:text-white transition">Tài Liệu API</a>
            <a href="/chinh-sach" class="hover:text-white transition">Chính Sách Bảo Hành</a>
            <a href="/user/nap-tien" class="hover:text-white transition">Nạp Tiền</a>
            <a href="/user/dashboard" class="hover:text-white transition">Khu Vực Khách Hàng</a>
        </div>
        <p class="font-medium text-gray-300">
            &copy; 2026 <span class="text-white font-bold"><?= defined('SHOP_NAME') ? SHOP_NAME : 'VanKha Team Shop' ?></span>, đại lý cung cấp api key ai agent. All rights reserved. By VankhaTeam
        </p>
    </div>
</footer>
