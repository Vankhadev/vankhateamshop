<?php
// ========================================================
// TRANG PHÁP LÝ: Chính Sách Thanh Toán
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'chinh-sach-thanh-toan';
$tieuDeTrang = 'Chính Sách Thanh Toán';
$moTaTrang   = 'Phương thức thanh toán VietQR tự động, nạp ví đại lý và quy định đối soát đơn hàng tại Tiệm AI.';
$mauSac      = 'emerald';
$iconTrang   = 'fa-credit-card';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Nguyên tắc định giá và niêm yết',
    'items' => 
    array (
      0 => 'Tất cả giá bán sản phẩm trên website Tiệm AI được niêm yết bằng Việt Nam Đồng (VNĐ) và đã bao gồm toàn bộ chi phí theo gói.',
      1 => 'Giá chốt của đơn hàng là giá tại thời điểm khách hàng tạo đơn và thanh toán thành công. Tiệm AI có quyền điều chỉnh giá bán hoặc áp dụng các chương trình ưu đãi mới theo biến động thị trường nguồn cung mà không áp dụng hồi tố cho các đơn hàng đã thanh toán trước đó.',
    ),
  ),
  1 => 
  array (
    'title' => 'Phương thức thanh toán chấp nhận',
    'items' => 
    array (
      0 => '1. Quét mã VietQR tự động (Dành cho tất cả khách hàng): Khách hàng mở ứng dụng ngân hàng hoặc ví điện tử bất kỳ, quét mã QR hiển thị tại trang thanh toán. Hệ thống tự động điền sẵn số tiền và nội dung đơn hàng, giao dịch được xác nhận ngay lập tức 24/7.',
      1 => '2. Trừ số dư Ví tài khoản (Dành cho đại lý & thành viên thân thiết): Khách hàng nạp sẵn số dư vào ví để mua hàng siêu tốc 1 click mà không cần quét mã chuyển khoản nhiều lần, đồng thời hưởng chiết khấu đại lý tốt nhất.',
    ),
  ),
  2 => 
  array (
    'title' => 'Thời gian giữ đơn & xác nhận thanh toán',
    'items' => 
    array (
      0 => 'Đơn hàng được xác nhận là "Đã thanh toán" ngay khi tiền về đúng số tài khoản ngân hàng của Tiệm AI kèm đúng nội dung chuyển khoản hiển thị trên màn hình.',
      1 => 'Mỗi mã QR thanh toán có hiệu lực trong vòng 05 phút. Sau 05 phút, đơn hàng chưa nhận tiền sẽ tự động hủy trên giao diện.',
      2 => 'Trường hợp khách hàng chuyển nhầm nội dung, chuyển thiếu hoặc chuyển sau khi đơn đã hủy: Bạn vui lòng chụp biên lai giao dịch và nhắn ngay cho Admin qua Telegram @Vankhateam hoặc Zalo 0847154088. Hệ thống sẽ đối soát thủ công và kích hoạt đơn hoặc nạp thẳng tiền vào ví cho bạn trong vòng 5–15 phút.',
    ),
  ),
  3 => 
  array (
    'title' => 'Quy định về số dư ví đại lý',
    'items' => 
    array (
      0 => 'Số dư ví tại Tiệm AI được nạp trước để thanh toán các sản phẩm và dịch vụ trên hệ thống. Hết số dư, khách hàng chỉ cần nạp thêm để tiếp tục lên đơn.',
      1 => 'Số dư ví không phải là tài khoản tiền gửi ngân hàng. Trường hợp đại lý muốn rút lại số dư ví chưa sử dụng, vui lòng liên hệ Admin để được đối soát và xử lý hoàn tiền theo quy định.',
    ),
  ),
  4 => 
  array (
    'title' => 'Thông tin hỗ trợ thanh toán',
    'items' => 
    array (
      0 => 'Mọi vấn đề liên quan đến chuyển khoản, đối soát tiền và kích hoạt đơn hàng:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email kế toán: vankhaqc@gmail.com',
      3 => 'Telegram hỗ trợ: @Vankhateam ',
      4 => 'Zalo trực ban: 0847154088',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';