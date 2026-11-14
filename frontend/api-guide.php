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
    </style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><a class="brand" href="/">TiemAI <small>Extension API Docs</small></a><nav class="nav"><a href="/">Trang chủ</a><a href="/buyer-api">API đại lý</a><a href="/tra-cuu-key">Tra cứu Key</a><a href="/login">Đăng nhập</a></nav></div></header>
<div class="layout">
    <aside class="toc"><h2>☰ Mục lục</h2><a class="active" href="#quickstart">1. Base URL & API Key</a><a href="#curl">2. Test nhanh cURL</a><a href="#base-urls">3. Base URL theo Client</a><a href="#tools">4. Cài đặt theo công cụ</a><a href="#sdk">5. SDK & HTTP</a><a href="#troubleshooting">6. Xử lý sự cố</a><a href="#security">7. Bảo mật API Key</a></aside>
    <main>
        <section class="hero"><span class="pill"><span class="dot"></span>Tài liệu cổng API trực tuyến</span><h1>Hướng dẫn tích hợp & sử dụng API</h1><p>Kết nối Claude Code, Codex CLI, OpenCode, DeepSeek TUI, Gemini CLI, Cline, Roo Code, Cursor, Continue và SDK trong vài bước. Tài liệu này dùng endpoint của <strong><?= $tenWebsite ?></strong>; không công khai URL nhà cung cấp.</p><div class="chips"><a class="chip" href="#claude">⚡ Claude Code</a><a class="chip" href="#codex">⚡ Codex CLI</a><a class="chip" href="#opencode">⚡ OpenCode</a><a class="chip" href="#gemini">✨ Gemini CLI</a><a class="chip" href="#cline">🧩 Cline</a><a class="chip" href="#kilo">⚙️ Kilo Code</a><a class="chip" href="#cursor">💻 Cursor / Continue</a><a class="chip" href="#troubleshooting">🛠️ Fix 401 / 404</a></div></section>
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
document.querySelectorAll('[data-copy]').forEach(function(button){button.addEventListener('click',function(){var text=button.getAttribute('data-copy');navigator.clipboard.writeText(text).then(function(){var old=button.textContent;button.textContent='Đã sao chép';setTimeout(function(){button.textContent=old},1400)}).catch(function(){alert('Không thể sao chép tự động. Vui lòng copy thủ công.')})})});
</script>
</body>
</html>
