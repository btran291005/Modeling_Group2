<?php
/**
 * api/account.php
 * Backend xử lý đăng nhập / đăng xuất
 * Nhận POST request từ form Login (AJAX)
 * Trả về JSON để frontend xử lý redirect
 */

// Khởi tạo session và load các dependency
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// --------Xác định action ----------
$action = $_POST['action'] ?? '';

// Tập trung xử lý theo action
match ($action) {
    'login'  => handleLogin(),
    'logout' => handleLogout(),
    default  => sendJson(false, 'Action không hợp lệ.', 400),
};


/* 
 * FUNCTION: Xử lý đăng nhập
 *  */
function handleLogin(): void
{
    global $pdo;

    // -Lấy và vệ sinh dữ liệu đầu vào ---
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // -Validate cơ bản ---
    if (empty($email) || empty($password)) {
        sendJson(false, 'Vui lòng nhập đầy đủ Email và Mật khẩu.');
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(false, 'Địa chỉ Email không hợp lệ.');
        return;
    }

    // -Truy vấn tài khoản theo email (Prepared Statement - chống SQL Injection) ---
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, email, password_hash, role, status
         FROM users
         WHERE email = :email
         LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // -Kiểm tra tài khoản tồn tại và mật khẩu đúng ---
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Trả về thông báo chung (không tiết lộ email nào tồn tại)
        sendJson(false, 'Email hoặc mật khẩu không chính xác.');
        return;
    }

    // -Kiểm tra trạng thái tài khoản ---
    if ($user['status'] === 'Locked') {
        sendJson(false, 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Admin.');
        return;
    }

    // -Đăng nhập thành công: Tái tạo Session ID (chống Session Fixation) ---
    session_regenerate_id(true);

    // Lưu thông tin vào Session
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    // -Xác định URL redirect dựa theo Role ---
    $redirectUrl = match ($user['role']) {
        'Admin' => 'admin/dashboard.php',
        'Staff' => 'staff/dashboard.php',
        default => 'index.php',
    };

    // Trả về JSON thành công kèm URL redirect cho JS xử lý
    sendJson(true, 'Đăng nhập thành công! Đang chuyển hướng...', 200, [
        'redirect' => $redirectUrl,
        'role'     => $user['role'],
        'name'     => $user['full_name'],
    ]);
}


/* 
 * FUNCTION: Xử lý đăng xuất
 *  */
function handleLogout(): void
{
    logout(); // Hàm trong auth.php tự xử lý redirect
}


/* 
 * HELPER: Gửi JSON response chuẩn hoá
 *  */
function sendJson(bool $success, string $message, int $httpCode = 200, array $data = []): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ), JSON_UNESCAPED_UNICODE);
    exit;
}