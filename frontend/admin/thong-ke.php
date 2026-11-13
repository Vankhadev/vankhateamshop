<?php
// ========================================================
// TRANG BÁO CÁO THỐNG KÊ DOANH THU & LỢI NHUẬN (ADMIN)
// vankhateamshop - Phân tích lợi nhuận ròng, giá vốn & biểu đồ tăng trưởng
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

// Bắt buộc quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

// 1. Thống kê tổng quan
$tongDoanhThu = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM `orders` WHERE `status` = 'COMPLETED'")->fetchColumn();
$tongSoDon = (int)$pdo->query("SELECT COUNT(*) FROM `orders` WHERE `status` = 'COMPLETED'")->fetchColumn();

// Ước tính giá vốn từ mapping sản phẩm
$tongGiaVon = (float)$pdo->query("
    SELECT COALESCE(SUM(spm.cost_price), 0)
    FROM `orders` o
    JOIN `supplier_product_mappings` spm ON o.product_id = spm.product_id
    WHERE o.status = 'COMPLETED'
")->fetchColumn();

$loiNhuanRong = max(0, $tongDoanhThu - $tongGiaVon);
$tySuatLoiNhuan = $tongDoanhThu > 0 ? round(($loiNhuanRong / $tongDoanhThu) * 100, 1) : 0;

// 2. Thống kê doanh thu 7 ngày gần nhất cho biểu đồ Chart.js
$stmt7Ngay = $pdo->query("
    SELECT DATE(created_at) as ngay, COALESCE(SUM(total_amount), 0) as doanh_thu
    FROM `orders`
    WHERE `status` = 'COMPLETED' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY ngay ASC
");
$dulieu7Ngay = $stmt7Ngay->fetchAll();

$nhanNgay = [];
$cotTien = [];
for ($i = 6; $i >= 0; $i--) {
    $ngayFormat = date('Y-m-d', strtotime("-$i days"));
    $ngayHienThi = date('d/m', strtotime("-$i days"));
    $nhanNgay[] = $ngayHienThi;

    $timThay = false;
    foreach ($dulieu7Ngay as $d) {
        if ($d['ngay'] === $ngayFormat) {
            $cotTien[] = (float)$d['doanh_thu'];
            $timThay = true;
            break;
        }
    }
    if (!$timThay) {
        $cotTien[] = 0;
    }
}

// 3. Top 5 sản phẩm bán chạy nhất
$topSanPham = $pdo->query("
    SELECT p.name, COUNT(o.id) as so_luong, SUM(o.total_amount) as tong_tien
    FROM `orders` o
    JOIN `products` p ON o.product_id = p.id
    WHERE o.status = 'COMPLETED'
    GROUP BY p.id
    ORDER BY so_luong DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống Kê Doanh Thu & Lợi Nhuận - Quản Trị <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

    <div class="flex min-h-screen">
        <!-- SIDEBAR MENU QUẢN TRỊ BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            
            <!-- THANH TOPBAR QUẢN TRỊ -->
            <header class="h-16 bg-[#0F172A] border-b border-gray-800 px-4 sm:px-6 flex items-center justify-between shrink-0 sticky top-0 z-30">
                <div class="flex items-center gap-2 sm:gap-3 overflow-hidden">
                    <button type="button" onclick="moAdminSidebar()" class="lg:hidden p-2 -ml-1 rounded-xl bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none shrink-0" aria-label="Mở Menu">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <h1 class="text-sm sm:text-base font-bold text-white flex items-center gap-2 truncate">
                        <i class="fas fa-chart-line text-amber-500"></i>
                        <span class="truncate">Báo Cáo & Thống Kê Kinh Doanh</span>
                    </h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a href="/" target="_blank" class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-semibold text-gray-200 transition flex items-center gap-1.5 border border-gray-700">
                        <i class="fas fa-globe text-blue-400"></i>
                        <span class="hidden sm:inline">Xem Website Shop</span>
                    </a>
                    <a href="/logout" class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-red-600/10 hover:bg-red-600/20 text-red-400 border border-red-500/20 text-xs font-semibold transition flex items-center gap-1.5">
                        <i class="fas fa-right-from-bracket"></i>
                        <span class="hidden sm:inline">Thoát</span>
                    </a>
                </div>
            </header>

            <!-- NỘI DUNG QUẢN TRỊ CHÍNH -->
            <main class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto">

        <!-- 1. CÁC THẺ CHỈ SỐ TÀI CHÍNH -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tổng Doanh Thu</span>
                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-400 flex items-center justify-center text-sm">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2"><?= number_format($tongDoanhThu, 0, ',', '.') ?> VNĐ</div>
                <span class="text-[11px] text-green-400 mt-1 block"><i class="fas fa-circle-check"></i> Đã hoàn thành <?= $tongSoDon ?> đơn</span>
            </div>

            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Giá Vốn Nhập Kho</span>
                    <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 flex items-center justify-center text-sm">
                        <i class="fas fa-cart-flatbed"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-white mt-2"><?= number_format($tongGiaVon, 0, ',', '.') ?> VNĐ</div>
                <span class="text-[11px] text-gray-400 mt-1 block">Chi trả sang Nhà Cung Cấp</span>
            </div>

            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Lợi Nhuận Ròng</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-400 mt-2"><?= number_format($loiNhuanRong, 0, ',', '.') ?> VNĐ</div>
                <span class="text-[11px] text-amber-300 mt-1 block">Thực nhận về tài khoản</span>
            </div>

            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tỷ Suất Lợi Nhuận</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center text-sm">
                        <i class="fas fa-percent"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-blue-400 mt-2"><?= $tySuatLoiNhuan ?>%</div>
                <span class="text-[11px] text-blue-300 mt-1 block">Biên lợi nhuận trung bình</span>
            </div>
        </div>

        <!-- 2. BIỂU ĐỒ DOANH THU 7 NGÀY & TOP SẢN PHẨM -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cột Biểu đồ Chart.js (2/3 chiều rộng) -->
            <div class="lg:col-span-2 bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fas fa-chart-area text-blue-400"></i> Xu Hướng Doanh Thu 7 Ngày Gần Nhất
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Biểu đồ thể hiện biến động dòng tiền thanh toán thành công theo ngày</p>
                    </div>
                </div>
                <div class="h-72 w-full">
                    <canvas id="bieuDoDoanhThu"></canvas>
                </div>
            </div>

            <!-- Cột Top Sản Phẩm Bán Chạy (1/3 chiều rộng) -->
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-fire text-amber-400"></i> Top Sản Phẩm Bán Chạy
                </h2>
                <div class="space-y-4">
                    <?php if (empty($topSanPham)): ?>
                        <div class="text-center py-8 text-xs text-gray-500">Chưa có dữ liệu bán hàng</div>
                    <?php else: ?>
                        <?php foreach ($topSanPham as $idx => $sp): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-[#0F172A] border border-gray-800">
                                <div class="flex items-center space-x-3">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs <?= $idx === 0 ? 'bg-amber-500 text-black' : 'bg-gray-800 text-gray-400' ?>">
                                        <?= $idx + 1 ?>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-white"><?= htmlspecialchars($sp['name']) ?></div>
                                        <div class="text-[11px] text-gray-400"><?= $sp['so_luong'] ?> lượt mua</div>
                                    </div>
                                </div>
                                <div class="text-xs font-bold text-green-400">
                                    <?= number_format($sp['tong_tien'], 0, ',', '.') ?>đ
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

                </main>

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>
    <script>
    // Khởi tạo biểu đồ Chart.js
    const ctx = document.getElementById('bieuDoDoanhThu').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($nhanNgay) ?>,
            datasets: [{
                label: 'Doanh Thu (VNĐ)',
                data: <?= json_encode($cotTien) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#60A5FA',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: {
                        color: '#9CA3AF',
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + 'đ';
                        }
                    }
                },
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#9CA3AF' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.parsed.y.toLocaleString('vi-VN') + ' VNĐ';
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>
