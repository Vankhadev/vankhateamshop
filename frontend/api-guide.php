<?php
// Tài liệu tích hợp API dành cho người dùng. Không đưa API key thật vào HTML.
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();
header('Cache-Control: no-store, private');
$baseUrl = rtrim(defined('SITE_URL') ? SITE_URL : 'https://tiemai.io.vn', '/');
$baseUrlHtml = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
$apiBaseHtml = htmlspecialchars($baseUrl . '/v1', ENT_QUOTES, 'UTF-8');
$tenWebsite = htmlspecialchars(defined('SHOP_NAME') ? SHOP_NAME : 'TiemAI.io.vn', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>Hướng dẫn tích hợp & sử dụng API - <?= $tenWebsite ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#020617;--card:#0f172aee;--muted:#94a3b8;--text:#f8fafc;--border:#1e293b;--primary:#6366f1;--primary2:#8b5cf6;--code:#070b14;--success:#34d399;--warn:#fbbf24}
        *{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:86px}body{margin:0;background:var(--bg);color:var(--text);font-family:Manrope,system-ui,sans-serif;line-height:1.65}body:before{content:"";position:fixed;inset:0;pointer-events:none;background:radial-gradient(circle at 12% 5%,#312e8128,transparent 32%),radial-gradient(circle at 92% 8%,#0ea5e925,transparent 30%),linear-gradient(#6366f10e 1px,transparent 1px),linear-gradient(90deg,#6366f10e 1px,transparent 1px);background-size:auto,auto,32px 32px,32px 32px;mask-image:linear-gradient(#000 35%,transparent 95%);z-index:-1}.topbar{position:sticky;top:0;z-index:20;background:#020617dd;border-bottom:1px solid var(--border);backdrop-filter:blur(16px)}.topbar-inner{max-width:1280px;margin:auto;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;gap:14px}.brand{color:#fff;text-decoration:none;font-weight:800}.brand small{color:#818cf8;margin-left:8px}.nav a{color:#cbd5e1;text-decoration:none;font-size:13px;margin-left:16px}.nav a:hover{color:#fff}.layout{max-width:1280px;margin:auto;padding:28px 20px 80px;display:grid;grid-template-columns:260px minmax(0,1fr);gap:32px}.toc{position:sticky;top:84px;max-height:calc(100vh - 105px);overflow:auto;background:#0f172ad9;border:1px solid var(--border);border-radius:16px;padding:16px 14px}.toc h2{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin:0 10px 12px}.toc a{display:block;color:#cbd5e1;text-decoration:none;font-size:13px;font-weight:600;padding:8px 10px;border-radius:9px}.toc a:hover,.toc a.active{background:#6366f126;color:#a5b4fc}.hero,.section{background:var(--card);border:1px solid var(--border);border-radius:18px;box-shadow:0 14px 36px #0007;padding:28px 30px;margin-bottom:24px}.pill{display:inline-flex;gap:8px;align-items:center;border:1px solid #6366f160;background:#6366f126;color:#a5b4fc;border-radius:999px;padding:4px 12px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.dot{width:7px;height:7px;border-radius:50%;background:var(--success);box-shadow:0 0 8px var(--success)}h1{font-size:34px;line-height:1.2;margin:14px 0 10px;letter-spacing:-.04em}h2{font-size:21px;margin:0 0 8px}h3{font-size:17px;margin:0 0 8px}p{color:#cbd5e1;margin:0 0 12px}.hero p{max-width:850px;font-size:15px}.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.chip{color:#cbd5e1;border:1px solid var(--border);background:#0b1222;border-radius:999px;padding:5px 12px;text-decoration:none;font-size:12px}.chip:hover{border-color:#818cf8;color:#a5b4fc}.num{display:inline-flex;width:32px;height:32px;align-items:center;justify-content:center;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--primary2));font-weight:800;margin-right:10px}.section-head{display:flex;align-items:center;margin-bottom:10px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}.field{background:#1e293b;border:1px solid var(--border);border-radius:14px;padding:15px}.label{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px}.value{font-family:ui-monospace,monospace;background:#0b1120;border:1px solid #334155;border-radius:9px;padding:9px 12px;color:#93c5fd;word-break:break-all;font-size:13px}.hint{font-size:12px;color:var(--muted);margin-top:8px}.code{background:var(--code);border:1px solid #1e293b;border-radius:13px;overflow:auto;margin:14px 0}.code-head{padding:8px 13px;color:#94a3b8;background:#0f172a;border-bottom:1px solid #1e293b;font-size:12px;display:flex;justify-content:space-between}.copy{border:1px solid #334155;background:#1e293b;color:#e2e8f0;border-radius:7px;padding:4px 9px;cursor:pointer}.copy:hover{background:#334155}.code pre{margin:0;padding:15px 17px;color:#e2e8f0;font:13px/1.65 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap}.callout{border:1px solid #6366f160;border-left:4px solid var(--primary);background:#6366f11a;border-radius:12px;padding:13px 16px;color:#e2e8f0;margin:14px 0}.warn{border-color:#f59e0b66;border-left-color:var(--warn);background:#f59e0b14}.table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:13px}.table{width:100%;border-collapse:collapse;font-size:13px}.table th,.table td{text-align:left;padding:11px 13px;border-bottom:1px solid var(--border)}.table th{font-size:11px;color:var(--muted);text-transform:uppercase;background:#1e293b}.table tr:last-child td{border-bottom:0}.tag{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800}.tag.good{color:var(--success);background:#10b9811f}.tag.warn{color:var(--warn);background:#f59e0b1f}.tool{padding-top:20px;margin-top:20px;border-top:1px solid var(--border)}.tool:first-child{border-top:0;margin-top:0;padding-top:0}.support{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.button{display:inline-block;background:var(--primary);color:#fff;text-decoration:none;border-radius:10px;padding:9px 15px;font-size:13px;font-weight:800}.button.secondary{background:#1e293b}.footer-note{color:#64748b;text-align:center;font-size:12px;margin-top:30px}@media(max-width:900px){.layout{grid-template-columns:1fr;gap:16px}.toc{position:relative;top:0;max-height:none}h1{font-size:28px}.hero,.section{padding:21px 18px}.nav a{margin-left:8px;font-size:12px}.nav a:first-child{display:none}}
        #tools, #sdk { display: none; }
    </style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><a class="brand" href="/">TiemAI <small>Extension API Docs</small></a><nav class="nav"><a href="/">Trang chủ</a><a href="/buyer-api">API đại lý</a><a href="/tra-cuu-key">Tra cứu Key</a><a href="/login">Đăng nhập</a></nav></div></header>
<div class="layout">
    <aside class="toc"><h2>☰ Mục lục</h2><a class="active" href="#quickstart">1. Base URL & API Key</a><a href="#curl">2. Test nhanh cURL</a><a href="#base-urls">3. Base URL theo Client</a><a href="#detailed-tools">4. Cài đặt theo công cụ</a><a href="#sdk-curl">5. SDK & HTTP</a><a href="#troubleshooting">6. Xử lý sự cố</a><a href="#security">7. Bảo mật API Key</a></aside>
    <main>
        <section class="hero"><span class="pill"><span class="dot"></span>Tài liệu cổng API trực tuyến</span><h1>Hướng dẫn tích hợp & sử dụng API</h1><p>Kết nối Claude Code, Codex CLI, OpenCode, DeepSeek TUI, Gemini CLI, Cline, Roo Code, Cursor, Continue và SDK trong vài bước. Tài liệu này dùng endpoint của <strong><?= $tenWebsite ?></strong>; không công khai URL nhà cung cấp.</p><div class="chips"><a class="chip" href="#claude-code">⚡ Claude Code</a><a class="chip" href="#codex-cli">⚡ Codex CLI</a><a class="chip" href="#opencode">⚡ OpenCode</a><a class="chip" href="#gemini-cli">✨ Gemini CLI</a><a class="chip" href="#cline">🧩 Cline</a><a class="chip" href="#kilo-code">⚙️ Kilo Code</a><a class="chip" href="#cursor-continue">💻 Cursor / Continue</a><a class="chip" href="#troubleshooting">🛠️ Fix 401 / 404</a></div></section>
        <section id="quickstart" class="section"><div class="section-head"><span class="num">01</span><h2>Cấu hình Base URL & API Key</h2></div><p>Dùng Base URL bên dưới cho các ứng dụng của bạn. API key thật chỉ được nhập ở ứng dụng của bạn hoặc lấy qua khu vực tài khoản đã đăng nhập.</p><div class="grid"><div class="field"><div class="label">Base URL (Origin)<span class="tag good">Website hiện tại</span></div><div class="value"><?= $baseUrlHtml ?></div><div class="hint">Claude Code và Gemini CLI dùng domain gốc, không thêm <code>/v1</code>.</div></div><div class="field"><div class="label">OpenAI-compatible Base URL</div><div class="value"><?= $apiBaseHtml ?></div><div class="hint">Codex, OpenCode, Cline, Roo Code, Cursor, Continue và OpenAI SDK dùng hậu tố <code>/v1</code>.</div></div></div><div class="callout warn"><strong>Không nhập API key vào URL.</strong> Không đưa key vào query string, GitHub, ảnh chụp màn hình hoặc mã nguồn frontend công khai. Trang tài liệu này chỉ dùng placeholder <code>YOUR_API_KEY</code>.</div></section>
        <section id="curl" class="section"><div class="section-head"><span class="num">02</span><h2>Kiểm tra kết nối nhanh với cURL</h2></div><p>Thay <code>YOUR_API_KEY</code> bằng API key của bạn trong terminal. Key không được lưu trong trang web này.</p><div class="grid"><div class="code"><div class="code-head"><span>GET /v1/models</span><button class="copy" data-copy="curl <?= htmlspecialchars($baseUrl . '/v1/models', ENT_QUOTES, 'UTF-8') ?> -H &quot;Authorization: Bearer YOUR_API_KEY&quot;">Sao chép</button></div><pre>curl "<?= $apiBaseHtml ?>/models" \
  -H "Authorization: Bearer YOUR_API_KEY"</pre></div><div class="code"><div class="code-head"><span>POST /v1/chat/completions</span><button class="copy" data-copy="curl -X POST <?= htmlspecialchars($baseUrl . '/v1/chat/completions', ENT_QUOTES, 'UTF-8') ?> -H &quot;Content-Type: application/json&quot; -H &quot;Authorization: Bearer YOUR_API_KEY&quot;">Sao chép</button></div><pre>curl -X POST "<?= $apiBaseHtml ?>/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"model":"gpt-5.5","messages":[{"role":"user","content":"Hello!"}]}'</pre></div></div></section>
        <section id="base-urls" class="section"><div class="section-head"><span class="num">03</span><h2>Bảng quy tắc Base URL theo từng Client</h2></div><p>Lỗi 404 thường do thừa hoặc thiếu hậu tố <code>/v1</code>. Kiểm tra bảng trước khi cấu hình.</p><div class="table-wrap"><table class="table"><thead><tr><th>Công cụ / Client</th><th>Biến / trường cấu hình</th><th>Base URL chuẩn</th><th>Quy tắc</th></tr></thead><tbody><tr><td>Claude Code</td><td><code>ANTHROPIC_BASE_URL</code></td><td><code><?= $baseUrlHtml ?></code></td><td><span class="tag warn">Không /v1</span></td></tr><tr><td>Gemini CLI</td><td><code>GOOGLE_GEMINI_BASE_URL</code></td><td><code><?= $baseUrlHtml ?></code></td><td><span class="tag warn">Không /v1</span></td></tr><tr><td>Codex CLI</td><td><code>base_url</code></td><td><code><?= $apiBaseHtml ?></code></td><td><span class="tag good">Có /v1</span></td></tr><tr><td>OpenCode / Cline / Roo Code</td><td><code>baseURL</code></td><td><code><?= $apiBaseHtml ?></code></td><td><span class="tag good">Có /v1</span></td></tr><tr><td>Cursor / Continue / OpenAI SDK</td><td><code>apiBase / base_url</code></td><td><code><?= $apiBaseHtml ?></code></td><td><span class="tag good">Có /v1</span></td></tr></tbody></table></div></section>
        <section id="tools" class="section"><div class="section-head"><span class="num">04</span><h2>Hướng dẫn cài đặt theo công cụ</h2></div><p>Các mẫu dưới đây không chứa token thật. Hãy đặt key trong biến môi trường hoặc file cấu hình riêng của máy bạn.</p><div id="claude" class="tool"><h3>⚡ Claude Code <span class="tag warn">Không /v1</span></h3><p>Claude Code tự nối đường dẫn Messages API.</p><div class="code"><div class="code-head"><span>macOS / Linux</span><button class="copy" data-copy="export ANTHROPIC_BASE_URL=<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>\nexport ANTHROPIC_AUTH_TOKEN=YOUR_API_KEY\nclaude">Sao chép</button></div><pre>export ANTHROPIC_BASE_URL="<?= $baseUrlHtml ?>"
export ANTHROPIC_AUTH_TOKEN="YOUR_API_KEY"
claude</pre></div></div><div id="codex" class="tool"><h3>⚡ Codex CLI <span class="tag good">Có /v1</span></h3><div class="code"><div class="code-head"><span>config.toml</span><button class="copy" data-copy="model_provider = &quot;TiemAI&quot;\n[model_providers.TiemAI]\nbase_url = &quot;<?= htmlspecialchars($baseUrl . '/v1', ENT_QUOTES, 'UTF-8') ?>&quot;\nwire_api = &quot;responses&quot;">Sao chép</button></div><pre>model_provider = "TiemAI"
[model_providers.TiemAI]
base_url = "<?= $apiBaseHtml ?>"
wire_api = "responses"</pre></div></div><div id="opencode" class="tool"><h3>⚡ OpenCode <span class="tag good">Có /v1</span></h3><div class="code"><div class="code-head"><span>opencode.json</span><button class="copy" data-copy="{ &quot;provider&quot;: { &quot;tiemai&quot;: { &quot;options&quot;: { &quot;baseURL&quot;: &quot;<?= htmlspecialchars($baseUrl . '/v1', ENT_QUOTES, 'UTF-8') ?>&quot;, &quot;apiKey&quot;: &quot;YOUR_API_KEY&quot; } } }">Sao chép</button></div><pre>{
  "provider": {
    "tiemai": {
      "options": {
        "baseURL": "<?= $apiBaseHtml ?>",
        "apiKey": "YOUR_API_KEY"
      }
    }
  }
}</pre></div></div><div id="gemini" class="tool"><h3>✨ Gemini CLI <span class="tag warn">Không /v1</span></h3><div class="code"><div class="code-head"><span>macOS / Linux</span><button class="copy" data-copy="export GOOGLE_GEMINI_BASE_URL=<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>\nexport GEMINI_API_KEY=YOUR_API_KEY">Sao chép</button></div><pre>export GOOGLE_GEMINI_BASE_URL="<?= $baseUrlHtml ?>"
export GEMINI_API_KEY="YOUR_API_KEY"
gemini</pre></div></div><div id="cline" class="tool"><h3>🧩 Cline / Roo Code <span class="tag good">Có /v1</span></h3><p>Chọn provider <strong>OpenAI Compatible</strong>, nhập Base URL <code><?= $apiBaseHtml ?></code>, API Key và Model ID.</p></div><div id="cursor" class="tool"><h3>💻 Cursor / Continue <span class="tag good">Có /v1</span></h3><p>Chọn OpenAI-compatible provider, Base URL <code><?= $apiBaseHtml ?></code>, sau đó nhập API key trong phần cài đặt riêng của ứng dụng.</p></div></section>
        <section id="detailed-tools" class="section">
            <div class="section-head"><span class="num">04</span><h2>Hướng dẫn cài đặt chi tiết theo từng công cụ</h2></div>
            <p>Chọn đúng công cụ và hệ điều hành. Các mẫu bên dưới dùng Base URL của website này và API key bạn nhập ở trình duyệt.</p>

            <div id="claude-code" class="tool">
                <div class="tool-title"><h3><span class="tool-icon">&gt;_</span>4.1. Claude Code (Anthropic CLI)</h3><span class="tag tag-no">Không có /v1</span></div>
                <p>Claude Code tự nối <code>/v1/messages</code>, nên <code>ANTHROPIC_BASE_URL</code> chỉ dùng domain gốc.</p>
                <div class="tabs"><button class="tab active" data-tab="claude-unix">🍎 macOS / Linux</button><button class="tab" data-tab="claude-ps">🪟 PowerShell</button><button class="tab" data-tab="claude-cmd">🪟 Windows CMD</button><button class="tab" data-tab="claude-json">📄 settings.json</button></div>
                <div class="panel active" id="claude-unix"><div class="code"><div class="code-head"><span>macOS / Linux Terminal</span><button data-copy-panel="claude-unix">Sao chép</button></div><pre>export ANTHROPIC_BASE_URL="<?= $baseUrlHtml ?>"
export ANTHROPIC_AUTH_TOKEN="YOUR_API_KEY"
export CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1
claude</pre></div></div>
                <div class="panel" id="claude-ps"><div class="code"><div class="code-head"><span>PowerShell</span><button data-copy-panel="claude-ps">Sao chép</button></div><pre>$env:ANTHROPIC_BASE_URL="<?= $baseUrlHtml ?>"
$env:ANTHROPIC_AUTH_TOKEN="YOUR_API_KEY"
$env:CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC="1"
claude</pre></div></div>
                <div class="panel" id="claude-cmd"><div class="code"><div class="code-head"><span>Windows CMD</span><button data-copy-panel="claude-cmd">Sao chép</button></div><pre>set ANTHROPIC_BASE_URL=<?= $baseUrlHtml ?>
set ANTHROPIC_AUTH_TOKEN=YOUR_API_KEY
claude</pre></div></div>
                <div class="panel" id="claude-json"><div class="code"><div class="code-head"><span>~/.claude/settings.json</span><button data-copy-panel="claude-json">Sao chép</button></div><pre>{
  "env": {
    "ANTHROPIC_BASE_URL": "<?= $baseUrlHtml ?>",
    "ANTHROPIC_AUTH_TOKEN": "YOUR_API_KEY"
  }
}</pre></div></div>
            </div>

            <div id="codex-cli" class="tool">
                <div class="tool-title"><h3><span class="tool-icon">&lt;/&gt;</span>4.2. OpenAI Codex CLI</h3><span class="tag tag-yes">Có /v1</span></div>
                <p>Codex CLI dùng Responses API với <code>wire_api = "responses"</code>. Tạo <code>config.toml</code> và <code>auth.json</code> trong <code>~/.codex</code>.</p>
                <div class="tabs"><button class="tab active" data-tab="codex-config">⚡ config.toml</button><button class="tab" data-tab="codex-auth">🔑 auth.json</button></div>
                <div class="panel active" id="codex-config"><div class="code"><div class="code-head"><span>~/.codex/config.toml</span><button data-copy-panel="codex-config">Sao chép</button></div><pre>model_provider = "TiemAI"
model = "YOUR_MODEL_ID"
disable_response_storage = true

[model_providers.TiemAI]
name = "TiemAI"
base_url = "<?= $apiBaseHtml ?>"
wire_api = "responses"
requires_openai_auth = true</pre></div></div>
                <div class="panel" id="codex-auth"><div class="code"><div class="code-head"><span>~/.codex/auth.json</span><button data-copy-panel="codex-auth">Sao chép</button></div><pre>{
  "OPENAI_API_KEY": "YOUR_API_KEY"
}</pre></div></div>
            </div>

            <div id="opencode" class="tool">
                <div class="tool-title"><h3><span class="tool-icon">▦</span>4.3. OpenCode</h3><span class="tag tag-yes">Có /v1</span></div>
                <p>Đặt <code>opencode.json</code> trong thư mục dự án hoặc <code>~/.config/opencode/opencode.json</code>.</p>
                <div class="tabs"><button class="tab active" data-tab="oc-openai">OpenAI (GPT/Codex)</button><button class="tab" data-tab="oc-anthropic">Anthropic (Claude)</button><button class="tab" data-tab="oc-gemini">Google Gemini</button></div>
                <div class="panel active" id="oc-openai"><div class="code"><div class="code-head"><span>opencode.json · OpenAI</span><button data-copy-panel="oc-openai">Sao chép</button></div><pre>{
  "provider": {
    "tiemai": {
      "options": {
        "baseURL": "<?= $apiBaseHtml ?>",
        "apiKey": "YOUR_API_KEY"
      },
      "models": {"YOUR_MODEL_ID": {"name": "YOUR_MODEL_ID"}}
    }
  }
}</pre></div></div>
                <div class="panel" id="oc-anthropic"><div class="code"><div class="code-head"><span>opencode.json · Anthropic</span><button data-copy-panel="oc-anthropic">Sao chép</button></div><pre>{
  "provider": {"tiemai": {"options": {
    "baseURL": "<?= $baseUrlHtml ?>",
    "apiKey": "YOUR_API_KEY"
  }}}
}</pre></div></div>
                <div class="panel" id="oc-gemini"><div class="code"><div class="code-head"><span>opencode.json · Gemini</span><button data-copy-panel="oc-gemini">Sao chép</button></div><pre>{
  "provider": {"tiemai": {"options": {
    "baseURL": "<?= htmlspecialchars($baseUrl . '/v1beta', ENT_QUOTES, 'UTF-8') ?>",
    "apiKey": "YOUR_API_KEY"
  }}}
}</pre></div></div>
            </div>

            <div id="codewhale" class="tool">
                <div class="tool-title"><h3><span class="tool-icon">🐋</span>4.4. DeepSeek TUI (CodeWhale)</h3><span class="tag tag-yes">Có /v1</span></div>
                <p>CodeWhale là tên mới của DeepSeek TUI. Cài bằng <code>npm install -g codewhale</code>, sau đó đặt Base URL có <code>/v1</code>.</p>
                <div class="tabs"><button class="tab active" data-tab="cw-unix">🍎 macOS / Linux</button><button class="tab" data-tab="cw-ps">🪟 PowerShell</button><button class="tab" data-tab="cw-config">📄 config.toml</button></div>
                <div class="panel active" id="cw-unix"><div class="code"><div class="code-head"><span>Terminal</span><button data-copy-panel="cw-unix">Sao chép</button></div><pre>npm install -g codewhale
export DEEPSEEK_API_KEY="YOUR_API_KEY"
export CODEWHALE_BASE_URL="<?= $apiBaseHtml ?>"
export CODEWHALE_MODEL="YOUR_MODEL_ID"
codewhale</pre></div></div>
                <div class="panel" id="cw-ps"><div class="code"><div class="code-head"><span>PowerShell</span><button data-copy-panel="cw-ps">Sao chép</button></div><pre>npm install -g codewhale
$env:DEEPSEEK_API_KEY="YOUR_API_KEY"
$env:CODEWHALE_BASE_URL="<?= $apiBaseHtml ?>"
codewhale</pre></div></div>
                <div class="panel" id="cw-config"><div class="code"><div class="code-head"><span>config.toml</span><button data-copy-panel="cw-config">Sao chép</button></div><pre>base_url = "<?= $apiBaseHtml ?>"
api_key = "YOUR_API_KEY"
model = "YOUR_MODEL_ID"</pre></div></div>
            </div>

            <div id="gemini-cli" class="tool">
                <div class="tool-title"><h3><span class="tool-icon">✦</span>4.5. Google Gemini CLI</h3><span class="tag tag-no">Không có /v1</span></div>
                <p>Gemini CLI tự nối <code>/v1beta</code>, vì vậy chỉ dùng domain gốc.</p>
                <div class="tabs"><button class="tab active" data-tab="gemini-unix">🍎 macOS / Linux</button><button class="tab" data-tab="gemini-ps">🪟 PowerShell</button><button class="tab" data-tab="gemini-cmd">🪟 Windows CMD</button></div>
                <div class="panel active" id="gemini-unix"><div class="code"><div class="code-head"><span>Terminal</span><button data-copy-panel="gemini-unix">Sao chép</button></div><pre>export GOOGLE_GEMINI_BASE_URL="<?= $baseUrlHtml ?>"
export GEMINI_API_KEY="YOUR_API_KEY"
export GEMINI_MODEL="YOUR_MODEL_ID"
gemini</pre></div></div>
                <div class="panel" id="gemini-ps"><div class="code"><div class="code-head"><span>PowerShell</span><button data-copy-panel="gemini-ps">Sao chép</button></div><pre>$env:GOOGLE_GEMINI_BASE_URL="<?= $baseUrlHtml ?>"
$env:GEMINI_API_KEY="YOUR_API_KEY"
$env:GEMINI_MODEL="YOUR_MODEL_ID"
gemini</pre></div></div>
                <div class="panel" id="gemini-cmd"><div class="code"><div class="code-head"><span>Windows CMD</span><button data-copy-panel="gemini-cmd">Sao chép</button></div><pre>set GOOGLE_GEMINI_BASE_URL=<?= $baseUrlHtml ?>
set GEMINI_API_KEY=YOUR_API_KEY
set GEMINI_MODEL=YOUR_MODEL_ID
gemini</pre></div></div>
            </div>

            <div id="omp-cli" class="tool"><div class="tool-title"><h3><span class="tool-icon">🥧</span>4.6. Oh My Pi (omp)</h3><span class="tag tag-yes">Có /v1</span></div><p>Cài <code>omp</code>, sau đó tạo <code>~/.omp/agent/models.yml</code>. API key có thể thay bằng tên biến môi trường.</p><div class="tabs"><button class="tab active" data-tab="omp-ps">🪟 Windows PowerShell</button><button class="tab" data-tab="omp-yaml">📄 models.yml</button></div><div class="panel active" id="omp-ps"><div class="code"><div class="code-head"><span>PowerShell</span><button data-copy-panel="omp-ps">Sao chép</button></div><pre>irm https://omp.sh/install.ps1 | iex
New-Item -ItemType Directory -Force -Path "$env:USERPROFILE\.omp\agent"
notepad "$env:USERPROFILE\.omp\agent\models.yml"
omp --model tiemai/YOUR_MODEL_ID</pre></div></div><div class="panel" id="omp-yaml"><div class="code"><div class="code-head"><span>models.yml</span><button data-copy-panel="omp-yaml">Sao chép</button></div><pre>providers:
  tiemai:
    baseUrl: <?= $apiBaseHtml ?>
    api: openai-completions
    apiKey: "YOUR_API_KEY"
    models:
      - id: YOUR_MODEL_ID
        name: YOUR_MODEL_ID
        contextWindow: 100000
        maxTokens: 8192</pre></div></div></div>

            <div id="cline" class="tool"><div class="tool-title"><h3><span class="tool-icon">🧩</span>4.7. Cline</h3><span class="tag tag-yes">Có /v1</span></div><p>Trong Cline → Settings, chọn <strong>OpenAI Compatible</strong>.</p><div class="code"><div class="code-head"><span>Cline Settings</span><button data-copy-panel="cline-code">Sao chép</button></div><pre>API Provider : OpenAI Compatible
Base URL     : <?= $apiBaseHtml ?>
API Key      : YOUR_API_KEY
Model ID     : YOUR_MODEL_ID</pre></div><div id="cline-code" hidden></div></div>
            <div id="roo-code" class="tool"><div class="tool-title"><h3><span class="tool-icon">🧩</span>4.8. Roo Code</h3><span class="tag tag-yes">Có /v1</span></div><p>Trong Roo Code → Settings → Providers, chọn OpenAI Compatible.</p><div class="code"><div class="code-head"><span>Roo Code Settings</span><button data-copy-panel="roo-code-text">Sao chép</button></div><pre>API Provider : OpenAI Compatible
Base URL     : <?= $apiBaseHtml ?>
API Key      : YOUR_API_KEY
Model ID     : YOUR_MODEL_ID</pre></div><div id="roo-code-text" hidden></div></div>
            <div id="kilo-code" class="tool"><div class="tool-title"><h3><span class="tool-icon">⚙️</span>4.9. Kilo Code</h3><span class="tag tag-yes">Có /v1</span></div><p>Trong Kilo Code, chọn provider <strong>OpenAI Compatible</strong>. Nhập Base URL có <code>/v1</code>, API key và model thuộc gói bạn đã mua.</p><div class="code"><div class="code-head"><span>Kilo Code · OpenAI Compatible</span><button data-copy-panel="kilo-code-text">Sao chép</button></div><pre>Provider: OpenAI Compatible
Base URL: <?= $apiBaseHtml ?>
API Key: YOUR_API_KEY
Model: YOUR_MODEL_ID</pre></div><div id="kilo-code-text" hidden></div></div>
            <div id="cursor-continue" class="tool"><div class="tool-title"><h3><span class="tool-icon">▣</span>4.10. Cursor / Continue</h3><span class="tag tag-yes">Có /v1</span></div><p>Cursor dùng OpenAI-compatible provider. Continue dùng <code>provider: openai</code> và <code>apiBase</code> có <code>/v1</code>.</p><div class="code"><div class="code-head"><span>Continue config.yaml</span><button data-copy-panel="cursor-code">Sao chép</button></div><pre>name: TiemAI Config
version: 0.0.1
schema: v1
models:
  - name: YOUR_MODEL_ID
    provider: openai
    model: YOUR_MODEL_ID
    apiBase: <?= $apiBaseHtml ?>
    apiKey: YOUR_API_KEY</pre></div><div id="cursor-code" hidden></div></div>
            <div id="sdk-curl" class="tool"><div class="tool-title"><h3><span class="tool-icon">&lt;/&gt;</span>4.10. Tích hợp SDK (Python / Node.js) & HTTP cURL</h3></div><p>Dùng SDK chính thức của OpenAI hoặc Anthropic với Base URL nội bộ của website.</p><div class="code"><div class="code-head"><span>Python · OpenAI SDK</span><button data-copy-panel="sdk-python">Sao chép</button></div><pre>from openai import OpenAI
client = OpenAI(base_url="<?= $apiBaseHtml ?>", api_key="YOUR_API_KEY")
response = client.chat.completions.create(
    model="YOUR_MODEL_ID",
    messages=[{"role":"user", "content":"Hello!"}]
)
print(response.choices[0].message.content)</pre></div><div id="sdk-python" hidden></div></div>
        </section>

        <section id="sdk" class="section"><div class="section-head"><span class="num">05</span><h2>SDK & HTTP: cấu hình chi tiết</h2></div><p>Với SDK chuẩn OpenAI, luôn dùng endpoint có <code>/v1</code>. API key được đọc từ biến môi trường trên máy của bạn.</p><div class="grid"><div id="kilo" class="tool"><h3>⚙️ Kilo Code</h3><p>Trong Kilo Code, chọn provider <strong>OpenAI Compatible</strong> hoặc provider tương thích OpenAI. Nhập Base URL và Model ID như sau:</p><div class="code"><div class="code-head"><span>Kilo Code / OpenAI Compatible</span><button class="copy" data-copy="Provider: OpenAI Compatible\nBase URL: <?= htmlspecialchars($baseUrl . '/v1', ENT_QUOTES, 'UTF-8') ?>\nAPI Key: YOUR_API_KEY\nModel: YOUR_MODEL_ID">Sao chép</button></div><pre>Provider: OpenAI Compatible
Base URL: <?= $apiBaseHtml ?>
API Key: YOUR_API_KEY
Model: YOUR_MODEL_ID</pre></div><p class="hint">Tên model phải đúng model thuộc gói/key bạn đã mua. Không dùng URL nhà cung cấp trong Kilo Code.</p></div><div id="anthropic-sdk" class="tool"><h3>🟠 Anthropic SDK</h3><p>Anthropic SDK tự nối <code>/v1/messages</code>, vì vậy dùng domain gốc, không thêm <code>/v1</code>.</p><div class="code"><div class="code-head"><span>Python</span><button class="copy" data-copy="from anthropic import Anthropic\nclient = Anthropic(api_key=\"YOUR_API_KEY\", base_url=\"<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>\")">Sao chép</button></div><pre>from anthropic import Anthropic

client = Anthropic(
    api_key="YOUR_API_KEY",
    base_url="<?= $baseUrlHtml ?>"
)
message = client.messages.create(
    model="claude-sonnet-4-6",
    max_tokens=256,
    messages=[{"role": "user", "content": "Hello!"}]
)</pre></div></div></div><div class="grid"><div class="tool"><h3>🐍 OpenAI SDK Python</h3><div class="code"><div class="code-head"><span>Python</span><button class="copy" data-copy="from openai import OpenAI\nclient = OpenAI(api_key=\"YOUR_API_KEY\", base_url=\"<?= htmlspecialchars($baseUrl . '/v1', ENT_QUOTES, 'UTF-8') ?>\")">Sao chép</button></div><pre>import os
from openai import OpenAI

client = OpenAI(
    api_key=os.environ["OPENAI_API_KEY"],
    base_url="<?= $apiBaseHtml ?>"
)
response = client.chat.completions.create(
    model="YOUR_MODEL_ID",
    messages=[{"role": "user", "content": "Hello!"}]
)
print(response.choices[0].message.content)</pre></div></div><div class="tool"><h3>🟩 OpenAI SDK Node.js</h3><div class="code"><div class="code-head"><span>Node.js</span><button class="copy" data-copy="import OpenAI from 'openai';\nconst client = new OpenAI({ apiKey: 'YOUR_API_KEY', baseURL: '<?= htmlspecialchars($baseUrl . '/v1', ENT_QUOTES, 'UTF-8') ?>' });">Sao chép</button></div><pre>import OpenAI from "openai";

const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY,
  baseURL: "<?= $apiBaseHtml ?>"
});
const response = await client.chat.completions.create({
  model: "YOUR_MODEL_ID",
  messages: [{ role: "user", content: "Hello!" }]
});
console.log(response.choices[0].message.content);</pre></div></div></div></section>
        <section id="troubleshooting" class="section"><div class="section-head"><span class="num">06</span><h2>Xử lý sự cố</h2></div><div class="grid"><div class="callout"><strong>401 Unauthorized</strong><br>Key thiếu, sai, bị khóa hoặc đã hết hạn. Kiểm tra header Authorization.</div><div class="callout warn"><strong>404 Not Found</strong><br>Claude Code/Gemini CLI dùng domain gốc; Codex/OpenCode/Cline/Cursor dùng <code>/v1</code>.</div><div class="callout"><strong>429 Too Many Requests</strong><br>Giảm số request đồng thời hoặc đợi hệ thống ổn định rồi thử lại.</div><div class="callout warn"><strong>403 Forbidden</strong><br>Key chưa được cấp quyền cho model hoặc gói đã hết hạn mức.</div></div></section>
        <section id="security" class="section"><div class="section-head"><span class="num">07</span><h2>Bảo mật API Key</h2></div><ul><li>Key được lưu ở backend/database của hệ thống; không hardcode vào file JavaScript công khai.</li><li>Ở trang tài liệu này, key chỉ xuất hiện dưới dạng <code>YOUR_API_KEY</code>.</li><li>Không gửi key qua URL, email công khai, GitHub hoặc ảnh chụp màn hình.</li><li>Nếu nghi ngờ lộ key, vào khu vực tài khoản để tạo key mới và vô hiệu hóa key cũ.</li></ul><div class="support"><a class="button" href="/user/api-docs">Khu vực quản lý API Key</a><a class="button secondary" href="/tra-cuu-key">Tra cứu Usage</a></div></section>
        <div class="footer-note">Base URL trên trang này lấy từ cấu hình website hiện tại. Tài liệu API đại lý để lấy sản phẩm về bán vẫn có tại <a href="/buyer-api">/buyer-api</a>.</div>
    </main>
</div>
<script>
(function () {
    const baseInput = document.getElementById('input-base');
    const keyInput = document.getElementById('input-key');
    const result = document.getElementById('test-result');

    function copyText(text, button) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
            const old = button.textContent;
            button.textContent = 'Đã sao chép';
            setTimeout(function () { button.textContent = old; }, 1400);
        }).catch(function () { alert('Không thể sao chép tự động. Vui lòng copy thủ công.'); });
    }

    function codeFor(id) {
        const panel = document.getElementById(id);
        if (!panel) return '';
        const pre = panel.querySelector('pre');
        return pre ? pre.innerText : '';
    }

    const codeTemplates = Array.from(document.querySelectorAll('.code pre')).map(function (pre) {
        pre.dataset.template = pre.textContent;
        return pre;
    });

    function renderCodeKey() {
        const key = keyInput.value || 'YOUR_API_KEY';
        codeTemplates.forEach(function (pre) {
            pre.textContent = pre.dataset.template.replaceAll('YOUR_API_KEY', key);
        });
    }

    function renderCurl() {
        const base = (baseInput.value || '').replace(/\/$/, '');
        const key = keyInput.value || 'YOUR_API_KEY';

        const model = 'YOUR_MODEL_ID';
        const models = `curl "${base}/v1/models" \\\n  -H "Authorization: Bearer ${key}"`;
        const chat = `curl -X POST "${base}/v1/chat/completions" \\\n  -H "Content-Type: application/json" \\\n  -H "Authorization: Bearer ${key}" \\\n  -d '{"model":"${model}","messages":[{"role":"user","content":"Hello!"}]}'`;
        const messages = `curl -X POST "${base}/v1/messages" \\\n  -H "Content-Type: application/json" \\\n  -H "x-api-key: ${key}" \\\n  -H "anthropic-version: 2023-06-01" \\\n  -d '{"model":"claude-sonnet-4-6","max_tokens":256,"messages":[{"role":"user","content":"Hello!"}]}'`;
        document.getElementById('code-models').textContent = models;
        document.getElementById('code-chat').textContent = chat;
        document.getElementById('code-messages').textContent = messages;
    }

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () { copyText(document.getElementById(button.dataset.copyTarget).value, button); });
    });
    document.querySelectorAll('[data-copy-code]').forEach(function (button) {
        button.addEventListener('click', function () { copyText(document.getElementById('code-' + button.dataset.copyCode).textContent, button); });
    });
    document.querySelectorAll('[data-copy-panel]').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.copyPanel);
            const pre = (panel && panel.querySelector('pre')) || button.closest('.tool')?.querySelector('pre');
            copyText(pre ? pre.innerText : '', button);
        });
    });
    document.querySelectorAll('.tab').forEach(function (button) {
        button.addEventListener('click', function () {
            const group = button.closest('.tool');
            group.querySelectorAll('.tab').forEach(function (tab) { tab.classList.remove('active'); });
            group.querySelectorAll('.panel').forEach(function (panel) { panel.classList.remove('active'); });
            button.classList.add('active');
            const panel = document.getElementById(button.dataset.tab);
            if (panel) panel.classList.add('active');
        });
    });
    document.getElementById('toggle-key').addEventListener('click', function () {
        const showing = keyInput.type === 'text';
        keyInput.type = showing ? 'password' : 'text';
        this.textContent = showing ? 'Hiện' : 'Ẩn';
    });
    [baseInput, keyInput].forEach(function (input) { input.addEventListener('input', function () { renderCurl(); renderCodeKey(); }); });
    document.getElementById('test-key').addEventListener('click', async function () {
        const base = (baseInput.value || '').replace(/\\/$/, '');
        const key = keyInput.value.trim();
        if (!key || key === 'YOUR_API_KEY') { result.textContent = 'Vui lòng nhập API key để kiểm tra.'; result.classList.add('visible'); return; }
        this.disabled = true; this.textContent = 'Đang kiểm tra...'; result.classList.add('visible'); result.textContent = 'Đang gọi danh sách model...';
        try {
            const response = await fetch(base + '/v1/models', { headers: { Authorization: 'Bearer ' + key }, cache: 'no-store' });
            const data = await response.json();
            if (!response.ok) throw new Error(data?.error?.message || 'HTTP ' + response.status);
            const models = Array.isArray(data.data) ? data.data : [];
            result.innerHTML = '<strong>Kết nối thành công.</strong> Tìm thấy ' + models.length + ' model.' + (models.length ? '<div class="chips">' + models.slice(0, 30).map(function (item) { return '<span class="chip">' + String(item.id).replace(/[&<>\"']/g, '') + '</span>'; }).join('') + '</div>' : '');
        } catch (error) { result.textContent = 'Không kết nối được: ' + error.message; }
        finally { this.disabled = false; this.textContent = '✓ Test kết nối & Lấy Model'; }
    });
    document.getElementById('toc-toggle').addEventListener('click', function () { document.getElementById('toc').classList.toggle('open'); });
    document.getElementById('theme-light').addEventListener('click', function () { document.documentElement.classList.remove('dark'); });
    document.getElementById('theme-dark').addEventListener('click', function () { document.documentElement.classList.add('dark'); });
    renderCurl();
    renderCodeKey();
}());
</script>
</body>
</html>
