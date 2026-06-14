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
        <p class="mb-0" style="color:#8fa8be;font-size:1rem;">
            Xem toàn bộ lịch sử định giá của tất cả nhân viên, lọc theo nhân viên, tên máy hoặc trạng thái.
        </p>
    </div>
</div>

<!-- Bộ lọc -->
<div class="rounded-3 mb-4" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
    <div class="px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">
            🔍 Bộ lọc tìm kiếm
        </span>
    </div>
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Từ khoá
                </label>
                <input type="text" id="admin-history-search" class="form-control"
                       placeholder="Tên nhân viên hoặc tên máy..."
                       style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
            </div>
            <div class="col-md-3">
                <label class="d-block mb-2" style="font-size:.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8fa8be;">
                    Trạng thái phiên
                </label>
                <select id="admin-history-status" class="form-select"
                        style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.09);border-radius:10px;color:#c8d8ea;font-size:1rem;padding:11px 14px;">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Pending">⏳ Đang chờ</option>
                    <option value="Purchased">✅ Đã thu mua</option>
                    <option value="Declined">❌ Từ chối</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-history-search"
                        class="btn w-100 fw-bold"
                        style="background:rgba(13,202,240,.13);border:1px solid rgba(13,202,240,.3);color:#0dcaf0;font-size:1rem;padding:11px 14px;border-radius:10px;">
                    🔍 Lọc
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" id="btn-history-reset"
                        class="btn w-100 fw-bold"
                        style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#8fa8be;font-size:1rem;padding:11px 14px;border-radius:10px;">
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
              style="font-size:.9rem;background:rgba(13,202,240,.12);border:1px solid rgba(13,202,240,.25);color:#0dcaf0;padding:4px 14px;border-radius:20px;letter-spacing:.3px;">
            0 phiên
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-borderless align-middle mb-0" id="admin-history-table">
            <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <th class="px-4 py-3" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:40px;">#</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">📅 Ngày giờ</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">🧑‍💼 Nhân viên</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">📱 Thiết bị</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">⚙️ Cấu hình</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);width:75px;">🔋 Pin</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">IMEI</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">🤖 Giá AI</th>
                    <th class="py-3"      style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);white-space:nowrap;">💰 Giá chốt</th>
                    <th class="px-4 py-3" style="font-size:.9rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(13,202,240,.8);">Trạng thái</th>
                </tr>
            </thead>
            <tbody id="admin-history-tbody">
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
/* ── Table rows – nền trong suốt hoàn toàn ── */
#admin-history-tbody tr {
    background: transparent !important;
}
#admin-history-tbody td {
    font-size: 1rem;
    color: #c8d8ea;
    padding-top: 14px !important;
    padding-bottom: 14px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
    vertical-align: middle;
    background: transparent !important;
}
#admin-history-tbody tr:last-child td { border-bottom: none !important; }

/* Hover – glow xanh nhẹ, KHÔNG override bằng màu nền đặc */
#admin-history-tbody tr:hover td {
    background: rgba(13,202,240,.04) !important;
}

/* ── Row "Từ chối" – không dim toàn bộ row, chỉ giảm nhẹ text ── */
#admin-history-tbody tr.row-declined td {
    color: #7a91a8;
}
#admin-history-tbody tr.row-declined td:last-child {
    opacity: 1; /* badge vẫn sắc nét */
}

/* ── Avatar chip ── */
.staff-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .9rem; font-weight: 700;
    background: rgba(13,202,240,.18);
    color: #0dcaf0;
    flex-shrink: 0;
}

/* ── Status badges ── */
.badge-pending {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,193,7,.12);
    color: #ffc107;
    border: 1px solid rgba(255,193,7,.3);
    font-size: .85rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; white-space: nowrap;
}
.badge-purchased {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(32,201,151,.12);
    color: #20c997;
    border: 1px solid rgba(32,201,151,.3);
    font-size: .85rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; white-space: nowrap;
}
.badge-declined {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(220,53,69,.12);
    color: #f87171;
    border: 1px solid rgba(220,53,69,.28);
    font-size: .85rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; white-space: nowrap;
}

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