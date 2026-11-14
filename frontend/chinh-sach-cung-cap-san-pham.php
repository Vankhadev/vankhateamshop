<?php
// ========================================================
// TRANG PHÁP LÝ: Chính Sách Cung Cấp Sản Phẩm
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'chinh-sach-cung-cap-san-pham';
$tieuDeTrang = 'Chính Sách Cung Cấp Sản Phẩm';
$moTaTrang   = 'Quy định về phạm vi sản phẩm số, cách thức nhận hàng và thời gian kích hoạt tại Tiệm AI.';
$mauSac      = 'amber';
$iconTrang   = 'fa-box-open';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Phạm vi & bản chất sản phẩm cung cấp',
    'items' => 
    array (
      0 => 'Tiệm AI chỉ cung cấp 100% sản phẩm kỹ thuật số (Digital Goods): Mã API Key (Cursor, Codex, Claude, DeepSeek, Gemini, Grok...), gói tài khoản AI bản quyền và bản quyền phần mềm hỗ trợ lập trình viên.',
      1 => 'Hoàn toàn không có hình thức giao nhận hàng vật lý, không giao qua bưu điện hay lắp đặt tận nơi.',
      2 => 'Các gói dịch vụ phụ thuộc vào hệ thống máy chủ và chính sách của nhà cung cấp gốc (OpenAI, Anthropic, Cursor, DeepSeek...). Khi bên gốc có điều chỉnh về quota hoặc kiến trúc mô hình, Tiệm AI sẽ thông báo công khai và hướng dẫn khách hàng cập nhật tương ứng.',
    ),
  ),
  1 => 
  array (
    'title' => 'Thời gian giao nhận & kích hoạt',
    'items' => 
    array (
      0 => 'Mã truy cập được xuất kho và giao tự động ngay sau khi hệ thống nhận đủ tiền thanh toán đúng đơn hàng đó, thông thường chỉ mất từ 1 đến 2 phút.',
      1 => 'Thời hạn sử dụng của gói (1 ngày, 3 ngày, 7 ngày, 30 ngày...) được tính bắt đầu từ thời điểm hệ thống xuất mã thành công trên trang đơn hàng của bạn, không tính từ thời điểm bạn bắt đầu mở máy tính ra cấu hình.',
      2 => 'Khách hàng có thể xem lại mã của mình bất kỳ lúc nào tại mục Đơn hàng của tôi hoặc sử dụng công cụ Tra Cứu Key trên website.',
    ),
  ),
  2 => 
  array (
    'title' => 'Bảo mật và trách nhiệm lưu trữ mã',
    'items' => 
    array (
      0 => 'Từ thời điểm nhận mã, khách hàng có trách nhiệm tự bảo mật mã API và tài khoản của mình. Mọi lượt gọi API, token tiêu thụ phát sinh từ mã đó được tính cho đơn hàng của bạn.',
      1 => 'Tuyệt đối không chia sẻ, bán lại hoặc dùng chung mã trái với quy định gói sản phẩm. Không dán mã API vào các mã nguồn công khai trên internet.',
      2 => 'Khách hàng có trách nhiệm nhập đúng email khi tạo đơn. Tiệm AI không chịu trách nhiệm trong trường hợp khách hàng nhập sai email của người khác.',
    ),
  ),
  3 => 
  array (
    'title' => 'Trường hợp kho tạm hết hàng & giải pháp bảo vệ khách hàng',
    'items' => 
    array (
      0 => 'Nếu kho đối tác tạm thời hết hàng vào thời điểm bạn thanh toán, hệ thống lập tức kích hoạt cơ chế bảo vệ: Đơn hàng được giữ trạng thái "Đang xử lý" để cấp bù sớm nhất.',
      1 => 'Nếu đơn hàng chưa có hàng bù trong thời gian quy định, hệ thống tự động hoàn tiền 100% vào số dư ví tài khoản hoặc cấp mã thay thế tương đương để khách hàng không bị gián đoạn công việc.',
    ),
  ),
  4 => 
  array (
    'title' => 'Kênh liên hệ hỗ trợ đơn hàng',
    'items' => 
    array (
      0 => 'Nếu cần hỗ trợ kỹ thuật cài đặt hoặc nhận mã:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email: vankhaqc@gmail.com',
      3 => 'Telegram: @Vankhateam ',
      4 => 'Zalo: 0904045075 (https://zalo.me/0904045075)',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';