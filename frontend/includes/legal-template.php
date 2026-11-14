<?php
// ========================================================
// TEMPLATE DÙNG CHUNG CHO CÁC TRANG CHÍNH SÁCH PHÁP LÝ
// Tiệm AI - tiemai.io.vn
// ========================================================

$danhSachTrangPhapLy = [
    'dieu-khoan-su-dung' => [
        'nhan' => 'Điều khoản sử dụng',
        'link' => '/dieu-khoan-su-dung',
        'icon' => 'fa-file-contract'
    ],
    'quy-che-hoat-dong' => [
        'nhan' => 'Quy chế hoạt động',
        'link' => '/quy-che-hoat-dong',
        'icon' => 'fa-gavel'
    ],
    'chinh-sach-thanh-toan' => [
        'nhan' => 'Chính sách thanh toán',
        'link' => '/chinh-sach-thanh-toan',
        'icon' => 'fa-credit-card'
    ],
    'chinh-sach-cung-cap-san-pham' => [
        'nhan' => 'Chính sách cung cấp sản phẩm',
        'link' => '/chinh-sach-cung-cap-san-pham',
        'icon' => 'fa-truck-fast'
    ],
    'bao-hanh-hoan-tien' => [
        'nhan' => 'Bảo hành & hoàn tiền',
        'link' => '/bao-hanh-hoan-tien',
        'icon' => 'fa-shield-halved'
    ],
    'dieu-khoan-dai-ly' => [
        'nhan' => 'Điều khoản đại lý',
        'link' => '/dieu-khoan-dai-ly',
        'icon' => 'fa-handshake'
    ],
    'chinh-sach-bao-mat' => [
        'nhan' => 'Chính sách bảo mật',
        'link' => '/chinh-sach-bao-mat',
        'icon' => 'fa-user-shield'
    ],
];

