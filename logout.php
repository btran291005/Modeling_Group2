<?php
/**
 * logout.php
 * Xử lý đăng xuất - Có thể gọi trực tiếp qua URL hoặc POST
 */
require_once __DIR__ . '/includes/auth.php';

// Gọi hàm logout() trong auth.php - tự xử lý redirect
logout();