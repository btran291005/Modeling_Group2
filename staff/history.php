<?php
/**
 * staff/history.php
 * UC14: Xem lịch sử định giá cá nhân của Staff
 *
 * Tính năng:
 *   - Bảng danh sách TẤT CẢ phiên của Staff đang đăng nhập
 *   - Filter theo trạng thái (Pending / Purchased / Declined)
 *   - Tìm kiếm theo tên thiết bị hoặc IMEI
 *   - Phân trang (server-side)
 *   - Xem chi tiết phiên trong modal (ảnh, quy tắc khấu trừ, thông tin khách)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user   = getCurrentUser();
$userId = (int) $user['user_id'];

/* ============================================================
 * NHẬN THAM SỐ FILTER + PHÂN TRANG
 * ============================================================ */
$filterStatus = $_GET['status'] ?? '';     // Pending | Purchased | Declined | ''
$searchKw     = trim($_GET['q'] ?? '');    // Tìm kiếm theo tên máy
$perPage      = 15;
$currentPage  = max(1, (int) ($_GET['page'] ?? 1));

/* ============================================================
 * BUILD QUERY
 * ============================================================ */
$conditions = ["vs.user_id = :uid"];
$params     = [':uid' => $userId];

if ($filterStatus && in_array($filterStatus, ['Pending', 'Purchased', 'Declined'])) {
    $conditions[] = "vs.final_status = :status";
    $params[':status'] = $filterStatus;
}

if ($searchKw !== '') {
    $conditions[] = "(dm.model_name LIKE :kw OR b.brand_name LIKE :kw OR g.imei LIKE :kw)";
    $params[':kw'] = "%{$searchKw}%";
}

$whereSQL = 'WHERE ' . implode(' AND ', $conditions);

// Đếm tổng bản ghi
$countSQL = "
    SELECT COUNT(DISTINCT vs.session_id)
    FROM valuation_sessions vs
    JOIN device_models dm ON vs.model_id  = dm.model_id
    JOIN brands        b  ON dm.brand_id  = b.brand_id
    LEFT JOIN gadgets  g  ON g.session_id = vs.session_id
    {$whereSQL}
";
$stmtCount = $pdo->prepare($countSQL);
$stmtCount->execute($params);
$totalRecords = (int) $stmtCount->fetchColumn();
$totalPages   = max(1, (int) ceil($totalRecords / $perPage));
$currentPage  = min($currentPage, $totalPages);
$offset       = ($currentPage - 1) * $perPage;

// Lấy dữ liệu trang hiện tại
$dataSQL = "
    SELECT
        vs.session_id,
        vs.battery_health,
        vs.ai_suggested_price,
        vs.final_status,
        vs.created_at,
        dm.model_name,
        b.brand_name,
        c.full_name   AS customer_name,
        c.phone_number,
        g.imei,
        g.status       AS gadget_status,
        (
            SELECT COUNT(*) FROM device_images di WHERE di.session_id = vs.session_id
        ) AS photo_count,
        (
            SELECT GROUP_CONCAT(apr.condition_name SEPARATOR ', ')
            FROM session_rule_details srd
            JOIN ai_pricing_rules apr ON srd.rule_id = apr.rule_id
            WHERE srd.session_id = vs.session_id
        ) AS applied_rules
    FROM valuation_sessions vs
    JOIN device_models dm ON vs.model_id   = dm.model_id
    JOIN brands        b  ON dm.brand_id   = b.brand_id
    LEFT JOIN customers c ON vs.customer_id = c.customer_id
    LEFT JOIN gadgets   g ON g.session_id  = vs.session_id
    {$whereSQL}
    ORDER BY vs.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmtData = $pdo->prepare($dataSQL);
$stmtData->execute(array_merge($params, [
    ':limit'  => $perPage,
    ':offset' => $offset,
]));
$sessions = $stmtData->fetchAll();

