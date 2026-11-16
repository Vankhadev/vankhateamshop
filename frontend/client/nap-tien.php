<?php
// ========================================================
// TRANG NẠP TIỀN TỰ ĐỘNG VÀO VÍ - VIETQR (SEPAY)
// vankhateamshop - Khách chọn/nhập số tiền -> Bấm Tạo Mã Nạp -> Quét QR -> Tự cộng tiền vào ví
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

// Đọc cấu hình từ bảng settings để luôn đồng bộ với Admin Settings
$walletMinDeposit = 20000;
$bankName = BANK_NAME;
$bankAccount = BANK_ACCOUNT_NO;
$accountName = BANK_ACCOUNT_NAME;
$bankBin = defined('BANK_BIN') ? BANK_BIN : '970416';

try {
    $stmtSettings = $pdo->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN ('wallet_min_deposit', 'sepay_bank_name', 'sepay_bank_account', 'sepay_account_name')");
    $sRows = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($sRows['wallet_min_deposit']) && is_numeric($sRows['wallet_min_deposit'])) {
        $walletMinDeposit = (float)$sRows['wallet_min_deposit'];
    }
    if (!empty($sRows['sepay_bank_name'])) $bankName = $sRows['sepay_bank_name'];
    if (!empty($sRows['sepay_bank_account'])) $bankAccount = $sRows['sepay_bank_account'];
    if (!empty($sRows['sepay_account_name'])) $accountName = $sRows['sepay_account_name'];
} catch (Exception $e) {}
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
            
            <!-- TIÊU ĐỀ CHÍNH -->
            <div class="text-center mb-6">
                <span class="text-xs font-bold text-green-400 uppercase tracking-wider">Hệ Thống Nạp Tiền Tự Động</span>
                <h1 class="text-xl font-bold text-white mt-1">Nạp Tiền Vào Số Dư Ví</h1>
                <p class="text-xs text-gray-400 mt-1">Quét mã VietQR từ bất kỳ ngân hàng nào, cộng tiền sau 2 - 5 giây</p>
                <div class="mt-2 text-xs text-gray-400">
                    Số dư hiện tại: <span class="font-bold text-green-400"><?= number_format($user['balance'], 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- BƯỚC 1: CHỌN SỐ TIỀN & BẤM NÚT TẠO MÃ NẠP (KHI CHƯA TẠO MÃ) -->
            <!-- ======================================================== -->
            <div x-show="!daTaoMa" x-transition>
                
                <!-- Chọn nhanh mệnh giá -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-300 mb-2">Chọn nhanh số tiền nạp:</label>
                    <div class="grid grid-cols-3 gap-2 text-xs font-bold">
                        <button type="button" @click="chonNhanh(5000)" :class="soTienNap === 5000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">5.000đ</button>
                        <button type="button" @click="chonNhanh(10000)" :class="soTienNap === 10000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">10.000đ</button>
                        <button type="button" @click="chonNhanh(30000)" :class="soTienNap === 30000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">30.000đ</button>
                        <button type="button" @click="chonNhanh(50000)" :class="soTienNap === 50000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">50.000đ</button>
                        <button type="button" @click="chonNhanh(100000)" :class="soTienNap === 100000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">100.000đ</button>
                        <button type="button" @click="chonNhanh(200000)" :class="soTienNap === 200000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">200.000đ</button>
                        <button type="button" @click="chonNhanh(500000)" :class="soTienNap === 500000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">500.000đ</button>
                        <button type="button" @click="chonNhanh(1000000)" :class="soTienNap === 1000000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">1.000.000đ</button>
                        <button type="button" @click="chonNhanh(2000000)" :class="soTienNap === 2000000 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'" class="py-2.5 rounded-xl border border-gray-700 transition">2.000.000đ</button>
                    </div>
                </div>

                <!-- Hoặc tự điền số tiền -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-300 mb-1.5">Hoặc nhập số tiền tùy chọn (VNĐ):</label>
                    <input type="number" step="10000" :min="minDeposit" x-model.number="soTienNap" placeholder="Ví dụ: 50000" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-green-400 font-bold focus:outline-none focus:border-blue-500">
                </div>

                <!-- Thông báo số tiền nạp tối thiểu -->
                <div class="mb-5 flex items-center justify-between text-xs bg-gray-950/80 border border-gray-800 rounded-xl px-3.5 py-2 text-gray-400">
                    <span class="flex items-center gap-1.5">
                        <i class="fas fa-shield-halved text-amber-400"></i> Hạn mức nạp tối thiểu:
                    </span>
                    <span class="font-bold text-amber-400 font-mono"><?= number_format($walletMinDeposit, 0, ',', '.') ?> VNĐ</span>
                </div>

                <!-- Thông báo lỗi (nếu có) -->
                <div x-show="thongBaoLoi" class="mb-4 p-3 rounded-xl bg-red-950/50 border border-red-800 text-red-300 text-xs flex items-center gap-2">
                    <i class="fas fa-circle-exclamation shrink-0 text-red-400"></i>
                    <span x-text="thongBaoLoi"></span>
                </div>

                <!-- NÚT TẠO MÃ NẠP TIỀN -->
                <button type="button" @click="taoMaNap()" :disabled="dangTaoMa" class="w-full py-3 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                    <i class="fas fa-qrcode text-base" :class="dangTaoMa ? 'fa-spin' : ''"></i>
                    <span x-text="dangTaoMa ? 'Đang tạo mã nạp...' : 'Tạo Mã Nạp Tiền'">Tạo Mã Nạp Tiền</span>
                </button>
            </div>

            <!-- ======================================================== -->
            <!-- BƯỚC 2: FORM NẠP TIỀN VÀO VÍ (HIỆN SAU KHI BẤM TẠO MÃ NẠP) -->
            <!-- ======================================================== -->
            <div x-show="daTaoMa" x-transition>
                
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-800">
                    <button type="button" @click="huyVaChonLai()" class="text-xs text-gray-400 hover:text-white flex items-center gap-1.5 transition">
                        <i class="fas fa-arrow-left"></i> Chọn lại số tiền khác
                    </button>
                    <span class="text-[11px] font-bold text-blue-400">
                        <i class="fas fa-receipt mr-1"></i> Mã nạp: <strong class="font-mono text-white" x-text="maNapTien"></strong>
                    </span>
                </div>

                <!-- KHUNG THẺ VIETQR ACB CHUẨN MẪU HÌNH ẢNH (VIỀN ĐẬM RÕ NÉT) -->
                <div class="bg-white text-gray-900 rounded-3xl p-5 sm:p-6 shadow-2xl border-2 border-gray-300 mb-5">
                    
                    <!-- Dòng tiêu đề Ngân hàng ACB + Huy hiệu Tự động 24/7 -->
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b-2 border-gray-200">
                        <div class="flex items-center gap-2.5">
                            <span class="text-2xl font-black text-[#005ba6] tracking-tight" x-text="bankName">ACB</span>
                            <span class="text-xs font-bold text-gray-700"><?= defined('BANK_FULL_NAME') ? BANK_FULL_NAME : 'Ngân hàng TMCP Á Châu' ?></span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-700 border border-emerald-300">
                            <i class="fas fa-bolt text-[11px]"></i> Tự động 24/7
                        </span>
                    </div>

                    <!-- Khung ảnh QR code VietQR -->
                    <div class="border-2 border-gray-300 rounded-2xl p-4 bg-gray-50/50 flex flex-col items-center justify-center mb-4 shadow-sm">
                        <div class="flex items-center gap-1 mb-2 font-black text-base tracking-wider">
                            <span class="text-rose-600">VIET</span><span class="text-blue-600">QR</span>
                        </div>
                        <img :src="linkQr" alt="Mã VietQR" class="w-60 h-auto object-contain mx-auto rounded-xl border border-gray-200 bg-white p-1">
                        <div class="flex items-center justify-center gap-2 text-xs text-gray-600 font-bold mt-3">
                            <span class="text-blue-600 font-extrabold">napas<span class="text-red-500 font-normal">247</span></span>
                            <span>|</span>
                            <span class="text-[#005ba6] font-extrabold" x-text="bankName">ACB</span>
                        </div>
                        <p class="text-xs text-gray-600 font-medium mt-1">Quét mã VietQR để chuyển tiền nhanh</p>
                    </div>

                    <!-- Thông tin chi tiết thanh toán viền đậm phân khung rõ nét -->
                    <div class="space-y-3 text-left">
                        
                        <!-- 1. CHỦ TÀI KHOẢN -->
                        <div class="border-2 border-gray-300 rounded-xl px-4 py-3 bg-gray-50/80">
                            <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">CHỦ TÀI KHOẢN</span>
                            <span class="block text-base font-black text-gray-900 mt-0.5 tracking-wide" x-text="accountName"><?= htmlspecialchars($accountName) ?></span>
                        </div>

                        <!-- 2. SỐ TÀI KHOẢN + NÚT SAO CHÉP -->
                        <div class="border-2 border-blue-300 bg-blue-50/50 rounded-xl px-4 py-3 flex items-center justify-between">
                            <div>
                                <span class="block text-[11px] font-extrabold text-[#0284c7] uppercase tracking-wider">SỐ TÀI KHOẢN</span>
                                <span class="block text-lg font-black text-[#005ba6] mt-0.5 font-mono tracking-wide" x-text="bankAccount"><?= htmlspecialchars($bankAccount) ?></span>
                            </div>
                            <button type="button" @click="saoChep(bankAccount, 'Số tài khoản')" class="px-3.5 py-2 rounded-xl bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-blue-500/20 active:scale-95">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                        </div>

                        <!-- 3. NẠP TỐI THIỂU -->
                        <div class="border-2 border-amber-300 bg-amber-50/80 rounded-xl px-4 py-3 flex items-center justify-between">
                            <div>
                                <span class="block text-[11px] font-extrabold text-amber-800 uppercase tracking-wider">NẠP TỐI THIỂU</span>
                                <span class="block text-sm font-black text-amber-900 mt-0.5 font-mono"><?= number_format($walletMinDeposit, 0, ',', '.') ?> VNĐ</span>
                            </div>
                            <span class="text-[11px] font-bold text-amber-700 bg-amber-100 border border-amber-300 px-2.5 py-1 rounded-lg">Không chuyển dưới mức này</span>
                        </div>

                        <!-- 4. NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC) - VIỀN NÉT ĐỨT ĐỎ NỔI BẬT -->
                        <div class="border-2 border-dashed border-rose-400 bg-rose-50/80 rounded-xl px-4 py-3 flex items-center justify-between">
                            <div>
                                <span class="block text-[11px] font-extrabold text-rose-600 uppercase tracking-wider">NỘI DUNG CHUYỂN KHOẢN (BẮT BUỘC)</span>
                                <span class="block text-lg font-black text-rose-600 mt-0.5 font-mono tracking-wider" x-text="maNapTien"></span>
                            </div>
                            <button type="button" @click="saoChep(maNapTien, 'Nội dung')" class="px-3.5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-rose-500/20 active:scale-95">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                        </div>

                        <!-- 5. SỐ TIỀN CẦN NẠP -->
                        <div class="border-2 border-emerald-300 bg-emerald-50/60 rounded-xl px-4 py-3 flex justify-between items-center">
                            <div>
                                <span class="block text-[11px] font-extrabold text-emerald-800 uppercase tracking-wider">SỐ TIỀN CẦN NẠP</span>
                                <span class="block text-lg font-black text-emerald-600 font-mono mt-0.5" x-text="dinhDangTien(soTienNap)"></span>
                            </div>
                            <button type="button" @click="saoChep(soTienNap, 'Số tiền')" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-emerald-500/20 active:scale-95">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                        </div>

                    </div>
                </div>

                <!-- DÒNG TRẠNG THÁI TỰ ĐỘNG KIỂM TRA SAU 5S & NÚT TÔI ĐÃ CHUYỂN KHOẢN -->
                <div class="pt-1 mb-4 space-y-2">
                    <div class="flex items-center justify-center gap-2 text-xs text-blue-400 font-semibold bg-gray-950 border border-gray-800 rounded-xl py-2 px-3">
                        <i class="fas fa-circle-notch fa-spin text-blue-400"></i>
                        <span>Tự động kiểm tra sau: <strong class="text-amber-400 font-mono text-sm" x-text="demNguoc5s + 's'">5s</strong></span>
                    </div>

                    <!-- NÚT TÔI ĐÃ CHUYỂN KHOẢN -->
                    <button type="button" @click="kiemTraGiaoDich(false)" :disabled="dangKiemTraNgay" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-bold text-xs transition shadow-lg shadow-green-600/30 flex items-center justify-center gap-2">
                        <i class="fas fa-check-circle" :class="dangKiemTraNgay ? 'fa-spin' : ''"></i>
                        <span x-text="dangKiemTraNgay ? 'Đang kiểm tra với ngân hàng...' : 'Tôi đã chuyển khoản'">Tôi đã chuyển khoản</span>
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-blue-950/40 border border-blue-800/50 text-[11px] text-blue-300 space-y-1">
                    <p><i class="fas fa-info-circle mr-1"></i> <strong>Lưu ý quan trọng:</strong></p>
                    <p>• Vui lòng giữ nguyên nội dung chuyển khoản là <strong class="text-white" x-text="maNapTien"></strong> để hệ thống tự động cộng tiền sau 2 - 5 giây.</p>
                    <p>• Số tiền nạp phải từ <strong><?= number_format($walletMinDeposit, 0, ',', '.') ?> VNĐ</strong> trở lên. Chuyển dưới mức này hệ thống sẽ từ chối cộng tiền vào ví.</p>
                </div>

            </div>

        </div>
    </main>

    <!-- POPUP MODAL THÔNG BÁO NẠP TIỀN VÀO VÍ THÀNH CÔNG -->
    <div x-show="popupNapThanhCong" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="bg-gray-900 border border-gray-700 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl text-center relative">
            
            <!-- Icon thành công hiệu ứng lấp lánh -->
            <div class="w-16 h-16 rounded-full bg-emerald-500/20 border-2 border-emerald-500 text-emerald-400 flex items-center justify-center mx-auto mb-4 text-3xl shadow-lg shadow-emerald-500/30">
                <i class="fas fa-check"></i>
            </div>

            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 mb-2">
                <i class="fas fa-circle-check"></i> Đã Cộng Thành Công
            </span>

            <h3 class="text-xl font-black text-white mb-1">Nạp Tiền Vào Ví Thành Công!</h3>
            <p class="text-xs text-gray-400 mb-5">Hệ thống VietQR tự động đã xác nhận giao dịch chuyển khoản</p>

            <!-- Khung chi tiết giao dịch -->
            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-4 text-left space-y-3 mb-5">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-400">Mã nạp ví:</span>
                    <span class="font-mono font-bold text-amber-400 text-sm" x-text="maNapTien"></span>
                </div>
                <div class="flex justify-between items-center text-xs border-t border-gray-800/80 pt-2.5">
                    <span class="text-gray-400">Trạng thái:</span>
                    <span class="font-bold text-emerald-400 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> Đã thanh toán
                    </span>
                </div>
                <div class="flex justify-between items-center text-xs border-t border-gray-800/80 pt-2.5">
                    <span class="text-gray-400">Số tiền nạp:</span>
                    <span class="font-mono font-black text-emerald-400 text-base" x-text="dinhDangTien(soTienVuaNap)"></span>
                </div>
                <div class="flex justify-between items-center text-xs border-t border-gray-800/80 pt-2.5">
                    <span class="text-gray-400">Số dư ví hiện tại:</span>
                    <span class="font-mono font-bold text-amber-400 text-sm" x-text="dinhDangTien(soDuMoiNhan)"></span>
                </div>
            </div>

            <!-- Nút đóng / Hoàn tất -->
            <button type="button" @click="window.location.reload()" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-bold text-sm transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                <i class="fas fa-wallet"></i> Xác Nhận & Xem Số Dư
            </button>
        </div>
    </div>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function ungDungNapTien() {
            return {
                soTienNap: <?= max(50000, $walletMinDeposit) ?>,
                minDeposit: <?= $walletMinDeposit ?>,
                soDuHienTai: <?= (float)$user['balance'] ?>,
                daTaoMa: false,
                dangTaoMa: false,
                linkQr: '',
                maNapTien: '',
                bankAccount: '<?= htmlspecialchars($bankAccount) ?>',
                accountName: '<?= htmlspecialchars($accountName) ?>',
                bankName: '<?= htmlspecialchars($bankName) ?>',
                bankBin: '<?= $bankBin ?>',
                demNguoc5s: 5,
                boDemGiay: null,
                dangKiemTraNgay: false,
                thongBaoLoi: '',
                popupNapThanhCong: false,
                soTienVuaNap: 0,
                soDuMoiNhan: <?= (float)$user['balance'] ?>,

                init() {
                    // Trạng thái ban đầu chưa tạo mã nạp
                },

                dinhDangTien(soTien) {
                    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(soTien);
                },

                chonNhanh(tien) {
                    this.soTienNap = tien;
                    this.thongBaoLoi = '';
                },

                taoMaNap() {
                    this.thongBaoLoi = '';
                    let amount = parseFloat(this.soTienNap);
                    if (isNaN(amount) || amount < this.minDeposit) {
                        this.thongBaoLoi = 'Số tiền nạp tối thiểu là ' + this.dinhDangTien(this.minDeposit) + '. Vui lòng nhập số tiền từ ' + this.dinhDangTien(this.minDeposit) + ' trở lên!';
                        return;
                    }

                    this.dangTaoMa = true;
                    let formData = new FormData();
                    formData.append('amount', amount);

                    fetch('/backend/api.php?action=tao_ma_nap_vi', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.thanh_cong) {
                            this.maNapTien = data.code;
                            this.linkQr = data.qr_url;
                            this.bankAccount = data.bank_account || this.bankAccount;
                            this.accountName = data.account_name || this.accountName;
                            this.bankName = data.bank_name || this.bankName;
                            this.daTaoMa = true;
                            this.batDauDemNguoc5s();
                        } else {
                            this.thongBaoLoi = data.thong_bao || 'Có lỗi xảy ra khi tạo mã nạp, vui lòng thử lại!';
                        }
                    })
                    .catch(err => {
                        console.error('Lỗi kết nối tạo mã nạp:', err);
                        this.thongBaoLoi = 'Không thể kết nối máy chủ, vui lòng thử lại sau ít giây!';
                    })
                    .finally(() => {
                        this.dangTaoMa = false;
                    });
                },

                huyVaChonLai() {
                    if (this.boDemGiay) clearInterval(this.boDemGiay);
                    this.daTaoMa = false;
                    this.demNguoc5s = 5;
                },

                batDauDemNguoc5s() {
                    if (this.boDemGiay) clearInterval(this.boDemGiay);
                    this.demNguoc5s = 5;
                    this.boDemGiay = setInterval(() => {
                        this.demNguoc5s--;
                        if (this.demNguoc5s <= 0) {
                            this.demNguoc5s = 5;
                            this.kiemTraGiaoDich(true);
                        }
                    }, 1000);
                },

                kiemTraGiaoDich(laTuDong = false) {
                    if (!this.daTaoMa || !this.maNapTien) return;
                    if (!laTuDong) this.dangKiemTraNgay = true;

                    fetch(`/backend/api.php?action=kiem_tra_trang_thai_nap_vi&code=${encodeURIComponent(this.maNapTien)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.da_nap || (data.so_du && data.so_du > this.soDuHienTai)) {
                                if (this.boDemGiay) clearInterval(this.boDemGiay);
                                let soTienCong = (data.so_tien_nap) ? data.so_tien_nap : (data.so_du - this.soDuHienTai);
                                this.soTienVuaNap = soTienCong;
                                this.soDuMoiNhan = data.so_du || (this.soDuHienTai + soTienCong);
                                this.soDuHienTai = this.soDuMoiNhan;
                                this.popupNapThanhCong = true;
                            } else if (data.bi_tu_choi) {
                                if (this.boDemGiay) clearInterval(this.boDemGiay);
                                alert('⚠️ ' + data.thong_bao);
                            } else if (!laTuDong) {
                                alert('Hệ thống đang kiểm tra giao dịch chuyển khoản với ngân hàng. Nếu bạn vừa chuyển tiền, vui lòng đợi 5 - 10 giây để hệ thống tự động cộng tiền nhé!');
                            }
                        })
                        .catch(err => {
                            console.error('Lỗi kiểm tra nạp:', err);
                        })
                        .finally(() => {
                            if (!laTuDong) this.dangKiemTraNgay = false;
                        });
                },

                saoChep(noiDung, tenTruong = '') {
                    navigator.clipboard.writeText(noiDung).then(() => {
                        alert('Đã sao chép' + (tenTruong ? ' ' + tenTruong : '') + ': ' + noiDung);
                    });
                }
            };
        }
    </script>
</body>
</html>
