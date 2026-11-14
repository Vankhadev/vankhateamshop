<?php
// ========================================================
// TRANG PHÁP LÝ: Chính Sách Bảo Hành & Hoàn Tiền
// Tiệm AI - tiemai.io.vn
// Website: tiemai.io.vn | Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slugHienTai = 'bao-hanh-hoan-tien';
$tieuDeTrang = 'Chính Sách Bảo Hành & Hoàn Tiền';
$moTaTrang   = 'Cam kết bảo hành chu đáo, đổi mã mới và quy định hoàn tiền 100% minh bạch tại Tiệm AI.';
$mauSac      = 'rose';
$iconTrang   = 'fa-shield-halved';

$cacMuc = array (
  0 => 
  array (
    'title' => 'Phạm vi áp dụng chính sách',
    'items' => 
    array (
      0 => 'Chính sách này áp dụng cho toàn bộ các mã API, tài khoản và gói dịch vụ AI đang được cung cấp tại website Tiệm AI (tiemai.io.vn).',
      1 => 'Vì sản phẩm là hàng số điện tử được kích hoạt và sử dụng ngay, quy trình bảo hành và hoàn tiền tuân thủ các nguyên tắc minh bạch dưới đây nhằm bảo đảm quyền lợi tối đa cho khách hàng.',
    ),
  ),
  1 => 
  array (
    'title' => 'Các trường hợp ĐƯỢC bảo hành và hoàn tiền',
    'items' => 
    array (
      0 => '1. Mã không hoạt động ngay từ đầu: Bạn nhập mã đúng theo hướng dẫn kết nối Base URL (https://tiemai.io.vn/v1) nhưng máy chủ báo lỗi xác thực, và Tiệm AI không có mã khác tương đương để đổi.',
      1 => '2. Giao nhầm gói: Mã nhận được không đúng với gói bạn đã đặt mua (nhầm loại model, nhầm thời hạn hoặc nhầm số lượng token/credit).',
      2 => '3. Lỗi kỹ thuật từ hệ thống: Khách hàng bị ngân hàng trừ tiền 2 lần cho 1 đơn hàng, nhận trùng mã, hoặc đơn lỗi không kích hoạt được.',
      3 => '4. Kho tạm hết hàng mà không có mã thay thế: Khách hàng đã thanh toán thành công nhưng kho hàng đối tác hết hàng và khách không muốn chờ.',
      4 => '5. Khách hàng đổi ý khi CHƯA sử dụng: Trong vòng 24 giờ kể từ khi nhận đơn, nếu bạn chưa bấm xem mã và chưa phát sinh bất kỳ lượt request/token nào trên hệ thống (Tiệm AI kiểm tra được lịch sử lưu lượng kết nối), bạn được hoàn đủ 100% không mất phí.',
    ),
  ),
  2 => 
  array (
    'title' => 'Các trường hợp KHÔNG thuộc diện hoàn tiền',
    'items' => 
    array (
      0 => 'Khách hàng đã bấm xem mã và đã sử dụng phát sinh request/token rồi mới đổi ý (vì mã đã xuất kho và đã kích hoạt hạn mức thì không thể thu hồi lại nguyên vẹn).',
      1 => 'Mã bị khóa do người dùng vi phạm quy định: Chia sẻ mã cho nhiều người xài chung, spam prompt độc hại, vi phạm chính sách cấm của bên phát hành gốc hoặc cấu hình sai từ phía khách hàng.',
      2 => 'Mã vẫn hoạt động bình thường, tài nguyên còn đầy đủ nhưng khách hàng không còn nhu cầu sử dụng.',
      3 => 'Gửi yêu cầu hỗ trợ trễ quá thời hạn quy định (quá 72 giờ kể từ khi phát sinh lỗi).',
    ),
  ),
  3 => 
  array (
    'title' => 'Thời hạn gửi yêu cầu bảo hành & hoàn tiền',
    'items' => 
    array (
      0 => 'Đối với mã lỗi, giao nhầm hoặc hết hàng: Khách hàng vui lòng thông báo cho Tiệm AI trong vòng 72 giờ (03 ngày) kể từ thời điểm đơn hàng hoàn tất.',
      1 => 'Đối với trường hợp đổi ý khi chưa dùng: Trong vòng 24 giờ sau khi thanh toán, với điều kiện chưa bấm xem mã và chưa phát sinh lưu lượng sử dụng.',
    ),
  ),
  4 => 
  array (
    'title' => 'Quy trình 5 bước nhận bảo hành & hoàn tiền',
    'items' => 
    array (
      0 => 'Bước 1: Nhắn tin cho Ban quản trị Tiệm AI qua Telegram @Vankhateam hoặc Zalo 0904045075.',
      1 => 'Bước 2: Cung cấp Mã đơn hàng (hoặc mã thanh toán VietQR) và địa chỉ email đã đặt hàng.',
      2 => 'Bước 3: Mô tả rõ hiện tượng lỗi kèm ảnh chụp màn hình / log lỗi (nếu có).',
      3 => 'Bước 4: Tạm dừng sử dụng mã đó để Tiệm AI kiểm tra lịch sử truy cập trên hệ thống.',
      4 => 'Bước 5: Xử lý giải quyết: Tiệm AI ưu tiên đổi mã mới tương đương ngay trong 24 giờ làm việc. Nếu đúng lỗi kỹ thuật mà không có mã thay thế, Tiệm AI chuyển khoản hoàn tiền 100% về tài khoản ngân hàng của bạn trong vòng 1–3 ngày làm việc.',
    ),
  ),
  5 => 
  array (
    'title' => 'Thông tin tiếp nhận yêu cầu bảo hành',
    'items' => 
    array (
      0 => 'Mọi khiếu nại và yêu cầu bảo hành được tiếp nhận chính thức qua:',
      1 => 'Website: https://tiemai.io.vn',
      2 => 'Email xử lý khiếu nại: vankhaqc@gmail.com',
      3 => 'Telegram trực tiếp: @Vankhateam ',
      4 => 'Zalo bảo hành: 0904045075 (https://zalo.me/0904045075)',
      5 => 'Tiệm AI cam kết xử lý công tâm, nhanh gọn và đặt quyền lợi khách hàng lên hàng đầu.',
    ),
  ),
);

require __DIR__ . '/includes/legal-template.php';