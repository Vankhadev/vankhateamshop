# 🚀 TiemAI Gateway - AI API Gateway cho Việt Nam

**Base URL:** `https://tiemai.io.vn/v1`  
**Website:** https://tiemai.io.vn

Cổng API Gateway tích hợp đa nhà cung cấp AI, hỗ trợ kết nối tất cả các AI Agent phổ biến: Claude Code, Codex CLI, Cline, Cursor, Kilo, Continue.dev, OpenCode, Roo Code, Windsurf, Gemini CLI và nhiều hơn nữa.

---

## ✨ Tính năng

- ✅ **OpenAI-compatible API** - Tương thích với OpenAI SDK
- ✅ **Anthropic Native Protocol** - Hỗ trợ Claude Messages API
- ✅ **Tự động chuyển đổi Model** - Fallback thông minh khi model không tồn tại
- ✅ **Streaming SSE** - Hỗ trợ streaming real-time
- ✅ **Multi-provider** - Tích hợp nhiều nhà cung cấp AI
- ✅ **API Key Management** - Quản lý key và quota
- ✅ **Usage Tracking** - Theo dõi usage chi tiết
- ✅ **Security Headers** - Bảo mật tối ưu

---

## 📦 Cài đặt nhanh

### 1. Lấy API Key

```bash
# Đăng ký tài khoản
https://tiemai.io.vn/register

# Mua gói AI key
https://tiemai.io.vn/san-pham

# Lấy key từ dashboard
https://tiemai.io.vn/user/don-hang
```

### 2. Cấu hình AI Agent

Chọn công cụ bạn đang dùng:

- **[Claude Code](configs/claude-code.sh)** - Anthropic CLI
- **[Codex CLI](configs/codex-cli/)** - OpenAI Codex
- **[Cline](configs/cline.json)** - VS Code Extension
- **[Cursor](configs/cursor.json)** - Cursor IDE
- **[Kilo Code](configs/kilo.json)** - Kilo AI
- **[Continue.dev](configs/continue-config.yaml)** - VS Code Extension
- **[OpenCode](configs/opencode.json)** - OpenCode CLI
- **[Roo Code](configs/roo-code.json)** - Roo Code Extension
- **[Windsurf](configs/windsurf.json)** - Windsurf IDE
- **[Gemini CLI](configs/gemini-cli.sh)** - Google Gemini

Xem hướng dẫn chi tiết: **[AI_AGENT_INTEGRATION.md](AI_AGENT_INTEGRATION.md)**

### 3. Test kết nối

```bash
# Test với cURL
curl "https://tiemai.io.vn/v1/models" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Chat completion
curl -X POST "https://tiemai.io.vn/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "claude-sonnet-5",
    "messages": [{"role": "user", "content": "Hello!"}]
  }'
```

---

## 📚 Tài liệu

