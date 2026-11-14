<?php
// ========================================================
// TRANG BẢNG GIÁ MODEL AI CHUYÊN NGHIỆP
// Tiệm AI - tiemai.io.vn
// Tra cứu chi tiết giá token theo nhà cung cấp & nền tảng
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

// Đọc dữ liệu bảng giá từ cache JSON
$dataFile = __DIR__ . '/../backend/data/models_pricing.json';
$pricingData = null;
if (file_exists($dataFile)) {
    $pricingData = json_decode(file_get_contents($dataFile), true);
}

// Nếu file chưa có dữ liệu, dùng dữ liệu fallback mẫu
if (!$pricingData || empty($pricingData['rows'])) {
    $pricingData = [
        'providers' => ['gwai' => '1gw.gwai.cloud', 'modelapi' => 'Modelapi.vn'],
        'platforms' => [
            'claude'   => ['label' => 'Claude', 'dot' => '#d97706'],
            'openai'   => ['label' => 'OpenAI', 'dot' => '#16a34a'],
            'grok'     => ['label' => 'Grok', 'dot' => '#6b7280'],
            'gemini'   => ['label' => 'Gemini', 'dot' => '#2563eb'],
            'deepseek' => ['label' => 'DeepSeek', 'dot' => '#4d6bfe'],
            'moonshot' => ['label' => 'Moonshot AI', 'dot' => '#10b981'],
            'zai'      => ['label' => 'Z.AI', 'dot' => '#84cc16'],
            'minimax'  => ['label' => 'Minimax', 'dot' => '#22c55e'],
            'khac'     => ['label' => 'Khác', 'dot' => '#9ca3af']
        ],
        'rows' => []
    ];
}

