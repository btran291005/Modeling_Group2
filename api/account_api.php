<?php

declare(strict_types=1);

/**
 * api/account_api.php
 *
 * Endpoint xử lý các nghiệp vụ tài khoản.
 * Route: switch(get_action())
 *
 * Public  : login
 * Private : logout, me
 * Admin   : get_list, create, update, toggle_status, reset_password, delete
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../services/auth_service.php';
require_once __DIR__ . '/../services/account_service.php';

$action = get_action();

switch ($action) {

    // ══════════════════════════════════════════════════════════════
    // case 'login'
    // ══════════════════════════════════════════════════════════════
    case 'login':
        require_method('POST');

        $body     = _parse_json_body();
        $email    = trim($body['email']    ?? post_str('email'));
        $password = trim($body['password'] ?? post_str('password'));

        try {
            $authSvc = new AuthService($pdo);
            $user    = $authSvc->login($email, $password);

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'user_id'   => $user['user_id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
            ];

            $authSvc->logLoginSuccess($user['user_id']);

            $redirect = match ($user['role']) {
                'Admin' => APP_BASE_URL . '/admin/dashboard.php',
                'Staff' => APP_BASE_URL . '/staff/dashboard.php',
                default => APP_BASE_URL . '/login.php',
            };

            json_ok([
                'redirect'  => $redirect,
                'role'      => $user['role'],
                'full_name' => $user['full_name'],
            ], 'Đăng nhập thành công!');

        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 401);
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'logout'
    // ══════════════════════════════════════════════════════════════
    case 'logout':
        require_method('POST');
        apiRequireLogin();

        $_SESSION = [];

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
    // ══════════════════════════════════════════════════════════════
    case 'me':
        apiRequireLogin();
        json_ok(currentUser());
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'get_list'
    // ══════════════════════════════════════════════════════════════
    case 'get_list':
        apiRequireAdmin();

        $svc    = new AccountService($pdo);
        $result = $svc->getList(
            post_str('search'),
            post_str('role_filter'),
            post_str('status_filter'),
            max(1, post_int('page', 1)),
            max(5, min(50, post_int('per_page', 10)))
        );

        _send_accounts_json(true, $result['users'], $result['pagination'], '');
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'create'
    // ══════════════════════════════════════════════════════════════
    case 'create':
        require_method('POST');
        apiRequireAdmin();

        $svc = new AccountService($pdo);

        try {
            $userId = $svc->create(
                post_str('full_name'),
                post_str('email'),
                post_str('password'),
                post_str('role', 'Staff')
            );
            _send_accounts_json(true, null, null, 'Tạo tài khoản thành công.');
        } catch (InvalidArgumentException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        } catch (RuntimeException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'update'
    // ══════════════════════════════════════════════════════════════
    case 'update':
        require_method('POST');
        apiRequireAdmin();

        $svc = new AccountService($pdo);

        try {
            $svc->update(
                post_int('user_id'),
                post_str('full_name'),
                post_str('email'),
                post_str('role')
            );
            _send_accounts_json(true, null, null, 'Cập nhật tài khoản thành công.');
        } catch (InvalidArgumentException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        } catch (RuntimeException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'toggle_status'
    // ══════════════════════════════════════════════════════════════
    case 'toggle_status':
        require_method('POST');
        apiRequireAdmin();

        $svc           = new AccountService($pdo);
        $currentUserId = (int) ($_SESSION['user']['user_id'] ?? 0);

        try {
            $result = $svc->toggleStatus(post_int('user_id'), $currentUserId);
            $msg    = $result['new_status'] === 'Locked'
                ? "Đã khoá tài khoản {$result['email']}."
                : "Đã mở khoá tài khoản {$result['email']}.";
            _send_accounts_json(true, null, null, $msg);
        } catch (InvalidArgumentException | RuntimeException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'reset_password'
    // ══════════════════════════════════════════════════════════════
    case 'reset_password':
        require_method('POST');
        apiRequireAdmin();

        $svc = new AccountService($pdo);

        try {
            $email = $svc->resetPassword(post_int('user_id'), post_str('new_password'));
            _send_accounts_json(true, null, null, "Đã reset mật khẩu cho {$email} thành công.");
        } catch (InvalidArgumentException | RuntimeException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // case 'delete'
    // ══════════════════════════════════════════════════════════════
    case 'delete':
        require_method('POST');
        apiRequireAdmin();

        $svc           = new AccountService($pdo);
        $currentUserId = (int) ($_SESSION['user']['user_id'] ?? 0);

        try {
            $email = $svc->delete(post_int('user_id'), $currentUserId);
            _send_accounts_json(true, null, null, "Đã xoá tài khoản {$email} thành công.");
        } catch (InvalidArgumentException | RuntimeException $e) {
            _send_accounts_json(false, null, null, $e->getMessage());
        }
        break;


    // ══════════════════════════════════════════════════════════════
    // default
    // ══════════════════════════════════════════════════════════════
    default:
        _send_accounts_json(false, null, null, 'Action không hợp lệ.');
}


// ──────────────────────────────────────────────────────────────────
// PRIVATE HELPERS
// ──────────────────────────────────────────────────────────────────

function _send_accounts_json(
    bool   $success,
    ?array $users,
    ?array $pagination,
    string $message
): never {
    if (!headers_sent()) {
        http_response_code($success ? 200 : 400);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $payload = ['success' => $success, 'message' => $message];
    if ($users      !== null) $payload['users']      = $users;
    if ($pagination !== null) $payload['pagination'] = $pagination;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function _parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}