<?php
/**
 * api/account.php
 * Backend xử lý TẤT CẢ nghiệp vụ liên quan đến tài khoản:
 *   - login / logout (public)
 *   - get_list       (Admin: lấy danh sách + tìm kiếm + phân trang)
 *   - create         (Admin: tạo tài khoản mới)
 *   - update         (Admin: sửa họ tên, email, role)
 *   - toggle_status  (Admin: khoá / mở khoá tài khoản)
 *   - reset_password (Admin: reset về mật khẩu mới)
 *   - delete         (Admin: xoá tài khoản)
 *
 * Tất cả response là JSON chuẩn: { success, message, data? }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// ---- GET: Staff tra cứu khách hàng theo số điện thoại ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    $action = trim($_GET['action'] ?? '');
    if ($action === 'lookup_customer') {
        $phone = trim($_GET['phone'] ?? '');
        if (empty($phone)) {
            exit(json_encode(['found' => false]));
        }
        $stmt = $pdo->prepare("SELECT full_name FROM customers WHERE phone_number = ? LIMIT 1");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();
        if ($customer) {
            exit(json_encode(['found' => true, 'full_name' => $customer['full_name']]));
        }
        exit(json_encode(['found' => false]));
    }
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Action không hợp lệ.']));
}

// ---- POST: Xử lý các actions bên dưới ----
// Chỉ nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(false, 'Method not allowed.', 405);
}

// Set header JSON ngay từ đầu
header('Content-Type: application/json; charset=utf-8');

$action = trim($_POST['action'] ?? '');

// Phân luồng theo action
match ($action) {
    'login'          => handleLogin(),
    'logout'         => handleLogout(),
    'get_list'       => handleGetList(),
    'create'         => handleCreate(),
    'update'         => handleUpdate(),
    'toggle_status'  => handleToggleStatus(),
    'reset_password' => handleResetPassword(),
    'delete'         => handleDelete(),
    default          => sendJson(false, 'Action không hợp lệ.', 400),
};


/* ============================================================
 * NHÓM 1: AUTH (Public)
 * ============================================================ */

/**
 * Đăng nhập — nhận email + password, trả redirect URL
 */
function handleLogin(): void
{
    global $pdo;

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate cơ bản
    if (empty($email) || empty($password)) {
        sendJson(false, 'Vui lòng nhập đầy đủ Email và Mật khẩu.');
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(false, 'Địa chỉ Email không hợp lệ.');
        return;
    }

    // Truy vấn DB — Prepared Statement chống SQL Injection
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, email, password_hash, role, status
         FROM users
         WHERE email = :email
         LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Kiểm tra tồn tại + mật khẩu
    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJson(false, 'Email hoặc mật khẩu không chính xác.');
        return;
    }

    // Kiểm tra trạng thái tài khoản
    if ($user['status'] === 'Locked') {
        sendJson(false, 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Admin.');
        return;
    }

    // Tái tạo Session ID — chống Session Fixation
    session_regenerate_id(true);

    // Lưu thông tin vào Session
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    // Ghi audit log
    logAudit($pdo, $user['user_id'], "Đăng nhập hệ thống thành công", 'users');

    $redirectUrl = match ($user['role']) {
        'Admin' => 'admin/dashboard.php',
        'Staff' => 'staff/dashboard.php',
        default => 'index.php',
    };

    sendJson(true, 'Đăng nhập thành công! Đang chuyển hướng...', 200, [
        'redirect' => $redirectUrl,
        'role'     => $user['role'],
        'name'     => $user['full_name'],
    ]);
}

/**
 * Đăng xuất
 */
function handleLogout(): void
{
    logout();
}


/* ============================================================
 * NHÓM 2: ACCOUNT MANAGEMENT (Admin only)
 * ============================================================ */

/**
 * Lấy danh sách tài khoản — hỗ trợ search, filter, phân trang
 * POST params: search?, role_filter?, status_filter?, page?, per_page?
 */
