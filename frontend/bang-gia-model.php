<?php
// ========================================================
// TRANG BẢNG GIÁ MODEL AI CHUYÊN NGHIỆP
// Tiệm AI - tiemai.io.vn
// Tra cứu chi tiết giá token theo nhà cung cấp & nền tảng
// Chuẩn giao diện cao cấp 100% theo phong cách GenzShop
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

// Nếu file chưa có dữ liệu hoặc rỗng, đồng bộ ngay
if (!$pricingData || empty($pricingData['rows'])) {
    if (file_exists(__DIR__ . '/../backend/cron_sync_models_pricing.php')) {
        require_once __DIR__ . '/../backend/cron_sync_models_pricing.php';
        dongBoBangGiaModelTuNguon();
        if (file_exists($dataFile)) {
            $pricingData = json_decode(file_get_contents($dataFile), true);
        }
    }
}

// Fallback an toàn nếu vẫn chưa có
if (!$pricingData || empty($pricingData['rows'])) {
    $pricingData = [
        'providers' => ['modelapi' => 'Modelapi.vn', 'gwai' => '1gw.gwai.cloud'],
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
    <title>Bảng Giá Model AI - <?= htmlspecialchars($siteName) ?> (tiemai.io.vn)</title>
    <meta name="description" content="Tra cứu bảng giá token các model AI Claude, OpenAI, Gemini, DeepSeek, Grok, Kimi, GLM theo từng nhà cung cấp, loại key và cấp độ tại <?= htmlspecialchars($siteName) ?>.">
    <link rel="canonical" href="https://tiemai.io.vn/bang-gia-model">
    
    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Phong cách Beige / Ivory Luxury chuẩn 100% GenzShop */
        .mp-wrap { background: #FAF6EE; min-height: 80vh; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .mp-card { background: #ffffff; border: 1px solid #e8edf1; border-radius: 12px; }
        .mp-filters { background: #FBF7EE; border-color: #EAD9B8; }
        .mp-hero { text-align: center; margin-bottom: 20px; }
        .mp-hero h1 { font-size: 1.5rem; font-weight: 800; color: #111827; }
        @media (min-width: 768px) { .mp-hero h1 { font-size: 1.875rem; } }
        .mp-hero p { color: #6b7280; margin-top: 6px; font-size: 13.5px; }
        .mp-notice { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; padding: 14px 18px; margin-bottom: 18px; font-size: 13px; line-height: 1.65; color: #92400E; }
        .mp-notice strong { color: #78350F; }
        .mp-notice .mp-notice-title { display: flex; align-items: center; gap: 8px; font-weight: 800; margin-bottom: 4px; color: #78350F; font-size: 13.5px; }
        .mp-filter-label { font-size: 11px; font-weight: 800; color: #111827; letter-spacing: .04em; }
        .mp-pill { border: 1px solid #e2e8f0; border-radius: 9999px; padding: 6px 14px; font-size: 13px; color: #334155; background: #fff; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-weight: 500; }
        .mp-pill:hover { border-color: #16a34a; color: #15803d; }
        .mp-pill.active { background: #16a34a; border-color: #16a34a; color: #fff; font-weight: 700; box-shadow: 0 1px 3px rgba(22, 163, 74, 0.2); }
        .mp-pill .dot { width: 7px; height: 7px; border-radius: 9999px; display: inline-block; }
        .mp-table th { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .03em; padding: 12px; text-align: left; border-bottom: 1px solid #eef2f5; white-space: nowrap; background: #fafbfc; }
        .mp-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .mp-table tbody tr:hover td { background-color: #f8fafc; }
        .mp-table .price { color: #16a34a; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .mp-chip { display: inline-flex; align-items: center; border-radius: 9999px; padding: 3px 10px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .mp-chip-gray { background: #f1f5f9; color: #475569; }
        .mp-chip-green { background: #dcfce7; color: #15803d; }
        .mp-chip-blue { background: #dbeafe; color: #1d4ed8; }
        .mp-chip-amber { background: #fef3c7; color: #b45309; }
        .mp-status { background: #dcfce7; color: #15803d; }
        .mp-status.off { background: #fee2e2; color: #b91c1c; }
        .mp-search { border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; width: 100%; outline: none; background: #fff; color: #111827; }
        .mp-search:focus { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.12); }
        .mp-select { border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 13px; background: #fff; outline: none; color: #111827; }
        .mp-select:focus { border-color: #16a34a; }
        .mp-provider-title { display: flex; align-items: center; gap: 8px; padding: 14px 16px; border-bottom: 1px solid #eef2f5; font-weight: 800; font-size: 14px; color: #111827; background: #fafbfc; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .mp-provider-title .count { background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; border-radius: 9999px; padding: 2px 8px; }
        .mp-family { font-weight: 700; font-size: 13.5px; color: #111827; }
        .mp-family .count { background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; border-radius: 9999px; padding: 2px 8px; margin-left: 6px; }
        .mp-slug { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; color: #334155; font-weight: 600; }
        .mp-copy { color: #94a3b8; cursor: pointer; border: 0; background: transparent; padding: 3px 6px; font-size: 12px; border-radius: 4px; transition: all .15s; }
        .mp-copy:hover { color: #16a34a; background: #e2e8f0; }
        .mp-copy.copied { color: #16a34a; font-weight: bold; }
        .mp-group-card { border-bottom: 1px solid #eef2f5; }
        .mp-group-card:last-child { border-bottom: none; }
        .mp-empty { padding: 48px 16px; text-align: center; color: #64748b; font-size: 14px; }
        @media (max-width: 1024px) { 
            .mp-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } 
            .mp-table { min-width: 960px; } 
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">
    <!-- Header chuẩn Tiệm AI -->
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Khung chính Bảng Giá Model (mp-wrap Chuẩn GenzShop) -->
    <div class="mp-wrap flex-1">
        <div class="container mx-auto px-3 md:px-4 py-6 md:py-8">
            <div class="max-w-6xl mx-auto">
                
                <!-- Tiêu đề Hero -->
                <div class="mp-hero">
                    <h1>Bảng giá theo Model</h1>
                    <p>Tra cứu giá token theo nhà cung cấp, nền tảng và loại key.</p>
                </div>

                <!-- Lưu ý cách đọc bảng giá -->
                <div class="mp-notice">
                    <div class="mp-notice-title"><i class="fas fa-triangle-exclamation"></i> Lưu ý về cách đọc bảng giá</div>
                    <strong>Giá niêm yết trên mỗi token không quyết định chi phí thực tế.</strong> Chi phí cuối cùng còn phụ thuộc vào <strong>tỷ lệ quy đổi của backend</strong> (backend rate): cùng một model, mỗi nhà cung cấp có thể áp dụng hệ số quy đổi token khác nhau. Ví dụ: gói $10 credit ở nơi có tỷ lệ quy đổi tốt có thể chạy được gấp đôi số prompt so với nơi tỷ lệ kém — dù đơn giá niêm yết trông rẻ hơn. Vì vậy hãy so sánh <strong>chi phí thực tế trên cùng một tác vụ</strong>, không chỉ so đơn giá $/1M token.
                </div>

                <!-- Khối bộ lọc thông minh (mp-filters) -->
                <div class="mp-card mp-filters p-4 md:p-5 mb-4 shadow-sm">
                    <!-- 1. Nhà cung cấp -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="mp-filter-label w-24 shrink-0">NHÀ CUNG CẤP</span>
                        <div class="flex flex-wrap gap-2" id="mp-provider-filters">
                            <button class="mp-pill active" data-provider="all">Tất cả</button>
                            <button class="mp-pill" data-provider="modelapi"><span class="dot" style="background:#16a34a"></span>Modelapi.vn</button>
                            <button class="mp-pill" data-provider="gwai"><span class="dot" style="background:#16a34a"></span>1gw.gwai.cloud</button>
                        </div>
                    </div>

                    <!-- 2. Nền tảng -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="mp-filter-label w-24 shrink-0">NỀN TẢNG</span>
                        <div class="flex flex-wrap gap-2" id="mp-platform-filters">
                            <button class="mp-pill active" data-platform="all">Tất cả</button>
                            <button class="mp-pill" data-platform="claude"><span class="dot" style="background:#d97706"></span>Claude <span class="mp-count opacity-60 text-xs">34</span></button>
                            <button class="mp-pill" data-platform="openai"><span class="dot" style="background:#16a34a"></span>OpenAI <span class="mp-count opacity-60 text-xs">30</span></button>
                            <button class="mp-pill" data-platform="grok"><span class="dot" style="background:#6b7280"></span>Grok <span class="mp-count opacity-60 text-xs">6</span></button>
                            <button class="mp-pill" data-platform="gemini"><span class="dot" style="background:#2563eb"></span>Gemini <span class="mp-count opacity-60 text-xs">7</span></button>
                            <button class="mp-pill" data-platform="deepseek"><span class="dot" style="background:#4d6bfe"></span>DeepSeek <span class="mp-count opacity-60 text-xs">3</span></button>
                            <button class="mp-pill" data-platform="moonshot"><span class="dot" style="background:#10b981"></span>Moonshot AI <span class="mp-count opacity-60 text-xs">2</span></button>
                            <button class="mp-pill" data-platform="zai"><span class="dot" style="background:#84cc16"></span>Z.AI <span class="mp-count opacity-60 text-xs">1</span></button>
                            <button class="mp-pill" data-platform="minimax"><span class="dot" style="background:#22c55e"></span>Minimax <span class="mp-count opacity-60 text-xs">1</span></button>
                            <button class="mp-pill" data-platform="khac"><span class="dot" style="background:#9ca3af"></span>Khác <span class="mp-count opacity-60 text-xs">1</span></button>
                        </div>
                    </div>

                    <!-- 3. Loại key -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="mp-filter-label w-24 shrink-0">LOẠI KEY</span>
                        <div class="flex flex-wrap gap-2" id="mp-key-filters">
                            <button class="mp-pill active" data-key="all">Tất cả</button>
                            <button class="mp-pill" data-key="credit">Key credit</button>
                            <button class="mp-pill" data-key="thang">Key tháng</button>
                        </div>
                    </div>

                    <!-- 4. Effort -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="mp-filter-label w-24 shrink-0">EFFORT</span>
                        <div class="flex flex-wrap gap-2" id="mp-effort-filters">
                            <button class="mp-pill active" data-effort="all">Tất cả</button>
                            <button class="mp-pill" data-effort="Default">Default</button>
                            <button class="mp-pill" data-effort="High">High</button>
                            <button class="mp-pill" data-effort="Max">Max</button>
                        </div>
                    </div>

                    <!-- 5. Tìm kiếm & Cấp độ select -->
                    <div class="flex flex-col md:flex-row gap-2 mt-4">
                        <div class="flex-1">
                            <input class="mp-search" id="mp-search" placeholder="Tìm kiếm model..."/>
                        </div>
                        <select class="mp-select" id="mp-level">
                            <option value="all">Tất cả cấp độ</option>
                            <option value="Efficient">Efficient</option>
                            <option value="Standard">Standard</option>
                            <option value="Frontier">Frontier</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>

                <!-- Danh sách kết quả render động (mp-results) -->
                <div id="mp-results"></div>

            </div>
        </div>
    </div>

    <!-- Footer chuẩn Tiệm AI -->
    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <!-- Logic Xử lý Render & Bộ Lọc Liên Động Chuẩn GenzShop -->
    <script>
    (function () {
        var rows = <?= json_encode($pricingData['rows'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
        var providers = <?= json_encode($pricingData['providers'] ?? ['modelapi' => 'Modelapi.vn', 'gwai' => '1gw.gwai.cloud'], JSON_UNESCAPED_UNICODE) ?>;
        var platforms = <?= json_encode($pricingData['platforms'] ?? [
            'claude'   => ['label' => 'Claude', 'dot' => '#d97706'],
            'openai'   => ['label' => 'OpenAI', 'dot' => '#16a34a'],
            'grok'     => ['label' => 'Grok', 'dot' => '#6b7280'],
            'gemini'   => ['label' => 'Gemini', 'dot' => '#2563eb'],
            'deepseek' => ['label' => 'DeepSeek', 'dot' => '#4d6bfe'],
            'moonshot' => ['label' => 'Moonshot AI', 'dot' => '#10b981'],
            'zai'      => ['label' => 'Z.AI', 'dot' => '#84cc16'],
            'minimax'  => ['label' => 'Minimax', 'dot' => '#22c55e'],
            'khac'     => ['label' => 'Khác', 'dot' => '#9ca3af']
        ], JSON_UNESCAPED_UNICODE) ?>;

        var T = {
            model: "Model",
            keyType: "Loại key",
            level: "Cấp độ",
            context: "Context",
            input: "Input/1M",
            output: "Output/1M",
            cread: "Cache Read/1M",
            cwrite: "Cache Write/1M",
            status: "Trạng thái",
            active: "Hoạt động",
            paused: "Tạm dừng",
            empty: "Không tìm thấy model nào phù hợp với bộ lọc.",
            copied: "Đã copy!",
            both: "Credit + Tháng",
            credit: "Key credit"
        };

        var state = { provider: 'all', platform: 'all', key: 'all', effort: 'all', q: '', level: 'all' };

        // Bộ lọc liên động: chọn NCC nào thì chỉ hiện nền tảng / loại key có dữ liệu của NCC đó.
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
                if (badge) badge.textContent = n > 0 ? ' ' + n : '';
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

        function bindPills(id, key) {
            var box = document.getElementById(id);
            if (!box) return;
            box.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.mp-pill');
                if (!btn) return;
                box.querySelectorAll('.mp-pill').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                state[key] = btn.dataset[key];
                if (key === 'provider') syncDependentFilters();
                render();
            });
        }
        bindPills('mp-provider-filters', 'provider');
        bindPills('mp-platform-filters', 'platform');
        bindPills('mp-key-filters', 'key');
        bindPills('mp-effort-filters', 'effort');

        document.getElementById('mp-search').addEventListener('input', function (e) { state.q = e.target.value.trim().toLowerCase(); render(); });
        document.getElementById('mp-level').addEventListener('change', function (e) { state.level = e.target.value; render(); });

        function matchKey(row) {
            if (state.key === 'all') return true;
            if (state.key === 'credit') return row.key === 'credit' || row.key === 'both';
            if (state.key === 'thang') return row.key === 'thang' || row.key === 'both';
            return true;
        }

        function rowHtml(r) {
            var effortCls = r.effort === 'Max' ? 'mp-chip-green' : (r.effort === 'High' ? 'mp-chip-amber' : 'mp-chip-gray');
            var keyCls = r.key === 'credit' ? 'mp-chip-blue' : 'mp-chip-green';
            var keyLabel = r.key === 'credit' ? T.credit : (r.key === 'thang' ? "Key tháng" : T.both);
            var levelCls = r.level === 'Frontier' ? 'mp-chip-amber' : (r.level === 'Efficient' ? 'mp-chip-green' : 'mp-chip-blue');
            
            var extra = '';
            if (r.extra) {
                extra = '<tr>' +
                    '<td></td><td></td>' +
                    '<td colspan="2" style="color:#6b7280; font-size:12px;">' + escapeHtml(r.extra.label) + '</td>' +
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
                '<span class="mp-slug">' + escapeHtml(r.slug) + '</span> ' +
                '<button type="button" class="mp-copy" data-copy="' + escapeHtml(r.slug) + '" title="Copy" aria-label="Copy"><i class="fas fa-copy"></i></button></td>' +
                '<td><span class="mp-chip ' + keyCls + '">' + escapeHtml(keyLabel) + '</span></td>' +
                '<td><span class="mp-chip ' + levelCls + '">' + escapeHtml(r.level || 'Standard') + '</span></td>' +
                '<td style="font-weight:600">' + escapeHtml(r.context) + '</td>' +
                priceCells +
                '<td><span class="mp-chip ' + (r.status ? 'mp-status' : 'mp-status off') + '">' + (r.status ? T.active : T.paused) + '</span></td>' +
                '</tr>' + extra;
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
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
            if (!filtered.length) { box.innerHTML = '<div class="mp-card mp-empty">' + T.empty + '</div>'; return; }

            // Gom nhóm theo platform -> family (giữ thứ tự file config)
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
                    html += '<div class="mp-card mb-4 shadow-sm"><div class="mp-provider-title">' +
                        '<span class="dot" style="display:inline-block;width:9px;height:9px;border-radius:9999px;background:' + escapeHtml(pl.dot) + '"></span>' +
                        escapeHtml(pl.label) + ' <span class="count">' + filtered.filter(function (x) { return x.platform === g.platform; }).length + '</span></div><div>';
                    lastPlatform = g.platform;
                }
                html += '<div class="mp-group-card px-4 py-3"><div class="mp-family">' + escapeHtml(g.family) +
                    '<span class="count">' + g.items.length + '</span></div>' +
                    '<div class="mp-table-wrap"><table class="mp-table w-full">' +
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
            el.title = T.copied;
            setTimeout(function () {
                if (icon) {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-copy');
                }
                el.classList.remove('copied');
                el.title = 'Copy';
            }, 1200);
        }

        function copyText(text, el) {
            function fallback() {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '0';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                document.body.removeChild(ta);
                if (ok) markCopied(el);
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { markCopied(el); }).catch(fallback);
            } else {
                fallback();
            }
        }

        document.getElementById('mp-results').addEventListener('click', function (ev) {
            var el = ev.target.closest('[data-copy]');
            if (!el) return;
            ev.preventDefault();
            copyText(el.getAttribute('data-copy') || '', el);
        });

        syncDependentFilters();
        render();
    })();
    </script>
</body>
</html>
