<?php
// ========================================================
// FOOTER DÙNG CHUNG CHO TOÀN BỘ WEBSITE
// vankhateamshop - Đồng bộ chân trang trên tất cả các trang
// Tự động nhận diện tên website từ cài đặt hệ thống / .env
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

$tenWebsiteFooter = defined('user_name') ? SHOP_NAME : 'VanKha Team Shop';
if (isset($pdo)) {
    try {
        $stmtSite = $pdo->query("SELECT `value` FROM `settings` WHERE `key` = 'site_name' LIMIT 1");
        $valSite = $stmtSite->fetchColumn();
        if (!empty($valSite)) {
            $tenWebsiteFooter = $valSite;
        }
    } catch (Exception $e) {
        // Fallback sang SHOP_NAME nếu chưa kết nối PDO
    }
}
?>
<footer class="border-t border-gray-800 bg-[#0F172A] py-8 text-center text-xs text-gray-400 mt-auto">
        <p class="font-medium text-gray-300">
            &copy; 2026 <span class="text-white font-bold"><?= htmlspecialchars($tenWebsiteFooter) ?></span>, đại lý cung cấp api key ai agent. All rights reserved. By VankhaTeam
        </p>
    </div>
</footer>
