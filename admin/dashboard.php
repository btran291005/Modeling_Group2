<?php
// ============================================================
// FILE: admin/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Bảng điều khiển Tổng quan');
?>

<h2 class="mb-1">📊 Bảng điều khiển Tổng quan</h2>
<p class="text-muted mb-4">Toàn cảnh hoạt động hệ thống — dữ liệu cập nhật theo thời gian thực.</p>

<!-- ============================================================
     KHU VỰC 1: 4 THẺ THỐNG KÊ TỔNG
     ============================================================ -->
<div class="row g-3 mb-4" id="dashboard-cards">

    <div class="col-md-3">
        <div class="card text-bg-primary h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Tổng nhân sự</div>
                <div class="fs-2 fw-bold" id="card-total-staff">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Admin + Staff đang hoạt động</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-info h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Máy tồn kho</div>
                <div class="fs-2 fw-bold" id="card-in-stock">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Chưa bán (Stored + Refurbishing)</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-success h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Đã bán</div>
                <div class="fs-2 fw-bold" id="card-sold">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Tổng thiết bị đã xuất kho</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-warning h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Tổng vốn đã chi</div>
                <div class="fs-2 fw-bold lh-sm" id="card-total-spent" style="font-size:1.25rem !important;">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </div>
                <div class="small opacity-75">Tổng giá AI đề xuất (đã mua)</div>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================
     KHU VỰC 2: 2 BẢNG DỮ LIỆU
     ============================================================ -->
<div class="row g-3">

    <!-- ===== CỘT TRÁI: Thống kê theo Hãng ===== -->
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>📦 Tồn kho theo Hãng</span>
                <span class="badge bg-secondary" id="badge-brands">—</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Hãng</th>
                                <th class="text-end">Số lượng</th>
                            </tr>
                        </thead>
                        <tbody id="brands-tbody">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                    Đang tải...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== CỘT PHẢI: 5 Giao dịch mới nhất ===== -->
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>🕘 Giao dịch mới nhất</span>
                <a href="valuation_log.php" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày giờ</th>
                                <th>Tên máy</th>
                                <th>Staff</th>
                                <th>IMEI</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody id="recent-tbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                    Đang tải...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Thông báo lỗi toàn cục (ẩn mặc định) -->
<div id="dashboard-error" class="alert alert-danger mt-3 d-none" role="alert">
    ⚠️ Không thể tải dữ liệu bảng điều khiển. Vui lòng tải lại trang.
</div>

<?php
renderFooter(['../assets/admin_app.js']);