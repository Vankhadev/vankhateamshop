<?php
// ========================================================
// TRANG HỒ SƠ TÀI KHOẢN & BẢO MẬT (CLIENT PROFILE)
// vankhateamshop - Đổi mật khẩu, quản lý Personal API Token, xem liên kết Google
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ../../backend/logout.php');
    exit;
}
$csrfToken = taoCsrfToken();
$tokenDaCap = !empty($user['api_token']);
$twoFactorEnabled = !empty($user['two_factor_enabled']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ & Bảo Mật - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungTaiKhoan()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- THANH MENU TAB QUẢN TRỊ TÀI KHOẢN KHÁCH HÀNG -->
    <div class="bg-[#111827] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-6 text-xs font-semibold overflow-x-auto py-3">
                <a href="/user/dashboard" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-chart-pie mr-1"></i> Tổng Quan
                </a>
                <a href="/user/don-hang" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-box-open mr-1"></i> Đơn Hàng Của Tôi
                </a>
                <a href="/user/nap-tien" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-wallet mr-1 text-green-400"></i> Nạp Tiền Ví
                </a>
                <a href="/user/api-docs" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-code mr-1"></i> Kết Nối API & Cursor
                </a>
                <a href="/user/bien-dong-so-du" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-clock-rotate-left mr-1"></i> Biến Động Số Dư
                </a>
                <a href="/user/ho-tro" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-headset mr-1"></i> Hỗ Trợ
                </a>
                <a href="/user/tai-khoan" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
                    <i class="fas fa-user-gear mr-1"></i> Cài Đặt Tài Khoản
                </a>
            </nav>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 py-8 w-full flex-1 space-y-6">
        
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-user-shield text-blue-400"></i> Quản Lý Tài Khoản & Bảo Mật
            </h1>
            <p class="text-xs text-gray-400 mt-1">Cập nhật mật khẩu, quản lý Personal API Token kết nối công cụ lập trình</p>
        </div>

        <!-- THÔNG BÁO AJAX -->
        <div x-show="thongBao" class="p-3.5 rounded-2xl text-xs font-bold" 
             :class="loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300'"
             x-text="thongBao"></div>

        <!-- KHỐI 1: THÔNG TIN HỒ SƠ & LIÊN KẾT GOOGLE -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl flex flex-col sm:flex-row items-center gap-6">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="w-20 h-20 rounded-2xl object-cover border-2 border-blue-500/50 shadow-lg">
            <?php else: ?>
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-3xl font-black shadow-lg">
                    <?= mb_substr($user['name'] ?? 'K', 0, 1) ?>
                </div>
            <?php endif; ?>

            <div class="flex-1 text-center sm:text-left space-y-1">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($user['name']) ?></h2>
                    <?php if (!empty($user['google_id'])): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-950 text-blue-400 border border-blue-800 flex items-center gap-1">
                            <i class="fab fa-google text-xs"></i> Đã liên kết Google
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($user['email']) ?></p>
                <p class="text-[11px] text-gray-500">Tham gia hệ thống: <?= date('d/m/Y', strtotime($user['created_at'])) ?> | Vai trò: <span class="uppercase font-bold text-amber-400"><?= $user['role'] ?></span></p>
            </div>
        </div>

        <!-- KHỐI 2: PERSONAL API TOKEN DÀNH CHO LẬP TRÌNH VIÊN -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-key text-amber-400"></i> Personal API Token Của Bạn
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Dùng Key này để gọi API đại lý hoặc tích hợp vào phần mềm của riêng bạn</p>
                </div>
                <button @click="taoMoiApiToken()" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-amber-400 border border-gray-700 text-xs font-bold transition flex items-center gap-1">
                    <i class="fas fa-rotate"></i> Tạo Mới
                </button>
            </div>

            <div class="flex items-center gap-2">
                <input type="text" readonly :value="apiToken || '<?= $tokenDaCap ? 'Token đã được cấp — bấm Hiện token' : 'Chưa có token' ?>'" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2.5 text-xs text-green-400 font-mono focus:outline-none select-all">
                <button @click="layApiToken()" :disabled="dangLayToken" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold text-xs transition shrink-0"><i class="fas fa-eye"></i> Hiện</button>
                <button @click="saoChep(apiToken)" :disabled="!apiToken" class="px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 disabled:opacity-50 text-white font-bold text-xs transition shrink-0"><i class="fas fa-copy"></i> Copy</button>
            </div>
            <p class="text-[11px] text-amber-300"><i class="fas fa-shield-halved mr-1"></i> Token không được nhúng vào HTML ban đầu và chỉ lấy qua yêu cầu POST có CSRF.</p>
        </div>

        <!-- KHỐI 3: ĐỔI MẬT KHẨU TÀI KHOẢN -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-lock text-purple-400"></i> Đổi Mật Khẩu Đăng Nhập
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Nếu bạn đăng nhập bằng Google, bạn vẫn có thể đặt mật khẩu để đăng nhập bằng Email sau này</p>
            </div>

            <form @submit.prevent="doiMatKhau()" class="space-y-4 max-w-md">
                <?php if (!empty($user['password'])): ?>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Mật khẩu hiện tại:</label>
                        <input type="password" x-model="matKhauCu" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1">Mật khẩu mới (Tối thiểu 6 ký tự):</label>
                    <input type="password" x-model="matKhauMoi" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1">Xác nhận mật khẩu mới:</label>
                    <input type="password" x-model="xacNhanMatKhau" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                </div>

                <button type="submit" :disabled="dangDoiPass" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                    <span x-show="!dangDoiPass"><i class="fas fa-shield-halved mr-1"></i> Lưu Mật Khẩu Mới</span>
                    <span x-show="dangDoiPass"><i class="fas fa-circle-notch fa-spin mr-1"></i> Đang xử lý...</span>
                </button>
            </form>
        </div>

        <!-- KHỐI 4: XÁC THỰC HAI LỚP QUA AUTHENTICATOR (2FA) -->
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-800 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-shield-halved text-emerald-400"></i> Xác Thực 2 Lớp (Google / Microsoft Authenticator)
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Bảo vệ tài khoản an toàn tuyệt đối với mã OTP 6 số cập nhật mỗi 30 giây</p>
                </div>
                <div>
                    <template x-if="twoFactorEnabled">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-950 text-emerald-300 border border-emerald-800 flex items-center gap-1.5">
                            <i class="fas fa-check-circle"></i> Đang Bật (Bảo Vệ Tối Đa)
                        </span>
                    </template>
                    <template x-if="!twoFactorEnabled">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-800 text-gray-400 border border-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-circle-xmark"></i> Đang Tắt
                        </span>
                    </template>
                </div>
            </div>

            <div class="text-xs text-gray-300 space-y-3">
                <template x-if="!twoFactorEnabled">
                    <div>
                        <p class="text-gray-400 mb-3 leading-relaxed">
                            Khi bật xác thực hai lớp, mỗi lần đăng nhập vào website, bạn sẽ cần nhập thêm mã xác thực 6 số từ ứng dụng Authenticator trên điện thoại (Google Authenticator, Microsoft Authenticator, Authy, Apple Passwords). Kẻ gian dù biết mật khẩu cũng không thể truy cập tài khoản của bạn.
                        </p>
                        <button type="button" @click="batDauKichHoat2Fa()" :disabled="dangXuLy2Fa" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs transition flex items-center gap-2 shadow-lg shadow-emerald-600/30">
                            <span x-show="!dangXuLy2Fa"><i class="fas fa-qrcode mr-1"></i> Bật Xác Thực Authenticator Ngay</span>
                            <span x-show="dangXuLy2Fa"><i class="fas fa-circle-notch fa-spin mr-1"></i> Đang kết nối...</span>
                        </button>
                    </div>
                </template>

                <template x-if="twoFactorEnabled">
                    <div class="space-y-3">
                        <div class="p-3.5 bg-emerald-950/30 border border-emerald-800/40 rounded-2xl text-[12px] text-emerald-300 flex items-start gap-2.5">
                            <i class="fas fa-lock text-emerald-400 mt-0.5"></i>
                            <div>
                                <b>Tài khoản đang được bảo vệ 2 lớp.</b>
                                <p class="text-gray-400 mt-0.5">Mỗi lần đăng nhập bạn cần mở ứng dụng Authenticator trên điện thoại để lấy mã 6 số.</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <button type="button" @click="moModalBackup = true; otpCodeBackup = ''; danhSachBackupCodes = []" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 text-xs font-bold transition flex items-center gap-1.5">
                                <i class="fas fa-key text-amber-400"></i> Mã Dự Phòng (Backup Codes)
                            </button>
                            <button type="button" @click="moModalTat2Fa = true; otpCodeTat = ''" class="px-4 py-2 rounded-xl bg-red-950/50 hover:bg-red-900/60 text-red-300 border border-red-800 text-xs font-bold transition flex items-center gap-1.5">
                                <i class="fas fa-power-off text-red-400"></i> Tắt Xác Thực 2FA
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- MODAL 1: BẬT XÁC THỰC 2FA (QUÉT MÃ QR & NHẬP OTP) -->
        <div x-show="moModalBat2Fa" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
            <div class="bg-gray-900 border border-gray-700 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl" @click.away="if (!daKichHoatXong) moModalBat2Fa = false">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-shield-alt text-emerald-400"></i> Thiết Lập Authenticator (2FA)
                    </h3>
                    <button type="button" @click="moModalBat2Fa = false" class="text-gray-400 hover:text-white text-sm"><i class="fas fa-times"></i></button>
                </div>

                <!-- BƯỚC 1 & 2: QUÉT QR VÀ NHẬP OTP -->
                <div x-show="!daKichHoatXong" class="space-y-4">
                    <p class="text-xs text-gray-300">
                        1. Mở ứng dụng <b>Google Authenticator</b> hoặc <b>Microsoft Authenticator</b> trên điện thoại & quét mã QR bên dưới:
                    </p>
                    <div class="flex justify-center p-4 bg-white rounded-2xl w-48 h-48 mx-auto shadow-inner">
                        <img :src="qrUrl" alt="QR Code 2FA" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-400 mb-1 text-center">Hoặc nhập mã khóa bí mật này thủ công:</p>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly :value="secret2fa" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs text-amber-300 font-mono text-center select-all">
                            <button type="button" @click="saoChep(secret2fa)" class="px-3 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-bold text-white shrink-0"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div class="border-t border-gray-800 pt-3">
                        <label class="block text-xs font-semibold text-gray-200 mb-1">
                            2. Nhập mã OTP 6 số đang hiển thị trên ứng dụng:
                        </label>
                        <input type="text" maxlength="6" x-model="otpCodeBat" placeholder="000000" class="w-full bg-gray-950 border border-emerald-600/70 rounded-xl px-4 py-2.5 text-center text-lg text-emerald-400 font-mono tracking-widest font-bold focus:outline-none">
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <button type="button" @click="moModalBat2Fa = false" class="flex-1 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition">Hủy Bỏ</button>
                        <button type="button" @click="xacNhanKichHoat2Fa()" :disabled="dangXuLy2Fa || otpCodeBat.length !== 6" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold text-xs transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-1.5">
                            <span x-show="!dangXuLy2Fa"><i class="fas fa-check"></i> Xác Nhận Bật</span>
                            <span x-show="dangXuLy2Fa"><i class="fas fa-circle-notch fa-spin"></i> Đang kiểm tra...</span>
                        </button>
                    </div>
                </div>

                <!-- BƯỚC 3: HIỂN THỊ MÃ DỰ PHÒNG KHI ĐÃ BẬT THÀNH CÔNG -->
                <div x-show="daKichHoatXong" class="space-y-4">
                    <div class="text-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 text-2xl flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-check"></i>
                        </div>
                        <h4 class="text-sm font-bold text-white">Đã Kích Hoạt 2FA Thành Công!</h4>
                        <p class="text-xs text-gray-400 mt-1">Lưu trữ 8 mã dự phòng khẩn cấp này vào nơi an toàn:</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 bg-gray-950 p-3 rounded-2xl border border-gray-800">
                        <template x-for="code in danhSachBackupCodes" :key="code">
                            <div class="p-1.5 bg-gray-900 rounded-lg text-center font-mono text-xs text-amber-300 font-bold" x-text="code"></div>
                        </template>
                    </div>

                    <p class="text-[11px] text-gray-400 leading-relaxed">
                        <i class="fas fa-exclamation-triangle text-amber-400 mr-1"></i> Mỗi mã dự phòng chỉ dùng được 1 lần nếu bạn bị mất điện thoại hoặc không mở được ứng dụng Authenticator.
                    </p>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="saoChep(danhSachBackupCodes.join('\n'))" class="flex-1 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                            <i class="fas fa-copy"></i> Sao Chép Toàn Bộ Mã
                        </button>
                        <button type="button" @click="moModalBat2Fa = false" class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                            Hoàn Tất
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 2: TẮT XÁC THỰC 2FA -->
        <div x-show="moModalTat2Fa" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
            <div class="bg-gray-900 border border-gray-700 rounded-3xl max-w-sm w-full p-6 space-y-4 shadow-2xl" @click.away="moModalTat2Fa = false">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-power-off text-red-400"></i> Tắt Xác Thực 2FA
                    </h3>
                    <button type="button" @click="moModalTat2Fa = false" class="text-gray-400 hover:text-white text-sm"><i class="fas fa-times"></i></button>
                </div>
                <p class="text-xs text-gray-300 leading-relaxed">
                    Nhập mã OTP 6 số từ ứng dụng Authenticator (hoặc mật khẩu tài khoản) để xác nhận tắt 2FA:
                </p>
                <div>
                    <input type="password" x-model="otpCodeTat" placeholder="Nhập OTP 6 số hoặc Mật khẩu..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white text-center focus:outline-none focus:border-red-500">
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button type="button" @click="moModalTat2Fa = false" class="flex-1 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition">Hủy</button>
                    <button type="button" @click="xacNhanTat2Fa()" :disabled="dangXuLy2Fa || !otpCodeTat" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white font-bold text-xs transition shadow-lg shadow-red-600/30 flex items-center justify-center gap-1.5">
                        <span x-show="!dangXuLy2Fa">Xác Nhận Tắt</span>
                        <span x-show="dangXuLy2Fa"><i class="fas fa-circle-notch fa-spin"></i> Đang xử lý...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 3: XEM / TẠO LẠI MÃ DỰ PHÒNG -->
        <div x-show="moModalBackup" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
            <div class="bg-gray-900 border border-gray-700 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl" @click.away="moModalBackup = false">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-key text-amber-400"></i> Mã Dự Phòng Khẩn Cấp (Backup Codes)
                    </h3>
                    <button type="button" @click="moModalBackup = false" class="text-gray-400 hover:text-white text-sm"><i class="fas fa-times"></i></button>
                </div>

                <div x-show="danhSachBackupCodes.length === 0" class="space-y-3">
                    <p class="text-xs text-gray-300 leading-relaxed">
                        Vui lòng nhập mã OTP 6 số từ ứng dụng Authenticator để xác thực trước khi xem mã dự phòng:
                    </p>
                    <input type="text" maxlength="6" x-model="otpCodeBackup" placeholder="000000" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-2.5 text-center text-base text-amber-300 font-mono tracking-widest font-bold focus:outline-none focus:border-amber-500">
                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" @click="moModalBackup = false" class="flex-1 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition">Đóng</button>
                        <button type="button" @click="layMaDuPhong()" :disabled="dangXuLy2Fa || otpCodeBackup.length !== 6" class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                            <span x-show="!dangXuLy2Fa">Xem Mã Dự Phòng</span>
                            <span x-show="dangXuLy2Fa"><i class="fas fa-circle-notch fa-spin"></i> Đang tải...</span>
                        </button>
                    </div>
                </div>

                <div x-show="danhSachBackupCodes.length > 0" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2 bg-gray-950 p-3 rounded-2xl border border-gray-800">
                        <template x-for="code in danhSachBackupCodes" :key="code">
                            <div class="p-1.5 bg-gray-900 rounded-lg text-center font-mono text-xs text-amber-300 font-bold" x-text="code"></div>
                        </template>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" @click="saoChep(danhSachBackupCodes.join('\n'))" class="flex-1 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                            <i class="fas fa-copy"></i> Sao Chép Mã
                        </button>
                        <button type="button" @click="taoLaiMaDuPhong()" :disabled="dangXuLy2Fa" class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                            <i class="fas fa-rotate"></i> Tạo Lại Mã Mới
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function ungDungTaiKhoan() {
            return {
                apiToken: '',
                csrfToken: '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>',
                dangLayToken: false,
                matKhauCu: '',
                matKhauMoi: '',
                xacNhanMatKhau: '',
                thongBao: '',
                loaiThongBao: 'success',
                dangDoiPass: false,

                // Quản lý 2FA Authenticator
                twoFactorEnabled: <?= $twoFactorEnabled ? 'true' : 'false' ?>,
                moModalBat2Fa: false,
                moModalTat2Fa: false,
                moModalBackup: false,
                qrUrl: '',
                secret2fa: '',
                otpCodeBat: '',
                otpCodeTat: '',
                otpCodeBackup: '',
                danhSachBackupCodes: [],
                daKichHoatXong: false,
                dangXuLy2Fa: false,

                saoChep(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Đã sao chép vào bộ nhớ tạm!');
                    });
                },

                batDauKichHoat2Fa() {
                    this.dangXuLy2Fa = true;
                    this.daKichHoatXong = false;
                    this.otpCodeBat = '';
                    const formData = new FormData();
                    formData.append('action', '2fa_khoi_tao');
                    formData.append('csrf_token', this.csrfToken);

                    fetch('/backend/api.php?action=2fa_khoi_tao', {
                        method: 'POST',
                        body: formData,
                        headers: {'Accept': 'application/json', 'X-CSRF-Token': this.csrfToken}
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangXuLy2Fa = false;
                        if (data.thanh_cong) {
                            this.secret2fa = data.secret;
                            this.qrUrl = data.qr_url;
                            this.moModalBat2Fa = true;
                        } else {
                            this.thongBao = data.thong_bao || 'Không thể tạo mã xác thực';
                            this.loaiThongBao = 'error';
                        }
                    })
                    .catch(() => {
                        this.dangXuLy2Fa = false;
                        this.thongBao = 'Lỗi kết nối máy chủ';
                        this.loaiThongBao = 'error';
                    });
                },

                xacNhanKichHoat2Fa() {
                    if (this.otpCodeBat.length !== 6) return;
                    this.dangXuLy2Fa = true;
                    const formData = new FormData();
                    formData.append('action', '2fa_kich_hoat');
                    formData.append('code', this.otpCodeBat);
                    formData.append('csrf_token', this.csrfToken);

                    fetch('/backend/api.php?action=2fa_kich_hoat', {
                        method: 'POST',
                        body: formData,
                        headers: {'Accept': 'application/json', 'X-CSRF-Token': this.csrfToken}
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangXuLy2Fa = false;
                        if (data.thanh_cong) {
                            this.twoFactorEnabled = true;
                            this.daKichHoatXong = true;
                            this.danhSachBackupCodes = data.recovery_codes || [];
                            this.thongBao = data.thong_bao;
                            this.loaiThongBao = 'success';
                        } else {
                            alert(data.thong_bao || 'Mã xác thực không đúng');
                        }
                    })
                    .catch(() => {
                        this.dangXuLy2Fa = false;
                        alert('Lỗi kết nối máy chủ');
                    });
                },

                xacNhanTat2Fa() {
                    if (!this.otpCodeTat) return;
                    this.dangXuLy2Fa = true;
                    const formData = new FormData();
                    formData.append('action', '2fa_tat');
                    formData.append('code', this.otpCodeTat);
                    formData.append('csrf_token', this.csrfToken);

                    fetch('/backend/api.php?action=2fa_tat', {
                        method: 'POST',
                        body: formData,
                        headers: {'Accept': 'application/json', 'X-CSRF-Token': this.csrfToken}
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangXuLy2Fa = false;
                        if (data.thanh_cong) {
                            this.twoFactorEnabled = false;
                            this.moModalTat2Fa = false;
                            this.thongBao = data.thong_bao;
                            this.loaiThongBao = 'success';
                        } else {
                            alert(data.thong_bao || 'Xác nhận không thành công');
                        }
                    })
                    .catch(() => {
                        this.dangXuLy2Fa = false;
                        alert('Lỗi kết nối máy chủ');
                    });
                },

                layMaDuPhong() {
                    if (this.otpCodeBackup.length !== 6) return;
                    this.dangXuLy2Fa = true;
                    const formData = new FormData();
                    formData.append('action', '2fa_lay_ma_du_phong');
                    formData.append('code', this.otpCodeBackup);
                    formData.append('csrf_token', this.csrfToken);

                    fetch('/backend/api.php?action=2fa_lay_ma_du_phong', {
                        method: 'POST',
                        body: formData,
                        headers: {'Accept': 'application/json', 'X-CSRF-Token': this.csrfToken}
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangXuLy2Fa = false;
                        if (data.thanh_cong) {
                            this.danhSachBackupCodes = data.recovery_codes || [];
                        } else {
                            alert(data.thong_bao || 'Mã OTP không đúng');
                        }
                    })
                    .catch(() => {
                        this.dangXuLy2Fa = false;
                        alert('Lỗi kết nối máy chủ');
                    });
                },

                taoLaiMaDuPhong() {
                    if (!confirm('Bạn có chắc muốn tạo lại 8 mã dự phòng mới? Các mã dự phòng cũ sẽ bị vô hiệu hóa.')) return;
                    this.dangXuLy2Fa = true;
                    const formData = new FormData();
                    formData.append('action', '2fa_tao_lai_ma_du_phong');
                    formData.append('code', this.otpCodeBackup);
                    formData.append('csrf_token', this.csrfToken);

                    fetch('/backend/api.php?action=2fa_tao_lai_ma_du_phong', {
                        method: 'POST',
                        body: formData,
                        headers: {'Accept': 'application/json', 'X-CSRF-Token': this.csrfToken}
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangXuLy2Fa = false;
                        if (data.thanh_cong) {
                            this.danhSachBackupCodes = data.recovery_codes || [];
                            alert('Đã tạo thành công bộ mã dự phòng mới!');
                        } else {
                            alert(data.thong_bao || 'Lỗi khi tạo mã mới');
                        }
                    })
                    .catch(() => {
                        this.dangXuLy2Fa = false;
                        alert('Lỗi kết nối máy chủ');
                    });
                },

                layApiToken() {
                    this.dangLayToken = true;
                    fetch('/backend/api.php?action=lay_api_token', {method: 'POST', headers: {'X-CSRF-Token': this.csrfToken, 'Accept': 'application/json'}})
                        .then(res => res.json())
                        .then(data => {
                            if (data.thanh_cong) this.apiToken = data.api_token;
                            else { this.thongBao = data.thong_bao || 'Không thể lấy token'; this.loaiThongBao = 'error'; }
                        })
                        .catch(() => { this.thongBao = 'Lỗi kết nối máy chủ'; this.loaiThongBao = 'error'; })
                        .finally(() => { this.dangLayToken = false; });
                },

                taoMoiApiToken() {
                    if (!confirm('Bạn có chắc muốn tạo lại API Token? Token cũ sẽ bị vô hiệu hóa.')) return;
                    fetch('/backend/api.php?action=tao_moi_api_token', {method: 'POST', headers: {'X-CSRF-Token': this.csrfToken, 'Accept': 'application/json'}})
                        .then(res => res.json())
                        .then(data => {
                            if (data.thanh_cong) { this.apiToken = data.api_token; this.thongBao = data.thong_bao; this.loaiThongBao = 'success'; }
                            else { this.thongBao = data.thong_bao || 'Không thể tạo token'; this.loaiThongBao = 'error'; }
                        })
                        .catch(() => { this.thongBao = 'Lỗi kết nối máy chủ'; this.loaiThongBao = 'error'; });
                },

                doiMatKhau() {
                    if (this.matKhauMoi !== this.xacNhanMatKhau) {
                        this.thongBao = 'Mật khẩu xác nhận không khớp!';
                        this.loaiThongBao = 'error';
                        return;
                    }

                    this.dangDoiPass = true;
                    this.thongBao = '';

                    const formData = new FormData();
                    formData.append('action', 'doi_mat_khau');
                    formData.append('old_password', this.matKhauCu);
                     formData.append('new_password', this.matKhauMoi);
                     formData.append('csrf_token', this.csrfToken);


                    fetch('/backend/api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.dangDoiPass = false;
                        this.thongBao = data.thong_bao;
                        this.loaiThongBao = data.thanh_cong ? 'success' : 'error';
                        if (data.thanh_cong) {
                            this.matKhauCu = '';
                            this.matKhauMoi = '';
                            this.xacNhanMatKhau = '';
                        }
                    })
                    .catch(() => {
                        this.dangDoiPass = false;
                        this.thongBao = 'Lỗi kết nối máy chủ!';
                        this.loaiThongBao = 'error';
                    });
                }
            };
        }
    </script>
</body>
</html>
