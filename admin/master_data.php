<?php
// ============================================================
// FILE: admin/master_data.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Dữ liệu Cấu hình & Giá');
?>

<h2 class="mb-1">🗂 Dữ liệu Cấu hình & Giá</h2>
<p class="text-muted mb-4">Quản lý Hãng sản xuất và Dòng máy, cấu hình Giá sàn (Base Price) cho AI định giá.</p>

<div class="row g-3">

    <!-- ===================== PHẦN 1: HÃNG ===================== -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Quản lý Hãng</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Tên hãng mới</label>
                    <div class="input-group">
                        <input type="text" id="new-brand-name" class="form-control" placeholder="VD: Apple">
                        <button type="button" id="btn-add-brand" class="btn btn-primary">Thêm Hãng</button>
                    </div>
                </div>

                <hr>

                <div class="mb-2">
                    <label class="form-label">Chọn hãng để xem dòng máy</label>
                    <select id="brand-select" class="form-select">
                        <option value="">-- Chọn hãng --</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== PHẦN 2: DÒNG MÁY ===================== -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Quản lý Dòng máy</span>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-model">
                    ➕ Thêm Dòng máy
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Dòng máy</th>
                                <th>RAM/ROM</th>
                                <th>Giá sàn (VNĐ)</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="models-tbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Vui lòng chọn hãng.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>


<!-- ============================================================
     MODAL: THÊM DÒNG MÁY
     ============================================================ -->
<div class="modal fade" id="modal-add-model" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-add-model">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm dòng máy mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Hãng</label>
                        <input type="text" id="model-brand-display" class="form-control" disabled>
                        <div class="form-text">Dòng máy sẽ thuộc hãng đang chọn ở trên.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên dòng máy</label>
                        <input type="text" name="model_name" id="model-name-input" class="form-control" placeholder="VD: iPhone 16 Pro" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">RAM (GB)</label>
                            <input type="number" name="ram_gb" id="model-ram-input" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">ROM (GB)</label>
                            <input type="number" name="rom_gb" id="model-rom-input" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giá sàn (VNĐ)</label>
                        <input type="number" name="base_price" id="model-price-input" class="form-control" min="0" step="100000" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-primary">Thêm dòng máy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/admin_app.js']);