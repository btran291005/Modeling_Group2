<?php

/**
 * index.php  (thư mục gốc)
 *
 * Hai vai trò:
 *   1. Router: Nếu đã đăng nhập → chuyển hướng đúng dashboard theo role.
 *   2. Login page: Nếu chưa đăng nhập → render form đăng nhập.
 */

require_once __DIR__ . '/includes/auth.php';

// ── 1. ROUTER: đã đăng nhập thì chuyển hướng ngay ────────────────
if (isLoggedIn()) {
    $destination = match (currentRole()) {
        'Admin' => '/admin/dashboard.php',
        'Staff' => '/staff/dashboard.php',
        default => '/login.php',          // role lạ → đẩy về login
    };
    header('Location: ' . $destination);
    exit;
}

// ── 2. LOGIN PAGE ─────────────────────────────────────────────────
$reason = htmlspecialchars($_GET['reason'] ?? '', ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — Smart Gadget</title>
    <link rel="stylesheet" href="/assets/css/general.css">
    <link rel="stylesheet" href="/assets/css/pages/login.css">
</head>
<body class="login-page">

    <div class="login-visual">
        <div class="visual-content">
            <div class="visual-logo">
                <div class="logo-icon">⚡</div>
                <span class="logo-text">Smart<span>Gadget</span></span>
            </div>
            <h2 class="visual-headline">Định giá thông minh<br>với <em>AI tích hợp</em></h2>
            <p class="visual-desc">
                Hệ thống thu mua thiết bị điện tử cũ.
                Tự động định giá bằng Gemini AI, phân quyền Admin / Staff rõ ràng.
            </p>
            <div class="feature-pills">
                <span class="feature-pill">⚡ AI định giá tự động</span>
                <span class="feature-pill">📦 Quản lý kho hàng</span>
                <span class="feature-pill">🔐 Phân quyền 2 cấp</span>
                <span class="feature-pill">📋 Audit log đầy đủ</span>
            </div>
        </div>
    </div>

    <div class="login-form-panel">
        <div class="login-form-container">

            <div class="form-header">
                <div class="form-header-eyebrow">Hệ thống nội bộ</div>
                <h1>Chào mừng trở lại</h1>
                <p>Đăng nhập để tiếp tục công việc</p>
            </div>

            <?php if ($reason === 'unauthenticated'): ?>
                <div class="login-alert" id="server-alert">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Bạn cần đăng nhập để tiếp tục.
                </div>
            <?php elseif ($reason === 'logged_out'): ?>
                <div class="login-alert login-alert--success" id="server-alert">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Bạn đã đăng xuất thành công.
                </div>
            <?php elseif ($reason === 'forbidden'): ?>
                <div class="login-alert login-alert--warning" id="server-alert">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Bạn không có quyền truy cập trang đó.
                </div>
            <?php endif; ?>

            <!-- AJAX alert -->
            <div class="login-alert hidden" id="ajax-alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span id="ajax-alert-msg"></span>
            </div>

            <form id="login-form" novalidate>

                <div class="form-group">
                    <label class="form-label" for="email">Địa chỉ Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input class="form-input" type="email" id="email"
                               placeholder="example@domain.com" autocomplete="email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input class="form-input" type="password" id="password"
                               placeholder="Mật khẩu của bạn" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" id="toggle-pwd" aria-label="Hiện/ẩn mật khẩu">
                            <svg id="eye-on" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg id="eye-off" class="hidden" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btn-login">
                    <span class="btn-text">Đăng nhập</span>
                    <div class="spinner"></div>
                </button>

            </form>

            <!-- Demo credentials (chỉ dev) -->
            <div class="demo-credentials">
                <h4>🔑 Tài khoản demo (mật khẩu: 123456)</h4>
                <div class="demo-item" data-email="admin@test.com" data-password="123456">
                    <span class="demo-role">👑 Admin</span>
                    <span class="demo-email">admin@test.com</span>
                    <span class="demo-hint">Click để điền</span>
                </div>
                <div class="demo-item" data-email="staff@test.com" data-password="123456">
                    <span class="demo-role">👤 Staff</span>
                    <span class="demo-email">staff@test.com</span>
                    <span class="demo-hint">Click để điền</span>
                </div>
            </div>

        </div>
    </div>

</body>
<script>
(function () {
    'use strict';

    const emailEl   = document.getElementById('email');
    const passEl    = document.getElementById('password');
    const form      = document.getElementById('login-form');
    const btnLogin  = document.getElementById('btn-login');
    const ajaxAlert = document.getElementById('ajax-alert');
    const ajaxMsg   = document.getElementById('ajax-alert-msg');
    const togglePwd = document.getElementById('toggle-pwd');
    const eyeOn     = document.getElementById('eye-on');
    const eyeOff    = document.getElementById('eye-off');

    // ---- Toggle hiện / ẩn mật khẩu ----
    togglePwd.addEventListener('click', () => {
        const hidden = passEl.type === 'password';
        passEl.type  = hidden ? 'text' : 'password';
        eyeOn.classList.toggle('hidden',  hidden);
        eyeOff.classList.toggle('hidden', !hidden);
    });

    // ---- Demo credentials click ----
    document.querySelectorAll('.demo-item').forEach(item => {
        item.addEventListener('click', () => {
            emailEl.value = item.dataset.email;
            passEl.value  = item.dataset.password;
            hideAlert();
        });
    });

    // ---- Submit qua fetch ----
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        const email    = emailEl.value.trim();
        const password = passEl.value.trim();
        if (!email || !password) { showAlert('Vui lòng nhập đầy đủ Email và Mật khẩu.'); return; }

        setLoading(true);

        try {
            const res  = await fetch('/api/account_api.php?action=login', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email, password }),
            });
            const data = await res.json();

            if (data.ok) {
                btnLogin.querySelector('.btn-text').textContent = 'Đang chuyển hướng...';
                window.location.href = data.data.redirect;
            } else {
                showAlert(data.msg || 'Đã có lỗi xảy ra. Vui lòng thử lại.');
                setLoading(false);
            }
        } catch {
            showAlert('Không thể kết nối server. Kiểm tra kết nối mạng.');
            setLoading(false);
        }
    });

    function showAlert(msg) {
        ajaxMsg.textContent = msg;
        ajaxAlert.classList.remove('hidden');
        const srv = document.getElementById('server-alert');
        if (srv) srv.classList.add('hidden');
    }
    function hideAlert() { ajaxAlert.classList.add('hidden'); }
    function setLoading(on) {
        btnLogin.disabled = on;
        btnLogin.classList.toggle('loading', on);
    }

    emailEl.focus();
}());
</script>
</html>