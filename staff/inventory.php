<?php
// ============================================================
// FILE: staff/inventory.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Staff');

renderHeader('Quản lý Kho - Nhân viên');
?>

<div class="mb-4">
    <h2 class="fw-bold mb-1">📦 Quản lý Kho</h2>
    <p class="text-secondary mb-0">Xem danh sách thiết bị trong kho và cập nhật trạng thái xử lý.</p>
</div>

<div class="card mb-3 bg-dark-subtle rounded-3 shadow-sm border-secondary border-opacity-25">
    <div class="card-body p-4">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small text-info fw-semibold">🔍 Tìm kiếm</label>
                <input type="text" id="inv-search" class="form-control"
                       placeholder="Tìm theo IMEI hoặc tên máy...">
            </div>
        </div>
    </div>
</div>

<div class="card bg-dark-subtle rounded-3 shadow-sm border-secondary border-opacity-25">
    <div class="card-header bg-transparent border-secondary border-opacity-25 fw-bold">
        🗃️ Danh sách thiết bị
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-borderless table-hover align-middle mb-0">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <th class="px-3 text-info text-uppercase small fw-bold">IMEI</th>
                        <th class="text-info text-uppercase small fw-bold">📱 Thiết bị</th>
                        <th class="text-info text-uppercase small fw-bold">⚙️ Cấu hình</th>
                        <th class="text-info text-uppercase small fw-bold">🔋 Pin</th>
                        <th class="text-info text-uppercase small fw-bold">💰 Giá</th>
                        <th class="text-info text-uppercase small fw-bold">👤 Khách hàng</th>
                        <th class="text-info text-uppercase small fw-bold">📅 Ngày nhập</th>
                        <th class="px-3 text-info text-uppercase small fw-bold">Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="inventory-tbody">
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            ⏳ Đang tải dữ liệu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/staff_app.js']);