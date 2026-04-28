<?php
/**
 * staff/inventory.php
 * UC15: Xem kho hàng — Staff chỉ được READ (theo R03)
 *
 * Tính năng:
 *   - Bảng danh sách thiết bị trong kho (tất cả, không chỉ của mình)
 *   - Filter theo trạng thái gadget: Stored | Refurbishing | Sold
 *   - Tìm kiếm theo IMEI, tên máy, hãng
 *   - Phân trang server-side
 *   - Xem chi tiết thiết bị (thông tin phiên, khách)
 *   - TUYỆT ĐỐI KHÔNG có nút DELETE hay UPDATE (R03)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

// Staff và Admin đều được xem (R03: Staff READ only)
requireLogin();
$user = getCurrentUser();

/* ============================================================
 * THAM SỐ FILTER + PHÂN TRANG
 * ============================================================ */
$filterStatus = $_GET['status'] ?? '';  // Stored | Refurbishing | Sold
$searchKw     = trim($_GET['q'] ?? '');
$perPage      = 15;
$currentPage  = max(1, (int) ($_GET['page'] ?? 1));

/* ============================================================
 * BUILD QUERY
 * ============================================================ */
$conditions = [];
$params     = [];

if ($filterStatus && in_array($filterStatus, ['Stored', 'Refurbishing', 'Sold'])) {
    $conditions[] = "g.status = :status";
    $params[':status'] = $filterStatus;
}

if ($searchKw !== '') {
    $conditions[] = "(g.imei LIKE :kw OR dm.model_name LIKE :kw OR b.brand_name LIKE :kw)";
    $params[':kw'] = "%{$searchKw}%";
}

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Đếm tổng
$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM gadgets g
    JOIN valuation_sessions vs ON g.session_id = vs.session_id
    JOIN device_models dm      ON vs.model_id  = dm.model_id
    JOIN brands        b       ON dm.brand_id  = b.brand_id
    {$whereSQL}
");
$stmtCount->execute($params);
$totalRecords = (int) $stmtCount->fetchColumn();
$totalPages   = max(1, (int) ceil($totalRecords / $perPage));
$currentPage  = min($currentPage, $totalPages);
$offset       = ($currentPage - 1) * $perPage;

