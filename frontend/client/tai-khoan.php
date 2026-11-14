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

                saoChep(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Đã sao chép Personal API Token!');
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
