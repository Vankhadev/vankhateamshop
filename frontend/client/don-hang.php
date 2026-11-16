<?php
// ========================================================
// TRANG QUẢN LÝ ĐƠN HÀNG CỦA KHÁCH (CLIENT ORDERS)
// vankhateamshop - Xem lịch sử mua, sao chép Key, tải file .txt
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

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

// Lấy toàn bộ đơn hàng của người dùng
$sql = "
    SELECT o.*, p.name AS product_name, p.sku AS product_sku, p.request_count
    FROM `orders` o
    LEFT JOIN `products` p ON o.product_id = p.id
    WHERE (o.user_id = ? OR o.customer_email = ?)
    ORDER BY o.id DESC
";
$stmtOrders = $pdo->prepare($sql);
$stmtOrders->execute([$user['id'], $user['email']]);
$orders = $stmtOrders->fetchAll();

$siteNameDisplay = layCaiDatHeThong('site_name', SHOP_NAME);
$soZaloDisplay = layCaiDatHeThong('site_contact_zalo', SHOP_ZALO);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <!-- Favicon Logo Website Tiem AI -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi - <?= htmlspecialchars($siteNameDisplay) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- THANH MENU TAB QUẢN TRỊ TÀI KHOẢN KHÁCH HÀNG -->
    <div class="bg-[#111827] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-6 text-xs font-semibold overflow-x-auto py-3">
                <a href="/user/dashboard" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-chart-pie mr-1"></i> Tổng Quan
                </a>
                <a href="/user/don-hang" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
                    <i class="fas fa-box-open mr-1"></i> Đơn Hàng Của Tôi
                </a>
                <a href="/user/nap-tien" class="text-gray-400 hover:text-white shrink-0">
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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-box-open text-blue-500"></i> Lịch Sử Đơn Hàng Của Bạn
                </h1>
                <p class="text-xs text-gray-400 mt-1">Toàn bộ các gói API và tài khoản bạn đã mua tại <?= SHOP_NAME ?></p>
            </div>
            <a href="/#san-pham" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-blue-600/30">
                <i class="fas fa-plus"></i> Mua Thêm Gói Mới
            </a>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-800/80 text-gray-400 font-bold uppercase tracking-wider border-b border-gray-700">
                        <tr>
                            <th class="py-3.5 px-4">Mã Đơn / Ngày Mua</th>
                            <th class="py-3.5 px-4">Tên Gói</th>
                            <th class="py-3.5 px-4">Số Tiền</th>
                            <th class="py-3.5 px-4 text-center">Trạng Thái</th>
                            <th class="py-3.5 px-4">Key / Tài Khoản Đã Nhận</th>
                            <th class="py-3.5 px-4 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-medium">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-500">
                                    <i class="fas fa-box-open text-3xl mb-2 block text-gray-600"></i>
                                    Bạn chưa có đơn hàng nào. <a href="/" class="text-blue-400 underline font-bold">Khám phá bảng giá ngay</a>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $order): 
                            $delivered = json_decode($order['delivered_data'] ?? '[]', true);
                            $keyText = is_array($delivered) ? implode("\n", $delivered) : ($order['delivered_data'] ?? '');
                        ?>
                            <tr class="hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-bold text-blue-400"><?= htmlspecialchars($order['order_code']) ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?= date('H:i - d/m/Y', strtotime($order['created_at'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($order['product_name'] ?? 'Gói cước') ?></div>
                                    <?php if (!empty($order['request_count'])): ?>
                                        <span class="text-[11px] text-amber-400 font-mono"><?= htmlspecialchars($order['request_count']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 font-bold text-green-400">
                                    <?= number_format($order['total_amount'], 0, ',', '.') ?>đ
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($order['status'] === 'COMPLETED'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-400 border border-green-800">Hoạt động</span>
                                    <?php elseif ($order['status'] === 'PENDING'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-400 border border-amber-800">Chờ tiền</span>
                                    <?php elseif ($order['status'] === 'REFUNDED'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-950 text-purple-400 border border-purple-800">Đã hoàn tiền</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-950 text-red-400 border border-red-800"><?= htmlspecialchars($order['status']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if (!empty($keyText)): ?>
                                        <div class="max-w-xs truncate font-mono text-xs text-amber-300 bg-gray-950 px-2.5 py-1 rounded border border-gray-800 select-all" title="<?= htmlspecialchars($keyText) ?>">
                                            <?= htmlspecialchars($keyText) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-[11px]">Chưa giao key</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <?php
                                        $orderPayload = [
                                            'order_code'     => $order['order_code'],
                                            'created_at'     => date('H:i - d/m/Y', strtotime($order['created_at'])),
                                            'product_name'   => $order['product_name'] ?? 'Gói cước',
                                            'total_amount'   => number_format($order['total_amount'], 0, ',', '.') . 'đ',
                                            'status'         => $order['status'],
                                            'customer_email' => $order['customer_email'],
                                            'key'            => $keyText,
                                            'error_log'      => $order['error_log'] ?? ''
                                        ];
                                        ?>
                                        <button onclick='moModalChiTiet(<?= json_encode($orderPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="px-2.5 py-1 rounded bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 text-xs font-semibold transition border border-blue-500/30 flex items-center gap-1" title="Xem Chi Tiết Đơn Hàng">
                                            <i class="fas fa-eye text-amber-400"></i> Xem Chi Tiết
                                        </button>
                                        <?php if (!empty($keyText)): ?>
                                            <button onclick="saoChep('<?= addslashes($keyText) ?>')" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold transition border border-gray-700" title="Sao chép Key">
                                                <i class="fas fa-copy text-blue-400"></i>
                                            </button>
                                            <button onclick="taiFileTxt('<?= $order['order_code'] ?>', '<?= addslashes($keyText) ?>')" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold transition border border-gray-700" title="Tải file .txt">
                                                <i class="fas fa-download text-green-400"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL XEM CHI TIẾT ĐƠN HÀNG -->
    <div id="modalChiTietDon" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
        <div class="bg-[#111827] border border-gray-800 rounded-3xl max-w-lg w-full p-6 relative shadow-2xl space-y-4">
            <button onclick="dongModalChiTiet()" class="absolute top-4 right-4 text-gray-400 hover:text-white text-lg">
                <i class="fas fa-times"></i>
            </button>
            <div class="flex items-center gap-2 border-b border-gray-800 pb-3">
                <i class="fas fa-receipt text-amber-400 text-lg"></i>
                <h3 class="text-base font-bold text-white">Chi Tiết Đơn Hàng #<span id="dtMaDon"></span></h3>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="p-3 bg-gray-950 rounded-xl border border-gray-800/80">
                    <span class="text-gray-400 block text-[11px]">Sản phẩm:</span>
                    <strong class="text-white text-xs" id="dtTenGoi"></strong>
                </div>
                <div class="p-3 bg-gray-950 rounded-xl border border-gray-800/80">
                    <span class="text-gray-400 block text-[11px]">Số tiền thanh toán:</span>
                    <strong class="text-green-400 text-xs" id="dtSoTien"></strong>
                </div>
                <div class="p-3 bg-gray-950 rounded-xl border border-gray-800/80">
                    <span class="text-gray-400 block text-[11px]">Thời gian đặt hàng:</span>
                    <span class="text-gray-300" id="dtThoiGian"></span>
                </div>
                <div class="p-3 bg-gray-950 rounded-xl border border-gray-800/80">
                    <span class="text-gray-400 block text-[11px]">Trạng thái:</span>
                    <span id="dtTrangThai" class="font-bold"></span>
                </div>
                <div class="col-span-2 p-3 bg-gray-950 rounded-xl border border-gray-800/80">
                    <span class="text-gray-400 block text-[11px]">Email nhận hàng:</span>
                    <span class="text-blue-300 font-mono" id="dtEmail"></span>
                </div>
            </div>

            <!-- Khu vực Key / Tài khoản -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-white uppercase flex items-center gap-1.5">
                        <i class="fas fa-key text-amber-400"></i> Key / Tài Khoản Đã Nhận:
                    </span>
                    <div class="flex items-center gap-2">
                        <button onclick="saoChepTuModal()" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition flex items-center gap-1">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <button onclick="taiFileTuModal()" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition flex items-center gap-1">
                            <i class="fas fa-download"></i> .TXT
                        </button>
                    </div>
                </div>
                <textarea id="dtKey" readonly class="w-full h-28 bg-gray-950 border border-gray-800 rounded-xl p-3 text-xs font-mono text-emerald-300 resize-none select-all focus:outline-none"></textarea>
            </div>

            <!-- Base URL Kết Nối API Chuẩn OpenAI -->
            <div class="p-3.5 bg-gray-950 rounded-2xl border border-blue-900/50 space-y-2 overflow-hidden">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-blue-400 font-bold uppercase text-[11px] flex items-center gap-1.5">
                        <i class="fas fa-link"></i> Base URL (OpenAI Compatible)
                    </span>
                    <button type="button" onclick="navigator.clipboard.writeText('https://tiemai.io.vn/v1'); alert('Đã sao chép Base URL: https://tiemai.io.vn/v1')" class="px-2.5 py-1 rounded-lg bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 hover:text-white text-[11px] font-bold transition flex items-center gap-1 border border-blue-500/30">
                        <i class="fas fa-copy"></i> Sao chép
                    </button>
                </div>
                <div class="flex items-center">
                    <code class="text-amber-300 font-mono text-xs bg-gray-900 px-3 py-1.5 rounded-xl border border-gray-800 flex-1 select-all break-all">https://tiemai.io.vn/v1</code>
                </div>
                <div class="text-[11px] text-gray-400 flex flex-wrap items-center gap-1">
                    <span>Chat Endpoint:</span> <code class="text-gray-300 font-mono text-[10px] break-all">https://tiemai.io.vn/v1/chat/completions</code>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-800">
                <a href="https://zalo.me/<?= htmlspecialchars($soZaloDisplay) ?>" target="_blank" class="text-xs text-blue-400 hover:underline flex items-center gap-1">
                    <i class="fas fa-headset"></i> Cần hỗ trợ về đơn này?
                </a>
                <button onclick="dongModalChiTiet()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold rounded-xl transition">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        let donHangDangXem = null;

        function moModalChiTiet(don) {
            donHangDangXem = don;
            document.getElementById('dtMaDon').innerText = don.order_code;
            document.getElementById('dtTenGoi').innerText = don.product_name;
            document.getElementById('dtSoTien').innerText = don.total_amount;
            document.getElementById('dtThoiGian').innerText = don.created_at;
            document.getElementById('dtEmail').innerText = don.customer_email;
            document.getElementById('dtKey').value = don.key || '(Chưa có thông tin key)';

            const elStatus = document.getElementById('dtTrangThai');
            if (don.status === 'COMPLETED') {
                elStatus.className = 'text-green-400 font-bold';
                elStatus.innerText = 'Hoàn thành / Đã giao';
            } else if (don.status === 'REFUNDED') {
                elStatus.className = 'text-purple-400 font-bold';
                elStatus.innerText = 'Đã hoàn tiền vào ví';
            } else if (don.status === 'PENDING') {
                elStatus.className = 'text-amber-400 font-bold';
                elStatus.innerText = 'Chờ thanh toán';
            } else {
                elStatus.className = 'text-red-400 font-bold';
                elStatus.innerText = don.status;
            }

            document.getElementById('modalChiTietDon').classList.remove('hidden');
        }

        function dongModalChiTiet() {
            document.getElementById('modalChiTietDon').classList.add('hidden');
        }

        function saoChepTuModal() {
            const val = document.getElementById('dtKey').value;
            if (val) saoChep(val);
        }

        function taiFileTuModal() {
            if (donHangDangXem && donHangDangXem.key) {
                taiFileTxt(donHangDangXem.order_code, donHangDangXem.key);
            }
        }

        function saoChep(noiDung) {
            navigator.clipboard.writeText(noiDung).then(() => {
                alert('Đã sao chép vào bộ nhớ tạm!');
            });
        }

        function taiFileTxt(maDon, noiDung) {
            const blob = new Blob([noiDung], { type: 'text/plain;charset=utf-8' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Key_${maDon}.txt`;
            link.click();
        }
    </script>
</body>
</html>
