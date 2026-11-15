#!/bin/bash
# ============================================================
# Gemini CLI - Kết nối TiemAI.io.vn Gateway
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================
# Lưu ý: Gemini CLI tự nối /v1beta -> KHÔNG dùng /v1

# ---- macOS / Linux ----
export GOOGLE_GEMINI_BASE_URL="https://tiemai.io.vn"
export GEMINI_API_KEY="YOUR_API_KEY"
export GEMINI_MODEL="gemini-2.0-flash-exp"

# Khởi chạy Gemini CLI
gemini
