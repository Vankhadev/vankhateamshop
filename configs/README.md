# 🚀 Cấu hình AI Gateway - TiemAI.io.vn

Thư mục này chứa các file cấu hình mẫu để kết nối các AI Agent khác nhau vào **Base URL: https://tiemai.io.vn/v1**

## 📋 Danh sách AI Agent được hỗ trợ

| AI Agent / IDE | File cấu hình | Base URL | Ghi chú |
|----------------|---------------|----------|---------|
| **Claude Code** | `claude-code.sh` | `https://tiemai.io.vn` | ❌ Không có `/v1` |
| **Codex CLI** | `codex-cli/` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Cline** | `cline.json` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Cursor IDE** | `cursor.json` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Kilo Code** | `kilo.json` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Continue.dev** | `continue-config.yaml` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **OpenCode** | `opencode.json` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Roo Code** | `roo-code.json` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Windsurf IDE** | `windsurf.json` | `https://tiemai.io.vn/v1` | ✅ Có `/v1` |
| **Gemini CLI** | `gemini-cli.sh` | `https://tiemai.io.vn` | ❌ Không có `/v1` |

## 🔑 Lấy API Key

1. Đăng ký tài khoản tại: https://tiemai.io.vn/register
2. Mua gói AI key tại: https://tiemai.io.vn/san-pham
3. Lấy API key từ đơn hàng hoặc dashboard

## ⚠️ Lưu ý quan trọng

### Base URL theo từng công cụ

**Không có `/v1` (chỉ dùng domain gốc):**
- Claude Code
- Gemini CLI
- Anthropic SDK (Python/Node.js)

**Có `/v1`:**
- Tất cả các công cụ khác (Codex, Cline, Cursor, Kilo, Continue, OpenCode, Roo Code, Windsurf, v.v.)

### Bảo mật API Key

- ❌ **KHÔNG** commit API key vào Git
- ❌ **KHÔNG** chia sẻ key công khai
- ✅ Dùng biến môi trường `.env`
- ✅ Dùng file cấu hình local (thêm vào `.gitignore`)

## 📖 Hướng dẫn chi tiết

Xem tài liệu đầy đủ tại: https://tiemai.io.vn/docs

## 💬 Hỗ trợ

- Website: https://tiemai.io.vn
- Tra cứu key: https://tiemai.io.vn/tra-cuu-key
