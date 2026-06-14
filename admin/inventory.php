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

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.55rem;color:#e6f0fa;letter-spacing:-.3px;">
            📦 Quản lý Kho Tổng
        </h2>
        <p class="mb-0" style="color:#8fa8be;font-size:1rem;">
            Xem toàn bộ thiết bị trong kho, tìm kiếm theo IMEI hoặc tên máy, và xử lý thiết bị.
        </p>
    </div>
</div>

<!-- Thanh tìm kiếm -->
<div class="rounded-3 mb-4" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
    <div class="px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">
            🔍 Bộ lọc tìm kiếm
        </span>
    </div>
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Từ khoá
                </label>
                <input type="text" id="admin-inv-search" class="form-control"
                       placeholder="Tìm theo IMEI hoặc tên máy..."
                       style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
            </div>
            <div class="col-md-3">
                <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Trạng thái
                </label>
                <select id="admin-inv-status" class="form-select"
                        style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Stored">Đang lưu kho</option>
                    <option value="Refurbishing">Đang tân trang</option>
                    <option value="Sold">Đã bán</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-inv-search"
                        class="btn w-100 fw-bold"
                        style="background:rgba(13,202,240,.13);border:1px solid rgba(13,202,240,.3);color:#0dcaf0;font-size:1rem;padding:11px 14px;border-radius:10px;">
                    🔍 Lọc
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-inv-reset"
                        class="btn w-100 fw-bold"
                        style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#8fa8be;font-size:1rem;padding:11px 14px;border-radius:10px;">
                    ↺ Đặt lại
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bảng danh sách thiết bị -->
<div class="rounded-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
    <div class="d-flex align-items-center justify-content-between px-4 py-3"
         style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">🗃️ Danh sách thiết bị trong kho</span>
        <span id="admin-inv-count"
              class="fw-bold"
              style="font-size:.9rem;background:rgba(13,202,240,.12);border:1px solid rgba(13,202,240,.25);color:#0dcaf0;padding:4px 14px;border-radius:20px;letter-spacing:.3px;">
            0 thiết bị
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-borderless table-hover align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <th class="px-4 py-3" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">IMEI</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">📱 Thiết bị</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">⚙️ Cấu hình</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:80px;">🔋 Pin</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">💰 Giá thu mua</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">👤 Khách hàng</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">🧑‍💼 Nhân viên</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">📅 Ngày nhập</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">Trạng thái</th>
                    <th class="px-4 py-3" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:90px;">Thao tác</th>
                </tr>
            </thead>
            <tbody id="admin-inv-tbody">
                <tr>
                    <td colspan="10" class="text-center py-5" style="color:#8fa8be;font-size:1rem;">
                        ⏳ Đang tải dữ liệu...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
/* ── Table rows ── */
#admin-inv-tbody td {
    font-size: 1rem;
    color: #c8d8ea;
    padding-top: 14px !important;
    padding-bottom: 14px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
    vertical-align: middle;
}
#admin-inv-tbody tr:last-child td { border-bottom: none !important; }
#admin-inv-tbody tr:hover td { background: rgba(13,202,240,.025) !important; }

/* ── Search inputs ── */
#admin-inv-search:focus,
#admin-inv-status:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
    outline: none;
}
#admin-inv-search::placeholder { color: #5a7a94; }
#admin-inv-status option { background: #0a101d; }

/* ── Status badges rendered by JS ── */
.badge-stored       { background: rgba(13,202,240,.15); color: #0dcaf0; border: 1px solid rgba(13,202,240,.3); }
.badge-refurbishing { background: rgba(255,193,7,.13);  color: #ffc107; border: 1px solid rgba(255,193,7,.3); }
.badge-sold         { background: rgba(25,135,84,.15);  color: #20c997; border: 1px solid rgba(25,135,84,.3); }
</style>

<?php
renderFooter(['../assets/admin_app.js']);
?>