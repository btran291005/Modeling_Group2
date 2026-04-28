<?php
/**
 * includes/auth.php
 * Quản lý Session và phân quyền truy cập
 * Được include ở ĐẦU mọi file PHP cần bảo vệ
 */

// Khởi động session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // Chặn JS đọc cookie (XSS protection)
        'cookie_samesite' => 'Lax',  // Chống CSRF cơ bản
        'use_strict_mode' => true,   // Session ID nghiêm ngặt
    ]);
}

/**
 * Kiểm tra người dùng đã đăng nhập chưa
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Kiểm tra role có phải Admin không
 * @return bool
 */
function isAdmin(): bool
{
    return isLoggedIn() && $_SESSION['role'] === 'Admin';
}

/**
 * Kiểm tra role có phải Staff không
 * @return bool
 */
function isStaff(): bool
{
    return isLoggedIn() && $_SESSION['role'] === 'Staff';
}

/**
 * Yêu cầu đăng nhập - đá về trang login nếu chưa login
 * Dùng ở đầu mọi trang cần xác thực
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: index.php?reason=unauthenticated');
        exit;
    }
}

/**
 * Yêu cầu quyền Admin - đá về dashboard tương ứng nếu không đủ quyền
 * Dùng ở đầu các trang trong /admin/
 */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        // Staff cố vào trang admin -> redirect về dashboard của Staff
        header('Location: staff/dashboard.php?reason=forbidden');
        exit;
    }
}

/**
 * Yêu cầu quyền Staff - đá về dashboard tương ứng nếu không đủ quyền
 * Dùng ở đầu các trang trong /staff/ (nếu muốn chặn Admin)
 */
function requireStaff(): void
{
    requireLogin();
    if (!isStaff()) {
        // Admin cố vào trang staff -> redirect về dashboard của Admin
        header('Location: admin/dashboard.php');
        exit;
    }
}

/**
 * Lấy thông tin user hiện tại từ Session
 * @return array ['user_id', 'full_name', 'email', 'role']
 */
function getCurrentUser(): array
{
    return [
        'user_id'   => $_SESSION['user_id']   ?? null,
        'full_name' => $_SESSION['full_name']  ?? '',
        'email'     => $_SESSION['email']      ?? '',
        'role'      => $_SESSION['role']       ?? '',
    ];
}

/**
 * Đăng xuất - Huỷ session và redirect về trang login
 */
function logout(): void
{
    // Xoá toàn bộ dữ liệu session
    $_SESSION = [];

    // Xoá cookie session nếu có
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
    header('Location: index.php?reason=logged_out');
    exit;
}