<?php
/**
 * index.php (Thư mục gốc)
 * Vai trò: Router điều hướng luồng truy cập đầu vào
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $destination = match (currentRole()) {
        'Admin' => APP_BASE_URL . '/admin/dashboard.php',
        'Staff' => APP_BASE_URL . '/staff/dashboard.php',
        default => APP_BASE_URL . '/login.php',
    };
    header('Location: ' . $destination);
    exit;
} else {
    header('Location: ' . APP_BASE_URL . '/login.php');
    exit;
}