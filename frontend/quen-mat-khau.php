<?php
// ========================================================
// TRANG QUÊN MẬT KHẨU / KHÔI PHỤC TÀI KHOẢN
// vankhateamshop - Khách yêu cầu cấp lại mật khẩu
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <!-- Favicon Logo Website Tiem AI -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi Phục Mật Khẩu - <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php if (CAPTCHA_PROVIDER === 'turnstile'): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php else: ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungQuenMatKhau()" class="min-h-screen flex flex-col antialiased selection:bg-blue-600 selection:text-white">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center space-x-3 group">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white font-bold text-2xl shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-transform duration-200">
                    VK
                </div>
                <div class="text-left">
                    <span class="text-2xl font-black tracking-tight text-white block">VanKha<span class="text-blue-500">Shop</span></span>
                    <span class="text-xs text-gray-400 font-semibold tracking-wider uppercase block">Hệ Thống API Tự Động</span>
                </div>
            </a>
            <h1 class="text-xl font-bold text-white mt-6">Khôi Phục Mật Khẩu</h1>
            <p class="text-sm text-gray-400 mt-1">Nhập địa chỉ email đăng ký để nhận hướng dẫn cấp lại mật khẩu</p>
        </div>

        <!-- Khung Form -->
        <div class="bg-[#111827] border border-gray-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl">

            <div x-show="thongBaoLoi" class="p-3 mb-5 rounded-xl bg-red-950/60 border border-red-800 text-red-300 text-sm font-medium flex items-center space-x-2" style="display: none;">
                <i class="fas fa-circle-exclamation flex-shrink-0"></i>
                <span x-text="thongBaoLoi"></span>
            </div>

            <div x-show="thongBaoThanhCong" class="p-3 mb-5 rounded-xl bg-green-950/60 border border-green-800 text-green-300 text-sm font-medium flex items-center space-x-2" style="display: none;">
                <i class="fas fa-circle-check flex-shrink-0"></i>
                <span x-text="thongBaoThanhCong"></span>
            </div>

            <form @submit.prevent="guiYeuCau()" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Địa Chỉ Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <input type="email" x-model="email" required placeholder="nhapemail@gmail.com" class="w-full bg-[#1E293B]/70 border border-gray-700/80 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition duration-150">
                    </div>
                </div>

                <button type="submit" :disabled="dangXuLy" class="w-full py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm transition duration-200 shadow-lg shadow-blue-600/30 flex items-center justify-center space-x-2 disabled:opacity-50">
                    <span x-show="!dangXuLy"><i class="fas fa-paper-plane mr-1"></i> Gửi Yêu Cầu Khôi Phục</span>
                    <span x-show="dangXuLy"><i class="fas fa-circle-notch fa-spin mr-1"></i> Đang xử lý...</span>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-gray-800/80 text-center">
                <a href="/login" class="text-sm font-medium text-gray-400 hover:text-blue-400 transition duration-150 inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Quay lại Đăng Nhập
                </a>
            </div>
        </div>
    </div>
    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungQuenMatKhau() {
        return {
            email: '',
            dangXuLy: false,
            thongBaoLoi: '',
            thongBaoThanhCong: '',

            async guiYeuCau() {
                this.thongBaoLoi = '';
                this.thongBaoThanhCong = '';
                this.dangXuLy = true;

                const form = new FormData();
                form.append('email', this.email);

                try {
                    const res = await fetch('/backend/api.php?action=quen_mat_khau', {
                        method: 'POST',
                        body: form
                    });
                    const data = await res.json();
                    if (data.thanh_cong) {
                        this.thongBaoThanhCong = data.thong_bao;
                    } else {
                        this.thongBaoLoi = data.thong_bao;
                    }
                } catch (e) {
                    this.thongBaoLoi = 'Lỗi kết nối máy chủ, vui lòng thử lại sau!';
                } finally {
                    this.dangXuLy = false;
                }
            }
        }
    }
    </script>
</body>
</html>
