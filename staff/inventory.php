<?php
// ============================================================
// FILE: staff/inventory.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ✅ Chữ hoa 'Staff' — khớp với giá trị lưu trong DB và session
requireRole('Staff');

renderHeader('Quản lý Kho - Nhân viên');
?>

<h2 class="mb-1">📦 Quản lý Kho</h2>
<p class="text-muted mb-4">Xem danh sách thiết bị trong kho và cập nhật trạng thái xử lý.</p>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" id="inv-search" class="form-control"
                       placeholder="Tìm theo IMEI hoặc tên máy...">
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Danh sách thiết bị</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>IMEI</th>
                        <th>Thiết bị</th>
                        <th>Cấu hình</th>
                        <th>Pin</th>
                        <th>Giá</th>
                        <th>Khách hàng</th>
                        <th>Ngày nhập</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="inventory-tbody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Đang tải dữ liệu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/js/staff_app.js']);