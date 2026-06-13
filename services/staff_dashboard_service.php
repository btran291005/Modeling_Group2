<?php
// ============================================================
// FILE: staff/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Staff');

renderHeader('Tổng quan của tôi');
?>

<h2 class="mb-1">👋 Xin chào, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Nhân viên') ?>!</h2>
<p class="text-muted mb-4">Đây là tổng quan hoạt động định giá của bạn.</p>

<!-- ── CARD METRICS ── -->
<div class="row g-3 mb-4" id="staff-cards">

    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column align-items-start gap-2">
                <span class="fs-2">📋</span>
                <div class="text-muted small">Phiên hôm nay</div>
                <div class="fs-3 fw-bold text-primary" id="card-today">—</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column align-items-start gap-2">
                <span class="fs-2">📅</span>
                <div class="text-muted small">Phiên tuần này</div>
                <div class="fs-3 fw-bold text-info" id="card-week">—</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column align-items-start gap-2">
                <span class="fs-2">✅</span>
                <div class="text-muted small">Máy đã thu mua</div>
                <div class="fs-3 fw-bold text-success" id="card-purchased">—</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column align-items-start gap-2">
                <span class="fs-2">💰</span>
                <div class="text-muted small">Tổng vốn đã thu</div>
                <div class="fs-4 fw-bold text-warning" id="card-capital">—</div>
            </div>
        </div>
    </div>

</div>

<!-- ── HOẠT ĐỘNG GẦN ĐÂY ── -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>🕒 Hoạt động gần đây</span>
        <span class="badge bg-secondary" id="staff-recent-count">0 phiên</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Ngày giờ</th>
                        <th>Thiết bị</th>
                        <th>IMEI</th>
                        <th>Giá AI đề xuất</th>
                        <th>Giá chốt</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="staff-recent-tbody">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Đang tải...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="history.php" class="btn btn-sm btn-outline-primary">Xem toàn bộ lịch sử →</a>
    </div>
</div>

<?php
renderFooter(['../assets/js/staff_app.js']);
?>