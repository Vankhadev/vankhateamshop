# ============================================================
# Python - Anthropic SDK với TiemAI Gateway
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================
# Lưu ý: Anthropic SDK tự nối /v1/messages -> KHÔNG dùng /v1

import os
from anthropic import Anthropic

# Cách 1: Dùng biến môi trường (khuyến nghị)
client = Anthropic(
    api_key=os.environ.get("ANTHROPIC_API_KEY"),
    base_url="https://tiemai.io.vn"
)

# Cách 2: Hard-code (KHÔNG nên dùng trong production)
# client = Anthropic(
#     api_key="YOUR_API_KEY",
#     base_url="https://tiemai.io.vn"
# )

# Chat với Claude
def chat_claude(message: str, model: str = "claude-sonnet-5"):
    try:
        response = client.messages.create(
            model=model,
            max_tokens=1024,
            messages=[
                {"role": "user", "content": message}
            ]
        )
        return response.content[0].text
    except Exception as e:
        return f"Error: {e}"

# Streaming chat
def chat_claude_stream(message: str, model: str = "claude-sonnet-5"):
    try:
        with client.messages.stream(
            model=model,
            max_tokens=1024,
            messages=[
                {"role": "user", "content": message}
            ]
        ) as stream:
            print("Claude: ", end="", flush=True)
            for text in stream.text_stream:
                print(text, end="", flush=True)
            print()
            
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    # Test chat
    print("Testing Anthropic SDK with TiemAI Gateway")
    print("="*50)
    result = chat_claude("Hello Claude! What's 2+2?")
    print(f"Response: {result}")
    
    # Test streaming
    print("\n" + "="*50)
    chat_claude_stream("Explain AI in one sentence")
