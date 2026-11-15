# ============================================================
# cURL Examples - TiemAI Gateway API
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================

# Thay YOUR_API_KEY bằng API key thật của bạn

# -------- 1. Lấy danh sách models --------
curl "https://tiemai.io.vn/v1/models" \
  -H "Authorization: Bearer YOUR_API_KEY"

# -------- 2. Chat Completions (OpenAI Compatible) --------
curl -X POST "https://tiemai.io.vn/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "claude-sonnet-5",
    "messages": [
      {"role": "user", "content": "Hello! What is 2+2?"}
    ]
  }'

# -------- 3. Chat Completions với Streaming --------
curl -X POST "https://tiemai.io.vn/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "gpt-5.6-sol",
    "messages": [
      {"role": "user", "content": "Explain AI in one sentence"}
    ],
    "stream": true
  }'

# -------- 4. Anthropic Messages API (Native Protocol) --------
curl -X POST "https://tiemai.io.vn/v1/messages" \
  -H "Content-Type: application/json" \
  -H "x-api-key: YOUR_API_KEY" \
  -H "anthropic-version: 2023-06-01" \
  -d '{
    "model": "claude-sonnet-5",
    "max_tokens": 1024,
    "messages": [
      {"role": "user", "content": "Hello Claude!"}
    ]
  }'

# -------- 5. Kiểm tra Usage/Quota --------
curl "https://tiemai.io.vn/v1/usage" \
  -H "Authorization: Bearer YOUR_API_KEY"

# hoặc
curl "https://tiemai.io.vn/v1/key/check" \
  -H "Authorization: Bearer YOUR_API_KEY"

# -------- 6. Chat với nhiều messages (conversation) --------
curl -X POST "https://tiemai.io.vn/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "claude-sonnet-5",
    "messages": [
      {"role": "system", "content": "You are a helpful coding assistant."},
      {"role": "user", "content": "How do I read a file in Python?"},
      {"role": "assistant", "content": "You can use the open() function..."},
      {"role": "user", "content": "Can you show me an example?"}
    ]
  }'

# -------- 7. Chat với temperature và max_tokens --------
curl -X POST "https://tiemai.io.vn/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "model": "gpt-6.1-sol",
    "messages": [
      {"role": "user", "content": "Write a haiku about coding"}
    ],
    "temperature": 0.7,
    "max_tokens": 100
  }'
