<?php
/**
 * admin/accounts.php
 * Trang quản lý tài khoản người dùng — Admin only
 *
 * Tính năng:
 *   - Bảng danh sách tài khoản (phân trang phía client qua AJAX)
 *   - Thanh tìm kiếm + filter theo Role / Status
 *   - Modal Thêm tài khoản mới
 *   - Modal Sửa thông tin (họ tên, email, role)
 *   - Khoá / mở khoá tài khoản (inline toggle)
 *   - Modal Reset mật khẩu
 *   - Xoá tài khoản (với confirm dialog)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

// Chỉ Admin được vào
requireAdmin();
$currentUser = getCurrentUser();

// Render layout
renderHtmlHead('Quản lý Tài khoản', [
    '../assets/css/pages/admin/accounts.css'
]);
renderSidebar('accounts');
renderMainOpen();
renderTopbar(
    'Quản lý Tài khoản',
    '<a href="../admin/dashboard.php">Dashboard</a> / Tài khoản'
);
?>

<!-- ===== NỘI DUNG TRANG ===== -->
<div class="page-content">

    <!-- ---------- Page Header + Actions ---------- -->
    <div class="accounts-header">
        <div>
            <h2 class="section-title">Danh sách tài khoản</h2>
            <p class="section-desc">Quản lý toàn bộ tài khoản Admin và Staff trong hệ thống.</p>
        </div>
        <button class="btn btn-primary" id="btn-open-create">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="16"/>
                <line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
            Thêm tài khoản
        </button>
    </div>

    <!-- ---------- Filter & Search Bar ---------- -->
    <div class="card accounts-filter-card">
        <div class="card-body">
            <div class="filter-row">
                <!-- Ô tìm kiếm -->
                <div class="filter-search">
                    <span class="filter-search-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </span>
                    <input
                        class="form-input filter-search-input"
                        type="text"
                        id="search-input"
                        placeholder="Tìm theo tên hoặc email..."
                        autocomplete="off"
                    >
                </div>

                <!-- Filter Role -->
                <select class="form-select filter-select" id="filter-role">
                    <option value="">Tất cả Role</option>
                    <option value="Admin">👑 Admin</option>
                    <option value="Staff">👤 Staff</option>
                </select>

                <!-- Filter Status -->
                <select class="form-select filter-select" id="filter-status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="Active">Hoạt động</option>
                    <option value="Locked">Đã khóa</option>
                </select>

                <!-- Nút reset filter -->
                <button class="btn btn-secondary" id="btn-reset-filter">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/>
                    </svg>
                    Đặt lại
                </button>
            </div>
        </div>
    </div>

    <!-- ---------- Bảng Danh Sách ---------- -->
    <div class="card">

        <!-- Loading overlay -->
        <div class="table-loading" id="table-loading">
            <div class="table-loading-spinner"></div>
            <span>Đang tải dữ liệu...</span>
        </div>

        <div class="card-body-flush" id="table-container">
            <!-- JS sẽ render bảng vào đây -->
        </div>

        <!-- Footer: phân trang + thông tin -->
        <div class="card-footer" id="table-footer">
            <span class="pagination-info" id="pagination-info">—</span>
            <div class="pagination" id="pagination-controls"></div>
        </div>
    </div>

</div><!-- end .page-content -->


<!-- ================================================================
     MODAL: THÊM TÀI KHOẢN MỚI
     ================================================================ -->
<div class="modal-backdrop" id="modal-create">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-icon modal-icon-blue">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <line x1="19" y1="8" x2="19" y2="14"/>
                        <line x1="22" y1="11" x2="16" y2="11"/>
                    </svg>
                </div>
                <div>
                    <h3 class="modal-title">Thêm tài khoản mới</h3>
                    <p class="modal-subtitle">Tạo tài khoản Admin hoặc Staff mới</p>
                </div>
            </div>
            <button class="modal-close" data-modal="modal-create">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div id="create-alert"></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required" for="c-fullname">Họ và tên</label>
                    <input class="form-input" type="text" id="c-fullname" placeholder="Nguyễn Văn A" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="c-email">Email</label>
                    <input class="form-input" type="email" id="c-email" placeholder="example@company.com" autocomplete="off">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required" for="c-password">Mật khẩu</label>
                    <div class="password-input-wrap">
                        <input class="form-input" type="password" id="c-password" placeholder="Tối thiểu 6 ký tự">
                        <button type="button" class="toggle-pass-btn" data-target="c-password">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <p class="form-hint">Mật khẩu tối thiểu 6 ký tự</p>
                </div>
                <div class="form-group">
                    <label class="form-label required" for="c-role">Phân quyền (Role)</label>
                    <select class="form-select" id="c-role">
                        <option value="Staff">👤 Staff — Nhân viên</option>
                        <option value="Admin">👑 Admin — Quản trị viên</option>
                    </select>
                    <p class="form-hint">Admin có toàn quyền. Staff chỉ Read/Insert/Update.</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal="modal-create">Huỷ</button>
            <button class="btn btn-primary" id="btn-create-submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span>Tạo tài khoản</span>
            </button>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL: SỬA THÔNG TIN TÀI KHOẢN
     ================================================================ -->
<div class="modal-backdrop" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-icon modal-icon-green">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="modal-title">Sửa thông tin tài khoản</h3>
                    <p class="modal-subtitle" id="edit-subtitle">—</p>
                </div>
            </div>
            <button class="modal-close" data-modal="modal-edit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="e-user-id">
            <div id="edit-alert"></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required" for="e-fullname">Họ và tên</label>
                    <input class="form-input" type="text" id="e-fullname" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="e-email">Email</label>
                    <input class="form-input" type="email" id="e-email" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="e-role">Phân quyền (Role)</label>
                <select class="form-select" id="e-role">
                    <option value="Staff">👤 Staff — Nhân viên</option>
                    <option value="Admin">👑 Admin — Quản trị viên</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal="modal-edit">Huỷ</button>
            <button class="btn btn-primary" id="btn-edit-submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span>Lưu thay đổi</span>
            </button>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL: RESET MẬT KHẨU
     ================================================================ -->
<div class="modal-backdrop" id="modal-reset">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-icon modal-icon-yellow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="modal-title">Reset mật khẩu</h3>
                    <p class="modal-subtitle" id="reset-subtitle">—</p>
                </div>
            </div>
            <button class="modal-close" data-modal="modal-reset">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="r-user-id">
            <div id="reset-alert"></div>
            <div class="form-group">
                <label class="form-label required" for="r-password">Mật khẩu mới</label>
                <div class="password-input-wrap">
                    <input class="form-input" type="password" id="r-password" placeholder="Tối thiểu 6 ký tự">
                    <button type="button" class="toggle-pass-btn" data-target="r-password">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="alert alert-warning" style="margin-top: var(--space-3);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                Người dùng sẽ cần đăng nhập lại bằng mật khẩu mới.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal="modal-reset">Huỷ</button>
            <button class="btn btn-warning" id="btn-reset-submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="1 4 1 10 7 10"/>
                    <path d="M3.51 15a9 9 0 1 0 .49-3.51"/>
                </svg>
                <span>Reset mật khẩu</span>
            </button>
        </div>
    </div>
</div>


<!-- ================================================================
     MODAL: XOÁ TÀI KHOẢN (Confirm)
     ================================================================ -->
<div class="modal-backdrop" id="modal-delete">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-icon modal-icon-red">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                </div>
                <div>
                    <h3 class="modal-title">Xoá tài khoản</h3>
                    <p class="modal-subtitle">Hành động này không thể hoàn tác</p>
                </div>
            </div>
            <button class="modal-close" data-modal="modal-delete">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="d-user-id">
            <div id="delete-alert"></div>
            <div class="delete-confirm-box">
                <div class="delete-confirm-icon">⚠️</div>
                <p class="delete-confirm-text">
                    Bạn sắp xoá tài khoản: <br>
                    <strong id="d-user-email">—</strong>
                </p>
                <p class="delete-confirm-sub">
                    Nếu tài khoản này có phiên định giá liên quan, hệ thống sẽ từ chối và đề nghị khoá tài khoản thay thế.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal="modal-delete">Huỷ</button>
            <button class="btn btn-danger" id="btn-delete-submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
                <span>Xác nhận xoá</span>
            </button>
        </div>
    </div>
</div>


<!-- ================================================================
     TOAST NOTIFICATION (góc phải dưới)
     ================================================================ -->
<div class="toast-container" id="toast-container"></div>


<?php renderLayoutClose(['../assets/js/accounts.js']); ?>


<!-- ================================================================
     JAVASCRIPT — Accounts Management
     ================================================================ -->
<script>
(function () {
    'use strict';

    // ===== STATE =====
    const state = {
        page:       1,
        perPage:    10,
        search:     '',
        roleFilter:   '',
        statusFilter: '',
        debounceTimer: null,
    };

    // ===== DOM REFS =====
    const searchInput    = document.getElementById('search-input');
    const filterRole     = document.getElementById('filter-role');
    const filterStatus   = document.getElementById('filter-status');
    const btnReset       = document.getElementById('btn-reset-filter');
    const tableContainer = document.getElementById('table-container');
    const tableLoading   = document.getElementById('table-loading');
    const paginationInfo = document.getElementById('pagination-info');
    const paginationCtrl = document.getElementById('pagination-controls');

    // Current admin user_id (để disable các action lên chính mình)
    const CURRENT_USER_ID = <?= (int) $currentUser['user_id'] ?>;

    // ===== INIT =====
    loadUsers();

    // ===== EVENTS: Filter =====
    searchInput.addEventListener('input', function () {
        clearTimeout(state.debounceTimer);
        state.debounceTimer = setTimeout(() => {
            state.search = this.value.trim();
            state.page   = 1;
            loadUsers();
        }, 350);
    });

    filterRole.addEventListener('change', function () {
        state.roleFilter = this.value;
        state.page       = 1;
        loadUsers();
    });

    filterStatus.addEventListener('change', function () {
        state.statusFilter = this.value;
        state.page         = 1;
        loadUsers();
    });

    btnReset.addEventListener('click', function () {
        searchInput.value   = '';
        filterRole.value    = '';
        filterStatus.value  = '';
        state.search        = '';
        state.roleFilter    = '';
        state.statusFilter  = '';
        state.page          = 1;
        loadUsers();
    });

    // ===== API: Load danh sách users =====
    async function loadUsers() {
        tableLoading.classList.add('visible');

        const fd = new FormData();
        fd.append('action',        'get_list');
        fd.append('search',        state.search);
        fd.append('role_filter',   state.roleFilter);
        fd.append('status_filter', state.statusFilter);
        fd.append('page',          state.page);
        fd.append('per_page',      state.perPage);

        try {
            const res  = await fetch('../api/account.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                showTableError(data.message);
                return;
            }

            renderTable(data.users);
            renderPagination(data.pagination);
        } catch (err) {
            showTableError('Không thể tải dữ liệu. Kiểm tra kết nối mạng.');
        } finally {
            tableLoading.classList.remove('visible');
        }
    }

    // ===== RENDER: Table =====
    function renderTable(users) {
        if (!users || users.length === 0) {
            tableContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <div class="empty-title">Không tìm thấy tài khoản nào</div>
                    <div class="empty-desc">Thử thay đổi bộ lọc hoặc từ khoá tìm kiếm.</div>
                </div>`;
            return;
        }

        const rows = users.map(u => {
            const isSelf   = u.user_id == CURRENT_USER_ID;
            const isActive = u.status === 'Active';
            const isAdmin  = u.role === 'Admin';

            const avatar = u.full_name.charAt(0).toUpperCase();
            const avatarBg = isAdmin ? 'avatar-admin' : 'avatar-staff';

            const statusBadge = isActive
                ? '<span class="badge badge-success badge-dot">Hoạt động</span>'
                : '<span class="badge badge-danger badge-dot">Đã khóa</span>';

            const roleBadge = isAdmin
                ? '<span class="badge badge-primary">👑 Admin</span>'
                : '<span class="badge badge-default">👤 Staff</span>';

            const lockBtn = `
                <button class="btn btn-sm ${isActive ? 'btn-ghost' : 'btn-success'} action-toggle-status"
                        data-id="${u.user_id}"
                        data-status="${u.status}"
                        data-name="${escHtml(u.full_name)}"
                        ${isSelf ? 'disabled title="Không thể khoá chính mình"' : `title="${isActive ? 'Khoá tài khoản' : 'Mở khoá'}"` }>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        ${isActive
                            ? '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'
                            : '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>'}
                    </svg>
                    ${isActive ? 'Khoá' : 'Mở khoá'}
                </button>`;

            return `
                <tr class="${!isActive ? 'row-locked' : ''}">
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar-sm ${avatarBg}">${avatar}</div>
                            <div class="user-cell-info">
                                <span class="user-cell-name td-primary">
                                    ${escHtml(u.full_name)}
                                    ${isSelf ? '<span class="badge-self">Bạn</span>' : ''}
                                </span>
                                <span class="user-cell-email">${escHtml(u.email)}</span>
                            </div>
                        </div>
                    </td>
                    <td>${roleBadge}</td>
                    <td>${statusBadge}</td>
                    <td class="td-mono">${formatDate(u.created_at)}</td>
                    <td>
                        <div class="td-action">
                            <!-- Sửa -->
                            <button class="btn btn-sm btn-secondary action-edit"
                                    data-id="${u.user_id}"
                                    data-name="${escHtml(u.full_name)}"
                                    data-email="${escHtml(u.email)}"
                                    data-role="${u.role}"
                                    title="Sửa thông tin">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Sửa
                            </button>
                            <!-- Khoá / mở -->
                            ${lockBtn}
                            <!-- Reset pass -->
                            <button class="btn btn-sm btn-ghost action-reset-pass"
                                    data-id="${u.user_id}"
                                    data-name="${escHtml(u.full_name)}"
                                    title="Reset mật khẩu">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </button>
                            <!-- Xoá -->
                            <button class="btn btn-sm btn-ghost btn-ghost-danger action-delete"
                                    data-id="${u.user_id}"
                                    data-email="${escHtml(u.email)}"
                                    ${isSelf ? 'disabled title="Không thể xoá chính mình"' : 'title="Xoá tài khoản"'}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        tableContainer.innerHTML = `
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tài khoản</th>
                            <th>Role</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;

        // Gắn event listeners cho các nút action
        bindTableActions();
    }

    // ===== RENDER: Pagination =====
    function renderPagination(p) {
        const start = (p.current_page - 1) * p.per_page + 1;
        const end   = Math.min(p.current_page * p.per_page, p.total);

        paginationInfo.textContent = p.total === 0
            ? 'Không có kết quả'
            : `Hiển thị ${start}–${end} / ${p.total} tài khoản`;

        if (p.total_pages <= 1) {
            paginationCtrl.innerHTML = '';
            return;
        }

        let html = '';

        // Prev
        html += `<button class="page-btn ${p.current_page <= 1 ? 'disabled' : ''}"
                         onclick="gotoPage(${p.current_page - 1})" ${p.current_page <= 1 ? 'disabled' : ''}>
                     ‹
                 </button>`;

        // Trang số
        for (let i = 1; i <= p.total_pages; i++) {
            if (
                i === 1 || i === p.total_pages ||
                Math.abs(i - p.current_page) <= 1
            ) {
                html += `<button class="page-btn ${i === p.current_page ? 'active' : ''}"
                                 onclick="gotoPage(${i})">${i}</button>`;
            } else if (
                i === p.current_page - 2 ||
                i === p.current_page + 2
            ) {
                html += `<span class="page-btn">…</span>`;
            }
        }

        // Next
        html += `<button class="page-btn ${p.current_page >= p.total_pages ? 'disabled' : ''}"
                         onclick="gotoPage(${p.current_page + 1})" ${p.current_page >= p.total_pages ? 'disabled' : ''}>
                     ›
                 </button>`;

        paginationCtrl.innerHTML = html;
    }

    // Export gotoPage cho onclick handler inline
    window.gotoPage = function (page) {
        state.page = page;
        loadUsers();
    };

    // ===== BIND: Table Action Buttons =====
    function bindTableActions() {
        // Edit
        document.querySelectorAll('.action-edit').forEach(btn => {
            btn.addEventListener('click', function () {
                openEditModal({
                    id:    this.dataset.id,
                    name:  this.dataset.name,
                    email: this.dataset.email,
                    role:  this.dataset.role,
                });
            });
        });

        // Toggle status
        document.querySelectorAll('.action-toggle-status').forEach(btn => {
            btn.addEventListener('click', function () {
                toggleStatus(this.dataset.id, this.dataset.name, this.dataset.status);
            });
        });

        // Reset password
        document.querySelectorAll('.action-reset-pass').forEach(btn => {
            btn.addEventListener('click', function () {
                openResetModal({ id: this.dataset.id, name: this.dataset.name });
            });
        });

        // Delete
        document.querySelectorAll('.action-delete').forEach(btn => {
            btn.addEventListener('click', function () {
                openDeleteModal({ id: this.dataset.id, email: this.dataset.email });
            });
        });
    }

    // ===== TABLE HELPERS =====
    function showTableError(msg) {
        tableContainer.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">⚠️</div>
                <div class="empty-title">Lỗi tải dữ liệu</div>
                <div class="empty-desc">${escHtml(msg)}</div>
            </div>`;
    }


    /* ============================
     * MODAL: THÊM TÀI KHOẢN
     * ============================ */

    document.getElementById('btn-open-create').addEventListener('click', () => openModal('modal-create'));

    document.getElementById('btn-create-submit').addEventListener('click', async function () {
        const btn      = this;
        const fullName = document.getElementById('c-fullname').value.trim();
        const email    = document.getElementById('c-email').value.trim();
        const password = document.getElementById('c-password').value.trim();
        const role     = document.getElementById('c-role').value;

        clearAlert('create-alert');

        const fd = new FormData();
        fd.append('action',    'create');
        fd.append('full_name', fullName);
        fd.append('email',     email);
        fd.append('password',  password);
        fd.append('role',      role);

        setLoading(btn, true);
        try {
            const res  = await fetch('../api/account.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-create');
                resetCreateForm();
                showToast(data.message, 'success');
                loadUsers();
            } else {
                showAlert('create-alert', data.message, 'danger');
            }
        } catch {
            showAlert('create-alert', 'Lỗi kết nối. Thử lại.', 'danger');
        } finally {
            setLoading(btn, false);
        }
    });

    function resetCreateForm() {
        ['c-fullname', 'c-email', 'c-password'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('c-role').value = 'Staff';
        clearAlert('create-alert');
    }


    /* ============================
     * MODAL: SỬA THÔNG TIN
     * ============================ */

    function openEditModal({ id, name, email, role }) {
        document.getElementById('e-user-id').value  = id;
        document.getElementById('e-fullname').value = name;
        document.getElementById('e-email').value    = email;
        document.getElementById('e-role').value     = role;
        document.getElementById('edit-subtitle').textContent = `ID #${id} — ${email}`;
        clearAlert('edit-alert');
        openModal('modal-edit');
    }

    document.getElementById('btn-edit-submit').addEventListener('click', async function () {
        const btn     = this;
        const userId  = document.getElementById('e-user-id').value;
        const fullName = document.getElementById('e-fullname').value.trim();
        const email   = document.getElementById('e-email').value.trim();
        const role    = document.getElementById('e-role').value;

        clearAlert('edit-alert');

        const fd = new FormData();
        fd.append('action',    'update');
        fd.append('user_id',   userId);
        fd.append('full_name', fullName);
        fd.append('email',     email);
        fd.append('role',      role);

        setLoading(btn, true);
        try {
            const res  = await fetch('../api/account.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-edit');
                showToast(data.message, 'success');
                loadUsers();
            } else {
                showAlert('edit-alert', data.message, 'danger');
            }
        } catch {
            showAlert('edit-alert', 'Lỗi kết nối. Thử lại.', 'danger');
        } finally {
            setLoading(btn, false);
        }
    });


    /* ============================
     * TOGGLE STATUS (inline)
     * ============================ */

    async function toggleStatus(userId, userName, currentStatus) {
        const action = currentStatus === 'Active' ? 'khoá' : 'mở khoá';
        if (!confirm(`Bạn có chắc muốn ${action} tài khoản "${userName}"?`)) return;

        const fd = new FormData();
        fd.append('action',  'toggle_status');
        fd.append('user_id', userId);

        try {
            const res  = await fetch('../api/account.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                loadUsers();
            } else {
                showToast(data.message, 'danger');
            }
        } catch {
            showToast('Lỗi kết nối. Thử lại.', 'danger');
        }
    }


    /* ============================
     * MODAL: RESET MẬT KHẨU
     * ============================ */

    function openResetModal({ id, name }) {
        document.getElementById('r-user-id').value = id;
        document.getElementById('r-password').value = '';
        document.getElementById('reset-subtitle').textContent = name;
        clearAlert('reset-alert');
        openModal('modal-reset');
    }

    document.getElementById('btn-reset-submit').addEventListener('click', async function () {
        const btn      = this;
        const userId   = document.getElementById('r-user-id').value;
        const password = document.getElementById('r-password').value.trim();

        clearAlert('reset-alert');

        const fd = new FormData();
        fd.append('action',       'reset_password');
        fd.append('user_id',      userId);
        fd.append('new_password', password);

        setLoading(btn, true);
        try {
            const res  = await fetch('../api/account.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-reset');
                showToast(data.message, 'success');
            } else {
                showAlert('reset-alert', data.message, 'danger');
            }
        } catch {
            showAlert('reset-alert', 'Lỗi kết nối. Thử lại.', 'danger');
        } finally {
            setLoading(btn, false);
        }
    });


    /* ============================
     * MODAL: XOÁ TÀI KHOẢN
     * ============================ */

    function openDeleteModal({ id, email }) {
        document.getElementById('d-user-id').value    = id;
        document.getElementById('d-user-email').textContent = email;
        clearAlert('delete-alert');
        openModal('modal-delete');
    }

    document.getElementById('btn-delete-submit').addEventListener('click', async function () {
        const btn    = this;
        const userId = document.getElementById('d-user-id').value;

        clearAlert('delete-alert');

        const fd = new FormData();
        fd.append('action',  'delete');
        fd.append('user_id', userId);

        setLoading(btn, true);
        try {
            const res  = await fetch('../api/account.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-delete');
                showToast(data.message, 'success');
                loadUsers();
            } else {
                showAlert('delete-alert', data.message, 'danger');
            }
        } catch {
            showAlert('delete-alert', 'Lỗi kết nối. Thử lại.', 'danger');
        } finally {
            setLoading(btn, false);
        }
    });


    /* ============================
     * TOGGLE PASSWORD VISIBILITY
     * ============================ */

    document.querySelectorAll('.toggle-pass-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            if (input) input.type = input.type === 'password' ? 'text' : 'password';
        });
    });


    /* ============================
     * MODAL SYSTEM (open/close)
     * ============================ */

    function openModal(id) {
        document.getElementById(id).classList.add('visible');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('visible');
        document.body.style.overflow = '';
    }

    // Nút đóng modal (X + backdrop click)
    document.querySelectorAll('.modal-close, [data-modal]').forEach(el => {
        el.addEventListener('click', function () {
            const modalId = this.dataset.modal || this.closest('.modal-backdrop')?.id;
            if (modalId) closeModal(modalId);
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // ESC để đóng modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.visible').forEach(m => {
                closeModal(m.id);
            });
        }
    });


    /* ============================
     * HELPERS
     * ============================ */

    function showAlert(containerId, message, type = 'danger') {
        const icons = {
            danger:  '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            success: '<polyline points="20 6 9 17 4 12"/>',
            warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>',
        };
        document.getElementById(containerId).innerHTML = `
            <div class="alert alert-${type}" style="margin-bottom:var(--space-4);">
                <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    ${icons[type] || icons.danger}
                </svg>
                <span>${escHtml(message)}</span>
            </div>`;
    }

    function clearAlert(containerId) {
        const el = document.getElementById(containerId);
        if (el) el.innerHTML = '';
    }

    function setLoading(btn, loading) {
        btn.disabled = loading;
        const span = btn.querySelector('span');
        if (span) span.textContent = loading ? 'Đang xử lý...' : btn.dataset.origText || span.textContent;
        if (!btn.dataset.origText && !loading) {} // keep as is
        if (loading && !btn.dataset.origText) {
            btn.dataset.origText = span?.textContent || '';
        }
        if (!loading && btn.dataset.origText) {
            if (span) span.textContent = btn.dataset.origText;
            delete btn.dataset.origText;
        }
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                ${type === 'success'
                    ? '<polyline points="20 6 9 17 4 12"/>'
                    : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>'}
            </svg>
            <span>${escHtml(message)}</span>`;
        document.getElementById('toast-container').appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => toast.classList.add('show'));

        // Tự ẩn sau 3.5s
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 350);
        }, 3500);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

})();
</script>