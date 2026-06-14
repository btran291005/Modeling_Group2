<?php
// ============================================================
// FILE: staff/inventory.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ✅ Chữ hoa 'Staff' – khớp với giá trị lưu trong DB và session
requireRole('Staff');

renderHeader('Quản lý Kho - Nhân viên');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-success fw-bold mb-1"><i class="bi bi-box-seam"></i> Quản lý Kho</h2>
            <p class="text-muted mb-0">Xem danh sách thiết bị trong kho và cập nhật trạng thái xử lý.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Quay lại</a>
    </div>

    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-3">
            <input type="text" id="inv-search" class="form-control form-control-lg border-2" placeholder="🔍 Tìm theo IMEI hoặc tên máy...">
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-success text-white fw-bold border-0 rounded-top">
            <i class="bi bi-list-ul"></i> Danh sách thiết bị
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>IMEI</th>
                            <th class="text-start">Thiết bị</th>
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
                            <td colspan="8" class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div> Đang tải dữ liệu...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/staff_app.js']);
?>