<?php
// ============================================================
// FILE: admin/accounts.php
// Vai trò: Quản lý danh sách, trạng thái và quyền của Nhân viên
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Quản lý Tài khoản');
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.8rem;color:#e6f0fa;letter-spacing:-.3px;">
            👥 Quản lý Tài khoản Nhân viên
        </h2>
        <p class="mb-0" style="color:#8fa8be;font-size:1rem;">
            Thêm, khoá/mở khoá, cập nhật phân quyền, đổi mật khẩu và xoá tài khoản trong hệ thống.
        </p>
    </div>
    <button type="button"
            class="btn d-flex align-items-center gap-2"
            data-bs-toggle="modal" data-bs-target="#modal-create"
            style="background:rgba(13,202,240,.12);border:1px solid rgba(13,202,240,.3);
                   color:#0dcaf0;font-weight:700;font-size:1rem;padding:10px 20px;border-radius:10px;">
        ➕ Thêm Nhân viên
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3"
             style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:rgba(13,202,240,.1);font-size:1.3rem;">👥</div>
            <div>
                <div style="font-size:.85rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#8fa8be;">Tổng tài khoản</div>
                <div class="fw-bold" style="font-size:1.8rem;color:#0dcaf0;line-height:1.2;" id="stat-total">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3"
             style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:rgba(32,201,151,.1);font-size:1.3rem;">✅</div>
            <div>
                <div style="font-size:.85rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#8fa8be;">Đang hoạt động</div>
                <div class="fw-bold" style="font-size:1.8rem;color:#20c997;line-height:1.2;" id="stat-active">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3"
             style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:rgba(220,53,69,.1);font-size:1.3rem;">🔒</div>
            <div>
                <div style="font-size:.85rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#8fa8be;">Đã khoá</div>
                <div class="fw-bold" style="font-size:1.8rem;color:#f08080;line-height:1.2;" id="stat-locked">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3"
             style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:rgba(255,193,7,.1);font-size:1.3rem;">👑</div>
            <div>
                <div style="font-size:.85rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#8fa8be;">Quản trị viên</div>
                <div class="fw-bold" style="font-size:1.8rem;color:#ffc107;line-height:1.2;" id="stat-admin">—</div>
            </div>
        </div>
    </div>
</div>

<div class="rounded-3" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">

    <div class="d-flex align-items-center justify-content-between px-4 py-3"
         style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">Danh sách tài khoản</span>
        <span class="badge rounded-pill" id="accounts-count"
              style="background:rgba(13,202,240,.1);color:#0dcaf0;border:1px solid rgba(13,202,240,.2);font-size:.9rem;padding:5px 12px;">
            Đang tải...
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-borderless table-hover align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <th class="px-4 py-3" style="font-size:.95rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);width:72px;">ID</th>
                    <th class="py-3"      style="font-size:.95rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);min-width:180px;">Họ Tên</th>
                    <th class="py-3"      style="font-size:.95rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);min-width:200px;">Email</th>
                    <th class="py-3"      style="font-size:.95rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);width:130px;">Phân quyền</th>
                    <th class="py-3"      style="font-size:.95rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);width:140px;">Trạng thái</th>
                    <th class="py-3 pe-4" style="font-size:.95rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">Thao tác</th>
                </tr>
            </thead>
            <tbody id="accounts-tbody">
                <tr>
                    <td colspan="6" class="text-center py-5 px-4">
                        <span class="spinner-border spinner-border-sm me-2" style="color:#0dcaf0;"></span>
                        <span style="color:#8fa8be;font-size:1rem;">Đang tải dữ liệu...</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
/* ── Table rows ── */
#accounts-tbody td {
    font-size: 1rem;
    color: #c8d8ea; /* Tăng độ sáng text bảng */
    padding-top: 16px !important;
    padding-bottom: 16px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
}
#accounts-tbody td:first-child { padding-left: 1.5rem !important; }
#accounts-tbody td:last-child  { padding-right: 1.5rem !important; }
#accounts-tbody tr:last-child td { border-bottom: none !important; }
#accounts-tbody tr:hover td { background: rgba(13,202,240,.025) !important; }

/* ── Avatar ── */
.user-avatar-circle {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 800; flex-shrink: 0;
}

