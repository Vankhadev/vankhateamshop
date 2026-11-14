<?php
// ========================================================
// TRUNG TÂM CHÍNH SÁCH PHÁP LÝ & QUY CHẾ HOẠT ĐỘNG
// Website: tiemai.io.vn
// Email: vankhaqc@gmail.com | Telegram: @Vankhateam
// ========================================================

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/security.php';

thietLapBaoMatHeaders();

$slug = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$slug = preg_replace('/\.php$/', '', $slug);

// Ánh xạ các đường dẫn phụ và alias về 7 trang chuẩn
$aliasMap = [
    'dieu-khoan'            => 'dieu-khoan-su-dung',
    'chinh-sach'            => 'dieu-khoan-su-dung',
    'chinh-sach-cung-cap'   => 'chinh-sach-cung-cap-san-pham',
    'chinh-sach-hoan-tien'  => 'bao-hanh-hoan-tien',
];
if (isset($aliasMap[$slug])) {
    $slug = $aliasMap[$slug];
}

$chinhSach = [
    'dieu-khoan-su-dung' => [
        'nhan'    => 'Điều khoản sử dụng',
        'tieu_de' => 'Điều Khoản Sử Dụng',
        'mo_ta'   => 'Quy định pháp lý khi truy cập website, đặt mua mã API và sử dụng dịch vụ tại Tiệm AI (tiemai.io.vn).',
        'icon'    => 'fa-file-contract',
        'mau'     => 'blue',
        'muc'     => [
            [
                'title' => 'Chấp nhận điều khoản',
                'items' => [
                    'Khi truy cập và sử dụng website Tiệm AI tại địa chỉ https://tiemai.io.vn, bạn đồng ý bị ràng buộc bởi Điều khoản sử dụng này cùng với Chính sách bảo mật và toàn bộ các quy định được công bố trên website.',
                    'Tiệm AI có quyền cập nhật, bổ sung các điều khoản bất kỳ lúc nào để phù hợp với quy định pháp luật và hoạt động thực tế. Phiên bản điều chỉnh có hiệu lực ngay khi được đăng tải trên website.',
                    'Nếu bạn không đồng ý với bất kỳ nội dung nào trong điều khoản, vui lòng dừng việc tạo đơn và không tiếp tục sử dụng dịch vụ của Tiệm AI.'
                ]
            ],
            [
                'title' => 'Mô tả dịch vụ & bản chất sản phẩm',
                'items' => [
                    'Tiệm AI (tiemai.io.vn) là nền tảng phân phối mã truy cập (API Key), hạ tầng AI Gateway và phần mềm kỹ thuật số tại Việt Nam. Khách hàng mua mã truy cập từ hệ thống và sử dụng thông qua các công cụ AI được ghi rõ trên từng trang sản phẩm.',
                    'Tất cả sản phẩm kinh doanh tại Tiệm AI là sản phẩm số (Digital Products) — thông tin truy cập (API Key, Base URL, tài khoản) được giao hoàn toàn tự động dưới dạng dữ liệu điện tử. Hoàn toàn không có sản phẩm vật lý nào giao qua đường bưu điện.',
                    'Mỗi khách hàng sau khi thanh toán thành công sẽ được cấp mã truy cập riêng biệt, không dùng chung và có hạn mức/chu kỳ sử dụng độc lập.'
                ]
            ],
            [
                'title' => 'Sản phẩm bạn nhận được & cách xem mã',
                'items' => [
                    'Mỗi đơn hàng hoàn tất sẽ cung cấp một mã truy cập (API Key) kèm địa chỉ máy chủ API Gateway (Base URL: https://tiemai.io.vn/v1) và tài liệu hướng dẫn cấu hình chi tiết.',
                    'Thông tin mã hiển thị trực tiếp tại trang chi tiết đơn hàng (nhấn nút "Hiển thị mã") ngay sau khi hệ thống xác nhận thanh toán thành công, đồng thời hệ thống gửi lưu trữ về email bạn đã đăng ký.',
                    'Lưu ý quan trọng: Mã truy cập là tài sản riêng của bạn. Vui lòng tự bảo quản và không đăng công khai mã lên các kho lưu trữ (như GitHub, diễn đàn). Mã bị lộ do lỗi người dùng dẫn đến việc bị bên thứ ba lạm dụng hoặc bị khóa tài khoản sẽ không thuộc phạm vi bảo hành.'
                ]
            ],
            [
                'title' => 'Quy trình giao hàng & xác nhận thanh toán',
                'items' => [
                    'Tiệm AI vận hành hệ thống giao hàng tự động 24/7: Khách hàng quét mã VietQR tại màn hình thanh toán, tiền vào tài khoản là hệ thống tự động xuất kho và giao mã trong vòng 1–2 phút.',
                    'Mã thanh toán VietQR của mỗi đơn hàng được giữ hiệu lực trong 05 phút. Sau thời gian này, đơn hàng chưa thanh toán sẽ tự động hủy trên giao diện.',
                    'Trong trường hợp kho đối tác tạm thời hết hàng hoặc đang đồng bộ, đơn hàng sẽ chuyển sang trạng thái "Đang xử lý". Hệ thống sẽ giao bù ngay khi có hàng hoặc tự động kích hoạt bảo vệ hoàn tiền 100% về ví tài khoản của bạn.',
                    'Khách hàng có thể bấm nút "Kiểm tra thanh toán" hoặc tra cứu lại bất kỳ lúc nào tại mục Đơn hàng của tôi và trang Tra Cứu Key.'
                ]
            ],
            [
                'title' => 'Quy định sử dụng dịch vụ hợp lệ',
                'items' => [
                    'Khách hàng có nghĩa vụ cung cấp thông tin chính xác khi đặt hàng, đặc biệt là địa chỉ email nhận thông tin đối soát.',
                    'Sử dụng mã truy cập đúng mục đích, phạm vi và hạn mức được mô tả ở gói sản phẩm (không chia sẻ bừa bãi, không cho thuê lại trái phép).',
                    'Tuyệt đối không sử dụng dịch vụ vào các mục đích vi phạm pháp luật nước CHXHCN Việt Nam, phát tán mã độc, lừa đảo, tấn công từ chối dịch vụ (DDoS) hoặc spam làm nghẽn hệ thống.',
                    'Để ngăn chặn hành vi đầu cơ và spam đơn, hệ thống có quyền tự động tạm khóa các tài khoản tạo số lượng lớn đơn rác liên tục trong thời gian ngắn.'
                ]
            ],
            [
                'title' => 'Giới hạn trách nhiệm & sở hữu trí tuệ',
                'items' => [
                    'Tiệm AI không chịu trách nhiệm đối với các thiệt hại gián tiếp phát sinh từ việc sử dụng hoặc không thể sử dụng dịch vụ (như mất dữ liệu, gián đoạn công việc cá nhân). Trách nhiệm tối đa của Tiệm AI trong mọi trường hợp không vượt quá tổng số tiền khách hàng đã thanh toán cho đơn hàng đó.',
                    'Tên thương hiệu, biểu trưng và nhãn hiệu của các tổ chức phát hành gốc (OpenAI, Anthropic, Cursor, DeepSeek, Google, xAI...) là tài sản của chủ sở hữu tương ứng. Tiệm AI là đơn vị bán lẻ độc lập cung cấp giải pháp kết nối Gateway, không phải là cơ quan đại diện hay liên doanh ủy quyền trực tiếp.'
                ]
            ],
            [
                'title' => 'Thông tin liên hệ hỗ trợ',
                'items' => [
                    'Nếu có bất kỳ thắc mắc nào về Điều khoản sử dụng, vui lòng liên hệ Tiệm AI:',
                    'Website: https://tiemai.io.vn',
                    'Email: vankhaqc@gmail.com',
                    'Telegram hỗ trợ: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo / Hotline: 0847154088 (https://zalo.me/0904045075)',
                    'Khung giờ làm việc: 08:30 – 24:00 hàng ngày (kể cả Thứ 7, Chủ Nhật và ngày Lễ).'
                ]
            ]
        ]
    ],

    'quy-che-hoat-dong' => [
        'nhan'    => 'Quy chế hoạt động',
        'tieu_de' => 'Quy Chế Hoạt Động',
        'mo_ta'   => 'Nguyên tắc vận hành, quyền và nghĩa vụ của Tiệm AI, khách hàng và hệ thống đại lý phân phối.',
        'icon'    => 'fa-gears',
        'mau'     => 'indigo',
        'muc'     => [
            [
                'title' => 'Quyền và nghĩa vụ của Tiệm AI',
                'items' => [
                    'Quyền của Tiệm AI: Quyết định danh mục sản phẩm, tính năng, giá bán niêm yết và các chương trình khuyến mại; ban hành và điều chỉnh quy chế giao dịch công khai trên website; tạm khóa hoặc từ chối phục vụ các tài khoản có hành vi gian lận thanh toán hoặc vi phạm pháp luật.',
                    'Nghĩa vụ của Tiệm AI: Minh bạch đầy đủ thông tin về sản phẩm (tính năng, mô hình AI hỗ trợ, thời hạn sử dụng, hạn mức RPM/token/credit, giá cả) trước khi khách hàng bấm thanh toán; bảo đảm mã giao hoạt động đúng như cam kết; vận hành hệ thống thanh toán tự động và bảo mật thông tin khách hàng; tiếp nhận và giải quyết khiếu nại thỏa đáng trong vòng 24 giờ làm việc.'
                ]
            ],
            [
                'title' => 'Quyền và nghĩa vụ của Khách hàng',
                'items' => [
                    'Quyền của Khách hàng: Được tiếp cận thông tin trung thực, chính xác về sản phẩm; tự do lựa chọn sản phẩm và phương thức thanh toán; được bảo vệ quyền lợi người tiêu dùng và hỗ trợ kỹ thuật trong suốt thời gian gói còn hạn; được yêu cầu bảo hành, đổi mã hoặc hoàn tiền theo đúng Chính sách hoàn tiền.',
                    'Nghĩa vụ của Khách hàng: Cung cấp đúng email nhận hàng; thanh toán đúng số tiền và nội dung chuyển khoản theo hướng dẫn; tự chịu trách nhiệm bảo quản mã truy cập cá nhân; không sử dụng mã vào các mục đích trái pháp luật.'
                ]
            ],
            [
                'title' => 'Chính sách cộng tác viên & đại lý phân phối',
                'items' => [
                    'Khách hàng có thể đăng ký tài khoản đại lý hoặc tham gia chương trình giới thiệu để nhận hoa hồng và chiết khấu sỉ.',
                    'Đại lý có quyền xem thống kê đơn hàng, số dư ví và áp dụng chiết khấu tự động khi đặt hàng qua tài khoản đại lý.',
                    'Đại lý có nghĩa vụ tư vấn trung thực cho khách hàng cuối, không quảng cáo thổi phồng tính năng, không bán phá giá dưới giá sàn quy định và cùng Tiệm AI hỗ trợ khách hàng khi có phát sinh sự cố kỹ thuật.'
                ]
            ],
            [
                'title' => 'Cơ chế xử lý tài khoản vi phạm',
                'items' => [
                    'Tiệm AI có quyền tạm ngừng hoặc chấm dứt tài khoản trong các trường hợp: Chia sẻ mã công khai hoặc bán lại trái phép; cố tình tạo lỗi thanh toán hoặc spam đơn ảo gây nghẽn máy chủ; sử dụng dịch vụ vào các hoạt động tấn công mạng hoặc vi phạm pháp luật.',
                    'Mọi quyết định xử lý sẽ được gửi thông báo cụ thể qua email đã đăng ký. Nếu cho rằng có sự nhầm lẫn, khách hàng có thể gửi khiếu nại qua kênh Telegram @Vankhateam để được kiểm tra và phục hồi quyền lợi.'
                ]
            ],
            [
                'title' => 'Minh bạch trong hiển thị sản phẩm',
                'items' => [
                    'Website tiemai.io.vn không sử dụng thuật toán cá nhân hóa để điều hướng thứ tự hiển thị và tuyệt đối không nhận tiền để thao túng vị trí sản phẩm.',
                    'Các sản phẩm hiển thị tại trang chủ được chọn lọc dựa trên tiêu chí: tính ổn định cao, được nhiều lập trình viên tin dùng và giá thành hợp lý nhất cho người dùng Việt Nam.'
                ]
            ],
            [
                'title' => 'Thông tin liên hệ vận hành',
                'items' => [
                    'Mọi ý kiến đóng góp hoặc thắc mắc về Quy chế hoạt động, vui lòng liên hệ Ban quản trị Tiệm AI:',
                    'Website: https://tiemai.io.vn',
                    'Email điều hành: vankhaqc@gmail.com',
                    'Telegram: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo: 0904045075 (https://zalo.me/0904045075)'
                ]
            ]
        ]
    ],

    'chinh-sach-thanh-toan' => [
        'nhan'    => 'Chính sách thanh toán',
        'tieu_de' => 'Chính Sách Thanh Toán',
        'mo_ta'   => 'Phương thức thanh toán VietQR tự động, nạp ví đại lý và quy định đối soát đơn hàng tại Tiệm AI.',
        'icon'    => 'fa-credit-card',
        'mau'     => 'emerald',
        'muc'     => [
            [
                'title' => 'Nguyên tắc định giá và niêm yết',
                'items' => [
                    'Tất cả giá bán sản phẩm trên website Tiệm AI được niêm yết bằng Việt Nam Đồng (VNĐ) và đã bao gồm toàn bộ chi phí theo gói.',
                    'Giá chốt của đơn hàng là giá tại thời điểm khách hàng tạo đơn và thanh toán thành công. Tiệm AI có quyền điều chỉnh giá bán hoặc áp dụng các chương trình ưu đãi mới theo biến động thị trường nguồn cung mà không áp dụng hồi tố cho các đơn hàng đã thanh toán trước đó.'
                ]
            ],
            [
                'title' => 'Phương thức thanh toán chấp nhận',
                'items' => [
                    '1. Quét mã VietQR tự động (Dành cho tất cả khách hàng): Khách hàng mở ứng dụng ngân hàng hoặc ví điện tử bất kỳ, quét mã QR hiển thị tại trang thanh toán. Hệ thống tự động điền sẵn số tiền và nội dung đơn hàng, giao dịch được xác nhận ngay lập tức 24/7.',
                    '2. Trừ số dư Ví tài khoản (Dành cho đại lý & thành viên thân thiết): Khách hàng nạp sẵn số dư vào ví để mua hàng siêu tốc 1 click mà không cần quét mã chuyển khoản nhiều lần, đồng thời hưởng chiết khấu đại lý tốt nhất.'
                ]
            ],
            [
                'title' => 'Thời gian giữ đơn & xác nhận thanh toán',
                'items' => [
                    'Đơn hàng được xác nhận là "Đã thanh toán" ngay khi tiền về đúng số tài khoản ngân hàng của Tiệm AI kèm đúng nội dung chuyển khoản hiển thị trên màn hình.',
                    'Mỗi mã QR thanh toán có hiệu lực trong vòng 05 phút. Sau 05 phút, đơn hàng chưa nhận tiền sẽ tự động hủy trên giao diện.',
                    'Trường hợp khách hàng chuyển nhầm nội dung, chuyển thiếu hoặc chuyển sau khi đơn đã hủy: Bạn vui lòng chụp biên lai giao dịch và nhắn ngay cho Admin qua Telegram @Vankhateam hoặc Zalo 0847154088. Hệ thống sẽ đối soát thủ công và kích hoạt đơn hoặc nạp thẳng tiền vào ví cho bạn trong vòng 5–15 phút.'
                ]
            ],
            [
                'title' => 'Quy định về số dư ví đại lý',
                'items' => [
                    'Số dư ví tại Tiệm AI được nạp trước để thanh toán các sản phẩm và dịch vụ trên hệ thống. Hết số dư, khách hàng chỉ cần nạp thêm để tiếp tục lên đơn.',
                    'Số dư ví không phải là tài khoản tiền gửi ngân hàng. Trường hợp đại lý muốn rút lại số dư ví chưa sử dụng, vui lòng liên hệ Admin để được đối soát và xử lý hoàn tiền theo quy định.'
                ]
            ],
            [
                'title' => 'Thông tin hỗ trợ thanh toán',
                'items' => [
                    'Mọi vấn đề liên quan đến chuyển khoản, đối soát tiền và kích hoạt đơn hàng:',
                    'Website: https://tiemai.io.vn',
                    'Email kế toán: vankhaqc@gmail.com',
                    'Telegram hỗ trợ: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo trực ban: 0904045075'
                ]
            ]
        ]
    ],

    'chinh-sach-cung-cap-san-pham' => [
        'nhan'    => 'Chính sách cung cấp sản phẩm',
        'tieu_de' => 'Chính Sách Cung Cấp Sản Phẩm',
        'mo_ta'   => 'Quy định về phạm vi sản phẩm số, cách thức nhận hàng và thời gian kích hoạt tại Tiệm AI.',
        'icon'    => 'fa-box-open',
        'mau'     => 'amber',
        'muc'     => [
            [
                'title' => 'Phạm vi & bản chất sản phẩm cung cấp',
                'items' => [
                    'Tiệm AI chỉ cung cấp 100% sản phẩm kỹ thuật số (Digital Goods): Mã API Key (Cursor, Codex, Claude, DeepSeek, Gemini, Grok...), gói tài khoản AI bản quyền và bản quyền phần mềm hỗ trợ lập trình viên.',
                    'Hoàn toàn không có hình thức giao nhận hàng vật lý, không giao qua bưu điện hay lắp đặt tận nơi.',
                    'Các gói dịch vụ phụ thuộc vào hệ thống máy chủ và chính sách của nhà cung cấp gốc (OpenAI, Anthropic, Cursor, DeepSeek...). Khi bên gốc có điều chỉnh về quota hoặc kiến trúc mô hình, Tiệm AI sẽ thông báo công khai và hướng dẫn khách hàng cập nhật tương ứng.'
                ]
            ],
            [
                'title' => 'Thời gian giao nhận & kích hoạt',
                'items' => [
                    'Mã truy cập được xuất kho và giao tự động ngay sau khi hệ thống nhận đủ tiền thanh toán đúng đơn hàng đó, thông thường chỉ mất từ 1 đến 2 phút.',
                    'Thời hạn sử dụng của gói (1 ngày, 3 ngày, 7 ngày, 30 ngày...) được tính bắt đầu từ thời điểm hệ thống xuất mã thành công trên trang đơn hàng của bạn, không tính từ thời điểm bạn bắt đầu mở máy tính ra cấu hình.',
                    'Khách hàng có thể xem lại mã của mình bất kỳ lúc nào tại mục Đơn hàng của tôi hoặc sử dụng công cụ Tra Cứu Key trên website.'
                ]
            ],
            [
                'title' => 'Bảo mật và trách nhiệm lưu trữ mã',
                'items' => [
                    'Từ thời điểm nhận mã, khách hàng có trách nhiệm tự bảo mật mã API và tài khoản của mình. Mọi lượt gọi API, token tiêu thụ phát sinh từ mã đó được tính cho đơn hàng của bạn.',
                    'Tuyệt đối không chia sẻ, bán lại hoặc dùng chung mã trái với quy định gói sản phẩm. Không dán mã API vào các mã nguồn công khai trên internet.',
                    'Khách hàng có trách nhiệm nhập đúng email khi tạo đơn. Tiệm AI không chịu trách nhiệm trong trường hợp khách hàng nhập sai email của người khác.'
                ]
            ],
            [
                'title' => 'Trường hợp kho tạm hết hàng & giải pháp bảo vệ khách hàng',
                'items' => [
                    'Nếu kho đối tác tạm thời hết hàng vào thời điểm bạn thanh toán, hệ thống lập tức kích hoạt cơ chế bảo vệ: Đơn hàng được giữ trạng thái "Đang xử lý" để cấp bù sớm nhất.',
                    'Nếu đơn hàng chưa có hàng bù trong thời gian quy định, hệ thống tự động hoàn tiền 100% vào số dư ví tài khoản hoặc cấp mã thay thế tương đương để khách hàng không bị gián đoạn công việc.'
                ]
            ],
            [
                'title' => 'Kênh liên hệ hỗ trợ đơn hàng',
                'items' => [
                    'Nếu cần hỗ trợ kỹ thuật cài đặt hoặc nhận mã:',
                    'Website: https://tiemai.io.vn',
                    'Email: vankhaqc@gmail.com',
                    'Telegram: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo: 0904045075 (https://zalo.me/0904045075)'
                ]
            ]
        ]
    ],

    'bao-hanh-hoan-tien' => [
        'nhan'    => 'Bảo hành & hoàn tiền',
        'tieu_de' => 'Chính Sách Bảo Hành & Hoàn Tiền',
        'mo_ta'   => 'Cam kết bảo hành chu đáo, đổi mã mới và quy định hoàn tiền 100% minh bạch tại Tiệm AI.',
        'icon'    => 'fa-shield-halved',
        'mau'     => 'rose',
        'muc'     => [
            [
                'title' => 'Phạm vi áp dụng chính sách',
                'items' => [
                    'Chính sách này áp dụng cho toàn bộ các mã API, tài khoản và gói dịch vụ AI đang được cung cấp tại website Tiệm AI (tiemai.io.vn).',
                    'Vì sản phẩm là hàng số điện tử được kích hoạt và sử dụng ngay, quy trình bảo hành và hoàn tiền tuân thủ các nguyên tắc minh bạch dưới đây nhằm bảo đảm quyền lợi tối đa cho khách hàng.'
                ]
            ],
            [
                'title' => 'Các trường hợp ĐƯỢC bảo hành và hoàn tiền',
                'items' => [
                    '1. Mã không hoạt động ngay từ đầu: Bạn nhập mã đúng theo hướng dẫn kết nối Base URL (https://tiemai.io.vn/v1) nhưng máy chủ báo lỗi xác thực, và Tiệm AI không có mã khác tương đương để đổi.',
                    '2. Giao nhầm gói: Mã nhận được không đúng với gói bạn đã đặt mua (nhầm loại model, nhầm thời hạn hoặc nhầm số lượng token/credit).',
                    '3. Lỗi kỹ thuật từ hệ thống: Khách hàng bị ngân hàng trừ tiền 2 lần cho 1 đơn hàng, nhận trùng mã, hoặc đơn lỗi không kích hoạt được.',
                    '4. Kho tạm hết hàng mà không có mã thay thế: Khách hàng đã thanh toán thành công nhưng kho hàng đối tác hết hàng và khách không muốn chờ.',
                    '5. Khách hàng đổi ý khi CHƯA sử dụng: Trong vòng 24 giờ kể từ khi nhận đơn, nếu bạn chưa bấm xem mã và chưa phát sinh bất kỳ lượt request/token nào trên hệ thống (Tiệm AI kiểm tra được lịch sử lưu lượng kết nối), bạn được hoàn đủ 100% không mất phí.'
                ]
            ],
            [
                'title' => 'Các trường hợp KHÔNG thuộc diện hoàn tiền',
                'items' => [
                    'Khách hàng đã bấm xem mã và đã sử dụng phát sinh request/token rồi mới đổi ý (vì mã đã xuất kho và đã kích hoạt hạn mức thì không thể thu hồi lại nguyên vẹn).',
                    'Mã bị khóa do người dùng vi phạm quy định: Chia sẻ mã cho nhiều người xài chung, spam prompt độc hại, vi phạm chính sách cấm của bên phát hành gốc hoặc cấu hình sai từ phía khách hàng.',
                    'Mã vẫn hoạt động bình thường, tài nguyên còn đầy đủ nhưng khách hàng không còn nhu cầu sử dụng.',
                    'Gửi yêu cầu hỗ trợ trễ quá thời hạn quy định (quá 72 giờ kể từ khi phát sinh lỗi).'
                ]
            ],
            [
                'title' => 'Thời hạn gửi yêu cầu bảo hành & hoàn tiền',
                'items' => [
                    'Đối với mã lỗi, giao nhầm hoặc hết hàng: Khách hàng vui lòng thông báo cho Tiệm AI trong vòng 72 giờ (03 ngày) kể từ thời điểm đơn hàng hoàn tất.',
                    'Đối với trường hợp đổi ý khi chưa dùng: Trong vòng 24 giờ sau khi thanh toán, với điều kiện chưa bấm xem mã và chưa phát sinh lưu lượng sử dụng.'
                ]
            ],
            [
                'title' => 'Quy trình 5 bước nhận bảo hành & hoàn tiền',
                'items' => [
                    'Bước 1: Nhắn tin cho Ban quản trị Tiệm AI qua Telegram @Vankhateam hoặc Zalo 0847154088.',
                    'Bước 2: Cung cấp Mã đơn hàng (hoặc mã thanh toán VietQR) và địa chỉ email đã đặt hàng.',
                    'Bước 3: Mô tả rõ hiện tượng lỗi kèm ảnh chụp màn hình / log lỗi (nếu có).',
                    'Bước 4: Tạm dừng sử dụng mã đó để Tiệm AI kiểm tra lịch sử truy cập trên hệ thống.',
                    'Bước 5: Xử lý giải quyết: Tiệm AI ưu tiên đổi mã mới tương đương ngay trong 24 giờ làm việc. Nếu đúng lỗi kỹ thuật mà không có mã thay thế, Tiệm AI chuyển khoản hoàn tiền 100% về tài khoản ngân hàng của bạn trong vòng 1–3 ngày làm việc.'
                ]
            ],
            [
                'title' => 'Thông tin tiếp nhận yêu cầu bảo hành',
                'items' => [
                    'Mọi khiếu nại và yêu cầu bảo hành được tiếp nhận chính thức qua:',
                    'Website: https://tiemai.io.vn',
                    'Email xử lý khiếu nại: vankhaqc@gmail.com',
                    'Telegram trực tiếp: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo bảo hành: 0847154088 (https://zalo.me/0904045075)',
                    'Tiệm AI cam kết xử lý công tâm, nhanh gọn và đặt quyền lợi khách hàng lên hàng đầu.'
                ]
            ]
        ]
    ],

    'dieu-khoan-dai-ly' => [
        'nhan'    => 'Điều khoản đại lý',
        'tieu_de' => 'Điều Khoản Dành Cho Đại Lý',
        'mo_ta'   => 'Chính sách giá sỉ, điều kiện hợp tác và quyền lợi dành cho đối tác phân phối tại Tiệm AI.',
        'icon'    => 'fa-handshake',
        'mau'     => 'purple',
        'muc'     => [
            [
                'title' => 'Định nghĩa & điều kiện hợp tác',
                'items' => [
                    'Đại lý (Reseller): Là cá nhân hoặc tổ chức được Tiệm AI kích hoạt quyền truy cập bảng giá sỉ và nạp số dư ví để phân phối lại sản phẩm cho khách hàng cuối.',
                    'Điều kiện trở thành đại lý: Đủ 18 tuổi, có thông tin liên lạc xác thực (Email, Telegram/Zalo); có tệp khách hàng hoặc kênh phân phối riêng (Website, hội nhóm, cộng đồng lập trình viên); tuân thủ chính sách giá và quy định của Tiệm AI.'
                ]
            ],
            [
                'title' => 'Chính sách giá sỉ & chiết khấu',
                'items' => [
                    'Đại lý được áp dụng bảng giá sỉ chiết khấu trực tiếp trên từng sản phẩm khi mua qua tài khoản đại lý hoặc qua API kết nối.',
                    'Đại lý tự quyết định giá bán lẻ ra thị trường cho khách hàng của mình nhưng không được bán phá giá dưới giá sàn quy định của Tiệm AI nhằm duy trì môi trường kinh doanh lành mạnh.',
                    'Trong các dịp khuyến mại đồng loạt, Tiệm AI sẽ thông báo trước để đại lý chủ động điều chỉnh kế hoạch kinh doanh.'
                ]
            ],
            [
                'title' => 'Trách nhiệm đối với khách hàng cuối',
                'items' => [
                    'Đại lý tự chịu trách nhiệm tìm kiếm, tư vấn và hỗ trợ khách hàng của mình trước và sau khi bán.',
                    'Khi sản phẩm gặp sự cố kỹ thuật từ phía nguồn cung, Tiệm AI cam kết đồng hành cùng đại lý để kiểm tra, đổi mã hoặc hoàn tiền cho đại lý để đại lý xử lý cho khách hàng.',
                    'Tuyệt đối không sử dụng thông tin khách hàng vào các mục đích trái pháp luật.'
                ]
            ],
            [
                'title' => 'Bảo mật mã truy cập & tài liệu nội bộ',
                'items' => [
                    'Mã truy cập là tài sản quan trọng, đại lý có trách nhiệm lưu trữ an toàn, chỉ bàn giao cho khách hàng qua kênh riêng tư, không để lộ mã ra bên ngoài.',
                    'Nghiêm cấm hành vi làm giả mã, chỉnh sửa mã hoặc bán lại các mã không rõ nguồn gốc mạo danh Tiệm AI.',
                    'Đại lý có trách nhiệm giữ bí mật bảng giá nhập nội bộ và các tài liệu kỹ thuật được cung cấp.'
                ]
            ],
            [
                'title' => 'Thanh toán & nạp số dư ví đại lý',
                'items' => [
                    'Đại lý nạp tiền trước vào Số Dư Ví trên website tiemai.io.vn để hệ thống tự động trừ tiền khi mua hàng.',
                    'Giá nhập được tính chuẩn xác theo bảng giá tại thời điểm đại lý tạo đơn. Mọi dữ liệu giao dịch được lưu trữ minh bạch trong mục Lịch sử ví và Đơn hàng.',
                    'Đại lý tự chịu trách nhiệm kê khai và thực hiện các nghĩa vụ thuế (nếu có) phát sinh từ hoạt động kinh doanh bán lẻ của mình.'
                ]
            ],
            [
                'title' => 'Tạm ngừng hợp tác đại lý',
                'items' => [
                    'Tiệm AI có quyền chấm dứt tư cách đại lý trong các trường hợp: Bán hàng giả, lừa đảo khách hàng, vi phạm pháp luật, phá giá thị trường nghiêm trọng hoặc lợi dụng lỗi hệ thống để trục lợi.',
                    'Trường hợp đại lý muốn ngừng hợp tác, vui lòng thông báo trước cho Tiệm AI để hai bên cùng đối soát và chốt hoàn trả số dư ví còn lại.'
                ]
            ],
            [
                'title' => 'Kênh liên hệ hợp tác đại lý',
                'items' => [
                    'Quý đối tác có nhu cầu hợp tác đại lý hoặc kết nối API nguồn cung sỉ, vui lòng liên hệ:',
                    'Website: https://tiemai.io.vn',
                    'Email đối tác: vankhaqc@gmail.com',
                    'Telegram quản lý đại lý: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo quản lý: 0904045075 (https://zalo.me/0904045075)'
                ]
            ]
        ]
    ],

    'chinh-sach-bao-mat' => [
        'nhan'    => 'Chính sách bảo mật',
        'tieu_de' => 'Chính Sách Bảo Mật Thông Tin',
        'mo_ta'   => 'Cam kết bảo vệ dữ liệu cá nhân, thông tin đơn hàng và an toàn giao dịch tại Tiệm AI theo Nghị định 13/2023/NĐ-CP.',
        'icon'    => 'fa-lock',
        'mau'     => 'cyan',
        'muc'     => [
            [
                'title' => 'Bên kiểm soát & xử lý dữ liệu',
                'items' => [
                    'Tiệm AI (tiemai.io.vn) là đơn vị kiểm soát và xử lý dữ liệu cá nhân đối với các thông tin bạn cung cấp khi đăng ký tài khoản, đặt mua mã API và liên hệ hỗ trợ.',
                    'Website: https://tiemai.io.vn',
                    'Email liên hệ bảo mật: vankhaqc@gmail.com',
                    'Telegram hỗ trợ: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo / Hotline: 0904045075'
                ]
            ],
            [
                'title' => 'Dữ liệu Tiệm AI thu thập',
                'items' => [
                    '1. Thông tin bạn cung cấp: Họ tên hiển thị, địa chỉ email nhận mã đơn hàng, mật khẩu tài khoản (được mã hóa một chiều bằng chuẩn an toàn bcrypt), số điện thoại và nội dung tin nhắn hỗ trợ khi bạn gửi yêu cầu.',
                    '2. Thông tin kỹ thuật hệ thống tự động ghi nhận: Địa chỉ IP truy cập, nhật ký kết nối API (thời gian gọi, model AI gọi, trạng thái phản hồi HTTP, độ trễ ms) để phục vụ cân bằng tải và giám sát an ninh mạng; cookie phiên đăng nhập và ghi nhớ giỏ hàng.',
                    '3. Thông tin Tiệm AI TUYỆT ĐỐI KHÔNG LƯU TRỮ: Mật khẩu tài khoản ngân hàng, mã OTP, số thẻ tín dụng hoặc thông tin ví tiền cá nhân của bạn. Giao dịch thanh toán VietQR được bạn thực hiện trực tiếp trên ứng dụng ngân hàng của chính bạn.'
                ]
            ],
            [
                'title' => 'Mục đích sử dụng dữ liệu',
                'items' => [
                    'Tạo lập tài khoản, quản lý phiên đăng nhập và xuất kho giao mã API tự động cho khách hàng.',
                    'Gửi email thông báo đơn hàng, cung cấp tài liệu hướng dẫn và thực hiện nghĩa vụ bảo hành, đổi mã khi có sự cố.',
                    'Bảo vệ an ninh hệ thống website: Phát hiện và ngăn chặn các hành vi gian lận thanh toán, tạo đơn ảo hoặc tấn công mạng.',
                    'Đối soát tài chính và thực hiện nghĩa vụ lưu trữ chứng từ theo quy định của pháp luật.'
                ]
            ],
            [
                'title' => 'Cam kết không chia sẻ dữ liệu cho bên thứ ba',
                'items' => [
                    'Tiệm AI cam kết TUYỆT ĐỐI KHÔNG BÁN, không trao đổi và không thương mại hóa dữ liệu cá nhân của khách hàng cho bất kỳ bên thứ ba nào vì mục đích tiếp thị hoặc quảng cáo.',
                    'Dữ liệu chỉ được chia sẻ trong phạm vi kỹ thuật tối thiểu cần thiết: Cổng ngân hàng để đối soát giao dịch VietQR; nhà cung cấp hạ tầng máy chủ bảo mật; hoặc khi có yêu cầu bằng văn bản từ cơ quan nhà nước có thẩm quyền theo luật định.'
                ]
            ],
            [
                'title' => 'Thời gian lưu trữ dữ liệu an toàn',
                'items' => [
                    'Thông tin tài khoản và đơn hàng: Được lưu trữ trong suốt thời gian bạn sử dụng dịch vụ hoặc cho đến khi bạn gửi yêu cầu xóa tài khoản.',
                    'Nhật ký giao dịch tài chính: Lưu trữ 24 tháng để phục vụ đối soát và giải quyết khiếu nại bảo hành.',
                    'Phiên đăng nhập (Session cookie): Được lưu trữ trong 24 giờ hoặc tự động xóa khi bạn bấm đăng xuất.'
                ]
            ],
            [
                'title' => 'Quyền của bạn đối với dữ liệu cá nhân',
                'items' => [
                    'Theo Nghị định 13/2023/NĐ-CP về Bảo vệ dữ liệu cá nhân, bạn có toàn quyền: Được biết, xem, chỉnh sửa, yêu cầu xóa bỏ dữ liệu cá nhân hoặc rút lại sự đồng ý đã cấp trước đó.',
                    'Khi muốn thực hiện các quyền này, bạn chỉ cần gửi email tới vankhaqc@gmail.com hoặc nhắn tin qua Telegram @Vankhateam. Tiệm AI sẽ tiếp nhận và xử lý trong vòng 72 giờ làm việc.'
                ]
            ],
            [
                'title' => 'Thông tin liên hệ phụ trách bảo vệ dữ liệu',
                'items' => [
                    'Mọi thắc mắc, phản ánh hoặc yêu cầu liên quan đến Chính sách bảo mật dữ liệu cá nhân, vui lòng liên hệ:',
                    'Website: https://tiemai.io.vn',
                    'Email phụ trách bảo mật: vankhaqc@gmail.com',
                    'Telegram: @Vankhateam (https://t.me/Vankhateam)',
                    'Zalo: 0904045075 (https://zalo.me/0904045075)'
                ]
            ]
        ]
    ]
];

