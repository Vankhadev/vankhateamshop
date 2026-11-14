<?php
// ========================================================
// TRANG PHÁP LÝ: Quy Chế Hoạt Động
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'quy-che-hoat-dong';
$tieuDeTrang = 'Quy Chế Hoạt Động';
$moTaTrang   = 'Nguyên tắc vận hành, quyền và nghĩa vụ của Tiệm AI, khách hàng và hệ thống đại lý phân phối.';
$mauSac      = 'indigo';
$iconTrang   = 'fa-gears';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Quyền và nghĩa vụ của Tiệm AI',
    'items' => 
    array (
      0 => 'Quyền của Tiệm AI: Quyết định danh mục sản phẩm, tính năng, giá bán niêm yết và các chương trình khuyến mại; ban hành và điều chỉnh quy chế giao dịch công khai trên website; tạm khóa hoặc từ chối phục vụ các tài khoản có hành vi gian lận thanh toán hoặc vi phạm pháp luật.',
      1 => 'Nghĩa vụ của Tiệm AI: Minh bạch đầy đủ thông tin về sản phẩm (tính năng, mô hình AI hỗ trợ, thời hạn sử dụng, hạn mức RPM/token/credit, giá cả) trước khi khách hàng bấm thanh toán; bảo đảm mã giao hoạt động đúng như cam kết; vận hành hệ thống thanh toán tự động và bảo mật thông tin khách hàng; tiếp nhận và giải quyết khiếu nại thỏa đáng trong vòng 24 giờ làm việc.',
    ),
  ),
  1 => 
  array (
    'title' => 'Quyền và nghĩa vụ của Khách hàng',
    'items' => 
    array (
      0 => 'Quyền của Khách hàng: Được tiếp cận thông tin trung thực, chính xác về sản phẩm; tự do lựa chọn sản phẩm và phương thức thanh toán; được bảo vệ quyền lợi người tiêu dùng và hỗ trợ kỹ thuật trong suốt thời gian gói còn hạn; được yêu cầu bảo hành, đổi mã hoặc hoàn tiền theo đúng Chính sách hoàn tiền.',
      1 => 'Nghĩa vụ của Khách hàng: Cung cấp đúng email nhận hàng; thanh toán đúng số tiền và nội dung chuyển khoản theo hướng dẫn; tự chịu trách nhiệm bảo quản mã truy cập cá nhân; không sử dụng mã vào các mục đích trái pháp luật.',
    ),
  ),
  2 => 
  array (
    'title' => 'Chính sách cộng tác viên & đại lý phân phối',
    'items' => 
    array (
      0 => 'Khách hàng có thể đăng ký tài khoản đại lý hoặc tham gia chương trình giới thiệu để nhận hoa hồng và chiết khấu sỉ.',
      1 => 'Đại lý có quyền xem thống kê đơn hàng, số dư ví và áp dụng chiết khấu tự động khi đặt hàng qua tài khoản đại lý.',
      2 => 'Đại lý có nghĩa vụ tư vấn trung thực cho khách hàng cuối, không quảng cáo thổi phồng tính năng, không bán phá giá dưới giá sàn quy định và cùng Tiệm AI hỗ trợ khách hàng khi có phát sinh sự cố kỹ thuật.',
    ),
  ),
  3 => 
  array (
    'title' => 'Cơ chế xử lý tài khoản vi phạm',
    'items' => 
    array (
      0 => 'Tiệm AI có quyền tạm ngừng hoặc chấm dứt tài khoản trong các trường hợp: Chia sẻ mã công khai hoặc bán lại trái phép; cố tình tạo lỗi thanh toán hoặc spam đơn ảo gây nghẽn máy chủ; sử dụng dịch vụ vào các hoạt động tấn công mạng hoặc vi phạm pháp luật.',
      1 => 'Mọi quyết định xử lý sẽ được gửi thông báo cụ thể qua email đã đăng ký. Nếu cho rằng có sự nhầm lẫn, khách hàng có thể gửi khiếu nại qua kênh Telegram @Vankhateam để được kiểm tra và phục hồi quyền lợi.',
    ),
  ),
  4 => 
  array (
    'title' => 'Minh bạch trong hiển thị sản phẩm',
    'items' => 
    array (
      0 => 'Website tiemai.io.vn không sử dụng thuật toán cá nhân hóa để điều hướng thứ tự hiển thị và tuyệt đối không nhận tiền để thao túng vị trí sản phẩm.',
      1 => 'Các sản phẩm hiển thị tại trang chủ được chọn lọc dựa trên tiêu chí: tính ổn định cao, được nhiều lập trình viên tin dùng và giá thành hợp lý nhất cho người dùng Việt Nam.',
    ),
  ),
  5 => 
  array (
    'title' => 'Thông tin liên hệ vận hành',
    'items' => 
    array (
      0 => 'Mọi ý kiến đóng góp hoặc thắc mắc về Quy chế hoạt động, vui lòng liên hệ Ban quản trị Tiệm AI:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email điều hành: vankhaqc@gmail.com',
      3 => 'Telegram: @Vankhateam ',
      4 => 'Zalo: 0904045075 (https://zalo.me/0904045075)',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';