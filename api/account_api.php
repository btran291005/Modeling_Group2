<?php

declare(strict_types=1);

/**
 * api/account_api.php
 *
 * Endpoint xử lý các nghiệp vụ tài khoản.
 * Route: switch($_GET['action'])
 *
 * Public  (không cần đăng nhập): login
 * Private (cần đăng nhập)       : logout, me
 * Admin only                     : list, create, update, toggle_status, reset_password, delete
 */

require_once __DIR__ . '/../config/db_connect.php';      // $pdo
require_once __DIR__ . '/../includes/auth.php';           // isLoggedIn(), requireRole()...
require_once __DIR__ . '/../api/_helpers.php';            // json_ok(), json_err(), post_str()...
require_once __DIR__ . '/../services/AuthService.php';

// Chỉ chấp nhận POST (ngoại trừ action=me có thể GET)
$action = get_action();

// ── Router ────────────────────────────────────────────────────────
switch ($action) {

    // ══════════════════════════════════════════════════════════════
    // case 'login'
    // POST /api/account_api.php?action=login
    // Body JSON: { email, password }
    // ══════════════════════════════════════════════════════════════
    case 'login':
        require_method('POST');

        // Đọc JSON body (login.php gửi fetch với Content-Type: application/json)
        $body     = _parse_json_body();
        $email    = trim($body['email']    ?? post_str('email'));
        $password = trim($body['password'] ?? post_str('password'));

        try {
            $svc  = new AuthService($pdo);
            $user = $svc->login($email, $password);

            // Tái tạo Session ID sau khi xác thực → chống Session Fixation
            session_regenerate_id(true);

            // Lưu thông tin tối thiểu vào session
            $_SESSION['user'] = [
                'user_id'   => $user['user_id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],   // 'Admin' | 'Staff'
            ];

            // Ghi audit log (tách biệt, không ảnh hưởng response nếu lỗi)
            $svc->logLoginSuccess($user['user_id']);

            // Tính redirect URL theo role
            $redirect = match ($user['role']) {
                'Admin' => '/admin/dashboard.php',
                'Staff' => '/staff/dashboard.php',
                default => '/login.php',
            };

            json_ok([
                'redirect'  => $redirect,
                'role'      => $user['role'],
                'full_name' => $user['full_name'],
            ], 'Đăng nhập thành công!');

        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 422);   // Validation error
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 401);   // Auth error
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'logout'
    // POST /api/account_api.php?action=logout
    // ══════════════════════════════════════════════════════════════
    case 'logout':
        require_method('POST');
        apiRequireLogin();

        // Xoá toàn bộ session data
        $_SESSION = [];

        // Xoá cookie phía trình duyệt
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        json_ok(null, 'Đăng xuất thành công.');
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'me'
    // GET /api/account_api.php?action=me
    // Trả về thông tin người dùng đang đăng nhập
    // ══════════════════════════════════════════════════════════════
    case 'me':
        apiRequireLogin();
        json_ok(currentUser());
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'list'
    // GET /api/account_api.php?action=list
    //     &page=1&per_page=15&search=&role=&status=
    // Admin only
    // ══════════════════════════════════════════════════════════════
    case 'list':
        apiRequireAdmin();

        $search   = trim($_GET['search'] ?? '');
        $role     = trim($_GET['role']   ?? '');
        $status   = trim($_GET['status'] ?? '');
        $page     = max(1, (int) ($_GET['page']     ?? 1));
        $perPage  = max(5, min(50, (int) ($_GET['per_page'] ?? 15)));

        // Build WHERE
        $where  = [];
        $params = [];
        if ($search !== '') {
            $where[]           = '(full_name LIKE :s OR email LIKE :s)';
            $params[':s']      = "%{$search}%";
        }
        if (in_array($role, ['Admin', 'Staff'], true)) {
            $where[]           = 'role = :role';
            $params[':role']   = $role;
        }
        if (in_array($status, ['Active', 'Locked'], true)) {
            $where[]           = 'status = :status';
            $params[':status'] = $status;
        }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSQL}");
        $stmtCount->execute($params);
        $total      = (int) $stmtCount->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        // Fetch
        $stmtList = $pdo->prepare(
            "SELECT user_id, full_name, email, role, status, created_at
             FROM   users
             {$whereSQL}
             ORDER  BY created_at DESC
             LIMIT  :lim OFFSET :off"
        );
        foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
        $stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmtList->execute();
        $users = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        json_ok([
            'users'      => $users,
            'pagination' => [
                'total'       => $total,
                'per_page'    => $perPage,
                'current_page'=> $page,
                'total_pages' => $totalPages,
            ],
        ]);
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'create'
    // POST /api/account_api.php?action=create
    // Admin only
    // ══════════════════════════════════════════════════════════════
    case 'create':
        require_method('POST');
        apiRequireAdmin();

        $fullName = post_str('full_name');
        $email    = post_str('email');
        $password = post_str('password');
        $role     = post_str('role', 'Staff');

        // Validate
        $errors = [];
        if (mb_strlen($fullName) < 2)                  $errors[] = 'Họ tên phải có ít nhất 2 ký tự.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
        if (mb_strlen($password) < 6)                  $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        if (!in_array($role, ['Admin', 'Staff'], true)) $errors[] = 'Role không hợp lệ.';
        if ($errors) json_err(implode(' ', $errors), 422);

        // Kiểm tra email trùng
        $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
        $dup->execute([':e' => $email]);
        if ((int) $dup->fetchColumn() > 0) json_err('Email này đã được sử dụng.', 409);

        // Insert
        $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role, status)
             VALUES (:n, :e, :h, :r, 'Active')"
        )->execute([
            ':n' => $fullName,
            ':e' => $email,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
            ':r' => $role,
        ]);

        _audit($pdo, "Tạo tài khoản mới: {$email} (Role: {$role})", 'users');
        json_ok(['user_id' => (int) $pdo->lastInsertId()], "Tạo tài khoản {$email} thành công.");
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'update'
    // POST /api/account_api.php?action=update
    // Admin only
    // ══════════════════════════════════════════════════════════════
    case 'update':
        require_method('POST');
        apiRequireAdmin();

        $userId   = post_int('user_id');
        $fullName = post_str('full_name');
        $email    = post_str('email');
        $role     = post_str('role');

        if ($userId <= 0 || mb_strlen($fullName) < 2
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !in_array($role, ['Admin', 'Staff'], true)) {
            json_err('Dữ liệu không hợp lệ.', 422);
        }

        // Email trùng với user khác
        $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e AND user_id != :id");
        $dup->execute([':e' => $email, ':id' => $userId]);
        if ((int) $dup->fetchColumn() > 0) json_err('Email đã được dùng bởi tài khoản khác.', 409);

        $pdo->prepare(
            "UPDATE users SET full_name = :n, email = :e, role = :r WHERE user_id = :id"
        )->execute([':n' => $fullName, ':e' => $email, ':r' => $role, ':id' => $userId]);

        _audit($pdo, "Cập nhật tài khoản ID#{$userId}: {$email}", 'users');
        json_ok(null, 'Cập nhật tài khoản thành công.');
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'toggle_status'
    // POST /api/account_api.php?action=toggle_status
    // Admin only — khoá / mở khoá tài khoản
    // ══════════════════════════════════════════════════════════════
    case 'toggle_status':
        require_method('POST');
        apiRequireAdmin();

        $userId = post_int('user_id');
        if ($userId <= 0) json_err('user_id không hợp lệ.', 422);

        // Không tự khoá chính mình
        if ($userId === (int) ($_SESSION['user']['user_id'] ?? 0)) {
            json_err('Bạn không thể khoá tài khoản của chính mình.', 403);
        }

        $stmt = $pdo->prepare("SELECT status, email FROM users WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) json_err('Tài khoản không tồn tại.', 404);

        $newStatus = $user['status'] === 'Active' ? 'Locked' : 'Active';
        $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :id")
            ->execute([':s' => $newStatus, ':id' => $userId]);

        $verb = $newStatus === 'Locked' ? 'Khoá' : 'Mở khoá';
        _audit($pdo, "{$verb} tài khoản: {$user['email']}", 'users');

        $msg = $newStatus === 'Locked'
            ? "Đã khoá tài khoản {$user['email']}."
            : "Đã mở khoá tài khoản {$user['email']}.";

        json_ok(['new_status' => $newStatus], $msg);
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'reset_password'
    // POST /api/account_api.php?action=reset_password
    // Admin only
    // ══════════════════════════════════════════════════════════════
    case 'reset_password':
        require_method('POST');
        apiRequireAdmin();

        $userId   = post_int('user_id');
        $password = post_str('new_password');

        if ($userId <= 0)          json_err('user_id không hợp lệ.', 422);
        if (mb_strlen($password) < 6) json_err('Mật khẩu mới phải có ít nhất 6 ký tự.', 422);

        $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) json_err('Tài khoản không tồn tại.', 404);

        $pdo->prepare("UPDATE users SET password_hash = :h WHERE user_id = :id")
            ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);

        _audit($pdo, "Reset mật khẩu cho: {$user['email']}", 'users');
        json_ok(null, "Đã reset mật khẩu cho {$user['email']} thành công.");
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'delete'
    // POST /api/account_api.php?action=delete
    // Admin only — chặn nếu có phiên định giá liên quan
    // ══════════════════════════════════════════════════════════════
    case 'delete':
        require_method('POST');
        apiRequireAdmin();

        $userId = post_int('user_id');
        if ($userId <= 0) json_err('user_id không hợp lệ.', 422);

        if ($userId === (int) ($_SESSION['user']['user_id'] ?? 0)) {
            json_err('Bạn không thể xoá tài khoản của chính mình.', 403);
        }

        $stmt = $pdo->prepare("SELECT email, role FROM users WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) json_err('Tài khoản không tồn tại.', 404);

        // Kiểm tra ràng buộc: có phiên định giá không
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM valuation_sessions WHERE user_id = :id");
        $countStmt->execute([':id' => $userId]);
        $count = (int) $countStmt->fetchColumn();
        if ($count > 0) {
            json_err(
                "Không thể xoá: tài khoản có {$count} phiên định giá liên quan. Hãy khoá thay vì xoá.",
                409
            );
        }

        // Xoá các bản ghi phụ thuộc (không có FK cascade)
        $pdo->prepare("DELETE FROM audit_logs    WHERE user_id = :id")->execute([':id' => $userId]);
        $pdo->prepare("DELETE FROM notifications WHERE user_id = :id")->execute([':id' => $userId]);
        $pdo->prepare("DELETE FROM users         WHERE user_id = :id")->execute([':id' => $userId]);

        _audit($pdo, "Xoá tài khoản: {$user['email']} (Role: {$user['role']})", 'users');
        json_ok(null, "Đã xoá tài khoản {$user['email']} thành công.");
        break;


    // ══════════════════════════════════════════════════════════════
    // default
    // ══════════════════════════════════════════════════════════════
    default:
        $safe = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safe}' không hợp lệ.", 400);
}


// ──────────────────────────────────────────────────────────────────
// PRIVATE HELPERS (dùng trong file này)
// ──────────────────────────────────────────────────────────────────

/**
 * Đọc JSON body từ php://input.
 * Trả về mảng rỗng nếu không parse được.
 */
function _parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Ghi audit log — wrapper nội bộ.
 */
function _audit(PDO $pdo, string $action, string $table): void
{
    try {
        $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, target_table) VALUES (:u, :a, :t)"
        )->execute([
            ':u' => $_SESSION['user']['user_id'] ?? 0,
            ':a' => $action,
            ':t' => $table,
        ]);
    } catch (Throwable) {}
}