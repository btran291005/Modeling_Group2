<?php
/**
 * admin/valuation_log.php
 * Nhật ký định giá toàn hệ thống
 * Tính năng: Xem tất cả phiên định giá, lọc đa chiều, xem chi tiết phiên
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();
$currentUser = getCurrentUser();

// ---------- Lọc & Phân trang ----------
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$staffFilter  = (int) ($_GET['staff_id'] ?? 0);
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$perPage      = 20;
$page         = max(1, (int) ($_GET['page'] ?? 1));

// Build WHERE
$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = "(dm.model_name LIKE :s OR b.brand_name LIKE :s OR c.full_name LIKE :s OR c.phone_number LIKE :s OR g.imei LIKE :s)";
    $params[':s'] = "%{$search}%";
}
if (in_array($statusFilter, ['Pending', 'Purchased', 'Declined'])) {
    $conditions[] = "vs.final_status = :st";
    $params[':st'] = $statusFilter;
}
if ($staffFilter > 0) {
    $conditions[] = "vs.user_id = :uid";
    $params[':uid'] = $staffFilter;
}
if ($dateFrom !== '') {
    $conditions[] = "DATE(vs.created_at) >= :df";
    $params[':df'] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[] = "DATE(vs.created_at) <= :dt";
    $params[':dt'] = $dateTo;
}

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Join chung
$joinSQL = "
    FROM valuation_sessions vs
    JOIN device_models dm ON vs.model_id    = dm.model_id
    JOIN brands b         ON dm.brand_id    = b.brand_id
    JOIN users u          ON vs.user_id     = u.user_id
    LEFT JOIN customers c ON vs.customer_id = c.customer_id
    LEFT JOIN gadgets g   ON g.session_id   = vs.session_id
";

// Đếm tổng
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT vs.session_id) {$joinSQL} {$whereSQL}");
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Lấy dữ liệu
$sql = "
    SELECT
        vs.session_id,
        vs.battery_health,
        vs.ai_suggested_price,
        vs.final_status,
        vs.created_at,
        dm.model_name,
        dm.ram_gb,
        dm.rom_gb,
        b.brand_name,
        u.user_id   AS staff_id,
        u.full_name AS staff_name,
        c.full_name AS customer_name,
        c.phone_number,
        g.imei,
        g.status    AS gadget_status
    {$joinSQL}
    {$whereSQL}
    GROUP BY vs.session_id
    ORDER BY vs.created_at DESC
    LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$sessions = $stmt->fetchAll();

// ---------- Thống kê nhanh ----------
$statTotal     = (int) $pdo->query("SELECT COUNT(*) FROM valuation_sessions")->fetchColumn();
$statPurchased = (int) $pdo->query("SELECT COUNT(*) FROM valuation_sessions WHERE final_status='Purchased'")->fetchColumn();
$statDeclined  = (int) $pdo->query("SELECT COUNT(*) FROM valuation_sessions WHERE final_status='Declined'")->fetchColumn();
$statPending   = (int) $pdo->query("SELECT COUNT(*) FROM valuation_sessions WHERE final_status='Pending'")->fetchColumn();
$statRevenue   = (int) $pdo->query("SELECT COALESCE(SUM(ai_suggested_price),0) FROM valuation_sessions WHERE final_status='Purchased'")->fetchColumn();

// Danh sách Staff để filter
$staffList = $pdo->query("SELECT user_id, full_name FROM users WHERE role='Staff' ORDER BY full_name ASC")->fetchAll();

// ---------- Render ----------
renderHtmlHead('Nhật ký định giá', [
    '../assets/css/pages/admin/valuation_log.css'
]);
renderSidebar('valuation_log');
renderMainOpen();
renderTopbar('Nhật ký định giá', '<a href="#">Admin</a> / Nhật ký');
?>

<div class="page-content">

    <!-- ===== Stats Row ===== -->
    <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">

        <div class="stat-card stat-card-blue">
            <div class="stat-card-header">
                <span class="stat-card-label">Tổng phiên</span>
                <div class="stat-card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statTotal) ?></div>
            <div class="stat-card-sub">Tất cả thời gian</div>
        </div>

        <div class="stat-card stat-card-green">
            <div class="stat-card-header">
                <span class="stat-card-label">Đã thu mua</span>
                <div class="stat-card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statPurchased) ?></div>
            <div class="stat-card-sub"><?= $statTotal > 0 ? round($statPurchased / $statTotal * 100) : 0 ?>% tỷ lệ mua</div>
        </div>

        <div class="stat-card stat-card-red">
            <div class="stat-card-header">
                <span class="stat-card-label">Từ chối</span>
                <div class="stat-card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statDeclined) ?></div>
            <div class="stat-card-sub">Phiên từ chối</div>
        </div>

        <div class="stat-card stat-card-yellow">
            <div class="stat-card-header">
                <span class="stat-card-label">Chờ xử lý</span>
                <div class="stat-card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statPending) ?></div>
            <div class="stat-card-sub">Pending</div>
        </div>

        <div class="stat-card stat-card-purple">
            <div class="stat-card-header">
                <span class="stat-card-label">Tổng thu mua</span>
                <div class="stat-card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value stat-vnd" data-vnd="<?= $statRevenue ?>" style="font-size:1.1rem">—</div>
            <div class="stat-card-sub">Tổng giá trị</div>
        </div>
    </div>

    <!-- ===== Bảng log ===== -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Danh sách phiên định giá</div>
                <div class="card-subtitle">Tổng <?= number_format($total) ?> phiên khớp bộ lọc</div>
            </div>
        </div>

        <!-- Filter bar nâng cao -->
        <div class="filter-bar filter-bar-advanced">
            <form method="GET" class="filter-form filter-form-wrap">
                <!-- Row 1 -->
                <div class="filter-row">
                    <div class="filter-search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" name="search" class="form-input" placeholder="Tìm máy, khách, IMEI..."
                               value="<?= e($search) ?>">
                    </div>
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="Purchased" <?= $statusFilter === 'Purchased' ? 'selected' : '' ?>>✅ Đã thu mua</option>
                        <option value="Declined"  <?= $statusFilter === 'Declined'  ? 'selected' : '' ?>>❌ Từ chối</option>
                        <option value="Pending"   <?= $statusFilter === 'Pending'   ? 'selected' : '' ?>>⏳ Chờ xử lý</option>
                    </select>
                    <select name="staff_id" class="form-select">
                        <option value="">Tất cả Staff</option>
                        <?php foreach ($staffList as $st): ?>
                            <option value="<?= $st['user_id'] ?>" <?= $staffFilter === (int)$st['user_id'] ? 'selected' : '' ?>>
                                <?= e($st['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Row 2: Date range -->
                <div class="filter-row">
                    <div class="date-range-group">
                        <label class="filter-label">Từ ngày</label>
                        <input type="date" name="date_from" class="form-input" value="<?= e($dateFrom) ?>">
                    </div>
                    <div class="date-range-group">
                        <label class="filter-label">Đến ngày</label>
                        <input type="date" name="date_to" class="form-input" value="<?= e($dateTo) ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-secondary btn-sm">Lọc</button>
                        <?php if ($search || $statusFilter || $staffFilter || $dateFrom || $dateTo): ?>
                            <a href="valuation_log.php" class="btn btn-ghost btn-sm">Xóa lọc</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bảng -->
        <div class="card-body-flush">
            <div class="table-wrapper">
                <?php if (empty($sessions)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <div class="empty-title">Không tìm thấy phiên nào</div>
                        <div class="empty-desc">Thử thay đổi bộ lọc để xem kết quả.</div>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Thiết bị</th>
                                <th>Staff</th>
                                <th>Khách hàng</th>
                                <th>Pin</th>
                                <th>Giá AI</th>
                                <th>IMEI</th>
                                <th>Trạng thái</th>
                                <th>Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                            <tr class="<?= $s['final_status'] === 'Declined' ? 'row-muted' : '' ?>">
                                <td class="td-mono td-muted"><?= $s['session_id'] ?></td>
                                <td>
                                    <div>
                                        <span class="td-primary"><?= e($s['brand_name']) ?> <?= e($s['model_name']) ?></span>
                                        <div class="td-muted" style="font-size:11px"><?= $s['ram_gb'] ?>GB RAM · <?= $s['rom_gb'] ?>GB</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar avatar-sm">
                                            <?= mb_strtoupper(mb_substr($s['staff_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                                        </div>
                                        <span><?= e(truncate($s['staff_name'], 14)) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($s['customer_name']): ?>
                                        <div>
                                            <div><?= e(truncate($s['customer_name'], 16)) ?></div>
                                            <div class="td-muted" style="font-size:11px"><?= e($s['phone_number']) ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="td-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="battery-cell">
                                        <div class="progress-bar-wrap" style="width:44px">
                                            <div class="progress-bar-fill <?= $s['battery_health'] >= 80 ? 'green' : ($s['battery_health'] >= 60 ? 'yellow' : 'red') ?>"
                                                 style="width:<?= $s['battery_health'] ?>%"></div>
                                        </div>
                                        <span class="td-mono"><?= $s['battery_health'] ?>%</span>
                                    </div>
                                </td>
                                <td class="td-mono td-primary" data-vnd="<?= $s['ai_suggested_price'] ?>">—</td>
                                <td class="td-mono td-muted imei-cell">
                                    <?= $s['imei'] ? e($s['imei']) : '<span class="td-muted">Chưa nhập</span>' ?>
                                </td>
                                <td><?= sessionStatusBadge($s['final_status']) ?></td>
                                <td class="td-mono td-muted" title="<?= e($s['created_at']) ?>">
                                    <?= timeAgo($s['created_at']) ?>
                                    <div style="font-size:10px;color:var(--text-disabled)"><?= formatDatetime($s['created_at']) ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1):
            $q = http_build_query(['search' => $search, 'status' => $statusFilter, 'staff_id' => $staffFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
        ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Hiển thị <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> / <?= number_format($total) ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= $q ?>" class="page-btn">‹</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&<?= $q ?>"
                       class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= $q ?>" class="page-btn">›</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- end .page-content -->

<?php renderLayoutClose(); ?>
<script>
// Format giá tiền VNĐ
document.querySelectorAll('[data-vnd]').forEach(el => {
    const val = parseInt(el.dataset.vnd, 10);
    if (!isNaN(val)) {
        el.textContent = new Intl.NumberFormat('vi-VN', {
            style: 'currency', currency: 'VND', maximumFractionDigits: 0
        }).format(val);
    }
});
</script>