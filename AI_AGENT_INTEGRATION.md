# 🤖 Hướng dẫn Tích hợp AI Agent - TiemAI Gateway

**Base URL:** `https://tiemai.io.vn/v1`  
**Website:** https://tiemai.io.vn  
**Tài liệu đầy đủ:** https://tiemai.io.vn/docs

---

## 📋 Mục lục

- [Lấy API Key](#lấy-api-key)
- [Danh sách AI Agent được hỗ trợ](#danh-sách-ai-agent-được-hỗ-trợ)
- [Quy tắc Base URL](#quy-tắc-base-url)
- [Hướng dẫn cài đặt theo từng công cụ](#hướng-dẫn-cài-đặt-theo-từng-công-cụ)
  - [Claude Code](#1-claude-code)
  - [Codex CLI](#2-codex-cli)
  - [Cline](#3-cline)
  - [Cursor IDE](#4-cursor-ide)
  - [Kilo Code](#5-kilo-code)
  - [Continue.dev](#6-continuedev)
  - [OpenCode](#7-opencode)
  - [Roo Code](#8-roo-code)
  - [Windsurf IDE](#9-windsurf-ide)
  - [Gemini CLI](#10-gemini-cli)
- [SDK & HTTP](#sdk--http)
- [Troubleshooting](#troubleshooting)
- [Bảo mật](#bảo-mật)

---

## 🔑 Lấy API Key

1. **Đăng ký tài khoản:** https://tiemai.io.vn/register
2. **Mua gói AI key:** https://tiemai.io.vn/san-pham
3. **Lấy key:** Từ đơn hàng hoặc dashboard: https://tiemai.io.vn/user/don-hang

---

## 📊 Danh sách AI Agent được hỗ trợ

| AI Agent / IDE | Base URL | Có `/v1`? | File config |
|----------------|----------|-----------|-------------|
| **Claude Code** | `https://tiemai.io.vn` | ❌ | `claude-code.sh`, `claude-code.ps1` |
| **Codex CLI** | `https://tiemai.io.vn/v1` | ✅ | `codex-cli/config.toml` |
| **Cline** | `https://tiemai.io.vn/v1` | ✅ | `cline.json` |
| **Cursor** | `https://tiemai.io.vn/v1` | ✅ | `cursor.json` |
| **Kilo Code** | `https://tiemai.io.vn/v1` | ✅ | `kilo.json` |
| **Continue.dev** | `https://tiemai.io.vn/v1` | ✅ | `continue-config.yaml` |
| **OpenCode** | `https://tiemai.io.vn/v1` | ✅ | `opencode.json` |
| **Roo Code** | `https://tiemai.io.vn/v1` | ✅ | `roo-code.json` |
| **Windsurf** | `https://tiemai.io.vn/v1` | ✅ | `windsurf.json` |
| **Gemini CLI** | `https://tiemai.io.vn` | ❌ | `gemini-cli.sh`, `gemini-cli.ps1` |

---

## ⚠️ Quy tắc Base URL

### 🔴 KHÔNG có `/v1` (chỉ dùng domain gốc):
- **Claude Code** - Tự nối `/v1/messages`
- **Gemini CLI** - Tự nối `/v1beta`
- **Anthropic SDK** (Python/Node.js) - Tự nối `/v1/messages`

### 🟢 CÓ `/v1`:
- **Tất cả các công cụ khác**: Codex, Cline, Cursor, Kilo, Continue, OpenCode, Roo Code, Windsurf, v.v.
- **OpenAI SDK** (Python/Node.js)
- **HTTP API / cURL**

---

## 📦 Hướng dẫn cài đặt theo từng công cụ

### 1. Claude Code

**Base URL:** `https://tiemai.io.vn` (❌ KHÔNG có `/v1`)

#### macOS / Linux
```bash
export ANTHROPIC_BASE_URL="https://tiemai.io.vn"
export ANTHROPIC_AUTH_TOKEN="YOUR_API_KEY"
export CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1
claude
```

#### Windows PowerShell
```powershell
$env:ANTHROPIC_BASE_URL = "https://tiemai.io.vn"
$env:ANTHROPIC_AUTH_TOKEN = "YOUR_API_KEY"
$env:CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC = "1"
claude
```

#### File config: `~/.claude/settings.json`
```json
{
  "env": {
    "ANTHROPIC_BASE_URL": "https://tiemai.io.vn",
    "ANTHROPIC_AUTH_TOKEN": "YOUR_API_KEY"
  }
}
```

📄 **File mẫu:** [`claude-code.sh`](claude-code.sh), [`claude-code.ps1`](claude-code.ps1), [`claude-settings.json`](claude-settings.json)

---

### 2. Codex CLI

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### Cài đặt
```bash
npm install -g @openai/codex-cli
mkdir -p ~/.codex
```

#### File config: `~/.codex/config.toml`
```toml
model_provider = "TiemAI"
model = "gpt-5.6-sol"
disable_response_storage = true

[model_providers.TiemAI]
name = "TiemAI Gateway"
base_url = "https://tiemai.io.vn/v1"
wire_api = "responses"
requires_openai_auth = true
```

#### File auth: `~/.codex/auth.json`
```json
{
  "OPENAI_API_KEY": "YOUR_API_KEY"
}
```

📄 **File mẫu:** [`codex-cli/config.toml`](codex-cli/config.toml), [`codex-cli/auth.json`](codex-cli/auth.json)

---

### 3. Cline

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### Trong Cline Settings:
- **API Provider:** OpenAI Compatible
- **Base URL:** `https://tiemai.io.vn/v1`
- **API Key:** `YOUR_API_KEY`
- **Model ID:** `claude-sonnet-5` (hoặc model khác)

📄 **File mẫu:** [`cline.json`](cline.json)

---

### 4. Cursor IDE

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### Trong Cursor Settings → Models:
- **Provider:** OpenAI Compatible
- **Base URL:** `https://tiemai.io.vn/v1`
- **API Key:** `YOUR_API_KEY`
- **Model:** `claude-sonnet-5`

📄 **File mẫu:** [`cursor.json`](cursor.json)

---

### 5. Kilo Code

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### File config: `kilo.json`
```json
{
  "$schema": "https://kilo.ai/schemas/kilo.json",
  "version": "1.0.0",
  "providers": {
    "tiemai": {
      "type": "openai-compatible",
      "name": "TiemAI Gateway",
      "baseURL": "https://tiemai.io.vn/v1",
      "apiKey": "${TIEMAI_API_KEY}",
      "models": [...]
    }
  }
}
```

📄 **File mẫu:** [`kilo.json`](kilo.json)

---

### 6. Continue.dev

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### File config: `~/.continue/config.yaml`
```yaml
name: TiemAI Configuration
version: 0.0.1
schema: v1

models:
  - name: Claude Sonnet 5
    provider: openai
    model: claude-sonnet-5
    apiBase: https://tiemai.io.vn/v1
    apiKey: YOUR_API_KEY
    contextLength: 200000
```

📄 **File mẫu:** [`continue-config.yaml`](continue-config.yaml)

---

### 7. OpenCode

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### File config: `opencode.json`
```json
{
  "provider": {
    "tiemai": {
      "type": "openai",
      "options": {
        "baseURL": "https://tiemai.io.vn/v1",
        "apiKey": "YOUR_API_KEY"
      },
      "models": {...}
    }
  }
}
```

📄 **File mẫu:** [`opencode.json`](opencode.json)

---

### 8. Roo Code

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### Trong Roo Code Settings:
- **Provider:** OpenAI Compatible
- **Base URL:** `https://tiemai.io.vn/v1`
- **API Key:** `YOUR_API_KEY`
- **Model:** `claude-sonnet-5`

📄 **File mẫu:** [`roo-code.json`](roo-code.json)

---

### 9. Windsurf IDE

**Base URL:** `https://tiemai.io.vn/v1` (✅ Có `/v1`)

#### File config:
```json
{
  "providers": [
    {
      "name": "TiemAI Gateway",
      "type": "openai-compatible",
      "baseURL": "https://tiemai.io.vn/v1",
      "apiKey": "YOUR_API_KEY"
    }
  ]
}
```

📄 **File mẫu:** [`windsurf.json`](windsurf.json)

---

### 10. Gemini CLI

**Base URL:** `https://tiemai.io.vn` (❌ KHÔNG có `/v1`)

#### macOS / Linux
```bash
export GOOGLE_GEMINI_BASE_URL="https://tiemai.io.vn"
export GEMINI_API_KEY="YOUR_API_KEY"
export GEMINI_MODEL="gemini-2.0-flash-exp"
gemini
```

#### Windows PowerShell
```powershell
$env:GOOGLE_GEMINI_BASE_URL = "https://tiemai.io.vn"
$env:GEMINI_API_KEY = "YOUR_API_KEY"
gemini
```

📄 **File mẫu:** [`gemini-cli.sh`](gemini-cli.sh), [`gemini-cli.ps1`](gemini-cli.ps1)

---

## 💻 SDK & HTTP

### Python - OpenAI SDK
```python
from openai import OpenAI

client = OpenAI(
    api_key="YOUR_API_KEY",
    base_url="https://tiemai.io.vn/v1"
)

response = client.chat.completions.create(
    model="claude-sonnet-5",
    messages=[{"role": "user", "content": "Hello!"}]
)
print(response.choices[0].message.content)
```

📄 **File mẫu:** [`sdk-python-openai.py`](sdk-python-openai.py)

### Python - Anthropic SDK
```python
from anthropic import Anthropic

client = Anthropic(
    api_key="YOUR_API_KEY",
    base_url="https://tiemai.io.vn"  # KHÔNG có /v1
)

message = client.messages.create(
    model="claude-sonnet-5",
    max_tokens=1024,
    messages=[{"role": "user", "content": "Hello!"}]
)
print(message.content[0].text)
```

📄 **File mẫu:** [`sdk-python-anthropic.py`](sdk-python-anthropic.py)

### Node.js - OpenAI SDK
```javascript
import OpenAI from 'openai';

const client = new OpenAI({
  apiKey: 'YOUR_API_KEY',
  baseURL: 'https://tiemai.io.vn/v1'
});

const response = await client.chat.completions.create({
  model: 'claude-sonnet-5',
  messages: [{ role: 'user', content: 'Hello!' }]
});

console.log(response.choices[0].message.content);
```

📄 **File mẫu:** [`sdk-nodejs-openai.js`](sdk-nodejs-openai.js)

### cURL - HTTP API
```bash
# Lấy danh sách models
curl "https://tiemai.io.vn/v1/models" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Chat completions
curl -X POST "https://tiemai.io.vn/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "claude-sonnet-5",
    "messages": [
      {"role": "user", "content": "Hello!"}
    ]
  }'

# Kiểm tra usage
curl "https://tiemai.io.vn/v1/usage" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

📄 **File mẫu:** [`curl-examples.sh`](curl-examples.sh)

---

## 🔧 Troubleshooting

### ❌ 401 Unauthorized
- **Nguyên nhân:** API key sai, thiếu, hoặc đã hết hạn
- **Giải pháp:** 
  - Kiểm tra API key tại: https://tiemai.io.vn/tra-cuu-key
  - Đảm bảo header `Authorization: Bearer YOUR_API_KEY` đúng

### ❌ 404 Not Found
- **Nguyên nhân:** Sai Base URL (thừa hoặc thiếu `/v1`)
- **Giải pháp:**
  - Claude Code/Gemini CLI: Dùng `https://tiemai.io.vn` (KHÔNG có `/v1`)
  - Các tool khác: Dùng `https://tiemai.io.vn/v1` (CÓ `/v1`)

### ❌ 403 Forbidden
- **Nguyên nhân:** Key chưa được cấp quyền cho model hoặc đã hết quota
- **Giải pháp:** Kiểm tra gói và quota tại dashboard

### ❌ 429 Too Many Requests
- **Nguyên nhân:** Vượt quá giới hạn request
- **Giải pháp:** Giảm số request đồng thời hoặc đợi cooldown

### ❌ 500 Internal Server Error
- **Nguyên nhân:** Lỗi máy chủ
- **Giải pháp:** Thử lại sau hoặc liên hệ support

---

## 🔒 Bảo mật

### ✅ NÊN làm:
- ✅ Dùng biến môi trường `.env` cho API key
- ✅ Thêm `.env` vào `.gitignore`
- ✅ Dùng file config local (không commit lên Git)
- ✅ Xoay (rotate) key định kỳ

### ❌ KHÔNG nên:
- ❌ Hard-code API key vào source code
- ❌ Commit API key lên GitHub/GitLab
- ❌ Chia sẻ key công khai
- ❌ Dùng API key trong client-side JavaScript

### File `.env` mẫu
```bash
# .env file
TIEMAI_API_KEY=YOUR_API_KEY
OPENAI_API_KEY=YOUR_API_KEY
ANTHROPIC_API_KEY=YOUR_API_KEY
```

📄 **File mẫu:** [`.env.example`](.env.example), [`.gitignore`](.gitignore)

---

## 📚 Models hỗ trợ

| Model ID | Tên | Context Window | Provider |
|----------|-----|----------------|----------|
| `claude-sonnet-5` | Claude Sonnet 5 | 200K | Anthropic |
| `claude-opus-5.5` | Claude Opus 5.5 | 200K | Anthropic |
| `gpt-5.6-sol` | GPT-5.6 Sol | 128K | OpenAI |
| `gpt-6.1-sol` | GPT-6.1 Sol | 200K | OpenAI |
| `gpt-5.6-luna` | GPT-5.6 Luna | 128K | OpenAI |
| `gpt-5.6-terra` | GPT-5.6 Terra | 128K | OpenAI |
| `deepseek-reasoner` | DeepSeek Reasoner | 64K | DeepSeek |

**Xem danh sách đầy đủ:**
```bash
curl "https://tiemai.io.vn/v1/models" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

## 💬 Hỗ trợ

- 🌐 **Website:** https://tiemai.io.vn
- 📖 **Tài liệu:** https://tiemai.io.vn/docs
- 🔍 **Tra cứu key:** https://tiemai.io.vn/tra-cuu-key
- 👤 **Dashboard:** https://tiemai.io.vn/user/dashboard

---

## 📄 License

Tài liệu này thuộc về **TiemAI.io.vn**. Vui lòng không sao chép hoặc phân phối mà không có sự cho phép.

---

**Cập nhật lần cuối:** 2026-11-15
