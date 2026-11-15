<?php
// ========================================================
// TRANG TÀI LIỆU TÍCH HỢP AI AGENT - API GUIDE
// tiemai.io.vn - Hướng dẫn kết nối mọi IDE & AI Agent
// Hỗ trợ: Claude, Cursor, Kilo, Codex, Cline, Windsurf, Continue, Aider...
// Base URL dùng chung: https://tiemai.io.vn/v1
// Ghi chú code 100% bằng Tiếng Việt dễ hiểu
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài Liệu Tích Hợp AI Agent - <?= SHOP_NAME ?> | Hướng dẫn kết nối Claude, Cursor, Kilo, Codex</title>
    <meta name="description" content="Hướng dẫn kết nối các AI Agent (Claude, Cursor, Kilo, Codex, Cline, Windsurf...) với Base URL https://tiemai.io.vn/v1. Cài đặt một lần dùng mọi nơi.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        pre, code { font-family: 'Fira Code', 'Cascadia Code', Consolas, Monaco, monospace; }
        pre { overflow-x: auto; }
        .tab-active { background: linear-gradient(135deg, #1e40af, #4f46e5); border-color: #3b82f6; color: #fff; }
        .tab-inactive { background: #111827; border-color: #374151; color: #9ca3af; }
        .tab-inactive:hover { border-color: #6b7280; color: #e5e7eb; }
        .copy-btn { transition: all .2s; }
        .copy-btn:hover { opacity: .8; }
        .gradient-border { background: linear-gradient(135deg, rgba(59,130,246,.15), rgba(139,92,246,.15)); border: 1px solid rgba(99,102,241,.3); }
        .ide-card { transition: all .2s; }
        .ide-card:hover { border-color: #3b82f6; transform: translateY(-2px); }
    </style>
</head>
<body x-data="ungDungApiGuide()" class="min-h-screen flex flex-col antialiased">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full flex-1 space-y-10">

        <!-- HERO -->
        <div class="border-b border-gray-800 pb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-bold uppercase tracking-wider mb-4">
                <i class="fas fa-plug"></i> Docs Tích Hợp AI Agent
            </div>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                Kết Nối AI Agent Chỉ Một Base URL
            </h1>
            <p class="text-base text-gray-400 mt-2 max-w-3xl leading-relaxed">
                Dùng <code class="bg-gray-800 px-2 py-0.5 rounded text-blue-400 text-sm font-bold">https://tiemai.io.vn/v1</code> làm điểm truy cập duy nhất cho <strong>mọi IDE</strong> và <strong>AI Agent</strong> — Claude, Cursor, Kilo, Codex, Cline, Windsurf, Continue, Aider và nhiều hơn nữa.
            </p>

            <!-- Badge thống kê nhanh -->
            <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold">
                <span class="px-3 py-1.5 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse inline-block"></span> Hệ thống đang hoạt động 24/7
                </span>
                <span class="px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-300 flex items-center gap-1.5">
                    <i class="fas fa-shield-halved"></i> HTTPS & Bearer Token
                </span>
                <span class="px-3 py-1.5 rounded-lg bg-purple-500/10 border border-purple-500/30 text-purple-300 flex items-center gap-1.5">
                    <i class="fas fa-code-branch"></i> OpenAI-Compatible API
                </span>
                <span class="px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-center gap-1.5">
                    <i class="fas fa-bolt"></i> Streaming SSE
                </span>
            </div>
        </div>

        <!-- BASE URL & XÁC THỰC -->
        <div class="gradient-border rounded-2xl p-6 space-y-5">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-globe text-blue-400"></i> Base URL & Xác Thực
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 space-y-2">
                    <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Base URL (OpenAI Compatible)</div>
                    <div class="flex items-center gap-2">
                        <code class="text-green-400 font-bold text-sm flex-1 truncate">https://tiemai.io.vn/v1</code>
                        <button @click="copy('https://tiemai.io.vn/v1', 'baseurl')" class="copy-btn px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                            <i class="fas" :class="copiedId === 'baseurl' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                        </button>
                    </div>
                </div>
                <div class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 space-y-2">
                    <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Endpoints hỗ trợ</div>
                    <div class="space-y-1 text-xs font-mono">
                        <div class="flex gap-2"><span class="text-blue-400">POST</span><span class="text-gray-200">/v1/chat/completions</span></div>
                        <div class="flex gap-2"><span class="text-blue-400">POST</span><span class="text-gray-200">/v1/messages</span> <span class="text-gray-500 text-[10px]">(Anthropic native)</span></div>
                        <div class="flex gap-2"><span class="text-green-400">GET</span><span class="text-gray-200">/v1/models</span></div>
                        <div class="flex gap-2"><span class="text-green-400">GET</span><span class="text-gray-200">/v1/usage</span></div>
                    </div>
                </div>
            </div>

            <!-- Header xác thực -->
            <div class="bg-[#0B0F19] border border-blue-900/40 rounded-xl p-4 space-y-3">
                <div class="text-xs font-bold text-white">Cách truyền API Key (3 cách đều được hỗ trợ):</div>
                <div class="space-y-2">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] flex items-center justify-center font-bold mt-0.5">1</span>
                        <div>
                            <div class="text-xs text-gray-300 font-semibold">Authorization Bearer (phổ biến nhất — Cursor, Kilo, Codex)</div>
                            <code class="text-xs text-blue-300 font-mono">Authorization: Bearer sk-your-api-key-here</code>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-purple-600 text-white text-[10px] flex items-center justify-center font-bold mt-0.5">2</span>
                        <div>
                            <div class="text-xs text-gray-300 font-semibold">x-api-key header (Claude CLI, Anthropic SDK, Cline)</div>
                            <code class="text-xs text-purple-300 font-mono">x-api-key: sk-your-api-key-here</code>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-gray-600 text-white text-[10px] flex items-center justify-center font-bold mt-0.5">3</span>
                        <div>
                            <div class="text-xs text-gray-300 font-semibold">Query param (thử nghiệm nhanh trên trình duyệt)</div>
                            <code class="text-xs text-gray-300 font-mono">?key=sk-your-api-key-here</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS HƯỚNG DẪN TỪNG IDE -->
        <div class="space-y-5">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-laptop-code text-purple-400"></i> Hướng Dẫn Cài Đặt Từng IDE & Tool
            </h2>

            <!-- Tabs chọn IDE -->
            <div class="flex flex-wrap gap-2">
                <?php
                $danhSachTabs = [
                    ['id' => 'claude',    'icon' => 'fa-brands fa-square-js',       'ten' => 'Claude Code',   'mau' => 'orange'],
                    ['id' => 'cursor',    'icon' => 'fas fa-mouse-pointer',           'ten' => 'Cursor IDE',    'mau' => 'blue'],
                    ['id' => 'kilo',      'icon' => 'fas fa-code',                    'ten' => 'Kilo Code',     'mau' => 'indigo'],
                    ['id' => 'codex',     'icon' => 'fas fa-terminal',                'ten' => 'OpenAI Codex',  'mau' => 'green'],
                    ['id' => 'cline',     'icon' => 'fas fa-puzzle-piece',            'ten' => 'Cline / Roo',   'mau' => 'cyan'],
                    ['id' => 'windsurf',  'icon' => 'fas fa-wind',                    'ten' => 'Windsurf',      'mau' => 'teal'],
                    ['id' => 'continue',  'icon' => 'fas fa-play-circle',             'ten' => 'Continue.dev',  'mau' => 'violet'],
                    ['id' => 'aider',     'icon' => 'fas fa-robot',                   'ten' => 'Aider',         'mau' => 'pink'],
                    ['id' => 'python',    'icon' => 'fab fa-python',                  'ten' => 'Python / SDK',  'mau' => 'yellow'],
                ];
                foreach ($danhSachTabs as $tab):
                ?>
                    <button
                        @click="tabHienTai = '<?= $tab['id'] ?>'"
                        :class="tabHienTai === '<?= $tab['id'] ?>' ? 'tab-active' : 'tab-inactive'"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-bold transition">
                        <i class="<?= $tab['icon'] ?>"></i>
                        <span><?= $tab['ten'] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- === CLAUDE CODE === -->
            <div x-show="tabHienTai === 'claude'" class="space-y-4">
                <div class="bg-[#111827] border border-orange-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-lg"><i class="fas fa-terminal"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Claude Code (Anthropic CLI)</div>
                            <div class="text-xs text-gray-400">Dùng flag <code class="bg-gray-800 px-1 rounded">--api-url</code> khi khởi động</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cách 1: Dùng flag trực tiếp</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code>claude --api-url https://tiemai.io.vn/v1 \
  --api-key sk-your-api-key-here</code></pre>
                            <button @click="copy('claude --api-url https://tiemai.io.vn/v1 --api-key sk-your-api-key-here', 'claude1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'claude1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cách 2: Biến môi trường (Khuyên dùng)</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code># Thêm vào ~/.bashrc hoặc ~/.zshrc
export ANTHROPIC_BASE_URL=https://tiemai.io.vn/v1
export ANTHROPIC_API_KEY=sk-your-api-key-here

# Rồi chạy bình thường
claude</code></pre>
                            <button @click="copy('export ANTHROPIC_BASE_URL=https://tiemai.io.vn/v1\nexport ANTHROPIC_API_KEY=sk-your-api-key-here', 'claude2')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'claude2' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cách 3: File cấu hình ~/.claude/settings.json</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 overflow-x-auto"><code>{
  "apiUrl": "https://tiemai.io.vn/v1",
  "apiKey": "sk-your-api-key-here"
}</code></pre>
                            <button @click="copy('{\"apiUrl\":\"https://tiemai.io.vn/v1\",\"apiKey\":\"sk-your-api-key-here\"}', 'claude3')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'claude3' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>
                    </div>
                    <div class="bg-orange-950/20 border border-orange-800/30 rounded-xl p-3 text-xs text-orange-300">
                        <i class="fas fa-info-circle mr-1"></i> Hệ thống tự động nhận diện model Claude và chuyển tiếp đúng gateway. Các model như <code>claude-3-5-sonnet</code>, <code>claude-opus-4</code> đều được ánh xạ tự động.
                    </div>
                </div>
            </div>

            <!-- === CURSOR IDE === -->
            <div x-show="tabHienTai === 'cursor'" class="space-y-4">
                <div class="bg-[#111827] border border-blue-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-lg"><i class="fas fa-mouse-pointer"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Cursor IDE</div>
                            <div class="text-xs text-gray-400">Cài đặt tại Settings → Models → OpenAI API Key</div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="text-sm font-bold text-white">Các bước cài đặt:</div>
                        <ol class="space-y-3">
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] flex items-center justify-center font-bold">1</span>
                                <div class="text-xs text-gray-300">Mở Cursor → <strong class="text-white">Settings</strong> (Ctrl+Shift+J) → Tab <strong class="text-white">Models</strong></div>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] flex items-center justify-center font-bold">2</span>
                                <div class="text-xs text-gray-300">Kéo xuống phần <strong class="text-white">OpenAI API Key</strong> → Nhập API Key của bạn vào ô</div>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] flex items-center justify-center font-bold">3</span>
                                <div class="text-xs text-gray-300">Kéo xuống tìm ô <strong class="text-white">Override OpenAI Base URL</strong> → Điền:</div>
                            </li>
                        </ol>
                        <div class="relative ml-9">
                            <pre class="bg-[#0B0F19] border border-blue-900/40 rounded-xl p-3 text-sm text-green-400 font-mono"><code>https://tiemai.io.vn/v1</code></pre>
                            <button @click="copy('https://tiemai.io.vn/v1', 'cursor1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'cursor1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>
                        <ol class="space-y-3" start="4">
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] flex items-center justify-center font-bold">4</span>
                                <div class="text-xs text-gray-300">Bấm <strong class="text-white">Verify</strong> → Nếu hiện danh sách model là thành công!</div>
                            </li>
                        </ol>
                    </div>

                    <div class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 space-y-2">
                        <div class="text-xs font-bold text-white">Cấu hình qua file cursor/.cursor-settings (nếu dùng team config):</div>
                        <div class="relative">
                            <pre class="text-xs text-blue-300 overflow-x-auto"><code>{
  "openai.apiBaseUrl": "https://tiemai.io.vn/v1",
  "openai.apiKey": "sk-your-api-key-here"
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === KILO CODE === -->
            <div x-show="tabHienTai === 'kilo'" class="space-y-4">
                <div class="bg-[#111827] border border-indigo-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-lg"><i class="fas fa-code"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Kilo Code (VS Code Extension)</div>
                            <div class="text-xs text-gray-400">Cài đặt qua VS Code Settings hoặc kilo.json</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cách 1: VS Code Settings UI</div>
                        <ol class="space-y-2 text-xs text-gray-300">
                            <li class="flex gap-2"><span class="text-indigo-400 font-bold">→</span> Mở VS Code → Extensions → Kilo Code → Settings</li>
                            <li class="flex gap-2"><span class="text-indigo-400 font-bold">→</span> Chọn Provider: <strong class="text-white">OpenAI Compatible</strong></li>
                            <li class="flex gap-2"><span class="text-indigo-400 font-bold">→</span> Base URL: <code class="text-green-400">https://tiemai.io.vn/v1</code></li>
                            <li class="flex gap-2"><span class="text-indigo-400 font-bold">→</span> API Key: Nhập key của bạn</li>
                        </ol>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cách 2: File kilo.json (Workspace config)</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 overflow-x-auto"><code>{
  "providers": {
    "openai-compatible": {
      "baseUrl": "https://tiemai.io.vn/v1",
      "apiKey": "sk-your-api-key-here"
    }
  },
  "defaultModel": "claude-sonnet-5"
}</code></pre>
                            <button @click="copy('{\"providers\":{\"openai-compatible\":{\"baseUrl\":\"https://tiemai.io.vn/v1\",\"apiKey\":\"sk-your-api-key-here\"}},\"defaultModel\":\"claude-sonnet-5\"}', 'kilo1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'kilo1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cách 3: .kilo/agent/*.md — Override trong Agent config</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code># Trong file .kilo/agent/my-agent.md
