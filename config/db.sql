-- 
-- Smart Gadget Valuation & Inventory - Database Schema
-- Chuẩn 3NF | MySQL 8.0+
-- 

CREATE DATABASE IF NOT EXISTS gadget_valuation
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gadget_valuation;

-- 
-- 1. BẢNG USERS - Tài khoản người dùng hệ thống
-- 
CREATE TABLE users (
    user_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role       ENUM('Admin','Staff') NOT NULL DEFAULT 'Staff',
    status     ENUM('Active','Locked') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 
-- 2. BẢNG BRANDS - Hãng sản xuất thiết bị
-- 
CREATE TABLE brands (
    brand_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 
-- 3. BẢNG DEVICE_MODELS - Mẫu thiết bị cụ thể
-- 
CREATE TABLE device_models (
    model_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id   INT UNSIGNED NOT NULL,
    model_name VARCHAR(120) NOT NULL,
    ram_gb     TINYINT UNSIGNED NOT NULL,
    rom_gb     SMALLINT UNSIGNED NOT NULL,
    base_price INT UNSIGNED NOT NULL COMMENT 'Giá cơ sở (VNĐ, lưu dạng nguyên)',
    FOREIGN KEY (brand_id) REFERENCES brands(brand_id)
) ENGINE=InnoDB;

-- 
-- 4. BẢNG AI_PRICING_RULES - Quy tắc định giá của AI
-- 
CREATE TABLE ai_pricing_rules (
    rule_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condition_name   VARCHAR(150) NOT NULL,
    deduction_percent DECIMAL(5,2) NOT NULL COMMENT 'Phần trăm trừ vào giá cơ sở',
    is_active        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 
-- 5. BẢNG CUSTOMERS - Khách hàng bán thiết bị
-- 
CREATE TABLE customers (
    customer_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15)  NOT NULL UNIQUE,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 
-- 6. BẢNG VALUATION_SESSIONS - Phiên định giá / thu mua
-- 
CREATE TABLE valuation_sessions (
    session_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL COMMENT 'Staff thực hiện định giá',
    model_id          INT UNSIGNED NOT NULL,
    customer_id       INT UNSIGNED NULL COMMENT 'NULL nếu chưa chốt thu mua',
    battery_health    TINYINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Phần trăm chai pin (0-100)',
    ai_suggested_price INT UNSIGNED NULL COMMENT 'Giá AI đề xuất (VNĐ)',
    final_status      ENUM('Pending','Purchased','Declined') NOT NULL DEFAULT 'Pending',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (model_id) REFERENCES device_models(model_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
) ENGINE=InnoDB;

-- 
-- 7. BẢNG SESSION_RULE_DETAILS - Bảng N-N: Phiên <-> Quy tắc AI
-- 
CREATE TABLE session_rule_details (
    session_id INT UNSIGNED NOT NULL,
    rule_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (session_id, rule_id),
    FOREIGN KEY (session_id) REFERENCES valuation_sessions(session_id),
    FOREIGN KEY (rule_id)    REFERENCES ai_pricing_rules(rule_id)
) ENGINE=InnoDB;

-- 
-- 8. BẢNG DEVICE_IMAGES - Ảnh chụp thiết bị
-- 
CREATE TABLE device_images (
    image_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    image_url  VARCHAR(300) NOT NULL COMMENT 'Đường dẫn vật lý trong /uploads/devices/',
    FOREIGN KEY (session_id) REFERENCES valuation_sessions(session_id)
) ENGINE=InnoDB;

-- 
-- 9. BẢNG GADGETS - Thiết bị đã nhập kho
-- 
CREATE TABLE gadgets (
    imei       VARCHAR(20) PRIMARY KEY COMMENT 'IMEI 15 chữ số',
    session_id INT UNSIGNED NOT NULL UNIQUE COMMENT 'Mỗi phiên chỉ nhập 1 máy',
    status     ENUM('Stored','Refurbishing','Sold') NOT NULL DEFAULT 'Stored',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES valuation_sessions(session_id)
) ENGINE=InnoDB;

-- 
-- 10. BẢNG NOTIFICATIONS - Thông báo trong hệ thống
-- 
CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 
-- 11. BẢNG AUDIT_LOGS - Nhật ký hành động hệ thống
-- 
CREATE TABLE audit_logs (
    log_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    action       TEXT NOT NULL,
    target_table VARCHAR(80) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;


-- 
-- MOCK DATA - Dữ liệu mẫu để test
-- Mật khẩu chung: '123456' | Hash: password_hash('123456')
-- 

-- ---- USERS (12 tài khoản) ----
-- Password hash của '123456' dùng PASSWORD_BCRYPT
INSERT INTO users (full_name, email, password_hash, role, status) VALUES
('Super Admin',      'admin@test.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active'),
('Nguyen Van Staff', 'staff@test.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Tran Thi Lan',     'lan.tran@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Le Van Duc',       'duc.le@test.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Pham Thi Hoa',     'hoa.pham@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Hoang Minh Tuan',  'tuan.hm@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Vo Thi Mai',       'mai.vo@test.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Locked'),
('Dang Van Khanh',   'khanh.dv@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active'),
('Nguyen Thi Bich',  'bich.nt@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Tran Van Phong',   'phong.tv@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Do Thi Thanh',     'thanh.dt@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active'),
('Ly Van Nam',       'nam.lv@test.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active');

-- ---- BRANDS (6 hãng) ----
INSERT INTO brands (brand_name) VALUES
('Apple'), ('Samsung'), ('Xiaomi'), ('OPPO'), ('Vivo'), ('Realme');

-- ---- DEVICE_MODELS (20 mẫu) ----
INSERT INTO device_models (brand_id, model_name, ram_gb, rom_gb, base_price) VALUES
(1, 'iPhone 15 Pro Max', 8,  256, 28000000),
(1, 'iPhone 15',         6,  128, 19000000),
(1, 'iPhone 14 Pro',     6,  256, 22000000),
(1, 'iPhone 13',         4,  128, 14000000),
(1, 'iPhone 12',         4,  64,  10000000),
(2, 'Samsung Galaxy S24 Ultra', 12, 256, 26000000),
(2, 'Samsung Galaxy S23',       8,  128, 16000000),
(2, 'Samsung Galaxy A55',       8,  256, 9000000),
(2, 'Samsung Galaxy A35',       6,  128, 7000000),
(2, 'Samsung Galaxy M55',       8,  256, 8500000),
(3, 'Xiaomi 14 Pro',    12, 256, 17000000),
(3, 'Xiaomi Redmi Note 13 Pro', 8, 256, 7500000),
(3, 'Xiaomi POCO X6 Pro', 12, 256, 9000000),
(4, 'OPPO Find X7',     12, 256, 18000000),
(4, 'OPPO Reno 12 Pro',  12, 256, 12000000),
(4, 'OPPO A79',          8,  256, 6000000),
(5, 'Vivo V30 Pro',     12, 256, 11000000),
(5, 'Vivo Y38',          8,  256, 5500000),
(6, 'Realme 12 Pro+',    12, 256, 9500000),
(6, 'Realme C65',        8,  256, 4500000);

-- ---- AI_PRICING_RULES (10 quy tắc) ----
INSERT INTO ai_pricing_rules (condition_name, deduction_percent, is_active) VALUES
('Màn hình bể/vỡ góc',              25.00, 1),
('Màn hình bám bóng/ố vàng',        10.00, 1),
('Vỏ máy trầy xước nặng',           15.00, 1),
('Vỏ máy móp/bẹp nhẹ',             12.00, 1),
('Pin chai dưới 80%',               10.00, 1),
('Pin chai dưới 60%',               20.00, 1),
('Camera sau mờ/vỡ kính',          18.00, 1),
('Loa/mic hoạt động không tốt',     8.00, 1),
('Face ID / Touch ID hỏng',         22.00, 1),
('Máy đã unlock/mở mạng không rõ nguồn gốc', 30.00, 0);

-- ---- CUSTOMERS (20 khách hàng) ----
INSERT INTO customers (full_name, phone_number) VALUES
('Nguyen Van An',    '0901234561'),
('Tran Thi Binh',   '0912345672'),
('Le Van Cuong',    '0923456783'),
('Pham Thi Dung',   '0934567894'),
('Hoang Van Em',    '0945678905'),
('Vu Thi Phuong',   '0956789016'),
('Bui Van Giang',   '0967890127'),
('Ngo Thi Huong',   '0978901238'),
('Do Van Ich',      '0989012349'),
('Dang Thi Kim',    '0990123450'),
('Cao Van Long',    '0901230001'),
('Dinh Thi My',     '0912340002'),
('Truong Van Nam',  '0923450003'),
('Ha Thi Oanh',     '0934560004'),
('Luu Van Phuc',    '0945670005'),
('Tong Thi Quynh',  '0956780006'),
('Mac Van Rung',    '0967890007'),
('Kiem Thi Sen',    '0978900008'),
('Au Van Truong',   '0989010009'),
('Quach Thi Uyen',  '0990120010');

-- ---- VALUATION_SESSIONS (20 phiên) ----
INSERT INTO valuation_sessions (user_id, model_id, customer_id, battery_health, ai_suggested_price, final_status, created_at) VALUES
(2,  1,  1,  85, 23000000, 'Purchased', '2025-06-01 09:15:00'),
(3,  6,  2,  78, 20500000, 'Purchased', '2025-06-01 10:30:00'),
(4,  2,  3,  92, 17200000, 'Purchased', '2025-06-02 08:45:00'),
(2,  11, 4,  65, 11000000, 'Purchased', '2025-06-02 14:20:00'),
(5,  7,  5,  88, 13500000, 'Purchased', '2025-06-03 09:00:00'),
(3,  3,  6,  72, 17800000, 'Purchased', '2025-06-03 11:15:00'),
(6,  14, 7,  95, 16500000, 'Purchased', '2025-06-04 09:30:00'),
(4,  8,  8,  80, 7200000,  'Purchased', '2025-06-04 13:45:00'),
(2,  4,  9,  55, 9800000,  'Purchased', '2025-06-05 10:00:00'),
(9,  17, 10, 90, 9500000,  'Purchased', '2025-06-05 15:00:00'),
(10, 5,  11, 68, 7100000,  'Purchased', '2025-06-06 09:00:00'),
(3,  12, 12, 85, 6200000,  'Purchased', '2025-06-06 11:30:00'),
(5,  19, 13, 77, 7300000,  'Purchased', '2025-06-07 10:00:00'),
(6,  15, 14, 60, 8200000,  'Declined',  '2025-06-07 14:00:00'),
(4,  9,  NULL, 95, 6300000, 'Pending',  '2025-06-08 09:00:00'),
(2,  13, NULL, 82, 7600000, 'Pending',  '2025-06-08 11:00:00'),
(9,  16, 15, 73, 4500000,  'Purchased', '2025-06-09 09:30:00'),
(10, 20, 16, 88, 3800000,  'Purchased', '2025-06-09 14:00:00'),
(11, 18, 17, 91, 4700000,  'Purchased', '2025-06-10 10:00:00'),
(12, 10, 18, 76, 6800000,  'Declined',  '2025-06-10 15:30:00');

-- ---- SESSION_RULE_DETAILS (áp dụng quy tắc cho các phiên) ----
INSERT INTO session_rule_details (session_id, rule_id) VALUES
(1,  3), (1,  5),
(2,  1), (2,  7),
(3,  2),
(4,  5), (4,  6),
(5,  3),
(6,  4), (6,  8),
(7,  2),
(8,  3), (8,  5),
(9,  5), (9,  6), (9,  9),
(10, 2),
(11, 5), (11, 8),
(12, 3),
(13, 4), (13, 5),
(14, 1), (14, 9),
(15, 2),
(17, 5), (17, 8),
(18, 3),
(19, 2),
(20, 4), (20, 5);

-- ---- DEVICE_IMAGES (ảnh mẫu cho các phiên) ----
INSERT INTO device_images (session_id, image_url) VALUES
(1,  'uploads/devices/session_1_front.jpg'),
(1,  'uploads/devices/session_1_back.jpg'),
(2,  'uploads/devices/session_2_front.jpg'),
(2,  'uploads/devices/session_2_back.jpg'),
(3,  'uploads/devices/session_3_front.jpg'),
(4,  'uploads/devices/session_4_front.jpg'),
(4,  'uploads/devices/session_4_back.jpg'),
(5,  'uploads/devices/session_5_front.jpg'),
(6,  'uploads/devices/session_6_front.jpg'),
(6,  'uploads/devices/session_6_back.jpg'),
(7,  'uploads/devices/session_7_front.jpg'),
(8,  'uploads/devices/session_8_front.jpg'),
(9,  'uploads/devices/session_9_front.jpg'),
(9,  'uploads/devices/session_9_back.jpg'),
(10, 'uploads/devices/session_10_front.jpg'),
(11, 'uploads/devices/session_11_front.jpg'),
(12, 'uploads/devices/session_12_front.jpg'),
(13, 'uploads/devices/session_13_front.jpg'),
(17, 'uploads/devices/session_17_front.jpg'),
(19, 'uploads/devices/session_19_front.jpg');

-- ---- GADGETS (thiết bị đã nhập kho - chỉ session Purchased) ----
INSERT INTO gadgets (imei, session_id, status) VALUES
('352001234567890', 1,  'Stored'),
('352001234567891', 2,  'Refurbishing'),
('352001234567892', 3,  'Stored'),
('352001234567893', 4,  'Sold'),
('352001234567894', 5,  'Stored'),
('352001234567895', 6,  'Refurbishing'),
('352001234567896', 7,  'Stored'),
('352001234567897', 8,  'Stored'),
('352001234567898', 9,  'Sold'),
('352001234567899', 10, 'Stored'),
('352001234567900', 11, 'Stored'),
('352001234567901', 12, 'Refurbishing'),
('352001234567902', 13, 'Stored'),
('352001234567903', 17, 'Stored'),
('352001234567904', 18, 'Stored'),
('352001234567905', 19, 'Stored');

-- ---- NOTIFICATIONS (20 thông báo) ----
INSERT INTO notifications (user_id, message, is_read, created_at) VALUES
(1, 'Phiên định giá #1 đã được chốt thành công.', 1, '2025-06-01 09:20:00'),
(1, 'Phiên định giá #2 đã được chốt thành công.', 1, '2025-06-01 10:35:00'),
(2, 'Phiên định giá #1 của bạn đã được xác nhận.', 1, '2025-06-01 09:20:00'),
(1, 'Tài khoản mai.vo@test.com đã bị khóa.', 0, '2025-06-02 08:00:00'),
(3, 'Phiên định giá #2 của bạn đã được xác nhận.', 1, '2025-06-01 10:35:00'),
(1, 'Thiết bị IMEI 352001234567893 đã được bán.', 0, '2025-06-03 10:00:00'),
(4, 'Phiên định giá #3 của bạn đã được xác nhận.', 0, '2025-06-02 08:50:00'),
(1, 'Quy tắc AI mới đã được thêm vào hệ thống.', 1, '2025-06-02 09:00:00'),
(2, 'Phiên định giá #4 của bạn đã được xác nhận.', 1, '2025-06-02 14:25:00'),
(1, 'Có 2 phiên định giá đang ở trạng thái Pending.', 0, '2025-06-08 12:00:00'),
(5, 'Phiên định giá #5 của bạn đã được xác nhận.', 0, '2025-06-03 09:05:00'),
(1, 'Thiết bị IMEI 352001234567898 đã được bán.', 0, '2025-06-05 11:00:00'),
(6, 'Phiên định giá #7 của bạn đã được xác nhận.', 0, '2025-06-04 09:35:00'),
(9, 'Phiên định giá #10 của bạn đã được xác nhận.', 0, '2025-06-05 15:05:00'),
(1, 'Hệ thống đã hoạt động ổn định trong 30 ngày.', 0, '2025-06-10 08:00:00'),
(10, 'Phiên định giá #11 của bạn đã được xác nhận.', 0, '2025-06-06 09:05:00'),
(2, 'Phiên định giá #16 đang chờ xử lý.', 0, '2025-06-08 11:05:00'),
(1, 'Báo cáo tháng 6 sẵn sàng để xem.', 0, '2025-06-11 08:00:00'),
(11, 'Phiên định giá #19 của bạn đã được xác nhận.', 0, '2025-06-10 10:05:00'),
(12, 'Phiên định giá #20 đã bị từ chối.', 0, '2025-06-10 15:35:00');

-- ---- AUDIT_LOGS (20 bản ghi) ----
INSERT INTO audit_logs (user_id, action, target_table, created_at) VALUES
(1,  'Tạo tài khoản mới: lan.tran@test.com',         'users',              '2025-05-28 09:00:00'),
(1,  'Tạo tài khoản mới: duc.le@test.com',           'users',              '2025-05-28 09:05:00'),
(1,  'Thêm quy tắc AI: Màn hình bể/vỡ góc (25%)',    'ai_pricing_rules',   '2025-05-30 10:00:00'),
(1,  'Thêm mẫu thiết bị: iPhone 15 Pro Max',         'device_models',      '2025-05-30 10:30:00'),
(2,  'Tạo phiên định giá #1 cho iPhone 15 Pro Max',  'valuation_sessions', '2025-06-01 09:15:00'),
(2,  'Chốt thu mua phiên #1, khách: Nguyen Van An',  'valuation_sessions', '2025-06-01 09:20:00'),
(3,  'Tạo phiên định giá #2 cho Galaxy S24 Ultra',   'valuation_sessions', '2025-06-01 10:30:00'),
(3,  'Chốt thu mua phiên #2, khách: Tran Thi Binh',  'valuation_sessions', '2025-06-01 10:35:00'),
(1,  'Khóa tài khoản: mai.vo@test.com',              'users',              '2025-06-02 08:00:00'),
(4,  'Tạo phiên định giá #3 cho iPhone 15',          'valuation_sessions', '2025-06-02 08:45:00'),
(1,  'Thêm hãng mới: Realme',                        'brands',             '2025-06-02 09:00:00'),
(2,  'Nhập kho thiết bị IMEI: 352001234567890',      'gadgets',            '2025-06-01 09:25:00'),
(1,  'Cập nhật giá cơ sở iPhone 14 Pro',             'device_models',      '2025-06-03 11:00:00'),
(6,  'Tạo phiên định giá #7 cho OPPO Find X7',       'valuation_sessions', '2025-06-04 09:30:00'),
(1,  'Vô hiệu hóa quy tắc AI: Máy unlock',           'ai_pricing_rules',   '2025-06-04 14:00:00'),
(9,  'Tạo phiên định giá #10 cho Vivo V30 Pro',      'valuation_sessions', '2025-06-05 15:00:00'),
(2,  'Cập nhật trạng thái máy 352001234567893: Sold','gadgets',            '2025-06-05 11:00:00'),
(1,  'Thêm mẫu thiết bị: Realme C65',                'device_models',      '2025-06-06 08:00:00'),
(5,  'Từ chối phiên định giá #14',                   'valuation_sessions', '2025-06-07 14:05:00'),
(1,  'Xuất báo cáo tháng 6/2025',                    'audit_logs',         '2025-06-11 08:00:00');