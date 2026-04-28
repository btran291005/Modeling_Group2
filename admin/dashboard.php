<?php
/**
 * admin/dashboard.php
 * Trang tổng quan Admin
 * Hiển thị KPI Stats, Phiên định giá gần đây, Audit Log
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

// Chỉ Admin được vào
requireAdmin();
$user = getCurrentUser();

// Lấy dữ liệu dashboard
$stats        = getAdminStats($pdo);
$recentSessions = getRecentSessions($pdo, 8);
$auditLogs    = getRecentAuditLogs($pdo, 10);

// Render HTML Head + mở layout
renderHtmlHead('Admin Dashboard', [
    '../assets/css/pages/admin/dashboard.css'
]);

renderSidebar('dashboard');
renderMainOpen();
renderTopbar('Tổng quan', '<a href="#">Home</a> / Dashboard');
?>

<!-- ===== NỘI DUNG TRANG ===== -->
<div class="page-content">

    <!-- ---------- Greeting ---------- -->
    <div class="dashboard-greeting">
        <div>
            <h2 class="greeting-title">
                <?php
                $hour = (int) date('H');
                echo match(true) {
                    $hour >= 5 && $hour < 12 => 'Chào buổi sáng',
                    $hour >= 12 && $hour < 18 => 'Chào buổi chiều',
                    default                   => 'Chào buổi tối',
                };
                ?>, <?= e($user['full_name']) ?> 👋
            </h2>
            <p class="greeting-sub">
                Hôm nay là <?= date('l, d/m/Y') ?> — Đây là tổng quan hoạt động hệ thống.
            </p>
        </div>
        <div class="greeting-actions">
            <a href="/admin/valuation_log.php" class="btn btn-secondary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                Nhật ký định giá
            </a>
            <a href="/admin/accounts.php" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
                Thêm tài khoản
            </a>
        </div>
    </div>

    <!-- ---------- KPI Stats ---------- -->
    <div class="stats-grid">

        <!-- Tổng phiên định giá -->
        <div class="stat-card stat-card-blue">
            <div class="stat-card-header">
                <span class="stat-card-label">Tổng phiên định giá</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($stats['total_sessions']) ?></div>
            <div class="stat-card-sub">Tất cả thời gian</div>
        </div>

        <!-- Thu mua hôm nay -->
        <div class="stat-card stat-card-green">
            <div class="stat-card-header">
                <span class="stat-card-label">Thu mua hôm nay</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['today_purchased'] ?></div>
            <div class="stat-card-sub">Thiết bị thu mua</div>
        </div>

        <!-- Trong kho -->
        <div class="stat-card stat-card-purple">
            <div class="stat-card-header">
                <span class="stat-card-label">Thiết bị trong kho</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['in_stock'] ?></div>
            <div class="stat-card-sub">Đang lưu kho</div>
        </div>

        <!-- Doanh thu ước tính -->
        <div class="stat-card stat-card-yellow">
            <div class="stat-card-header">
                <span class="stat-card-label">Doanh thu ước tính</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value stat-vnd" data-vnd="<?= $stats['estimated_revenue'] ?>">
                —
            </div>
            <div class="stat-card-sub">Tổng giá trị đã thu mua</div>
        </div>

        <!-- Staff hoạt động -->
        <div class="stat-card stat-card-red" style="grid-column: span 1;">
            <div class="stat-card-header">
                <span class="stat-card-label">Staff hoạt động</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['active_staff'] ?></div>
            <div class="stat-card-sub">Nhân viên đang hoạt động</div>
        </div>

    </div>

    <!-- ---------- Grid: Recent Sessions + Audit Log ---------- -->
    <div class="dashboard-grid">

        <!-- Phiên định giá gần đây -->
        <div class="card dashboard-sessions">
            <div class="card-header">
                <div>
                    <div class="card-title">Phiên định giá gần đây</div>
                    <div class="card-subtitle">8 phiên mới nhất toàn hệ thống</div>
                </div>
                <a href="/admin/valuation_log.php" class="btn btn-ghost btn-sm">
                    Xem tất cả →
                </a>
            </div>
            <div class="card-body-flush">
                <div class="table-wrapper">
                    <?php if (empty($recentSessions)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <div class="empty-title">Chưa có phiên nào</div>
                            <div class="empty-desc">Khi Staff bắt đầu định giá, dữ liệu sẽ hiển thị ở đây.</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Thiết bị</th>
                                    <th>Staff</th>
                                    <th>Pin</th>
                                    <th>Giá AI</th>
                                    <th>Trạng thái</th>
                                    <th>Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSessions as $s): ?>
                                <tr>
                                    <td class="td-mono td-primary">#<?= $s['session_id'] ?></td>
                                    <td>
                                        <div class="device-cell">
                                            <span class="td-primary"><?= e($s['brand_name']) ?> <?= e($s['model_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="staff-cell">
                                            <div class="avatar avatar-sm">
                                                <?= mb_strtoupper(mb_substr($s['staff_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                                            </div>
                                            <span><?= e(truncate($s['staff_name'], 15)) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="battery-cell">
                                            <div class="progress-bar-wrap" style="width:60px">
                                                <div class="progress-bar-fill <?= $s['battery_health'] >= 80 ? 'green' : ($s['battery_health'] >= 60 ? 'yellow' : 'red') ?>"
                                                     style="width:<?= $s['battery_health'] ?>%"></div>
                                            </div>
                                            <span class="td-mono"><?= $s['battery_health'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="td-mono td-primary" data-vnd="<?= $s['ai_suggested_price'] ?>">—</td>
                                    <td><?= sessionStatusBadge($s['final_status']) ?></td>
                                    <td class="td-mono" title="<?= e($s['created_at']) ?>"><?= timeAgo($s['created_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Audit Log gần đây -->
        <div class="card dashboard-audit">
            <div class="card-header">
                <div>
                    <div class="card-title">Hoạt động hệ thống</div>
                    <div class="card-subtitle">Audit log 10 hành động gần nhất</div>
                </div>
            </div>
            <div class="card-body-flush audit-list">
                <?php if (empty($auditLogs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <div class="empty-title">Chưa có hoạt động</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                    <div class="audit-item">
                        <div class="audit-avatar">
                            <?= mb_strtoupper(mb_substr($log['full_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                        </div>
                        <div class="audit-content">
                            <div class="audit-action">
                                <span class="audit-user"><?= e(truncate($log['full_name'], 16)) ?></span>
                                <span class="audit-verb"><?= e(truncate($log['action'], 40)) ?></span>
                            </div>
                            <div class="audit-meta">
                                <?= roleBadge($log['role']) ?>
                                <span class="audit-table">→ <?= e($log['target_table']) ?></span>
                            </div>
                        </div>
                        <div class="audit-time" title="<?= e($log['created_at']) ?>">
                            <?= timeAgo($log['created_at']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- end .dashboard-grid -->

</div><!-- end .page-content -->

<?php
renderLayoutClose([]);
?>