$siteName = 'Tiệm AI';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Giá Model AI - <?= $siteName ?> (tiemai.io.vn)</title>
    <meta name="description" content="Tra cứu bảng giá token các model AI Claude, OpenAI, Gemini, DeepSeek, Grok, Kimi, GLM theo từng nhà cung cấp và loại key tại <?= $siteName ?>.">
    <link rel="canonical" href="https://tiemai.io.vn/bang-gia-model">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
        
        .mp-card { background: #111827; border: 1px solid #1F2937; border-radius: 16px; }
        .mp-filters { background: #0F172A; border: 1px solid #1E293B; }
        .mp-notice { background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 16px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; line-height: 1.65; color: #FDE68A; }
        .mp-notice strong { color: #F59E0B; }
        
        .mp-pill { border: 1px solid #334155; border-radius: 9999px; padding: 6px 14px; font-size: 12.5px; color: #94A3B8; background: #1E293B; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-weight: 500; }
        .mp-pill:hover { border-color: #3B82F6; color: #FFFFFF; background: #1E293B; }
        .mp-pill.active { background: #2563EB; border-color: #3B82F6; color: #FFFFFF; font-weight: 700; box-shadow: 0 0 12px rgba(37, 99, 235, 0.35); }
        .mp-pill .dot { width: 7px; height: 7px; border-radius: 9999px; display: inline-block; }
        
        .mp-table th { font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: .04em; padding: 12px 14px; text-align: left; border-bottom: 1px solid #1F2937; white-space: nowrap; background: #0F172A; }
        .mp-table td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid #1F2937; vertical-align: middle; }
        .mp-table tr:hover td { background: rgba(30, 41, 59, 0.5); }
        .mp-table .price { color: #10B981; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; font-family: ui-monospace, monospace; }
        
        .mp-chip { display: inline-flex; align-items: center; border-radius: 9999px; padding: 2px 8px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .mp-chip-gray { background: #1F2937; color: #9CA3AF; border: 1px solid #374151; }
        .mp-chip-green { background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .mp-chip-blue { background: rgba(59, 130, 246, 0.15); color: #60A5FA; border: 1px solid rgba(59, 130, 246, 0.3); }
        .mp-chip-amber { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.3); }
        
        .mp-status { background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .mp-status.off { background: rgba(239, 68, 68, 0.15); color: #F87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        .mp-search { border: 1px solid #334155; border-radius: 12px; padding: 10px 14px; font-size: 13px; width: 100%; outline: none; background: #0F172A; color: #FFFFFF; transition: all .15s; }
        .mp-search:focus { border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .mp-select { border: 1px solid #334155; border-radius: 12px; padding: 10px 14px; font-size: 13px; background: #0F172A; color: #FFFFFF; outline: none; }
        .mp-select:focus { border-color: #3B82F6; }
        
        .mp-provider-title { display: flex; align-items: center; gap: 8px; padding: 14px 18px; border-bottom: 1px solid #1F2937; font-weight: 800; font-size: 14px; color: #FFFFFF; background: #131C2E; border-top-left-radius: 16px; border-top-right-radius: 16px; }
        .mp-provider-title .count { background: #1E293B; color: #94A3B8; font-size: 11px; font-weight: 700; border-radius: 9999px; padding: 2px 8px; }
        .mp-family { font-weight: 700; font-size: 13px; color: #E5E7EB; }
        .mp-family .count { background: #1E293B; color: #94A3B8; font-size: 10px; font-weight: 700; border-radius: 9999px; padding: 2px 7px; margin-left: 6px; }
        .mp-slug { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; color: #93C5FD; font-weight: 600; }
        
        .mp-copy { color: #64748B; cursor: pointer; border: 0; background: transparent; padding: 3px 6px; font-size: 12px; border-radius: 6px; transition: all .15s; }
        .mp-copy:hover { color: #60A5FA; background: rgba(59, 130, 246, 0.1); }
        .mp-copy.copied { color: #34D399; }
        
        .mp-group-card { border-bottom: 1px solid #1F2937; }
        .mp-group-card:last-child { border-bottom: none; }
        .mp-empty { padding: 48px 16px; text-align: center; color: #64748B; font-size: 14px; }
        
        @media (max-width: 1024px) { 
            .mp-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } 
            .mp-table { min-width: 880px; } 
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1">
        <!-- Hero Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold uppercase tracking-wider mb-2">
                <i class="fas fa-tag"></i> Bảng giá Model AI chính thức
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white tracking-tight mt-1">Bảng Giá Theo Model</h1>
            <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-2xl mx-auto leading-relaxed">
                Tra cứu giá token chi tiết theo từng nhà cung cấp, nền tảng và loại key tại hệ thống <?= htmlspecialchars($siteName) ?>.
            </p>
        </div>

        <!-- Hộp lưu ý cách đọc bảng giá -->
        <div class="mp-notice">
            <div class="flex items-center gap-2 font-black text-sm mb-1 text-amber-400">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Lưu ý quan trọng về cách đọc bảng giá</span>
            </div>
            <strong>Giá niêm yết trên mỗi token không quyết định chi phí thực tế.</strong> Chi phí cuối cùng còn phụ thuộc vào <strong>tỷ lệ quy đổi của backend</strong> (backend rate): cùng một model, mỗi nhà cung cấp có thể áp dụng hệ số quy đổi token khác nhau. Ví dụ: gói $10 credit ở nơi có tỷ lệ quy đổi tốt có thể chạy được gấp đôi số prompt so với nơi tỷ lệ kém — dù đơn giá niêm yết trông rẻ hơn. Vì vậy hãy so sánh <strong>chi phí thực tế trên cùng một tác vụ</strong>, không chỉ so đơn giá $/1M token.
        </div>

        <!-- Khối bộ lọc thông minh (Filters Card) -->
        <div class="mp-card mp-filters p-4 md:p-6 mb-6 shadow-xl">
            <!-- 1. Bộ lọc Nhà cung cấp -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="text-xs font-bold text-gray-400 uppercase w-28 shrink-0 tracking-wider">NHÀ CUNG CẤP</span>
                <div class="flex flex-wrap gap-2" id="mp-provider-filters">
                    <button class="mp-pill active" data-provider="all">Tất cả</button>
                    <?php foreach ($pricingData['providers'] as $code => $name): ?>
                        <button class="mp-pill" data-provider="<?= htmlspecialchars($code) ?>">
                            <span class="dot bg-emerald-500"></span><?= htmlspecialchars($name) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 2. Bộ lọc Nền tảng -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="text-xs font-bold text-gray-400 uppercase w-28 shrink-0 tracking-wider">NỀN TẢNG</span>
                <div class="flex flex-wrap gap-2" id="mp-platform-filters">
                    <button class="mp-pill active" data-platform="all">Tất cả</button>
                    <?php foreach ($pricingData['platforms'] as $code => $plat): ?>
                        <button class="mp-pill" data-platform="<?= htmlspecialchars($code) ?>">
                            <span class="dot" style="background:<?= htmlspecialchars($plat['dot'] ?? '#3b82f6') ?>"></span>
                            <?= htmlspecialchars($plat['label']) ?>
                            <span class="mp-count text-[11px] opacity-70 ml-1 font-bold"></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. Bộ lọc Loại Key -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="text-xs font-bold text-gray-400 uppercase w-28 shrink-0 tracking-wider">LOẠI KEY</span>
                <div class="flex flex-wrap gap-2" id="mp-key-filters">
                    <button class="mp-pill active" data-key="all">Tất cả</button>
                    <button class="mp-pill" data-key="credit">Key credit</button>
                    <button class="mp-pill" data-key="thang">Key tháng</button>
                </div>
            </div>

            <!-- 4. Bộ lọc Effort -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="text-xs font-bold text-gray-400 uppercase w-28 shrink-0 tracking-wider">EFFORT</span>
                <div class="flex flex-wrap gap-2" id="mp-effort-filters">
                    <button class="mp-pill active" data-effort="all">Tất cả</button>
                    <button class="mp-pill" data-effort="Default">Default</button>
                    <button class="mp-pill" data-effort="High">High</button>
                    <button class="mp-pill" data-effort="Max">Max</button>
                </div>
            </div>

            <!-- 5. Tìm kiếm & Cấp độ -->
            <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-gray-800">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                    <input class="mp-search pl-9" id="mp-search" placeholder="Tìm kiếm nhanh theo tên model hoặc slug (ví dụ: claude, gemini, gpt-5, deepseek)..." />
                </div>
                <select class="mp-select sm:w-56" id="mp-level">
                    <option value="all">Tất cả cấp độ</option>
                    <option value="Efficient">Efficient</option>
                    <option value="Standard">Standard</option>
                    <option value="Frontier">Frontier</option>
                    <option value="Advanced">Advanced</option>
                </select>
            </div>
        </div>

        <!-- Vùng hiển thị danh sách Model -->
        <div id="mp-results" class="space-y-4"></div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- Dữ liệu và mã điều khiển bảng giá -->
    <script>
    (function () {
        var rows = <?= json_encode($pricingData['rows'], JSON_UNESCAPED_UNICODE) ?>;
        var providers = <?= json_encode($pricingData['providers'], JSON_UNESCAPED_UNICODE) ?>;
        var platforms = <?= json_encode($pricingData['platforms'], JSON_UNESCAPED_UNICODE) ?>;

        var T = {
            model: "Model",
            keyType: "Loại key",
            level: "Cấp độ",
            context: "Context",
            input: "Input / 1M",
            output: "Output / 1M",
            cread: "Cache Read",
            cwrite: "Cache Write",
            status: "Trạng thái",
            active: "Hoạt động",
            paused: "Tạm dừng",
            credit: "Key credit",
            both: "Credit + Tháng",
            thang: "Key tháng",
            copied: "Đã copy!",
            empty: "Không tìm thấy model nào phù hợp với bộ lọc."
        };

        var state = { provider: 'all', platform: 'all', key: 'all', effort: 'all', q: '', level: 'all' };

        function scopedRows() {
            if (state.provider === 'all') return rows;
            return rows.filter(function (r) { return r.provider === state.provider; });
        }

        function hasKey(list, k) {
            return list.some(function (r) {
                if (k === 'credit') return r.key === 'credit' || r.key === 'both';
                if (k === 'thang') return r.key === 'thang' || r.key === 'both';
                return true;
            });
        }

        function syncDependentFilters() {
            var list = scopedRows();
            var plats = {};
            list.forEach(function (r) { plats[r.platform] = (plats[r.platform] || 0) + 1; });
            
            var platBox = document.getElementById('mp-platform-filters');
            platBox.querySelectorAll('.mp-pill').forEach(function (btn) {
                var p = btn.dataset.platform;
                if (!p || p === 'all') return;
                var n = plats[p] || 0;
                var badge = btn.querySelector('.mp-count');
                if (badge) badge.textContent = n > 0 ? '(' + n + ')' : '';
                btn.style.display = n > 0 ? '' : 'none';
            });

            if (state.platform !== 'all' && !(plats[state.platform] > 0)) {
                state.platform = 'all';
                platBox.querySelectorAll('.mp-pill').forEach(function (b) { b.classList.toggle('active', b.dataset.platform === 'all'); });
            }

            var keyBox = document.getElementById('mp-key-filters');
            var showCredit = hasKey(list, 'credit');
            var showThang = hasKey(list, 'thang');
            keyBox.querySelectorAll('.mp-pill').forEach(function (btn) {
                var k = btn.dataset.key;
                if (!k || k === 'all') return;
                btn.style.display = ((k === 'credit' && showCredit) || (k === 'thang' && showThang)) ? '' : 'none';
            });
            if ((state.key === 'credit' && !showCredit) || (state.key === 'thang' && !showThang)) {
                state.key = 'all';
                keyBox.querySelectorAll('.mp-pill').forEach(function (b) { b.classList.toggle('active', b.dataset.key === 'all'); });
            }
        }

        function matchKey(r) {
            if (state.key === 'all') return true;
            if (state.key === 'credit') return r.key === 'credit' || r.key === 'both';
            if (state.key === 'thang') return r.key === 'thang' || r.key === 'both';
            return true;
        }

        function bindPills(containerId, stateKey) {
            var box = document.getElementById(containerId);
            if (!box) return;
            box.addEventListener('click', function (e) {
                var btn = e.target.closest('.mp-pill');
                if (!btn) return;
                box.querySelectorAll('.mp-pill').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                state[stateKey] = btn.dataset[stateKey];
                if (stateKey === 'provider') syncDependentFilters();
                render();
            });
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function rowHtml(r) {
            var effortCls = r.effort === 'Max' ? 'mp-chip-green' : (r.effort === 'High' ? 'mp-chip-amber' : 'mp-chip-gray');
            var keyCls = r.key === 'credit' ? 'mp-chip-blue' : 'mp-chip-green';
            var keyLabel = r.key === 'credit' ? T.credit : (r.key === 'thang' ? T.thang : T.both);
            var extra = '';
            if (r.extra) {
                extra = '<tr>' +
                    '<td></td><td></td>' +
                    '<td colspan="2" class="text-gray-400 text-xs italic">' + escapeHtml(r.extra.label) + '</td>' +
                    '<td class="price">' + escapeHtml(r.extra.input) + '</td>' +
                    '<td class="price">' + escapeHtml(r.extra.output) + '</td>' +
                    '<td class="price">' + escapeHtml(r.extra.cread) + '</td>' +
                    '<td class="price">' + escapeHtml(r.extra.cwrite) + '</td>' +
                    '<td></td></tr>';
            }
            var isPerRequest = /\/\s*request/i.test(r.input || '');
            var priceCells;
            if (isPerRequest) {
                priceCells = '<td class="price" colspan="4">' + escapeHtml(r.input) + '</td>';
            } else {
                priceCells =
                    '<td class="price">' + escapeHtml(r.input) + '</td>' +
                    '<td class="price">' + escapeHtml(r.output) + '</td>' +
                    '<td class="price">' + escapeHtml(r.cread) + '</td>' +
                    '<td class="price">' + escapeHtml(r.cwrite) + '</td>';
            }
            return '<tr data-slug="' + escapeHtml(r.slug) + '">' +
                '<td><span class="mp-chip ' + effortCls + '">' + escapeHtml(r.effort) + '</span> ' +
                '<span class="mp-slug ml-1">' + escapeHtml(r.slug) + '</span> ' +
                '<button type="button" class="mp-copy" data-copy="' + escapeHtml(r.slug) + '" title="Copy slug" aria-label="Copy"><i class="fas fa-copy"></i></button></td>' +
                '<td><span class="mp-chip ' + keyCls + '">' + escapeHtml(keyLabel) + '</span></td>' +
                '<td><span class="mp-chip mp-chip-amber">' + escapeHtml(r.level) + '</span></td>' +
                '<td class="font-semibold text-gray-300">' + escapeHtml(r.context) + '</td>' +
                priceCells +
                '<td><span class="mp-chip ' + (r.status ? 'mp-status' : 'mp-status off') + '">' + (r.status ? T.active : T.paused) + '</span></td>' +
                '</tr>' + extra;
        }

        function render() {
            var box = document.getElementById('mp-results');
            var filtered = rows.filter(function (r) {
                if (state.provider !== 'all' && r.provider !== state.provider) return false;
                if (state.platform !== 'all' && r.platform !== state.platform) return false;
                if (!matchKey(r)) return false;
                if (state.effort !== 'all' && r.effort !== state.effort) return false;
                if (state.level !== 'all' && r.level !== state.level) return false;
                if (state.q && (r.slug + ' ' + r.family).toLowerCase().indexOf(state.q) === -1) return false;
                return true;
            });

            if (!filtered.length) {
                box.innerHTML = '<div class="mp-card mp-empty p-8 text-center text-gray-400"><i class="fas fa-folder-open text-3xl mb-3 block text-gray-600"></i>' + T.empty + '</div>';
                return;
            }

            // Gom nhóm theo platform -> family
            var order = [];
            var map = {};
            filtered.forEach(function (r) {
                var k = r.platform + '||' + r.family;
                if (!map[k]) { map[k] = { platform: r.platform, family: r.family, items: [] }; order.push(k); }
                map[k].items.push(r);
            });

            var html = '';
            var lastPlatform = null;
            order.forEach(function (k) {
                var g = map[k];
                if (g.platform !== lastPlatform) {
                    if (lastPlatform !== null) html += '</div></div>';
                    var pl = platforms[g.platform] || { label: g.platform, dot: '#9ca3af' };
                    html += '<div class="mp-card mb-6 shadow-xl overflow-hidden">' +
                        '<div class="mp-provider-title">' +
                        '<span class="dot" style="display:inline-block;width:9px;height:9px;border-radius:9999px;background:' + escapeHtml(pl.dot) + '"></span>' +
                        escapeHtml(pl.label) + ' <span class="count">' + filtered.filter(function (x) { return x.platform === g.platform; }).length + ' models</span></div><div>';
                    lastPlatform = g.platform;
                }
                html += '<div class="mp-group-card px-4 py-4">' +
                    '<div class="mp-family mb-3 flex items-center justify-between">' +
                    '<span><i class="fas fa-cubes text-blue-400 mr-2"></i>' + escapeHtml(g.family) + ' <span class="count">' + g.items.length + '</span></span>' +
                    '</div>' +
                    '<div class="mp-table-wrap rounded-xl border border-gray-800 overflow-hidden"><table class="mp-table w-full">' +
                    '<thead><tr><th>' + T.model + '</th><th>' + T.keyType + '</th><th>' + T.level + '</th><th>' + T.context + '</th>' +
                    '<th>' + T.input + '</th><th>' + T.output + '</th><th>' + T.cread + '</th><th>' + T.cwrite + '</th><th>' + T.status + '</th></tr></thead>' +
                    '<tbody>' + g.items.map(rowHtml).join('') + '</tbody></table></div></div>';
            });
            html += '</div></div>';
            box.innerHTML = html;
        }

        function markCopied(el) {
            var icon = el.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check');
            }
            el.classList.add('copied');
            setTimeout(function () {
                if (icon) {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-copy');
                }
                el.classList.remove('copied');
            }, 1200);
        }

        function copyText(text, el) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { markCopied(el); }).catch(function () {});
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '0';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                markCopied(el);
            }
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.mp-copy');
            if (btn && btn.dataset.copy) {
                copyText(btn.dataset.copy, btn);
            }
        });

        var searchInput = document.getElementById('mp-search');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                state.q = (e.target.value || '').toLowerCase().trim();
                render();
            });
        }

        var levelSelect = document.getElementById('mp-level');
        if (levelSelect) {
            levelSelect.addEventListener('change', function (e) {
                state.level = e.target.value;
                render();
            });
        }

        bindPills('mp-provider-filters', 'provider');
        bindPills('mp-platform-filters', 'platform');
        bindPills('mp-key-filters', 'key');
        bindPills('mp-effort-filters', 'effort');

        syncDependentFilters();
        render();
    })();
    </script>
</body>
</html>
