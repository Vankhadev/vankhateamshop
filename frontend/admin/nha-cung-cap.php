<?php
// ========================================================
// TRANG QUẢN LÝ NHÀ CUNG CẤP API & KHO NGUỒN (ADMIN SUPPLIERS)
// vankhateamshop - Thêm/sửa nguồn API đối tác, cài đặt Key, xem số dư, xem kho và đồng bộ giá vốn & tồn kho
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';
require_once __DIR__ . '/../../backend/supplier_api.php';

thietLapBaoMatHeaders();

// Kiểm tra quyền Admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

$thongBao = '';
$loaiThongBao = '';

// Xử lý Thêm / Sửa Nhà Cung Cấp
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'them_nha_cung_cap') {
        $code = strtolower(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $apiUrl = rtrim(trim($_POST['api_url'] ?? ''), '/');
        $apiKey = trim($_POST['api_key'] ?? '');
        $minBalance = (float)($_POST['min_balance_alert'] ?? 200000);

        if (empty($code) || empty($name) || empty($apiUrl) || empty($apiKey)) {
            $thongBao = 'Vui lòng điền đầy đủ thông tin nhà cung cấp!';
            $loaiThongBao = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO `suppliers` (`code`, `name`, `api_url`, `api_key`, `balance`, `min_balance_alert`, `is_active`)
                    VALUES (?, ?, ?, ?, 0.00, ?, 1)
                ");
                $stmt->execute([$code, $name, $apiUrl, $apiKey, $minBalance]);
                $thongBao = 'Đã thêm nhà cung cấp mới thành công!';
                $loaiThongBao = 'success';
            } catch (Exception $e) {
                $thongBao = 'Lỗi thêm nhà cung cấp: ' . $e->getMessage();
                $loaiThongBao = 'error';
            }
        }
    } elseif ($action === 'sua_nha_cung_cap') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $apiUrl = rtrim(trim($_POST['api_url'] ?? ''), '/');
        $apiKey = trim($_POST['api_key'] ?? '');
        $minBalance = (float)($_POST['min_balance_alert'] ?? 200000);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE `suppliers` 
                SET `name` = ?, `api_url` = ?, `api_key` = ?, `min_balance_alert` = ?, `is_active` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([$name, $apiUrl, $apiKey, $minBalance, $isActive, $id]);
            $thongBao = 'Đã cập nhật nhà cung cấp thành công!';
            $loaiThongBao = 'success';
        } catch (Exception $e) {
            $thongBao = 'Lỗi cập nhật: ' . $e->getMessage();
            $loaiThongBao = 'error';
        }
    }
}