// Mặc định nếu slug không hợp lệ thì hiển thị Điều khoản sử dụng
if (!isset($chinhSach[$slug])) {
    $slug = 'dieu-khoan-su-dung';
}
$trang = $chinhSach[$slug];
$tenWebsite = 'Tiệm AI';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($trang['tieu_de']) ?> - <?= $tenWebsite ?> (tiemai.io.vn)</title>
    <meta name="description" content="<?= htmlspecialchars($trang['mo_ta']) ?>">
    <link rel="canonical" href="https://tiemai.io.vn/<?= htmlspecialchars($slug) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0B0F19; color: #E5E7EB; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="max-w-6xl mx-auto px-4 py-10 w-full flex-1" x-data="{ dangMoMenu: false }">
        <!-- Tiêu đề trang -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold uppercase tracking-wider mb-2">
                <i class="fas fa-scale-balanced"></i> Trung tâm Pháp Lý Tiệm AI
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1"><?= htmlspecialchars($trang['tieu_de']) ?></h1>
            <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-2xl mx-auto leading-relaxed"><?= htmlspecialchars($trang['mo_ta']) ?></p>
        </div>

        <div class="grid lg:grid-cols-[260px_1fr] gap-6 items-start">
            <!-- Sidebar điều hướng 7 trang pháp lý -->
            <aside class="bg-gray-900/90 border border-gray-800 rounded-3xl p-4 lg:sticky lg:top-24 shadow-xl backdrop-blur-sm">
                <button type="button" @click="dangMoMenu = !dangMoMenu" class="lg:hidden w-full flex items-center justify-between text-sm font-bold text-white mb-0 py-2">
                    <span class="flex items-center gap-2"><i class="fas fa-list text-blue-400"></i> Danh mục pháp lý</span>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform" :class="dangMoMenu ? 'rotate-180' : ''"></i>
                </button>
                <div :class="dangMoMenu ? 'block mt-3' : 'hidden'" class="lg:block space-y-1.5">
                    <div class="text-[11px] font-bold text-gray-500 uppercase px-3 py-1 hidden lg:block tracking-wider">Tài liệu pháp lý</div>
                    <?php foreach ($chinhSach as $ma => $mucChinhSach): ?>
                        <a href="/<?= htmlspecialchars($ma) ?>" 
                           class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-2xl text-xs font-semibold transition-all duration-200 <?= $ma === $slug ? 'bg-blue-600/20 text-blue-300 border border-blue-500/30 font-bold shadow-sm' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' ?>">
                            <i class="fas <?= htmlspecialchars($mucChinhSach['icon']) ?> w-4 text-center <?= $ma === $slug ? 'text-blue-400' : 'text-gray-500' ?>"></i>
                            <span><?= htmlspecialchars($mucChinhSach['nhan']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- Nội dung chi tiết các điều khoản -->
            <article class="space-y-5">
                <?php foreach ($trang['muc'] as $so => $phan): ?>
                    <section class="bg-gray-900/80 border border-gray-800 rounded-3xl p-6 sm:p-7 shadow-xl hover:border-gray-700/60 transition-colors">
                        <div class="flex items-center gap-3 text-<?= htmlspecialchars($trang['mau']) ?>-400 mb-4 pb-3 border-b border-gray-800/80">
                            <span class="w-8 h-8 rounded-xl bg-<?= htmlspecialchars($trang['mau']) ?>-500/10 border border-<?= htmlspecialchars($trang['mau']) ?>-500/20 flex items-center justify-center font-bold text-xs">
                                <?= sprintf('%02d', $so + 1) ?>
                            </span>
                            <h2 class="text-base sm:text-lg font-bold text-white"><?= htmlspecialchars($phan['title']) ?></h2>
                        </div>
                        <ul class="space-y-2.5 text-xs sm:text-sm text-gray-300 leading-relaxed list-disc pl-5">
                            <?php foreach ($phan['items'] as $noiDung): ?>
                                <li><?= htmlspecialchars($noiDung) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>

                <!-- Box liên hệ chính thức của Tiệm AI -->
                <div class="bg-gradient-to-r from-blue-950/70 via-indigo-950/50 to-purple-950/50 border border-blue-800/60 rounded-3xl p-6 sm:p-8 text-center shadow-2xl space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center mx-auto text-blue-400 text-xl">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h2 class="text-base sm:text-lg font-bold text-white">Bạn cần hỗ trợ về pháp lý hoặc giao dịch?</h2>
                    <p class="text-xs text-gray-300 max-w-xl mx-auto leading-relaxed">
                        Đội ngũ hỗ trợ Tiệm AI luôn sẵn sàng tiếp nhận và giải quyết thắc mắc của bạn qua các kênh liên lạc chính thức:
                    </p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-left pt-2 max-w-2xl mx-auto">
                        <div class="bg-gray-900/80 border border-gray-800 p-3.5 rounded-2xl flex items-center gap-3">
                            <i class="fas fa-envelope text-amber-400 text-lg"></i>
                            <div class="overflow-hidden">
                                <div class="text-[10px] text-gray-400 uppercase font-bold">Email chính thức</div>
                                <div class="text-xs font-bold text-white truncate">vankhaqc@gmail.com</div>
                            </div>
                        </div>
                        <a href="https://t.me/Vankhateam" target="_blank" rel="noopener noreferrer" class="bg-gray-900/80 border border-gray-800 hover:border-blue-500/40 p-3.5 rounded-2xl flex items-center gap-3 transition group">
                            <i class="fab fa-telegram text-blue-400 text-lg group-hover:scale-110 transition-transform"></i>
                            <div class="overflow-hidden">
                                <div class="text-[10px] text-gray-400 uppercase font-bold">Telegram hỗ trợ</div>
                                <div class="text-xs font-bold text-white truncate">@Vankhateam</div>
                            </div>
                        </a>
                        <a href="https://zalo.me/0904045075" target="_blank" rel="noopener noreferrer" class="bg-gray-900/80 border border-gray-800 hover:border-blue-500/40 p-3.5 rounded-2xl flex items-center gap-3 transition group">
                            <i class="fas fa-comment-dots text-emerald-400 text-lg group-hover:scale-110 transition-transform"></i>
                            <div class="overflow-hidden">
                                <div class="text-[10px] text-gray-400 uppercase font-bold">Zalo / Hotline</div>
                                <div class="text-xs font-bold text-white truncate">0904045075</div>
                            </div>
                        </a>
                    </div>
                </div>

                <p class="text-[11px] text-gray-500 text-center pt-2">
                    © <?= date('Y') ?> <strong>tiemai.io.vn</strong>. Nội dung chính sách được bảo hộ và cập nhật lần cuối vào ngày <?= date('d/m/Y') ?>.
                </p>
            </article>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
