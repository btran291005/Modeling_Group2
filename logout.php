<?php

/**
 * logout.php  (thư mục gốc)
 *
 * Xử lý đăng xuất: Hỗ trợ cả redirect trực tiếp (GET) và AJAX (POST).
 *
 * GET  /logout.php           → phá session, redirect về index.php
 * POST /logout.php           → phá session, trả JSON (cho fetch trong JS)
 */

require_once __DIR__ . '/includes/auth.php';

// ── Phá session ───────────────────────────────────────────────────
_destroySession();

// ── Phân luồng theo phương thức ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX logout
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'msg' => 'Đã đăng xuất.', 'data' => null]);
    exit;
}

// GET logout → redirect về trang login
header('Location: /index.php?reason=logged_out');
exit;


// ─────────────────────────────────────────────────────────────────
// HELPER
// ─────────────────────────────────────────────────────────────────

function _destroySession(): void
{
    // Xoá toàn bộ dữ liệu session
    $_SESSION = [];

    // Xoá cookie session phía trình duyệt
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();
}