function handleGetList(): void
{
    requireAdminApi();
    global $pdo;

    // Tham số đầu vào
    $search       = trim($_POST['search']        ?? '');
    $roleFilter   = trim($_POST['role_filter']   ?? '');
    $statusFilter = trim($_POST['status_filter'] ?? '');
    $page         = max(1, (int) ($_POST['page']     ?? 1));
    $perPage      = min(50, max(5, (int) ($_POST['per_page'] ?? 10)));

    // Xây dựng WHERE động
    $conditions = [];
    $params     = [];

    if ($search !== '') {
        $conditions[] = "(full_name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    if (in_array($roleFilter, ['Admin', 'Staff'], true)) {
        $conditions[] = "role = :role";
        $params[':role'] = $roleFilter;
    }
    if (in_array($statusFilter, ['Active', 'Locked'], true)) {
        $conditions[] = "status = :status";
        $params[':status'] = $statusFilter;
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Đếm tổng bản ghi (để tính tổng trang)
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users {$whereClause}");
    $stmtCount->execute($params);
    $total      = (int) $stmtCount->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Query chính
    $sql = "SELECT user_id, full_name, email, role, status, created_at
            FROM users
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    sendJson(true, 'OK', 200, [
        'users'       => $users,
        'pagination'  => [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total_pages'  => $totalPages,
        ],
    ]);
}

/**
 * Tạo tài khoản mới
 * POST params: full_name, email, password, role
 */
function handleCreate(): void
{
    requireAdminApi();
    global $pdo;

    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']     ?? '');
    $password = trim($_POST['password']  ?? '');
    $role     = trim($_POST['role']      ?? 'Staff');

    // Validate
    $errors = [];
    if (empty($fullName) || mb_strlen($fullName) < 2) {
        $errors[] = 'Họ tên phải có ít nhất 2 ký tự.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Địa chỉ Email không hợp lệ.';
    }
    if (mb_strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if (!in_array($role, ['Admin', 'Staff'], true)) {
        $errors[] = 'Role không hợp lệ.';
    }
    if ($errors) {
        sendJson(false, implode(' ', $errors));
        return;
    }

    // Kiểm tra email đã tồn tại chưa
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmtCheck->execute([':email' => $email]);
    if ((int) $stmtCheck->fetchColumn() > 0) {
        sendJson(false, 'Email này đã được sử dụng. Vui lòng chọn email khác.');
        return;
    }

    // Hash mật khẩu
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert tài khoản mới
    $stmtInsert = $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, role, status)
         VALUES (:full_name, :email, :hash, :role, 'Active')"
    );
    $stmtInsert->execute([
        ':full_name' => $fullName,
        ':email'     => $email,
        ':hash'      => $passwordHash,
        ':role'      => $role,
    ]);
    $newUserId = (int) $pdo->lastInsertId();

    // Ghi audit log
    $actorId = $_SESSION['user_id'];
    logAudit($pdo, $actorId, "Tạo tài khoản mới: {$email} (Role: {$role})", 'users');

    sendJson(true, "Tạo tài khoản {$email} thành công!", 200, [
        'user_id' => $newUserId,
    ]);
}

/**
 * Cập nhật thông tin tài khoản (họ tên, email, role)
 * POST params: user_id, full_name, email, role
 */
function handleUpdate(): void
{
    requireAdminApi();
    global $pdo;

    $userId   = (int) ($_POST['user_id']   ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']     ?? '');
    $role     = trim($_POST['role']      ?? '');

    // Validate
    $errors = [];
    if ($userId <= 0) $errors[] = 'User ID không hợp lệ.';
    if (empty($fullName) || mb_strlen($fullName) < 2) $errors[] = 'Họ tên phải có ít nhất 2 ký tự.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (!in_array($role, ['Admin', 'Staff'], true)) $errors[] = 'Role không hợp lệ.';
    if ($errors) {
        sendJson(false, implode(' ', $errors));
        return;
    }

    // Kiểm tra user tồn tại
    $stmtExist = $pdo->prepare("SELECT user_id FROM users WHERE user_id = :uid");
    $stmtExist->execute([':uid' => $userId]);
    if (!$stmtExist->fetch()) {
        sendJson(false, 'Tài khoản không tồn tại.');
        return;
    }

    // Kiểm tra email duplicate (ngoại trừ chính user này)
    $stmtEmail = $pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :uid"
    );
    $stmtEmail->execute([':email' => $email, ':uid' => $userId]);
    if ((int) $stmtEmail->fetchColumn() > 0) {
        sendJson(false, 'Email này đã được sử dụng bởi tài khoản khác.');
        return;
    }

    // Update
    $stmtUpdate = $pdo->prepare(
        "UPDATE users SET full_name = :name, email = :email, role = :role
         WHERE user_id = :uid"
    );
    $stmtUpdate->execute([
        ':name'  => $fullName,
        ':email' => $email,
        ':role'  => $role,
        ':uid'   => $userId,
    ]);

    // Ghi audit log
    logAudit($pdo, $_SESSION['user_id'], "Cập nhật tài khoản ID#{$userId}: {$email}", 'users');

    sendJson(true, 'Cập nhật tài khoản thành công.');
}

/**
 * Khoá / mở khoá tài khoản (toggle)
 * POST params: user_id
 */
function handleToggleStatus(): void
{
    requireAdminApi();
    global $pdo;

    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        sendJson(false, 'User ID không hợp lệ.');
        return;
    }

    // Không cho phép tự khoá chính mình
    if ($userId === (int) $_SESSION['user_id']) {
        sendJson(false, 'Bạn không thể khoá tài khoản của chính mình.');
        return;
    }

    // Lấy trạng thái hiện tại
    $stmtGet = $pdo->prepare("SELECT status, email FROM users WHERE user_id = :uid");
    $stmtGet->execute([':uid' => $userId]);
    $user = $stmtGet->fetch();

    if (!$user) {
        sendJson(false, 'Tài khoản không tồn tại.');
        return;
    }

    // Toggle status
    $newStatus = $user['status'] === 'Active' ? 'Locked' : 'Active';
    $stmtUpdate = $pdo->prepare(
        "UPDATE users SET status = :status WHERE user_id = :uid"
    );
    $stmtUpdate->execute([':status' => $newStatus, ':uid' => $userId]);

    // Ghi audit log
    $action = $newStatus === 'Locked' ? 'Khóa tài khoản' : 'Mở khóa tài khoản';
    logAudit($pdo, $_SESSION['user_id'], "{$action}: {$user['email']}", 'users');

    $msg = $newStatus === 'Locked'
        ? "Đã khoá tài khoản {$user['email']}."
        : "Đã mở khoá tài khoản {$user['email']}.";

    sendJson(true, $msg, 200, ['new_status' => $newStatus]);
}

