<?php
// ========================================================
// TRANG PHÁP LÝ: Điều Khoản Dành Cho Đại Lý
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'dieu-khoan-dai-ly';
$tieuDeTrang = 'Điều Khoản Dành Cho Đại Lý';
$moTaTrang   = 'Chính sách giá sỉ, điều kiện hợp tác và quyền lợi dành cho đối tác phân phối tại Tiệm AI.';
$mauSac      = 'purple';
$iconTrang   = 'fa-handshake';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Định nghĩa & điều kiện hợp tác',
    'items' => 
    array (
      0 => 'Đại lý (Reseller): Là cá nhân hoặc tổ chức được Tiệm AI kích hoạt quyền truy cập bảng giá sỉ và nạp số dư ví để phân phối lại sản phẩm cho khách hàng cuối.',
      1 => 'Điều kiện trở thành đại lý: Đủ 18 tuổi, có thông tin liên lạc xác thực (Email, Telegram/Zalo); có tệp khách hàng hoặc kênh phân phối riêng (Website, hội nhóm, cộng đồng lập trình viên); tuân thủ chính sách giá và quy định của Tiệm AI.',
    ),
  ),
  1 => 
  array (
    'title' => 'Chính sách giá sỉ & chiết khấu',
    'items' => 
    array (
      0 => 'Đại lý được áp dụng bảng giá sỉ chiết khấu trực tiếp trên từng sản phẩm khi mua qua tài khoản đại lý hoặc qua API kết nối.',
      1 => 'Đại lý tự quyết định giá bán lẻ ra thị trường cho khách hàng của mình nhưng không được bán phá giá dưới giá sàn quy định của Tiệm AI nhằm duy trì môi trường kinh doanh lành mạnh.',
      2 => 'Trong các dịp khuyến mại đồng loạt, Tiệm AI sẽ thông báo trước để đại lý chủ động điều chỉnh kế hoạch kinh doanh.',
    ),
  ),
  2 => 
  array (
    'title' => 'Trách nhiệm đối với khách hàng cuối',
    'items' => 
    array (
      0 => 'Đại lý tự chịu trách nhiệm tìm kiếm, tư vấn và hỗ trợ khách hàng của mình trước và sau khi bán.',
      1 => 'Khi sản phẩm gặp sự cố kỹ thuật từ phía nguồn cung, Tiệm AI cam kết đồng hành cùng đại lý để kiểm tra, đổi mã hoặc hoàn tiền cho đại lý để đại lý xử lý cho khách hàng.',
      2 => 'Tuyệt đối không sử dụng thông tin khách hàng vào các mục đích trái pháp luật.',
    ),
  ),
  3 => 
  array (
    'title' => 'Bảo mật mã truy cập & tài liệu nội bộ',
    'items' => 
    array (
      0 => 'Mã truy cập là tài sản quan trọng, đại lý có trách nhiệm lưu trữ an toàn, chỉ bàn giao cho khách hàng qua kênh riêng tư, không để lộ mã ra bên ngoài.',
      1 => 'Nghiêm cấm hành vi làm giả mã, chỉnh sửa mã hoặc bán lại các mã không rõ nguồn gốc mạo danh Tiệm AI.',
      2 => 'Đại lý có trách nhiệm giữ bí mật bảng giá nhập nội bộ và các tài liệu kỹ thuật được cung cấp.',
    ),
  ),
  4 => 
  array (
    'title' => 'Thanh toán & nạp số dư ví đại lý',
    'items' => 
    array (
      0 => 'Đại lý nạp tiền trước vào Số Dư Ví trên website tiemai.io.vn để hệ thống tự động trừ tiền khi mua hàng.',
      1 => 'Giá nhập được tính chuẩn xác theo bảng giá tại thời điểm đại lý tạo đơn. Mọi dữ liệu giao dịch được lưu trữ minh bạch trong mục Lịch sử ví và Đơn hàng.',
      2 => 'Đại lý tự chịu trách nhiệm kê khai và thực hiện các nghĩa vụ thuế (nếu có) phát sinh từ hoạt động kinh doanh bán lẻ của mình.',
    ),
  ),
  5 => 
  array (
    'title' => 'Tạm ngừng hợp tác đại lý',
    'items' => 
    array (
      0 => 'Tiệm AI có quyền chấm dứt tư cách đại lý trong các trường hợp: Bán hàng giả, lừa đảo khách hàng, vi phạm pháp luật, phá giá thị trường nghiêm trọng hoặc lợi dụng lỗi hệ thống để trục lợi.',
      1 => 'Trường hợp đại lý muốn ngừng hợp tác, vui lòng thông báo trước cho Tiệm AI để hai bên cùng đối soát và chốt hoàn trả số dư ví còn lại.',
    ),
  ),
  6 => 
  array (
    'title' => 'Kênh liên hệ hợp tác đại lý',
    'items' => 
    array (
      0 => 'Quý đối tác có nhu cầu hợp tác đại lý hoặc kết nối API nguồn cung sỉ, vui lòng liên hệ:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email đối tác: vankhaqc@gmail.com',
      3 => 'Telegram quản lý đại lý: @Vankhateam ',
      4 => 'Zalo quản lý: 0847154088 (https://zalo.me/0847154088)',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';