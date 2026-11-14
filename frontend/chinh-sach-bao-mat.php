<?php
// ========================================================
// TRANG PHÁP LÝ: Chính Sách Bảo Mật Thông Tin
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'chinh-sach-bao-mat';
$tieuDeTrang = 'Chính Sách Bảo Mật Thông Tin';
$moTaTrang   = 'Cam kết bảo vệ dữ liệu cá nhân, thông tin đơn hàng và an toàn giao dịch tại Tiệm AI theo Nghị định 13/2023/NĐ-CP.';
$mauSac      = 'cyan';
$iconTrang   = 'fa-lock';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Bên kiểm soát & xử lý dữ liệu',
    'items' => 
    array (
      0 => 'Tiệm AI (tiemai.io.vn) là đơn vị kiểm soát và xử lý dữ liệu cá nhân đối với các thông tin bạn cung cấp khi đăng ký tài khoản, đặt mua mã API và liên hệ hỗ trợ.',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email liên hệ bảo mật: vankhaqc@gmail.com',
      3 => 'Telegram hỗ trợ: @Vankhateam ',
      4 => 'Zalo / Hotline: 0904045075',
    ),
  ),
  1 => 
  array (
    'title' => 'Dữ liệu Tiệm AI thu thập',
    'items' => 
    array (
      0 => '1. Thông tin bạn cung cấp: Họ tên hiển thị, địa chỉ email nhận mã đơn hàng, mật khẩu tài khoản (được mã hóa một chiều bằng chuẩn an toàn bcrypt), số điện thoại và nội dung tin nhắn hỗ trợ khi bạn gửi yêu cầu.',
      1 => '2. Thông tin kỹ thuật hệ thống tự động ghi nhận: Địa chỉ IP truy cập, nhật ký kết nối API (thời gian gọi, model AI gọi, trạng thái phản hồi HTTP, độ trễ ms) để phục vụ cân bằng tải và giám sát an ninh mạng; cookie phiên đăng nhập và ghi nhớ giỏ hàng.',
      2 => '3. Thông tin Tiệm AI TUYỆT ĐỐI KHÔNG LƯU TRỮ: Mật khẩu tài khoản ngân hàng, mã OTP, số thẻ tín dụng hoặc thông tin ví tiền cá nhân của bạn. Giao dịch thanh toán VietQR được bạn thực hiện trực tiếp trên ứng dụng ngân hàng của chính bạn.',
    ),
  ),
  2 => 
  array (
    'title' => 'Mục đích sử dụng dữ liệu',
    'items' => 
    array (
      0 => 'Tạo lập tài khoản, quản lý phiên đăng nhập và xuất kho giao mã API tự động cho khách hàng.',
      1 => 'Gửi email thông báo đơn hàng, cung cấp tài liệu hướng dẫn và thực hiện nghĩa vụ bảo hành, đổi mã khi có sự cố.',
      2 => 'Bảo vệ an ninh hệ thống website: Phát hiện và ngăn chặn các hành vi gian lận thanh toán, tạo đơn ảo hoặc tấn công mạng.',
      3 => 'Đối soát tài chính và thực hiện nghĩa vụ lưu trữ chứng từ theo quy định của pháp luật.',
    ),
  ),
  3 => 
  array (
    'title' => 'Cam kết không chia sẻ dữ liệu cho bên thứ ba',
    'items' => 
    array (
      0 => 'Tiệm AI cam kết TUYỆT ĐỐI KHÔNG BÁN, không trao đổi và không thương mại hóa dữ liệu cá nhân của khách hàng cho bất kỳ bên thứ ba nào vì mục đích tiếp thị hoặc quảng cáo.',
      1 => 'Dữ liệu chỉ được chia sẻ trong phạm vi kỹ thuật tối thiểu cần thiết: Cổng ngân hàng để đối soát giao dịch VietQR; nhà cung cấp hạ tầng máy chủ bảo mật; hoặc khi có yêu cầu bằng văn bản từ cơ quan nhà nước có thẩm quyền theo luật định.',
    ),
  ),
  4 => 
  array (
    'title' => 'Thời gian lưu trữ dữ liệu an toàn',
    'items' => 
    array (
      0 => 'Thông tin tài khoản và đơn hàng: Được lưu trữ trong suốt thời gian bạn sử dụng dịch vụ hoặc cho đến khi bạn gửi yêu cầu xóa tài khoản.',
      1 => 'Nhật ký giao dịch tài chính: Lưu trữ 24 tháng để phục vụ đối soát và giải quyết khiếu nại bảo hành.',
      2 => 'Phiên đăng nhập (Session cookie): Được lưu trữ trong 24 giờ hoặc tự động xóa khi bạn bấm đăng xuất.',
    ),
  ),
  5 => 
  array (
    'title' => 'Quyền của bạn đối với dữ liệu cá nhân',
    'items' => 
    array (
      0 => 'Theo Nghị định 13/2023/NĐ-CP về Bảo vệ dữ liệu cá nhân, bạn có toàn quyền: Được biết, xem, chỉnh sửa, yêu cầu xóa bỏ dữ liệu cá nhân hoặc rút lại sự đồng ý đã cấp trước đó.',
      1 => 'Khi muốn thực hiện các quyền này, bạn chỉ cần gửi email tới vankhaqc@gmail.com hoặc nhắn tin qua Telegram @Vankhateam. Tiệm AI sẽ tiếp nhận và xử lý trong vòng 72 giờ làm việc.',
    ),
  ),
  6 => 
  array (
    'title' => 'Thông tin liên hệ phụ trách bảo vệ dữ liệu',
    'items' => 
    array (
      0 => 'Mọi thắc mắc, phản ánh hoặc yêu cầu liên quan đến Chính sách bảo mật dữ liệu cá nhân, vui lòng liên hệ:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email phụ trách bảo mật: vankhaqc@gmail.com',
      3 => 'Telegram: @Vankhateam ',
      4 => 'Zalo: 0904045075 (https://zalo.me/0904045075)',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';