// Lấy dữ liệu
$stmtData = $pdo->prepare("
    SELECT
        g.imei,
        g.status         AS gadget_status,
        vs.session_id,
        vs.battery_health,
        vs.ai_suggested_price,
        vs.created_at    AS purchase_date,
        dm.model_name,
        dm.ram_gb,
        dm.rom_gb,
        b.brand_name,
        u.full_name      AS staff_name,
        c.full_name      AS customer_name,
        c.phone_number,
        (
            SELECT di.image_url
            FROM device_images di
            WHERE di.session_id = vs.session_id
            ORDER BY di.image_id ASC LIMIT 1
        ) AS thumb_url
    FROM gadgets g
    JOIN valuation_sessions vs ON g.session_id  = vs.session_id
    JOIN device_models dm      ON vs.model_id   = dm.model_id
    JOIN brands        b       ON dm.brand_id   = b.brand_id
    JOIN users         u       ON vs.user_id    = u.user_id
    LEFT JOIN customers c      ON vs.customer_id = c.customer_id
    {$whereSQL}
    ORDER BY g.imei DESC
    LIMIT :limit OFFSET :offset
");
$stmtData->execute(array_merge($params, [
    ':limit'  => $perPage,
    ':offset' => $offset,
]));
$gadgets = $stmtData->fetchAll();

// Thống kê kho (summary bar)
$stmtSummary = $pdo->query("
    SELECT
        COUNT(*)                          AS total,
        SUM(status = 'Stored')            AS stored,
        SUM(status = 'Refurbishing')      AS refurbishing,
        SUM(status = 'Sold')              AS sold
    FROM gadgets
");
$summary = $stmtSummary->fetch();

/* ============================================================
 * RENDER
 * ============================================================ */
renderHtmlHead('Kho hàng', [
    '../assets/css/pages/staff/inventory.css'
]);
renderSidebar('inventory_staff');
renderMainOpen();
renderTopbar('Kho hàng', '<a href="../staff/dashboard.php">Dashboard</a> / Kho hàng');
?>

<div class="page-content">

    <!-- ===== KHO SUMMARY BAR ===== -->
    <div class="inv-summary-row">

        <div class="inv-stat-card">
            <div class="inv-stat-icon inv-stat-icon--blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
            </div>
            <div class="inv-stat-body">
                <div class="inv-stat-num"><?= $summary['total'] ?></div>
                <div class="inv-stat-label">Tổng thiết bị</div>
            </div>
        </div>

        <div class="inv-stat-card">
            <div class="inv-stat-icon inv-stat-icon--green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 11 12 14 22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <div class="inv-stat-body">
                <div class="inv-stat-num"><?= $summary['stored'] ?></div>
                <div class="inv-stat-label">Trong kho</div>
            </div>
        </div>

        <div class="inv-stat-card">
            <div class="inv-stat-icon inv-stat-icon--yellow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                </svg>
            </div>
            <div class="inv-stat-body">
                <div class="inv-stat-num"><?= $summary['refurbishing'] ?></div>
                <div class="inv-stat-label">Tân trang</div>
            </div>
        </div>

        <div class="inv-stat-card">
            <div class="inv-stat-icon inv-stat-icon--purple">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </div>
            <div class="inv-stat-body">
                <div class="inv-stat-num"><?= $summary['sold'] ?></div>
                <div class="inv-stat-label">Đã bán</div>
            </div>
        </div>

        <!-- Notice: read-only -->
        <div class="inv-readonly-notice">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            Bạn đang ở chế độ <strong>Chỉ xem</strong>.<br>
            Liên hệ Admin để cập nhật trạng thái kho.
        </div>

    </div>

    <!-- ===== FILTER & SEARCH ===== -->
    <div class="card inv-filter-card">
        <div class="card-body">
            <form method="GET" class="filter-row">

                <!-- Tìm kiếm -->
                <div class="search-wrap">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="q" class="form-control search-input"
                           placeholder="Tìm IMEI, hãng, mẫu máy..."
                           value="<?= e($searchKw) ?>" autocomplete="off">
                    <?php if ($searchKw): ?>
                        <button type="button" class="search-clear"
                                onclick="location.href='?status=<?= urlencode($filterStatus) ?>'" title="Xoá">×</button>
                    <?php endif; ?>
                </div>

                <!-- Filter trạng thái -->
                <div class="filter-status-group">
                    <?php
                    $statuses = [
                        ''             => ['Tất cả',      'filter-btn--all'],
                        'Stored'       => ['Trong kho',    'filter-btn--green'],
                        'Refurbishing' => ['Tân trang',    'filter-btn--yellow'],
                        'Sold'         => ['Đã bán',       'filter-btn--purple'],
                    ];
                    foreach ($statuses as $val => $info):
                        $active = ($filterStatus === $val) ? 'active' : '';
                    ?>
                        <a href="?status=<?= urlencode($val) ?>&q=<?= urlencode($searchKw) ?>"
                           class="filter-status-btn <?= $info[1] ?> <?= $active ?>">
                            <?= $info[0] ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Tìm kiếm</button>
            </form>
        </div>
    </div>

    <!-- ===== BẢNG KHO HÀNG ===== -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Danh sách thiết bị trong kho</div>
                <div class="card-subtitle">
                    <?= number_format($totalRecords) ?> thiết bị
                    <?= $filterStatus ? '— lọc: ' . e($filterStatus) : '' ?>
                    <?= $searchKw ? '— tìm: "' . e($searchKw) . '"' : '' ?>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-header-right">
                    <span class="pagination-info">Trang <?= $currentPage ?>/<?= $totalPages ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body-flush">
            <?php if (empty($gadgets)): ?>
                <div class="empty-state" style="padding: 3rem;">
                    <div class="empty-icon">📦</div>
                    <div class="empty-title">Kho trống</div>
                    <div class="empty-desc">
                        <?= $searchKw || $filterStatus
                            ? 'Không tìm thấy thiết bị phù hợp.'
                            : 'Chưa có thiết bị nào được nhập kho.' ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th width="150">IMEI</th>
                                <th>Thiết bị</th>
                                <th width="100">Cấu hình</th>
                                <th width="80">Pin</th>
                                <th width="140">Giá thu mua</th>
                                <th width="160">Khách hàng</th>
                                <th width="110">Staff thu mua</th>
                                <th width="110">Trạng thái</th>
                                <th width="110">Ngày nhập</th>
                                <th width="60" class="td-center">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gadgets as $g): ?>
                            <tr>
                                <!-- IMEI -->
                                <td>
                                    <span class="imei-full td-mono"><?= e($g['imei']) ?></span>
                                </td>

                                <!-- Thiết bị + thumb -->
                                <td>
                                    <div class="device-cell">
                                        <?php if ($g['thumb_url']): ?>
                                            <img src="../<?= e($g['thumb_url']) ?>"
                                                 class="device-thumb" alt="ảnh máy"
                                                 onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="device-thumb-placeholder">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4">
                                                    <rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="device-info">
                                            <span class="device-brand"><?= e($g['brand_name']) ?></span>
                                            <span class="device-model"><?= e($g['model_name']) ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Cấu hình -->
                                <td>
                                    <span class="config-badge"><?= $g['ram_gb'] ?>GB / <?= $g['rom_gb'] ?>GB</span>
                                </td>

                                <!-- Pin -->
                                <td>
                                    <?php $bat = (int) $g['battery_health']; ?>
                                    <div class="battery-cell">
                                        <div class="bat-bar-mini <?= $bat >= 80 ? 'bat-good' : ($bat >= 60 ? 'bat-ok' : 'bat-low') ?>"
                                             style="width:<?= $bat ?>%"></div>
                                        <span class="td-mono" style="font-size:0.8rem;"><?= $bat ?>%</span>
                                    </div>
                                </td>

                                <!-- Giá thu mua -->
                                <td>
                                    <span class="price-cell td-mono" data-vnd="<?= $g['ai_suggested_price'] ?>">—</span>
                                </td>

                                <!-- Khách hàng -->
                                <td>
                                    <?php if ($g['customer_name']): ?>
                                        <div class="customer-mini">
                                            <span><?= e(truncate($g['customer_name'], 14)) ?></span>
                                            <span class="td-mono text-muted" style="font-size:0.75rem;"><?= e($g['phone_number']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted text-sm">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Staff -->
                                <td>
                                    <span class="staff-name"><?= e(truncate($g['staff_name'], 14)) ?></span>
                                </td>

                                <!-- Trạng thái -->
                                <td><?= gadgetStatusBadge($g['gadget_status']) ?></td>

                                <!-- Ngày nhập -->
                                <td class="td-mono text-muted" style="font-size:0.8rem;"
                                    title="<?= e($g['purchase_date']) ?>">
                                    <?= formatDatetime($g['purchase_date'], false) ?>
                                </td>

                                <!-- Chi tiết -->
                                <td class="td-center">
                                    <button class="btn-detail"
                                            onclick="showGadgetDetail(this)"
                                            data-gadget='<?= e(json_encode([
                                                'imei'         => $g['imei'],
                                                'brand'        => $g['brand_name'],
                                                'model'        => $g['model_name'],
                                                'ram'          => $g['ram_gb'],
                                                'rom'          => $g['rom_gb'],
                                                'battery'      => $g['battery_health'],
                                                'price'        => $g['ai_suggested_price'],
                                                'status'       => $g['gadget_status'],
                                                'customer'     => $g['customer_name'],
                                                'phone'        => $g['phone_number'],
                                                'staff'        => $g['staff_name'],
                                                'session_id'   => $g['session_id'],
                                                'purchase_date'=> $g['purchase_date'],
                                            ], JSON_UNESCAPED_UNICODE)) ?>'
                                            title="Xem chi tiết">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        </svg>
                                    </button>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PHÂN TRANG -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-wrap">
                    <?php
                    $baseUrl = '?' . http_build_query(array_filter([
                        'status' => $filterStatus,
                        'q'      => $searchKw,
                    ]));
                    if ($currentPage > 1): ?>
                        <a href="<?= $baseUrl ?>&page=<?= $currentPage - 1 ?>" class="page-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        </a>
                    <?php endif;
                    $start = max(1, $currentPage - 2);
                    $end   = min($totalPages, $currentPage + 2);
                    if ($start > 1): ?>
                        <a href="<?= $baseUrl ?>&page=1" class="page-btn">1</a>
                        <?php if ($start > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <?php endif;
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?= $baseUrl ?>&page=<?= $i ?>"
                           class="page-btn <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                        <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="page-btn"><?= $totalPages ?></a>
                    <?php endif;
                    if ($currentPage < $totalPages): ?>
                        <a href="<?= $baseUrl ?>&page=<?= $currentPage + 1 ?>" class="page-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end .page-content -->


<!-- ============================================================
     MODAL: CHI TIẾT THIẾT BỊ KHO
     ============================================================ -->
<div class="modal-overlay" id="gadget-modal" onclick="closeGadgetModal(event)">
    <div class="modal modal-detail" role="dialog" aria-modal="true">

        <div class="modal-header">
            <h3 class="modal-title">Chi tiết thiết bị</h3>
            <button class="modal-close" onclick="closeGadgetModal()">&times;</button>
        </div>

        <div class="modal-body">

            <div class="detail-section">
                <div class="detail-section-label">Thiết bị</div>
                <div class="detail-device-name" id="gm-device">—</div>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-key">IMEI</span>
                    <span class="detail-val td-mono" id="gm-imei">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Cấu hình</span>
                    <span class="detail-val" id="gm-config">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Tỷ lệ pin</span>
                    <span class="detail-val" id="gm-battery">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Giá thu mua</span>
                    <span class="detail-val detail-val--price" id="gm-price">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Trạng thái</span>
                    <span class="detail-val" id="gm-status">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Phiên #</span>
                    <span class="detail-val td-mono" id="gm-session">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Ngày nhập kho</span>
                    <span class="detail-val" id="gm-date">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Staff thu mua</span>
                    <span class="detail-val" id="gm-staff">—</span>
                </div>
            </div>

            <div class="detail-section" id="gm-customer-section">
                <div class="detail-section-label">Khách hàng bán máy</div>
                <div class="detail-customer-row">
                    <div class="detail-item">
                        <span class="detail-key">Tên</span>
                        <span class="detail-val" id="gm-cname">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">SĐT</span>
                        <span class="detail-val td-mono" id="gm-cphone">—</span>
                    </div>
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeGadgetModal()">Đóng</button>
        </div>
    </div>
</div>


<?php renderLayoutClose(); ?>


<script>
/* ============================================================
   inventory.js — inline (Staff, read-only)
   ============================================================ */

function showGadgetDetail(btn) {
    const data = JSON.parse(btn.dataset.gadget);

    document.getElementById('gm-device').textContent   = `${data.brand} ${data.model}`;
    document.getElementById('gm-imei').textContent     = data.imei;
    document.getElementById('gm-config').textContent   = `${data.ram}GB RAM / ${data.rom}GB ROM`;
    document.getElementById('gm-battery').textContent  = `${data.battery}%`;
    document.getElementById('gm-price').textContent    = formatVND(data.price);
    document.getElementById('gm-session').textContent  = `#${data.session_id}`;
    document.getElementById('gm-date').textContent     = data.purchase_date;
    document.getElementById('gm-staff').textContent    = data.staff;

    const statusMap = {
        'Stored':       '<span class="badge badge-primary">Trong kho</span>',
        'Refurbishing': '<span class="badge badge-warning">Đang tân trang</span>',
        'Sold':         '<span class="badge badge-success">Đã bán</span>',
    };
    document.getElementById('gm-status').innerHTML = statusMap[data.status] || data.status;

    if (data.customer) {
        document.getElementById('gm-cname').textContent  = data.customer;
        document.getElementById('gm-cphone').textContent = data.phone;
        document.getElementById('gm-customer-section').style.display = '';
    } else {
        document.getElementById('gm-customer-section').style.display = 'none';
    }

    document.getElementById('gadget-modal').classList.add('visible');
}

function closeGadgetModal(e) {
    if (e && e.target !== document.getElementById('gadget-modal')) return;
    document.getElementById('gadget-modal').classList.remove('visible');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeGadgetModal();
});
</script>