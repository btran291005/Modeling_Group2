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
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// ──────────────────────────────────────────────────────────────────
// BASE URL – hardcode tên thư mục project
// ──────────────────────────────────────────────────────────────────

define('APP_BASE_URL', '/Modeling');

// ──────────────────────────────────────────────────────────────────
// KIỂM TRA TRẠNG THÁI ĐĂNG NHẬP
// ──────────────────────────────────────────────────────────────────

function isLoggedIn(): bool
{
    return isset($_SESSION['user']['user_id'], $_SESSION['user']['role']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function currentRole(): string
{
    return $_SESSION['user']['role'] ?? '';
}

// ──────────────────────────────────────────────────────────────────
// GUARD FUNCTIONS
// ──────────────────────────────────────────────────────────────────

function requireLogin(string $loginUrl = ''): void
{
    if (!isLoggedIn()) {
        $url = $loginUrl !== '' ? $loginUrl : APP_BASE_URL . '/login.php';
        header('Location: ' . $url . '?reason=unauthenticated');
        exit;
    }
}

function requireRole(string $requiredRole): void
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_BASE_URL . '/login.php?reason=unauthenticated');
        exit;
    }

    $actual = currentRole();

    if ($actual !== $requiredRole) {
        $fallback = match ($actual) {
            'Admin' => APP_BASE_URL . '/admin/dashboard.php',
            'Staff' => APP_BASE_URL . '/staff/dashboard.php',
            default => APP_BASE_URL . '/login.php',
        };

        header('HTTP/1.1 403 Forbidden');
        header('Location: ' . $fallback . '?reason=forbidden');
        exit;
    }
}

function requireAdmin(): void
{
    requireRole('Admin');
}

function requireStaff(): void
{
    requireRole('Staff');
}

function requireAnyRole(): void
{
    requireLogin();
}

// ──────────────────────────────────────────────────────────────────
// API GUARD (trả JSON thay vì redirect)
// ──────────────────────────────────────────────────────────────────

function apiRequireLogin(): void
{
    if (!isLoggedIn()) {
        json_err('Bạn chưa đăng nhập.', 401);
    }
}

function apiRequireRole(string $requiredRole): void
{
    apiRequireLogin();

    if (currentRole() !== $requiredRole) {
        json_err('Bạn không có quyền thực hiện thao tác này.', 403);
    }
}

function apiRequireAdmin(): void
{
    apiRequireRole('Admin');
}

function apiRequireStaff(): void
{
    apiRequireRole('Staff');
}