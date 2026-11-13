-- ========================================================
-- CƠ SỞ DỮ LIỆU ĐẦY ĐỦ CHO WEBSITE BÁN API TỰ ĐỘNG
-- Tên CSDL: vankhateamshop
-- Hỗ trợ: Đăng nhập Google, CAPTCHA, Multi-supplier Fallback, Tickets Hỗ Trợ, Mã Giảm Giá, Thông Báo, Nhật Ký
-- Ghi chú code 100% bằng Tiếng Việt dễ hiểu
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `api_logs`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `ticket_replies`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `supplier_product_mappings`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. BẢNG NGƯỜI DÙNG & TÀI KHOẢN (HỖ TRỢ ĐĂNG NHẬP GOOGLE)
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NULL COMMENT 'Mật khẩu mã hóa (NULL nếu đăng nhập bằng Google)',
    `google_id` VARCHAR(100) NULL UNIQUE COMMENT 'Mã định danh duy nhất từ Google OAuth',
    `avatar` VARCHAR(255) NULL COMMENT 'Ảnh đại diện tài khoản',
    `phone` VARCHAR(20) NULL,
    `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Số dư ví VNĐ',
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `api_token` VARCHAR(80) NULL UNIQUE COMMENT 'Key để khách gọi API đại lý',
    `is_reseller` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: Là Đại Lý, 0: Khách Thường',
    `reseller_key` VARCHAR(80) NULL UNIQUE COMMENT 'Khóa API riêng biệt dành riêng cho đại lý',
    `reseller_discount` DECIMAL(5, 2) NOT NULL DEFAULT 5.00 COMMENT 'Chiết khấu % riêng cho đại lý',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG NHÀ CUNG CẤP (KHO NGUỒN CUNG ỨNG API)
CREATE TABLE `suppliers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã nhận diện (vd: supplier_primary, supplier_backup)',
    `name` VARCHAR(100) NOT NULL COMMENT 'Tên hiển thị nhà cung cấp',
    `api_url` VARCHAR(255) NOT NULL COMMENT 'Base URL API của nhà cung cấp',
    `api_key` TEXT NOT NULL COMMENT 'API Key được cấp',
    `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Số dư ví thực tế bên nguồn',
    `min_balance_alert` DECIMAL(15, 2) NOT NULL DEFAULT 200000.00 COMMENT 'Ngưỡng tiền tối thiểu để cảnh báo',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1: Đang bật, 0: Tạm tắt',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG DANH MỤC SẢN PHẨM
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `icon` VARCHAR(255) NULL,
    `color` VARCHAR(20) DEFAULT '#1f2937',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG SẢN PHẨM BÁN TRÊN WEB
CREATE TABLE `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã SKU trên web bạn',
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `image` VARCHAR(255) NULL COMMENT 'Ảnh đại diện gói sản phẩm',
    `badge` VARCHAR(50) NULL COMMENT 'Hot, -23%, Mới...',
    `description` TEXT NULL,
    `price` DECIMAL(15, 2) NOT NULL COMMENT 'Giá bán lẻ cho khách (VNĐ)',
    `original_price` DECIMAL(15, 2) NULL COMMENT 'Giá niêm yết (gạch ngang)',
    `request_count` VARCHAR(100) NULL COMMENT 'Thông số: 700 Request, 200-400 msgs/ngày...',
    `duration_days` INT NOT NULL DEFAULT 30 COMMENT 'Thời hạn tính theo ngày',
    `features` JSON NULL COMMENT 'Mảng tính năng hiển thị',
    `is_hot` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG ÁNH XẠ NGUỒN CUNG CẤP & ĐỊNH TUYẾN FALLBACK