// Stats tổng hợp cá nhân (cho summary bar)
$statsRow = $pdo->prepare("
    SELECT
        COUNT(*)                                            AS total,
        SUM(final_status = 'Purchased')                    AS purchased,
        SUM(final_status = 'Declined')                     AS declined,
        SUM(final_status = 'Pending')                      AS pending,
        COALESCE(SUM(CASE WHEN final_status='Purchased' THEN ai_suggested_price ELSE 0 END), 0) AS revenue
    FROM valuation_sessions WHERE user_id = ?
");
$statsRow->execute([$userId]);
$stats = $statsRow->fetch();

/* ============================================================
 * RENDER
 * ============================================================ */
renderHtmlHead('Lịch sử định giá', [
    '../assets/css/pages/staff/history.css'
]);
renderSidebar('history');
renderMainOpen();
renderTopbar('Lịch sử định giá', '<a href="../staff/dashboard.php">Dashboard</a> / Lịch sử');
?>

<div class="page-content">

    <!-- ===== SUMMARY BAR ===== -->
    <div class="history-summary-bar">
        <div class="summary-item">
            <span class="summary-num"><?= $stats['total'] ?></span>
            <span class="summary-label">Tổng phiên</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item summary-item--green">
            <span class="summary-num"><?= $stats['purchased'] ?></span>
            <span class="summary-label">Đã thu mua</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item summary-item--red">
            <span class="summary-num"><?= $stats['declined'] ?></span>
            <span class="summary-label">Từ chối</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item summary-item--yellow">
            <span class="summary-num"><?= $stats['pending'] ?></span>
            <span class="summary-label">Đang chờ</span>
        </div>
        <div class="summary-divider summary-divider--grow"></div>
        <div class="summary-item summary-item--blue">
            <span class="summary-num summary-num--lg" data-vnd="<?= $stats['revenue'] ?>">—</span>
            <span class="summary-label">Tổng giá trị thu mua</span>
        </div>
    </div>

    <!-- ===== FILTER & SEARCH ===== -->
    <div class="card history-filter-card">
        <div class="card-body">
            <form method="GET" class="filter-row" id="filter-form">

                <!-- Tìm kiếm -->
                <div class="search-wrap">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="q" class="form-control search-input"
                           placeholder="Tìm theo hãng, mẫu máy, IMEI..."
                           value="<?= e($searchKw) ?>" autocomplete="off">
                    <?php if ($searchKw): ?>
                        <button type="button" class="search-clear" onclick="clearSearch()" title="Xoá">×</button>
                    <?php endif; ?>
                </div>

                <!-- Filter trạng thái -->
                <div class="filter-status-group">
                    <?php
                    $statuses = [
                        ''          => ['Tất cả',    'filter-btn--all'],
                        'Purchased' => ['Đã mua',    'filter-btn--green'],
                        'Declined'  => ['Từ chối',   'filter-btn--red'],
                        'Pending'   => ['Đang chờ',  'filter-btn--yellow'],
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

                <!-- Nút search (hidden submit) -->
                <button type="submit" class="btn btn-primary btn-sm">Tìm kiếm</button>

            </form>
        </div>
    </div>

    <!-- ===== BẢNG DỮ LIỆU ===== -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Phiên định giá của tôi</div>
                <div class="card-subtitle">
                    <?php if ($totalRecords > 0): ?>
                        <?= number_format($totalRecords) ?> phiên
                        <?= $filterStatus ? ' — lọc: ' . e($filterStatus) : '' ?>
                        <?= $searchKw ? ' — từ khóa: "' . e($searchKw) . '"' : '' ?>
                    <?php else: ?>
                        Không tìm thấy kết quả
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($totalRecords > 0): ?>
                <div class="card-header-right">
                    <span class="pagination-info">
                        Trang <?= $currentPage ?>/<?= $totalPages ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body-flush">
            <?php if (empty($sessions)): ?>
                <div class="empty-state" style="padding: 3rem;">
                    <div class="empty-icon">🔍</div>
                    <div class="empty-title">Không có phiên nào</div>
                    <div class="empty-desc">
                        <?= $searchKw || $filterStatus
                            ? 'Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.'
                            : 'Bạn chưa có phiên định giá nào. Hãy tạo phiên đầu tiên!' ?>
                    </div>
                    <?php if (!$searchKw && !$filterStatus): ?>
                        <a href="../staff/valuation.php" class="btn btn-primary" style="margin-top:1rem;">
                            Tạo phiên định giá
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th width="60">#</th>
                                <th>Thiết bị</th>
                                <th>Khách hàng</th>
                                <th width="80">Pin</th>
                                <th width="140">Giá AI đề xuất</th>
                                <th width="120">IMEI</th>
                                <th width="110">Kết quả</th>
                                <th width="120">Thời gian</th>
                                <th width="70" class="td-center">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                            <tr class="session-row" data-id="<?= $s['session_id'] ?>">

                                <!-- ID -->
                                <td class="td-mono td-primary">#<?= $s['session_id'] ?></td>

                                <!-- Thiết bị -->
                                <td>
                                    <div class="device-info">
                                        <span class="device-brand"><?= e($s['brand_name']) ?></span>
                                        <span class="device-model"><?= e($s['model_name']) ?></span>
                                        <?php if ($s['photo_count'] > 0): ?>
                                            <span class="photo-count-badge">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                <?= $s['photo_count'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Khách hàng -->
                                <td>
                                    <?php if ($s['customer_name']): ?>
                                        <div class="customer-mini">
                                            <span><?= e(truncate($s['customer_name'], 18)) ?></span>
                                            <span class="td-mono text-muted" style="font-size:0.75rem;"><?= e($s['phone_number']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8125rem;">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Pin -->
                                <td>
                                    <div class="battery-cell">
                                        <?php
                                        $bat = (int) $s['battery_health'];
                                        $batClass = $bat >= 80 ? 'bat-good' : ($bat >= 60 ? 'bat-ok' : 'bat-low');
                                        ?>
                                        <div class="bat-bar-mini <?= $batClass ?>" style="width:<?= $bat ?>%"></div>
                                        <span class="td-mono" style="font-size:0.8rem;"><?= $bat ?>%</span>
                                    </div>
                                </td>

                                <!-- Giá AI -->
                                <td>
                                    <span class="price-cell td-mono" data-vnd="<?= $s['ai_suggested_price'] ?>">—</span>
                                </td>

                                <!-- IMEI -->
                                <td>
                                    <?php if ($s['imei']): ?>
                                        <span class="td-mono imei-cell" title="<?= e($s['imei']) ?>">
                                            <?= e(substr($s['imei'], 0, 8)) ?>...
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8125rem;">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Kết quả -->
                                <td><?= sessionStatusBadge($s['final_status']) ?></td>

                                <!-- Thời gian -->
                                <td class="td-mono text-muted" style="font-size:0.8rem;" title="<?= e($s['created_at']) ?>">
                                    <?= timeAgo($s['created_at']) ?>
                                </td>

                                <!-- Nút chi tiết -->
                                <td class="td-center">
                                    <button class="btn-detail"
                                            onclick="showDetail(<?= $s['session_id'] ?>, this)"
                                            data-session='<?= e(json_encode([
                                                'id'           => $s['session_id'],
                                                'brand'        => $s['brand_name'],
                                                'model'        => $s['model_name'],
                                                'battery'      => $s['battery_health'],
                                                'price'        => $s['ai_suggested_price'],
                                                'status'       => $s['final_status'],
                                                'imei'         => $s['imei'],
                                                'gadget_status'=> $s['gadget_status'],
                                                'customer'     => $s['customer_name'],
                                                'phone'        => $s['phone_number'],
                                                'created_at'   => $s['created_at'],
                                                'rules'        => $s['applied_rules'],
                                                'photos'       => $s['photo_count'],
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

                    // Nút Previous
                    if ($currentPage > 1): ?>
                        <a href="<?= $baseUrl ?>&page=<?= $currentPage - 1 ?>" class="page-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        </a>
                    <?php endif;

                    // Số trang
                    $start = max(1, $currentPage - 2);
                    $end   = min($totalPages, $currentPage + 2);
                    if ($start > 1): ?>
                        <a href="<?= $baseUrl ?>&page=1" class="page-btn">1</a>
                        <?php if ($start > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <?php endif;

                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?= $baseUrl ?>&page=<?= $i ?>"
                           class="page-btn <?= $i === $currentPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor;

                    if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                        <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="page-btn"><?= $totalPages ?></a>
                    <?php endif;

                    // Nút Next
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
     MODAL: CHI TIẾT PHIÊN
     ============================================================ -->
<div class="modal-overlay" id="detail-modal" onclick="closeDetailModal(event)">
    <div class="modal modal-detail" role="dialog" aria-modal="true">

        <div class="modal-header">
            <h3 class="modal-title" id="dm-title">Chi tiết phiên #—</h3>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>

        <div class="modal-body">

            <!-- Thiết bị -->
            <div class="detail-section">
                <div class="detail-section-label">Thiết bị</div>
                <div class="detail-device-name" id="dm-device">—</div>
            </div>

            <!-- Grid thông tin -->
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-key">Tỷ lệ pin</span>
                    <span class="detail-val" id="dm-battery">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Giá AI đề xuất</span>
                    <span class="detail-val detail-val--price" id="dm-price">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Kết quả</span>
                    <span class="detail-val" id="dm-status">—</span>
                </div>
                <div class="detail-item">
                    <span class="detail-key">Thời gian</span>
                    <span class="detail-val" id="dm-time">—</span>
                </div>
                <div class="detail-item" id="dm-imei-row">
                    <span class="detail-key">IMEI</span>
                    <span class="detail-val td-mono" id="dm-imei">—</span>
                </div>
                <div class="detail-item" id="dm-gadget-row">
                    <span class="detail-key">Trạng thái kho</span>
                    <span class="detail-val" id="dm-gadget">—</span>
                </div>
            </div>

            <!-- Khách hàng -->
            <div class="detail-section" id="dm-customer-section">
                <div class="detail-section-label">Khách hàng</div>
                <div class="detail-customer-row">
                    <div class="detail-item">
                        <span class="detail-key">Tên</span>
                        <span class="detail-val" id="dm-cname">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-key">SĐT</span>
                        <span class="detail-val td-mono" id="dm-cphone">—</span>
                    </div>
                </div>
            </div>

            <!-- Quy tắc khấu trừ -->
            <div class="detail-section" id="dm-rules-section">
                <div class="detail-section-label">Quy tắc khấu trừ áp dụng</div>
                <div class="detail-val" id="dm-rules">—</div>
            </div>

            <!-- Số ảnh -->
            <div class="detail-section">
                <div class="detail-section-label">Hình ảnh thiết bị</div>
                <div class="detail-val" id="dm-photos">—</div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeDetailModal()">Đóng</button>
        </div>
    </div>
</div>


<?php renderLayoutClose(); ?>


<script>
/* ============================================================
   history.js — inline
   ============================================================ */

/* ---- Xoá search ---- */
function clearSearch() {
    const url = new URL(window.location.href);
    url.searchParams.delete('q');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

/* ---- Format VNĐ (redundant fallback nếu renderLayoutClose chưa chạy) ---- */
document.querySelectorAll('[data-vnd]').forEach(el => {
    const v = parseInt(el.dataset.vnd, 10);
    if (!isNaN(v)) el.textContent = new Intl.NumberFormat('vi-VN', {style:'currency', currency:'VND', maximumFractionDigits:0}).format(v);
});

/* ---- Hiện modal chi tiết ---- */
function showDetail(sessionId, btn) {
    const data = JSON.parse(btn.dataset.session);

    document.getElementById('dm-title').textContent  = `Chi tiết phiên #${data.id}`;
    document.getElementById('dm-device').textContent = `${data.brand} ${data.model}`;
    document.getElementById('dm-battery').textContent= `${data.battery}%`;
    document.getElementById('dm-price').textContent  = formatVND(data.price);
    document.getElementById('dm-time').textContent   = data.created_at;

    // Status badge
    const statusMap = {
        'Purchased': '<span class="badge badge-success">Đã mua</span>',
        'Declined':  '<span class="badge badge-danger">Từ chối</span>',
        'Pending':   '<span class="badge badge-warning">Đang chờ</span>',
    };
    document.getElementById('dm-status').innerHTML = statusMap[data.status] || data.status;

    // IMEI & kho
    if (data.imei) {
        document.getElementById('dm-imei').textContent   = data.imei;
        document.getElementById('dm-imei-row').style.display = '';
    } else {
        document.getElementById('dm-imei-row').style.display = 'none';
    }

    if (data.gadget_status) {
        const gadgetMap = {
            'Stored':       '<span class="badge badge-primary">Trong kho</span>',
            'Refurbishing': '<span class="badge badge-warning">Đang tân trang</span>',
            'Sold':         '<span class="badge badge-success">Đã bán</span>',
        };
        document.getElementById('dm-gadget').innerHTML = gadgetMap[data.gadget_status] || data.gadget_status;
        document.getElementById('dm-gadget-row').style.display = '';
    } else {
        document.getElementById('dm-gadget-row').style.display = 'none';
    }

    // Khách hàng
    if (data.customer) {
        document.getElementById('dm-cname').textContent  = data.customer;
        document.getElementById('dm-cphone').textContent = data.phone;
        document.getElementById('dm-customer-section').style.display = '';
    } else {
        document.getElementById('dm-customer-section').style.display = 'none';
    }

    // Rules
    if (data.rules) {
        document.getElementById('dm-rules').textContent = data.rules;
        document.getElementById('dm-rules-section').style.display = '';
    } else {
        document.getElementById('dm-rules').textContent = 'Không có quy tắc khấu trừ';
        document.getElementById('dm-rules-section').style.display = '';
    }

    // Photos
    document.getElementById('dm-photos').textContent = data.photos > 0
        ? `${data.photos} ảnh đã tải lên`
        : 'Không có ảnh';

    document.getElementById('detail-modal').classList.add('visible');
}

function closeDetailModal(e) {
    if (e && e.target !== document.getElementById('detail-modal')) return;
    document.getElementById('detail-modal').classList.remove('visible');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDetailModal();
});
</script>