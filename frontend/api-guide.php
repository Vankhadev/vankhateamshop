<?php
// ========================================================
// TRANG TÀI LIỆU TÍCH HỢP AI AGENT - API GUIDE
// tiemai.io.vn - Hướng dẫn kết nối mọi IDE & AI Agent chuẩn 100%
// Hỗ trợ: Claude Code, Cursor, Kilo Code, Cline, Roo Code, Codex, Windsurf, Continue, Aider, Python...
// Base URL chuẩn: https://tiemai.io.vn/v1 (riêng Claude Code CLI dùng domain gốc)
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
    <title>Tài Liệu Tích Hợp AI Agent Chuẩn 100% - <?= SHOP_NAME ?> | Hướng dẫn kết nối Claude, Cursor, Cline, Kilo, Codex</title>
    <meta name="description" content="Hướng dẫn chuẩn xác kết nối AI Agent (Cline, Cursor, Claude Code CLI, Kilo Code, Windsurf, Codex...) với Base URL https://tiemai.io.vn/v1. Hướng dẫn chi tiết tránh mọi lỗi kết nối.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        pre, code { font-family: 'Fira Code', 'Cascadia Code', Consolas, Monaco, monospace; }
        pre { overflow-x: auto; }
        .tab-active { background: linear-gradient(135deg, #1e40af, #4f46e5); border-color: #3b82f6; color: #fff; box-shadow: 0 4px 14px -2px rgba(59, 130, 246, 0.4); }
        .tab-inactive { background: #111827; border-color: #374151; color: #9ca3af; }
        .tab-inactive:hover { border-color: #6b7280; color: #e5e7eb; background: #1f2937; }
        .copy-btn { transition: all .2s; }
        .copy-btn:hover { opacity: .85; transform: scale(1.04); }
        .gradient-border { background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(139,92,246,.12)); border: 1px solid rgba(99,102,241,.3); }
        .card-warning { background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(185, 28, 28, 0.04)); border: 1px solid rgba(239, 68, 68, 0.35); }
        .card-success { background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(5, 150, 105, 0.04)); border: 1px solid rgba(16, 185, 129, 0.35); }
    </style>
</head>
<body x-data="ungDungApiGuide()" class="min-h-screen flex flex-col antialiased">

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1 space-y-10">

        <!-- HERO SECTION -->
        <div class="border-b border-gray-800 pb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-bold uppercase tracking-wider mb-4">
                <i class="fas fa-plug"></i> Docs Tích Hợp AI Agent Chuẩn 100%
            </div>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                Kết Nối AI Agent Chỉ Một Base URL
            </h1>
            <p class="text-base text-gray-300 mt-2 max-w-3xl leading-relaxed">
                Dùng <code class="bg-blue-950/70 border border-blue-700/50 px-2.5 py-0.5 rounded text-blue-300 text-sm font-bold">https://tiemai.io.vn/v1</code> làm cổng truy cập duy nhất cho <strong>mọi IDE và AI Coding Agent</strong>. Hướng dẫn chi tiết từng nút bấm, tránh các lỗi phổ biến nhất.
            </p>

            <!-- Badge thống kê & cam kết -->
            <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold">
                <span class="px-3 py-1.5 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse inline-block"></span> Cổng Gateway Hoạt Động 24/7
                </span>
                <span class="px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-300 flex items-center gap-1.5">
                    <i class="fas fa-shield-halved"></i> HTTPS / Bearer Token & x-api-key
                </span>
                <span class="px-3 py-1.5 rounded-lg bg-purple-500/10 border border-purple-500/30 text-purple-300 flex items-center gap-1.5">
                    <i class="fas fa-code-branch"></i> OpenAI & Anthropic Compatible
                </span>
                <span class="px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-center gap-1.5">
                    <i class="fas fa-bolt"></i> Streaming SSE Bắn Chữ Mượt Mà
                </span>
            </div>
        </div>

        <!-- CÔNG CỤ TỰ ĐỘNG: DÁN KEY ĐỂ LẤY CẤU HÌNH CHUẨN CỦA GÓI ĐÓ -->
        <div class="gradient-border rounded-2xl p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-magic text-yellow-400"></i> Kiểm Tra Key & Nhận Cấu Hình Tự Động
                    </h2>
                    <p class="text-xs text-gray-400 mt-1">Dán API Key đã mua vào đây để hệ thống tự nhận diện gói và tạo cấu hình mẫu chính xác 100% cho bạn.</p>
                </div>
                <div class="shrink-0">
                    <span class="text-[11px] bg-blue-900/40 border border-blue-700/50 text-blue-300 px-3 py-1 rounded-full font-semibold">
                        <i class="fas fa-check-double mr-1"></i> Tránh cấu hình sai model
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    x-model="apiKeyTest"
                    placeholder="Dán API Key của bạn vào đây: sk-..."
                    class="flex-1 bg-[#0B0F19] border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white font-mono placeholder-gray-600 focus:outline-none focus:border-blue-500 transition">
                <button
                    @click="kiemTraKetNoi()"
                    :disabled="dangKiemTra"
                    class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition flex items-center justify-center gap-2 shrink-0 shadow-lg shadow-blue-600/30 disabled:opacity-60">
                    <i class="fas fa-circle-notch fa-spin" x-show="dangKiemTra" style="display:none;"></i>
                    <i class="fas fa-search" x-show="!dangKiemTra"></i>
                    Kiểm Tra & Tạo Cấu Hình
                </button>
            </div>

            <!-- Kết quả kiểm tra thông minh -->
            <div x-show="ketQuaTest" x-cloak class="rounded-xl p-5 space-y-4 transition-all text-xs"
                 :class="ketQuaTest?.ok ? 'card-success' : 'card-warning'">
                <div class="flex items-center justify-between border-b pb-3" :class="ketQuaTest?.ok ? 'border-green-800/40' : 'border-red-800/40'">
                    <div class="flex items-center gap-2 font-bold text-sm" :class="ketQuaTest?.ok ? 'text-green-400' : 'text-red-400'">
                        <i class="fas" :class="ketQuaTest?.ok ? 'fa-check-circle text-base' : 'fa-triangle-exclamation text-base'"></i>
                        <span x-text="ketQuaTest?.ok ? 'API Key hợp lệ & Đang hoạt động bình thường' : 'Không thể xác thực API Key'"></span>
                    </div>
                    <span x-show="ketQuaTest?.ok" class="bg-green-500/20 text-green-300 border border-green-500/30 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                        Active
                    </span>
                </div>

                <template x-if="ketQuaTest?.ok">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-[#0B0F19]/80 border border-gray-800 rounded-xl p-3.5">
                            <div>
                                <span class="text-gray-500 block text-[11px]">Gói dịch vụ đã mua:</span>
                                <strong class="text-white text-xs block truncate mt-0.5" x-text="ketQuaTest?.package"></strong>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[11px]">Model chuẩn nên chọn:</span>
                                <code class="text-yellow-400 font-bold text-xs mt-0.5 inline-block" x-text="ketQuaTest?.recommended_model"></code>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[11px]">Hạn mức đã dùng:</span>
                                <span class="text-amber-400 font-semibold" x-text="ketQuaTest?.used_requests + ' / ' + ketQuaTest?.total_requests + ' requests'"></span>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[11px]">Số request còn lại:</span>
                                <span class="text-green-400 font-bold" x-text="ketQuaTest?.remaining_requests + ' requests'"></span>
                            </div>
                        </div>

                        <!-- Cấu hình điền nhanh -->
                        <div class="bg-[#0B0F19] border border-blue-900/50 rounded-xl p-4 space-y-3">
                            <div class="font-bold text-white flex items-center gap-2">
                                <i class="fas fa-sliders text-blue-400"></i> Thông số cấu hình chính xác cho Key này:
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="bg-gray-900 border border-gray-800 rounded-lg p-2.5">
                                    <div class="text-[10px] text-gray-400 font-semibold uppercase">Base URL (Cline/Cursor/Kilo):</div>
                                    <div class="flex items-center justify-between mt-1">
                                        <code class="text-green-400 font-bold truncate">https://tiemai.io.vn/v1</code>
                                        <button @click="copy('https://tiemai.io.vn/v1', 'live_base')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]">
                                            <i class="fas" :class="copiedId === 'live_base' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-gray-900 border border-gray-800 rounded-lg p-2.5">
                                    <div class="text-[10px] text-gray-400 font-semibold uppercase">Model ID chuẩn:</div>
                                    <div class="flex items-center justify-between mt-1">
                                        <code class="text-purple-400 font-bold truncate" x-text="ketQuaTest?.recommended_model"></code>
                                        <button @click="copy(ketQuaTest?.recommended_model, 'live_model')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]">
                                            <i class="fas" :class="copiedId === 'live_model' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-gray-900 border border-gray-800 rounded-lg p-2.5">
                                    <div class="text-[10px] text-gray-400 font-semibold uppercase">API Key của bạn:</div>
                                    <div class="flex items-center justify-between mt-1">
                                        <code class="text-blue-400 truncate" x-text="ketQuaTest?.key"></code>
                                        <button @click="copy(apiKeyTest.trim(), 'live_key')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]">
                                            <i class="fas" :class="copiedId === 'live_key' ? 'fa-check text-green-400' : 'fa-copy'"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="!ketQuaTest?.ok">
                    <div class="text-red-300 space-y-1">
                        <div class="font-bold" x-text="ketQuaTest?.message"></div>
                        <div class="text-[11px] text-gray-400">Kiểm tra lại xem bạn có copy thừa khoảng trắng ở đầu hoặc cuối API Key không, hoặc liên hệ admin qua Zalo/Telegram để được trợ giúp.</div>
                    </div>
                </template>
            </div>
        </div>

        <!-- BẢNG QUY ĐỊNH MODEL THEO TỪNG GÓI SẢN PHẨM (TRÁNH CHỌN SAI) -->
        <div class="bg-[#111827] border border-gray-800 rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-layer-group text-blue-400"></i> Bạn Mua Gói Nào Thì Điền Model Đó (Cực Kỳ Quan Trọng)
            </h2>
            <p class="text-xs text-gray-400">Nhiều khách mua gói Claude nhưng lại điền model GPT, hoặc mua gói Codex lại điền model không hỗ trợ dẫn đến lỗi. Hãy xem đúng gói của bạn dưới đây:</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- GÓI CLAUDE CODE -->
                <div class="bg-[#0B0F19] border border-orange-900/40 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-orange-400 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fas fa-fire"></i> Gói CLAUDE CODE API
                        </span>
                        <span class="text-[10px] bg-orange-950/60 text-orange-300 border border-orange-800/40 px-2 py-0.5 rounded">Order có chữ CLAUDE</span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="text-gray-300 font-semibold">Model khuyên dùng tốt nhất:</div>
                        <div class="flex items-center justify-between bg-gray-900 border border-gray-800 px-2.5 py-1.5 rounded-lg">
                            <code class="text-green-400 font-bold">claude-sonnet-5</code>
                            <button @click="copy('claude-sonnet-5', 'pkg_cs5')" class="copy-btn text-gray-400 hover:text-white text-[10px]"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="text-gray-400 text-[11px] pt-1">Model cao cấp hơn:</div>
                        <div class="flex items-center justify-between bg-gray-900 border border-gray-800 px-2.5 py-1.5 rounded-lg">
                            <code class="text-purple-400">claude-opus-5.5</code>
                            <button @click="copy('claude-opus-5.5', 'pkg_co55')" class="copy-btn text-gray-400 hover:text-white text-[10px]"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="text-[11px] text-gray-400 pt-1">
                            <span class="text-emerald-400">✓ Tự động map:</span> <code>claude-3-5-sonnet</code>, <code>claude-3-7-sonnet</code><br>
                            <span class="text-red-400">✕ Không dùng:</span> Các model <code>gpt-*</code>
                        </div>
                    </div>
                </div>

                <!-- GÓI CODEX - GPT -->
                <div class="bg-[#0B0F19] border border-green-900/40 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-green-400 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fas fa-microchip"></i> Gói CODEX - GPT API
                        </span>
                        <span class="text-[10px] bg-green-950/60 text-green-300 border border-green-800/40 px-2 py-0.5 rounded">Order có chữ CODEX/GPT</span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="text-gray-300 font-semibold">Model khuyên dùng tốt nhất:</div>
                        <div class="flex items-center justify-between bg-gray-900 border border-gray-800 px-2.5 py-1.5 rounded-lg">
                            <code class="text-green-400 font-bold">gpt-5.6-sol</code>
                            <button @click="copy('gpt-5.6-sol', 'pkg_g56s')" class="copy-btn text-gray-400 hover:text-white text-[10px]"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="text-gray-400 text-[11px] pt-1">Model cao cấp khác:</div>
                        <div class="flex items-center justify-between bg-gray-900 border border-gray-800 px-2.5 py-1.5 rounded-lg">
                            <code class="text-blue-400">gpt-6.1-sol</code>
                            <button @click="copy('gpt-6.1-sol', 'pkg_g61s')" class="copy-btn text-gray-400 hover:text-white text-[10px]"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="text-[11px] text-gray-400 pt-1">
                            <span class="text-emerald-400">✓ Hỗ trợ thêm:</span> <code>gpt-5.6-terra</code>, <code>claude-opus-5.5</code><br>
                            <span class="text-emerald-400">✓ Tự động map:</span> <code>gpt-4o</code> ➔ <code>gpt-5.6-sol</code>
                        </div>
                    </div>
                </div>

                <!-- GÓI CURSOR API -->
                <div class="bg-[#0B0F19] border border-blue-900/40 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fas fa-mouse-pointer"></i> Gói CURSOR API
                        </span>
                        <span class="text-[10px] bg-blue-950/60 text-blue-300 border border-blue-800/40 px-2 py-0.5 rounded">Dùng trong Cursor / Kilo</span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="text-gray-300 font-semibold">Model khuyên dùng tốt nhất:</div>
                        <div class="flex items-center justify-between bg-gray-900 border border-gray-800 px-2.5 py-1.5 rounded-lg">
                            <code class="text-green-400 font-bold">claude-sonnet-5</code>
                            <button @click="copy('claude-sonnet-5', 'pkg_cur_cs5')" class="copy-btn text-gray-400 hover:text-white text-[10px]"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="text-gray-400 text-[11px] pt-1">Model dự phòng:</div>
                        <div class="flex items-center justify-between bg-gray-900 border border-gray-800 px-2.5 py-1.5 rounded-lg">
                            <code class="text-blue-400">gpt-5.6-sol</code>
                            <button @click="copy('gpt-5.6-sol', 'pkg_cur_g56')" class="copy-btn text-gray-400 hover:text-white text-[10px]"><i class="fas fa-copy"></i></button>
                        </div>
                        <div class="text-[11px] text-gray-400 pt-1">
                            <span class="text-emerald-400">✓ Fast Autocomplete:</span> <code>cursor-small</code>, <code>gpt-5.4-mini</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HƯỚNG DẪN CÀI ĐẶT CHI TIẾT TỪNG IDE & TOOL -->
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-laptop-code text-purple-400"></i> Hướng Dẫn Cài Đặt Chi Tiết Từng Công Cụ
                </h2>
                <span class="text-xs text-gray-400 hidden sm:inline">Chọn công cụ của bạn bên dưới</span>
            </div>

            <!-- Tabs chọn IDE -->
            <div class="flex flex-wrap gap-2">
                <?php
                $danhSachTabs = [
                    ['id' => 'cline',     'icon' => 'fas fa-puzzle-piece',            'ten' => 'Cline / Roo Code', 'hot' => true],
                    ['id' => 'cursor',    'icon' => 'fas fa-mouse-pointer',           'ten' => 'Cursor IDE',       'hot' => true],
                    ['id' => 'claude',    'icon' => 'fas fa-terminal',                'ten' => 'Claude Code CLI',  'hot' => true],
                    ['id' => 'kilo',      'icon' => 'fas fa-code',                    'ten' => 'Kilo Code',        'hot' => false],
                    ['id' => 'codex',     'icon' => 'fas fa-terminal',                'ten' => 'OpenAI Codex',     'hot' => false],
                    ['id' => 'windsurf',  'icon' => 'fas fa-wind',                    'ten' => 'Windsurf',         'hot' => false],
                    ['id' => 'continue',  'icon' => 'fas fa-play-circle',             'ten' => 'Continue.dev',     'hot' => false],
                    ['id' => 'aider',     'icon' => 'fas fa-robot',                   'ten' => 'Aider Pair AI',    'hot' => false],
                    ['id' => 'python',    'icon' => 'fab fa-python',                  'ten' => 'Python / SDK',     'hot' => false],
                ];
                foreach ($danhSachTabs as $tab):
                ?>
                    <button
                        @click="tabHienTai = '<?= $tab['id'] ?>'"
                        :class="tabHienTai === '<?= $tab['id'] ?>' ? 'tab-active' : 'tab-inactive'"
                        class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl border text-xs font-bold transition relative">
                        <i class="<?= $tab['icon'] ?>"></i>
                        <span><?= $tab['ten'] ?></span>
                        <?php if ($tab['hot']): ?>
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block animate-ping"></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- === 1. CLINE / ROO CODE === -->
            <div x-show="tabHienTai === 'cline'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-cyan-900/40 rounded-2xl p-6 space-y-5">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xl shadow"><i class="fas fa-puzzle-piece"></i></div>
                        <div>
                            <div class="font-bold text-white text-base flex items-center gap-2">
                                Cline & Roo Code (VS Code Extension)
                                <span class="bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 text-[10px] px-2 py-0.5 rounded font-mono">OpenAI Compatible</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">Cách cấu hình chuẩn 100%, không bị lỗi Stream ngắt quãng.</div>
                        </div>
                    </div>

                    <!-- CẢNH BÁO QUAN TRỌNG VỀ LỖI CLINE -->
                    <div class="card-warning rounded-xl p-4 space-y-2 text-xs">
                        <div class="font-bold text-red-400 flex items-center gap-2 text-sm">
                            <i class="fas fa-triangle-exclamation"></i> 3 LỖI KHÁCH HÀNG HAY MẮC PHẢI NHẤT TRONG CLINE (HÃY ĐỌC KỸ):
                        </div>
                        <ul class="list-disc list-inside space-y-1.5 text-gray-300 pl-1 leading-relaxed">
                            <li><strong class="text-white">BỎ TÍCH (Uncheck) ô "Use Azure Identity Authentication":</strong> Nếu bạn tích chọn ô này, Cline sẽ không gửi API Key mà đòi đăng nhập Azure OAuth, dẫn đến báo lỗi <code class="text-red-300">Response stream ended without a finish reason</code> ngay lập tức!</li>
                            <li><strong class="text-white">Base URL KHÔNG được thêm <code class="text-red-300">/chat/completions</code>:</strong> Chỉ điền chính xác <code class="text-green-400 font-bold">https://tiemai.io.vn/v1</code>. Cline sẽ tự động nối đuôi vào.</li>
                            <li><strong class="text-white">XÓA BỎ Custom Headers:</strong> Nhấn nút <strong class="text-white">Remove</strong> xóa dòng <code class="text-gray-400">header1: Header value</code>, không tự ý thêm custom header vào.</li>
                        </ul>
                    </div>

                    <!-- CÁC BƯỚC ĐIỀN CHUẨN TRONG CLINE -->
                    <div class="space-y-3">
                        <div class="text-sm font-bold text-white">📋 Các bước điền trên giao diện Cline Settings:</div>
                        <div class="bg-[#0B0F19] border border-cyan-900/40 rounded-xl p-4 space-y-3 font-mono text-xs">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center border-b border-gray-800 pb-2.5">
                                <span class="md:col-span-3 text-gray-400 font-sans font-semibold">1. API Provider:</span>
                                <div class="md:col-span-9 text-white font-bold bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-800">
                                    OpenAI Compatible
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center border-b border-gray-800 pb-2.5">
                                <span class="md:col-span-3 text-gray-400 font-sans font-semibold">2. Base URL:</span>
                                <div class="md:col-span-9 flex items-center justify-between bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-800">
                                    <code class="text-green-400 font-bold truncate">https://tiemai.io.vn/v1</code>
                                    <button @click="copy('https://tiemai.io.vn/v1', 'cline_base')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]">
                                        <i class="fas" :class="copiedId === 'cline_base' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center border-b border-gray-800 pb-2.5">
                                <span class="md:col-span-3 text-gray-400 font-sans font-semibold">3. OpenAI Compatible API Key:</span>
                                <div class="md:col-span-9 flex items-center justify-between bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-800">
                                    <code class="text-blue-400 truncate">sk-your-api-key-here</code>
                                    <span class="text-gray-500 text-[11px] font-sans">Dán key mua trên web</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center border-b border-gray-800 pb-2.5">
                                <span class="md:col-span-3 text-gray-400 font-sans font-semibold">4. Model ID:</span>
                                <div class="md:col-span-9 flex items-center justify-between bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-800">
                                    <div class="flex items-center gap-2">
                                        <code class="text-purple-400 font-bold">claude-sonnet-5</code>
                                        <span class="text-gray-500 text-[10px] font-sans">(Nếu mua gói Claude) hoặc <code class="text-blue-300">gpt-5.6-sol</code> (Gói Codex)</span>
                                    </div>
                                    <button @click="copy('claude-sonnet-5', 'cline_model')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]">
                                        <i class="fas" :class="copiedId === 'cline_model' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                                <span class="md:col-span-3 text-red-400 font-sans font-bold">5. Checkbox Azure:</span>
                                <div class="md:col-span-9 text-red-300 font-sans font-bold bg-red-950/30 border border-red-800/40 px-3 py-1.5 rounded-lg">
                                    [ ] BỎ TÍCH "Use Azure Identity Authentication"
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === 2. CURSOR IDE === -->
            <div x-show="tabHienTai === 'cursor'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-blue-900/40 rounded-2xl p-6 space-y-5">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-xl shadow"><i class="fas fa-mouse-pointer"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Cursor IDE (Cài đặt OpenAI API Key)</div>
                            <div class="text-xs text-gray-400 mt-0.5">Vào Cursor Settings → tab Models → Override Base URL</div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="text-sm font-bold text-white">🎯 5 bước thao tác chính xác trong Cursor:</div>
                        <ol class="space-y-3.5 text-xs text-gray-300">
                            <li class="flex gap-3 items-start">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold mt-0.5">1</span>
                                <div>
                                    <strong class="text-white">Mở Cursor Settings:</strong>
                                    Bấm phím tắt <kbd class="bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700">Ctrl</kbd> + <kbd class="bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700">,</kbd> (hoặc <kbd class="bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700">Cmd</kbd> + <kbd class="bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700">,</kbd> trên Mac) → Click chọn tab <strong class="text-white">Models</strong> ở cột menu bên trái.
                                </div>
                            </li>
                            <li class="flex gap-3 items-start">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold mt-0.5">2</span>
                                <div>
                                    <strong class="text-white">Bật mục OpenAI API Key:</strong>
                                    Kéo xuống mục <strong class="text-white">OpenAI API Key</strong>, gạt công tắc switch sang màu xanh (ON), sau đó dán API Key <code class="text-blue-400">sk-...</code> của bạn vào.
                                </div>
                            </li>
                            <li class="flex gap-3 items-start">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold mt-0.5">3</span>
                                <div class="flex-1">
                                    <strong class="text-white">Bật mục Override OpenAI Base URL:</strong>
                                    Kéo xuống mục <strong class="text-white">Override OpenAI Base URL</strong>, gạt công tắc sang ON, điền chính xác:
                                    <div class="flex items-center gap-2 mt-1.5 bg-[#0B0F19] border border-blue-900/50 rounded-lg p-2 max-w-md">
                                        <code class="text-green-400 font-bold truncate flex-1">https://tiemai.io.vn/v1</code>
                                        <button @click="copy('https://tiemai.io.vn/v1', 'cursor_base')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]">
                                            <i class="fas" :class="copiedId === 'cursor_base' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                        </button>
                                    </div>
                                </div>
                            </li>
                            <li class="flex gap-3 items-start">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold mt-0.5">4</span>
                                <div>
                                    <strong class="text-white">Nhấn nút Verify:</strong>
                                    Bấm nút <span class="bg-blue-600 px-2 py-0.5 rounded text-white font-bold">Verify</span> màu xanh bên cạnh ô API Key. Khi hiện dấu tích xanh hoặc thông báo verify thành công là gateway đã nhận diện key!
                                </div>
                            </li>
                            <li class="flex gap-3 items-start">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold mt-0.5">5</span>
                                <div>
                                    <strong class="text-white">Thêm Model (Add Model):</strong>
                                    Ở danh sách Model phía trên, bấm nút <strong class="text-white">+ Add model</strong>, gõ tên model theo gói bạn mua:
                                    <ul class="list-disc list-inside mt-1 space-y-1 text-gray-300">
                                        <li>Gói Claude: gõ <code class="text-green-400">claude-sonnet-5</code> hoặc <code class="text-purple-400">claude-opus-5.5</code></li>
                                        <li>Gói Codex: gõ <code class="text-green-400">gpt-5.6-sol</code> hoặc <code class="text-blue-400">gpt-6.1-sol</code></li>
                                    </ul>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- === 3. CLAUDE CODE CLI === -->
            <div x-show="tabHienTai === 'claude'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-orange-900/40 rounded-2xl p-6 space-y-5">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shadow"><i class="fas fa-terminal"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Claude Code (Anthropic Official CLI)</div>
                            <div class="text-xs text-gray-400 mt-0.5">Chạy qua Terminal — Lưu ý đặc biệt: KHÔNG THÊM /v1 VÀO BASE URL</div>
                        </div>
                    </div>

                    <!-- CẢNH BÁO ĐỎ CỦA CLAUDE CLI -->
                    <div class="card-warning rounded-xl p-4 space-y-1.5 text-xs text-orange-200">
                        <div class="font-bold text-red-400 flex items-center gap-2 text-sm">
                            <i class="fas fa-exclamation-circle"></i> ĐẶC THÙ BẮT BUỘC CỦA CLAUDE CODE CLI:
                        </div>
                        <p class="leading-relaxed text-gray-300">
                            Claude Code CLI tự động nối đuôi <code class="text-orange-300">/v1/messages</code> vào URL. Vì vậy Base URL của Claude CLI bắt buộc phải là <strong class="text-white">https://tiemai.io.vn</strong> (KHÔNG CÓ <code class="text-red-300">/v1</code>). Nếu thêm <code class="text-red-300">/v1</code> sẽ bị lỗi 404!
                        </p>
                    </div>

                    <div class="space-y-4" x-data="{ osTab: 'win_ps' }">
                        <div class="text-sm font-bold text-white">Chọn hệ điều hành của bạn:</div>
                        <div class="flex flex-wrap gap-2">
                            <button @click="osTab = 'win_ps'" :class="osTab === 'win_ps' ? 'tab-active' : 'tab-inactive'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition">
                                🪟 Windows (PowerShell)
                            </button>
                            <button @click="osTab = 'win_cmd'" :class="osTab === 'win_cmd' ? 'tab-active' : 'tab-inactive'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition">
                                🪟 Windows (CMD)
                            </button>
                            <button @click="osTab = 'unix'" :class="osTab === 'unix' ? 'tab-active' : 'tab-inactive'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition">
                                🍎 macOS / Linux (bash, zsh)
                            </button>
                            <button @click="osTab = 'json'" :class="osTab === 'json' ? 'tab-active' : 'tab-inactive'" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition">
                                📄 File settings.json (Tự động)
                            </button>
                        </div>

                        <!-- Windows PowerShell -->
                        <div x-show="osTab === 'win_ps'" class="space-y-2">
                            <div class="relative">
                                <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 font-mono"><code>$env:ANTHROPIC_BASE_URL = "https://tiemai.io.vn"
$env:ANTHROPIC_AUTH_TOKEN = "sk-your-api-key-here"
$env:CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC = "1"
claude</code></pre>
                                <button @click="copy('$env:ANTHROPIC_BASE_URL = \"https://tiemai.io.vn\"\n$env:ANTHROPIC_AUTH_TOKEN = \"sk-your-api-key-here\"\n$env:CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC = \"1\"\nclaude', 'cl_ps')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                    <i class="fas" :class="copiedId === 'cl_ps' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                </button>
                            </div>
                        </div>

                        <!-- Windows CMD -->
                        <div x-show="osTab === 'win_cmd'" style="display:none;" class="space-y-2">
                            <div class="relative">
                                <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-yellow-300 font-mono"><code>set ANTHROPIC_BASE_URL=https://tiemai.io.vn
set ANTHROPIC_AUTH_TOKEN=sk-your-api-key-here
set CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1
claude</code></pre>
                                <button @click="copy('set ANTHROPIC_BASE_URL=https://tiemai.io.vn\nset ANTHROPIC_AUTH_TOKEN=sk-your-api-key-here\nset CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1\nclaude', 'cl_cmd')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                    <i class="fas" :class="copiedId === 'cl_cmd' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                </button>
                            </div>
                        </div>

                        <!-- macOS / Linux -->
                        <div x-show="osTab === 'unix'" style="display:none;" class="space-y-2">
                            <div class="relative">
                                <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 font-mono"><code>export ANTHROPIC_BASE_URL="https://tiemai.io.vn"
export ANTHROPIC_AUTH_TOKEN="sk-your-api-key-here"
export CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1
claude</code></pre>
                                <button @click="copy('export ANTHROPIC_BASE_URL=\"https://tiemai.io.vn\"\nexport ANTHROPIC_AUTH_TOKEN=\"sk-your-api-key-here\"\nexport CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1\nclaude', 'cl_unix')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                    <i class="fas" :class="copiedId === 'cl_unix' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                </button>
                            </div>
                            <div class="text-[11px] text-gray-400">Thêm 3 dòng trên vào <code class="text-blue-300">~/.zshrc</code> hoặc <code class="text-blue-300">~/.bashrc</code> để mỗi lần mở terminal không phải gõ lại.</div>
                        </div>

                        <!-- File settings.json -->
                        <div x-show="osTab === 'json'" style="display:none;" class="space-y-2">
                            <div class="text-xs text-gray-300">Tạo hoặc sửa file tại đường dẫn <code class="text-orange-400">~/.claude/settings.json</code>:</div>
                            <div class="relative">
                                <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-purple-300 font-mono"><code>{
  "env": {
    "ANTHROPIC_BASE_URL": "https://tiemai.io.vn",
    "ANTHROPIC_AUTH_TOKEN": "sk-your-api-key-here",
    "CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC": "1"
  }
}</code></pre>
                                <button @click="copy('{\n  \"env\": {\n    \"ANTHROPIC_BASE_URL\": \"https://tiemai.io.vn\",\n    \"ANTHROPIC_AUTH_TOKEN\": \"sk-your-api-key-here\",\n    \"CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC\": \"1\"\n  }\n}', 'cl_json')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                    <i class="fas" :class="copiedId === 'cl_json' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === 4. KILO CODE === -->
            <div x-show="tabHienTai === 'kilo'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-indigo-900/40 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl shadow"><i class="fas fa-code"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Kilo Code (VS Code Extension)</div>
                            <div class="text-xs text-gray-400 mt-0.5">Cài đặt trực tiếp trên thanh Settings của Kilo Code</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <ol class="space-y-3 text-xs text-gray-300">
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center font-bold">1</span>
                                <div>Click icon <strong class="text-white">Kilo Code</strong> trên sidebar VS Code → Bấm biểu tượng bánh răng <strong class="text-white">Settings ⚙️</strong>.</div>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center font-bold">2</span>
                                <div>Dropdown <strong class="text-white">API Provider</strong>: chọn <span class="bg-indigo-600/30 text-indigo-300 border border-indigo-600/50 px-2 py-0.5 rounded font-mono">OpenAI Compatible</span>.</div>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                                <div class="w-full space-y-2">
                                    <div class="text-white font-semibold">Điền các trường thông số:</div>
                                    <div class="bg-[#0B0F19] border border-indigo-900/40 rounded-xl p-3.5 space-y-2 font-mono text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-400 font-sans">Base URL:</span>
                                            <div class="flex items-center gap-2">
                                                <code class="text-green-400 font-bold">https://tiemai.io.vn/v1</code>
                                                <button @click="copy('https://tiemai.io.vn/v1', 'kilo_u')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]"><i class="fas fa-copy"></i></button>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-gray-800 pt-2">
                                            <span class="text-gray-400 font-sans">API Key:</span>
                                            <code class="text-blue-400">sk-your-api-key-here</code>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-gray-800 pt-2">
                                            <span class="text-gray-400 font-sans">Model ID:</span>
                                            <div class="flex items-center gap-2">
                                                <code class="text-purple-400 font-bold">claude-sonnet-5</code>
                                                <button @click="copy('claude-sonnet-5', 'kilo_m')" class="copy-btn text-gray-400 hover:text-white px-2 py-0.5 rounded bg-gray-800 text-[10px]"><i class="fas fa-copy"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center font-bold">4</span>
                                <div>Bấm <strong class="text-white">Save</strong> và gửi câu lệnh chat đầu tiên để tận hưởng!</div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- === 5. OPENAI CODEX CLI === -->
            <div x-show="tabHienTai === 'codex'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-green-900/40 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-green-500/20 text-green-400 flex items-center justify-center text-xl shadow"><i class="fas fa-terminal"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">OpenAI Codex CLI</div>
                            <div class="text-xs text-gray-400 mt-0.5">Dùng biến môi trường chuẩn OPENAI_BASE_URL (CÓ /v1)</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Terminal Linux / macOS / WSL:</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 font-mono"><code>export OPENAI_BASE_URL="https://tiemai.io.vn/v1"
export OPENAI_API_KEY="sk-your-api-key-here"

# Chạy lệnh
codex --model gpt-5.6-sol "viết api đăng nhập bằng php"</code></pre>
                            <button @click="copy('export OPENAI_BASE_URL=\"https://tiemai.io.vn/v1\"\nexport OPENAI_API_KEY=\"sk-your-api-key-here\"', 'codex_c')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'codex_c' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                            </button>
                        </div>

                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Windows PowerShell:</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 font-mono"><code>$env:OPENAI_BASE_URL = "https://tiemai.io.vn/v1"
$env:OPENAI_API_KEY = "sk-your-api-key-here"
codex --model gpt-5.6-sol "generate unit tests"</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === 6. WINDSURF === -->
            <div x-show="tabHienTai === 'windsurf'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-teal-900/40 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-teal-500/20 text-teal-400 flex items-center justify-center text-xl shadow"><i class="fas fa-wind"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Windsurf (Codeium IDE)</div>
                            <div class="text-xs text-gray-400 mt-0.5">Vào Windsurf Settings → AI Provider → Custom OpenAI</div>
                        </div>
                    </div>

                    <ol class="space-y-3 text-xs text-gray-300">
                        <li class="flex gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-teal-600 text-white text-xs flex items-center justify-center font-bold">1</span>
                            <div>Mở Windsurf → Vào mục <strong class="text-white">Settings</strong> → <strong class="text-white">Windsurf Settings</strong>.</div>
                        </li>
                        <li class="flex gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-teal-600 text-white text-xs flex items-center justify-center font-bold">2</span>
                            <div>Tại mục <strong class="text-white">AI Provider</strong>, gạt bật <span class="text-teal-300 font-mono">Custom OpenAI</span>.</div>
                        </li>
                        <li class="flex gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-teal-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                            <div class="w-full">
                                <div>Điền các thông số:</div>
                                <div class="mt-2 bg-[#0B0F19] border border-teal-900/40 rounded-xl p-3.5 space-y-2 font-mono text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-400 font-sans">Base URL:</span>
                                        <code class="text-green-400 font-bold">https://tiemai.io.vn/v1</code>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-gray-800 pt-2">
                                        <span class="text-gray-400 font-sans">API Key:</span>
                                        <code class="text-blue-400">sk-your-api-key-here</code>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-gray-800 pt-2">
                                        <span class="text-gray-400 font-sans">Model:</span>
                                        <code class="text-purple-400 font-bold">claude-sonnet-5</code>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- === 7. CONTINUE.DEV === -->
            <div x-show="tabHienTai === 'continue'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-violet-900/40 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center text-xl shadow"><i class="fas fa-play-circle"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Continue.dev (VS Code / JetBrains)</div>
                            <div class="text-xs text-gray-400 mt-0.5">Cấu hình qua file ~/.continue/config.json</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs text-gray-300">Dán cấu hình này vào file <code class="text-violet-400">~/.continue/config.json</code>:</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-blue-300 font-mono"><code>{
  "models": [
    {
      "title": "TiemAI Claude Sonnet 5",
      "provider": "openai",
      "model": "claude-sonnet-5",
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
                            <button @click="copy('{\n  \"models\": [\n    {\n      \"title\": \"TiemAI Claude Sonnet 5\",\n      \"provider\": \"openai\",\n      \"model\": \"claude-sonnet-5\",\n      \"apiBase\": \"https://tiemai.io.vn/v1\",\n      \"apiKey\": \"sk-your-api-key-here\"\n    }\n  ]\n}', 'cont_c')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'cont_c' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- === 8. AIDER === -->
            <div x-show="tabHienTai === 'aider'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-pink-900/40 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-pink-500/20 text-pink-400 flex items-center justify-center text-xl shadow"><i class="fas fa-robot"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Aider (Terminal AI Pair Programming)</div>
                            <div class="text-xs text-gray-400 mt-0.5">Khởi động Aider với cờ --openai-api-base</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-300 uppercase tracking-wider">Chạy trực tiếp từ Terminal:</div>
                        <div class="relative">
                            <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 font-mono"><code>aider --openai-api-base https://tiemai.io.vn/v1 \
  --openai-api-key sk-your-api-key-here \
  --model openai/claude-sonnet-5</code></pre>
                            <button @click="copy('aider --openai-api-base https://tiemai.io.vn/v1 --openai-api-key sk-your-api-key-here --model openai/claude-sonnet-5', 'aider_c')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                <i class="fas" :class="copiedId === 'aider_c' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                            </button>
                        </div>
                        <div class="text-[11px] text-gray-400"><i class="fas fa-info-circle text-blue-400"></i> Lưu ý trong Aider: Model cần có tiền tố <code class="text-yellow-400">openai/</code> đằng trước (ví dụ: <code>openai/claude-sonnet-5</code>).</div>
                    </div>
                </div>
            </div>

            <!-- === 9. PYTHON / SDK / CURL === -->
            <div x-show="tabHienTai === 'python'" x-cloak class="space-y-4">
                <div class="bg-[#111827] border border-yellow-900/40 rounded-2xl p-6 space-y-5">
                    <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                        <div class="w-11 h-11 rounded-xl bg-yellow-500/20 text-yellow-400 flex items-center justify-center text-xl shadow"><i class="fab fa-python"></i></div>
                        <div>
                            <div class="font-bold text-white text-base">Python / Node.js / cURL HTTP</div>
                            <div class="text-xs text-gray-400 mt-0.5">Code mẫu sẵn sàng cho dự án của bạn</div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <div class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">Python (Thư viện official `openai`):</div>
                            <div class="relative">
                                <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-yellow-300 font-mono"><code>from openai import OpenAI

client = OpenAI(
    base_url="https://tiemai.io.vn/v1",
    api_key="sk-your-api-key-here"
)

response = client.chat.completions.create(
    model="claude-sonnet-5",
    messages=[{"role": "user", "content": "Xin chào AI!"}],
    stream=True
)

for chunk in response:
    if chunk.choices[0].delta.content:
        print(chunk.choices[0].delta.content, end="", flush=True)</code></pre>
                                <button @click="copy('from openai import OpenAI\n\nclient = OpenAI(base_url=\"https://tiemai.io.vn/v1\", api_key=\"sk-your-api-key-here\")\n\nresponse = client.chat.completions.create(model=\"claude-sonnet-5\", messages=[{\"role\": \"user\", \"content\": \"Xin chao!\"}])', 'py_c')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                    <i class="fas" :class="copiedId === 'py_c' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">cURL HTTP Terminal (Kiểm tra nhanh):</div>
                            <div class="relative">
                                <pre class="bg-[#0B0F19] border border-gray-800 rounded-xl p-4 text-xs text-green-300 font-mono"><code>curl -X POST https://tiemai.io.vn/v1/chat/completions \
  -H "Authorization: Bearer sk-your-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "claude-sonnet-5",
    "messages": [{"role": "user", "content": "Xin chao!"}],
    "stream": false
  }'</code></pre>
                                <button @click="copy('curl -X POST https://tiemai.io.vn/v1/chat/completions -H \"Authorization: Bearer sk-your-api-key-here\" -H \"Content-Type: application/json\" -d \'{\"model\":\"claude-sonnet-5\",\"messages\":[{\"role\":\"user\",\"content\":\"Xin chao!\"}],\"stream\":false}\'', 'curl_c')" class="copy-btn absolute top-3 right-3 px-2 py-1 rounded bg-gray-800 text-gray-400 hover:text-white text-xs">
                                    <i class="fas" :class="copiedId === 'curl_c' ? 'fa-check text-green-400' : 'fa-copy'"></i> Sao chép
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MỤC BẮT BỆNH LỖI NHANH TRONG 30 GIÂY (CHEAT SHEET) -->
        <div class="bg-[#111827] border border-red-900/30 rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-stethoscope text-red-400"></i> Bảng Bắt Bệnh Lỗi Nhanh Trong 30 Giây
            </h2>
            <p class="text-xs text-gray-400">Nếu bạn gặp bất kỳ thông báo lỗi nào dưới đây, hãy làm theo cách sửa tương ứng:</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <!-- Lỗi 1: Cline Stream -->
                <div class="bg-[#0B0F19] border border-red-900/40 rounded-xl p-4 space-y-2">
                    <div class="font-bold text-red-400 flex items-center gap-2">
                        <i class="fas fa-circle-xmark"></i> "Response stream ended without a finish reason" (Cline)
                    </div>
                    <div class="text-gray-300 leading-relaxed">
                        <strong class="text-white">Nguyên nhân:</strong> Đang tích ô "Use Azure Identity Authentication" hoặc điền thừa <code class="text-red-300">/chat/completions</code> vào Base URL.<br>
                        <strong class="text-emerald-400">👉 Cách sửa:</strong> Bỏ tích ô Azure, sửa Base URL đúng <code class="text-green-400">https://tiemai.io.vn/v1</code>, xóa Custom Headers.
                    </div>
                </div>

                <!-- Lỗi 2: 401 Unauthorized -->
                <div class="bg-[#0B0F19] border border-red-900/40 rounded-xl p-4 space-y-2">
                    <div class="font-bold text-red-400 flex items-center gap-2">
                        <i class="fas fa-circle-xmark"></i> "401 Unauthorized" hoặc "Invalid API Key"
                    </div>
                    <div class="text-gray-300 leading-relaxed">
                        <strong class="text-white">Nguyên nhân:</strong> Key bị copy thừa khoảng trắng, hoặc đơn hàng chưa thanh toán xong.<br>
                        <strong class="text-emerald-400">👉 Cách sửa:</strong> Dán key vào ô "Kiểm Tra Key" ở đầu trang này để kiểm tra xem key có đang Active không.
                    </div>
                </div>

                <!-- Lỗi 3: 404 Not Found -->
                <div class="bg-[#0B0F19] border border-red-900/40 rounded-xl p-4 space-y-2">
                    <div class="font-bold text-red-400 flex items-center gap-2">
                        <i class="fas fa-circle-xmark"></i> "404 Not Found" (Claude Code CLI)
                    </div>
                    <div class="text-gray-300 leading-relaxed">
                        <strong class="text-white">Nguyên nhân:</strong> Đang thêm <code class="text-red-300">/v1</code> vào Base URL của Claude CLI.<br>
                        <strong class="text-emerald-400">👉 Cách sửa:</strong> Với Claude Code CLI, Base URL bắt buộc là <code class="text-green-400">https://tiemai.io.vn</code> (không có /v1).
                    </div>
                </div>

                <!-- Lỗi 4: Model not supported -->
                <div class="bg-[#0B0F19] border border-red-900/40 rounded-xl p-4 space-y-2">
                    <div class="font-bold text-red-400 flex items-center gap-2">
                        <i class="fas fa-circle-xmark"></i> "Model not supported / not found"
                    </div>
                    <div class="text-gray-300 leading-relaxed">
                        <strong class="text-white">Nguyên nhân:</strong> Bạn đang chọn model không thuộc gói dịch vụ đã mua (ví dụ mua gói Claude nhưng gõ model GPT).<br>
                        <strong class="text-emerald-400">👉 Cách sửa:</strong> Đổi Model ID thành <code class="text-purple-400">claude-sonnet-5</code> hoặc <code class="text-green-400">gpt-5.6-sol</code> theo đúng bảng tra cứu ở trên.
                    </div>
                </div>
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
                     'a' => 'Với Cursor, Cline, Kilo, Windsurf, Codex: Nhập chính xác: <code class="text-green-400 font-bold">https://tiemai.io.vn/v1</code> (không có dấu "/" ở cuối và không có /chat/completions). Riêng Claude Code CLI thì dùng <code class="text-green-400 font-bold">https://tiemai.io.vn</code>.'],
                    ['q' => 'Key của tôi dạng sk-... có dùng được không?',
                     'a' => 'Có. Mọi key mua tại tiemai.io.vn đều có dạng <code class="text-blue-400">sk-...</code> và được hỗ trợ đầy đủ trên tất cả các tool.'],
                    ['q' => 'Tôi đang dùng Claude 3.5 Sonnet, gateway có hỗ trợ không?',
                     'a' => 'Có. Hệ thống tự động ánh xạ <code class="text-gray-300">claude-3-5-sonnet-*</code> sang <code class="text-orange-400 font-bold">claude-sonnet-5</code> — model mới và mạnh nhất.'],
                    ['q' => 'Có hỗ trợ Streaming (SSE) từng chữ không?',
                     'a' => 'Có 100%. Gateway hỗ trợ Streaming SSE qua Server-Sent Events tiêu chuẩn, chữ ra đến đâu hiển thị ngay đến đó không có độ trễ.'],
                    ['q' => 'Dùng được bao nhiêu request mỗi ngày?',
                     'a' => 'Tùy theo gói bạn mua (ví dụ gói ngày, gói tháng, gói 700 request hay 1500 request). Bạn có thể dán key vào ô Kiểm tra ở đầu trang hoặc vào mục Tra Cứu Key để xem số lượng còn lại.']
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

        <!-- LIÊN HỆ HỖ TRỢ KỸ THUẬT 24/7 -->
        <div class="gradient-border rounded-2xl p-6 text-center space-y-3">
            <i class="fas fa-headset text-3xl text-blue-400"></i>
            <h3 class="text-lg font-bold text-white">Vẫn Cần Trợ Giúp Cấu Hình?</h3>
            <p class="text-xs text-gray-400 max-w-md mx-auto">Nếu làm theo các bước trên mà vẫn chưa kết nối được, hãy nhắn tin ngay cho Admin qua Zalo hoặc Telegram để được hỗ trợ qua UltraViewer / TeamViewer miễn phí!</p>
            <div class="flex flex-wrap justify-center gap-3 mt-2">
                <a href="https://zalo.me/<?= htmlspecialchars(SHOP_ZALO) ?>" target="_blank" rel="noopener noreferrer"
                   class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-blue-600/30">
                    <i class="fas fa-comment-dots"></i> Nhắn Zalo: <?= htmlspecialchars(SHOP_ZALO) ?>
                </a>
                <a href="<?= htmlspecialchars(SHOP_TELEGRAM) ?>" target="_blank" rel="noopener noreferrer"
                   class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-sky-600/30">
                    <i class="fab fa-telegram"></i> Telegram Hỗ Trợ
                </a>
                <a href="/san-pham"
                   class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 border border-gray-700 text-white text-xs font-bold transition flex items-center gap-2">
                    <i class="fas fa-shopping-cart"></i> Mua Thêm API Key
                </a>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script>
    function ungDungApiGuide() {
        return {
            tabHienTai: 'cline',
            copiedId: null,
            apiKeyTest: '',
            dangKiemTra: false,
            ketQuaTest: null,

            copy(text, id) {
                if (!text) return;
                navigator.clipboard.writeText(text).then(() => {
                    this.copiedId = id;
                    setTimeout(() => { this.copiedId = null; }, 1800);
                }).catch(() => {
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
                const k = this.apiKeyTest.trim();
                if (!k) {
                    alert('Vui lòng dán API Key của bạn vào ô kiểm tra!');
                    return;
                }
                this.dangKiemTra = true;
                this.ketQuaTest = null;
                try {
                    const res = await fetch('/v1/usage', {
                        headers: {
                            'Authorization': 'Bearer ' + k,
                            'Content-Type': 'application/json'
                        }
                    });
                    const data = await res.json();
                    if (res.ok && data.object === 'usage') {
                        this.ketQuaTest = { ok: true, ...data };
                    } else {
                        this.ketQuaTest = {
                            ok: false,
                            message: data?.error?.message || 'API Key không hợp lệ hoặc đã hết hạn.'
                        };
                    }
                } catch (e) {
                    this.ketQuaTest = {
                        ok: false,
                        message: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại mạng internet.'
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