/* ── Action buttons ── */
.action-strip { display: flex; gap: 8px; align-items: center; flex-wrap: nowrap; }
.btn-act {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; padding: 0;
    border-radius: 10px;
    font-size: 1.1rem;
    border: 1px solid transparent;
    cursor: pointer; transition: all .2s ease;
    line-height: 1;
}
.btn-act-edit   { background:rgba(13,202,240,.1); border-color:rgba(13,202,240,.28); color:#0dcaf0; }
.btn-act-edit:hover   { background:rgba(13,202,240,.22); color:#4dd4f5; transform:translateY(-2px); box-shadow:0 4px 10px rgba(13,202,240,.15); }
.btn-act-lock   { background:rgba(220,53,69,.1);  border-color:rgba(220,53,69,.25);  color:#f08080; }
.btn-act-unlock { background:rgba(32,201,151,.1); border-color:rgba(32,201,151,.28); color:#20c997; }
.btn-act-pass   { background:rgba(255,193,7,.1);  border-color:rgba(255,193,7,.28);  color:#ffc107; }
.btn-act-del    { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.1); color:#8fa8be; }
.btn-act-del:hover    { background:rgba(220,53,69,.15); border-color:rgba(220,53,69,.3); color:#f08080; transform:translateY(-2px); box-shadow:0 4px 10px rgba(220,53,69,.15); }
.btn-act-lock:hover   { transform:translateY(-2px); box-shadow:0 4px 10px rgba(220,53,69,.15); }
.btn-act-unlock:hover { transform:translateY(-2px); box-shadow:0 4px 10px rgba(32,201,151,.15); }
.btn-act-pass:hover   { transform:translateY(-2px); box-shadow:0 4px 10px rgba(255,193,7,.15); }

/* ── Modals ── */
.modal-dark { background: #080e1a; border: 1px solid rgba(255,255,255,.08); border-radius: 16px; }
.modal-dark .modal-header { border-bottom: 1px solid rgba(255,255,255,.07); padding: 20px 24px; }
.modal-dark .modal-footer { border-top: 1px solid rgba(255,255,255,.07); padding: 16px 24px; }
.modal-dark .modal-body   { padding: 22px 24px; }
.modal-dark .form-label   {
    font-size: .85rem; font-weight: 700; letter-spacing: 1px;
    text-transform: uppercase; color: #8fa8be; margin-bottom: 8px; display: block;
}
.modal-dark .form-control,
.modal-dark .form-select {
    background: rgba(0,0,0,.4) !important;
    border: 1px solid rgba(255,255,255,.09) !important;
    border-radius: 10px !important;
    color: #c8d8ea !important;
    font-size: 1rem; padding: 11px 14px;
}
.modal-dark .form-control:focus,
.modal-dark .form-select:focus {
    border-color: rgba(13,202,240,.5) !important;
    box-shadow: 0 0 0 3px rgba(13,202,240,.1) !important;
    background: rgba(0,0,0,.55) !important;
}
.modal-dark .form-control::placeholder { color: #5a7a94; }
.modal-dark .form-select option { background: #0a101d; }
</style>


<div class="modal fade" id="modal-create" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-dark">
            <form id="form-create">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" style="color:#e6f0fa;font-size:1.2rem;">➕ Thêm tài khoản mới</h5>
                        <p class="mb-0 mt-1" style="font-size:.9rem;color:#8fa8be;">Tạo tài khoản Staff hoặc Admin mới trong hệ thống.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Họ và tên</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Nguyễn Văn A" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự" minlength="6" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Phân quyền</label>
                        <select name="role" class="form-select">
                            <option value="Staff">👤 Staff — Nhân viên định giá</option>
                            <option value="Admin">👑 Admin — Quản trị viên</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-sm fw-bold"
                            style="background:rgba(13,202,240,.15);border:1px solid rgba(13,202,240,.4);color:#0dcaf0;padding:8px 18px;">
                        ✅ Tạo tài khoản
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="modal-edit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-dark">
            <form id="form-edit">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" style="color:#e6f0fa;font-size:1.2rem;">✏️ Cập nhật Tài khoản</h5>
                        <p class="mb-0 mt-1" style="font-size:.9rem;color:#8fa8be;">Thay đổi thông tin và phân quyền người dùng.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="mb-3">
                        <label class="form-label">Họ và tên</label>
                        <input type="text" name="full_name" id="edit-full-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email (Tài khoản)</label>
                        <input type="email" name="email" id="edit-email" class="form-control" readonly style="opacity: 0.7; cursor: not-allowed;">
                        <small class="text-secondary mt-1 d-block" style="font-size: 0.8rem;">*Không thể thay đổi email đăng nhập</small>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Phân quyền</label>
                        <select name="role" id="edit-role" class="form-select">
                            <option value="Staff">👤 Staff — Nhân viên định giá</option>
                            <option value="Admin">👑 Admin — Quản trị viên</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-sm fw-bold"
                            style="background:rgba(13,202,240,.15);border:1px solid rgba(13,202,240,.4);color:#0dcaf0;padding:8px 18px;">
                        💾 Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="modal-reset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-dark">
            <form id="form-reset">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" style="color:#e6f0fa;font-size:1.2rem;">🔑 Đổi mật khẩu</h5>
                        <p class="mb-0 mt-1" style="font-size:.9rem;color:#ffc107;" id="reset-target-label"></p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="reset-user-id">
                    <div class="mb-1">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="new_password" class="form-control"
                               placeholder="Tối thiểu 6 ký tự" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-sm fw-bold"
                            style="background:rgba(255,193,7,.15);border:1px solid rgba(255,193,7,.4);color:#ffc107;padding:8px 18px;">
                        💾 Lưu mật khẩu mới
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="modal-delete-account" tabindex="-1"
     aria-labelledby="modal-delete-account-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-dark" style="border-color:rgba(220,53,69,.3) !important;">
            <div class="modal-header" style="background:rgba(220,53,69,.08);border-bottom-color:rgba(220,53,69,.2) !important;">
                <h5 class="modal-title fw-bold" id="modal-delete-account-title"
                    style="color:#f08080;font-size:1.2rem;">⚠️ Xác nhận xóa tài khoản</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" style="color:#8fa8be;font-size:.95rem;">Bạn sắp xóa tài khoản:</p>
                <p class="fw-bold mb-3" id="delete-account-email"
                   style="color:#f08080;font-family:'Courier New',monospace;font-size:1rem;
                          background:rgba(220,53,69,.08);padding:10px 14px;border-radius:8px;word-break:break-all;">—</p>
                <input type="hidden" id="delete-account-id" value="">
                <div class="p-3 rounded-3" style="background:rgba(220,53,69,.06);border:1px solid rgba(220,53,69,.15);">
                    <p class="mb-0" style="color:#c58a9e;line-height:1.6;font-size:.9rem;">
                        Hành động này <strong style="color:#f08080;">không thể hoàn tác</strong>.
                        Nếu tài khoản đã có phiên định giá, hệ thống sẽ từ chối —
                        hãy <strong style="color:#ffc107;">khoá</strong> thay vì xóa.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-sm fw-bold" id="btn-confirm-delete-account"
                        style="background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.4);color:#f08080;padding:8px 18px;">
                    🗑 Xóa ngay
                </button>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter(['../asset/admin_app.js']);
?>

<script>
(function patchAccounts() {
    'use strict';

    /* ── Badge templates ── */
    const ROLE_BADGES = {
        Admin: `<span class="badge rounded-pill px-3 py-2"
                    style="background:rgba(13,202,240,.13);color:#0dcaf0;border:1px solid rgba(13,202,240,.28);font-size:.9rem;">
                    👑 Admin</span>`,
        Staff: `<span class="badge rounded-pill px-3 py-2"
                    style="background:rgba(108,117,125,.13);color:#c8d8ea;border:1px solid rgba(108,117,125,.22);font-size:.9rem;">
                    👤 Staff</span>`,
    };
    const STATUS_BADGES = {
        Active: `<span class="badge rounded-pill px-3 py-2"
                     style="background:rgba(32,201,151,.13);color:#20c997;border:1px solid rgba(32,201,151,.28);font-size:.9rem;">
                     <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#20c997;margin-right:5px;vertical-align:middle;box-shadow:0 0 5px #20c997;"></span>Hoạt động</span>`,
        Locked: `<span class="badge rounded-pill px-3 py-2"
                     style="background:rgba(220,53,69,.13);color:#f08080;border:1px solid rgba(220,53,69,.28);font-size:.9rem;">
                     <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#f08080;margin-right:5px;vertical-align:middle;"></span>Đã khoá</span>`,
    };

    /* ── Build a single table row ── */
    function buildRow(u) {
        const role       = ROLE_BADGES[u.role]     || Api.esc(u.role   || '—');
        const status     = STATUS_BADGES[u.status] || Api.esc(u.status || '—');
        const isActive   = u.status === 'Active';

        /* Avatar color based on role */
        const avatarBg  = u.role === 'Admin'
            ? 'background:rgba(13,202,240,.15);color:#0dcaf0;border:1px solid rgba(13,202,240,.25);'
            : 'background:rgba(255,255,255,.06);color:#c8d8ea;border:1px solid rgba(255,255,255,.1);';
        const initials  = Api.esc((u.full_name || '?')[0].toUpperCase());

        /* Toggle button */
        const toggleCls = isActive ? 'btn-act btn-act-lock btn-toggle-status' : 'btn-act btn-act-unlock btn-toggle-status';
        const toggleLbl = isActive ? '🔒' : '🔓';
        const toggleTitle = isActive ? 'Khoá tài khoản' : 'Mở khoá tài khoản';

        return `
            <tr data-id="${u.user_id}">
                <td class="px-4">
                    <span style="font-size:.9rem;font-weight:700;color:#8fa8be;font-family:'Courier New',monospace;">#${u.user_id}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="user-avatar-circle" style="${avatarBg}font-weight:800;">
                            ${initials}
                        </div>
                        <span style="color:#e6f0fa;font-weight:600;font-size:1rem;">${Api.esc(u.full_name)}</span>
                    </div>
                </td>
                <td>
                    <span style="font-family:'Courier New',monospace;font-size:.95rem;color:#8fa8be;">
                        ${Api.esc(u.email)}
                    </span>
                </td>
                <td>${role}</td>
                <td>${status}</td>
                <td class="pe-4">
                    <div class="action-strip">
                        <button type="button" class="${toggleCls}"
                                data-id="${u.user_id}" data-email="${Api.esc(u.email)}"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="${toggleTitle}">
                            ${toggleLbl}
                        </button>
                        <button type="button" class="btn-act btn-act-edit btn-edit-account"
                                data-id="${u.user_id}" data-name="${Api.esc(u.full_name)}" data-email="${Api.esc(u.email)}" data-role="${Api.esc(u.role)}"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Cập nhật quyền">
                            ✏️
                        </button>
                        <button type="button" class="btn-act btn-act-pass btn-reset-pass"
                                data-id="${u.user_id}" data-email="${Api.esc(u.email)}"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Đổi mật khẩu">
                            🔑
                        </button>
                        <button type="button" class="btn-act btn-act-del btn-delete-account"
                                data-id="${u.user_id}" data-email="${Api.esc(u.email)}"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Xóa tài khoản">
                            🗑
                        </button>
                    </div>
                </td>
            </tr>`;
    }

    /* ── Update mini stat chips ── */
    function updateStats(users) {
        const total  = users.length;
        const active = users.filter(u => u.status === 'Active').length;
        const locked = users.filter(u => u.status === 'Locked').length;
        const admins = users.filter(u => u.role   === 'Admin').length;
        const el = id => document.getElementById(id);
        if (el('stat-total'))  el('stat-total').textContent  = total;
        if (el('stat-active')) el('stat-active').textContent = active;
        if (el('stat-locked')) el('stat-locked').textContent = locked;
        if (el('stat-admin'))  el('stat-admin').textContent  = admins;

        const badge = el('accounts-count');
        if (badge) badge.textContent = total + ' tài khoản';
    }

    /* ── Main loader ── */
    document.addEventListener('DOMContentLoaded', () => {
        const API_BASE = '../api/';
        const tbodyEl  = document.getElementById('accounts-tbody');
        if (!tbodyEl) return;

        async function loadAccountsPatched() {
            tbodyEl.innerHTML = `<tr><td colspan="6" class="text-center py-5">
                <span class="spinner-border spinner-border-sm me-2" style="color:#0dcaf0;"></span>
                <span style="color:#8fa8be;font-size:1rem;">Đang tải dữ liệu...</span>
            </td></tr>`;

            const fd = new FormData();
            fd.append('search',        '');
            fd.append('role_filter',   '');
            fd.append('status_filter', '');
            fd.append('page',          '1');
            fd.append('per_page',      '100');

            let res;
            try {
                const r = await fetch(API_BASE + 'account_api.php?action=get_list', { method: 'POST', body: fd });
                res = await r.json();
            } catch {
                tbodyEl.innerHTML = `<tr><td colspan="6" class="text-center py-5" style="color:#f08080;">
                    ⚠️ Không thể tải danh sách tài khoản.</td></tr>`;
                return;
            }

            if (!res.success || !Array.isArray(res.users)) {
                tbodyEl.innerHTML = `<tr><td colspan="6" class="text-center py-5" style="color:#f08080;">
                    ${Api.esc(res.message || 'Lỗi tải dữ liệu.')}</td></tr>`;
                return;
            }
            if (res.users.length === 0) {
                tbodyEl.innerHTML = `<tr><td colspan="6" class="text-center py-5" style="color:#8fa8be;">
                    Chưa có tài khoản nào.</td></tr>`;
                return;
            }

            tbodyEl.innerHTML = res.users.map(buildRow).join('');
            updateStats(res.users);
            bindRowActions();
        }

        /* ── Row action bindings ── */
        function bindRowActions() {
            /* Initialize Tooltips for new buttons */
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            /* Toggle status */
            document.querySelectorAll('.btn-toggle-status').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const email = this.dataset.email;
                    const verb  = this.classList.contains('btn-act-lock') ? 'khoá' : 'mở khoá';
                    if (!confirm(`Bạn có chắc muốn ${verb} tài khoản "${email}"?`)) return;
                    this.disabled = true;
                    const fd = new FormData();
                    fd.append('user_id', this.dataset.id);
                    const res = await Api.post(API_BASE + 'account_api.php', 'toggle_status', fd);
                    this.disabled = false;
                    if (res.success) {
                        alert(res.message || 'Cập nhật thành công.');
                        loadAccountsPatched();
                    } else {
                        alert(res.message || 'Cập nhật thất bại.');
                    }
                });
            });

            /* Show edit modal */
            document.querySelectorAll('.btn-edit-account').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('edit-user-id').value = this.dataset.id;
                    document.getElementById('edit-full-name').value = this.dataset.name;
                    document.getElementById('edit-email').value = this.dataset.email;
                    document.getElementById('edit-role').value = this.dataset.role;
                    new bootstrap.Modal(document.getElementById('modal-edit')).show();
                });
            });

            /* Show delete modal */
            document.querySelectorAll('.btn-delete-account').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('delete-account-id').value = this.dataset.id;
                    document.getElementById('delete-account-email').textContent = this.dataset.email;
                    new bootstrap.Modal(document.getElementById('modal-delete-account')).show();
                });
            });

            /* Show reset pass modal */
            document.querySelectorAll('.btn-reset-pass').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('reset-user-id').value = this.dataset.id;
                    document.getElementById('reset-target-label').textContent = 'Tài khoản: ' + this.dataset.email;
                    new bootstrap.Modal(document.getElementById('modal-reset')).show();
                });
            });
        }

        /* ── Create account form ── */
        const formCreate = document.getElementById('form-create');
        if (formCreate) {
            formCreate.addEventListener('submit', async function (e) {
                e.preventDefault();
                const res = await Api.post(API_BASE + 'account_api.php', 'create', new FormData(this));
                if (res.success) {
                    alert(res.message || 'Đã tạo tài khoản thành công.');
                    bootstrap.Modal.getInstance(document.getElementById('modal-create'))?.hide();
                    this.reset();
                    loadAccountsPatched();
                } else {
                    alert(res.message || 'Tạo tài khoản thất bại.');
                }
            });
        }

        /* ── Confirm delete account ── */
        const btnConfirmDel = document.getElementById('btn-confirm-delete-account');
        if (btnConfirmDel) {
            btnConfirmDel.addEventListener('click', async function () {
                const id = parseInt(document.getElementById('delete-account-id').value, 10);
                if (!id) return;
                this.disabled = true;
                const fd = new FormData();
                fd.append('user_id', id);
                const res = await Api.post(API_BASE + 'account_api.php', 'delete', fd);
                this.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('modal-delete-account'))?.hide();
                alert(res.message || (res.success ? 'Đã xóa tài khoản.' : 'Xóa thất bại.'));
                if (res.success) loadAccountsPatched();
            });
        }

        /* ── Edit account form ── */
        const formEdit = document.getElementById('form-edit');
        if (formEdit) {
            formEdit.addEventListener('submit', async function (e) {
                e.preventDefault();
                const res = await Api.post(API_BASE + 'account_api.php', 'update', new FormData(this));
                if (res.success) {
                    alert(res.message || 'Cập nhật thành công.');
                    bootstrap.Modal.getInstance(document.getElementById('modal-edit'))?.hide();
                    loadAccountsPatched();
                } else {
                    alert(res.message || 'Cập nhật thất bại.');
                }
            });
        }

        /* ── Reset password form ── */
        const formReset = document.getElementById('form-reset');
        if (formReset) {
            formReset.addEventListener('submit', async function (e) {
                e.preventDefault();
                const res = await Api.post(API_BASE + 'account_api.php', 'reset_password', new FormData(this));
                if (res.success) {
                    alert(res.message || 'Đổi mật khẩu thành công.');
                    bootstrap.Modal.getInstance(document.getElementById('modal-reset'))?.hide();
                    this.reset();
                } else {
                    alert(res.message || 'Đổi mật khẩu thất bại.');
                }
            });
        }

        /* ── Boot ── */
        loadAccountsPatched();
    });
})();
</script>