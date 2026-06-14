<?php
// ============================================================
// FILE: admin/inventory.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Quản lý Kho Tổng');
?>

<h2 class="mb-1">📦 Quản lý Kho Tổng</h2>
<p class="text-muted mb-4">Xem toàn bộ thiết bị trong kho, tìm kiếm theo IMEI hoặc tên máy, và xử lý thiết bị.</p>

<!-- Thanh tìm kiếm -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Tìm kiếm</label>
                <input type="text" id="admin-inv-search" class="form-control"
                       placeholder="Tìm theo IMEI hoặc tên máy...">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Trạng thái</label>
                <select id="admin-inv-status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Stored">Đang lưu kho</option>
                    <option value="Refurbishing">Đang tân trang</option>
                    <option value="Sold">Đã bán</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-inv-search" class="btn btn-info w-100">
                    🔍 Lọc
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-inv-reset" class="btn btn-outline-secondary w-100">
                    ↺ Đặt lại
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bảng danh sách thiết bị -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Danh sách thiết bị trong kho</span>
        <span class="badge bg-secondary" id="admin-inv-count">0 thiết bị</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>IMEI</th>
                        <th>Thiết bị</th>
                        <th>Cấu hình</th>
                        <th>Pin (%)</th>
                        <th>Giá thu mua</th>
                        <th>Khách hàng</th>
                        <th>Nhân viên</th>
                        <th>Ngày nhập</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="admin-inv-tbody">
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