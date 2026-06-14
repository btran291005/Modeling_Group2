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

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.55rem;color:#e6f0fa;letter-spacing:-.3px;">
            📋 Nhật ký Định giá toàn Hệ thống
        </h2>
        <p class="mb-0" style="color:#8fa8be;font-size:.9rem;">
            Xem toàn bộ lịch sử định giá của tất cả nhân viên, lọc theo nhân viên, tên máy hoặc trạng thái.
        </p>
    </div>
</div>

<!-- Bộ lọc -->
<div class="rounded-3 mb-4" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
    <div class="px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:.85rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">
            🔍 Bộ lọc tìm kiếm
        </span>
    </div>
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="d-block mb-2" style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Từ khoá
                </label>
                <input type="text" id="admin-history-search" class="form-control"
                       placeholder="Tên nhân viên hoặc tên máy..."
                       style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:.95rem;padding:11px 14px;">
            </div>
            <div class="col-md-3">
                <label class="d-block mb-2" style="font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Trạng thái phiên
                </label>
                <select id="admin-history-status" class="form-select"
                        style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:.95rem;padding:11px 14px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Pending">⏳ Đang chờ</option>
                    <option value="Purchased">✅ Đã thu mua</option>
                    <option value="Declined">❌ Từ chối</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-history-search"
                        class="btn w-100 fw-bold"
                        style="background:rgba(13,202,240,.13);border:1px solid rgba(13,202,240,.3);color:#0dcaf0;font-size:.9rem;padding:11px 14px;border-radius:10px;">
                    🔍 Lọc
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-history-reset"
                        class="btn w-100 fw-bold"
                        style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#8fa8be;font-size:.9rem;padding:11px 14px;border-radius:10px;">
                    ↺ Đặt lại
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bảng nhật ký -->
<div class="rounded-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
    <div class="d-flex align-items-center justify-content-between px-4 py-3"
         style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">🗂️ Nhật ký định giá</span>
        <span id="admin-history-count"
              class="fw-bold"
              style="font-size:.82rem;background:rgba(13,202,240,.12);border:1px solid rgba(13,202,240,.25);color:#0dcaf0;padding:4px 14px;border-radius:20px;letter-spacing:.3px;">
            0 phiên
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-borderless table-hover align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <th class="px-4 py-3" style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:40px;">#</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">📅 Ngày giờ</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">🧑‍💼 Nhân viên</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">📱 Thiết bị</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">⚙️ Cấu hình</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:75px;">🔋 Pin</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">IMEI</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">🤖 Giá AI</th>
                    <th class="py-3"      style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">💰 Giá chốt</th>
                    <th class="px-4 py-3" style="font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">Trạng thái</th>
                </tr>
            </thead>
            <tbody id="admin-history-tbody">
                <tr>
                    <td colspan="10" class="text-center py-5" style="color:#8fa8be;font-size:.95rem;">
                        ⏳ Đang tải dữ liệu...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
/* ── Table rows ── */
#admin-history-tbody td {
    font-size: .88rem;
    color: #c8d8ea;
    padding-top: 13px !important;
    padding-bottom: 13px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
    vertical-align: middle;
}
#admin-history-tbody tr:last-child td { border-bottom: none !important; }
#admin-history-tbody tr:hover td     { background: rgba(13,202,240,.025) !important; }

/* ── Declined row dim ── */
#admin-history-tbody tr.row-declined td { opacity: .5; }

/* ── Avatar chip (rendered by JS) ── */
.staff-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8rem; font-weight: 700;
    background: rgba(13,202,240,.18);
    color: #0dcaf0;
    flex-shrink: 0;
}

/* ── Status badges ── */
.badge-pending   { background:rgba(255,193,7,.15);  color:#ffc107; border:1px solid rgba(255,193,7,.3);  font-size:.8rem; padding:4px 10px; border-radius:20px; font-weight:700; white-space:nowrap; }
.badge-purchased { background:rgba(25,135,84,.15);  color:#20c997; border:1px solid rgba(25,135,84,.3);  font-size:.8rem; padding:4px 10px; border-radius:20px; font-weight:700; white-space:nowrap; }
.badge-declined  { background:rgba(220,53,69,.15);  color:#f87171; border:1px solid rgba(220,53,69,.3);  font-size:.8rem; padding:4px 10px; border-radius:20px; font-weight:700; white-space:nowrap; }

/* ── Search inputs ── */
#admin-history-search:focus,
#admin-history-status:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
    outline: none;
}
#admin-history-search::placeholder { color: #5a7a94; }
#admin-history-status option        { background: #0a101d; }
</style>

<?php
renderFooter();
?>