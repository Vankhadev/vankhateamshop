<?php
// ========================================================
// TRANG NẠP TIỀN TỰ ĐỘNG VÀO VÍ - VIETQR (SEPAY)
// vankhateamshop - Khách nhập số tiền -> Quét QR -> Tự cộng tiền vào ví
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

// Áp dụng tiêu đề bảo mật
thietLapBaoMatHeaders();

// Bắt buộc phải đăng nhập
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

// Tạo mã nạp tiền ngẫu nhiên chuẩn tiemai + 5 số ngẫu nhiên từ 0 đến 9 (VD: tiemai58291)
$maNapTien = 'tiemai' . sprintf('%05d', mt_rand(10000, 99999));
try {
    $stmtCode = $pdo->prepare("INSERT INTO `wallet_deposit_codes` (`user_id`, `code`, `status`) VALUES (?, ?, 'PENDING')");
    $stmtCode->execute([$user['id'], $maNapTien]);
} catch (Exception $e) {
    // Nếu trùng mã ngẫu nhiên thì sinh mã khác
    $maNapTien = 'tiemai' . sprintf('%05d', mt_rand(10000, 99999));
    $stmtCode = $pdo->prepare("INSERT INTO `wallet_deposit_codes` (`user_id`, `code`, `status`) VALUES (?, ?, 'PENDING')");
    $stmtCode->execute([$user['id'], $maNapTien]);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nạp Tiền Vào Ví - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungNapTien()" class="min-h-screen flex flex-col antialiased">

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
                <a href="/user/nap-tien" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
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
                <a href="/user/tai-khoan" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-user-gear mr-1"></i> Cài Đặt Tài Khoản
                </a>
            </nav>
        </div>
    </div>

    <main class="max-w-xl mx-auto px-4 py-10 w-full flex-1">
        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <div class="text-center mb-6">
                <span class="text-xs font-bold text-green-400 uppercase tracking-wider">Hệ Thống Nạp Tiền Tự Động</span>
                <h1 class="text-xl font-bold text-white mt-1">Nạp Tiền Vào Số Dư Ví</h1>
                <p class="text-xs text-gray-400 mt-1">Quét mã VietQR từ bất kỳ ngân hàng nào, cộng tiền sau 2 - 5 giây</p>
                <div class="mt-2 text-xs text-gray-400">
                    Số dư hiện tại: <span class="font-bold text-green-400"><?= number_format($user['balance'], 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>

            <!-- Chọn nhanh mệnh giá -->
            <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-300 mb-2">Chọn nhanh số tiền nạp:</label>
                <div class="grid grid-cols-3 gap-2 text-xs font-bold">
                    <button @click="soTienNap = 50000; taoLinkQr()" :class="soTienNap === 50000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'" class="py-2 rounded-xl border border-gray-700 transition">50.000đ</button>
                    <button @click="soTienNap = 100000; taoLinkQr()" :class="soTienNap === 100000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'" class="py-2 rounded-xl border border-gray-700 transition">100.000đ</button>
                    <button @click="soTienNap = 200000; taoLinkQr()" :class="soTienNap === 200000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'" class="py-2 rounded-xl border border-gray-700 transition">200.000đ</button>
                    <button @click="soTienNap = 500000; taoLinkQr()" :class="soTienNap === 500000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'" class="py-2 rounded-xl border border-gray-700 transition">500.000đ</button>
                    <button @click="soTienNap = 1000000; taoLinkQr()" :class="soTienNap === 1000000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'" class="py-2 rounded-xl border border-gray-700 transition">1.000.000đ</button>
                    <button @click="soTienNap = 2000000; taoLinkQr()" :class="soTienNap === 2000000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'" class="py-2 rounded-xl border border-gray-700 transition">2.000.000đ</button>
                </div>
            </div>

            <!-- Hoặc tự điền số tiền -->
            <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-300 mb-1.5">Hoặc nhập số tiền tùy chọn (VNĐ):</label>
                <input type="number" step="10000" min="10000" x-model="soTienNap" @input="taoLinkQr()" placeholder="Ví dụ: 150000" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-green-400 font-bold focus:outline-none focus:border-blue-500">
            </div>

            <!-- KHUNG THẺ VIETQR ACB CHUẨN MẪU HÌNH ẢNH -->
            <div class="bg-white text-gray-900 rounded-2xl p-4 sm:p-5 shadow-xl border border-gray-100 mb-5">
                
                <!-- Dòng tiêu đề Ngân hàng ACB + Huy hiệu Tự động 24/7 -->
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xl font-black text-[#005ba6] tracking-tight">ACB</span>
                        <span class="text-xs font-semibold text-gray-700"><?= defined('BANK_FULL_NAME') ? BANK_FULL_NAME : 'Ngân hàng TMCP Á Châu' ?></span>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
                        <i class="fas fa-bolt text-[10px]"></i> Tự động 24/7
                    </span>
                </div>

                <!-- Khung ảnh QR code VietQR -->
                <div class="border border-gray-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center mb-3.5 shadow-sm">
                    <div class="flex items-center gap-1 mb-2 font-black text-sm tracking-wider">
                        <span class="text-rose-600">VIET</span><span class="text-blue-600">QR</span>
                    </div>
                    <img :src="linkQr" alt="Mã VietQR" class="w-56 h-auto object-contain mx-auto rounded-lg">
                    <div class="flex items-center justify-center gap-2 text-[11px] text-gray-500 font-semibold mt-2.5">
                        <span class="text-blue-600 font-bold">napas<span class="text-red-500 font-normal">247</span></span>
                        <span>|</span>
                        <span class="text-[#005ba6] font-bold">ACB</span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">Quét mã VietQR để chuyển tiền nhanh</p>
                </div>

                <!-- Thông tin tài khoản & nội dung chuyển khoản -->
                <div class="space-y-2.5 text-left">
                    
                    <!-- CHỦ TÀI KHOẢN -->
                    <div class="border border-gray-200 rounded-xl px-3.5 py-2.5 bg-gray-50/50">
                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">CHỦ TÀI KHOẢN</span>
                        <span class="block text-sm font-black text-gray-900 mt-0.5"><?= BANK_ACCOUNT_NAME ?></span>
                    </div>

                    <!-- SỐ TÀI KHOẢN + NÚT SAO CHÉP -->
                    <div class="border border-gray-200 rounded-xl px-3.5 py-2.5 bg-gray-50/50 flex items-center justify-between">
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">SỐ TÀI KHOẢN</span>
                            <span class="block text-base font-black text-[#0284c7] mt-0.5 font-mono"><?= BANK_ACCOUNT_NO ?></span>
                        </div>
                        <button @click="saoChep('<?= BANK_ACCOUNT_NO ?>')" class="px-3 py-1.5 rounded-lg bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </div>

                    <!-- NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC) - VIỀN NÉT ĐỨT XANH DƯƠNG -->
                    <div class="border-2 border-dashed border-[#0284c7] bg-[#f0f9ff] rounded-xl px-3.5 py-2.5 flex items-center justify-between">
                        <div>
                            <span class="block text-[10px] font-extrabold text-rose-500 uppercase tracking-wider">NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC)</span>
                            <span class="block text-base font-black text-rose-600 mt-0.5 font-mono tracking-wide" x-text="maNapTien"></span>
                        </div>
                        <button @click="saoChep(maNapTien)" class="px-3 py-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </div>

                    <!-- SỐ TIỀN CẦN NẠP -->
                    <div class="flex justify-between items-center px-1 pt-1 text-xs">
                        <span class="text-gray-500 font-semibold">Số tiền cần nạp:</span>
                        <span class="text-base font-black text-emerald-600 font-mono" x-text="dinhDangTien(soTienNap)"></span>
                    </div>

                </div>
            </div>

            <!-- DÒNG TRẠNG THÁI TỰ ĐỘNG KIỂM TRA SAU 5S & NÚT KIỂM TRA NGAY -->
            <div class="pt-1 mb-4 space-y-2">
                <div class="flex items-center justify-center gap-2 text-xs text-blue-400 font-semibold bg-gray-950 border border-gray-800 rounded-xl py-2 px-3">
                    <i class="fas fa-circle-notch fa-spin text-blue-400"></i>
                    <span>Tự động kiểm tra sau: <strong class="text-amber-400 font-mono text-sm" x-text="demNguoc5s + 's'">5s</strong></span>
                </div>

                <!-- NÚT KIỂM TRA NGAY -->
                <button @click="kiemTraSoDuNgay(false)" :disabled="dangKiemTraNgay" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-bold text-xs transition shadow-lg shadow-green-600/30 flex items-center justify-center gap-2">
                    <i class="fas fa-magnifying-glass" :class="dangKiemTraNgay ? 'fa-spin' : ''"></i>
                    <span x-text="dangKiemTraNgay ? 'Đang kiểm tra với ngân hàng...' : 'Kiểm Tra Ngay'">Kiểm Tra Ngay</span>
                </button>
            </div>

            <div class="p-3 rounded-xl bg-blue-950/40 border border-blue-800/50 text-[11px] text-blue-300">
                <i class="fas fa-info-circle mr-1"></i> <strong>Lưu ý:</strong> Vui lòng giữ nguyên nội dung chuyển khoản là <strong x-text="maNapTien"></strong> để hệ thống tự động cộng tiền vào ví chính xác sau khi chuyển khoản thành công.
            </div>
        </div>
    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function ungDungNapTien() {
            return {
                soTienNap: 100000,
                soDuHienTai: <?= (float)$user['balance'] ?>,
                linkQr: '',
                maNapTien: '<?= $maNapTien ?>',
                demNguoc5s: 5,
                boDemGiay: null,
                dangKiemTraNgay: false,

                init() {
                    this.taoLinkQr();
                    this.batDauDemNguoc5s();
                },

                dinhDangTien(soTien) {
                    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(soTien);
                },

                taoLinkQr() {
                    if (!this.soTienNap || this.soTienNap < 10000) return;
                    let bankBin = '<?= defined("BANK_BIN") ? BANK_BIN : "970416" ?>';
                    this.linkQr = `https://api.vietqr.io/image/${bankBin}-<?= BANK_ACCOUNT_NO ?>-compact2.jpg?amount=${this.soTienNap}&addInfo=${this.maNapTien}&accountName=<?= urlencode(BANK_ACCOUNT_NAME) ?>`;
                },

                batDauDemNguoc5s() {
                    if (this.boDemGiay) clearInterval(this.boDemGiay);
                    this.demNguoc5s = 5;
                    this.boDemGiay = setInterval(() => {
                        this.demNguoc5s--;
                        if (this.demNguoc5s <= 0) {
                            this.demNguoc5s = 5;
                            this.kiemTraSoDuNgay(true);
                        }
                    }, 1000);
                },

                kiemTraSoDuNgay(laTuDong = false) {
                    if (!laTuDong) this.dangKiemTraNgay = true;
                    
                    fetch('/backend/api.php?action=kiem_tra_so_du_vi')
                        .then(r => r.json())
                        .then(data => {
                            if (data.thanh_cong) {
                                if (data.so_du > this.soDuHienTai) {
                                    clearInterval(this.boDemGiay);
                                    let soTienCong = data.so_du - this.soDuHienTai;
                                    alert('🎉 Chúc mừng! Bạn vừa nạp thành công ' + this.dinhDangTien(soTienCong) + ' vào tài khoản! Số dư mới: ' + data.so_du_chu);
                                    window.location.reload();
                                } else if (!laTuDong) {
                                    alert('Chưa nhận được giao dịch chuyển khoản mới. Vui lòng hoàn tất chuyển khoản và thử lại sau ít giây!');
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Lỗi kiểm tra số dư:', err);
                        })
                        .finally(() => {
                            if (!laTuDong) this.dangKiemTraNgay = false;
                        });
                },

                saoChep(noiDung) {
                    navigator.clipboard.writeText(noiDung).then(() => {
                        alert('Đã sao chép nội dung: ' + noiDung);
                    });
                }
            };
        }
    </script>
</body>
</html>