# Khai báo provider sử dụng custom gateway
provider: openai-compatible
baseUrl: https://tiemai.io.vn/v1
apiKey: sk-your-api-key-here
model: claude-sonnet-5</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === CODEX CLI === -->
            <div x-show="tabHienTai === 'codex'" class="space-y-4">
                <div class="bg-[#111827] border border-green-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-green-500/20 text-green-400 flex items-center justify-center text-lg"><i class="fas fa-terminal"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">OpenAI Codex CLI</div>
                            <div class="text-xs text-gray-400">Dùng biến môi trường hoặc flag --base-url</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Biến môi trường (Khuyên dùng)</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code># Thêm vào ~/.bashrc hoặc ~/.zshrc hoặc ~/.profile
export OPENAI_BASE_URL=https://tiemai.io.vn/v1
export OPENAI_API_KEY=sk-your-api-key-here

# Dùng Codex bình thường
codex "viết hàm đọc file JSON"</code></pre>
                            <button @click="copy('export OPENAI_BASE_URL=https://tiemai.io.vn/v1\nexport OPENAI_API_KEY=sk-your-api-key-here', 'codex1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'codex1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Flag trực tiếp</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code>codex --base-url https://tiemai.io.vn/v1 \
  --api-key sk-your-api-key-here \
  "viết test cho function tính tổng"</code></pre>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">File cấu hình ~/.codex/config.json</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 overflow-x-auto"><code>{
  "apiBase": "https://tiemai.io.vn/v1",
  "apiKey": "sk-your-api-key-here",
  "model": "gpt-5.6-sol"
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === CLINE / ROO === -->
            <div x-show="tabHienTai === 'cline'" class="space-y-4">
                <div class="bg-[#111827] border border-cyan-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-lg"><i class="fas fa-puzzle-piece"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Cline / Roo Code (VS Code Extension)</div>
                            <div class="text-xs text-gray-400">Cài đặt trong Extension Settings — chọn OpenAI Compatible</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <ol class="space-y-3 text-xs text-gray-300">
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-cyan-600 text-white text-[11px] flex items-center justify-center font-bold">1</span>
                                Mở VS Code → Cline/Roo sidebar → Biểu tượng <strong class="text-white">Settings ⚙️</strong>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-cyan-600 text-white text-[11px] flex items-center justify-center font-bold">2</span>
                                Chọn <strong class="text-white">API Provider</strong>: <code class="bg-gray-800 px-1 rounded">OpenAI Compatible</code>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-cyan-600 text-white text-[11px] flex items-center justify-center font-bold">3</span>
                                <div>
                                    <div>Điền các trường sau:</div>
                                    <div class="mt-2 bg-[#0B0F19] border border-gray-800 rounded-xl p-3 space-y-1.5">
                                        <div class="flex gap-2"><span class="text-gray-500 w-32">Base URL:</span><code class="text-green-400">https://tiemai.io.vn/v1</code></div>
                                        <div class="flex gap-2"><span class="text-gray-500 w-32">API Key:</span><code class="text-blue-400">sk-your-api-key-here</code></div>
                                        <div class="flex gap-2"><span class="text-gray-500 w-32">Model ID:</span><code class="text-purple-400">claude-sonnet-5</code></div>
                                    </div>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-cyan-600 text-white text-[11px] flex items-center justify-center font-bold">4</span>
                                Bấm <strong class="text-white">Save</strong> → Thử gửi tin nhắn đầu tiên
                            </li>
                        </ol>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Cài qua VS Code settings.json (tự động):</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 overflow-x-auto"><code>{
  "cline.apiProvider": "openai",
  "cline.openAiBaseUrl": "https://tiemai.io.vn/v1",
  "cline.openAiApiKey": "sk-your-api-key-here",
  "cline.openAiModelId": "claude-sonnet-5"
}</code></pre>
                            <button @click="copy('{\"cline.apiProvider\":\"openai\",\"cline.openAiBaseUrl\":\"https://tiemai.io.vn/v1\",\"cline.openAiApiKey\":\"sk-your-api-key-here\",\"cline.openAiModelId\":\"claude-sonnet-5\"}', 'cline1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'cline1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === WINDSURF === -->
            <div x-show="tabHienTai === 'windsurf'" class="space-y-4">
                <div class="bg-[#111827] border border-teal-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-teal-500/20 text-teal-400 flex items-center justify-center text-lg"><i class="fas fa-wind"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Windsurf (by Codeium)</div>
                            <div class="text-xs text-gray-400">Cài đặt qua Windsurf Settings → AI Provider</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <ol class="space-y-3 text-xs text-gray-300">
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">1</span>
                                Mở Windsurf → <strong class="text-white">Settings</strong> → <strong class="text-white">Windsurf Settings</strong>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">2</span>
                                Phần <strong class="text-white">AI Provider</strong> → Chọn <code class="bg-gray-800 px-1 rounded">Custom OpenAI</code> hoặc <code class="bg-gray-800 px-1 rounded">OpenAI Compatible</code>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">3</span>
                                <div>
                                    Nhập:<br>
                                    <div class="mt-2 bg-[#0B0F19] border border-gray-800 rounded-xl p-3 space-y-1.5 text-xs font-mono">
                                        <div><span class="text-gray-500">Base URL: </span><span class="text-green-400">https://tiemai.io.vn/v1</span></div>
                                        <div><span class="text-gray-500">API Key:  </span><span class="text-blue-400">sk-your-api-key-here</span></div>
                                    </div>
                                </div>
                            </li>
                        </ol>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Biến môi trường:</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code>export OPENAI_API_BASE=https://tiemai.io.vn/v1
