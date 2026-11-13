<?php
// ========================================================
// TRANG KẾT NỐI API & TRÌNH THỬ NGHIỆM (API PLAYGROUND)
// vankhateamshop - Dành cho khách hàng & đối tác lập trình
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/db.php';
require_once __DIR__ . '/../../backend/security.php';

thietLapBaoMatHeaders();

// Kiểm tra đăng nhập
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// Nếu tài khoản chưa có API Token thì tự sinh 1 token
if (empty($user['api_token'])) {
    $tokenMoi = 'vk_' . bin2hex(random_bytes(24));
    $pdo->prepare("UPDATE `users` SET `api_token` = ? WHERE `id` = ?")->execute([$tokenMoi, $user['id']]);
    $user['api_token'] = $tokenMoi;
}

// Lấy danh sách sản phẩm/model API đang kinh doanh
$products = $pdo->query("SELECT * FROM `products` WHERE `is_active` = 1 ORDER BY `id` ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết Nối API & Playground - <?= SHOP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body x-data="ungDungApiDocs()" class="min-h-screen flex flex-col antialiased">

    <!-- HEADER DÙNG CHUNG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- THANH MENU TAB QUẢN TRỊ TÀI KHOẢN KHÁCH HÀNG -->
    <div class="bg-[#111827] border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-6 text-xs font-semibold overflow-x-auto py-3">
                <a href="/user/dashboard" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-chart-pie mr-1"></i> Tổng Quan
                </a>
                <a href="/user/don-hang" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-box-open mr-1"></i> Đơn Hàng Của Tôi
                </a>
                <a href="/user/nap-tien" class="text-gray-400 hover:text-white shrink-0">
                    <i class="fas fa-wallet mr-1 text-green-400"></i> Nạp Tiền Ví
                </a>
                <a href="/user/api-docs" class="text-blue-400 font-bold shrink-0 border-b-2 border-blue-500 pb-1">
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

    <!-- NỘI DUNG CHÍNH -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="mb-8">
            <h1 class="text-2xl font-black text-white flex items-center gap-3">
                <i class="fas fa-code text-blue-500"></i> Tài Liệu Tích Hợp API & Trình Thử Nghiệm
            </h1>
            <p class="text-sm text-gray-400 mt-1">Cổng kết nối chuẩn định dạng OpenAI `/v1/chat/completions` tương thích hoàn hảo với Cursor IDE, Cline, NextChat, Python và Node.js.</p>
        </div>

        <!-- 1. KHUNG API KEY CÁ NHÂN -->
        <div class="bg-[#111827] border border-blue-900/50 rounded-2xl p-6 mb-8 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fas fa-key text-amber-400"></i> API Token Của Bạn (Bearer Token)
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">Dùng khóa này trong Header Authorization để gọi API từ ứng dụng của bạn.</p>
                </div>
                <button @click="taoMoiToken()" :disabled="dangTaoToken" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs font-semibold text-gray-300 transition duration-150 flex items-center gap-1.5">
                    <i class="fas fa-rotate" :class="{'fa-spin': dangTaoToken}"></i> Tạo Lại Khóa Mới
                </button>
            </div>

            <div class="flex items-center space-x-2">
                <div class="relative flex-1">
                    <input :type="hienToken ? 'text' : 'password'" x-model="apiToken" readonly class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-blue-300 font-mono focus:outline-none">
                    <button @click="hienToken = !hienToken" class="absolute right-3 top-2.5 text-gray-400 hover:text-white text-xs">
                        <i class="fas" :class="hienToken ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
                <button @click="saoChep(apiToken)" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition duration-150 flex items-center gap-1.5 flex-shrink-0">
                    <i class="fas fa-copy"></i> Sao Chép
                </button>
            </div>
            
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="bg-[#0F172A] p-3 rounded-xl border border-gray-800">
                    <span class="text-gray-400 block mb-1">Base URL (OpenAI Compatible):</span>
                    <code class="text-amber-400 font-mono font-bold"><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/v1</code>
                </div>
                <div class="bg-[#0F172A] p-3 rounded-xl border border-gray-800">
                    <span class="text-gray-400 block mb-1">Chat Completions Endpoint:</span>
                    <code class="text-green-400 font-mono font-bold"><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/v1/chat/completions</code>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- 2. HƯỚNG DẪN CẤU HÌNH & CODE MẪU -->
            <div class="space-y-6">
                <!-- Tab chọn ngôn ngữ -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-800 pb-3 mb-4">
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <i class="fas fa-laptop-code text-blue-400"></i> Code Mẫu Tích Hợp
                        </h3>
                        <div class="flex space-x-1">
                            <button @click="tabCode = 'cursor'" :class="tabCode === 'cursor' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'" class="px-2.5 py-1 rounded-lg text-xs font-semibold">Cursor</button>
                            <button @click="tabCode = 'python'" :class="tabCode === 'python' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'" class="px-2.5 py-1 rounded-lg text-xs font-semibold">Python</button>
                            <button @click="tabCode = 'nodejs'" :class="tabCode === 'nodejs' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'" class="px-2.5 py-1 rounded-lg text-xs font-semibold">Node.js</button>
                            <button @click="tabCode = 'curl'" :class="tabCode === 'curl' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'" class="px-2.5 py-1 rounded-lg text-xs font-semibold">cURL</button>
                        </div>
                    </div>

                    <!-- Cursor IDE -->
                    <div x-show="tabCode === 'cursor'" class="space-y-3">
                        <p class="text-xs text-gray-300">Cấu hình trong Cursor Settings (<kbd class="bg-gray-800 px-1 py-0.5 rounded text-[10px]">Ctrl + Shift + J</kbd> -> Models):</p>
                        <div class="bg-[#0B0F19] p-3 rounded-xl border border-gray-800 space-y-2 text-xs font-mono">
                            <div><span class="text-gray-500">1. Override OpenAI Base URL:</span> <span class="text-amber-400"><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/v1</span></div>
                            <div><span class="text-gray-500">2. OpenAI API Key:</span> <span class="text-blue-400" x-text="apiToken"></span></div>
                            <div><span class="text-gray-500">3. Thêm Model:</span> <span class="text-green-400">claude-3-5-sonnet, deepseek-reasoner, cursor-small</span></div>
                        </div>
                    </div>

                    <!-- Python Code -->
                    <div x-show="tabCode === 'python'" class="relative">
                        <pre class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800 text-[11px] font-mono text-gray-300 overflow-x-auto"><code>from openai import OpenAI

client = OpenAI(
    api_key="<span class="text-blue-400" x-text="apiToken"></span>",
    base_url="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/v1"
)

response = client.chat.completions.create(
    model="claude-3-5-sonnet",
    messages=[
        {"role": "user", "content": "Xin chào, hãy viết 1 hàm tính giai thừa bằng Python."}
    ]
)

print(response.choices[0].message.content)</code></pre>
                    </div>

                    <!-- Node.js Code -->
                    <div x-show="tabCode === 'nodejs'" class="relative">
                        <pre class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800 text-[11px] font-mono text-gray-300 overflow-x-auto"><code>import OpenAI from 'openai';

const openai = new OpenAI({
  apiKey: '<span class="text-blue-400" x-text="apiToken"></span>',
  baseURL: '<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/v1',
});

const completion = await openai.chat.completions.create({
  model: 'deepseek-chat',
  messages: [{ role: 'user', content: 'Hello!' }],
});

console.log(completion.choices[0].message.content);</code></pre>
                    </div>

                    <!-- cURL Code -->
                    <div x-show="tabCode === 'curl'" class="relative">
                        <pre class="bg-[#0B0F19] p-4 rounded-xl border border-gray-800 text-[11px] font-mono text-gray-300 overflow-x-auto"><code>curl <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <span class="text-blue-400" x-text="apiToken"></span>" \
  -d '{
    "model": "claude-3-5-sonnet",
    "messages": [{"role": "user", "content": "Xin chào"}]
  }'</code></pre>
                    </div>
                </div>

                <!-- Bảng danh sách Model khả dụng -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl">
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-layer-group text-purple-400"></i> Danh Sách Model Định Tuyến Hỗ Trợ
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-800">
                                <tr>
                                    <th class="py-2">Mã Model (ID)</th>
                                    <th class="py-2">Mô Tả</th>
                                    <th class="py-2 text-right">Trạng Thái</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/60 font-mono text-[11px]">
                                <tr>
                                    <td class="py-2.5 text-blue-400 font-bold">claude-3-5-sonnet</td>
                                    <td class="py-2.5 text-gray-300 font-sans">Claude 3.5 Sonnet đỉnh cao lập trình</td>
                                    <td class="py-2.5 text-right"><span class="px-2 py-0.5 rounded bg-green-500/10 text-green-400 font-sans font-semibold">Online</span></td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 text-blue-400 font-bold">deepseek-reasoner</td>
                                    <td class="py-2.5 text-gray-300 font-sans">DeepSeek R1 suy luận chuỗi tư duy</td>
                                    <td class="py-2.5 text-right"><span class="px-2 py-0.5 rounded bg-green-500/10 text-green-400 font-sans font-semibold">Online</span></td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 text-blue-400 font-bold">deepseek-chat</td>
                                    <td class="py-2.5 text-gray-300 font-sans">DeepSeek V3 phản hồi siêu tốc</td>
                                    <td class="py-2.5 text-right"><span class="px-2 py-0.5 rounded bg-green-500/10 text-green-400 font-sans font-semibold">Online</span></td>
                                </tr>
                                <tr>
                                    <td class="py-2.5 text-blue-400 font-bold">cursor-small</td>
                                    <td class="py-2.5 text-gray-300 font-sans">Mô hình gợi ý code nhanh Cursor</td>
                                    <td class="py-2.5 text-right"><span class="px-2 py-0.5 rounded bg-green-500/10 text-green-400 font-sans font-semibold">Online</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. TRÌNH THỬ NGHIỆM API TRỰC TIẾP (API PLAYGROUND) -->
            <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 shadow-xl flex flex-col">
                <div class="border-b border-gray-800 pb-3 mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <i class="fas fa-play text-green-400"></i> Trình Thử Nghiệm API Trực Tiếp
                        </h3>
                        <p class="text-[11px] text-gray-400">Gửi request kiểm tra Gateway phản hồi ngay trên trình duyệt</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-green-500/10 border border-green-500/20 text-green-400 text-[10px] font-bold">
                        <i class="fas fa-circle text-[8px] mr-1"></i> Live
                    </span>
                </div>

                <div class="space-y-4 flex-1 flex flex-col">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Chọn Model Test</label>
                        <select x-model="modelChon" class="w-full bg-[#1E293B] border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500">
                            <option value="cursor-small">cursor-small (Gợi ý code nhanh)</option>
                            <option value="claude-3-5-sonnet">claude-3-5-sonnet (Claude 3.5 Sonnet)</option>
                            <option value="deepseek-reasoner">deepseek-reasoner (DeepSeek R1)</option>
                            <option value="deepseek-chat">deepseek-chat (DeepSeek V3)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Nội Dung Prompt Thử Nghiệm</label>
                        <textarea x-model="noiDungPrompt" rows="3" class="w-full bg-[#1E293B] border border-gray-700 rounded-xl p-3 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-blue-500" placeholder="Nhập câu hỏi thử nghiệm..."></textarea>
                    </div>

                    <button @click="guiThuNghiemApi()" :disabled="dangTestApi" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs transition duration-150 shadow-md shadow-blue-600/30 flex items-center justify-center gap-2">
                        <span x-show="!dangTestApi"><i class="fas fa-paper-plane"></i> Gửi Thử Nghiệm API</span>
                        <span x-show="dangTestApi"><i class="fas fa-circle-notch fa-spin"></i> Đang gửi yêu cầu...</span>
                    </button>

                    <!-- Kết quả trả về từ API -->
                    <div class="flex-1 flex flex-col mt-2">
                        <div class="flex items-center justify-between text-xs text-gray-400 mb-1.5">
                            <span>Phản hồi từ Gateway:</span>
                            <span x-show="thoiGianTest" class="text-green-400 font-mono text-[10px]" x-text="'Mã: 200 OK | ' + thoiGianTest"></span>
                        </div>
                        <div class="flex-1 min-h-[160px] bg-[#0B0F19] border border-gray-800 rounded-xl p-3 font-mono text-xs text-gray-300 overflow-y-auto">
                            <span x-show="!ketQuaPhanHoi && !dangTestApi" class="text-gray-600 italic">Bấm "Gửi Thử Nghiệm API" để xem dữ liệu phản hồi...</span>
                            <span x-show="dangTestApi" class="text-blue-400"><i class="fas fa-spinner fa-spin mr-1"></i> Đang chờ máy chủ phản hồi...</span>
                            <div x-show="ketQuaPhanHoi" class="text-emerald-400 whitespace-pre-wrap" x-text="ketQuaPhanHoi"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- FOOTER CHÂN TRANG ĐỒNG BỘ TOÀN BỘ WEBSITE -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
    function ungDungApiDocs() {
        return {
            apiToken: '<?= htmlspecialchars($user['api_token']) ?>',
            hienToken: false,
            dangTaoToken: false,
            tabCode: 'cursor',

            modelChon: 'cursor-small',
            noiDungPrompt: 'Xin chào, kiểm tra kết nối API Gateway của VanKhaShop!',
            dangTestApi: false,
            ketQuaPhanHoi: '',
            thoiGianTest: '',

            saoChep(vanBan) {
                navigator.clipboard.writeText(vanBan);
                alert('Đã sao chép vào bộ nhớ tạm!');
            },

            async taoMoiToken() {
                if (!confirm('Bạn có chắc muốn tạo mới API Token? Khóa cũ sẽ bị vô hiệu hóa ngay lập tức!')) return;
                this.dangTaoToken = true;
                try {
                    const res = await fetch('/backend/api.php?action=tao_moi_api_token', { method: 'POST' });
                    const data = await res.json();
                    if (data.thanh_cong) {
                        this.apiToken = data.token_moi;
                        alert('Đã sinh khóa API Token mới thành công!');
                    } else {
                        alert(data.thong_bao || 'Lỗi không thể sinh token');
                    }
                } catch (e) {
                    alert('Lỗi kết nối máy chủ');
                } finally {
                    this.dangTaoToken = false;
                }
            },

            async guiThuNghiemApi() {
                this.dangTestApi = true;
                this.ketQuaPhanHoi = '';
                this.thoiGianTest = '';

                const form = new FormData();
                form.append('model', this.modelChon);
                form.append('message', this.noiDungPrompt);

                try {
                    const res = await fetch('/backend/api.php?action=test_chat_proxy', {
                        method: 'POST',
                        body: form
                    });
                    const data = await res.json();
                    if (data.thanh_cong) {
                        this.ketQuaPhanHoi = data.response;
                        this.thoiGianTest = data.timestamp;
                    } else {
                        this.ketQuaPhanHoi = 'Lỗi: ' + (data.thong_bao || 'Không nhận được dữ liệu');
                    }
                } catch (e) {
                    this.ketQuaPhanHoi = 'Lỗi kết nối đến máy chủ Gateway';
                } finally {
                    this.dangTestApi = false;
                }
            }
        }
    }
    </script>
</body>
</html>
