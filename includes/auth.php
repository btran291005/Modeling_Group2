<?php

/**
 * includes/auth.php
 *
 * Guard functions cho toàn hệ thống.
 * Include ở ĐẦU mọi file cần bảo vệ, sau session_start().
 *
 * Cấu trúc $_SESSION['user']:
 *   [
 *     'user_id'   => int,
 *     'full_name' => string,
 *     'email'     => string,
 *     'role'      => 'Admin' | 'Staff',
 *   ]
 */

// Khởi động session một lần duy nhất (idempotent)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // Chặn JS đọc cookie → chống XSS
        'cookie_samesite' => 'Lax',  // Chống CSRF cơ bản
        'use_strict_mode' => true,   // Từ chối session ID không hợp lệ
    ]);
}

// ──────────────────────────────────────────────────────────────────
// KIỂM TRA TRẠNG THÁI ĐĂNG NHẬP
// ──────────────────────────────────────────────────────────────────

/**
 * Kiểm tra người dùng đã đăng nhập chưa.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']['user_id'], $_SESSION['user']['role']);
}

/**
 * Lấy thông tin người dùng hiện tại từ session.
 *
 * @return array|null  Trả về null nếu chưa đăng nhập.
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Lấy role của người dùng hiện tại.
 *
 * @return string  'Admin' | 'Staff' | ''
 */
function currentRole(): string
{
    return $_SESSION['user']['role'] ?? '';
}

// ──────────────────────────────────────────────────────────────────
// GUARD FUNCTIONS — Dùng ở đầu mỗi page/endpoint
// ──────────────────────────────────────────────────────────────────

/**
 * Yêu cầu đăng nhập.
 * Chưa đăng nhập → redirect về login.php.
 *
 * @param string $loginUrl  Đường dẫn tương đối về trang login.
 */
function requireLogin(string $loginUrl = '/login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . $loginUrl . '?reason=unauthenticated');
        exit;
    }
}

/**
 * Yêu cầu role cụ thể.
 *
 * Logic:
 *   - Chưa đăng nhập             → redirect về login.php
 *   - Role không khớp (admin)    → redirect về staff/dashboard.php + 403
 *   - Role không khớp (staff)    → redirect về admin/dashboard.php + 403
 *
 * @param string $requiredRole  'Admin' hoặc 'Staff' (case-sensitive theo DB)
 */
function requireRole(string $requiredRole): void
{
    // Bước 1: Phải đăng nhập trước
    if (!isLoggedIn()) {
        header('Location: /login.php?reason=unauthenticated');
        exit;
    }

    $actual = currentRole();

    // Bước 2: Kiểm tra role
    if ($actual !== $requiredRole) {
        // Redirect về dashboard phù hợp với role thực tế của người dùng
        $fallback = match ($actual) {
            'Admin' => '/admin/dashboard.php',
            'Staff' => '/staff/dashboard.php',
            default => '/login.php',
        };

        header('HTTP/1.1 403 Forbidden');
        header('Location: ' . $fallback . '?reason=forbidden');
        exit;
    }
}

/**
 * Shortcut: chỉ cho phép Admin.
 */
function requireAdmin(): void
{
    requireRole('Admin');
}

/**
 * Shortcut: chỉ cho phép Staff.
 */
function requireStaff(): void
{
    requireRole('Staff');
}

/**
 * Shortcut: cho phép cả Admin lẫn Staff (chỉ cần đăng nhập).
 */
function requireAnyRole(): void
{
    requireLogin();
}

// ──────────────────────────────────────────────────────────────────
// HELPER: API Guard (trả JSON thay vì redirect)
// Dùng trong api/*.php thay vì trong views
// ──────────────────────────────────────────────────────────────────

/**
 * Guard cho API endpoint: chưa đăng nhập → trả JSON 401.
 * Yêu cầu api/_helpers.php đã được include trước.
 */
function apiRequireLogin(): void
{
    if (!isLoggedIn()) {
        // json_err() được định nghĩa trong api/_helpers.php
        json_err('Bạn chưa đăng nhập.', 401);
    }
}

/**
 * Guard cho API endpoint: sai role → trả JSON 403.
 *
 * @param string $requiredRole  'Admin' | 'Staff'
 */
function apiRequireRole(string $requiredRole): void
{
    apiRequireLogin();

    if (currentRole() !== $requiredRole) {
        json_err('Bạn không có quyền thực hiện thao tác này.', 403);
    }
}

/**
 * Shorthand apiRequireRole cho Admin.
 */
function apiRequireAdmin(): void
{
    apiRequireRole('Admin');
}

/**
 * Shorthand apiRequireRole cho Staff.
 */
function apiRequireStaff(): void
{
    apiRequireRole('Staff');
}