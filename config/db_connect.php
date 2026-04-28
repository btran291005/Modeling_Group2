<?php
/**
 * config/db_connect.php
 * Kết nối CSDL an toàn qua PDO
 * Trả về object $pdo để sử dụng ở các file khác
 */

// --------Thông tin kết nối ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'gadget_valuation');
define('DB_USER', 'root');       // Thay theo môi trường thực tế
define('DB_PASS', '');           // Thay theo môi trường thực tế
define('DB_CHARSET', 'utf8mb4');

// --------DSN (Data Source Name) ----------
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_NAME,
    DB_CHARSET
);

// --------Tùy chọn PDO an toàn ----------
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Ném Exception khi lỗi
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Trả về mảng kết hợp
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Dùng Prepared Statement thật
];

// --------Khởi tạo kết nối ----------
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    // Không lộ chi tiết lỗi ra ngoài (bảo mật)
    error_log('[DB ERROR] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.'
    ]));
}