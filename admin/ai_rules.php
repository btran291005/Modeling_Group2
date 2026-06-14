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

requireRole('Admin');

renderHeader('Quy tắc định giá AI');
?>

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.55rem;color:#e6f0fa;letter-spacing:-.3px;">
            ⚙️ Quy tắc định giá AI
        </h2>
        <p class="mb-0" style="color:#8fa8be;font-size:1rem;">
            Quản lý các điều kiện khấu trừ khi AI định giá thiết bị.
        </p>
    </div>
    <button type="button" id="btn-open-create"
            class="btn fw-bold d-flex align-items-center gap-2"
            style="background:rgba(13,202,240,.13);border:1px solid rgba(13,202,240,.3);color:#0dcaf0;font-size:1rem;padding:9px 18px;border-radius:10px;">
        ➕ Thêm quy tắc
    </button>
</div>

<!-- ============================================================
     THỐNG KÊ
     ============================================================ -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-3">
        <div class="rounded-3 text-center h-100 p-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="mb-1" style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">📊 Tổng quy tắc</div>
            <div class="fw-bold" id="stat-total" style="font-size:2rem;color:#e6f0fa;">–</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rounded-3 text-center h-100 p-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="mb-1" style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">✅ Đang bật</div>
            <div class="fw-bold" id="stat-active" style="font-size:2rem;color:#20c997;">–</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rounded-3 text-center h-100 p-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="mb-1" style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">🚫 Đã tắt</div>
            <div class="fw-bold" id="stat-inactive" style="font-size:2rem;color:#8fa8be;">–</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="rounded-3 text-center h-100 p-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="mb-1" style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">💸 Tổng % trừ (bật)</div>
            <div class="fw-bold" id="stat-total-pct" style="font-size:2rem;color:#ffc107;">–</div>
        </div>
    </div>

</div>

<!-- ============================================================
     BẢNG DANH SÁCH QUY TẮC
     ============================================================ -->
<div class="rounded-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
    <div class="px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">📋 Danh sách quy tắc</span>
    </div>
    <div class="table-responsive">
        <table class="table table-borderless table-hover align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <th class="px-4 py-3" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:40px;">#</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">Điều kiện / Tên quy tắc</th>
                    <th class="py-3 text-center" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:130px;">Khấu trừ (%)</th>
                    <th class="py-3 text-center" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:100px;">Lần dùng</th>
                    <th class="py-3 text-center" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:130px;">Trạng thái</th>
                    <th class="px-4 py-3 text-center" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:140px;">Thao tác</th>
                </tr>
            </thead>
            <tbody id="rules-tbody">
                <tr>
                    <td colspan="6" class="text-center py-5" style="color:#8fa8be;font-size:1rem;">
                        <span class="spinner-border spinner-border-sm me-2" style="color:#0dcaf0;"></span>
                        Đang tải dữ liệu...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     MODAL THÊM / SỬA QUY TẮC
     ============================================================ -->
<div class="modal fade" id="modal-rule" tabindex="-1" aria-labelledby="modal-rule-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#080e1a;border:1px solid rgba(255,255,255,.08);border-radius:16px;">

            <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.07);padding:20px 24px;">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modal-rule-title" style="color:#e6f0fa;font-size:1.2rem;">➕ Thêm quy tắc mới</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body" style="padding:22px 24px;">
                <form id="rule-form" novalidate>
                    <input type="hidden" id="rule-id" name="id" value="">

                    <div class="mb-3">
                        <label for="rule-name" class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                            Tên / Điều kiện quy tắc <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="rule-name" name="name"
                               placeholder="VD: Màn hình bị vỡ" required
                               style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                        <div class="invalid-feedback">Vui lòng nhập tên quy tắc.</div>
                    </div>

                    <div class="mb-3">
                        <label for="rule-pct" class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                            Phần trăm khấu trừ (%) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="rule-pct" name="pct"
                                   min="0" max="100" step="0.5" placeholder="0 – 100" required
                                   style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px 0 0 10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                            <span class="input-group-text fw-bold"
                                  style="background:rgba(13,202,240,.1);border:1px solid rgba(255,255,255,.09);border-left:none;border-radius:0 10px 10px 0;color:#0dcaf0;font-size:1rem;">%</span>
                        </div>
                        <div class="invalid-feedback">Giá trị phải từ 0 đến 100.</div>
                    </div>

                    <div class="mb-1">
                        <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">Trạng thái</label>
                        <div class="form-check form-switch ps-0 d-flex align-items-center gap-3">
                            <input type="checkbox" class="form-check-input ms-0" id="rule-is-active"
                                   name="is_active" role="switch" checked
                                   style="width:2.5em;height:1.3em;cursor:pointer;">
                            <label class="form-check-label" for="rule-is-active"
                                   style="color:#c8d8ea;font-size:1rem;">
                                Kích hoạt ngay sau khi lưu
                            </label>
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.07);padding:16px 24px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                <button type="submit" form="rule-form" id="btn-rule-submit"
                        class="btn btn-sm fw-bold"
                        style="background:rgba(13,202,240,.15);border:1px solid rgba(13,202,240,.4);color:#0dcaf0;padding:8px 18px;">
                    💾 Lưu quy tắc
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
        <div class="modal-content" style="background:#080e1a;border:1px solid rgba(220,53,69,.35);border-radius:16px;">

            <div class="modal-header" style="background:rgba(220,53,69,.15);border-bottom:1px solid rgba(220,53,69,.2);padding:16px 20px;">
                <h5 class="modal-title fw-bold mb-0" id="modal-delete-title" style="color:#f87171;font-size:1rem;">⚠️ Xác nhận xoá</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body" style="padding:20px;">
                <p class="mb-1" style="color:#8fa8be;font-size:1rem;">Bạn sắp xoá quy tắc:</p>
                <p class="fw-bold mb-0" id="delete-rule-name" style="color:#e6f0fa;font-size:1rem;">–</p>
                <input type="hidden" id="delete-rule-id" value="">
                <p class="mt-2 mb-0" style="color:#5a7a94;font-size:.9rem;">Hành động này không thể hoàn tác.</p>
            </div>

            <div class="modal-footer" style="border-top:1px solid rgba(220,53,69,.15);padding:12px 20px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                <button type="button" id="btn-confirm-delete"
                        class="btn btn-sm fw-bold"
                        style="background:rgba(220,53,69,.2);border:1px solid rgba(220,53,69,.4);color:#f87171;padding:7px 16px;">
                    🗑️ Xoá ngay
                </button>
            </div>

        </div>
    </div>
</div>

<style>
#rules-tbody td {
    font-size: 1rem;
    color: #c8d8ea;
    padding-top: 14px !important;
    padding-bottom: 14px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
    vertical-align: middle;
}
#rules-tbody tr:last-child td { border-bottom: none !important; }
#rules-tbody tr:hover td { background: rgba(13,202,240,.025) !important; }

#rule-form .form-control:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
}
#rule-form .form-control::placeholder { color: #5a7a94; }
</style>

<?php
renderFooter(['../assets/admin_app.js']);
?>