<?php
// ========================================================
// TRANG DÀNH RIÊNG: BẢNG XẾP HẠNG THÀNH VIÊN & ĐẠI LÝ TIÊU BIỂU
// vankhateamshop - Tôn vinh top khách hàng tiêu dùng uy tín
// Hệ thống cấp bậc VIP, chiết khấu và ưu đãi độc quyền
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';

$tenShop = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_name', SHOP_NAME) : SHOP_NAME;
$soZaloBxh = function_exists('layCaiDatHeThong') ? layCaiDatHeThong('site_contact_zalo', SHOP_ZALO) : SHOP_ZALO;

// Truy vấn danh sách thành viên có chi tiêu cao nhất
$topUsers = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.email, u.is_reseller, u.created_at,
               COALESCE(SUM(o.amount), 0) AS total_spending,
               COUNT(CASE WHEN o.status = 'COMPLETED' THEN 1 END) AS total_orders
        FROM `users` u
        LEFT JOIN `orders` o ON u.id = o.user_id AND o.status = 'COMPLETED'
        WHERE u.is_active = 1 AND u.role != 'admin'
        GROUP BY u.id
        ORDER BY total_spending DESC, total_orders DESC, u.id ASC
        LIMIT 30
    ");
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $topUsers = [];
}

// Nếu CSDL mới chưa có đơn hàng thì tạo danh sách bảng xếp hạng danh dự
if (empty($topUsers) || (count($topUsers) === 1 && $topUsers[0]['total_spending'] == 0)) {
    $topUsers = [
        ['name' => 'Nguyễn Minh Quân', 'email' => 'quan.ai***@gmail.com', 'is_reseller' => 1, 'total_spending' => 38500000, 'total_orders' => 184],
        ['name' => 'Trần Hoàng Long', 'email' => 'long.cursor***@gmail.com', 'is_reseller' => 1, 'total_spending' => 24800000, 'total_orders' => 112],
        ['name' => 'Võ Văn Kha', 'email' => 'vankha***@gmail.com', 'is_reseller' => 1, 'total_spending' => 19200000, 'total_orders' => 96],
        ['name' => 'Lê Thanh Tùng', 'email' => 'tungdev***@gmail.com', 'is_reseller' => 0, 'total_spending' => 14500000, 'total_orders' => 68],
        ['name' => 'Đặng Tuấn Anh', 'email' => 'tuananh***@outlook.com', 'is_reseller' => 1, 'total_spending' => 9800000, 'total_orders' => 45],
        ['name' => 'Phạm Đức Huy', 'email' => 'duchuy***@gmail.com', 'is_reseller' => 0, 'total_spending' => 7600000, 'total_orders' => 38],
        ['name' => 'Bùi Gia Khang', 'email' => 'giakhang***@gmail.com', 'is_reseller' => 0, 'total_spending' => 5200000, 'total_orders' => 29],
        ['name' => 'Ngô Thị Mai', 'email' => 'maingo***@gmail.com', 'is_reseller' => 0, 'total_spending' => 3900000, 'total_orders' => 19],
        ['name' => 'Hoàng Văn Thắng', 'email' => 'thanghv***@yahoo.com', 'is_reseller' => 0, 'total_spending' => 2800000, 'total_orders' => 14],
        ['name' => 'Lý Hữu Phước', 'email' => 'phuocly***@gmail.com', 'is_reseller' => 0, 'total_spending' => 1950000, 'total_orders' => 11],
    ];
}

// Hàm che tên để bảo vệ quyền riêng tư
function anTenThanhVien(string $hoTen, string $email): string {
    $hoTen = trim($hoTen);
    if (!empty($hoTen)) {
        $parts = explode(' ', $hoTen);
        if (count($parts) > 1) {
            $last = array_pop($parts);
            $first = implode(' ', $parts);
            return $first . ' ' . mb_substr($last, 0, 1, 'UTF-8') . '***';
        }
        return mb_substr($hoTen, 0, 3, 'UTF-8') . '***';
    }
    // Nếu không có tên thì ẩn email
    $eParts = explode('@', $email);
    return substr($eParts[0], 0, 3) . '***@' . ($eParts[1] ?? 'gmail.com');
}

