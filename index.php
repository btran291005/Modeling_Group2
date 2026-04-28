<?php
/**
 * index.php
 * Trang Đăng nhập - Entry point của hệ thống
 * Nếu đã đăng nhập -> redirect về dashboard tương ứng
 */
require_once __DIR__ . '/includes/auth.php';

// Nếu đã đăng nhập, redirect ngay
if (isLoggedIn()) {
    $url = isAdmin()
        ? 'admin/dashboard.php'
        : 'staff/dashboard.php';
    header("Location: $url");
    exit;
}

// Lấy thông báo lý do redirect về (nếu có)
$reason = $_GET['reason'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — Smart Gadget Valuation</title>
    <meta name="description" content="Hệ thống quản lý thu mua thiết bị điện tử cũ">

    <!-- Preconnect fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/general.css">
    <link rel="stylesheet" href="assets/css/pages/login.css">
</head>

<body class="login-page">

    <!-- ========== CỘT TRÁI: VISUAL PANEL ========== -->
    <div class="login-visual">
        <div class="visual-content">

            <!-- Logo -->
            <div class="visual-logo">
                <div class="logo-icon">⚡</div>
                <span class="logo-text">Smart<span>Gadget</span></span>
            </div>

            <!-- Headline -->
            <h2 class="visual-headline">
                Định giá thông minh<br>
                với <em>AI tích hợp</em>
            </h2>

            <p class="visual-desc">
                Hệ thống quản lý thu mua thiết bị điện tử cũ.
                Tự động định giá bằng Gemini AI, chuẩn hóa quy trình
                nhập kho và chống gian lận hiệu quả.
            </p>

            <!-- Feature Pills -->
            <div class="feature-pills">
                <span class="feature-pill">
                    <span class="pill-dot"></span>
                    AI định giá tự động
                </span>
                <span class="feature-pill">
                    <span class="pill-dot"></span>
                    Quản lý kho hàng
                </span>
                <span class="feature-pill">
                    <span class="pill-dot"></span>
                    Phân quyền 2 cấp
                </span>
                <span class="feature-pill">
                    <span class="pill-dot"></span>
                    Audit log đầy đủ
                </span>
                <span class="feature-pill">
                    <span class="pill-dot"></span>
                    Báo cáo thống kê
                </span>
            </div>

        </div>
    </div>

    <!-- ========== CỘT PHẢI: FORM PANEL ========== -->
    <div class="login-form-panel">
        <div class="login-form-container">

            <!-- Form Header -->
            <div class="form-header">
                <div class="form-header-eyebrow">Hệ thống nội bộ</div>
                <h1>Chào mừng trở lại</h1>
                <p>Đăng nhập để tiếp tục công việc</p>
            </div>

            <!-- Thông báo lỗi từ server (PHP render) -->
            <?php if ($reason === 'unauthenticated'): ?>
            <div class="login-alert" style="margin-bottom: 20px;" id="server-alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Bạn cần đăng nhập để tiếp tục.
            </div>
            <?php elseif ($reason === 'logged_out'): ?>
            <div class="login-alert" style="background: var(--success-subtle); border-color: rgba(34,197,94,0.3); color: var(--success); margin-bottom: 20px; " id="server-alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Bạn đã đăng xuất thành công.
            </div>
            <?php endif; ?>

            <!-- Alert lỗi AJAX (JS điều khiển) -->
            <div class="login-alert hidden" style="margin-bottom: 20px;" id="ajax-alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span id="ajax-alert-text"></span>
            </div>

            <!-- Form Đăng Nhập -->
            <form class="login-form" id="login-form" novalidate>
                <!-- Hidden action field -->
                <input type="hidden" name="action" value="login">

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Địa chỉ Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input
                            class="form-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="example@domain.com"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <!-- Mật khẩu -->
                <div class="form-group">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input
                            class="form-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Nhập mật khẩu của bạn"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="toggle-password" id="toggle-password" aria-label="Hiện/ẩn mật khẩu">
                            <!-- Eye icon (mặc định: đang ẩn) -->
                            <svg id="eye-show" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <!-- Eye-off icon (khi đang hiện) -->
                            <svg id="eye-hide" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hidden">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Nút Đăng nhập -->
                <button type="submit" class="btn-login" id="btn-login">
                    <span class="btn-text">Đăng nhập</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <!-- Demo Credentials (chỉ dùng môi trường dev) -->
            <div class="demo-credentials">
                <h4>🔑 Tài khoản demo (mật khẩu: 123456)</h4>

                <div class="demo-item" data-email="admin@test.com" data-password="123456">
                    <div class="demo-info">
                        <span class="demo-role">👑 Admin</span>
                        <span class="demo-email">admin@test.com</span>
                    </div>
                    <span class="demo-hint">Click để điền</span>
                </div>

                <div class="demo-item" data-email="staff@test.com" data-password="123456">
                    <div class="demo-info">
                        <span class="demo-role">👤 Staff</span>
                        <span class="demo-email">staff@test.com</span>
                    </div>
                    <span class="demo-hint">Click để điền</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="form-footer">
                <p>Smart Gadget Valuation &amp; Inventory<br>
                <strong>Hệ thống nội bộ — Chỉ dành cho nhân viên</strong></p>
            </div>

        </div>
    </div>

</body>

<!-- ========== JAVASCRIPT ĐĂNG NHẬP ========== -->
<script>
(function () {
    'use strict';

    // ---------- DOM References ----------
    const form        = document.getElementById('login-form');
    const emailInput  = document.getElementById('email');
    const passInput   = document.getElementById('password');
    const btnLogin    = document.getElementById('btn-login');
    const ajaxAlert   = document.getElementById('ajax-alert');
    const alertText   = document.getElementById('ajax-alert-text');
    const toggleBtn   = document.getElementById('toggle-password');
    const eyeShow     = document.getElementById('eye-show');
    const eyeHide     = document.getElementById('eye-hide');
    const demoItems   = document.querySelectorAll('.demo-item');

    // ---------- Toggle hiện/ẩn mật khẩu ----------
    toggleBtn.addEventListener('click', function () {
        const isHidden = passInput.type === 'password';
        passInput.type = isHidden ? 'text' : 'password';
        eyeShow.classList.toggle('hidden', isHidden);
        eyeHide.classList.toggle('hidden', !isHidden);
    });

    // ---------- Click vào demo credentials để tự điền ----------
    demoItems.forEach(function (item) {
        item.addEventListener('click', function () {
            emailInput.value = item.dataset.email;
            passInput.value  = item.dataset.password;
            hideAlert(); // Ẩn lỗi cũ nếu có
            emailInput.focus();
        });
    });

    // ---------- Submit Form bằng Fetch API (AJAX) ----------
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email    = emailInput.value.trim();
        const password = passInput.value.trim();

        // Validate phía client trước khi gửi
        if (!email || !password) {
            showAlert('Vui lòng nhập đầy đủ Email và Mật khẩu.');
            return;
        }

        // Vào trạng thái loading
        setLoading(true);
        hideAlert();

        try {
            // Chuẩn bị dữ liệu gửi lên
            const formData = new FormData(form);

            // Gọi API
            const response = await fetch('api/account.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                // Đăng nhập thành công -> redirect
                btnLogin.querySelector('.btn-text').textContent = 'Đang chuyển hướng...';
                window.location.href = data.redirect;
            } else {
                // Đăng nhập thất bại -> hiện thông báo lỗi
                showAlert(data.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.');
                setLoading(false);
            }

        } catch (error) {
            // Lỗi mạng hoặc lỗi server
            showAlert('Không thể kết nối server. Vui lòng kiểm tra lại kết nối mạng.');
            setLoading(false);
        }
    });

    // ---------- Helper: Hiện/ẩn alert ----------
    function showAlert(message) {
        alertText.textContent = message;
        ajaxAlert.classList.remove('hidden');

        // Ẩn thông báo từ PHP nếu có
        const serverAlert = document.getElementById('server-alert');
        if (serverAlert) serverAlert.classList.add('hidden');
    }

    function hideAlert() {
        ajaxAlert.classList.add('hidden');
    }

    // ---------- Helper: Trạng thái loading ----------
    function setLoading(isLoading) {
        btnLogin.disabled = isLoading;
        btnLogin.classList.toggle('loading', isLoading);
    }

    // ---------- Auto-focus vào email khi load trang ----------
    emailInput.focus();

})();
</script>
</html>