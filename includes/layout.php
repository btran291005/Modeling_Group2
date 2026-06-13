<?php
// ============================================================
// FILE: includes/layout.php
// ============================================================

declare(strict_types=1);

/**
 * In ra phần đầu trang: DOCTYPE, head, navbar, mở wrapper + sidebar
 *
 * @param string $title  Tiêu đề trang (hiển thị trên tab + navbar)
 */
function renderHeader(string $title): void
{
    $role     = $_SESSION['role']      ?? '';
    $fullName = $_SESSION['full_name'] ?? '';
    $roleLower = strtolower($role);
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Smart Gadget</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $roleLower === 'admin' ? '../' : '../' ?>assets/css/style.css">
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar navbar-dark bg-dark px-3">
        <span class="navbar-brand mb-0 h1">⚡ Smart Gadget — <?= htmlspecialchars($title) ?></span>
        <div class="d-flex align-items-center text-white">
            <span class="me-3">
                👤 <?= htmlspecialchars($fullName) ?>
                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($role) ?></span>
            </span>
            <a href="<?= $roleLower === 'admin' ? '../' : '../' ?>logout.php" class="btn btn-outline-light btn-sm">
                Đăng xuất
            </a>
        </div>
    </nav>

    <div class="d-flex">

        <!-- ===== SIDEBAR ===== -->
        <aside class="bg-light border-end p-3" style="width:220px; min-height:calc(100vh - 56px);">
            <ul class="nav flex-column gap-1">
                <?php if ($roleLower === 'admin'): ?>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="accounts.php" class="nav-link">👥 Tài khoản</a>
                    </li>
                    <li class="nav-item">
                        <a href="master_data.php" class="nav-link">🗂 Dữ liệu nền</a>
                    </li>
                    <li class="nav-item">
                        <a href="ai_rules.php" class="nav-link">⚡ Quy tắc AI</a>
                    </li>
                    <li class="nav-item">
                        <a href="inventory.php" class="nav-link">📦 Kho hàng</a>
                    </li>
                    <li class="nav-item">
                        <a href="valuation_log.php" class="nav-link">📋 Nhật ký định giá</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="valuation.php" class="nav-link">⚡ Định giá mới</a>
                    </li>
                    <li class="nav-item">
                        <a href="history.php" class="nav-link">🕘 Lịch sử định giá</a>
                    </li>
                    <li class="nav-item">
                        <a href="inventory.php" class="nav-link">📦 Kho hàng</a>
                    </li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="flex-grow-1 p-4">
    <?php
}

/**
 * Đóng các thẻ HTML mở trong renderHeader() + nạp JS chung
 */
function renderFooter(): void
{
    ?>
        </main><!-- end main content -->
    </div><!-- end d-flex wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/api.js"></script>
</body>
</html>
    <?php
}