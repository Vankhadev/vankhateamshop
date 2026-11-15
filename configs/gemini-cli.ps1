# ============================================================
# Gemini CLI - PowerShell (Windows)
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================
# Lưu ý: Gemini CLI tự nối /v1beta -> KHÔNG dùng /v1

$env:GOOGLE_GEMINI_BASE_URL = "https://tiemai.io.vn"
$env:GEMINI_API_KEY = "YOUR_API_KEY"
$env:GEMINI_MODEL = "gemini-2.0-flash-exp"

# Khởi chạy Gemini CLI
gemini
