<?php

/**
 * admin/dashboard.php
 *
 * Gate: requireAdmin() → 403 nếu không phải Admin.
 * Rule: File này CHỈ làm 3 việc:
 *   1. Check auth
 *   2. Gọi Service lấy dữ liệu
 *   3. Render HTML
 * KHÔNG viết SQL trực tiếp ở đây.
 */

require_once __DIR__ . '/../config/db_connect.php';   // $pdo
require_once __DIR__ . '/../includes/auth.php';        // requireAdmin()
require_once __DIR__ . '/../includes/layout.php';      // renderHtmlHead(), renderSidebar()...

// ── GATE ─────────────────────────────────────────────────────────
requireAdmin();   // Redirect 403 nếu không phải Admin

$user = currentUser();

// ── DATA (gọi Service, KHÔNG viết SQL ở đây) ─────────────────────
// Ví dụ khi đã có AdminDashboardService:
// require_once __DIR__ . '/../services/AdminDashboardService.php';
// $svc   = new AdminDashboardService($pdo);
// $stats = $svc->getKpiStats();
// $recentSessions = $svc->getRecentSessions(8);
// $auditLogs      = $svc->getRecentAuditLogs(10);

// TODO: Replace stub below with real Service calls
$stats          = ['total_sessions' => 0, 'today_purchased' => 0, 'in_stock' => 0, 'active_staff' => 0, 'estimated_revenue' => 0];
$recentSessions = [];
$auditLogs      = [];

// ── VIEW ──────────────────────────────────────────────────────────
renderHtmlHead('Admin Dashboard', ['../assets/css/pages/admin/dashboard.css']);
renderSidebar('dashboard');
renderMainOpen();
renderTopbar('Tổng quan', '<a href="/admin/dashboard.php">Dashboard</a>');
?>

<div class="page-content">

    <div class="dashboard-greeting">
        <h2>Xin chào, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?> 👋</h2>
        <p>Role: <strong>👑 Admin</strong> — <?= date('d/m/Y H:i') ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-label">Tổng phiên định giá</div>
            <div class="stat-card-value"><?= number_format($stats['total_sessions']) ?></div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-label">Thu mua hôm nay</div>
            <div class="stat-card-value"><?= $stats['today_purchased'] ?></div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-card-label">Thiết bị trong kho</div>
            <div class="stat-card-value"><?= $stats['in_stock'] ?></div>
        </div>
        <div class="stat-card stat-card-yellow">
            <div class="stat-card-label">Doanh thu ước tính</div>
            <div class="stat-card-value" data-vnd="<?= $stats['estimated_revenue'] ?>">—</div>
        </div>
        <div class="stat-card stat-card-red">
            <div class="stat-card-label">Staff hoạt động</div>
            <div class="stat-card-value"><?= $stats['active_staff'] ?></div>
        </div>
    </div>

    <p class="text-muted" style="text-align:center;margin-top:2rem;">
        Nội dung chi tiết sẽ được bổ sung khi implement AdminDashboardService.
    </p>

</div>

<?php renderLayoutClose(); ?>