CREATE TABLE `supplier_product_mappings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `supplier_sku` VARCHAR(100) NOT NULL COMMENT 'SKU bên web nguồn đối tác',
    `cost_price` DECIMAL(15, 2) NOT NULL COMMENT 'Giá vốn nhập từ nguồn',
    `stock` INT NOT NULL DEFAULT 999 COMMENT 'Tồn kho từ nguồn',
    `priority` INT NOT NULL DEFAULT 1 COMMENT '1: Ưu tiên mua trước, 2: Mua dự phòng',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BẢNG ĐƠN HÀNG
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã đơn (vd: VK261113001)',
    `user_id` BIGINT UNSIGNED NULL COMMENT 'Null nếu khách mua nhanh không đăng nhập',
    `product_id` INT UNSIGNED NOT NULL,
    `customer_email` VARCHAR(150) NOT NULL COMMENT 'Email khách nhận key',
    `quantity` INT NOT NULL DEFAULT 1,
    `total_amount` DECIMAL(15, 2) NOT NULL COMMENT 'Tổng tiền thanh toán',
    `status` ENUM('PENDING', 'PAID', 'PROCESSING', 'COMPLETED', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    `paid_at` DATETIME NULL COMMENT 'Thời điểm thanh toán thành công',
    `supplier_id` INT UNSIGNED NULL COMMENT 'Nhà cung cấp đã giao đơn này thành công',
    `supplier_order_code` VARCHAR(100) NULL COMMENT 'Mã đơn bên web nguồn',
    `delivered_data` LONGTEXT NULL COMMENT 'JSON thông tin tài khoản / key trả cho khách',
    `error_log` TEXT NULL COMMENT 'Ghi log nếu bị lỗi fallback',
    `ip_address` VARCHAR(45) NULL COMMENT 'Địa chỉ IP thật của khách qua Cloudflare',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG LỊCH SỬ BIẾN ĐỘNG SỐ DƯ VÍ
CREATE TABLE `transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `type` ENUM('DEPOSIT', 'PURCHASE', 'REFUND', 'BONUS') NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `balance_before` DECIMAL(15, 2) NOT NULL,
    `balance_after` DECIMAL(15, 2) NOT NULL,
    `note` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BẢNG CÀI ĐẶT HỆ THỐNG