export OPENAI_API_KEY=sk-your-api-key-here</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === CONTINUE.DEV === -->
            <div x-show="tabHienTai === 'continue'" class="space-y-4">
                <div class="bg-[#111827] border border-violet-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center text-lg"><i class="fas fa-play-circle"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Continue.dev (VS Code / JetBrains)</div>
                            <div class="text-xs text-gray-400">Cài đặt qua ~/.continue/config.json</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">File ~/.continue/config.json</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 overflow-x-auto"><code>{
  "models": [
    {
      "title": "TiemAI Claude Sonnet",
      "provider": "openai",
      "model": "claude-sonnet-5",
      "apiBase": "https://tiemai.io.vn/v1",
      "apiKey": "sk-your-api-key-here"
    },
    {
      "title": "TiemAI GPT-5.6",
      "provider": "openai",
      "model": "gpt-5.6-sol",
      "apiBase": "https://tiemai.io.vn/v1",
      "apiKey": "sk-your-api-key-here"
    }
  ],
  "tabAutocompleteModel": {
    "title": "TiemAI Autocomplete",
    "provider": "openai",
    "model": "gpt-5.4-mini",
    "apiBase": "https://tiemai.io.vn/v1",
    "apiKey": "sk-your-api-key-here"
  }
}</code></pre>
                            <button @click="copy('~/.continue/config.json', 'cont1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'cont1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === AIDER === -->
            <div x-show="tabHienTai === 'aider'" class="space-y-4">
                <div class="bg-[#111827] border border-pink-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-pink-500/20 text-pink-400 flex items-center justify-center text-lg"><i class="fas fa-robot"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Aider (AI Pair Programming)</div>
                            <div class="text-xs text-gray-400">Dùng flag --openai-api-base hoặc biến môi trường</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Chạy trực tiếp</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code>aider --openai-api-base https://tiemai.io.vn/v1 \
  --openai-api-key sk-your-api-key-here \
  --model claude-sonnet-5</code></pre>
                            <button @click="copy('aider --openai-api-base https://tiemai.io.vn/v1 --openai-api-key sk-your-api-key-here --model claude-sonnet-5', 'aider1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'aider1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">File .aider.conf.yml (trong thư mục project)</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-yellow-300 overflow-x-auto"><code>openai-api-base: https://tiemai.io.vn/v1
