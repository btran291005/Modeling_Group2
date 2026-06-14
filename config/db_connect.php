<?php
/**
 * config/db_connect.php
 * Kết nối CSDL an toàn qua PDO
 * Trả về object $pdo để sử dụng ở các file khác
 */

// Bật output buffering để các Notice/Warning lỡ in ra không phá JSON response
if (ob_get_level() === 0) {
    ob_start();
}

// --------Thông tin kết nối ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'gadget_valuation');
define('DB_USER', 'root');
define('DB_PASS', '');
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

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'ok'      => false,
        'success' => false,
        'msg'     => 'Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.',
        'message' => 'Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.',
    ]));
}