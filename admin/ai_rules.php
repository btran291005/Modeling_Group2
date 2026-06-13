<?php
// ============================================================
// FILE: admin/ai_rules.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('admin');

renderHeader('Quy tắc định giá AI');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">⚙️ Quy tắc định giá AI</h2>
        <p class="text-muted mb-0 small">Quản lý các điều kiện khấu trừ khi AI định giá thiết bị.</p>
    </div>
    <button type="button" class="btn btn-primary" id="btn-open-create">
        + Thêm quy tắc
    </button>
</div>

<!-- ============================================================
     KHU VỰC THỐNG KÊ (JS sẽ tính toán & đổ số liệu)
     ============================================================ -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase">Tổng quy tắc</div>
                <div class="fs-3 fw-bold text-primary" id="stat-total">—</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase">Đang bật</div>
                <div class="fs-3 fw-bold text-success" id="stat-active">—</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase">Đã tắt</div>
                <div class="fs-3 fw-bold text-secondary" id="stat-inactive">—</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="small text-muted text-uppercase">Tổng % trừ (đang bật)</div>
                <div class="fs-3 fw-bold text-warning" id="stat-total-pct">—</div>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================
     BẢNG DANH SÁCH QUY TẮC
     ============================================================ -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Điều kiện / Tên quy tắc</th>
                        <th class="text-center" style="width:130px">Khấu trừ (%)</th>
                        <th class="text-center" style="width:100px">Lần dùng</th>
                        <th class="text-center" style="width:130px">Trạng thái</th>
                        <th class="text-center" style="width:140px">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="rules-tbody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Đang tải dữ liệu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL THÊM / SỬA QUY TẮC
     ============================================================ -->
<div class="modal fade" id="modal-rule" tabindex="-1" aria-labelledby="modal-rule-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modal-rule-title">Thêm quy tắc mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body">
                <!-- id="rule-form" — JS dùng để submit -->
                <form id="rule-form" novalidate>
                    <!-- Hidden: chứa ID khi edit, rỗng khi tạo mới -->
                    <input type="hidden" id="rule-id" name="id" value="">

                    <div class="mb-3">
                        <label for="rule-name" class="form-label fw-semibold">
                            Tên / Điều kiện quy tắc <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="rule-name"
                               name="name"
                               placeholder="VD: Màn hình bị vỡ"
                               required>
                        <div class="invalid-feedback">Vui lòng nhập tên quy tắc.</div>
                    </div>

                    <div class="mb-3">
                        <label for="rule-pct" class="form-label fw-semibold">
                            Phần trăm khấu trừ (%) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number"
                                   class="form-control"
                                   id="rule-pct"
                                   name="pct"
                                   min="0"
                                   max="100"
                                   step="0.5"
                                   placeholder="0 – 100"
                                   required>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="invalid-feedback">Giá trị phải từ 0 đến 100.</div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-semibold">Trạng thái</label>
                        <div class="form-check form-switch">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="rule-is-active"
                                   name="is_active"
                                   role="switch"
                                   checked>
                            <label class="form-check-label" for="rule-is-active">
                                Kích hoạt ngay sau khi lưu
                            </label>
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" form="rule-form" class="btn btn-primary" id="btn-rule-submit">
                    Lưu quy tắc
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ============================================================
     MODAL XÁC NHẬN XÓA
     ============================================================ -->
<div class="modal fade" id="modal-delete-rule" tabindex="-1" aria-labelledby="modal-delete-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-danger">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modal-delete-title">⚠️ Xác nhận xóa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body">
                <p class="mb-1">Bạn sắp xóa quy tắc:</p>
                <p class="fw-bold mb-0" id="delete-rule-name">—</p>
                <input type="hidden" id="delete-rule-id" value="">
                <p class="small text-muted mt-2 mb-0">Hành động này không thể hoàn tác.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-delete">
                    Xóa ngay
                </button>
            </div>

        </div>
    </div>
</div>

<?php
renderFooter(['../assets/js/admin_app.js']);