CREATE TABLE `settings` (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BẢNG MÃ GIẢM GIÁ / COUPON
CREATE TABLE `coupons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã giảm giá (vd: CHAOMUNG10)',
    `discount_type` ENUM('PERCENT', 'FIXED') NOT NULL DEFAULT 'PERCENT',
    `discount_value` DECIMAL(15, 2) NOT NULL COMMENT '10% hoặc 20000đ',
    `min_order_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `max_uses` INT NOT NULL DEFAULT 100,
    `used_count` INT NOT NULL DEFAULT 0,
    `expires_at` TIMESTAMP NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BẢNG VÉ HỖ TRỢ & KHIẾU NẠI BẢO HÀNH (TICKETS)
CREATE TABLE `tickets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_code` VARCHAR(50) NULL COMMENT 'Mã đơn cần bảo hành',
    `title` VARCHAR(255) NOT NULL,
    `status` ENUM('OPEN', 'ANSWERED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    `priority` ENUM('LOW', 'MEDIUM', 'HIGH') NOT NULL DEFAULT 'MEDIUM',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. BẢNG TIN NHẮN TRONG VÉ HỖ TRỢ
CREATE TABLE `ticket_replies` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. BẢNG THÔNG BÁO GHIM NỔI BẬT TRÊN WEB
CREATE TABLE `announcements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `type` ENUM('INFO', 'WARNING', 'SUCCESS', 'DANGER') NOT NULL DEFAULT 'INFO',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. BẢNG NHẬT KÝ HỆ THỐNG (AUDIT LOGS)
CREATE TABLE `system_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `level` ENUM('INFO', 'WARNING', 'ERROR') NOT NULL DEFAULT 'INFO',
    `action` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. BẢNG QUẢN LÝ GỌI API & GIÁM SÁT MODEL (IP, TÊN KHÁCH, EMAIL, MODEL, ĐỘ TRỄ, HTTP, PHẢN HỒI)
CREATE TABLE `api_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `customer_email` VARCHAR(150) NULL,
    `customer_name` VARCHAR(100) NULL,
    `api_key_used` VARCHAR(80) NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `is_https` TINYINT(1) NOT NULL DEFAULT 1,
    `endpoint` VARCHAR(255) NOT NULL,
    `model` VARCHAR(100) NULL,
    `http_status` INT NOT NULL DEFAULT 200,
    `latency_ms` INT NOT NULL DEFAULT 0,
    `request_body` TEXT NULL,
    `response_summary` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`ip_address`),
    INDEX (`model`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. BẢNG NHẬT KÝ WEBHOOK SEPAY (XÁC THỰC HMAC-SHA256 & CHỐNG TRÙNG RACE-SAFE)
CREATE TABLE IF NOT EXISTS `sepay_transactions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `sepay_id` BIGINT NOT NULL UNIQUE COMMENT 'ID giao dịch SePay không đổi qua retry',
    `gateway` VARCHAR(100) NOT NULL COMMENT 'Ngân hàng thực hiện (ACB, Vietcombank, v.v.)',
    `transaction_date` DATETIME NOT NULL,
    `account_number` VARCHAR(100) NULL,
    `sub_account` VARCHAR(250) NULL,
    `code` VARCHAR(250) NULL COMMENT 'Mã thanh toán trích xuất từ nội dung',
    `amount_in` BIGINT NOT NULL DEFAULT 0,
    `amount_out` BIGINT NOT NULL DEFAULT 0,
    `accumulated` BIGINT NOT NULL DEFAULT 0,
    `content` TEXT NULL,
    `reference_code` VARCHAR(255) NULL,
    `body` JSON NOT NULL COMMENT 'Payload gốc phục vụ debug và đối soát',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_code` (`code`),
    INDEX `idx_account` (`account_number`, `transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================
-- DỮ LIỆU KHỞI TẠO HỆ THỐNG
-- ========================================================

-- 1. Tài khoản Quản trị viên mặc định (Mật khẩu: Admin@123456)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `balance`, `role`) VALUES 
('Admin VanKha', 'admin@vankhateamshop.vn', '$2y$12$KkQ1bWqW8g8rC4xQ8Kx2w.m1T0gJ8Ww2zL5q1sY3kP4r8q1w.m1T0', '0987654321', 0.00, 'admin');

-- 2. Cấu hình nhà cung cấp API đối tác
INSERT INTO `suppliers` (`code`, `name`, `api_url`, `api_key`, `balance`, `min_balance_alert`, `is_active`) VALUES
('supplier_primary', 'Nhà Cung Cấp API Chính', 'https://api.nhacungcap.com/v1', 'YOUR_SUPPLIER_API_KEY_HERE', 0.00, 200000.00, 1);

-- 3. Danh mục thực tế
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `color`, `sort_order`) VALUES
(1, 'Cursor API', 'cursor', 'fa-bolt', '#1f2937', 1),
(2, 'Claude API', 'claude', 'fa-brain', '#f97316', 2),
(3, 'Codex API', 'codex', 'fa-code', '#10b981', 3),
(4, 'DeepSeek API', 'deepseek', 'fa-magnifying-glass', '#4d6bfe', 4),
(5, 'Gemini API', 'gemini', 'fa-gem', '#0ea5e9', 5),
(6, 'Grok API', 'grok', 'fa-robot', '#111827', 6),
(7, 'Kimi API', 'kimi', 'fa-moon', '#6366f1', 7),
(8, 'GLM (Zhipu) API', 'zhipu', 'fa-feather', '#a855f7', 8);

