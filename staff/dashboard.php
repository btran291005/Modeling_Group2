<?php
// ============================================================
// FILE: staff/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Staff');

$fullName = $_SESSION['user']['full_name'] ?? 'Staff';

renderHeader('Staff Dashboard');
?>

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1">👋 Chào, <?= htmlspecialchars($fullName) ?></h2>
        <p class="text-secondary mb-0">Đây là tổng quan công việc của bạn hôm nay.</p>
    </div>
    <a href="valuation.php" class="btn btn-info fw-bold d-flex align-items-center gap-2">
        ⚡ Định giá mới
    </a>
</div>

<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="card text-bg-primary h-100 bg-dark-subtle border-primary border-opacity-25 rounded-3 shadow-sm">
            <div class="card-body p-4">
                <div class="small text-uppercase fw-bold text-info opacity-75 mb-2">📅 Phiên hôm nay</div>
                <div class="fs-2 fw-bold text-info" id="card-today">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small text-secondary mt-1">Đã định giá hôm nay</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 bg-dark-subtle border-secondary border-opacity-25 rounded-3 shadow-sm">
            <div class="card-body p-4">
                <div class="small text-uppercase fw-bold text-light opacity-75 mb-2">🗓️ Phiên tuần này</div>
                <div class="fs-2 fw-bold" id="card-week">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small text-secondary mt-1">Tính theo tuần hiện tại</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 bg-dark-subtle border-success border-opacity-25 rounded-3 shadow-sm">
            <div class="card-body p-4">
                <div class="small text-uppercase fw-bold text-success opacity-75 mb-2">✅ Đã thu mua</div>
                <div class="fs-2 fw-bold text-success" id="card-purchased">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small text-secondary mt-1">Thiết bị đã chốt mua</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 bg-dark-subtle border-warning border-opacity-25 rounded-3 shadow-sm">
            <div class="card-body p-4">
                <div class="small text-uppercase fw-bold text-warning opacity-75 mb-2">💰 Tổng vốn đã thu mua</div>
                <div class="fw-bold text-warning lh-sm" id="card-capital" style="font-size:1.4rem;">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small text-secondary mt-1">Tổng giá AI đề xuất (đã mua)</div>
            </div>
        </div>
    </div>

</div>

<!-- ===== Bắt đầu định giá mới – banner ngang full-width ===== -->
<div class="card bg-dark-subtle rounded-3 shadow-sm border-info border-opacity-25 mb-4">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size:2.5rem;" class="lh-1">⚡</div>
            <div>
                <h4 class="mb-1 fw-bold">Bắt đầu định giá mới</h4>
                <p class="text-secondary mb-0">Nhập thông tin thiết bị, để AI tự động tính giá thu mua tối ưu.</p>
            </div>
        </div>
        <a href="valuation.php" class="btn btn-info btn-lg fw-bold px-4 flex-shrink-0">
            🤖 Tạo phiên định giá
        </a>
    </div>
</div>

<!-- ===== Hoạt động gần đây – full width ===== -->
<div class="card bg-dark-subtle rounded-3 shadow-sm border-secondary border-opacity-25">
    <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-secondary border-opacity-25">
        <span class="fw-bold">🕘 Hoạt động gần đây</span>
        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-2" id="staff-recent-count">0 phiên</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-borderless table-hover align-middle mb-0">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <th class="px-3 text-info text-uppercase small fw-bold">#</th>
                        <th class="text-info text-uppercase small fw-bold text-nowrap">📅 Ngày giờ</th>
                        <th class="text-info text-uppercase small fw-bold">📱 Thiết bị</th>
                        <th class="text-info text-uppercase small fw-bold">IMEI</th>
                        <th class="text-info text-uppercase small fw-bold text-nowrap">🤖 Giá AI đề xuất</th>
                        <th class="text-info text-uppercase small fw-bold text-nowrap">💰 Giá chốt mua</th>
                        <th class="px-3 text-info text-uppercase small fw-bold">Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="staff-recent-tbody">
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Đang tải...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-end bg-transparent border-secondary border-opacity-25">
        <a href="history.php" class="btn btn-sm btn-outline-info">Xem toàn bộ lịch sử →</a>
    </div>
</div>

<?php
renderFooter(['../assets/staff_app.js']);