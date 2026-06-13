<?php

require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — Smart Gadget Valuation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f1f5f9; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-card { width:100%; max-width:380px; }
    </style>
</head>
<body>
    <div class="card login-card shadow-sm">
        <div class="card-body p-4">
            <h4 class="text-center mb-3">⚡ Smart Gadget</h4>
            <p class="text-center text-muted mb-4">Đăng nhập hệ thống</p>

            <div id="error-msg" class="alert alert-danger d-none"></div>

            <form id="login-form">
                <div class="mb-3">
                    <label class="form-label">Username (Email)</label>
                    <input type="text" id="username" name="username" class="form-control" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100" id="btn-submit">Đăng nhập</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('login-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const errorBox = document.getElementById('error-msg');
        const btn      = document.getElementById('btn-submit');
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();

        if (!username || !password) {
            errorBox.textContent = 'Vui lòng nhập đầy đủ Username và Password.';
            errorBox.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Đang xử lý...';

        try {
            const res = await fetch('api/account_api.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();

            if (data.ok) {
                window.location.href = 'index.php';
            } else {
                errorBox.textContent = data.msg || 'Đăng nhập thất bại.';
                errorBox.classList.remove('d-none');
            }
        } catch (err) {
            errorBox.textContent = 'Không thể kết nối server. Vui lòng thử lại.';
            errorBox.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Đăng nhập';
        }
    });
    </script>
</body>
</html>