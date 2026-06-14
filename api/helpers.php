<?php

declare(strict_types=1);
 
// ──────────────────────────────────────────────────────────────
// 1. RESPONSE HELPERS
// ──────────────────────────────────────────────────────────────
 
/**
 * Trả về JSON thành công và kết thúc script.
 *
 * @param  mixed  $data  Dữ liệu trả về (array, object, scalar, null đều được)
 * @param  string $msg   Thông báo tùy chọn (ví dụ: "Tạo thành công")
 * @param  int    $code  HTTP status code (mặc định 200)
 */
function json_ok(mixed $data = null, string $msg = '', int $code = 200): never
{
    _send_json(['ok' => true, 'data' => $data, 'msg' => $msg], $code);
}
 
/**
 * Trả về JSON lỗi và kết thúc script.
 *
 * @param  string $msg   Thông báo lỗi hiển thị cho client
 * @param  int    $code  HTTP status code (mặc định 400)
 * @param  mixed  $data  Dữ liệu bổ sung (ví dụ: danh sách field lỗi)
 */
function json_err(string $msg, int $code = 400, mixed $data = null): never
{
    _send_json(['ok' => false, 'data' => $data, 'msg' => $msg], $code);
}
 
/**
 * Gửi JSON response thực sự — hàm nội bộ, không gọi từ ngoài.
 */
function _send_json(array $payload, int $code): never
{
    // Xoá toàn bộ output buffer hiện có (notice/warning/whitespace lỡ in ra trước đó)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Đặt header trước khi có bất kỳ output nào
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        // Ngăn cache trên các endpoint có dữ liệu nhạy cảm
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
 
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
 
// ──────────────────────────────────────────────────────────────
// 2. REQUEST HELPERS
// ──────────────────────────────────────────────────────────────
 
/**
 * Lấy 'action' từ GET hoặc POST (GET ưu tiên hơn).
 * Dùng ở đầu switch-case trong mỗi api/*.php.
 *
 * @return string  Ví dụ: "list", "create", "delete"
 */
function get_action(): string
{
    return trim($_GET['action'] ?? $_POST['action'] ?? '');
}
 
/**
 * Lấy giá trị string từ $_POST, trim và fallback về $default.
 */
function post_str(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}
 
/**
 * Lấy giá trị int từ $_POST, fallback về $default.
 */
function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}
 
/**
 * Lấy giá trị float từ $_POST, fallback về $default.
 */
function post_float(string $key, float $default = 0.0): float
{
    return (float) ($_POST[$key] ?? $default);
}
 
/**
 * Lấy mảng int từ $_POST (dùng cho rule_ids[], v.v.)
 *
 * @return int[]
 */
function post_int_array(string $key): array
{
    $raw = $_POST[$key] ?? [];
    return array_map('intval', (array) $raw);
}
 
/**
 * Yêu cầu phương thức HTTP cụ thể; trả lỗi 405 nếu không khớp.
 *
 * @param  string ...$methods  Ví dụ: 'POST', 'GET'
 */
function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $methods, true)) {
        json_err('Method Not Allowed.', 405);
    }
}
 
// ──────────────────────────────────────────────────────────────
// 3. GUARD HELPERS (Auth — phụ thuộc auth.php đã được include)
// ──────────────────────────────────────────────────────────────
 
/**
 * Yêu cầu đăng nhập; trả lỗi 401 nếu chưa login.
 * Gọi SAU khi auth.php đã được include.
 */
function require_auth(): void
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        json_err('Bạn chưa đăng nhập.', 401);
    }
}
 
/**
 * Yêu cầu quyền Admin; trả lỗi 403 nếu không đủ quyền.
 */
function require_admin(): void
{
    require_auth();
    if (!function_exists('isAdmin') || !isAdmin()) {
        json_err('Bạn không có quyền thực hiện thao tác này.', 403);
    }
}
 
/**
 * Yêu cầu role cụ thể; trả lỗi 403 nếu không khớp.
 *
 * @param  string $role  'Admin' | 'Staff'
 */
function require_role(string $role): void
{
    require_auth();
    if (($_SESSION['role'] ?? '') !== $role) {
        json_err('Bạn không có quyền thực hiện thao tác này.', 403);
    }
}
 
// ──────────────────────────────────────────────────────────────
// 4. VALIDATION HELPERS
// ──────────────────────────────────────────────────────────────
 
/**
 * Validate số điện thoại Việt Nam (03x, 05x, 07x, 08x, 09x).
 */
function is_valid_vn_phone(string $phone): bool
{
    return (bool) preg_match('/^(0[35789])[0-9]{8}$/', $phone);
}
 
/**
 * Validate IMEI (đúng 15 chữ số).
 */
function is_valid_imei(string $imei): bool
{
    return (bool) preg_match('/^\d{15}$/', $imei);
}
 
/**
 * Escape HTML để render dữ liệu an toàn (dùng trong view nếu cần).
 */
function esc(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}