/**
 * Reset mật khẩu về giá trị mới do Admin nhập
 * POST params: user_id, new_password
 */
function handleResetPassword(): void
{
    requireAdminApi();
    global $pdo;

    $userId      = (int) ($_POST['user_id']      ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($userId <= 0) {
        sendJson(false, 'User ID không hợp lệ.');
        return;
    }
    if (mb_strlen($newPassword) < 6) {
        sendJson(false, 'Mật khẩu mới phải có ít nhất 6 ký tự.');
        return;
    }

    // Kiểm tra user tồn tại
    $stmtGet = $pdo->prepare("SELECT email FROM users WHERE user_id = :uid");
    $stmtGet->execute([':uid' => $userId]);
    $user = $stmtGet->fetch();
    if (!$user) {
        sendJson(false, 'Tài khoản không tồn tại.');
        return;
    }

    // Hash và update
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmtUpdate = $pdo->prepare(
        "UPDATE users SET password_hash = :hash WHERE user_id = :uid"
    );
    $stmtUpdate->execute([':hash' => $newHash, ':uid' => $userId]);

    // Ghi audit log
    logAudit($pdo, $_SESSION['user_id'], "Reset mật khẩu cho tài khoản: {$user['email']}", 'users');

    sendJson(true, "Đã reset mật khẩu cho {$user['email']} thành công.");
}

/**
 * Xoá tài khoản (Admin only — R03)
 * POST params: user_id
 */
function handleDelete(): void
{
    requireAdminApi();
    global $pdo;

    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        sendJson(false, 'User ID không hợp lệ.');
        return;
    }

    // Không tự xoá chính mình
    if ($userId === (int) $_SESSION['user_id']) {
        sendJson(false, 'Bạn không thể xoá tài khoản của chính mình.');
        return;
    }

    // Kiểm tra tồn tại
    $stmtGet = $pdo->prepare("SELECT email, role FROM users WHERE user_id = :uid");
    $stmtGet->execute([':uid' => $userId]);
    $user = $stmtGet->fetch();
    if (!$user) {
        sendJson(false, 'Tài khoản không tồn tại hoặc đã bị xoá.');
        return;
    }

    // Kiểm tra user có phiên định giá liên quan không
    $stmtCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM valuation_sessions WHERE user_id = :uid"
    );
    $stmtCheck->execute([':uid' => $userId]);
    $sessionCount = (int) $stmtCheck->fetchColumn();

    if ($sessionCount > 0) {
        sendJson(false, "Không thể xoá: tài khoản này có {$sessionCount} phiên định giá liên quan. Hãy khoá tài khoản thay vì xoá.");
        return;
    }

    // Xoá audit logs của user trước (để tránh FK constraint)
    $pdo->prepare("DELETE FROM audit_logs WHERE user_id = :uid")->execute([':uid' => $userId]);
    $pdo->prepare("DELETE FROM notifications WHERE user_id = :uid")->execute([':uid' => $userId]);

    // Xoá user
    $pdo->prepare("DELETE FROM users WHERE user_id = :uid")->execute([':uid' => $userId]);

    // Ghi audit log của Admin
    logAudit($pdo, $_SESSION['user_id'], "Xoá tài khoản: {$user['email']} (Role: {$user['role']})", 'users');

    sendJson(true, "Đã xoá tài khoản {$user['email']} thành công.");
}


/* ============================================================
 * HELPERS
 * ============================================================ */

/**
 * Gửi JSON response chuẩn hoá
 */
function sendJson(bool $success, string $message, int $httpCode = 200, array $data = []): void
{
    http_response_code($httpCode);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Yêu cầu quyền Admin — nếu không đủ quyền trả JSON 403
 */
function requireAdminApi(): void
{
    if (!isAdmin()) {
        sendJson(false, 'Bạn không có quyền thực hiện thao tác này.', 403);
    }
}

/**
 * Ghi audit log vào bảng audit_logs
 */
function logAudit(PDO $pdo, int $userId, string $action, string $targetTable = ''): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, target_table) VALUES (:uid, :action, :table)"
        );
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
            ':table'  => $targetTable,
        ]);
    } catch (Exception $e) {
        // Không để lỗi audit phá vỡ luồng chính
        error_log('[AUDIT ERROR] ' . $e->getMessage());
    }
}