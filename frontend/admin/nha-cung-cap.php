<?php
// ========================================================
// TRANG QUẢN LÝ NHÀ CUNG CẤP API (ADMIN SUPPLIERS)
// vankhateamshop - Thêm/sửa nguồn API đối tác, cài đặt Key, xem số dư trực tiếp
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
    </style>
</head>
<body x-data="ungDungNhaCungCap()" class="min-h-screen flex flex-col antialiased">

    <!-- THANH MENU QUẢN TRỊ VIÊN -->
    <header class="bg-[#0F172A] border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-amber-600 flex items-center justify-center text-white font-bold">
                    <i class="fas fa-network-wired"></i>
                </div>
                <div>
                    <span class="text-base font-bold text-white">VanKha<span class="text-amber-500">Admin</span></span>
                    <span class="text-[10px] text-gray-400 block font-semibold uppercase">Quản Lý Nhà Cung Cấp API</span>
                </div>
            </div>

            <!-- Menu quản trị -->
            <nav class="hidden xl:flex items-center space-x-3 text-xs font-semibold">
                <a href="/admin/dashboard" class="text-gray-300 hover:text-white"><i class="fas fa-chart-pie mr-1"></i> Tổng Quan</a>
                <a href="/admin/thong-ke" class="text-gray-300 hover:text-white"><i class="fas fa-chart-line mr-1"></i> Báo Cáo</a>
                <a href="/admin/don-hang" class="text-gray-300 hover:text-white"><i class="fas fa-receipt mr-1"></i> Đơn Hàng</a>
                <a href="/admin/san-pham" class="text-gray-300 hover:text-white"><i class="fas fa-cubes mr-1"></i> Sản Phẩm</a>
                <a href="/admin/danh-muc" class="text-gray-300 hover:text-white"><i class="fas fa-folder-tree mr-1"></i> Danh Mục</a>
                <a href="/admin/thanh-vien" class="text-gray-300 hover:text-white"><i class="fas fa-users mr-1"></i> Thành Viên</a>
                <a href="/admin/ngan-hang" class="text-gray-300 hover:text-white"><i class="fas fa-building-columns mr-1"></i> Ngân Hàng</a>
                <a href="/admin/nha-cung-cap" class="text-amber-400 font-bold border-b-2 border-amber-500 pb-1"><i class="fas fa-network-wired mr-1"></i> Nhà Cung Cấp</a>
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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-network-wired text-amber-500"></i> Các Nguồn Cung Cấp API Đối Tác
                </h1>
                <p class="text-xs text-gray-400 mt-1">Cài đặt kết nối máy chủ, API Key bí mật và kiểm tra số dư ví nguồn trực tiếp</p>
            </div>
            
            <button @click="moModalThem = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Thêm Nhà Cung Cấp Mới
            </button>
        </div>

        <?php if (!empty($thongBao)): ?>
            <div class="p-4 rounded-xl mb-6 flex items-center gap-3 text-xs font-bold <?= $loaiThongBao === 'success' ? 'bg-green-950/70 border border-green-700 text-green-300' : 'bg-red-950/70 border border-red-700 text-red-300' ?>">
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

                        <div class="flex items-center gap-2">
                            <button @click="kiemTraSoDu(<?= $s['id'] ?>)" class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                                <i class="fas fa-rotate"></i> Check Live
                            </button>
                            <button @click="moSua(<?= htmlspecialchars(json_encode($s)) ?>)" class="px-3 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold text-xs transition border border-gray-700">
                                <i class="fas fa-pen-to-square"></i> Sửa
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- MODAL THÊM NHÀ CUNG CẤP -->
        <div x-show="moModalThem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
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
        <div x-show="moModalSua" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;" x-transition>
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

    <footer class="border-t border-gray-800/80 bg-[#0F172A] py-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> <?= SHOP_NAME ?> - Hệ Thống Điều Phối Đa Nguồn API
    </footer>

    <script>
        function ungDungNhaCungCap() {
            return {
                moModalThem: false,
                moModalSua: false,
                suaData: {},

                moSua(supplier) {
                    this.suaData = supplier;
                    this.moModalSua = true;
                },

                kiemTraSoDu(id) {
                    fetch('/backend/api.php?action=kiem_tra_vi_nguon')
                        .then(res => res.json())
                        .then(data => {
                            if (data.thanh_cong) {
                                alert('Số dư ví: ' + data.so_du_chu);
                                location.reload();
                            } else {
                                alert(data.thong_bao || 'Không thể kiểm tra');
                            }
                        });
                }
            };
        }
    </script>
</body>
</html>
