#!/bin/bash
# ============================================================
# Claude Code - Kết nối TiemAI.io.vn Gateway
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================
# Lưu ý: Claude Code tự nối /v1/messages -> KHÔNG dùng /v1

# ---- macOS / Linux ----
export ANTHROPIC_BASE_URL="https://tiemai.io.vn"
export ANTHROPIC_AUTH_TOKEN="YOUR_API_KEY"
export CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1

# Khởi chạy Claude Code
claude
