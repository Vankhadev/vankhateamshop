<?php
// ========================================================
// THANH HEADER & POPUP TIỆN ÍCH DÙNG CHUNG CHO TOÀN BỘ WEBSITE
// vankhateamshop - Đồng bộ 100% giao diện điều hướng, tra cứu usage, bảng xếp hạng
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo) && file_exists(__DIR__ . '/../../backend/db.php')) {
    require_once __DIR__ . '/../../backend/db.php';
}

if (!defined('SHOP_NAME') && file_exists(__DIR__ . '/../../backend/config.php')) {
    require_once __DIR__ . '/../../backend/config.php';
}

// Tự động phân giải đường dẫn tương đối chuẩn xác
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isClientDir = (strpos($scriptName, '/client/') !== false);
$rootPfx = $isClientDir ? '../' : './';
$clientPfx = $isClientDir ? './' : 'client/';
$backendPfx = $isClientDir ? '../../backend/' : '../backend/';

// Lấy thông tin người dùng nếu đã đăng nhập
if (!empty($_SESSION['user_id']) && (!isset($userHienTai) || !is_array($userHienTai)) && isset($pdo)) {
    try {
        $stmtHdr = $pdo->prepare("SELECT id, name, email, balance, role, avatar FROM `users` WHERE `id` = ?");
        $stmtHdr->execute([$_SESSION['user_id']]);
        $userHienTai = $stmtHdr->fetch();
    } catch (Exception $e) {
        $userHienTai = null;
    }
}
$daDangNhap = !empty($userHienTai);

