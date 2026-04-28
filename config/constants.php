<?php
/**
 * config/constants.php
 * Hằng số toàn hệ thống - Smart Gadget Valuation & Inventory
 */

// ============================================================
// PHIÊN BẢN & MÔI TRƯỜNG
// ============================================================
define('APP_NAME',    'Smart Gadget Valuation');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // 'development' | 'production'

// ============================================================
// ĐƯỜNG DẪN
// ============================================================
define('BASE_PATH',    dirname(__DIR__));           // /htdocs/gadget-valuation
define('UPLOAD_PATH',  BASE_PATH . '/uploads/devices/');
define('UPLOAD_URL',   '../uploads/devices/');

// ============================================================
// GIỚI HẠN FILE UPLOAD
// ============================================================
define('UPLOAD_MAX_SIZE_MB', 5);
define('UPLOAD_MAX_SIZE',    UPLOAD_MAX_SIZE_MB * 1024 * 1024);
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ============================================================
// PHÂN TRANG
// ============================================================
define('PER_PAGE_DEFAULT', 15);
define('PER_PAGE_LOG',     20);

// ============================================================
// SESSION
// ============================================================
define('SESSION_TIMEOUT', 3600 * 8); // 8 giờ

// ============================================================
// GEMINI AI
// ============================================================
define('GEMINI_MODEL',   'gemini-2.0-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

// ============================================================
// NGHIỆP VỤ
// ============================================================
define('BATTERY_DEDUCTION_THRESHOLD', 80);  // Pin < 80% bắt đầu trừ thêm
define('BATTERY_DEDUCTION_PER_PERCENT', 0.3); // Mỗi % dưới ngưỡng trừ 0.3%

// Giá sàn tối thiểu (bảo vệ khỏi đề xuất giá quá thấp)
define('MIN_SUGGESTED_PRICE', 100000); // 100.000 VNĐ

// ============================================================
// ENUM LABELS (dùng hiển thị giao diện)
// ============================================================
define('SESSION_STATUS_LABELS', [
    'Pending'   => 'Đang chờ',
    'Purchased' => 'Đã mua',
    'Declined'  => 'Từ chối',
]);

define('GADGET_STATUS_LABELS', [
    'Stored'       => 'Trong kho',
    'Refurbishing' => 'Đang tân trang',
    'Sold'         => 'Đã bán',
]);

define('USER_STATUS_LABELS', [
    'Active' => 'Hoạt động',
    'Locked' => 'Đã khóa',
]);