openai-api-key: sk-your-api-key-here
model: claude-sonnet-5</code></pre>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Biến môi trường</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code>export OPENAI_API_BASE=https://tiemai.io.vn/v1
export OPENAI_API_KEY=sk-your-api-key-here
aider --model claude-sonnet-5</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === PYTHON / SDK === -->
            <div x-show="tabHienTai === 'python'" class="space-y-4">
                <div class="bg-[#111827] border border-yellow-900/40 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/20 text-yellow-400 flex items-center justify-center text-lg"><i class="fab fa-python"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Python / SDK / HTTP</div>
                            <div class="text-xs text-gray-400">openai SDK, anthropic SDK, requests, curl</div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">openai Python SDK</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-yellow-300 overflow-x-auto"><code>from openai import OpenAI

client = OpenAI(
    base_url="https://tiemai.io.vn/v1",
    api_key="sk-your-api-key-here"
)

response = client.chat.completions.create(
    model="claude-sonnet-5",
    messages=[{"role": "user", "content": "Xin chào!"}],
    stream=True
)

for chunk in response:
    if chunk.choices[0].delta.content:
        print(chunk.choices[0].delta.content, end="")</code></pre>
                            <button @click="copy('from openai import OpenAI\n\nclient = OpenAI(base_url=\"https://tiemai.io.vn/v1\", api_key=\"sk-your-api-key-here\")\n\nresponse = client.chat.completions.create(model=\"claude-sonnet-5\", messages=[{\"role\": \"user\", \"content\": \"Xin chao!\"}])', 'py1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'py1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">anthropic Python SDK (native /v1/messages)</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-orange-300 overflow-x-auto"><code>import anthropic

