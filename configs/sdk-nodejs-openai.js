// ============================================================
// Node.js - OpenAI SDK với TiemAI Gateway
// Hướng dẫn: https://tiemai.io.vn/docs
// ============================================================

import OpenAI from 'openai';

// Cách 1: Dùng biến môi trường (khuyến nghị)
const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY,
  baseURL: 'https://tiemai.io.vn/v1'
});

// Cách 2: Hard-code (KHÔNG nên dùng trong production)
// const client = new OpenAI({
//   apiKey: 'YOUR_API_KEY',
//   baseURL: 'https://tiemai.io.vn/v1'
// });

// Test kết nối
async function testConnection() {
  try {
    const models = await client.models.list();
    console.log('✓ Kết nối thành công!');
    console.log(`Tìm thấy ${models.data.length} models`);
    models.data.slice(0, 5).forEach(model => {
      console.log(`  - ${model.id}`);
    });
  } catch (error) {
    console.error('✗ Lỗi kết nối:', error.message);
  }
}

// Chat completion
async function chat(message, model = 'claude-sonnet-5') {
  try {
    const response = await client.chat.completions.create({
      model: model,
      messages: [
        { role: 'user', content: message }
      ]
    });
    
    return response.choices[0].message.content;
  } catch (error) {
    return `Error: ${error.message}`;
  }
}

// Streaming chat
async function chatStream(message, model = 'claude-sonnet-5') {
  try {
    const stream = await client.chat.completions.create({
      model: model,
      messages: [
        { role: 'user', content: message }
      ],
      stream: true
    });
    
    process.stdout.write('Assistant: ');
    for await (const chunk of stream) {
      const content = chunk.choices[0]?.delta?.content || '';
      process.stdout.write(content);
    }
    console.log('\n');
    
  } catch (error) {
    console.error('Error:', error.message);
  }
}

// Main
(async () => {
  // Test kết nối
  await testConnection();
  
  // Test chat
  console.log('\n' + '='.repeat(50));
  const result = await chat("Hello! What's 2+2?");
  console.log(`Response: ${result}`);
  
  // Test streaming
  console.log('\n' + '='.repeat(50));
  await chatStream('Explain JavaScript in one sentence');
})();