$slugHienTai = $slugHienTai ?? 'dieu-khoan-su-dung';
$tieuDeTrang = $tieuDeTrang ?? 'Chính sách pháp lý';
$moTaTrang = $moTaTrang ?? 'Quy định pháp lý tại Tiệm AI.';
$mauSac = $mauSac ?? 'blue';
$iconTrang = $iconTrang ?? 'fa-file-contract';
$cacMuc = $cacMuc ?? [];
$tenWebsite = 'Tiệm AI';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tieuDeTrang) ?> - <?= $tenWebsite ?> (tiemai.io.vn)</title>
    <meta name="description" content="<?= htmlspecialchars($moTaTrang) ?>">
    <link rel="canonical" href="https://tiemai.io.vn/<?= htmlspecialchars($slugHienTai) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">
    <?php require_once __DIR__ . '/header.php'; ?>

    <main class="max-w-6xl mx-auto px-4 py-10 w-full flex-1" x-data="{ dangMoMenu: false }">
        <!-- Tiêu đề trang -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold uppercase tracking-wider mb-2">
                <i class="fas fa-scale-balanced"></i> Trung tâm Pháp Lý Tiệm AI
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1"><?= htmlspecialchars($tieuDeTrang) ?></h1>
            <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-2xl mx-auto leading-relaxed"><?= htmlspecialchars($moTaTrang) ?></p>
        </div>

        <div class="grid lg:grid-cols-[260px_1fr] gap-6 items-start">
            <!-- Sidebar điều hướng 7 trang pháp lý -->
            <aside class="bg-gray-900/90 border border-gray-800 rounded-3xl p-4 lg:sticky lg:top-24 shadow-xl backdrop-blur-sm space-y-4">
                <button type="button" @click="dangMoMenu = !dangMoMenu" class="lg:hidden w-full flex items-center justify-between text-sm font-bold text-white mb-0 py-2">
                    <span class="flex items-center gap-2"><i class="fas fa-list text-blue-400"></i> Danh mục pháp lý</span>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform" :class="dangMoMenu ? 'rotate-180' : ''"></i>
                </button>
                <div :class="dangMoMenu ? 'block' : 'hidden'" class="lg:block space-y-1.5">
                    <div class="text-[12px] font-black text-white uppercase px-3 py-1 tracking-wider">PHÁP LÝ</div>
                    <?php foreach ($danhSachTrangPhapLy as $ma => $muc): ?>
                        <a href="<?= htmlspecialchars($muc['link']) ?>" 
                           class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-2xl text-xs transition-all duration-200 <?= $ma === $slugHienTai ? 'bg-blue-600/20 text-white border border-blue-500/40 font-bold shadow-sm' : 'text-gray-400 hover:text-white hover:bg-gray-800/60 font-medium' ?>">
                            <i class="fas <?= htmlspecialchars($muc['icon']) ?> w-4 text-center <?= $ma === $slugHienTai ? 'text-blue-400' : 'text-gray-500' ?>"></i>
                            <span><?= htmlspecialchars($muc['nhan']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Thẻ liên hệ nhanh -->
                <div class="pt-4 border-t border-gray-800 space-y-2 hidden lg:block">
                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider px-1">Kênh hỗ trợ</div>
                    <a href="https://t.me/Vankhateam" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-xs text-sky-400 hover:text-sky-300 transition px-1 py-1">
                        <i class="fab fa-telegram w-4 text-center"></i> <span>@Vankhateam</span>
                    </a>
                    <a href="mailto:vankhaqc@gmail.com" class="flex items-center gap-2 text-xs text-amber-400 hover:text-amber-300 transition px-1 py-1">
                        <i class="fas fa-envelope w-4 text-center"></i> <span>vankhaqc@gmail.com</span>
                    </a>
                    <a href="https://zalo.me/0847154088" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-xs text-emerald-400 hover:text-emerald-300 transition px-1 py-1">
                        <i class="fas fa-comment-dots w-4 text-center"></i> <span>Zalo: 0847154088</span>
                    </a>
                </div>
            </aside>

            <!-- Nội dung chi tiết các điều khoản -->
            <article class="space-y-5">
                <?php foreach ($cacMuc as $so => $phan): ?>
                    <section class="bg-gray-900/80 border border-gray-800 rounded-3xl p-6 sm:p-7 shadow-xl hover:border-gray-700/60 transition-colors">
                        <div class="flex items-center gap-3 text-<?= htmlspecialchars($mauSac) ?>-400 mb-4 pb-3 border-b border-gray-800/80">
                            <span class="w-8 h-8 rounded-xl bg-<?= htmlspecialchars($mauSac) ?>-500/10 border border-<?= htmlspecialchars($mauSac) ?>-500/20 flex items-center justify-center font-bold text-xs">
                                <?= sprintf('%02d', $so + 1) ?>
                            </span>
                            <h2 class="text-base sm:text-lg font-bold text-white"><?= htmlspecialchars($phan['title']) ?></h2>
                        </div>
                        <ul class="space-y-2.5 text-xs sm:text-sm text-gray-300 leading-relaxed list-disc pl-5">
                            <?php foreach ($phan['items'] as $noiDung): ?>
                                <li><?= htmlspecialchars($noiDung) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>

                <!-- Box liên hệ chính thức của Tiệm AI -->
                <div class="bg-gradient-to-r from-blue-950/70 via-indigo-950/50 to-purple-950/50 border border-blue-800/60 rounded-3xl p-6 sm:p-8 text-center shadow-2xl space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center mx-auto text-blue-400 text-xl">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h2 class="text-base sm:text-lg font-bold text-white">Bạn cần hỗ trợ về pháp lý hoặc giao dịch?</h2>
                    <p class="text-xs text-gray-300 max-w-xl mx-auto leading-relaxed">
                        Đội ngũ hỗ trợ Tiệm AI luôn sẵn sàng tiếp nhận và giải quyết thắc mắc của bạn qua các kênh liên lạc chính thức:
                    </p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-left pt-2 max-w-2xl mx-auto">
                        <div class="bg-gray-900/80 border border-gray-800 p-3.5 rounded-2xl flex items-center gap-3">
                            <i class="fas fa-envelope text-amber-400 text-lg"></i>
                            <div class="overflow-hidden">
                                <div class="text-[10px] text-gray-400 uppercase font-bold">Email chính thức</div>
                                <div class="text-xs font-bold text-white truncate">vankhaqc@gmail.com</div>
                            </div>
                        </div>
                        <a href="https://t.me/Vankhateam" target="_blank" rel="noopener noreferrer" class="bg-gray-900/80 border border-gray-800 hover:border-blue-500/40 p-3.5 rounded-2xl flex items-center gap-3 transition group">
                            <i class="fab fa-telegram text-blue-400 text-lg group-hover:scale-110 transition-transform"></i>
                            <div class="overflow-hidden">
                                <div class="text-[10px] text-gray-400 uppercase font-bold">Telegram hỗ trợ</div>
                                <div class="text-xs font-bold text-white truncate">@Vankhateam</div>
                            </div>
                        </a>
                        <a href="https://zalo.me/0847154088" target="_blank" rel="noopener noreferrer" class="bg-gray-900/80 border border-gray-800 hover:border-blue-500/40 p-3.5 rounded-2xl flex items-center gap-3 transition group">
                            <i class="fas fa-comment-dots text-emerald-400 text-lg group-hover:scale-110 transition-transform"></i>
                            <div class="overflow-hidden">
                                <div class="text-[10px] text-gray-400 uppercase font-bold">Zalo / Hotline</div>
                                <div class="text-xs font-bold text-white truncate">0847154088</div>
                            </div>
                        </a>
                    </div>
                </div>

                <p class="text-[11px] text-gray-500 text-center pt-2">
                    © <?= date('Y') ?> <strong>tiemai.io.vn</strong>. Nội dung chính sách được bảo hộ và cập nhật lần cuối vào ngày <?= date('d/m/Y') ?>.
                </p>
            </article>
        </div>
    </main>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>