client = anthropic.Anthropic(
    base_url="https://tiemai.io.vn/v1",
    api_key="sk-your-api-key-here"
)

message = client.messages.create(
    model="claude-sonnet-5",
    max_tokens=1024,
    messages=[{"role": "user", "content": "Hello, World!"}]
)
print(message.content)</code></pre>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">curl (thử nghiệm nhanh)</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 overflow-x-auto"><code>curl https://tiemai.io.vn/v1/chat/completions \
  -H "Authorization: Bearer sk-your-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "claude-sonnet-5",
    "messages": [{"role": "user", "content": "Xin chao!"}],
    "stream": false
  }'</code></pre>
                            <button @click="copy('curl https://tiemai.io.vn/v1/chat/completions -H \"Authorization: Bearer sk-your-api-key-here\" -H \"Content-Type: application/json\" -d \'{\"model\":\"claude-sonnet-5\",\"messages\":[{\"role\":\"user\",\"content\":\"Xin chao!\"}]}\'', 'curl1')" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'curl1' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BẢNG MODEL HỖ TRỢ -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-microchip text-purple-400"></i> Danh Sách Model Hỗ Trợ
            </h2>
            <p class="text-xs text-gray-400">Tất cả model dưới đây đều có thể dùng với Base URL <code class="text-blue-400">https://tiemai.io.vn/v1</code>. Hệ thống sẽ tự động ánh xạ model cũ sang model tương đương mới nhất.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <!-- Claude Models -->
                <div class="bg-[#0B0F19] border border-orange-900/30 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-sm font-bold text-orange-300 mb-2">
                        <i class="fas fa-square text-orange-400 text-xs"></i> Anthropic Claude
                    </div>
                    <div class="space-y-1 text-xs font-mono">
                        <div class="flex items-center justify-between"><span class="text-gray-200">claude-sonnet-5</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">claude-opus-5.5</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">claude-fable-5</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-400 line-through">claude-3-5-sonnet</span><span class="text-blue-400 text-[10px]">↳ sonnet-5</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-400 line-through">claude-3-opus</span><span class="text-blue-400 text-[10px]">↳ opus-5.5</span></div>
                    </div>
                </div>

                <!-- OpenAI / GPT Models -->
                <div class="bg-[#0B0F19] border border-green-900/30 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-sm font-bold text-green-300 mb-2">
                        <i class="fas fa-circle text-green-400 text-xs"></i> OpenAI / GPT
                    </div>
                    <div class="space-y-1 text-xs font-mono">
                        <div class="flex items-center justify-between"><span class="text-gray-200">gpt-5.6-sol</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">gpt-6.1-sol</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">gpt-5.6-luna</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">gpt-5.6-terra</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">gpt-5.4-mini</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-400 line-through">gpt-4o</span><span class="text-blue-400 text-[10px]">↳ gpt-5.6-sol</span></div>
                    </div>
                </div>

                <!-- Khác -->
                <div class="bg-[#0B0F19] border border-blue-900/30 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2 text-sm font-bold text-blue-300 mb-2">
                        <i class="fas fa-sparkles text-blue-400 text-xs"></i> DeepSeek & Khác
                    </div>
                    <div class="space-y-1 text-xs font-mono">
                        <div class="flex items-center justify-between"><span class="text-gray-200">deepseek-reasoner</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">deepseek-chat</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-200">cursor-small</span><span class="text-green-400 text-[10px]">● Active</span></div>
                        <div class="flex items-center justify-between text-gray-500 text-[10px]"><span class="italic">Và nhiều model khác...</span><span></span></div>
                    </div>
                    <a href="/bang-gia-model" class="inline-flex items-center gap-1 text-[11px] text-blue-400 hover:text-blue-300 font-semibold mt-2">
                        <i class="fas fa-arrow-right text-[9px]"></i> Xem đầy đủ bảng giá model
                    </a>
                </div>
            </div>

            <div class="bg-blue-950/20 border border-blue-800/30 rounded-xl p-3 text-xs text-blue-300">
                <i class="fas fa-wand-magic-sparkles mr-1 text-blue-400"></i>
                <strong>Auto Model Mapping:</strong> Hệ thống tự động chuyển model cũ (vd: <code>claude-3-5-sonnet-20241022</code>, <code>gpt-4-turbo</code>) sang model mới tương đương. Không cần lo về tên model chính xác.
            </div>
        </div>

        <!-- CÁC IDE & TOOL KHÁC (QUICK REFERENCE) -->
        <div class="space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-grid-2 text-gray-400"></i> Các Tool & IDE Khác (Quick Reference)
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- GitHub Copilot Alternative -->
                <div class="bg-[#111827] border border-gray-800 rounded-xl p-4 ide-card">
                    <div class="flex items-center gap-2 font-bold text-sm text-white mb-2">
                        <i class="fab fa-github text-gray-400"></i> VS Code + OpenAI Extension
                    </div>
                    <code class="text-xs text-gray-400 block">settings.json:</code>
                    <pre class="text-xs text-blue-300 mt-1"><code>"openai.baseURL": "https://tiemai.io.vn/v1",
