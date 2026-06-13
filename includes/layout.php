<?php
// ============================================================
// FILE: includes/layout.php
// Theme: Bootstrap 5 Native Dark Mode
// ============================================================

declare(strict_types=1);

/**
 * In ra phần đầu trang: DOCTYPE, head, navbar, mở wrapper + sidebar
 *
 * @param string $title  Tiêu đề trang (hiển thị trên tab + navbar)
 */
function renderHeader(string $title): void
{
    $role      = $_SESSION['user']['role']      ?? '';
    $fullName  = $_SESSION['user']['full_name'] ?? '';
    $roleLower = strtolower($role);
    
    // Lấy tên file hiện tại để tự động active menu
    $currentFile = basename($_SERVER['SCRIPT_NAME']);
    ?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Smart Gadget AI</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* CSS cực mỏng chỉ để làm khung Sidebar cố định */
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #121212; }
        .sidebar { min-height: 100vh; width: 260px; border-right: 1px solid var(--bs-border-color); background-color: var(--bs-dark-bg-subtle); }
        .main-content { flex: 1; height: 100vh; overflow-y: auto; }
        .nav-pills .nav-link { border-radius: 8px; margin-bottom: 4px; }
        .nav-pills .nav-link:hover:not(.active) { background-color: rgba(255,255,255,0.05); }
    </style>
</head>
<body class="d-flex">

    <aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
        <div class="d-flex align-items-center mb-4 me-md-auto text-decoration-none">
            <span class="fs-4 fw-bold text-info">⚡ Smart Gadget</span>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <?php if ($roleLower === 'admin'): ?>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : 'text-light' ?>">📊 Tổng quan</a>
                </li>
                <li class="nav-item">
                    <a href="accounts.php" class="nav-link <?= $currentFile === 'accounts.php' ? 'active' : 'text-light' ?>">👥 Quản lý Tài khoản</a>
                </li>
                <li class="nav-item">
                    <a href="master_data.php" class="nav-link <?= $currentFile === 'master_data.php' ? 'active' : 'text-light' ?>">🗄️ Dữ liệu Cấu hình</a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php" class="nav-link <?= $currentFile === 'inventory.php' ? 'active' : 'text-light' ?>">📦 Kho hàng tổng</a>
                </li>
                <li class="nav-item">
                    <a href="history.php" class="nav-link <?= $currentFile === 'history.php' ? 'active' : 'text-light' ?>">🕘 Nhật ký Định giá</a>
                </li>
                <li class="nav-item">
                    <a href="ai_rules.php" class="nav-link <?= $currentFile === 'ai_rules.php' ? 'active' : 'text-light' ?>">🤖 Quy tắc AI</a>
                </li>
            <?php elseif ($roleLower === 'staff'): ?>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : 'text-light' ?>">📊 Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="valuation.php" class="nav-link <?= $currentFile === 'valuation.php' ? 'active' : 'text-light' ?>">⚡ Định giá mới</a>
                </li>
                <li class="nav-item">
                    <a href="history.php" class="nav-link <?= $currentFile === 'history.php' ? 'active' : 'text-light' ?>">🕘 Lịch sử định giá</a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php" class="nav-link <?= $currentFile === 'inventory.php' ? 'active' : 'text-light' ?>">📦 Kho hàng</a>
                </li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="main-content d-flex flex-column">
        
        <header class="navbar navbar-expand border-bottom px-4 py-3 sticky-top" style="background-color: var(--bs-body-bg); backdrop-filter: blur(10px);">
            <h4 class="mb-0 fw-semibold text-light"><?= htmlspecialchars($title) ?></h4>
            <div class="ms-auto d-flex align-items-center gap-4">
                <div class="text-end">
                    <div class="fw-bold text-info" style="font-size: 0.95rem;"><?= htmlspecialchars($fullName) ?></div>
                    <div class="text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?= htmlspecialchars($role) ?></div>
                </div>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">Đăng xuất</a>
            </div>
        </header>

        <div class="container-fluid p-4">
<?php
}

/**
 * Đóng các thẻ HTML mở trong renderHeader() + nạp JS chung.
 *
 * @param array $extraScripts  Danh sách đường dẫn JS bổ sung, nạp SAU api.js
 */
function renderFooter(array $extraScripts = []): void
{
    $role      = $_SESSION['user']['role'] ?? '';
    $roleLower = strtolower($role);
    
    // Tự động load file app.js tương ứng với Role
    $appJs = $roleLower === 'admin' ? '../assets/js/admin_app.js' : '../assets/js/staff_app.js';
    ?>
        </div></main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/api.js"></script>
    <script src="<?= $appJs ?>"></script>
    
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
<?php
}