-- 4. Toàn bộ các gói API chất lượng cao
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `slug`, `badge`, `description`, `price`, `original_price`, `request_count`, `duration_days`, `features`, `is_hot`) VALUES
(1, 1, 'VK-CUR-30D-700', 'API Cursor 30 Ngày', 'api-cursor-30-ngay-700-req', '-23%', 'Gói cơ bản phù hợp cho cá nhân code hàng ngày', 85000, 110000, '700 Request', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Có giới hạn 700 request", "Bảo hành 30 ngày"]', 1),
(2, 1, 'VK-CUR-30D-1300', 'API Cursor 30 Ngày', 'api-cursor-30-ngay-1300-req', '-20%', 'Gói tiêu chuẩn cho lập trình viên làm việc full-time', 120000, 150000, '1300 Request', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Có giới hạn 1300 request", "Bảo hành 30 ngày"]', 1),
(3, 1, 'VK-CUR-30D-2600', 'API Cursor 30 Ngày', 'api-cursor-30-ngay-2600-req', '-15%', 'Gói nâng cao cho dự án lớn, gọi nhiều code refactor', 170000, 200000, '2600 Request', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Có giới hạn 2600 request", "Bảo hành 30 ngày"]', 1),
(4, 1, 'VK-CUR-30D-6500', 'API Cursor 30 Ngày', 'api-cursor-30-ngay-6500-req', '-15%', 'Gói chuyên sâu cho team nhỏ hoặc dự án chạy liên tục', 230000, 270000, '6500 Request', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Có giới hạn 6500 request", "Bảo hành 30 ngày"]', 0),
(5, 1, 'VK-CUR-30D-13000', 'API Cursor 30 Ngày', 'api-cursor-30-ngay-13000-req', '-13%', 'Gói siêu dung lượng cho lập trình viên chuyên nghiệp', 350000, 400000, '13.000 Request', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Có giới hạn 13.000 request", "Bảo hành 30 ngày"]', 0),
(6, 1, 'VK-CUR-30D-30000', 'API Cursor 30 Ngày', 'api-cursor-30-ngay-30000-req', '-25%', 'Gói doanh nghiệp / agency làm sản phẩm lớn', 750000, 1000000, '30.000 Request', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Có giới hạn 30.000 request", "Bảo hành 30 ngày"]', 0),
(7, 1, 'VK-CUR-1D-RESET', 'API Cursor 1 Ngày', 'api-cursor-1-ngay', '-17%', 'Trải nghiệm thử trong 24 giờ', 25000, 30000, '200 - 400 tin nhắn/ngày (reset 7h AM)', 1, '["Tốc độ cao", "Sử dụng All model và Max", "Không giới hạn request tổng", "Bảo hành trong thời gian sử dụng"]', 1),
(8, 1, 'VK-CUR-3D-RESET', 'API Cursor 3 Ngày', 'api-cursor-3-ngay', '-25%', 'Gói làm task ngắn hạn cuối tuần hoặc sprint', 60000, 80000, '200 - 400 tin nhắn/ngày (reset 7h AM)', 3, '["Tốc độ cao", "Sử dụng All model và Max", "Không giới hạn request tổng", "Bảo hành trong thời gian sử dụng"]', 0),
(9, 1, 'VK-CUR-7D-RESET', 'API Cursor 7 Ngày', 'api-cursor-7-ngay', '-15%', 'Gói 1 tuần làm dự án', 85000, 100000, '200 - 400 tin nhắn/ngày (reset 7h AM)', 7, '["Tốc độ cao", "Sử dụng All model và Max", "Không giới hạn request tổng", "Bảo hành trong thời gian sử dụng"]', 1),
(10, 1, 'VK-CUR-30D-RESET', 'API Cursor 30 Ngày (Reset Ngày)', 'api-cursor-30-ngay-reset-ngay', '-15%', 'Gói tiết kiệm dùng đều đặn mỗi ngày suốt tháng', 230000, 270000, '200 - 400 tin nhắn/ngày (reset 7h AM)', 30, '["Tốc độ cao", "Sử dụng All model và Max", "Không giới hạn request tổng", "Bảo hành trong thời gian sử dụng"]', 1),
(11, 2, 'VK-CLAUDE-35-SONNET', 'API Claude 3.5 Sonnet', 'api-claude-35-sonnet-30-ngay', 'Hot', 'Model lập trình và suy luận logic đỉnh cao nhất hiện nay', 290000, 350000, '15.000 Tokens/Request', 30, '["Hỗ trợ Claude 3.5 Sonnet & Haiku", "Tốc độ phản hồi cực nhanh", "Hỗ trợ Vision đọc ảnh/code", "Chuẩn OpenAI endpoint"]', 1),
(12, 4, 'VK-DEEPSEEK-R1', 'API DeepSeek R1 & V3', 'api-deepseek-r1-v3', 'Mới', 'Model suy luận tư duy mở mã nguồn mở mạnh mẽ', 90000, 130000, 'Không giới hạn TPM', 30, '["Full quyền truy cập DeepSeek-R1", "Khả năng suy luận Chain-of-Thought", "Độ trễ thấp về VN", "Bảo hành 30 ngày"]', 1);

-- 5. Ánh xạ sản phẩm sang nhà cung cấp đối tác
INSERT INTO `supplier_product_mappings` (`product_id`, `supplier_id`, `supplier_sku`, `cost_price`, `stock`, `priority`, `is_active`) VALUES
(1, 1, 'CUR-30D-700', 70000, 100, 1, 1),
(2, 1, 'CUR-30D-1300', 100000, 100, 1, 1),
(3, 1, 'CUR-30D-2600', 145000, 80, 1, 1),
(4, 1, 'CUR-30D-6500', 195000, 50, 1, 1),
(5, 1, 'CUR-30D-13000', 300000, 50, 1, 1),
(6, 1, 'CUR-30D-30000', 650000, 30, 1, 1),
(7, 1, 'CUR-1D-RESET', 18000, 200, 1, 1),
(8, 1, 'CUR-3D-RESET', 45000, 150, 1, 1),
(9, 1, 'CUR-7D-RESET', 68000, 150, 1, 1),
(10, 1, 'CUR-30D-RESET', 190000, 80, 1, 1);

-- 6. Cấu hình hệ thống (Chuẩn bị sẵn cho SePay và Telegram)
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('site_name', 'VanKha Team Shop - API AI Chính Hãng', 'Tên thương hiệu website'),
('site_contact_zalo', '0847154088', 'Số Zalo hỗ trợ khách hàng'),
('site_contact_telegram', 'https://t.me/vankhateam', 'Link kênh Telegram CSKH'),
('sepay_api_key', '', 'API Key tài khoản SePay.vn để nhận tiền tự động'),
('sepay_bank_account', '', 'Số tài khoản ngân hàng nhận tiền'),
('telegram_bot_token', '', 'Bot Token Telegram nhận cảnh báo hết tiền / lỗi đơn'),
('telegram_chat_id', '', 'Chat ID nhận cảnh báo');

-- 7. Mã giảm giá mẫu
INSERT INTO `coupons` (`code`, `discount_type`, `discount_value`, `min_order_amount`, `max_uses`, `is_active`) VALUES
('CHAOMUNG10', 'PERCENT', 10.00, 50000.00, 500, 1),
('GIAM20K', 'FIXED', 20000.00, 100000.00, 200, 1);

-- 8. Thông báo mẫu hiển thị trang chủ
INSERT INTO `announcements` (`title`, `content`, `type`, `is_active`) VALUES
('Chào Mừng Bạn Đến Với VanKha Team Shop', 'Hệ thống tự động giao key 24/7 qua VietQR. Nhập mã CHAOMUNG10 để được giảm ngay 10% cho đơn hàng đầu tiên!', 'SUCCESS', 1);
