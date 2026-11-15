# ============================================================
# Python - OpenAI SDK với TiemAI Gateway
# Hướng dẫn: https://tiemai.io.vn/docs
# ============================================================

import os
from openai import OpenAI

# Cách 1: Dùng biến môi trường (khuyến nghị)
client = OpenAI(
    api_key=os.environ.get("OPENAI_API_KEY"),
    base_url="https://tiemai.io.vn/v1"
)

# Cách 2: Hard-code (KHÔNG nên dùng trong production)
# client = OpenAI(
#     api_key="YOUR_API_KEY",
#     base_url="https://tiemai.io.vn/v1"
# )

# Test kết nối
def test_connection():
    try:
        # Lấy danh sách model
        models = client.models.list()
        print("✓ Kết nối thành công!")
        print(f"Tìm thấy {len(models.data)} models")
        for model in models.data[:5]:
            print(f"  - {model.id}")
    except Exception as e:
        print(f"✗ Lỗi kết nối: {e}")

# Chat completion
def chat(message: str, model: str = "claude-sonnet-5"):
    try:
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "user", "content": message}
            ],
            stream=False
        )
        return response.choices[0].message.content
    except Exception as e:
        return f"Error: {e}"

# Streaming chat
def chat_stream(message: str, model: str = "claude-sonnet-5"):
    try:
        stream = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "user", "content": message}
            ],
            stream=True
        )
        
        print("Assistant: ", end="", flush=True)
        for chunk in stream:
            if chunk.choices[0].delta.content:
                print(chunk.choices[0].delta.content, end="", flush=True)
        print()
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    # Test kết nối
    test_connection()
    
    # Test chat
    print("\n" + "="*50)
    result = chat("Hello! What's 2+2?")
    print(f"Response: {result}")
    
    # Test streaming
    print("\n" + "="*50)
    chat_stream("Explain Python in one sentence")