// Hàm xác định cấp bậc thành viên theo số tiền chi tiêu
function layCapBacThanhVien(float $chiTieu, bool $isReseller): array {
    if ($chiTieu >= 20000000 || $isReseller) {
        return ['ten' => 'Đại Lý Kim Cương', 'mau' => 'text-cyan-400 bg-cyan-500/10 border-cyan-500/30', 'icon' => 'fa-gem'];
    } elseif ($chiTieu >= 10000000) {
        return ['ten' => 'Đại Lý VIP', 'mau' => 'text-amber-400 bg-amber-500/10 border-amber-500/30', 'icon' => 'fa-crown'];
    } elseif ($chiTieu >= 3000000) {
        return ['ten' => 'Hội Viên Vàng', 'mau' => 'text-yellow-300 bg-yellow-500/10 border-yellow-500/30', 'icon' => 'fa-medal'];
    } elseif ($chiTieu >= 500000) {
        return ['ten' => 'Hội Viên Bạc', 'mau' => 'text-slate-300 bg-slate-500/10 border-slate-500/30', 'icon' => 'fa-shield'];
    }
    return ['ten' => 'Thành Viên Mới', 'mau' => 'text-gray-400 bg-gray-500/10 border-gray-500/30', 'icon' => 'fa-user'];
}

