<?php
// ========================================================
// THANH SIDEBAR MENU QUẢN TRỊ BÊN TRÁI (ADMIN SIDEBAR)
// vankhateamshop - Đồng bộ toàn bộ các trang Admin Panel
// Chuẩn Responsive Desktop (Cố định) & Mobile/Tablet (Off-canvas Drawer)
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

$uriHienTai = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminName = $_SESSION['user_name'] ?? 'Admin VanKha';
$adminEmail = $_SESSION['user_email'] ?? 'admin@tiemai.io.vn';
?>
<!-- LỚP PHỦ BACKDROP KHI MỞ MENU TRÊN MOBILE -->
<div id="admin-sidebar-backdrop" 
     onclick="dongAdminSidebar()" 
     class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 transition-opacity duration-300 opacity-0 pointer-events-none lg:hidden">
</div>

<!-- KHỐI SIDEBAR TRÁI CỐ ĐỊNH TRÊN DESKTOP / TRƯỢT OFF-CANVAS TRÊN MOBILE -->
<aside id="admin-sidebar" 
       class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-[#0F172A] border-r border-gray-800 flex flex-col h-screen overflow-y-auto transform -translate-x-full transition-transform duration-300 ease-in-out lg:static lg:w-64 lg:h-screen lg:sticky lg:top-0 lg:translate-x-0 lg:z-auto shrink-0 shadow-2xl lg:shadow-none">
    
    <!-- Logo & Nhãn hiệu Quản trị + Nút Đóng trên Mobile -->
    <div class="h-16 flex items-center justify-between px-5 border-b border-gray-800/80 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-600 to-orange-500 flex items-center justify-center text-white font-bold shadow-lg shadow-amber-600/20 shrink-0">
                <i class="fas fa-shield-halved text-sm"></i>
            </div>
            <div>
                <span class="text-sm font-black text-white tracking-wide block">VanKha<span class="text-amber-500">Admin</span></span>
                <span class="text-[9px] text-gray-400 font-semibold uppercase tracking-wider block">Cổng Quản Trị Hệ Thống</span>
            </div>
        </div>
        <!-- Nút Đóng Sidebar trên Mobile -->
        <button type="button" onclick="dongAdminSidebar()" class="lg:hidden p-2 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800/80 transition focus:outline-none" aria-label="Đóng Menu">
            <i class="fas fa-xmark text-lg"></i>
        </button>
    </div>

    <!-- Thông tin Quản trị viên nhanh -->
    <div class="p-3.5 mx-3 my-3 rounded-2xl bg-gray-900/80 border border-gray-800 flex items-center gap-3 shrink-0">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-700 text-white flex items-center justify-center font-bold text-sm shrink-0">
            <?= mb_substr($adminName, 0, 1) ?>
        </div>
        <div class="overflow-hidden">
            <div class="text-xs font-bold text-white truncate"><?= htmlspecialchars($adminName) ?></div>
            <div class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Admin Tối Cao
            </div>
        </div>
    </div>

    <!-- Danh sách các mục Menu điều hướng (Phân nhóm chuyên nghiệp) -->
    <div class="flex-1 px-3 space-y-4 text-xs font-medium pb-6 overflow-y-auto">
        
        <!-- NHÓM 1: TỔNG QUAN -->
        <div>
            <div class="px-3 mb-1.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Tổng Quan</div>
            <div class="space-y-0.5">
                <a href="/admin/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/dashboard' || $uriHienTai === '/admin') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-chart-pie w-4 text-center"></i>
                    <span>Bảng Điều Khiển</span>
                </a>
                <a href="/admin/thong-ke" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/thong-ke') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-chart-line w-4 text-center"></i>
                    <span>Báo Cáo & Thống Kê</span>
                </a>
            </div>
        </div>

        <!-- NHÓM 2: KINH DOANH & KHO SẢN PHẨM -->
        <div>
            <div class="px-3 mb-1.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Kinh Doanh & Sản Phẩm</div>
            <div class="space-y-0.5">
                <a href="/admin/don-hang" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/don-hang') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-receipt w-4 text-center"></i>
                    <span>Quản Lý Đơn Hàng</span>
                </a>
                <a href="/admin/san-pham" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/san-pham') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-cubes w-4 text-center"></i>
                    <span>Gói Sản Phẩm API</span>
                </a>
                <a href="/admin/danh-muc" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/danh-muc') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-folder-tree w-4 text-center"></i>
                    <span>Danh Mục API</span>
                </a>
                <a href="/admin/dai-ly" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/dai-ly') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-handshake w-4 text-center"></i>
                    <span>Quản Lý Đại Lý API</span>
                </a>
                <a href="/admin/ma-giam-gia" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/ma-giam-gia') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-ticket w-4 text-center"></i>
                    <span>Mã Giảm Giá (Coupon)</span>
                </a>
            </div>
        </div>

        <!-- NHÓM 3: KHÁCH HÀNG & TÀI CHÍNH -->
        <div>
            <div class="px-3 mb-1.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Khách Hàng & Ví Tiền</div>
            <div class="space-y-0.5">
                <a href="/admin/thanh-vien" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/thanh-vien') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-users w-4 text-center"></i>
                    <span>Quản Lý Thành Viên</span>
                </a>
                <a href="/admin/bien-dong-so-du" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/bien-dong-so-du') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-clock-rotate-left w-4 text-center"></i>
                    <span>Biến Động Số Dư</span>
                </a>
                <a href="/admin/ngan-hang" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/ngan-hang') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-building-columns w-4 text-center"></i>
                    <span>Ngân Hàng ACB & VietQR</span>
                </a>
            </div>
        </div>

        <!-- NHÓM 4: HẠ TẦNG & CÀI ĐẶT -->
        <div>
            <div class="px-3 mb-1.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Hạ Tầng & Hệ Thống</div>
            <div class="space-y-0.5">
                <a href="/admin/nha-cung-cap" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/nha-cung-cap') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-network-wired w-4 text-center"></i>
                    <span>Nhà Cung Cấp Nguồn</span>
                </a>
                <a href="/admin/api-logs" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/api-logs') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-bolt w-4 text-center"></i>
                    <span>Nhật Ký Gọi API</span>
                </a>
                <a href="/admin/thong-bao" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/thong-bao') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-bullhorn w-4 text-center"></i>
                    <span>Thông Báo Website</span>
                </a>
                <a href="/admin/ho-tro" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/ho-tro') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-headset w-4 text-center"></i>
                    <span>Hỗ Trợ & Khiếu Nại</span>
                </a>
                <a href="/admin/nhat-ky" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/nhat-ky') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-book w-4 text-center"></i>
                    <span>Nhật Ký Bảo Mật</span>
                </a>
                <a href="/admin/cai-dat" class="flex items-center gap-3 px-3 py-2 rounded-xl transition <?= ($uriHienTai === '/admin/cai-dat') ? 'bg-amber-500/15 text-amber-400 font-bold border border-amber-500/30' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                    <i class="fas fa-gear w-4 text-center"></i>
                    <span>Cài Đặt Hệ Thống</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Chân trang Sidebar: Xem web & Đăng xuất -->
    <div class="p-3 border-t border-gray-800/80 bg-gray-950/60 space-y-1 shrink-0">
        <a href="/" target="_blank" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition text-xs font-semibold">
            <i class="fas fa-arrow-up-right-from-square text-blue-400 w-4 text-center"></i>
            <span>Xem Website Shop</span>
        </a>
        <a href="/logout" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-red-400 hover:text-red-300 hover:bg-red-950/40 transition text-xs font-semibold">
            <i class="fas fa-right-from-bracket w-4 text-center"></i>
            <span>Đăng Xuất</span>
        </a>
    </div>
</aside>

<!-- SCRIPT ĐIỀU KHIỂN SIDEBAR TRÊN MOBILE -->
<script>
function moAdminSidebar() {
    var sb = document.getElementById('admin-sidebar');
    var bd = document.getElementById('admin-sidebar-backdrop');
    if (sb) {
        sb.classList.remove('-translate-x-full');
        sb.classList.add('translate-x-0');
    }
    if (bd) {
        bd.classList.remove('opacity-0', 'pointer-events-none');
        bd.classList.add('opacity-100', 'pointer-events-auto');
    }
    document.body.classList.add('overflow-hidden', 'lg:overflow-auto');
}

function dongAdminSidebar() {
    var sb = document.getElementById('admin-sidebar');
    var bd = document.getElementById('admin-sidebar-backdrop');
    if (sb) {
        sb.classList.add('-translate-x-full');
        sb.classList.remove('translate-x-0');
    }
    if (bd) {
        bd.classList.add('opacity-0', 'pointer-events-none');
        bd.classList.remove('opacity-100', 'pointer-events-auto');
    }
    document.body.classList.remove('overflow-hidden', 'lg:overflow-auto');
}

function toggleAdminSidebar() {
    var sb = document.getElementById('admin-sidebar');
    if (sb && sb.classList.contains('translate-x-0')) {
        dongAdminSidebar();
    } else {
        moAdminSidebar();
    }
}

// Tự động đóng menu trên mobile khi click vào một link bên trong sidebar
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('#admin-sidebar a');
    links.forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 1024) {
                dongAdminSidebar();
            }
        });
    });
});
</script>
