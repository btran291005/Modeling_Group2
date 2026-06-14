<?php
// ============================================================
// FILE: includes/layout.php
// Theme: Bootstrap 5 Native Dark Mode — Cyberpunk Admin Shell
// ============================================================

declare(strict_types=1);

function renderHeader(string $title): void
{
    $role      = $_SESSION['user']['role']      ?? '';
    $fullName  = $_SESSION['user']['full_name'] ?? '';
    $roleLower = strtolower($role);
    $currentFile = basename($_SERVER['SCRIPT_NAME']);

    // Breadcrumb mapping
    $breadcrumbs = [
        'dashboard.php'   => 'Tổng quan',
        'accounts.php'    => 'Tài khoản',
        'master_data.php' => 'Dữ liệu cấu hình',
        'inventory.php'   => 'Kho hàng',
        'history.php'     => 'Nhật ký định giá',
        'ai_rules.php'    => 'Quy tắc AI',
        'valuation.php'   => 'Định giá mới',
    ];
    $currentBreadcrumb = $breadcrumbs[$currentFile] ?? $title;
    ?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Smart Gadget AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --topbar-height: 68px;
            --accent: #0dcaf0;
            --accent-glow: rgba(13, 202, 240, 0.15);
            --accent-dim: rgba(13, 202, 240, 0.07);
            --sidebar-bg: #0b0f1a;
            --sidebar-border: rgba(13, 202, 240, 0.12);
            --body-bg: #0f1318;
        }

        /* ─── RESET ─── */
        html, body { height: 100%; margin: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--body-bg);
            color: #c9d8e8;
        }

        /* ─── LAYOUT SHELL ─── */
        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 100;
            /* subtle scanline effect */
            background-image:
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 3px,
                    rgba(13, 202, 240, 0.015) 3px,
                    rgba(13, 202, 240, 0.015) 4px
                );
        }

        /* Glow line trên cùng sidebar */
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0.8;
        }

        /* ─── LOGO AREA ─── */
        .sidebar-logo {
            padding: 24px 20px 16px;
            border-bottom: 1px solid var(--sidebar-border);
        }
        .sidebar-logo .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #0dcaf0, #0a5f82);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 0 16px rgba(13,202,240,0.4);
            flex-shrink: 0;
        }
        .sidebar-logo .logo-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }
        .sidebar-logo .logo-sub {
            font-size: 0.7rem;
            color: var(--accent);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: 0.8;
        }

        /* ─── NAV SECTION LABEL ─── */
        .nav-section-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(13, 202, 240, 0.5);
            padding: 20px 20px 8px;
        }

        /* ─── NAV ITEMS ─── */
        .sidebar-nav {
            padding: 0 12px;
            flex: 1;
            overflow-y: auto;
        }
        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--sidebar-border); border-radius: 3px; }

        .nav-item-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 3px;
            color: #7a91a8;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.18s ease;
            position: relative;
        }
        .nav-item-link .nav-icon {
            font-size: 1.15rem;
            width: 26px;
            text-align: center;
            flex-shrink: 0;
        }
        .nav-item-link:hover {
            background: var(--accent-dim);
            color: #b8d4e8;
        }
        .nav-item-link.active {
            background: var(--accent-glow);
            color: var(--accent);
            font-weight: 600;
        }
        /* Active glow strip */
        .nav-item-link.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
            box-shadow: 0 0 8px var(--accent);
        }

        /* ─── SIDEBAR FOOTER (USER INFO) ─── */
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--sidebar-border);
            background: rgba(0,0,0,0.2);
        }
        .user-avatar {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #0dcaf0 0%, #0856a5 100%);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 0 10px rgba(13,202,240,0.3);
        }
        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #c9d8e8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 130px;
        }
        .user-role-badge {
            font-size: 0.65rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--accent);
            opacity: 0.85;
        }
        .btn-logout-sidebar {
            width: 32px; height: 32px;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.25);
            border-radius: 8px;
            color: #dc3545;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.15s;
            flex-shrink: 0;
        }
        .btn-logout-sidebar:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b7a;
        }

        /* ─── MAIN CONTENT AREA ─── */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ─── TOPBAR ─── */
        .topbar {
            height: var(--topbar-height);
            min-height: var(--topbar-height);
            background: rgba(11, 15, 26, 0.85);
            border-bottom: 1px solid var(--sidebar-border);
            backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        /* Breadcrumb in topbar */
        .topbar-breadcrumb {
            font-size: 0.78rem;
            color: rgba(13, 202, 240, 0.55);
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .topbar-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #e2eeff;
            line-height: 1.2;
        }

        /* Status dot */
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #20c997;
            box-shadow: 0 0 6px #20c997;
            animation: pulse-dot 2s infinite;
            flex-shrink: 0;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* System time */
        .topbar-time {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.3);
            font-variant-numeric: tabular-nums;
            font-family: 'Courier New', monospace;
        }

        /* ─── PAGE CONTENT ─── */
        .page-content {
            flex: 1;
            overflow-y: auto;
            padding: 28px 32px;
        }
        .page-content::-webkit-scrollbar { width: 4px; }
        .page-content::-webkit-scrollbar-thumb { background: rgba(13,202,240,0.15); border-radius: 4px; }

        /* ─── CARD OVERRIDES ─── */
        .card {
            border: 1px solid rgba(255,255,255,0.07) !important;
            border-radius: 14px !important;
            background: rgba(15, 22, 35, 0.9) !important;
        }
        .card-header {
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            background: rgba(0,0,0,0.2) !important;
            border-radius: 14px 14px 0 0 !important;
            padding: 14px 20px !important;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .card-body { padding: 20px !important; }
        .card-footer {
            border-top: 1px solid rgba(255,255,255,0.07) !important;
            background: rgba(0,0,0,0.15) !important;
            border-radius: 0 0 14px 14px !important;
            padding: 12px 20px !important;
        }

        /* Stat cards */
        .stat-card {
            border-radius: 14px !important;
            padding: 22px 24px !important;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -30px; right: -20px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: 0.75;
            margin-bottom: 6px;
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.1;
        }
        .stat-card .stat-sub {
            font-size: 0.75rem;
            opacity: 0.65;
            margin-top: 4px;
        }
        .stat-card .stat-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2.2rem;
            opacity: 0.18;
        }

        /* Table */
        .table { color: #b0c4d8 !important; }
        .table thead th {
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            letter-spacing: 1.2px !important;
            text-transform: uppercase !important;
            color: rgba(13, 202, 240, 0.6) !important;
            border-bottom: 1px solid rgba(255,255,255,0.08) !important;
            padding: 12px 16px !important;
            white-space: nowrap;
        }
        .table td {
            border-bottom: 1px solid rgba(255,255,255,0.04) !important;
            padding: 12px 16px !important;
            vertical-align: middle !important;
        }
        .table-hover tbody tr:hover td {
            background: rgba(13, 202, 240, 0.04) !important;
        }

        /* Page header */
        .page-header { margin-bottom: 24px; }
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #e2eeff;
            margin-bottom: 4px;
        }
        .page-header p { color: #576a7e; font-size: 0.88rem; margin: 0; }
    </style>
</head>
<body>

<div class="app-shell">

    <!-- ═══════════════════════════════════════════ SIDEBAR ═══ -->
    <aside class="sidebar">

        <!-- Logo -->
        <div class="sidebar-logo">
            <div class="d-flex align-items-center gap-3">
                <div class="logo-icon">⚡</div>
                <div>
                    <div class="logo-text">Smart Gadget</div>
                    <div class="logo-sub">AI System</div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="sidebar-nav">
            <div class="nav-section-label">
                <?= $roleLower === 'admin' ? 'QUẢN TRỊ HỆ THỐNG' : 'NHÂN VIÊN' ?>
            </div>

            <?php if ($roleLower === 'admin'): ?>

                <a href="dashboard.php"
                   class="nav-item-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Tổng quan</span>
                </a>

                <a href="accounts.php"
                   class="nav-item-link <?= $currentFile === 'accounts.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>
                    <span>Quản lý Tài khoản</span>
                </a>

                <div class="nav-section-label" style="padding-top:16px;">KHO & DỮ LIỆU</div>

                <a href="master_data.php"
                   class="nav-item-link <?= $currentFile === 'master_data.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🗄️</span>
                    <span>Dữ liệu Cấu hình</span>
                </a>

                <a href="inventory.php"
                   class="nav-item-link <?= $currentFile === 'inventory.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Kho hàng tổng</span>
                </a>

                <div class="nav-section-label" style="padding-top:16px;">BÁO CÁO & AI</div>

                <a href="history.php"
                   class="nav-item-link <?= $currentFile === 'history.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🕘</span>
                    <span>Nhật ký Định giá</span>
                </a>

                <a href="ai_rules.php"
                   class="nav-item-link <?= $currentFile === 'ai_rules.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🤖</span>
                    <span>Quy tắc AI</span>
                </a>

            <?php elseif ($roleLower === 'staff'): ?>

                <a href="dashboard.php"
                   class="nav-item-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>

                <a href="valuation.php"
                   class="nav-item-link <?= $currentFile === 'valuation.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚡</span>
                    <span>Định giá mới</span>
                </a>

                <div class="nav-section-label" style="padding-top:16px;">LỊCH SỬ</div>

                <a href="history.php"
                   class="nav-item-link <?= $currentFile === 'history.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🕘</span>
                    <span>Lịch sử định giá</span>
                </a>

                <a href="inventory.php"
                   class="nav-item-link <?= $currentFile === 'inventory.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Kho hàng</span>
                </a>

            <?php endif; ?>
        </div>

        <!-- User Footer -->
        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2">
                <div class="user-avatar">
                    <?= strtoupper(mb_substr($fullName, 0, 1)) ?>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="user-name"><?= htmlspecialchars($fullName) ?></div>
                    <div class="user-role-badge"><?= htmlspecialchars($role) ?></div>
                </div>
                <a href="../logout.php" class="btn-logout-sidebar" title="Đăng xuất">⏻</a>
            </div>
        </div>

    </aside>
    <!-- ══════════════════════════════════════ END SIDEBAR ═══ -->

    <!-- ═════════════════════════════════════ MAIN WRAPPER ═══ -->
    <div class="main-wrapper">

        <!-- ─── TOPBAR ─── -->
        <header class="topbar">
            <div class="flex-grow-1">
                <div class="topbar-breadcrumb">
                    <?= htmlspecialchars($roleLower === 'admin' ? 'Admin' : 'Staff') ?>
                    &nbsp;/&nbsp;<?= htmlspecialchars($currentBreadcrumb) ?>
                </div>
                <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="status-dot" title="Hệ thống đang hoạt động"></div>
                <div class="topbar-time" id="topbar-clock">--:--:--</div>
            </div>
        </header>

        <!-- ─── PAGE CONTENT ─── -->
        <div class="page-content">
<?php
}

function renderFooter(array $extraScripts = []): void
{
    $role      = $_SESSION['user']['role'] ?? '';
    $roleLower = strtolower($role);
    $appJs     = $roleLower === 'admin' ? '../assets/admin_app.js' : '../assets/staff_app.js';
    ?>
        </div><!-- /.page-content -->
    </div><!-- /.main-wrapper -->
</div><!-- /.app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/api.js"></script>
<script src="<?= htmlspecialchars($appJs) ?>"></script>

<?php foreach ($extraScripts as $script): ?>
    <script src="<?= htmlspecialchars($script) ?>"></script>
<?php endforeach; ?>

<script>
    // Live clock in topbar
    (function () {
        const el = document.getElementById('topbar-clock');
        if (!el) return;
        function tick() {
            el.textContent = new Date().toLocaleTimeString('vi-VN', { hour12: false });
        }
        tick();
        setInterval(tick, 1000);
    })();
</script>
</body>
</html>
<?php
}