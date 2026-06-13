<?php
// ============================================================
// FILE: admin/history.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Nhật ký Định giá toàn Hệ thống');
?>

<h2 class="mb-1">📋 Nhật ký Định giá toàn Hệ thống</h2>
<p class="text-muted mb-4">Xem toàn bộ lịch sử định giá của tất cả nhân viên, lọc theo nhân viên, tên máy hoặc trạng thái.</p>

<!-- Bộ lọc -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tìm kiếm</label>
                <input type="text" id="admin-history-search" class="form-control"
                       placeholder="Tên nhân viên hoặc tên máy...">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Trạng thái phiên</label>
                <select id="admin-history-status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Pending">⏳ Đang chờ</option>
                    <option value="Purchased">✅ Đã thu mua</option>
                    <option value="Declined">❌ Từ chối</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-history-search" class="btn btn-primary w-100">
                    🔍 Lọc
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-history-reset" class="btn btn-outline-secondary w-100">
                    ↺ Đặt lại
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bảng nhật ký -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Nhật ký định giá</span>
        <span class="badge bg-secondary" id="admin-history-count">0 phiên</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Ngày giờ</th>
                        <th>Nhân viên</th>
                        <th>Thiết bị</th>
                        <th>Cấu hình</th>
                        <th>Pin (%)</th>
                        <th>IMEI</th>
                        <th>Giá AI đề xuất</th>
                        <th>Giá chốt mua</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="admin-history-tbody">
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">Đang tải dữ liệu...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/admin_app.js']);
?>