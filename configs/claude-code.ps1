# ============================================================
# Claude Code - PowerShell (Windows)
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================
# Lưu ý: Claude Code tự nối /v1/messages -> KHÔNG dùng /v1

$env:ANTHROPIC_BASE_URL = "https://tiemai.io.vn"
$env:ANTHROPIC_AUTH_TOKEN = "YOUR_API_KEY"
$env:CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC = "1"

# Khởi chạy Claude Code
claude
