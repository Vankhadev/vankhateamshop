<?php
// ========================================================
// TRANG QUẢN TRỊ VIÊN (ADMIN DASHBOARD) - CHỦ SHOP
// vankhateamshop - Thống kê doanh thu, kiểm tra số dư ví Nhà Cung Cấp
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';
require_once __DIR__ . '/../../backend/supplier_api.php';

// Thiết lập tiêu đề bảo mật HTTP
thietLapBaoMatHeaders();

// Bắt buộc phải đăng nhập quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 1. Thống kê tiền bạc từ bảng orders
$tongDoanhThu = (float)$pdo->query("SELECT SUM(total_amount) FROM `orders` WHERE `status` = 'COMPLETED'")->fetchColumn();
$tongSoDon = (int)$pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
$donThanhCong = (int)$pdo->query("SELECT COUNT(*) FROM `orders` WHERE `status` = 'COMPLETED'")->fetchColumn();
$tongThanhVien = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

// 2. Lấy thông tin nhà cung cấp đang kích hoạt
$supplier = $pdo->query("SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1")->fetch();

// 3. Đơn hàng gần đây
$recentOrders = $pdo->query("
    SELECT o.*, p.name AS product_name 
    FROM `orders` o
    LEFT JOIN `products` p ON o.product_id = p.id
    ORDER BY o.id DESC 
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị Hệ Thống - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungAdmin()" class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ TRÊN CÙNG -->
    <header class="bg-[#0F172A] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Trang Quản Trị Hệ Thống</span>
                </div>
            </div>

            <!-- Menu quản trị -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/dai-ly" class="text-gray-300 hover:text-white"><i class="fas fa-handshake mr-1"></i> Đại Lý</a>
                <a href="/admin/api-logs" class="text-gray-300 hover:text-white"><i class="fas fa-bolt mr-1"></i> Logs API</a>
                <a href="/admin/danh-muc" class="text-gray-300 hover:text-white"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
                <a href="/admin/thanh-vien" class="text-gray-300 hover:text-white"><i class="fas fa-users mr-1"></i> Thành Viên</a>
                <a href="/admin/ngan-hang" class="text-gray-300 hover:text-white"><i class="fas fa-building-columns mr-1"></i> Ngân Hàng</a>
                <a href="/admin/nha-cung-cap" class="text-gray-300 hover:text-white"><i class="fas fa-network-wired mr-1"></i> Nhà Cung Cấp</a>
                <a href="/admin/ma-giam-gia" class="text-gray-300 hover:text-white"><i class="fas fa-ticket mr-1"></i> Mã Giảm Giá</a>
                <a href="/admin/ho-tro" class="text-gray-300 hover:text-white"><i class="fas fa-headset mr-1"></i> Hỗ Trợ</a>
                <a href="/admin/thong-bao" class="text-gray-300 hover:text-white"><i class="fas fa-bullhorn mr-1"></i> Thông Báo</a>
                <a href="/admin/nhat-ky" class="text-gray-300 hover:text-white"><i class="fas fa-shield-virus mr-1"></i> Nhật Ký</a>
                <a href="/admin/cai-dat" class="text-gray-300 hover:text-white"><i class="fas fa-gear mr-1"></i> Cài Đặt</a>
                <a href="/logout" class="text-red-400 hover:text-red-300 ml-2">
                    <i class="fas fa-right-from-bracket mr-1"></i> Đăng Xuất
                </a>
            </nav>
        </div>
    </header>

    <!-- NỘI DUNG QUẢN TRỊ CHÍNH -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <!-- THẺ CẢNH BÁO SỐ DƯ VÍ NGUỒN NHÀ CUNG CẤP -->
        <div class="bg-gradient-to-r from-blue-950/60 to-indigo-950/60 border border-blue-800/80 rounded-2xl p-5 mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center text-2xl shrink-0">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white">Số Dư Ví Nhà Cung Cấp API</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-900/60 text-green-400 border border-green-700/60">Live API</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Tiền vốn để hệ thống tự động gọi mua gói Cursor/Claude cho khách khi họ chuyển khoản</p>
                </div>
            </div>

            <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-end">
                <div class="text-left md:text-right">
                    <span class="text-xs text-gray-400 block">Số dư hiện tại:</span>
                    <span class="text-2xl font-black text-amber-400" x-text="soDuNguonText">
                        <?= number_format($supplier['balance'] ?? 0, 0, ',', '.') ?> VNĐ
                    </span>
                </div>
                <button @click="kiemTraSoDuNguon()" :disabled="dangKiemTra" class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                    <i class="fas fa-rotate" :class="dangKiemTra ? 'fa-spin' : ''"></i>
                    <span>Kiểm Tra Live</span>
                </button>
            </div>
        </div>

        <!-- 4 THẺ THỐNG KÊ DOANH THU & ĐƠN HÀNG -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Thẻ 1 -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400">Tổng Doanh Thu</span>
                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-400 flex items-center justify-center text-sm">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-green-400 mt-2">
                    <?= number_format($tongDoanhThu, 0, ',', '.') ?>đ
                </div>
                <div class="text-[11px] text-gray-500 mt-1">Đã cộng từ thanh toán VietQR thành công</div>
            </div>

            <!-- Thẻ 2 -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400">Tổng Số Đơn Hàng</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center text-sm">
                        <i class="fas fa-cart-shopping"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2">
                    <?= number_format($tongSoDon) ?>
                </div>
                <div class="text-[11px] text-gray-500 mt-1">Bao gồm tất cả đơn tạo ra</div>
            </div>

            <!-- Thẻ 3 -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400">Đơn Hoàn Thành</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm">
                        <i class="fas fa-circle-check"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-400 mt-2">
                    <?= number_format($donThanhCong) ?>
                </div>
                <div class="text-[11px] text-gray-500 mt-1">Đã tự động giao Key thành công</div>
            </div>

            <!-- Thẻ 4 -->
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400">Khách Hàng Đăng Ký</span>
                    <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center text-sm">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-purple-400 mt-2">
                    <?= number_format($tongThanhVien) ?>
                </div>
                <div class="text-[11px] text-gray-500 mt-1">Thành viên có tài khoản trên web</div>
            </div>
        </div>

        <!-- BẢNG ĐƠN HÀNG GẦN ĐÂY -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-xl mb-8">
            <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-amber-400"></i> 10 Đơn Hàng Mới Nhất
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Theo dõi thời gian thực khách quét mã VietQR nhận Key</p>
                </div>
                <a href="/admin/don-hang" class="text-xs font-bold text-blue-400 hover:text-blue-300">
                    Xem Tất Cả Đơn &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3 px-4">Mã Đơn / Thời Gian</th>
                            <th class="py-3 px-4">Sản Phẩm</th>
                            <th class="py-3 px-4">Email Khách Hàng</th>
                            <th class="py-3 px-4">Số Tiền</th>
                            <th class="py-3 px-4 text-center">Trạng Thái</th>
                            <th class="py-3 px-4">Dữ Liệu Key Đã Giao</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-500">Chưa có đơn hàng nào phát sinh trên hệ thống.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($recentOrders as $order): ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-amber-400"><?= htmlspecialchars($order['order_code']) ?></div>
                                    <div class="text-[10px] text-gray-500"><?= date('H:i d/m/Y', strtotime($order['created_at'])) ?></div>
                                </td>
                                <td class="py-3 px-4 font-bold text-white"><?= htmlspecialchars($order['product_name'] ?? 'Gói đã xóa') ?></td>
                                <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($order['customer_email']) ?></td>
                                <td class="py-3 px-4 font-bold text-green-400"><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($order['status'] === 'COMPLETED'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">Thành công</span>
                                    <?php elseif ($order['status'] === 'PENDING'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">Chờ tiền</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-950 text-blue-400 border border-blue-800"><?= htmlspecialchars($order['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="max-w-[200px] truncate font-mono text-[11px] text-gray-300 bg-gray-950 px-2 py-1 rounded border border-gray-800">
                                        <?= htmlspecialchars($order['delivered_data'] ?? 'Chưa giao') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="bg-gray-950 border-t border-gray-900 py-6 text-center text-xs text-gray-500">
        &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
    </footer>

    <script>
        function ungDungAdmin() {
            return {
                dangKiemTra: false,
                soDuNguonText: '<?= number_format($supplier['balance'] ?? 0, 0, ',', '.') ?> VNĐ',

                // Hàm gọi API backend kiểm tra số dư ví nhà cung cấp live
                kiemTraSoDuNguon() {
                    this.dangKiemTra = true;
                    fetch('/backend/api.php?action=kiem_tra_vi_nguon')
                        .then(res => res.json())
                        .then(data => {
                            this.dangKiemTra = false;
                            if (data.thanh_cong) {
                                this.soDuNguonText = data.so_du_chu;
                                alert('Số dư ví nhà cung cấp hiện tại: ' + data.so_du_chu);
                            } else {
                                alert('Thông báo: ' + (data.thong_bao || 'Không thể kiểm tra'));
                            }
                        })
                        .catch(() => {
                            this.dangKiemTra = false;
                            alert('Không thể kết nối đến file backend/api.php');
                        });
                }
            }
        }
    </script>
</body>
</html>