// Lấy top 10 thành viên cho bảng xếp hạng
if ((!isset($topUsers) || !is_array($topUsers)) && isset($pdo)) {
    try {
        $topUsers = $pdo->query("
            SELECT u.name, u.email, SUM(o.total_amount) AS tong_chi_tieu 
            FROM `orders` o 
            JOIN `users` u ON o.user_id = u.id 
            WHERE o.status = 'COMPLETED' 
            GROUP BY u.id 
            ORDER BY tong_chi_tieu DESC 
            LIMIT 10
        ")->fetchAll();
    } catch (Exception $e) {
        $topUsers = [];
    }
}

$tenWebsiteHeader = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_name', defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn') : (defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn');
$tenNganHeader = trim(explode(' - ', $tenWebsiteHeader)[0]);
$soZaloHeader = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_zalo', defined('SHOP_ZALO') ? SHOP_ZALO : '0904045075') : (defined('SHOP_ZALO') ? SHOP_ZALO : '0904045075');
$linkTeleHeader = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_telegram', defined('SHOP_TELEGRAM') ? SHOP_TELEGRAM : 'https://t.me/vankhashop') : (defined('SHOP_TELEGRAM') ? SHOP_TELEGRAM : 'https://t.me/vankhashop');
?>

<!-- KHỐI HEADER ĐIỀU HƯỚNG DÙNG CHUNG (KÈM POPUP MODALS) -->
<div x-data="khoDieuHuongHeader()" 
     @mo-tra-cuu.window="moPopupKiemTraKey = true"
     @mo-xep-hang.window="moPopupBangXepHang = true"
     @mo-trang-thai.window="moPopupStatusModel = true"
     class="relative w-full">

    <!-- THANH HEADER CHÍNH -->
    <header class="sticky top-0 z-50 bg-[#0F172A]/95 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-[1440px] w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3">
                
                <!-- Logo Shop -->
                <a href="/" class="flex items-center gap-2.5 shrink-0 mr-1 xl:mr-4">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-black text-base shadow-lg shadow-blue-500/20 shrink-0">
                        VK
                    </div>
                    <div class="hidden sm:block shrink-0">
                        <span class="text-base font-bold text-white tracking-wide block leading-tight whitespace-nowrap"><?= htmlspecialchars($tenNganHeader) ?></span>
                        <span class="text-[9px] text-gray-400 font-semibold tracking-wider uppercase block leading-tight whitespace-nowrap">Cổng API AI Tự Động</span>
                    </div>
                </a>

                <!-- Menu điều hướng trung tâm (Đồng bộ mọi trang) -->
                <nav class="hidden lg:flex items-center gap-3 xl:gap-5 text-xs xl:text-sm font-medium">
                    <a href="/" class="text-white hover:text-blue-400 transition font-bold whitespace-nowrap">Trang Chủ</a>
                    <a href="/san-pham" class="text-gray-300 hover:text-white transition whitespace-nowrap">Sản Phẩm</a>
                    <a href="/phan-mem" class="text-gray-300 hover:text-emerald-400 transition flex items-center gap-1.5 font-medium whitespace-nowrap">
                        <i class="fas fa-cubes text-emerald-400 text-xs"></i>
                        <span>Phần Mềm</span>
                    </a>

                    <!-- DROPDOWN 1: QUẢN LÝ API -->
                    <div class="relative" @click.away="moMenuApi = false">
                        <button @click="moMenuApi = !moMenuApi; moMenuHD = false" class="text-gray-300 hover:text-white transition flex items-center gap-1.5 focus:outline-none whitespace-nowrap">
                            <span>Quản lý API</span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform" :class="moMenuApi ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="moMenuApi" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute left-0 mt-2 w-52 bg-[#1E293B] border border-gray-700/80 rounded-2xl shadow-2xl p-2 z-50 space-y-1 text-xs font-semibold" style="display: none;">
                            <a href="/san-pham" @click="moMenuApi = false" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-tag text-blue-400 w-4 text-center"></i>
                                <span>Bảng giá Model</span>
                            </a>
                            <a href="javascript:void(0)" @click="moMenuApi = false; moPopupKiemTraKey = true" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-chart-line text-green-400 w-4 text-center"></i>
                                <span>Kiểm tra usage</span>
                            </a>
                            <a href="<?= $daDangNhap ? '/user/nap-tien' : '/login' ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-coins text-amber-400 w-4 text-center"></i>
                                <span>Nạp Credit API</span>
                            </a>
                            <a href="https://status.gwai.cloud/" target="_blank" rel="noopener noreferrer" @click="moMenuApi = false" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-wave-square text-purple-400 w-4 text-center"></i>
                                <span>Trạng thái Model</span>
                                <i class="fas fa-arrow-up-right-from-square text-[9px] text-gray-500 ml-auto"></i>
                            </a>
                        </div>
                    </div>

                    <!-- DROPDOWN 2: HƯỚNG DẪN -->
                    <div class="relative" @click.away="moMenuHD = false">
                        <button @click="moMenuHD = !moMenuHD; moMenuApi = false" class="text-gray-300 hover:text-white transition flex items-center gap-1.5 focus:outline-none whitespace-nowrap">
                            <span>Hướng dẫn</span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform" :class="moMenuHD ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="moMenuHD" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute left-0 mt-2 w-52 bg-[#1E293B] border border-gray-700/80 rounded-2xl shadow-2xl p-2 z-50 space-y-1 text-xs font-semibold" style="display: none;">
                            <a href="/docs" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-handshake text-amber-400 w-4 text-center"></i>
                                <span>Tài Liệu Buyer API</span>
                            </a>
                            <a href="/user/api-docs" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-laptop-code text-blue-400 w-4 text-center"></i>
                                <span>Kết Nối Cursor IDE</span>
                            </a>
                            <a href="/chinh-sach" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 transition whitespace-nowrap">
                                <i class="fas fa-shield-halved text-green-400 w-4 text-center"></i>
                                <span>Chính Sách Bảo Hành</span>
                            </a>
                        </div>
                    </div>

                    <!-- BẢNG XẾP HẠNG -->
                    <a href="javascript:void(0)" @click="moPopupBangXepHang = true" class="text-gray-300 hover:text-amber-400 transition flex items-center gap-1.5 font-medium whitespace-nowrap">
                        <i class="fas fa-trophy text-amber-400"></i>
                        <span>Bảng Xếp Hạng</span>
                    </a>
                </nav>

                <!-- Khu vực Người dùng & Công cụ bên phải -->
                <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                    <!-- Nút Tra Cứu Key: hiển thị từ tablet sm trở lên -->
                    <button @click="moPopupKiemTraKey = true" class="hidden sm:inline-flex px-3 py-1.5 rounded-xl border border-gray-700 bg-gray-800/80 text-xs font-semibold text-gray-200 hover:bg-gray-700 hover:text-white transition items-center gap-1.5 shadow-sm whitespace-nowrap">
                        <i class="fas fa-chart-line text-blue-400"></i>
                        <span>Tra Cứu Key</span>
                    </button>

                    <?php if ($daDangNhap): ?>
                        <!-- Ví tiền thành viên: Luôn hiển thị gọn gàng trên cả Mobile và Desktop -->
                        <a href="/user/nap-tien" title="Bấm để nạp thêm tiền vào ví" class="px-2 sm:px-2.5 py-1 sm:py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 font-bold text-[11px] sm:text-xs transition flex items-center gap-1 whitespace-nowrap shadow-sm">
                            <i class="fas fa-wallet text-emerald-400 text-xs"></i>
                            <span class="hidden sm:inline">Ví: </span>
                            <span><?= number_format($userHienTai['balance'] ?? 0, 0, ',', '.') ?>đ</span>
                        </a>

                        <?php if (!empty($userHienTai['role']) && $userHienTai['role'] === 'admin'): ?>
                            <!-- Nút tắt Admin: Chỉ hiển thị trên Desktop lg -->
                            <a href="/admin/dashboard" class="hidden lg:inline-flex text-amber-400 hover:text-amber-300 transition text-xs font-bold px-2.5 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 items-center gap-1 whitespace-nowrap shadow-sm">
                                <i class="fas fa-shield-halved text-amber-400"></i>
                                <span>Admin</span>
                            </a>
                        <?php endif; ?>

                        <!-- Thông tin cá nhân & Đăng xuất: Chỉ hiển thị trên Desktop lg -->
                        <div class="hidden lg:flex items-center gap-2 bg-gray-800/80 border border-gray-700 rounded-xl px-2.5 py-1 shadow-sm shrink-0">
                            <?php if (!empty($userHienTai['avatar'])): ?>
                                <img src="<?= htmlspecialchars($userHienTai['avatar']) ?>" class="w-6 h-6 rounded-full object-cover shrink-0">
                            <?php else: ?>
                                <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                    <?= mb_substr($userHienTai['name'] ?? 'U', 0, 1) ?>
                                </div>
                            <?php endif; ?>
                            <a href="/user/dashboard" class="text-xs font-bold text-white hover:text-blue-400 max-w-[90px] truncate whitespace-nowrap">
                                <?= htmlspecialchars($userHienTai['name']) ?>
                            </a>
                            <a href="/logout" title="Đăng xuất" class="text-gray-400 hover:text-red-400 text-xs pl-0.5">
                                <i class="fas fa-right-from-bracket"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Chưa đăng nhập -> Nút đăng nhập Google trên Desktop -->
                        <a href="/login" class="hidden sm:inline-flex px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-xs font-bold text-white transition items-center gap-1.5 shadow-md shadow-blue-600/30 whitespace-nowrap">
                            <i class="fab fa-google text-white text-xs"></i>
                            <span>Đăng Nhập</span>
                        </a>
                    <?php endif; ?>

                    <!-- NÚT HAMBURGER MOBILE (Hiển thị trên Mobile & Tablet < lg) -->
                    <button @click="moMenuMobile = !moMenuMobile" class="lg:hidden p-2 rounded-xl text-gray-300 hover:text-white hover:bg-gray-800 focus:outline-none transition border border-gray-800 shrink-0 flex items-center justify-center w-9 h-9" aria-label="Menu">
                        <i class="fas" :class="moMenuMobile ? 'fa-times text-base' : 'fa-bars text-base'"></i>
                    </button>
                </div>

            </div>
        </div>

        <!-- MENU MOBILE DRAWER (RESPONSIVE CHUYÊN NGHIỆP CHO ĐIỆN THOẠI) -->
        <div x-show="moMenuMobile" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="lg:hidden bg-[#0F172A] border-b border-gray-800 px-4 py-4 space-y-3.5 shadow-2xl max-h-[85vh] overflow-y-auto" style="display: none;">
            
            <?php if ($daDangNhap): ?>
                <!-- Khối User Profile trên Mobile -->
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($userHienTai['avatar'])): ?>
                            <img src="<?= htmlspecialchars($userHienTai['avatar']) ?>" class="w-10 h-10 rounded-full object-cover border border-gray-700">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm">
                                <?= mb_substr($userHienTai['name'] ?? 'U', 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <div class="overflow-hidden">
                            <div class="text-xs font-bold text-white truncate max-w-[150px]"><?= htmlspecialchars($userHienTai['name']) ?></div>
                            <div class="text-[10px] text-gray-400 truncate max-w-[150px]"><?= htmlspecialchars($userHienTai['email']) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <?php if (!empty($userHienTai['role']) && $userHienTai['role'] === 'admin'): ?>
                            <a href="/admin/dashboard" @click="moMenuMobile = false" class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[11px] font-bold">
                                <i class="fas fa-shield-halved"></i> Admin
                            </a>
                        <?php endif; ?>
                        <a href="/logout" title="Đăng xuất" class="p-2 rounded-lg bg-red-600/10 text-red-400 hover:bg-red-600/20 text-xs">
                            <i class="fas fa-right-from-bracket"></i>
                        </a>
                    </div>
                </div>

                <!-- Số dư ví & Nạp ngay -->
                <div class="bg-emerald-950/30 border border-emerald-800/40 rounded-2xl p-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400 font-medium">Số dư ví của bạn</div>
                            <div class="text-sm font-bold text-emerald-400"><?= number_format($userHienTai['balance'] ?? 0, 0, ',', '.') ?>đ</div>
                        </div>
                    </div>
                    <a href="/user/nap-tien" @click="moMenuMobile = false" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-md shadow-emerald-600/30 flex items-center gap-1">
                        <i class="fas fa-plus-circle"></i> Nạp Ngay
                    </a>
                </div>
            <?php else: ?>
                <!-- Nút đăng nhập nổi bật cho khách trên Mobile -->
                <a href="/login" @click="moMenuMobile = false" class="w-full py-3 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-center font-bold text-xs shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                    <i class="fab fa-google text-sm"></i> Đăng Nhập / Tạo Tài Khoản
                </a>
            <?php endif; ?>

            <!-- Lưới liên kết điều hướng Mobile -->
            <div class="grid grid-cols-2 gap-2 text-xs font-semibold">
                <a href="/" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-blue-500 transition">
                    <i class="fas fa-home text-blue-400 w-4 text-center"></i> <span>Trang Chủ</span>
                </a>
                <a href="/san-pham" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-blue-500 transition">
                    <i class="fas fa-cubes text-purple-400 w-4 text-center"></i> <span>Sản Phẩm API</span>
                </a>
                <a href="/phan-mem" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-emerald-500 transition">
                    <i class="fas fa-laptop-code text-emerald-400 w-4 text-center"></i> <span>Phần Mềm</span>
                </a>
                <a href="https://status.gwai.cloud/" target="_blank" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-green-500 transition">
                    <i class="fas fa-wave-square text-green-400 w-4 text-center"></i> <span>Status Model</span>
                </a>
                <a href="javascript:void(0)" @click="moMenuMobile = false; moPopupKiemTraKey = true" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-blue-500 transition">
                    <i class="fas fa-chart-line text-blue-400 w-4 text-center"></i> <span>Tra Cứu Key</span>
                </a>
                <a href="javascript:void(0)" @click="moMenuMobile = false; moPopupBangXepHang = true" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-amber-500 transition">
                    <i class="fas fa-trophy text-amber-400 w-4 text-center"></i> <span>Bảng Xếp Hạng</span>
                </a>
                <a href="/docs" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-amber-500 transition">
                    <i class="fas fa-book-bookmark text-amber-400 w-4 text-center"></i> <span>Tài Liệu API</span>
                </a>
                <a href="/user/api-docs" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 border border-gray-800 text-white hover:border-blue-500 transition">
                    <i class="fas fa-terminal text-cyan-400 w-4 text-center"></i> <span>Kết Nối Cursor</span>
                </a>
            </div>

            <?php if ($daDangNhap): ?>
                <!-- Mục tài khoản nhanh -->
                <div class="border-t border-gray-800/80 pt-3">
                    <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider mb-2">Quản Lý Cá Nhân</div>
                    <div class="grid grid-cols-2 gap-2 text-xs font-semibold">
                        <a href="/user/don-hang" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-900 text-gray-300 hover:text-white border border-gray-800">
                            <i class="fas fa-receipt text-blue-400"></i> <span>Đơn hàng đã mua</span>
                        </a>
                        <a href="/user/bien-dong-so-du" @click="moMenuMobile = false" class="flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-900 text-gray-300 hover:text-white border border-gray-800">
                            <i class="fas fa-clock-rotate-left text-purple-400"></i> <span>Biến động số dư</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

                <div class="flex items-center justify-between text-[11px] text-gray-400 pt-2 border-t border-gray-800/60">
                    <span>Hỗ trợ nhanh:</span>
                    <div class="flex items-center gap-3">
                        <a href="https://zalo.me/<?= htmlspecialchars($soZaloHeader) ?>" target="_blank" class="text-blue-400 font-bold hover:underline"><i class="fas fa-comment-dots mr-1"></i>Zalo: <?= htmlspecialchars($soZaloHeader) ?></a>
                    </div>
                </div>
            </div>

        </div>
    </header>

    <!-- MODAL 1: TRA CỨU USAGE CỦA KEY -->
    <div x-show="moPopupKiemTraKey" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-md w-full p-6 relative shadow-2xl">
            <button @click="moPopupKiemTraKey = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
                <i class="fas fa-chart-line text-blue-400"></i> Tra Cứu Usage Của Key
            </h3>
            <p class="text-xs text-gray-400 mb-4">Dán API Key đã mua để xem số request còn lại và ngày hết hạn.</p>
            
            <div class="space-y-3">
                <input type="text" x-model="oNhapKey" placeholder="sk-..." class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500 font-mono">
                <button @click="kiemTraKey()" :disabled="dangKiemTraKey" class="w-full py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center justify-center gap-2">
                    <i class="fas fa-circle-notch fa-spin" x-show="dangKiemTraKey" style="display: none;"></i>
                    <span>Kiểm Tra Ngay</span>
                </button>
            </div>

            <div x-show="ketQuaTraCuu" class="mt-4 p-3 bg-gray-900 rounded-xl text-xs space-y-1.5 border border-gray-800" style="display: none;">
                <div class="flex justify-between"><span class="text-gray-400">Trạng thái:</span><span class="text-green-400 font-bold" x-text="ketQuaTraCuu?.trang_thai"></span></div>
                <div class="flex justify-between"><span class="text-gray-400">Đã dùng:</span><span class="text-white font-semibold" x-text="ketQuaTraCuu?.da_dung"></span></div>
                <div class="flex justify-between"><span class="text-gray-400">Còn lại:</span><span class="text-amber-400 font-bold" x-text="ketQuaTraCuu?.con_lai"></span></div>
                <div class="flex justify-between"><span class="text-gray-400">Hạn sử dụng:</span><span class="text-gray-300" x-text="ketQuaTraCuu?.han_dung"></span></div>
            </div>
        </div>
    </div>

    <!-- MODAL 2: BẢNG XẾP HẠNG THÀNH VIÊN & ĐẠI LÝ -->
    <div x-show="moPopupBangXepHang" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-sm">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Bảng Xếp Hạng Đại Lý & Khách Hàng</h3>
                        <p class="text-[11px] text-gray-400">Top 10 thành viên có mức tiêu dùng uy tín nhất</p>
                    </div>
                </div>
                <button @click="moPopupBangXepHang = false" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                <?php if (empty($topUsers)): ?>
                    <div class="text-center py-6 text-xs text-gray-500">Chưa có dữ liệu xếp hạng</div>
                <?php else: ?>
                    <?php foreach ($topUsers as $rank => $tu): 
                        $emailAn = preg_replace('/(?<=..).(?=.*@)/u', '*', $tu['email']);
                    ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-[#0B0F19] border border-gray-800 text-xs">
                            <div class="flex items-center space-x-3">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs <?= $rank === 0 ? 'bg-amber-500 text-black' : ($rank === 1 ? 'bg-slate-300 text-black' : ($rank === 2 ? 'bg-amber-700 text-white' : 'bg-gray-800 text-gray-400')) ?>">
                                    <?= $rank + 1 ?>
                                </div>
                                <div>
                                    <div class="font-bold text-white"><?= htmlspecialchars($tu['name']) ?></div>
                                    <div class="text-[11px] text-gray-500 font-mono"><?= htmlspecialchars($emailAn) ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-amber-400 font-mono"><?= number_format($tu['tong_chi_tieu'], 0, ',', '.') ?>đ</div>
                                <span class="text-[10px] text-gray-400">Đã hoàn thành</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL 3: TRẠNG THÁI MODEL (MODEL HEALTH & STATUS) -->
    <div x-show="moPopupStatusModel" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
        <div class="bg-[#111827] border border-gray-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-green-500/20 text-green-400 flex items-center justify-center font-bold text-sm">
                        <i class="fas fa-wave-square"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Trạng Thái Các Model AI</h3>
                        <p class="text-[11px] text-gray-400">Giám sát thời gian thực & độ trễ mạng</p>
                    </div>
                </div>
                <button @click="moPopupStatusModel = false" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between p-3 rounded-xl bg-[#0B0F19] border border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="font-bold text-white font-mono">claude-3-5-sonnet</span>
                    </div>
                    <span class="text-[11px] font-mono text-green-400">99.9% (320ms)</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-[#0B0F19] border border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="font-bold text-white font-mono">cursor-small</span>
                    </div>
                    <span class="text-[11px] font-mono text-green-400">99.8% (180ms)</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-[#0B0F19] border border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="font-bold text-white font-mono">deepseek-r1</span>
                    </div>
                    <span class="text-[11px] font-mono text-green-400">99.7% (450ms)</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-[#0B0F19] border border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="font-bold text-white font-mono">gpt-4o</span>
                    </div>
                    <span class="text-[11px] font-mono text-green-400">99.9% (290ms)</span>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function khoDieuHuongHeader() {
    return {
        moMenuMobile: false,
        moMenuApi: false,
        moMenuHD: false,
        moPopupKiemTraKey: false,
        moPopupStatusModel: false,
        moPopupBangXepHang: false,
        oNhapKey: '',
        ketQuaTraCuu: null,
        dangKiemTraKey: false,

        async kiemTraKey() {
            if (!this.oNhapKey || this.oNhapKey.trim() === '') {
                alert('Vui lòng nhập API Key cần tra cứu');
                return;
            }
            this.dangKiemTraKey = true;
            try {
                let res = await fetch('/backend/api.php?action=tra_cuu_key&key=' + encodeURIComponent(this.oNhapKey.trim()));
                let data = await res.json();
                if (data.thanh_cong) {
                    this.ketQuaTraCuu = data;
                } else {
                    alert(data.thong_bao || 'Không tìm thấy thông tin của key này');
                }
            } catch (e) {
                alert('Không thể kết nối đến máy chủ để tra cứu');
            } finally {
                this.dangKiemTraKey = false;
            }
        }
    }
}
</script>