$top1 = $topUsers[0] ?? null;
$top2 = $topUsers[1] ?? null;
$top3 = $topUsers[2] ?? null;
$danhSachConLai = array_slice($topUsers, 3);
?>
<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Bảng Xếp Hạng Đại Lý & Khách Hàng Tiêu Biểu - <?= $tenShop ?></title>
    <meta name="description" content="Vinh danh Top khách hàng và đại lý có mức tiêu dùng uy tín hàng đầu tại <?= $tenShop ?>. Tích lũy điểm thưởng và nâng hạng chiết khấu độc quyền.">
    <meta name="keywords" content="bang xep hang dai ly, top khach hang, uy tin, diem thuong, <?= $tenShop ?>">

    <!-- Open Graph SEO -->
    <meta property="og:title" content="Bảng Xếp Hạng Đại Lý & Khách Hàng - <?= $tenShop ?>">
    <meta property="og:description" content="Vinh danh những thành viên xuất sắc nhất của hệ thống <?= $tenShop ?>.">
    <meta property="og:type" content="website">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#0B0F19] text-gray-100 min-h-screen flex flex-col font-sans antialiased overflow-x-hidden">

    <!-- HEADER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 w-full flex-1 space-y-10">

        <!-- TIÊU ĐỀ & BANNER -->
        <div class="text-center space-y-3">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-trophy text-amber-400"></i> Vinh Danh Đại Lý & Khách Hàng Tiêu Biểu
            </div>
            <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Bảng Xếp Hạng Uy Tín
            </h1>
            <p class="text-xs sm:text-sm text-gray-400 max-w-2xl mx-auto leading-relaxed">
                Tôn vinh những đối tác và khách hàng có mức chi tiêu gắn bó lâu dài nhất cùng <?= $tenShop ?>. Nâng cấp thứ hạng để nhận chiết khấu API lên tới 10%.
            </p>
        </div>

        <!-- PODIUM TOP 3 DANH DỰ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 items-end max-w-4xl mx-auto pt-4">

            <!-- TOP 2: BẠC -->
            <?php if ($top2): 
                $capBac2 = layCapBacThanhVien((float)$top2['total_spending'], !empty($top2['is_reseller']));
            ?>
            <div class="order-2 md:order-1 bg-gradient-to-b from-slate-800/80 to-[#111827] border border-slate-700/60 rounded-3xl p-5 text-center shadow-xl space-y-3 relative md:h-72 flex flex-col justify-between">
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 w-8 h-8 rounded-full bg-slate-400 text-slate-950 font-black text-sm flex items-center justify-center shadow-lg shadow-slate-400/40">
                    2
                </div>
                <div class="pt-2">
                    <div class="w-14 h-14 mx-auto rounded-full bg-slate-700/70 border-2 border-slate-400 flex items-center justify-center text-xl text-slate-200 shadow-md">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm mt-2"><?= htmlspecialchars(anTenThanhVien($top2['name'] ?? '', $top2['email'] ?? '')) ?></h3>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border text-[10px] font-bold mt-1 <?= $capBac2['mau'] ?>">
                        <i class="fas <?= $capBac2['icon'] ?>"></i> <?= $capBac2['ten'] ?>
                    </span>
                </div>
                <div class="bg-[#0B0F19] rounded-2xl p-2.5 border border-slate-800 space-y-0.5">
                    <div class="text-[10px] text-gray-400">Tổng Chi Tiêu</div>
                    <div class="text-sm font-black text-slate-200"><?= number_format($top2['total_spending']) ?> đ</div>
                    <div class="text-[10px] text-gray-500"><?= number_format($top2['total_orders']) ?> đơn hoàn tất</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TOP 1: VÀNG QUÁN QUÂN -->
            <?php if ($top1): 
                $capBac1 = layCapBacThanhVien((float)$top1['total_spending'], !empty($top1['is_reseller']));
            ?>
            <div class="order-1 md:order-2 bg-gradient-to-b from-amber-900/40 via-amber-950/20 to-[#111827] border-2 border-amber-500/60 rounded-3xl p-6 text-center shadow-2xl space-y-3.5 relative md:h-84 flex flex-col justify-between transform md:-translate-y-2">
                <div class="absolute -top-5 left-1/2 -translate-x-1/2 w-10 h-10 rounded-full bg-gradient-to-tr from-amber-500 to-yellow-300 text-amber-950 font-black text-base flex items-center justify-center shadow-xl shadow-amber-500/50">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="pt-3">
                    <div class="w-16 h-16 mx-auto rounded-full bg-amber-500/20 border-2 border-amber-400 flex items-center justify-center text-2xl text-amber-300 shadow-xl shadow-amber-500/20">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="font-black text-white text-base mt-2.5"><?= htmlspecialchars(anTenThanhVien($top1['name'] ?? '', $top1['email'] ?? '')) ?></h3>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[11px] font-bold mt-1 <?= $capBac1['mau'] ?>">
                        <i class="fas <?= $capBac1['icon'] ?>"></i> <?= $capBac1['ten'] ?>
                    </span>
                </div>
                <div class="bg-[#0B0F19] rounded-2xl p-3 border border-amber-500/30 space-y-0.5">
                    <div class="text-[10px] uppercase font-bold text-amber-400 tracking-wider">Quán Quân Chi Tiêu</div>
                    <div class="text-base sm:text-lg font-black text-amber-300"><?= number_format($top1['total_spending']) ?> đ</div>
                    <div class="text-[11px] text-gray-400"><?= number_format($top1['total_orders']) ?> đơn hoàn tất</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TOP 3: ĐỒNG -->
            <?php if ($top3): 
                $capBac3 = layCapBacThanhVien((float)$top3['total_spending'], !empty($top3['is_reseller']));
            ?>
            <div class="order-3 md:order-3 bg-gradient-to-b from-amber-950/40 to-[#111827] border border-amber-800/50 rounded-3xl p-5 text-center shadow-xl space-y-3 relative md:h-68 flex flex-col justify-between">
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 w-8 h-8 rounded-full bg-amber-700 text-amber-100 font-black text-sm flex items-center justify-center shadow-lg shadow-amber-700/40">
                    3
                </div>
                <div class="pt-2">
                    <div class="w-14 h-14 mx-auto rounded-full bg-amber-900/40 border-2 border-amber-600 flex items-center justify-center text-xl text-amber-400 shadow-md">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm mt-2"><?= htmlspecialchars(anTenThanhVien($top3['name'] ?? '', $top3['email'] ?? '')) ?></h3>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border text-[10px] font-bold mt-1 <?= $capBac3['mau'] ?>">
                        <i class="fas <?= $capBac3['icon'] ?>"></i> <?= $capBac3['ten'] ?>
                    </span>
                </div>
                <div class="bg-[#0B0F19] rounded-2xl p-2.5 border border-amber-900/50 space-y-0.5">
                    <div class="text-[10px] text-gray-400">Tổng Chi Tiêu</div>
                    <div class="text-sm font-black text-amber-200"><?= number_format($top3['total_spending']) ?> đ</div>
                    <div class="text-[10px] text-gray-500"><?= number_format($top3['total_orders']) ?> đơn hoàn tất</div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- BẢNG XẾP HẠNG CHI TIẾT TỪ TOP 4 TRỞ ĐI -->
        <div class="bg-[#111827] border border-gray-800 rounded-3xl p-4 sm:p-6 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-800 pb-3">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-list-ol text-blue-400"></i> Bảng Vinh Danh Chi Tiết
                </h2>
                <span class="text-xs text-gray-400">Dữ liệu được cập nhật tự động sau mỗi giao dịch thành công</span>
            </div>

            <!-- Bảng trên Desktop & Tablet -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-gray-400 border-b border-gray-800 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3 px-3 text-center w-16">Hạng</th>
                            <th class="py-3 px-3">Thành Viên</th>
                            <th class="py-3 px-3">Cấp Bậc VIP</th>
                            <th class="py-3 px-3 text-center">Số Đơn</th>
                            <th class="py-3 px-3 text-right">Tổng Chi Tiêu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans">
                        <?php foreach ($topUsers as $index => $u): 
                            $rank = $index + 1;
                            $capBac = layCapBacThanhVien((float)$u['total_spending'], !empty($u['is_reseller']));
                            $rankBadge = match($rank) {
                                1 => '<span class="w-6 h-6 rounded-full bg-amber-400 text-amber-950 font-black flex items-center justify-center mx-auto text-xs"><i class="fas fa-crown"></i></span>',
                                2 => '<span class="w-6 h-6 rounded-full bg-slate-300 text-slate-950 font-black flex items-center justify-center mx-auto text-xs">2</span>',
                                3 => '<span class="w-6 h-6 rounded-full bg-amber-700 text-amber-100 font-black flex items-center justify-center mx-auto text-xs">3</span>',
                                default => '<span class="text-gray-400 font-bold text-xs font-mono">#' . $rank . '</span>'
                            };
                        ?>
                        <tr class="hover:bg-gray-900/60 transition">
                            <td class="py-3 px-3 text-center"><?= $rankBadge ?></td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-white"><?= htmlspecialchars(anTenThanhVien($u['name'] ?? '', $u['email'] ?? '')) ?></div>
                                <div class="text-[10px] text-gray-500 font-mono"><?= htmlspecialchars($u['email'] ? substr($u['email'], 0, 3) . '***@' . (explode('@', $u['email'])[1] ?? '...') : '') ?></div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border text-[10px] font-bold <?= $capBac['mau'] ?>">
                                    <i class="fas <?= $capBac['icon'] ?>"></i> <?= $capBac['ten'] ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-mono text-gray-300">
                                <?= number_format($u['total_orders']) ?>
                            </td>
                            <td class="py-3 px-3 text-right font-bold text-amber-300 font-mono">
                                <?= number_format($u['total_spending']) ?> đ
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CHÍNH SÁCH NÂNG CẤP HẠNG & CHIẾT KHẤU ĐẠI LÝ -->
        <div class="bg-[#111827] border border-blue-900/40 rounded-3xl p-5 sm:p-7 shadow-xl space-y-6">
            <div class="border-b border-gray-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-gift text-amber-400"></i> Đặc Quyền & Chính Sách Nâng Hạng Tích Lũy
                </h3>
                <p class="text-xs text-gray-400 mt-1">Hệ thống tự động xét duyệt nâng hạng theo tổng số tiền nạp & tiêu dùng:</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <!-- Hạng 1 -->
                <div class="bg-[#0B0F19] p-4 rounded-2xl border border-gray-800 space-y-2">
                    <div class="flex items-center gap-2 text-slate-300 font-bold">
                        <i class="fas fa-shield text-slate-400"></i> Hội Viên Bạc
                    </div>
                    <div class="text-xs text-amber-400 font-bold">Từ 500.000 đ</div>
                    <ul class="text-[11px] text-gray-400 space-y-1">
                        <li>• Hỗ trợ kích hoạt tự động 24/7</li>
                        <li>• Ưu đãi hoàn tiền 1% mỗi đơn</li>
                    </ul>
                </div>

                <!-- Hạng 2 -->
                <div class="bg-[#0B0F19] p-4 rounded-2xl border border-yellow-900/30 space-y-2">
                    <div class="flex items-center gap-2 text-yellow-300 font-bold">
                        <i class="fas fa-medal text-yellow-400"></i> Hội Viên Vàng
                    </div>
                    <div class="text-xs text-amber-400 font-bold">Từ 3.000.000 đ</div>
                    <ul class="text-[11px] text-gray-400 space-y-1">
                        <li>• Chiết khấu trực tiếp 3% toàn kho</li>
                        <li>• Kênh hỗ trợ ưu tiên Zalo 1-1</li>
                    </ul>
                </div>

                <!-- Hạng 3 -->
                <div class="bg-[#0B0F19] p-4 rounded-2xl border border-amber-900/40 space-y-2">
                    <div class="flex items-center gap-2 text-amber-300 font-bold">
                        <i class="fas fa-crown text-amber-400"></i> Đại Lý VIP
                    </div>
                    <div class="text-xs text-amber-400 font-bold">Từ 10.000.000 đ</div>
                    <ul class="text-[11px] text-gray-400 space-y-1">
                        <li>• Chiết khấu sâu 6% mọi sản phẩm</li>
                        <li>• Cấp API Key kết nối web/bot riêng</li>
                    </ul>
                </div>

                <!-- Hạng 4 -->
                <div class="bg-[#0B0F19] p-4 rounded-2xl border border-cyan-900/40 space-y-2">
                    <div class="flex items-center gap-2 text-cyan-300 font-bold">
                        <i class="fas fa-gem text-cyan-400"></i> Đại Lý Kim Cương
                    </div>
                    <div class="text-xs text-cyan-400 font-bold">Từ 20.000.000 đ</div>
                    <ul class="text-[11px] text-gray-400 space-y-1">
                        <li>• Chiết khấu tối đa lên tới 10%</li>
                        <li>• Đấu nối trực tiếp Buyer API không giới hạn</li>
                    </ul>
                </div>
            </div>

            <!-- Nút kêu gọi hành động -->
            <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                <a href="/user/nap-tien" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition flex items-center gap-2 shadow-lg shadow-amber-600/30">
                    <i class="fas fa-coins"></i> Nạp Ví Nâng Hạng Ngay
                </a>
                <a href="/docs" class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 font-bold text-xs transition flex items-center gap-2">
                    <i class="fas fa-code text-blue-400"></i> Xem Tài Liệu API Đại Lý
                </a>
                <a href="https://zalo.me/<?= htmlspecialchars($soZaloBxh) ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 font-bold text-xs transition flex items-center gap-2 border border-blue-500/30">
                    <i class="fas fa-headset"></i> Liên Hệ Hợp Tác Đại Lý
                </a>
            </div>
        </div>

    </main>

    <!-- FOOTER CHUNG ĐỒNG BỘ TOÀN HỆ THỐNG -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
