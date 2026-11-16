/**
 * Vankha Team - Branding & DevTools Console Logger
 * Tự động in thông tin giới thiệu khi mở F12 Console & đảm bảo Favicon chuẩn Logo
 */
(function() {
    'use strict';

    if (window._vankhaBrandLoaded) return;
    window._vankhaBrandLoaded = true;

    // 1. Hàm in thông tin bản quyền Vankha Team vào F12 Console
    function inThongTinConsole() {
        console.log(
            '%ccode bởi Vankha Team',
            'color: #0088ff; font-size: 16px; font-weight: 800; font-family: monospace, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2px 0;'
        );
        console.log(
            '%ctiktok @Vankhadev',
            'color: #facc15; font-size: 14px; font-weight: 700; font-family: monospace, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2px 0;'
        );
        console.log(
            '%ctelegram @ Vankhadev',
            'color: #ef4444; font-size: 14px; font-weight: 700; font-family: monospace, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2px 0;'
        );
        console.log(
            '%cweb smm panel shopvankha.com',
            'color: #f97316; font-size: 14px; font-weight: 700; font-family: monospace, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2px 0;'
        );
    }

    // In ngay lập tức khi tải trang (dành cho người dùng đã mở sẵn DevTools F12)
    inThongTinConsole();

    // Lắng nghe sự kiện phím F12 hoặc các tổ hợp phím tắt mở DevTools
    window.addEventListener('keydown', function(e) {
        // Phím F12 (key === 'F12' hoặc keyCode 123)
        // Hoặc Ctrl + Shift + I/J/C / Cmd + Option + I/J/C
        if (
            e.key === 'F12' || 
            e.keyCode === 123 || 
            ((e.ctrlKey || e.metaKey) && e.shiftKey && ['I', 'i', 'J', 'j', 'C', 'c'].includes(e.key))
        ) {
            setTimeout(inThongTinConsole, 150);
        }
    });

    // Phát hiện DevTools mở ra bằng cách theo dõi resize của window
    var daInKhiResize = false;
    window.addEventListener('resize', function() {
        var threshold = 160;
        var rongKhacBiet = window.outerWidth - window.innerWidth > threshold;
        var caoKhacBiet = window.outerHeight - window.innerHeight > threshold;
        if ((rongKhacBiet || caoKhacBiet) && !daInKhiResize) {
            daInKhiResize = true;
            inThongTinConsole();
        } else if (!rongKhacBiet && !caoKhacBiet) {
            daInKhiResize = false;
        }
    });

    // 2. Tự động kiểm tra và đảm bảo Favicon chuẩn Logo Web trên thẻ <head>
    function caiDatFavicon() {
        try {
            var logoUrl = '/assets/images/logo.png';
            var icoUrl = '/favicon.ico';
            var head = document.head || document.getElementsByTagName('head')[0];
            if (!head) return;

            // Kiểm tra link icon
            var faviconLink = document.querySelector("link[rel*='icon']");
            if (!faviconLink) {
                faviconLink = document.createElement('link');
                faviconLink.rel = 'icon';
                faviconLink.type = 'image/png';
                faviconLink.href = logoUrl;
                head.appendChild(faviconLink);
            } else {
                faviconLink.href = logoUrl;
            }

            // Kiểm tra shortcut icon
            var shortcutLink = document.querySelector("link[rel='shortcut icon']");
            if (!shortcutLink) {
                shortcutLink = document.createElement('link');
                shortcutLink.rel = 'shortcut icon';
                shortcutLink.href = icoUrl;
                head.appendChild(shortcutLink);
            }

            // Kiểm tra apple-touch-icon
            var appleLink = document.querySelector("link[rel='apple-touch-icon']");
            if (!appleLink) {
                appleLink = document.createElement('link');
                appleLink.rel = 'apple-touch-icon';
                appleLink.href = logoUrl;
                head.appendChild(appleLink);
            }
        } catch (err) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', caiDatFavicon);
    } else {
        caiDatFavicon();
    }
})();