"openai.apiKey": "sk-your-key"</code></pre>
                </div>

                <!-- LM Studio / Jan.ai -->
                <div class="bg-[#111827] border border-gray-800 rounded-xl p-4 ide-card">
                    <div class="flex items-center gap-2 font-bold text-sm text-white mb-2">
                        <i class="fas fa-server text-purple-400"></i> LM Studio / Jan.ai / Ollama Bridge
                    </div>
                    <div class="text-xs text-gray-400 mb-1">Remote Server URL:</div>
                    <code class="text-xs text-green-400">https://tiemai.io.vn/v1</code>
                </div>

                <!-- OpenHands / Devin -->
                <div class="bg-[#111827] border border-gray-800 rounded-xl p-4 ide-card">
                    <div class="flex items-center gap-2 font-bold text-sm text-white mb-2">
                        <i class="fas fa-hands text-blue-400"></i> OpenHands (trước là OpenDevin)
                    </div>
                    <pre class="text-xs text-green-300"><code>LLM_BASE_URL=https://tiemai.io.vn/v1
LLM_API_KEY=sk-your-key
LLM_MODEL=claude-sonnet-5</code></pre>
                </div>

                <!-- Tabby / FauxPilot -->
                <div class="bg-[#111827] border border-gray-800 rounded-xl p-4 ide-card">
                    <div class="flex items-center gap-2 font-bold text-sm text-white mb-2">
                        <i class="fas fa-cat text-amber-400"></i> Tabby / FauxPilot / SuperMaven
                    </div>
                    <div class="text-xs text-gray-400">Trỏ backend URL về:</div>
                    <code class="text-xs text-green-400">https://tiemai.io.vn/v1</code>
                    <div class="text-xs text-gray-500 mt-1">Bearer token trong Authorization header</div>
                </div>

                <!-- IntelliJ / JetBrains -->
                <div class="bg-[#111827] border border-gray-800 rounded-xl p-4 ide-card">
                    <div class="flex items-center gap-2 font-bold text-sm text-white mb-2">
                        <i class="fas fa-coffee text-red-400"></i> JetBrains AI Assistant
                    </div>
                    <div class="text-xs text-gray-400">Settings → AI Assistant → Custom Server:</div>
                    <code class="text-xs text-green-400 block mt-1">https://tiemai.io.vn/v1</code>
                </div>

                <!-- n8n / LangChain -->
                <div class="bg-[#111827] border border-gray-800 rounded-xl p-4 ide-card">
                    <div class="flex items-center gap-2 font-bold text-sm text-white mb-2">
                        <i class="fas fa-diagram-project text-green-400"></i> n8n / LangChain / LlamaIndex
                    </div>
                    <pre class="text-xs text-yellow-300"><code>OPENAI_API_BASE=https://tiemai.io.vn/v1
