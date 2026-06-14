<?php
// ============================================================
// FILE: staff/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ✅ Chữ hoa 'Staff' — khớp với giá trị lưu trong DB và session
requireRole('Staff');

$fullName = $_SESSION['user']['full_name'] ?? 'Staff';

renderHeader('Staff Dashboard');
?>

<h2 class="mb-1">Chào, <?= htmlspecialchars($fullName) ?> 👋</h2>
<p class="text-muted mb-4">Đây là tổng quan công việc của bạn hôm nay.</p>

<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="card text-bg-primary h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Phiên hôm nay</div>
                <div class="fs-2 fw-bold" id="card-today">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Đã định giá hôm nay</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-info h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Phiên tuần này</div>
                <div class="fs-2 fw-bold" id="card-week">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Tính theo tuần hiện tại</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-success h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Đã thu mua</div>
                <div class="fs-2 fw-bold" id="card-purchased">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Thiết bị đã chốt mua</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-warning h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Tổng vốn đã thu mua</div>
                <div class="fs-2 fw-bold lh-sm" id="card-capital" style="font-size:1.25rem !important;">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Tổng giá AI đề xuất (đã mua)</div>
            </div>
        </div>
    </div>

</div>

<div class="row g-3">

    <!-- ===== Bắt đầu định giá mới ===== -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center py-5">
                <h4 class="mb-2">⚡ Bắt đầu định giá mới</h4>
                <p class="text-muted mb-4">Nhập thông tin thiết bị, để AI tự động tính giá thu mua tối ưu.</p>
                <a href="valuation.php" class="btn btn-primary btn-lg">
                    Tạo phiên định giá
                </a>
            </div>
        </div>
    </div>

    <!-- ===== Hoạt động gần đây ===== -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>🕘 Hoạt động gần đây</span>
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
                                <th>Giá chốt mua</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody id="staff-recent-tbody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                    Đang tải...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="history.php" class="btn btn-sm btn-outline-secondary">Xem toàn bộ lịch sử</a>
            </div>
        </div>
    </div>

</div>

<?php
renderFooter(['../assets/staff_app.js']);