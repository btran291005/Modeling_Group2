<?php
/**
 * admin/inventory.php
 * Quản lý kho thiết bị điện tử đã thu mua
 * Tính năng: Xem kho, Lọc theo trạng thái, Cập nhật trạng thái, Xem chi tiết
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();
$currentUser = getCurrentUser();

// ---------- Xử lý flash message ----------
$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ---------- Xử lý cập nhật trạng thái (AJAX) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $body   = json_decode(file_get_contents('php://input'), true);
    $action = $body['action'] ?? '';

    if ($action === 'update_status') {
        $imei      = trim($body['imei'] ?? '');
        $newStatus = $body['status'] ?? '';

        if (!$imei || !in_array($newStatus, ['Stored', 'Refurbishing', 'Sold'])) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE gadgets SET status = :status WHERE imei = :imei");
            $stmt->execute([':status' => $newStatus, ':imei' => $imei]);

            // Ghi audit log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (:uid, :act, 'gadgets')")
                ->execute([
                    ':uid' => $_SESSION['user_id'],
                    ':act' => "Cập nhật trạng thái IMEI {$imei} → {$newStatus}"
                ]);

            echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu.']);
        }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ.']);
    exit;
}

// ---------- Phân trang & Lọc ----------
$search        = trim($_GET['search'] ?? '');
$statusFilter  = $_GET['status'] ?? '';
$brandFilter   = (int) ($_GET['brand_id'] ?? 0);
$perPage       = 15;
$page          = max(1, (int) ($_GET['page'] ?? 1));

// Build WHERE
$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = "(g.imei LIKE :search OR dm.model_name LIKE :search OR b.brand_name LIKE :search OR c.full_name LIKE :search OR c.phone_number LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (in_array($statusFilter, ['Stored', 'Refurbishing', 'Sold'])) {
    $conditions[] = "g.status = :status";
    $params[':status'] = $statusFilter;
}
if ($brandFilter > 0) {
    $conditions[] = "b.brand_id = :brand_id";
    $params[':brand_id'] = $brandFilter;
}

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Đếm tổng
$countSQL = "
    SELECT COUNT(*)
    FROM gadgets g
    JOIN valuation_sessions vs ON g.session_id = vs.session_id
    JOIN device_models dm      ON vs.model_id   = dm.model_id
    JOIN brands b              ON dm.brand_id   = b.brand_id
    LEFT JOIN customers c      ON vs.customer_id = c.customer_id
    {$whereSQL}
";
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Lấy danh sách thiết bị
$sql = "
    SELECT
        g.imei,
        g.status         AS gadget_status,
        g.created_at     AS received_at,
        dm.model_name,
        dm.ram_gb,
        dm.rom_gb,
        b.brand_name,
        vs.session_id,
        vs.battery_health,
        vs.ai_suggested_price,
        u.full_name      AS staff_name,
        c.full_name      AS customer_name,
        c.phone_number
    FROM gadgets g
    JOIN valuation_sessions vs ON g.session_id = vs.session_id
    JOIN device_models dm      ON vs.model_id   = dm.model_id
    JOIN brands b              ON dm.brand_id   = b.brand_id
    JOIN users u               ON vs.user_id    = u.user_id
    LEFT JOIN customers c      ON vs.customer_id = c.customer_id
    {$whereSQL}
    ORDER BY g.created_at DESC
    LIMIT :lim OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim',    $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$gadgets = $stmt->fetchAll();

// ---------- Thống kê ----------
$statStored       = (int) $pdo->query("SELECT COUNT(*) FROM gadgets WHERE status='Stored'")->fetchColumn();
$statRefurbishing = (int) $pdo->query("SELECT COUNT(*) FROM gadgets WHERE status='Refurbishing'")->fetchColumn();
$statSold         = (int) $pdo->query("SELECT COUNT(*) FROM gadgets WHERE status='Sold'")->fetchColumn();

// Danh sách hãng để filter
$brands = $pdo->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC")->fetchAll();

// ---------- Render ----------
renderHtmlHead('Kho thiết bị', [
    '../assets/css/pages/admin/inventory.css'
]);
renderSidebar('inventory_admin');
renderMainOpen();
renderTopbar('Kho thiết bị', '<a href="#">Admin</a> / Kho');
?>

<div class="page-content">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:1rem">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ===== Stats Cards ===== -->
    <div class="stats-grid stats-grid-3">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-header">
                <span class="stat-card-label">Đang trong kho</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statStored) ?></div>
            <div class="stat-card-sub">Sẵn sàng bán</div>
        </div>
        <div class="stat-card stat-card-yellow">
            <div class="stat-card-header">
                <span class="stat-card-label">Đang tân trang</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statRefurbishing) ?></div>
            <div class="stat-card-sub">Cần xử lý</div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-header">
                <span class="stat-card-label">Đã bán</span>
                <div class="stat-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value"><?= number_format($statSold) ?></div>
            <div class="stat-card-sub">Tổng đã xuất kho</div>
        </div>
    </div>

    <!-- ===== Bảng kho ===== -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Danh sách thiết bị</div>
                <div class="card-subtitle">Tổng <?= number_format($total) ?> thiết bị</div>
            </div>
            <!-- Export nút (placeholder) -->
            <button class="btn btn-secondary btn-sm" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                In danh sách
            </button>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="filter-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="search" class="form-input" placeholder="IMEI, tên máy, khách hàng..."
                           value="<?= e($search) ?>">
                </div>
                <select name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Stored"       <?= $statusFilter === 'Stored'       ? 'selected' : '' ?>>Trong kho</option>
                    <option value="Refurbishing" <?= $statusFilter === 'Refurbishing' ? 'selected' : '' ?>>Đang tân trang</option>
                    <option value="Sold"         <?= $statusFilter === 'Sold'         ? 'selected' : '' ?>>Đã bán</option>
                </select>
                <select name="brand_id" class="form-select">
                    <option value="">Tất cả hãng</option>
                    <?php foreach ($brands as $br): ?>
                        <option value="<?= $br['brand_id'] ?>" <?= $brandFilter === (int)$br['brand_id'] ? 'selected' : '' ?>>
                            <?= e($br['brand_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Lọc</button>
                <?php if ($search || $statusFilter || $brandFilter): ?>
                    <a href="inventory.php" class="btn btn-ghost btn-sm">Xóa lọc</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Bảng thiết bị -->
        <div class="card-body-flush">
            <div class="table-wrapper">
                <?php if (empty($gadgets)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <div class="empty-title">Kho đang trống</div>
                        <div class="empty-desc">Khi Staff hoàn tất thu mua và nhập IMEI, thiết bị sẽ hiển thị ở đây.</div>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>IMEI</th>
                                <th>Thiết bị</th>
                                <th>Cấu hình</th>
                                <th>Pin</th>
                                <th>Giá thu mua</th>
                                <th>Khách hàng</th>
                                <th>Ngày nhập</th>
                                <th>Trạng thái</th>
                                <th>Đổi TT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gadgets as $g): ?>
                            <tr data-imei="<?= e($g['imei']) ?>">
                                <td class="td-mono td-primary imei-cell">
                                    <?= e($g['imei']) ?>
                                </td>
                                <td>
                                    <div class="device-name-cell">
                                        <span class="td-primary"><?= e($g['brand_name']) ?> <?= e($g['model_name']) ?></span>
                                        <span class="td-muted" style="font-size:11px">#<?= $g['session_id'] ?></span>
                                    </div>
                                </td>
                                <td class="td-mono td-muted">
                                    <?= $g['ram_gb'] ?>GB · <?= $g['rom_gb'] ?>GB
                                </td>
                                <td>
                                    <div class="battery-cell">
                                        <div class="progress-bar-wrap" style="width:50px">
                                            <div class="progress-bar-fill <?= $g['battery_health'] >= 80 ? 'green' : ($g['battery_health'] >= 60 ? 'yellow' : 'red') ?>"
                                                 style="width:<?= $g['battery_health'] ?>%"></div>
                                        </div>
                                        <span class="td-mono"><?= $g['battery_health'] ?>%</span>
                                    </div>
                                </td>
                                <td class="td-mono td-primary" data-vnd="<?= $g['ai_suggested_price'] ?>">—</td>
                                <td>
                                    <?php if ($g['customer_name']): ?>
                                        <div class="customer-cell">
                                            <span><?= e(truncate($g['customer_name'], 18)) ?></span>
                                            <span class="td-muted" style="font-size:11px"><?= e($g['phone_number']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="td-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-mono td-muted" title="<?= e($g['received_at']) ?>">
                                    <?= formatDatetime($g['received_at'], false) ?>
                                </td>
                                <td class="status-cell" id="status-<?= e($g['imei']) ?>">
                                    <?= gadgetStatusBadge($g['gadget_status']) ?>
                                </td>
                                <td>
                                    <select class="status-select form-select form-select-sm"
                                            data-imei="<?= e($g['imei']) ?>"
                                            onchange="updateGadgetStatus(this)">
                                        <option value="Stored"       <?= $g['gadget_status'] === 'Stored'       ? 'selected' : '' ?>>Trong kho</option>
                                        <option value="Refurbishing" <?= $g['gadget_status'] === 'Refurbishing' ? 'selected' : '' ?>>Tân trang</option>
                                        <option value="Sold"         <?= $g['gadget_status'] === 'Sold'         ? 'selected' : '' ?>>Đã bán</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Hiển thị <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> / <?= number_format($total) ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query(['search' => $search, 'status' => $statusFilter, 'brand_id' => $brandFilter]) ?>" class="page-btn">‹</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&<?= http_build_query(['search' => $search, 'status' => $statusFilter, 'brand_id' => $brandFilter]) ?>"
                       class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query(['search' => $search, 'status' => $statusFilter, 'brand_id' => $brandFilter]) ?>" class="page-btn">›</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- end .page-content -->

<!-- Toast -->
<div id="toast" class="toast" style="display:none"></div>

<?php renderLayoutClose(); ?>
<script>
/**
 * Cập nhật trạng thái thiết bị qua AJAX
 * Không reload trang, cập nhật badge ngay lập tức
 */
