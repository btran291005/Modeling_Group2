<?php
/**
 * staff/dashboard.php
 * Trang tổng quan Staff 
 * Hiển thị stats cá nhân, Quick Action, Lịch sử gần đây
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

// Đã đăng nhập (cả Admin và Staff đều được)
requireLogin();
$user = getCurrentUser();

// Lấy stats và lịch sử của chính Staff này
$stats          = getStaffStats($pdo, (int) $user['user_id']);
$recentSessions = getRecentSessions($pdo, 6, (int) $user['user_id']);

// Tỷ lệ thu mua (để render progress bar)
$purchaseRate = $stats['total_mine'] > 0
    ? round($stats['purchased'] / $stats['total_mine'] * 100)
    : 0;

renderHtmlHead('Staff Dashboard', [
    '../assets/css/pages/staff/dashboard.css'
]);

renderSidebar('dashboard');
renderMainOpen();
renderTopbar('Tổng quan', '<a href="#">Home</a> / Dashboard');
?>

<!-- ===== NỘI DUNG TRANG ===== -->
<div class="page-content">

    <!-- ---------- Welcome Row ---------- -->
    <div class="staff-welcome-row">

        <!-- Greeting Card -->
        <div class="staff-greeting-card">
            <div class="greeting-avatar">
                <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
            </div>
            <div class="greeting-info">
                <p class="greeting-time-label">
                    <?php
                    $hour = (int) date('H');
                    echo match(true) {
                        $hour >= 5 && $hour < 12 => '☀️ Chào buổi sáng',
                        $hour >= 12 && $hour < 18 => '🌤 Chào buổi chiều',
                        default                   => '🌙 Chào buổi tối',
                    };
                    ?>
                </p>
                <h2 class="greeting-name"><?= e($user['full_name']) ?></h2>
                <p class="greeting-date"><?= date('l, d/m/Y') ?></p>
            </div>
        </div>

        <!-- Quick Action: Bắt đầu định giá mới -->
        <div class="quick-action-card">
            <div class="qa-icon">⚡</div>
            <h3>Bắt đầu định giá mới</h3>
            <p>Nhập thông tin thiết bị, chụp ảnh và để AI<br>tự động tính giá thu mua tối ưu.</p>
            <a href="/staff/valuation.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                Tạo phiên định giá
            </a>
        </div>

    </div>

    <!-- ---------- Stats Row ---------- -->
    <div class="staff-stats-row">

        <div class="stat-card stat-card-blue">
            <div class="stat-card-header">
                <span class="stat-card-label">Tổng phiên của tôi</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['total_mine'] ?></div>
            <div class="stat-card-sub">Từ khi bắt đầu làm việc</div>
        </div>

        <div class="stat-card stat-card-yellow">
            <div class="stat-card-header">
                <span class="stat-card-label">Phiên hôm nay</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['today_mine'] ?></div>
            <div class="stat-card-sub">Ngày <?= date('d/m/Y') ?></div>
        </div>

        <div class="stat-card stat-card-green">
            <div class="stat-card-header">
                <span class="stat-card-label">Đã thu mua</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['purchased'] ?></div>
            <div class="stat-card-sub">Thiết bị đã chốt mua</div>
        </div>

        <div class="stat-card stat-card-red">
            <div class="stat-card-header">
                <span class="stat-card-label">Từ chối</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= $stats['declined'] ?></div>
            <div class="stat-card-sub">Không đạt yêu cầu</div>
        </div>

    </div>

    <!-- ---------- Purchase Rate Progress ---------- -->
    <div class="card purchase-rate-card">
        <div class="card-body">
            <div class="rate-header">
                <div>
                    <div class="rate-label">Tỷ lệ thu mua thành công</div>
                    <div class="rate-desc">Dựa trên tổng <?= $stats['total_mine'] ?> phiên</div>
                </div>
                <div class="rate-value"><?= $purchaseRate ?>%</div>
            </div>
            <div class="progress-bar-wrap" style="margin-top: var(--space-3);">
                <div class="progress-bar-fill <?= $purchaseRate >= 70 ? 'green' : ($purchaseRate >= 40 ? 'yellow' : 'red') ?>"
                     style="width: <?= $purchaseRate ?>%"></div>
            </div>
            <div class="rate-legend">
                <span class="rate-leg rate-leg-success">✅ Đã mua: <?= $stats['purchased'] ?></span>
                <span class="rate-leg rate-leg-danger">❌ Từ chối: <?= $stats['declined'] ?></span>
                <span class="rate-leg rate-leg-warning">⏳ Đang chờ: <?= max(0, $stats['total_mine'] - $stats['purchased'] - $stats['declined']) ?></span>
            </div>
        </div>
    </div>

    <!-- ---------- Lịch sử phiên gần đây ---------- -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Phiên định giá của tôi</div>
                <div class="card-subtitle">6 phiên gần nhất</div>
            </div>
            <a href="/staff/history.php" class="btn btn-ghost btn-sm">
                Xem tất cả →
            </a>
        </div>
        <div class="card-body-flush">
            <?php if (empty($recentSessions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📱</div>
                    <div class="empty-title">Chưa có phiên định giá nào</div>
                    <div class="empty-desc">Nhấn "Tạo phiên định giá" để bắt đầu nhận thiết bị và định giá bằng AI.</div>
                    <a href="/staff/valuation.php" class="btn btn-primary" style="margin-top: var(--space-4);">
                        Bắt đầu ngay
                    </a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Thiết bị</th>
                                <th>Khách hàng</th>
                                <th>Pin</th>
                                <th>Giá AI đề xuất</th>
                                <th>Kết quả</th>
                                <th>Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSessions as $s): ?>
                            <tr>
                                <td class="td-mono td-primary">#<?= $s['session_id'] ?></td>
                                <td>
                                    <div class="device-info">
                                        <span class="device-brand"><?= e($s['brand_name']) ?></span>
                                        <span class="device-model"><?= e($s['model_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($s['customer_name']): ?>
                                        <div class="customer-info">
                                            <span><?= e(truncate($s['customer_name'], 16)) ?></span>
                                            <span class="td-mono" style="font-size:0.75rem;color:var(--text-muted)">
                                                <?= e($s['phone_number']) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:0.8125rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="battery-display">
                                        <div class="battery-icon <?= $s['battery_health'] >= 80 ? 'good' : ($s['battery_health'] >= 60 ? 'ok' : 'low') ?>">
                                            🔋
                                        </div>
                                        <span class="td-mono"><?= $s['battery_health'] ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="price-value td-mono td-primary" data-vnd="<?= $s['ai_suggested_price'] ?>">
                                        —
                                    </span>
                                </td>
                                <td><?= sessionStatusBadge($s['final_status']) ?></td>
                                <td class="td-mono" title="<?= e($s['created_at']) ?>">
                                    <?= timeAgo($s['created_at']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end .page-content -->

<?php
renderLayoutClose([]);
?>