// Lấy danh sách tất cả các nhà cung cấp
$suppliers = $pdo->query("SELECT * FROM `suppliers` ORDER BY `id` ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Nhà Cung Cấp API - <?= SHOP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="ungDungNhaCungCap()" class="min-h-screen bg-[#0B0F19] text-[#E5E7EB] antialiased">

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
                        <i class="fas fa-network-wired text-amber-500"></i>
                        <span class="truncate">Cấu Hình Nhà Cung Cấp & Kho Nguồn</span>
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
            <main class="p-4 sm:p-6 lg:p-8 flex-1 max-w-7xl w-full mx-auto space-y-6">
        
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-bold text-white flex items-center gap-2">
                            <i class="fas fa-network-wired text-amber-500"></i> Các Nguồn Cung Cấp API Đối Tác
                        </h1>
                        <p class="text-xs text-gray-400 mt-1">Cài đặt kết nối máy chủ, API Key bí mật, xem tồn kho thực tế và 1-click nhập sản phẩm</p>
                    </div>
                    
                    <div class="flex items-center gap-2 flex-wrap">
                        <button @click="dongBoTonKho()" :disabled="dangDongBo" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-emerald-600/30">
                            <i class="fas fa-arrows-rotate" :class="dangDongBo ? 'fa-spin' : ''"></i>
                            <span x-text="dangDongBo ? 'Đang đồng bộ...' : 'Đồng Bộ Tồn Kho & Giá Vốn'"></span>
                        </button>
                        <button @click="moKhoNcc()" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-purple-600/30">
                            <i class="fas fa-boxes-stacked"></i> Xem Kho Hàng NCC
                        </button>
                        <button @click="moModalThem = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                            <i class="fas fa-plus"></i> Thêm Nguồn Mới
                        </button>
                    </div>
                </div>

                <?php if (!empty($thongBao)): ?>
                    <div class="p-4 rounded-xl flex items-center gap-3 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
                        <i class="fas <?= $loaiThongBao === 'success' ? 'fa-circle-check text-green-400' : 'fa-circle-exclamation text-red-400' ?> text-base"></i>
                        <span><?= htmlspecialchars($thongBao) ?></span>
                    </div>
                <?php endif; ?>

                <!-- DANH SÁCH NHÀ CUNG CẤP -->
                <div class="space-y-4">
                    <?php foreach ($suppliers as $s): ?>
                        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                            <div class="space-y-2 flex-1">
                                <div class="flex items-center gap-3">
                                    <span class="px-2.5 py-1 rounded-xl bg-blue-950 text-blue-400 border border-blue-800 font-mono text-xs font-bold">
                                        #<?= $s['id'] ?> - <?= htmlspecialchars($s['code']) ?>
                                    </span>
                                    <h3 class="text-base font-bold text-white"><?= htmlspecialchars($s['name']) ?></h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $s['is_active'] ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                                        <?= $s['is_active'] ? 'Đang hoạt động' : 'Tạm tắt' ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono pt-2">
                                    <div class="p-2.5 bg-gray-950 rounded-xl border border-gray-800 text-gray-300 truncate" title="<?= htmlspecialchars($s['api_url']) ?>">
                                        <span class="text-gray-500 font-sans">URL:</span> <?= htmlspecialchars($s['api_url']) ?>
                                    </div>
                                    <div class="p-2.5 bg-gray-950 rounded-xl border border-gray-800 text-amber-300 truncate" title="<?= htmlspecialchars($s['api_key']) ?>">
                                        <span class="text-gray-500 font-sans">Key:</span> <?= substr($s['api_key'], 0, 8) ?>••••••••<?= substr($s['api_key'], -4) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- KHỐI SỐ DƯ & HÀNH ĐỘNG -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full md:w-auto border-t md:border-t-0 border-gray-800 pt-4 md:pt-0">
                                <div class="text-left sm:text-right">
                                    <span class="text-xs text-gray-400 block">Số dư ví tại nguồn:</span>
                                    <span class="text-xl font-black text-green-400">
                                        <?= number_format($s['balance'], 0, ',', '.') ?> VNĐ
                                    </span>
                                    <span class="text-[10px] text-gray-500 block">Cảnh báo nếu dưới <?= number_format($s['min_balance_alert'], 0, ',', '.') ?>đ</span>
                                </div>

                                <div class="flex items-center gap-2 flex-wrap">
                                    <button @click="kiemTraSoDu(<?= $s['id'] ?>)" class="px-3 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                                        <i class="fas fa-rotate"></i> Check Ví
                                    </button>
                                    <button @click="moKhoNcc(<?= $s['id'] ?>)" class="px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-purple-600/30">
                                        <i class="fas fa-boxes-stacked"></i> Kho Hàng
                                    </button>
                                    <button @click="moSua(<?= htmlspecialchars(json_encode($s)) ?>)" class="px-3 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition border border-gray-700">
                                        <i class="fas fa-pen-to-square"></i> Sửa
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- MODAL XEM KHO HÀNG & 1-CLICK NHẬP SẢN PHẨM TỪ NHÀ CUNG CẤP -->
                <div x-show="moModalKho" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition>
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-5xl w-full p-6 relative shadow-2xl space-y-4 max-h-[92vh] flex flex-col">
                        <button @click="moModalKho = false" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
                            <i class="fas fa-times text-lg"></i>
                        </button>

                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-gray-800 pb-3">
                            <div>
                                <h3 class="text-base font-bold text-white flex items-center gap-2">
                                    <i class="fas fa-boxes-stacked text-purple-400"></i> Kho Hàng Thời Gian Thực Nhà Cung Cấp (<span x-text="khoData.supplier_name || "></span>)
                                </h3>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Tổng cộng <strong class="text-white" x-text="khoData.tong_ncc || 0"></strong> gói | Đã liên kết: <strong class="text-green-400" x-text="khoData.da_map || 0"></strong> | Còn hàng: <strong class="text-blue-400" x-text="khoData.con_hang || 0"></strong>
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button @click="taiKhoNcc()" :disabled="dangTaiKho" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-200 transition border border-gray-700 flex items-center gap-1">
                                    <i class="fas fa-rotate" :class="dangTaiKho ? 'fa-spin' : ''"></i> Tải lại
                                </button>
                                <button @click="nhapTatCaSanPham()" :disabled="dangNhap" class="px-4 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition shadow-md shadow-purple-600/30 flex items-center gap-1.5">
                                    <i class="fas fa-cloud-arrow-down" :class="dangNhap ? 'fa-bounce' : ''"></i>
                                    <span x-text="dangNhap ? 'Đang nhập...' : '1-Click Nhập Toàn Bộ Vào Shop'"></span>
                                </button>
                            </div>
                        </div>

                        <!-- THANH LỌC & TÌM KIẾM KHO -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                            <input type="text" x-model="tuKhoaKho" placeholder="Tìm kiếm tên sản phẩm, mã SKU NCC (vd: CUR-0013)..." class="w-full sm:w-80 bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                            
                            <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 scrollbar-none">
                                <button @click="locType = 'ALL'" :class="locType === 'ALL' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400'" class="px-3 py-1 rounded-lg font-bold transition whitespace-nowrap">Tất cả</button>
                                <button @click="locType = 'cursor'" :class="locType === 'cursor' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400'" class="px-3 py-1 rounded-lg font-bold transition whitespace-nowrap">Cursor</button>
                                <button @click="locType = 'claude'" :class="locType === 'claude' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400'" class="px-3 py-1 rounded-lg font-bold transition whitespace-nowrap">Claude</button>
                                <button @click="locType = 'codex'" :class="locType === 'codex' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400'" class="px-3 py-1 rounded-lg font-bold transition whitespace-nowrap">Codex</button>
                                <button @click="locType = 'gemini'" :class="locType === 'gemini' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400'" class="px-3 py-1 rounded-lg font-bold transition whitespace-nowrap">Gemini</button>
                                <button @click="locType = 'deepseek'" :class="locType === 'deepseek' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400'" class="px-3 py-1 rounded-lg font-bold transition whitespace-nowrap">DeepSeek</button>
                            </div>
                        </div>

                        <!-- DANH SÁCH SẢN PHẨM TRONG KHO NCC -->
                        <div class="overflow-y-auto flex-1 border border-gray-800 rounded-2xl bg-gray-950">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-900 text-gray-400 font-bold uppercase tracking-wider sticky top-0 z-10 border-b border-gray-800">
                                    <tr>
                                        <th class="py-3 px-3">Mã SKU NCC</th>
                                        <th class="py-3 px-4">Tên Sản Phẩm</th>
                                        <th class="py-3 px-3">Loại</th>
                                        <th class="py-3 px-3">Giá Vốn Tại Nguồn</th>
                                        <th class="py-3 px-3 text-center">Tồn Kho NCC</th>
                                        <th class="py-3 px-3">Trạng Thái Shop</th>
                                        <th class="py-3 px-3 text-right">Thao Tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/60 font-medium">
                                    <template x-if="dangTaiKho">
                                        <tr>
                                            <td colspan="7" class="py-12 text-center text-gray-400">
                                                <i class="fas fa-spinner fa-spin text-2xl text-purple-400 mb-2 block"></i>
                                                Đang tải dữ liệu thời gian thực từ nhà cung cấp...
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-for="p in danhSachKhoLoc" :key="p.supplier_sku">
                                        <tr class="hover:bg-gray-900/60 transition">
                                            <td class="py-3 px-3 font-mono text-purple-400 font-bold" x-text="p.supplier_sku"></td>
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-white" x-text="p.name"></div>
                                                <div class="text-[10px] text-gray-500 truncate max-w-xs" x-text="p.description"></div>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-800 text-gray-300 uppercase" x-text="p.type"></span>
                                            </td>
                                            <td class="py-3 px-3 font-bold text-amber-400 font-mono" x-text="p.cost_text"></td>
                                            <td class="py-3 px-3 text-center">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="p.stock > 0 ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-red-950 text-red-400 border border-red-800'" x-text="p.stock > 0 ? 'Còn: ' + p.stock : 'Hết hàng (0)'">
                                                </span>
                                            </td>
                                            <td class="py-3 px-3">
                                                <template x-if="p.is_mapped">
                                                    <div>
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/10 text-green-400 border border-green-500/30">
                                                            <i class="fas fa-circle-check mr-1"></i> Đã có trong Shop
                                                        </span>
                                                        <div class="text-[10px] text-gray-400 font-mono mt-0.5" x-text="'Giá bán: ' + new Intl.NumberFormat('vi-VN').format(p.shop_product.product_price) + 'đ'"></div>
                                                    </div>
                                                </template>
                                                <template x-if="!p.is_mapped">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-800 text-gray-400">
                                                        Chưa nhập vào shop
                                                    </span>
                                                </template>
                                            </td>
                                            <td class="py-3 px-3 text-right">
                                                <button @click="nhap1SanPham(p.supplier_sku)" :disabled="dangNhap" class="px-3 py-1 rounded-lg text-xs font-bold transition" :class="p.is_mapped ? 'bg-gray-800 hover:bg-gray-700 text-purple-300' : 'bg-purple-600 hover:bg-purple-500 text-white shadow'">
                                                    <i class="fas fa-cloud-arrow-down mr-1"></i>
                                                    <span x-text="p.is_mapped ? 'Cập nhật' : 'Nhập vào Shop'"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MODAL THÊM NHÀ CUNG CẤP -->
                <div x-show="moModalThem" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition>
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-lg w-full p-6 relative shadow-2xl">
                        <button @click="moModalThem = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                            <i class="fas fa-times text-lg"></i>
                        </button>

                        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-plus text-blue-500"></i> Thêm Nhà Cung Cấp API Mới
                        </h3>

                        <form method="POST" action="" class="space-y-4 text-xs">
                            <input type="hidden" name="action" value="them_nha_cung_cap">

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Mã định danh (chữ thường, không dấu, vd: supplier_backup):</label>
                                <input type="text" name="code" required placeholder="supplier_2" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Tên hiển thị nhà cung cấp:</label>
                                <input type="text" name="name" required placeholder="Nhà Cung Cấp Dự Phòng 2" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Base URL API:</label>
                                <input type="url" name="api_url" required placeholder="https://api.nhacungcap.com/v1" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">API Key bí mật:</label>
                                <input type="text" name="api_key" required placeholder="sk-..." class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Ngưỡng tiền tối thiểu cảnh báo (VNĐ):</label>
                                <input type="number" step="10000" name="min_balance_alert" value="200000" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-green-400 font-bold focus:outline-none focus:border-blue-500">
                            </div>

                            <button type="submit" class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold transition shadow-lg shadow-blue-600/30">
                                Thêm Vào Hệ Thống
                            </button>
                        </form>
                    </div>
                </div>

                <!-- MODAL SỬA NHÀ CUNG CẤP -->
                <div x-show="moModalSua" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition>
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-lg w-full p-6 relative shadow-2xl">
                        <button @click="moModalSua = false" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                            <i class="fas fa-times text-lg"></i>
                        </button>

                        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-pen-to-square text-amber-500"></i> Cập Nhật Nhà Cung Cấp
                        </h3>

                        <form method="POST" action="" class="space-y-4 text-xs">
                            <input type="hidden" name="action" value="sua_nha_cung_cap">
                            <input type="hidden" name="id" :value="suaData.id">

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Tên hiển thị:</label>
                                <input type="text" name="name" x-model="suaData.name" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-amber-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Base URL API:</label>
                                <input type="url" name="api_url" x-model="suaData.api_url" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">API Key bí mật:</label>
                                <input type="text" name="api_key" x-model="suaData.api_key" required class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-amber-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-semibold mb-1">Ngưỡng cảnh báo số dư (VNĐ):</label>
                                <input type="number" step="10000" name="min_balance_alert" x-model="suaData.min_balance_alert" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-green-400 font-bold focus:outline-none focus:border-amber-500">
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <input type="checkbox" id="is_active_edit" name="is_active" value="1" :checked="suaData.is_active == 1" class="rounded bg-gray-800 border-gray-700 text-blue-600">
                                <label for="is_active_edit" class="text-xs text-gray-300 font-semibold cursor-pointer">Bật hoạt động nhà cung cấp này</label>
                            </div>

                            <button type="submit" class="w-full py-3 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold transition shadow-lg shadow-amber-600/30">
                                Lưu Thay Đổi
                            </button>
                        </form>
                    </div>
                </div>

            </main>

            <footer class="bg-gray-950 border-t border-gray-900 py-4 text-center text-xs text-gray-500 mt-auto">
                &copy; 2026 <?= SHOP_NAME ?> - Trang Quản Trị Hệ Thống Tự Động
            </footer>
        </div>
    </div>
    <script>
        function ungDungNhaCungCap() {
            return {
                moModalThem: false,
                moModalSua: false,
                suaData: {},

                // Kho hàng NCC
                moModalKho: false,
                dangTaiKho: false,
                dangDongBo: false,
                dangNhap: false,
                khoData: { tong_ncc: 0, da_map: 0, con_hang: 0, products: [] },
                tuKhoaKho: '',
                locType: 'ALL',

                get danhSachKhoLoc() {
                    let items = this.khoData.products || [];
                    if (this.locType !== 'ALL') {
                        items = items.filter(i => (i.type || '').toLowerCase() === this.locType.toLowerCase());
                    }
                    if (this.tuKhoaKho.trim() !== '') {
                        const kw = this.tuKhoaKho.toLowerCase();
                        items = items.filter(i => (i.name || '').toLowerCase().includes(kw) || (i.supplier_sku || '').toLowerCase().includes(kw));
                    }
                    return items;
                },

                moSua(supplier) {
                    this.suaData = supplier;
                    this.moModalSua = true;
                },

                kiemTraSoDu(id) {
                    fetch('/backend/api.php?action=kiem_tra_vi_nguon&id=' + encodeURIComponent(id))
                        .then(res => res.json())
                        .then(data => {
                            if (data.thanh_cong) {
                                alert('Số dư ví tại nguồn [' + (data.ten_ncc || 'Nguồn') + ']: ' + data.so_du_chu);
                                location.reload();
                            } else {
                                alert(data.thong_bao || 'Không thể kiểm tra');
                            }
                        });
                },

                moKhoNcc(id) {
                    this.selectedSupplierId = id || 0;
                    this.moModalKho = true;
                    this.taiKhoNcc(id);
                },

                async taiKhoNcc(id) {
                    id = id || this.selectedSupplierId || 0;
                    this.dangTaiKho = true;
                    try {
                        const res = await fetch('/backend/api.php?action=admin_lay_kho_ncc&supplier_id=' + encodeURIComponent(id));
                        const d = await res.json();
                        this.dangTaiKho = false;
                        if (d.thanh_cong) {
                            this.khoData = d;
                        } else {
                            alert('Lỗi tải kho: ' + (d.thong_bao || ''));
                        }
                    } catch(e) {
                        this.dangTaiKho = false;
                        alert('Lỗi kết nối máy chủ khi tải kho NCC');
                    }
                },

                async dongBoTonKho() {
                    this.dangDongBo = true;
                    try {
                        const res = await fetch('/backend/api.php?action=admin_dong_bo_ton_kho');
                        const d = await res.json();
                        this.dangDongBo = false;
                        if (d.thanh_cong) {
                            alert(d.thong_bao);
                            if (this.moModalKho) {
                                this.taiKhoNcc();
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Lỗi đồng bộ: ' + (d.thong_bao || ''));
                        }
                    } catch(e) {
                        this.dangDongBo = false;
                        alert('Lỗi kết nối máy chủ');
                    }
                },

                async nhap1SanPham(sku) {
                    this.dangNhap = true;
                    const fd = new FormData();
                    fd.append('supplier_sku', sku);
                    try {
                        const res = await fetch('/backend/api.php?action=admin_nhap_san_pham_ncc', { method: 'POST', body: fd });
                        const d = await res.json();
                        this.dangNhap = false;
                        if (d.thanh_cong) {
                            alert(d.thong_bao);
                            this.taiKhoNcc();
                        } else {
                            alert('Lỗi nhập: ' + (d.thong_bao || ''));
                        }
                    } catch(e) {
                        this.dangNhap = false;
                        alert('Lỗi kết nối máy chủ');
                    }
                },

                async nhapTatCaSanPham() {
                    if (!confirm('Bạn có chắc muốn tự động nhập TẤT CẢ các gói sản phẩm từ Nhà Cung Cấp vào Shop? Hệ thống sẽ tự tạo danh mục và tính giá bán tự động!')) return;
                    this.dangNhap = true;
                    const fd = new FormData();
                    fd.append('supplier_sku', 'ALL');
                    try {
                        const res = await fetch('/backend/api.php?action=admin_nhap_san_pham_ncc', { method: 'POST', body: fd });
                        const d = await res.json();
                        this.dangNhap = false;
                        if (d.thanh_cong) {
                            alert(d.thong_bao);
                            this.taiKhoNcc();
                        } else {
                            alert('Lỗi nhập toàn bộ: ' + (d.thong_bao || ''));
                        }
                    } catch(e) {
                        this.dangNhap = false;
                        alert('Lỗi kết nối máy chủ');
                    }
                }
            };
        }
    </script>
</body>
</html>
