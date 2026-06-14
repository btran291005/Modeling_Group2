<?php
/**
 * login.php
 * Giao diện Đăng nhập phân 2 luồng (Admin/Staff)
 * Có tính năng Ẩn/Hiện mật khẩu (Toggle Password Visibility)
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Smart Gadget AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #03070d;
            --bg-panel: #0a101d;
            
            /* Admin Theme */
            --theme-color: #00f0ff;
            --theme-glow: rgba(0, 240, 255, 0.3);
            --theme-dim: rgba(0, 240, 255, 0.08);
            
            /* Staff Theme */
            --staff-color: #00ff66;
            --staff-glow: rgba(0, 255, 102, 0.3);
            --staff-dim: rgba(0, 255, 102, 0.08);
            
            --text-main: #e2f1ff;
            --text-muted: #647b91;
            --border-color: #1a2a44;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            margin: 0;
            overflow-x: hidden;
            letter-spacing: -0.1px;
        }

        /* -------------------------------------------
           LEFT SIDE: BRANDING
           ------------------------------------------- */
        .bg-branding {
            background-color: #02050a;
            background-image: 
                radial-gradient(circle at 50% 30%, #0d2847 0%, transparent 60%),
                linear-gradient(var(--theme-dim) 1px, transparent 1px),
                linear-gradient(90deg, var(--theme-dim) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
            position: relative;
            transition: all 0.4s ease;
        }

        body.theme-staff .bg-branding {
            background-image: 
                radial-gradient(circle at 50% 30%, #06301a 0%, transparent 60%),
                linear-gradient(var(--staff-dim) 1px, transparent 1px),
                linear-gradient(90deg, var(--staff-dim) 1px, transparent 1px);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .tech-sphere {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 2px dashed var(--theme-color);
            margin: 0 auto 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 50px var(--theme-glow), inset 0 0 50px var(--theme-glow);
            animation: spin 20s linear infinite;
            transition: all 0.4s ease;
        }
        
        body.theme-staff .tech-sphere {
            border-color: var(--staff-color);
            box-shadow: 0 0 50px var(--staff-glow), inset 0 0 50px var(--staff-glow);
        }

        .tech-sphere-inner {
            width: 120px;
            height: 120px;
            background: var(--theme-dim);
            border-radius: 50%;
            backdrop-filter: blur(5px);
            border: 1px solid var(--theme-color);
            animation: spin 15s linear infinite reverse;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s ease;
        }

        body.theme-staff .tech-sphere-inner {
            background: var(--staff-dim);
            border-color: var(--staff-color);
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* -------------------------------------------
           RIGHT SIDE: LOGIN FORM
           ------------------------------------------- */
        .login-panel-container {
            background-color: var(--bg-panel);
            box-shadow: -10px 0 30px rgba(0,0,0,0.5);
            z-index: 10;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        /* Role Tabs */
        .role-tabs {
            display: flex;
            position: relative;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 40px;
            padding: 6px;
        }
        .role-tabs input { display: none; }
        
        .tab-label {
            flex: 1;
            text-align: center;
            padding: 12px;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1px;
            z-index: 2;
            cursor: pointer;
            transition: color 0.3s ease;
            margin: 0;
        }
        .role-tabs input:checked + .tab-label { color: #fff; text-shadow: 0 0 8px rgba(255,255,255,0.5); }
        
        .tab-slider {
            position: absolute;
            top: 6px; bottom: 6px;
            width: calc(50% - 6px);
            border-radius: 8px;
            z-index: 1;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        
        #tab-admin:checked ~ .tab-slider {
            transform: translateX(0);
            background: rgba(0, 240, 255, 0.12);
            border: 1px solid var(--theme-color);
            box-shadow: 0 0 15px var(--theme-glow);
        }
        
        #tab-staff:checked ~ .tab-slider {
            transform: translateX(100%);
            background: rgba(0, 255, 102, 0.12);
            border: 1px solid var(--staff-color);
            box-shadow: 0 0 15px var(--staff-glow);
        }

        /* Inputs Box Styling */
        .form-floating > .form-control {
            background-color: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 12px !important;
            color: var(--text-main) !important;
            padding-left: 18px !important;
            box-shadow: none !important;
            transition: all 0.25s ease;
        }

        .form-floating > label {
            padding-left: 18px !important;
            color: var(--text-muted);
        }

        .form-floating > label::after {
            background-color: var(--bg-panel) !important;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem) !important;
        }

        body:not(.theme-staff) .form-control:focus {
            border-color: var(--theme-color) !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        body.theme-staff .form-control:focus {
            border-color: var(--staff-color) !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }

        /* -------------------------------------------
           PASSWORD TOGGLE ICON
           ------------------------------------------- */
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 18px;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            z-index: 5;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Khi focus input thì icon sáng lên theo theme */
        body:not(.theme-staff) .form-control:focus ~ .password-toggle,
        body:not(.theme-staff) .password-toggle:hover {
            color: var(--theme-color);
        }

        body.theme-staff .form-control:focus ~ .password-toggle,
        body.theme-staff .password-toggle:hover {
            color: var(--staff-color);
        }

        /* Quên mật khẩu link */
        .forgot-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s ease;
        }
        body:not(.theme-staff) .forgot-link:hover { color: var(--theme-color); }
        body.theme-staff .forgot-link:hover { color: var(--staff-color); }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: var(--theme-color);
            color: #000;
            border: none;
            box-shadow: 0 0 15px var(--theme-glow);
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            box-shadow: 0 0 25px var(--theme-glow);
            transform: translateY(-2px);
            color: #000;
            background: #fff;
        }

        body.theme-staff .btn-login {
            background: var(--staff-color);
            box-shadow: 0 0 15px var(--staff-glow);
        }

        body.theme-staff .btn-login:hover {
            box-shadow: 0 0 25px var(--staff-glow);
            background: #fff;
        }

        /* -------------------------------------------
           DEMO ACCOUNT TEXT
           ------------------------------------------- */
        .demo-account-text {
            margin-top: 24px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .demo-account-text b {
            color: var(--text-main);
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="container-fluid vh-100 p-0">
        <div class="row g-0 h-100">
            
            <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-center align-items-center bg-branding">
                <div class="brand-content text-center px-5">
                    <div class="tech-sphere">
                        <div class="tech-sphere-inner">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                    
                    <h1 class="display-6 fw-bold text-white mb-3" style="text-shadow: 0 0 20px rgba(255,255,255,0.3); letter-spacing: 1px;">
                        SMART GADGET AI
                    </h1>
                    <p class="fs-6 text-secondary fw-light" style="max-width: 420px; margin: 0 auto; opacity: 0.8; line-height: 1.6;">
                        Hệ thống quản lý kho tổng và định giá tự động thiết bị thông minh tích hợp công nghệ AI trợ lý.
                    </p>
                </div>
            </div>

            <div class="col-lg-6 d-flex flex-column justify-content-center login-panel-container px-4 px-sm-5">
                <div class="login-box">
                    
                    <div class="mb-5">
                        <h2 class="fw-bold text-white mb-2" style="letter-spacing: -0.5px;">Đăng nhập hệ thống</h2>
                        <p class="text-secondary small">Vui lòng chọn vai trò để bắt đầu phiên làm việc.</p>
                    </div>

                    <div class="role-tabs">
                        <input type="radio" name="role_tab" id="tab-admin" value="admin" checked>
                        <label for="tab-admin" class="tab-label">👑 ADMIN SYSTEM</label>

                        <input type="radio" name="role_tab" id="tab-staff" value="staff">
                        <label for="tab-staff" class="tab-label">👤 STAFF SYSTEM</label>
                        
                        <div class="tab-slider"></div>
                    </div>

                    <div id="error-msg" class="alert d-none border-danger-subtle bg-danger-subtle text-danger p-3 text-center small mb-4 rounded-3 fw-medium"></div>

                    <form id="login-form">
                        <div class="form-floating mb-4">
                            <input type="email" class="form-control" id="email" placeholder="name@example.com" required autocomplete="off">
                            <label for="email">Tài khoản đăng nhập</label>
                        </div>
                        
                        <div class="form-floating mb-3 position-relative">
                            <input type="password" class="form-control pe-5" id="password" placeholder="Password" required>
                            <label for="password">Mật khẩu</label>
                            
                            <span class="password-toggle" id="toggle-password" title="Hiện/Ẩn mật khẩu">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon-svg">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>

                        <div class="d-flex justify-content-end mb-4 pb-1">
                            <a href="#" onclick="alert('Vui lòng báo Quản trị viên (Admin) để thiết lập lại mật khẩu cá nhân!')" class="forgot-link">Quên mật khẩu?</a>
                        </div>

                        <button type="submit" id="btn-submit" class="btn btn-login">
                            Đăng nhập
                        </button>
                    </form>

                    <!-- ============================================================
                         DEMO ACCOUNT TEXT
                         ============================================================ -->
                    <div class="demo-account-text">
                        <div><b>Demo Account</b></div>
                        <div>Admin — Tài khoản: <b>admin@test.com</b> | Mật khẩu: <b>admin123</b></div>
                        <div>Staff — Tài khoản: <b>staff@test.com</b> | Mật khẩu: <b>staff123</b></div>
                    </div>
                    
                    <div class="mt-5 text-center small text-secondary" style="opacity: 0.5;">
                        &copy; 2026 Core Infrastructure System. All rights reserved.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // --- 1. Switch Theme Dynamic Effect + Auto-fill Demo Account ---
        const tabAdmin = document.getElementById('tab-admin');
        const tabStaff = document.getElementById('tab-staff');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const body = document.body;

        // Thông tin tài khoản demo theo vai trò
        const DEMO_ACCOUNTS = {
            admin: { email: 'admin@test.com', password: 'admin123' },
            staff: { email: 'staff@test.com', password: 'staff123' },
        };

        function fillDemoAccount(role) {
            const acc = DEMO_ACCOUNTS[role];
            if (!acc) return;
            emailInput.value    = acc.email;
            passwordInput.value = acc.password;
        }

        function updateTheme() {
            if (tabStaff.checked) {
                body.classList.add('theme-staff');
                emailInput.placeholder = 'staff@system.com';
                fillDemoAccount('staff');
            } else {
                body.classList.remove('theme-staff');
                emailInput.placeholder = 'admin@system.com';
                fillDemoAccount('admin');
            }
        }

        tabAdmin.addEventListener('change', updateTheme);
        tabStaff.addEventListener('change', updateTheme);

        // Điền sẵn tài khoản demo tương ứng với tab đang chọn khi tải trang
        updateTheme();

        // --- 2. Toggle Password Visibility ---
        const togglePassword = document.getElementById('toggle-password');

        togglePassword.addEventListener('click', function () {
            // Đảo ngược thuộc tính type: password <-> text
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Đổi SVG Icon tương ứng (Mắt mở / Mắt gạch chéo)
            if (type === 'text') {
                this.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon-svg">
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                        <line x1="2" y1="2" x2="22" y2="22"></line>
                    </svg>
                `;
            } else {
                this.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon-svg">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            }
        });

        // --- 3. Core Authorization Pipeline ---
        document.getElementById('login-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            
            const errorBox = document.getElementById('error-msg');
            const btn = document.getElementById('btn-submit');
            errorBox.classList.add('d-none');
            errorBox.textContent = '';

            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();

            if (!email || !password) {
                errorBox.textContent = 'Vui lòng nhập đầy đủ Tài khoản và Mật khẩu.';
                errorBox.classList.remove('d-none');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>ĐANG XỬ LÝ...';

            try {
                const res = await fetch('api/account_api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await res.json();

                if (data.ok) {
                    btn.innerHTML = '✅ ĐĂNG NHẬP THÀNH CÔNG';
                    setTimeout(() => {
                        window.location.href = data.data.redirect || 'index.php';
                    }, 400);
                } else {
                    errorBox.textContent = data.msg || 'Tài khoản hoặc mật khẩu không chính xác.';
                    errorBox.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = 'Đăng nhập';
                }
            } catch (err) {
                errorBox.textContent = 'Mất kết nối đến Gateway Server.';
                errorBox.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = 'Đăng nhập';
            }
        });
    </script>
</body>
</html>