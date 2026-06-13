<?php
/**
 * index.php (Thư mục gốc)
 * Vai trò: Router điều hướng luồng truy cập đầu vào
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    // Nếu đã đăng nhập -> chuyển hướng thẳng vào dashboard theo quyền
    $destination = match (currentRole()) {
        'Admin' => '../admin/dashboard.php',
        'Staff' => '../staff/dashboard.php',
        default => 'login.php',
    };
    header('Location: ' . $destination);
    exit;
} else {
    // Nếu chưa đăng nhập -> chuyển hướng sang trang login chuyên dụng
    header('Location: login.php');
    exit;
}