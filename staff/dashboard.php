<?php

/**
 * staff/dashboard.php
 *
 * Gate: requireStaff() → 403 nếu không phải Staff.
 * Rule: File này CHỈ làm 3 việc:
 *   1. Check auth
 *   2. Gọi Service lấy dữ liệu
 *   3. Render HTML
 * KHÔNG viết SQL trực tiếp ở đây.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ── GATE ─────────────────────────────────────────────────────────
requireStaff();   // Redirect 403 nếu không phải Staff

$user = currentUser();

// ── DATA (gọi Service, KHÔNG viết SQL ở đây) ─────────────────────
// Ví dụ khi đã có StaffDashboardService:
// require_once __DIR__ . '/../services/StaffDashboardService.php';
// $svc            = new StaffDashboardService($pdo);
// $stats          = $svc->getPersonalStats((int) $user['user_id']);
// $recentSessions = $svc->getRecentSessions((int) $user['user_id'], 6);

// TODO: Replace stub below with real Service calls
$stats          = ['total_mine' => 0, 'today_mine' => 0, 'purchased' => 0, 'declined' => 0];
$recentSessions = [];
$purchaseRate   = 0;

// ── VIEW ──────────────────────────────────────────────────────────
renderHtmlHead('Staff Dashboard', ['../assets/css/pages/staff/dashboard.css']);
renderSidebar('dashboard');
renderMainOpen();
renderTopbar('Tổng quan', '<a href="/staff/dashboard.php">Dashboard</a>');
?>

<div class="page-content">

    <div class="staff-welcome-row">
        <div class="staff-greeting-card">
            <div class="greeting-avatar">
                <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
            </div>
            <div class="greeting-info">
                <h2 class="greeting-name">
                    <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p>Role: <strong>👤 Staff</strong> — <?= date('d/m/Y H:i') ?></p>
            </div>
        </div>

        <div class="quick-action-card">
            <div class="qa-icon">⚡</div>
            <h3>Bắt đầu định giá mới</h3>
            <a href="/staff/valuation.php" class="btn btn-primary">Tạo phiên định giá</a>
        </div>
    </div>

    <div class="staff-stats-row">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-label">Tổng phiên của tôi</div>
            <div class="stat-card-value"><?= $stats['total_mine'] ?></div>
        </div>
        <div class="stat-card stat-card-yellow">
            <div class="stat-card-label">Phiên hôm nay</div>
            <div class="stat-card-value"><?= $stats['today_mine'] ?></div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-label">Đã thu mua</div>
            <div class="stat-card-value"><?= $stats['purchased'] ?></div>
        </div>
        <div class="stat-card stat-card-red">
            <div class="stat-card-label">Từ chối</div>
            <div class="stat-card-value"><?= $stats['declined'] ?></div>
        </div>
    </div>

    <p class="text-muted" style="text-align:center;margin-top:2rem;">
        Nội dung chi tiết sẽ được bổ sung khi implement StaffDashboardService.
    </p>

</div>

<?php renderLayoutClose(); ?>