- 📖 **[Hướng dẫn tích hợp đầy đủ](AI_AGENT_INTEGRATION.md)** - Tích hợp tất cả AI Agent
- 🔧 **[Config Files](configs/)** - File cấu hình mẫu cho từng tool
- 🌐 **[API Documentation](https://tiemai.io.vn/docs)** - Tài liệu API online
- 🔍 **[Tra cứu Key](https://tiemai.io.vn/tra-cuu-key)** - Kiểm tra usage và quota

---

## 🛠️ API Endpoints

| Endpoint | Method | Mô tả |
|----------|--------|-------|
| `/v1/models` | GET | Lấy danh sách models |
| `/v1/chat/completions` | POST | OpenAI-compatible chat |
| `/v1/messages` | POST | Anthropic native protocol |
| `/v1/usage` | GET | Kiểm tra usage/quota |
| `/v1/key/check` | GET/POST | Kiểm tra API key |

---

## 🎯 Models hỗ trợ

### Claude (Anthropic)
- `claude-sonnet-5` - Claude Sonnet 5 (200K context)
- `claude-opus-5.5` - Claude Opus 5.5 (200K context)
- `claude-fable-5` - Claude Fable 5 (200K context)

### GPT (OpenAI)
- `gpt-5.6-sol` - GPT-5.6 Sol (128K context)
- `gpt-6.1-sol` - GPT-6.1 Sol (200K context)
- `gpt-5.6-luna` - GPT-5.6 Luna (128K context)
- `gpt-5.6-terra` - GPT-5.6 Terra (128K context)
- `gpt-5.4-mini` - GPT-5.4 Mini (128K context)

### DeepSeek
- `deepseek-reasoner` - DeepSeek Reasoner (64K context)
- `deepseek-chat` - DeepSeek Chat (64K context)

**Xem danh sách đầy đủ:**
```bash
curl "https://tiemai.io.vn/v1/models" -H "Authorization: Bearer YOUR_API_KEY"
```

---

## 💻 Ví dụ Code

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
```

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
```

Xem thêm ví dụ: [`configs/`](configs/)

---

## 🔒 Bảo mật

### ✅ Best Practices

- ✅ Dùng biến môi trường `.env` cho API key
- ✅ Thêm `.env` vào `.gitignore`
- ✅ Không commit API key vào Git
- ✅ Xoay (rotate) key định kỳ
- ✅ Kiểm tra usage thường xuyên

### ❌ Không nên

- ❌ Hard-code API key trong source code
- ❌ Chia sẻ key công khai
- ❌ Dùng key trong client-side JavaScript
- ❌ Commit config có key thật lên Git

---

## 🔧 Troubleshooting

| Lỗi | Nguyên nhân | Giải pháp |
|-----|-------------|-----------|
| 401 Unauthorized | API key sai/thiếu | Kiểm tra key tại `/tra-cuu-key` |
| 404 Not Found | Sai Base URL | Claude/Gemini dùng `tiemai.io.vn`, còn lại dùng `tiemai.io.vn/v1` |
| 403 Forbidden | Hết quota/quyền | Kiểm tra gói tại dashboard |
| 429 Too Many Requests | Quá nhiều request | Giảm tốc độ request |
| 500 Internal Error | Lỗi server | Thử lại sau |

---

## 📂 Cấu trúc Project

```
vankhateamshop/
├── backend/
│   ├── proxy_api.php          # API Gateway chính
│   ├── config.php              # Cấu hình
│   ├── db.php                  # Database connection
│   └── security.php            # Security headers
├── frontend/
│   ├── api-guide.php           # Tài liệu API
│   ├── docs.php                # API đại lý
│   └── tra-cuu-key.php         # Tra cứu key
├── configs/                    # File cấu hình mẫu
│   ├── claude-code.sh          # Claude Code config
│   ├── codex-cli/              # Codex CLI configs
│   ├── cline.json              # Cline config
│   ├── cursor.json             # Cursor config
│   ├── kilo.json               # Kilo Code config
│   ├── continue-config.yaml    # Continue.dev config
│   ├── opencode.json           # OpenCode config
│   ├── roo-code.json           # Roo Code config
│   ├── windsurf.json           # Windsurf config
│   ├── gemini-cli.sh           # Gemini CLI config
│   ├── sdk-python-openai.py    # Python SDK example
│   ├── sdk-python-anthropic.py # Python Anthropic SDK
│   ├── sdk-nodejs-openai.js    # Node.js SDK example
│   ├── curl-examples.sh        # cURL examples
│   ├── .env.example            # Environment variables
│   └── README.md               # Config documentation
├── AI_AGENT_INTEGRATION.md     # Hướng dẫn tích hợp đầy đủ
├── index.php                   # Router chính
└── README.md                   # File này
```

---

## 🌟 Features

### Auto Model Fallback
Gateway tự động chuyển đổi model khi client gọi model không tồn tại:

```php
// Client gọi: claude-3-5-sonnet
// Gateway tự chuyển thành: claude-sonnet-5

// Client gọi: gpt-4o
// Gateway tự chuyển thành: gpt-5.6-sol
```

### Multi-Provider Support
Hỗ trợ nhiều nhà cung cấp AI:
- Taphoaai (Claude models)
- Zplay (GPT models)
- Custom suppliers

### Usage Tracking
Theo dõi chi tiết:
- Request count
- Model usage
- Latency
- HTTP status
- IP address

---

## 📊 API Response Format

### OpenAI Compatible
```json
{
  "id": "chatcmpl-123",
  "object": "chat.completion",
  "created": 1234567890,
  "model": "claude-sonnet-5",
  "choices": [{
    "index": 0,
    "message": {
      "role": "assistant",
      "content": "Hello! How can I help you?"
    },
    "finish_reason": "stop"
  }],
  "usage": {
    "prompt_tokens": 10,
    "completion_tokens": 20,
    "total_tokens": 30
  }
}
```

### Anthropic Native
```json
{
  "id": "msg_123",
  "type": "message",
  "role": "assistant",
  "content": [{
    "type": "text",
    "text": "Hello! How can I help you?"
  }],
  "model": "claude-sonnet-5",
  "stop_reason": "end_turn",
  "usage": {
    "input_tokens": 10,
    "output_tokens": 20
  }
}
```

---

## 🚀 Deployment

### Requirements
- PHP 7.4+
- MySQL/MariaDB
- Apache/Nginx với mod_rewrite
- SSL certificate (HTTPS)

### Environment Variables
```bash
# Database
DB_HOST=localhost
DB_NAME=vankhateamshop
DB_USER=root
DB_PASS=

# Site
SITE_URL=https://tiemai.io.vn
SHOP_NAME=TiemAI.io.vn
```

---

## 💬 Support

- 🌐 **Website:** https://tiemai.io.vn
- 📖 **Tài liệu:** https://tiemai.io.vn/docs
- 🔍 **Tra cứu key:** https://tiemai.io.vn/tra-cuu-key
- 👤 **Dashboard:** https://tiemai.io.vn/user/dashboard
- 🛒 **Mua gói:** https://tiemai.io.vn/san-pham

---

## 📄 License

© 2024-2026 TiemAI.io.vn. All rights reserved.

---

**Cập nhật lần cuối:** 2026-11-15