async function updateGadgetStatus(selectEl) {
    const imei      = selectEl.dataset.imei;
    const newStatus = selectEl.value;
    const oldValue  = selectEl.dataset.prev || selectEl.options[0].value;

    // Lưu giá trị cũ để rollback nếu lỗi
    selectEl.dataset.prev = newStatus;

    try {
        const res = await fetch('inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'update_status', imei, status: newStatus })
        });
        const data = await res.json();

        if (data.success) {
            // Cập nhật badge trạng thái ngay
            const statusCell = document.getElementById('status-' + imei);
            if (statusCell) {
                const badgeMap = {
                    'Stored':       '<span class="badge badge-primary">Trong kho</span>',
                    'Refurbishing': '<span class="badge badge-warning">Đang tân trang</span>',
                    'Sold':         '<span class="badge badge-success">Đã bán</span>'
                };
                statusCell.innerHTML = badgeMap[newStatus] || newStatus;
            }
            showToast('Cập nhật trạng thái thành công!', 'success');
        } else {
            // Rollback giá trị nếu thất bại
            selectEl.value = oldValue;
            showToast(data.message || 'Cập nhật thất bại.', 'error');
        }
    } catch (e) {
        selectEl.value = oldValue;
        showToast('Lỗi kết nối server.', 'error');
    }
}

// Lưu giá trị ban đầu của mỗi select
document.querySelectorAll('.status-select').forEach(sel => {
    sel.dataset.prev = sel.value;
});

// Format giá tiền
document.querySelectorAll('[data-vnd]').forEach(el => {
    const val = parseInt(el.dataset.vnd, 10);
    if (!isNaN(val)) {
        el.textContent = new Intl.NumberFormat('vi-VN', {
            style: 'currency', currency: 'VND', maximumFractionDigits: 0
        }).format(val);
    }
});

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent  = msg;
    t.className    = 'toast toast-' + type;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}
</script>