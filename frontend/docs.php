<?php
// ========================================================
// TRANG TÀI LIỆU KẾT NỐI API ĐỐI TÁC (BUYER & PARTNER API DOCS)
// vankhateamshop - Tự động phát hiện Base URL theo tên miền website
// Hỗ trợ đấu nối Bot, Web bán hàng tự động & Cursor IDE
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Tự động nhận diện Base URL theo tên miền website trên mạng
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
$baseUrl = $protocol . '://' . $host;
$partnerApiUrl = $baseUrl . '/api/partner/v1';

// Lấy danh sách sản phẩm mẫu thực tế
$products = $pdo->query("SELECT * FROM `products` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 8")->fetchAll();
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
    <title>Tài Liệu Kết Nối Đối Tác (Buyer API) - <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        pre code { font-family: 'Fira Code', Consolas, Monaco, monospace; }
    </style>
</head>
<body x-data="ungDungDocs()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1 space-y-12">

        <!-- TIÊU ĐỀ CHÍNH -->
        <div class="border-b border-gray-800 pb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider mb-4">
                <i class="fas fa-handshake"></i> API Chính Thức Dành Cho Đối Tác & Đại Lý
            </div>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                <?= SHOP_NAME ?> Buyer API
            </h1>
<p class="text-base text-gray-400 mt-2 max-w-3xl leading-relaxed">
                 API chính thức do <strong><?= SHOP_NAME ?></strong> phát hành, dành cho đối tác bán sản phẩm tự động trên website, ứng dụng hoặc bot Telegram/Discord của mình.
             </p>
             <div class="mt-4 inline-flex flex-wrap items-center gap-2 text-xs">
                 <span class="px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-300"><i class="fas fa-store mr-1"></i>Đây là Buyer API lấy sản phẩm về bán</span>
                 <a href="/docs" class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:text-white">Docs tích hợp Codex / Claude / Kilo / Cursor</a>
             </div>
        </div>

        <!-- HƯỚNG DẪN DÙNG NHANH & CẦN NHỚ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-6 shadow-xl">
                <h2 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-bolt text-amber-400"></i> Cách Dùng Rất Nhanh
                </h2>
                <ol class="list-decimal list-inside space-y-2 text-xs text-gray-300 leading-relaxed font-medium">
                    <li>Dùng <strong>API Key đại lý</strong> được Admin cấp riêng cho bạn (mỗi đại lý có 1 mã riêng).</li>
                    <li>Lấy danh sách sản phẩm và tồn kho bằng: <code class="text-blue-400 font-mono">GET /products.php</code>.</li>
                    <li>Xem số dư ví hiện tại bằng: <code class="text-blue-400 font-mono">GET /balance.php</code>.</li>
                    <li>Mua hàng tự động bằng: <code class="text-blue-400 font-mono">POST /purchase.php</code>.</li>
                    <li>Khi thành công, lấy dữ liệu tài khoản trong <code class="text-green-400 font-mono">deliveredAccounts</code> để giao ngay cho khách.</li>
                </ol>
            </div>

            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-6 shadow-xl">
                <h2 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation text-amber-500"></i> Cần Ghi Nhớ
                </h2>
                <ul class="list-disc list-inside space-y-2 text-xs text-gray-300 leading-relaxed font-medium">
                    <li>Địa chỉ endpoint phải kèm đuôi <code class="text-amber-400 font-mono">.php</code> (ví dụ: <code class="text-amber-400 font-mono">/products.php</code>).</li>
                    <li>Production bắt buộc gọi qua <strong>HTTPS</strong> — key nằm trong header nên phải đi qua kênh mã hóa SSL.</li>
                    <li>Mỗi đơn của khách phải có một <code class="text-blue-400 font-mono">idempotency_key</code> riêng (8 đến 128 ký tự).</li>
                    <li>Nếu gửi lại cùng một đơn, giữ nguyên <code class="text-blue-400 font-mono">idempotency_key</code> cũ — hệ thống trả lại đơn cũ kèm <code class="text-green-400 font-mono">"replay": true</code>, <strong>tuyệt đối không trừ tiền lần hai</strong>.</li>
                    <li>Khi mua, hệ thống trừ trực tiếp vào số dư ví (VNĐ) của tài khoản đại lý.</li>
                </ul>
            </div>
        </div>

        <!-- BASE URL & XÁC THỰC -->
        <div id="buyer-api" class="bg-[#111827] border border-blue-900/60 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-lock text-blue-400"></i> Base URL & Cơ Chế Xác Thực
            </h2>
            <p class="text-xs text-gray-300">
                Tất cả request gọi tới hệ thống đại lý đều sử dụng Base URL tự động theo tên miền của shop:
            </p>
            <div class="bg-[#0B0F19] p-3.5 rounded-xl border border-gray-800 font-mono text-sm text-green-400 font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 overflow-hidden">
                <span class="break-all whitespace-normal">Base URL: <?= htmlspecialchars($partnerApiUrl) ?></span>
                <button @click="saoChep('<?= htmlspecialchars($partnerApiUrl) ?>')" class="self-start sm:self-center shrink-0 text-xs text-gray-400 hover:text-white font-sans px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 transition flex items-center gap-1.5">
                    <i class="fas fa-copy"></i> <span>Copy</span>
                </button>
            </div>

            <p class="text-xs text-gray-300">Mọi request phải kèm header xác thực (khuyến nghị dùng <code class="text-amber-400 font-mono">X-API-Key</code>):</p>
            <div class="bg-[#0B0F19] p-3 rounded-xl border border-gray-800 font-mono text-xs text-gray-300 space-y-1.5 overflow-hidden">
                <div class="break-all whitespace-normal"><span class="text-gray-500">Cách 1:</span> <span class="text-blue-400 font-bold">X-API-Key:</span> YOUR_PARTNER_API_KEY</div>
                <div class="break-all whitespace-normal"><span class="text-gray-500">Cách 2:</span> <span class="text-blue-400 font-bold">Authorization:</span> Bearer YOUR_PARTNER_API_KEY</div>
            </div>
        </div>

        <!-- 1. ENDPOINT: GET /products.php -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4">
            <div class="flex items-center space-x-3">
                <span class="px-2.5 py-1 rounded-lg bg-green-500/20 text-green-400 font-mono font-black text-xs">GET</span>
                <span class="font-mono text-sm text-white font-bold">/products.php</span>
            </div>
            <p class="text-xs text-gray-300">Lấy danh sách sản phẩm đang mở bán, kèm giá đại lý (đã trừ % chiết khấu riêng của bạn) và tồn kho.</p>

            <div class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800">
                <div class="flex items-center justify-between text-gray-400 text-xs mb-2">
                    <span>Ví dụ cURL:</span>
                    <button @click="saoChep('curl &quot;<?= $partnerApiUrl ?>/products.php&quot; -H &quot;X-API-Key: YOUR_API_KEY&quot;')" class="hover:text-white"><i class="fas fa-copy"></i></button>
                </div>
                <pre class="text-xs text-blue-300 overflow-x-auto"><code>curl "<?= $partnerApiUrl ?>/products.php" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
            </div>

            <div class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800">
                <div class="text-gray-400 text-xs mb-2">Phản hồi JSON (Mẫu):</div>
                <pre class="text-xs text-gray-300 overflow-x-auto"><code>{
  "success": true,
  "walletCurrency": "VND",
  "products": [
    {
      "product_id": "VK-CUR-30D-700",
      "name": "API Cursor 30 Ngày (700 Request)",
      "description": "Gói cơ bản phù hợp cho cá nhân code hàng ngày",
      "type": "cursor",
      "walletCurrency": "VND",
      "walletPricing": 76500,
      "walletPricingText": "76.500 VND",
      "available": 999
    }
  ]
}</code></pre>
            </div>
        </div>

        <!-- 2. ENDPOINT: GET /balance.php -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4">
            <div class="flex items-center space-x-3">
                <span class="px-2.5 py-1 rounded-lg bg-green-500/20 text-green-400 font-mono font-black text-xs">GET</span>
                <span class="font-mono text-sm text-white font-bold">/balance.php</span>
            </div>
            <p class="text-xs text-gray-300">Xem số dư ví hiện tại của tài khoản gắn với API key.</p>

            <div class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800">
                <div class="flex items-center justify-between text-gray-400 text-xs mb-2">
                    <span>Ví dụ cURL:</span>
                    <button @click="saoChep('curl &quot;<?= $partnerApiUrl ?>/balance.php&quot; -H &quot;X-API-Key: YOUR_API_KEY&quot;')" class="hover:text-white"><i class="fas fa-copy"></i></button>
                </div>
                <pre class="text-xs text-blue-300 overflow-x-auto"><code>curl "<?= $partnerApiUrl ?>/balance.php" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
            </div>

            <div class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800">
                <div class="text-gray-400 text-xs mb-2">Phản hồi JSON:</div>
                <pre class="text-xs text-gray-300 overflow-x-auto"><code>{
  "success": true,
  "walletCurrency": "VND",
  "balance": 5000000,
  "balanceText": "5.000.000 VND"
}</code></pre>
            </div>
        </div>

        <!-- 3. ENDPOINT: POST /purchase.php -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4">
            <div class="flex items-center space-x-3">
                <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 font-mono font-black text-xs">POST</span>
                <span class="font-mono text-sm text-white font-bold">/purchase.php</span>
            </div>
            <p class="text-xs text-gray-300">Mua hàng và nhận tài khoản/key ngay. Trừ số dư ví, trả về tài khoản trong <code class="text-green-400 font-mono">deliveredAccounts</code>.</p>

            <div class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800">
                <div class="flex items-center justify-between text-gray-400 text-xs mb-2">
                    <span>Ví dụ cURL:</span>
                    <button @click="saoChep('curl -X POST &quot;<?= $partnerApiUrl ?>/purchase.php&quot; -H &quot;X-API-Key: YOUR_API_KEY&quot; -H &quot;Content-Type: application/json&quot; -d \'{\n    &quot;product_id&quot;: &quot;VK-CUR-30D-700&quot;,\n    &quot;quantity&quot;: 1,\n    &quot;idempotency_key&quot;: &quot;don-khach-0001&quot;\n  }\'')" class="hover:text-white"><i class="fas fa-copy"></i></button>
                </div>
                <pre class="text-xs text-blue-300 overflow-x-auto"><code>curl -X POST "<?= $partnerApiUrl ?>/purchase.php" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": "VK-CUR-30D-700",
    "quantity": 1,
    "idempotency_key": "don-khach-0001",
    "customer_email": "khachhang@gmail.com"
  }'</code></pre>
            </div>

            <!-- Bảng tham số -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-gray-400 border-b border-gray-800">
                        <tr>
                            <th class="py-2">Tham số Body</th>
                            <th class="py-2">Kiểu</th>
                            <th class="py-2">Mô tả</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-mono text-[11px]">
                        <tr>
                            <td class="py-2 text-blue-400 font-bold">product_id</td>
                            <td class="py-2 text-gray-400 font-sans">string</td>
                            <td class="py-2 text-gray-300 font-sans">SKU sản phẩm (Bắt buộc). Ví dụ: <code>VK-CUR-30D-700</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-blue-400 font-bold">quantity</td>
                            <td class="py-2 text-gray-400 font-sans">integer</td>
                            <td class="py-2 text-gray-300 font-sans">Số lượng cần mua từ 1 đến 1000 (Bắt buộc)</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-blue-400 font-bold">idempotency_key</td>
                            <td class="py-2 text-gray-400 font-sans">string</td>
                            <td class="py-2 text-gray-300 font-sans">Mã khóa chống trừ tiền 2 lần từ 8 đến 128 ký tự (Bắt buộc)</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-blue-400 font-bold">customer_email</td>
                            <td class="py-2 text-gray-400 font-sans">string</td>
                            <td class="py-2 text-gray-300 font-sans">Email khách hàng của bạn để đối soát lịch sử (Tùy chọn)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800">
                <div class="text-gray-400 text-xs mb-2">Phản hồi JSON thành công:</div>
                <pre class="text-xs text-gray-300 overflow-x-auto"><code>{
  "success": true,
  "walletCurrency": "VND",
  "orderCode": "VK261114A1B2C3",
  "productType": "cursor",
  "quantity": 1,
  "amount": 76500,
  "amountText": "76.500 VND",
  "balance": 4923500,
  "balanceText": "4.923.500 VND",
  "deliveredAccounts": [
    {
      "key": "sk-vankha-token-active-700req",
      "user": "acc@example.com",
      "password": "example-password"
    }
  ]
}</code></pre>
            </div>
        </div>

        <!-- 4. BẢNG MÃ LỖI (ERROR CODE) -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-shield-virus text-red-400"></i> Bảng Mã Lỗi (errorCode)
            </h2>
            <p class="text-xs text-gray-300">Mọi lỗi đều trả về định dạng JSON chuẩn (HTTP status &ge; 400):</p>
            
            <div class="bg-[#0B0F19] p-3 rounded-xl border border-gray-800 text-xs font-mono text-gray-300">
                <span class="text-red-400 font-bold">{ "success": false, "errorCode": "INSUFFICIENT_BALANCE", "detail": "Số dư ví không đủ..." }</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-gray-400 border-b border-gray-800 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-2.5 px-3">HTTP Status</th>
                            <th class="py-2.5 px-3">errorCode</th>
                            <th class="py-2.5 px-3">Ý nghĩa & Cách xử lý</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 font-sans text-xs">
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-amber-400">400</td>
                            <td class="py-2 px-3 font-mono text-red-400 font-bold">INVALID_BODY</td>
                            <td class="py-2 px-3 text-gray-300">Body gửi lên không phải JSON hợp lệ</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-amber-400">401</td>
                            <td class="py-2 px-3 font-mono text-red-400 font-bold">MISSING_API_KEY</td>
                            <td class="py-2 px-3 text-gray-300">Thiếu header X-API-Key (hoặc Authorization: Bearer)</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-amber-400">401</td>
                            <td class="py-2 px-3 font-mono text-red-400 font-bold">INVALID_API_KEY</td>
                            <td class="py-2 px-3 text-gray-300">Key sai hoặc đã bị thu hồi quyền đại lý</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-red-400">402</td>
                            <td class="py-2 px-3 font-mono text-amber-400 font-bold">INSUFFICIENT_BALANCE</td>
                            <td class="py-2 px-3 text-gray-300">Số dư ví của đại lý không đủ để mua đơn hàng này. Cần nạp thêm tiền.</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-amber-400">404</td>
                            <td class="py-2 px-3 font-mono text-gray-300 font-bold">PRODUCT_NOT_FOUND</td>
                            <td class="py-2 px-3 text-gray-300">Không tìm thấy mã sản phẩm (SKU)</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-amber-400">409</td>
                            <td class="py-2 px-3 font-mono text-amber-400 font-bold">PRODUCT_INACTIVE</td>
                            <td class="py-2 px-3 text-gray-300">Sản phẩm hiện đang tạm dừng bán</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-amber-400">422</td>
                            <td class="py-2 px-3 font-mono text-gray-300 font-bold">INVALID_IDEMPOTENCY_KEY</td>
                            <td class="py-2 px-3 text-gray-300">idempotency_key sai định dạng hoặc ngắn hơn 8 ký tự</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-mono font-bold text-red-400">500</td>
                            <td class="py-2 px-3 font-mono text-red-400 font-bold">INTERNAL_ERROR</td>
                            <td class="py-2 px-3 text-gray-300">Lỗi hệ thống máy chủ, đơn hàng chưa bị trừ tiền</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungDocs() {
        return {
            saoChep(text) {
                navigator.clipboard.writeText(text);
                alert('Đã sao chép vào bộ nhớ tạm!');
            }
        }
    }
    </script>
</body>
</html>