OPENAI_API_KEY=sk-your-key</code></pre>
                </div>
            </div>
        </div>

        <!-- KIỂM TRA KẾT NỐI TRỰC TIẾP -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-plug text-green-400"></i> Kiểm Tra Kết Nối Ngay
            </h2>
            <p class="text-xs text-gray-400">Dán API Key vào ô dưới đây để kiểm tra kết nối và xem thông tin usage của key.</p>

            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    x-model="apiKeyTest"
                    placeholder="Dán API Key tại đây: sk-..."
                    class="flex-1 bg-[#0B0F19] border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white font-mono placeholder-gray-600 focus:outline-none focus:border-blue-500 transition">
                <button
                    @click="kiemTraKetNoi()"
                    :disabled="dangKiemTra"
                    class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center gap-2 shrink-0 disabled:opacity-60">
                    <i class="fas fa-circle-notch fa-spin" x-show="dangKiemTra" style="display:none;"></i>
                    <i class="fas fa-plug" x-show="!dangKiemTra"></i>
                    Kiểm Tra
                </button>
            </div>

            <div x-show="ketQuaTest" class="bg-[#0B0F19] border rounded-xl p-4 space-y-2 text-xs font-mono transition-all"
                 :class="ketQuaTest?.ok ? 'border-green-800/50' : 'border-red-800/50'">
                <div class="flex items-center gap-2 font-bold text-sm mb-2" :class="ketQuaTest?.ok ? 'text-green-400' : 'text-red-400'">
                    <i class="fas" :class="ketQuaTest?.ok ? 'fa-check-circle' : 'fa-times-circle'"></i>
                    <span x-text="ketQuaTest?.ok ? 'Kết nối thành công!' : 'Kết nối thất bại'"></span>
                </div>
                <template x-if="ketQuaTest?.ok">
                    <div class="space-y-1.5">
                        <div class="flex gap-2"><span class="text-gray-500 w-32">Trạng thái:</span><span class="text-green-400 font-bold" x-text="ketQuaTest?.status"></span></div>
                        <div class="flex gap-2"><span class="text-gray-500 w-32">Gói dịch vụ:</span><span class="text-white" x-text="ketQuaTest?.package"></span></div>
                        <div class="flex gap-2"><span class="text-gray-500 w-32">Đã dùng:</span><span class="text-amber-400" x-text="ketQuaTest?.used_requests + ' requests'"></span></div>
                        <div class="flex gap-2"><span class="text-gray-500 w-32">Còn lại:</span><span class="text-blue-400 font-bold" x-text="ketQuaTest?.remaining_requests + ' requests'"></span></div>
                        <div class="flex gap-2"><span class="text-gray-500 w-32">Base URL:</span><span class="text-green-300" x-text="ketQuaTest?.gateway_base_url"></span></div>
                    </div>
                </template>
                <template x-if="!ketQuaTest?.ok">
                    <div class="text-red-400" x-text="ketQuaTest?.message"></div>
                </template>
            </div>
        </div>

        <!-- CÂU HỎI THƯỜNG GẶP -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-circle-question text-blue-400"></i> Câu Hỏi Thường Gặp (FAQ)
            </h2>
            <div class="space-y-3" x-data="{ mo: null }">
                <?php
                $faq = [
                    ['q' => 'Base URL phải nhập chính xác như thế nào?',
                     'a' => 'Nhập chính xác: <code class="text-green-400">https://tiemai.io.vn/v1</code> (không có dấu "/" ở cuối). Một số tool cần bỏ /v1 — thì dùng <code class="text-green-400">https://tiemai.io.vn</code>.'],
                    ['q' => 'Key của tôi dạng sk-... có dùng được không?',
                     'a' => 'Có. Mọi key mua tại tiemai.io.vn đều dạng <code class="text-blue-400">sk-...</code> và được hỗ trợ đầy đủ với tất cả các tool trên.'],
                    ['q' => 'Tôi đang dùng Claude 3.5 Sonnet, gateway có hỗ trợ không?',
                     'a' => 'Có. Hệ thống tự động ánh xạ <code class="text-gray-300">claude-3-5-sonnet-*</code> sang <code class="text-orange-400">claude-sonnet-5</code> — model mới nhất. Bạn không cần đổi cấu hình.'],
                    ['q' => 'Có hỗ trợ Streaming (SSE) không?',
                     'a' => 'Có. Thêm <code class="text-blue-300">"stream": true</code> vào request body. Hỗ trợ cả Streaming SSE và Non-streaming JSON.'],
                    ['q' => 'Lỗi 401 Unauthorized khi kết nối?',
                     'a' => 'Kiểm tra lại: (1) Key đã đúng chưa — dùng mục "Kiểm Tra Kết Nối" ở trên, (2) Header đúng là <code class="text-blue-300">Authorization: Bearer sk-...</code>, (3) Key chưa hết hạn.'],
                    ['q' => 'Dùng được bao nhiêu request mỗi ngày?',
                     'a' => 'Tùy gói bạn mua. Xem chi tiết tại <a href="/tra-cuu-key" class="text-blue-400 hover:underline">trang tra cứu key</a> hoặc gọi <code class="text-green-400">GET /v1/usage</code>.'],
                ];
                foreach ($faq as $i => $item):
                ?>
                <div class="border border-gray-800 rounded-xl overflow-hidden">
                    <button @click="mo = mo === <?= $i ?> ? null : <?= $i ?>"
                        class="w-full text-left px-4 py-3 flex items-center justify-between gap-3 hover:bg-gray-800/50 transition">
                        <span class="text-xs font-semibold text-white"><?= htmlspecialchars($item['q']) ?></span>
                        <i class="fas fa-chevron-down text-gray-500 text-xs transition-transform shrink-0" :class="mo === <?= $i ?> ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="mo === <?= $i ?>" x-transition class="px-4 pb-3 text-xs text-gray-400 leading-relaxed" style="display: none;">
                        <?= $item['a'] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LIÊN HỆ HỖ TRỢ -->
        <div class="gradient-border rounded-2xl p-6 text-center space-y-3">
            <i class="fas fa-headset text-3xl text-blue-400"></i>
            <h3 class="text-lg font-bold text-white">Cần Hỗ Trợ Kỹ Thuật?</h3>
            <p class="text-xs text-gray-400 max-w-md mx-auto">Đội ngũ hỗ trợ 24/7 qua Zalo và Telegram. Phản hồi trong vòng 15 phút.</p>
            <div class="flex flex-wrap justify-center gap-3 mt-2">
                <a href="https://zalo.me/<?= htmlspecialchars(SHOP_ZALO) ?>" target="_blank" rel="noopener noreferrer"
                   class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition flex items-center gap-2 shadow-md shadow-blue-600/30">
                    <i class="fas fa-comment-dots"></i> Zalo: <?= htmlspecialchars(SHOP_ZALO) ?>
                </a>
                <a href="<?= htmlspecialchars(SHOP_TELEGRAM) ?>" target="_blank" rel="noopener noreferrer"
                   class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition flex items-center gap-2 shadow-md shadow-sky-600/30">
                    <i class="fab fa-telegram"></i> Telegram
                </a>
                <a href="/san-pham"
                   class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 border border-gray-700 text-white text-xs font-bold transition flex items-center gap-2">
                    <i class="fas fa-shopping-cart"></i> Mua API Key
                </a>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungApiGuide() {
        return {
            tabHienTai: 'claude',
            copiedId: null,
            apiKeyTest: '',
            dangKiemTra: false,
            ketQuaTest: null,

            copy(text, id) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copiedId = id;
                    setTimeout(() => { this.copiedId = null; }, 1800);
                }).catch(() => {
                    // Fallback cũ cho trình duyệt không hỗ trợ
                    const el = document.createElement('textarea');
                    el.value = text;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                    this.copiedId = id;
                    setTimeout(() => { this.copiedId = null; }, 1800);
                });
            },

            async kiemTraKetNoi() {
                if (!this.apiKeyTest.trim()) {
                    alert('Vui lòng nhập API Key cần kiểm tra');
                    return;
                }
                this.dangKiemTra = true;
                this.ketQuaTest = null;
                try {
                    const res = await fetch('/v1/usage', {
                        headers: {
                            'Authorization': 'Bearer ' + this.apiKeyTest.trim(),
                            'Content-Type': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (res.ok && data.object === 'usage') {
                        this.ketQuaTest = { ok: true, ...data };
                    } else {
                        this.ketQuaTest = {
                            ok: false,
                            message: data?.error?.message || 'API Key không hợp lệ hoặc đã hết hạn'
                        };
                    }
                } catch (e) {
                    this.ketQuaTest = {
                        ok: false,
                        message: 'Không thể kết nối đến máy chủ. Kiểm tra lại mạng internet.'
                    };
                } finally {
                    this.dangKiemTra = false;
                }
            }
        };
    }
    </script>
</body>
</html>
