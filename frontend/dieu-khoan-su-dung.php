<?php
// ========================================================
// TRANG PHÁP LÝ: Điều Khoản Sử Dụng
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'dieu-khoan-su-dung';
$tieuDeTrang = 'Điều Khoản Sử Dụng';
$moTaTrang   = 'Quy định pháp lý khi truy cập website, đặt mua mã API và sử dụng dịch vụ tại Tiệm AI (tiemai.io.vn).';
$mauSac      = 'blue';
$iconTrang   = 'fa-file-contract';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Chấp nhận điều khoản',
    'items' => 
    array (
      0 => 'Khi truy cập và sử dụng website Tiệm AI tại địa chỉ https://tiemai.io.vn, bạn đồng ý bị ràng buộc bởi Điều khoản sử dụng này cùng với Chính sách bảo mật và toàn bộ các quy định được công bố trên website.',
      1 => 'Tiệm AI có quyền cập nhật, bổ sung các điều khoản bất kỳ lúc nào để phù hợp với quy định pháp luật và hoạt động thực tế. Phiên bản điều chỉnh có hiệu lực ngay khi được đăng tải trên website.',
      2 => 'Nếu bạn không đồng ý với bất kỳ nội dung nào trong điều khoản, vui lòng dừng việc tạo đơn và không tiếp tục sử dụng dịch vụ của Tiệm AI.',
    ),
  ),
  1 => 
  array (
    'title' => 'Mô tả dịch vụ & bản chất sản phẩm',
    'items' => 
    array (
      0 => 'Tiệm AI (tiemai.io.vn) là nền tảng phân phối mã truy cập (API Key), hạ tầng AI Gateway và phần mềm kỹ thuật số tại Việt Nam. Khách hàng mua mã truy cập từ hệ thống và sử dụng thông qua các công cụ AI được ghi rõ trên từng trang sản phẩm.',
      1 => 'Tất cả sản phẩm kinh doanh tại Tiệm AI là sản phẩm số (Digital Products) — thông tin truy cập (API Key, Base URL, tài khoản) được giao hoàn toàn tự động dưới dạng dữ liệu điện tử. Hoàn toàn không có sản phẩm vật lý nào giao qua đường bưu điện.',
      2 => 'Mỗi khách hàng sau khi thanh toán thành công sẽ được cấp mã truy cập riêng biệt, không dùng chung và có hạn mức/chu kỳ sử dụng độc lập.',
    ),
  ),
  2 => 
  array (
    'title' => 'Sản phẩm bạn nhận được & cách xem mã',
    'items' => 
    array (
      0 => 'Mỗi đơn hàng hoàn tất sẽ cung cấp một mã truy cập (API Key) kèm địa chỉ máy chủ API Gateway (Base URL: https://tiemai.io.vn/v1) và tài liệu hướng dẫn cấu hình chi tiết.',
      1 => 'Thông tin mã hiển thị trực tiếp tại trang chi tiết đơn hàng (nhấn nút "Hiển thị mã") ngay sau khi hệ thống xác nhận thanh toán thành công, đồng thời hệ thống gửi lưu trữ về email bạn đã đăng ký.',
      2 => 'Lưu ý quan trọng: Mã truy cập là tài sản riêng của bạn. Vui lòng tự bảo quản và không đăng công khai mã lên các kho lưu trữ (như GitHub, diễn đàn). Mã bị lộ do lỗi người dùng dẫn đến việc bị bên thứ ba lạm dụng hoặc bị khóa tài khoản sẽ không thuộc phạm vi bảo hành.',
    ),
  ),
  3 => 
  array (
    'title' => 'Quy trình giao hàng & xác nhận thanh toán',
    'items' => 
    array (
      0 => 'Tiệm AI vận hành hệ thống giao hàng tự động 24/7: Khách hàng quét mã VietQR tại màn hình thanh toán, tiền vào tài khoản là hệ thống tự động xuất kho và giao mã trong vòng 1–2 phút.',
      1 => 'Mã thanh toán VietQR của mỗi đơn hàng được giữ hiệu lực trong 05 phút. Sau thời gian này, đơn hàng chưa thanh toán sẽ tự động hủy trên giao diện.',
      2 => 'Trong trường hợp kho đối tác tạm thời hết hàng hoặc đang đồng bộ, đơn hàng sẽ chuyển sang trạng thái "Đang xử lý". Hệ thống sẽ giao bù ngay khi có hàng hoặc tự động kích hoạt bảo vệ hoàn tiền 100% về ví tài khoản của bạn.',
      3 => 'Khách hàng có thể bấm nút "Kiểm tra thanh toán" hoặc tra cứu lại bất kỳ lúc nào tại mục Đơn hàng của tôi và trang Tra Cứu Key.',
    ),
  ),
  4 => 
  array (
    'title' => 'Quy định sử dụng dịch vụ hợp lệ',
    'items' => 
    array (
      0 => 'Khách hàng có nghĩa vụ cung cấp thông tin chính xác khi đặt hàng, đặc biệt là địa chỉ email nhận thông tin đối soát.',
      1 => 'Sử dụng mã truy cập đúng mục đích, phạm vi và hạn mức được mô tả ở gói sản phẩm (không chia sẻ bừa bãi, không cho thuê lại trái phép).',
      2 => 'Tuyệt đối không sử dụng dịch vụ vào các mục đích vi phạm pháp luật nước CHXHCN Việt Nam, phát tán mã độc, lừa đảo, tấn công từ chối dịch vụ (DDoS) hoặc spam làm nghẽn hệ thống.',
      3 => 'Để ngăn chặn hành vi đầu cơ và spam đơn, hệ thống có quyền tự động tạm khóa các tài khoản tạo số lượng lớn đơn rác liên tục trong thời gian ngắn.',
    ),
  ),
  5 => 
  array (
    'title' => 'Giới hạn trách nhiệm & sở hữu trí tuệ',
    'items' => 
    array (
      0 => 'Tiệm AI không chịu trách nhiệm đối với các thiệt hại gián tiếp phát sinh từ việc sử dụng hoặc không thể sử dụng dịch vụ (như mất dữ liệu, gián đoạn công việc cá nhân). Trách nhiệm tối đa của Tiệm AI trong mọi trường hợp không vượt quá tổng số tiền khách hàng đã thanh toán cho đơn hàng đó.',
      1 => 'Tên thương hiệu, biểu trưng và nhãn hiệu của các tổ chức phát hành gốc (OpenAI, Anthropic, Cursor, DeepSeek, Google, xAI...) là tài sản của chủ sở hữu tương ứng. Tiệm AI là đơn vị bán lẻ độc lập cung cấp giải pháp kết nối Gateway, không phải là cơ quan đại diện hay liên doanh ủy quyền trực tiếp.',
    ),
  ),
  6 => 
  array (
    'title' => 'Thông tin liên hệ hỗ trợ',
    'items' => 
    array (
      0 => 'Nếu có bất kỳ thắc mắc nào về Điều khoản sử dụng, vui lòng liên hệ Tiệm AI:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email: vankhaqc@gmail.com',
      3 => 'Telegram hỗ trợ: @Vankhateam ',
      4 => 'Zalo / Hotline: 0904045075 (https://zalo.me/0904045075)',
      5 => 'Khung giờ làm việc: 08:30 – 24:00 hàng ngày (kể cả Thứ 7, Chủ Nhật và ngày Lễ).',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';