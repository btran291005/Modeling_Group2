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

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.55rem;color:#e6f0fa;letter-spacing:-.3px;">
            🗄️ Dữ liệu Cấu hình & Giá
        </h2>
        <p class="mb-0" style="color:#8fa8be;font-size:1rem;">
            Quản lý Hãng sản xuất và Dòng máy, cấu hình Giá sàn (Base Price) cho AI định giá.
        </p>
    </div>
</div>

<div class="row g-4">

    <!-- ===================== PHẦN 1: HÃNG ===================== -->
    <div class="col-md-4">
        <div class="rounded-3 h-100" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">

            <div class="px-4 py-3 d-flex align-items-center gap-2"
                 style="border-bottom:1px solid rgba(255,255,255,.06);">
                <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">🏭 Quản lý Hãng</span>
            </div>

            <div class="p-4">
                <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Tên hãng mới
                </label>
                <div class="input-group mb-4">
                    <input type="text" id="new-brand-name"
                           class="form-control"
                           placeholder="VD: Apple"
                           style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px 0 0 10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                    <button type="button" id="btn-add-brand"
                            class="btn fw-bold"
                            style="background:rgba(13,202,240,.13);border:1px solid rgba(13,202,240,.3);color:#0dcaf0;font-size:1rem;padding:0 18px;border-radius:0 10px 10px 0;">
                        ➕ Thêm
                    </button>
                </div>

                <div style="height:1px;background:rgba(255,255,255,.06);margin-bottom:1.25rem;"></div>

                <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Chọn hãng để xem dòng máy
                </label>
                <select id="brand-select"
                        class="form-select"
                        style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                    <option value="">-- Chọn hãng --</option>
                </select>
                <div class="mt-2" style="font-size:.9rem;color:#5a7a94;">
                    Chọn một hãng để hiện danh sách dòng máy bên phải.
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== PHẦN 2: DÒNG MÁY ===================== -->
    <div class="col-md-8">
        <div class="rounded-3 h-100" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">

            <div class="d-flex align-items-center justify-content-between px-4 py-3"
                 style="border-bottom:1px solid rgba(255,255,255,.06);">
                <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">📱 Quản lý Dòng máy</span>
                <button type="button"
                        class="btn btn-sm d-flex align-items-center gap-2"
                        data-bs-toggle="modal" data-bs-target="#modal-add-model"
                        style="background:rgba(13,202,240,.12);border:1px solid rgba(13,202,240,.3);color:#0dcaf0;font-weight:600;font-size:1rem;padding:7px 15px;border-radius:8px;">
                    ➕ Thêm Dòng máy
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless table-hover align-middle mb-0">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                            <th class="px-4 py-3" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:60px;">ID</th>
                            <th class="py-3"       style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">Dòng máy</th>
                            <th class="py-3"       style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:130px;">RAM / ROM</th>
                            <th class="py-3"       style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);min-width:240px;">Giá sàn (VNĐ)</th>
                            <th class="px-4 py-3"  style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:80px;">–</th>
                        </tr>
                    </thead>
                    <tbody id="models-tbody">
                        <tr>
                            <td colspan="5" class="text-center py-5"
                                style="color:#8fa8be;font-size:1rem;">
                                ← Vui lòng chọn hãng để xem dòng máy.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>


<!-- ============================================================
     MODAL: THÊM DÒNG MÁY
     ============================================================ -->
<div class="modal fade" id="modal-add-model" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#080e1a;border:1px solid rgba(255,255,255,.08);border-radius:16px;">
            <form id="form-add-model">

                <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.07);padding:20px 24px;">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" style="color:#e6f0fa;font-size:1.2rem;">➕ Thêm dòng máy mới</h5>
                        <p class="mb-0 mt-1" style="font-size:1rem;color:#8fa8be;">Dòng máy sẽ được thêm vào hãng đang chọn.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="padding:22px 24px;">

                    <div class="mb-3">
                        <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">Hãng</label>
                        <input type="text" id="model-brand-display"
                               class="form-control" disabled
                               style="background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.07);border-radius:10px;color:#5a7a94;font-size:1rem;padding:11px 14px;">
                        <small class="mt-1 d-block" style="color:#5a7a94;font-size:.9rem;">Dòng máy sẽ thuộc hãng đang chọn ở bảng bên.</small>
                    </div>

                    <div class="mb-3">
                        <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                            Tên dòng máy <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="model_name" id="model-name-input"
                               class="form-control" placeholder="VD: iPhone 16 Pro" required
                               style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">RAM (GB)</label>
                            <input type="number" name="ram_gb" id="model-ram-input"
                                   class="form-control" min="0" value="0"
                                   style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                        </div>
                        <div class="col-6">
                            <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">ROM (GB)</label>
                            <input type="number" name="rom_gb" id="model-rom-input"
                                   class="form-control" min="0" value="0"
                                   style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                            Giá sàn (VNĐ) <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="base_price" id="model-price-input"
                               class="form-control" min="0" step="100000" required
                               style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                    </div>

                </div>

                <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.07);padding:16px 24px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-sm fw-bold"
                            style="background:rgba(13,202,240,.15);border:1px solid rgba(13,202,240,.4);color:#0dcaf0;padding:8px 18px;">
                        ✅ Thêm dòng máy
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
/* ── Table rows ── */
#models-tbody td {
    font-size: 1rem;
    color: #c8d8ea;
    padding-top: 14px !important;
    padding-bottom: 14px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
    vertical-align: middle;
}
#models-tbody tr:last-child td { border-bottom: none !important; }
#models-tbody tr:hover td { background: rgba(13,202,240,.025) !important; }

/* ── Price input group inside table ── */
.model-price-input {
    background: rgba(0,0,0,.4) !important;
    border: 1px solid rgba(255,255,255,.09) !important;
    border-radius: 8px 0 0 8px !important;
    color: #c8d8ea !important;
    font-size: 1rem;
}
.model-price-input:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.08) !important;
    background: rgba(0,0,0,.55) !important;
}
.btn-save-price {
    background: rgba(13,202,240,.1) !important;
    border: 1px solid rgba(13,202,240,.28) !important;
    border-left: none !important;
    color: #0dcaf0 !important;
    border-radius: 0 8px 8px 0 !important;
    font-size: .9rem;
    font-weight: 700;
    padding: 0 12px;
    transition: all .2s;
}
.btn-save-price:hover {
    background: rgba(13,202,240,.22) !important;
    color: #4dd4f5 !important;
}

/* ── Modal inputs ── */
.modal-content .form-control:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
}
.modal-content .form-control::placeholder { color: #5a7a94; }
.modal-content .form-control:disabled { opacity: .55; cursor: not-allowed; }

/* ── Brand select ── */
#brand-select:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
    outline: none;
}
#brand-select option { background: #0a101d; }

/* ── New brand input ── */
#new-brand-name:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
    outline: none;
}
#new-brand-name::placeholder { color: #5a7a94; }
</style>

<?php
renderFooter(['../assets/admin_app.js']);
?>