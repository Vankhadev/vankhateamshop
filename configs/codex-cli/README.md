# Codex CLI - TiemAI Gateway

## Hướng dẫn cài đặt

1. Cài đặt Codex CLI (nếu chưa có):
```bash
npm install -g @openai/codex-cli
```

2. Tạo thư mục cấu hình:
```bash
mkdir -p ~/.codex
```

3. Copy file cấu hình:
```bash
cp config.toml ~/.codex/config.toml
cp auth.json ~/.codex/auth.json
```

4. Sửa `auth.json` và thay `YOUR_API_KEY` bằng API key thật của bạn

5. Khởi chạy:
```bash
codex
```

## Model hỗ trợ

- `gpt-5.6-sol` - GPT-5.6 Sol (khuyến nghị)
- `gpt-6.1-sol` - GPT-6.1 Sol
- `gpt-5.6-luna` - GPT-5.6 Luna
- `gpt-5.6-terra` - GPT-5.6 Terra
- `claude-sonnet-5` - Claude Sonnet 5
- `claude-opus-5.5` - Claude Opus 5.5

Xem danh sách đầy đủ: https://tiemai.io.vn/v1/models
