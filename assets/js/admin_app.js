// ============================================================
// FILE: assets/js/admin_app.js
// Quản lý Admin — Tài khoản + Kho Tổng + Nhật ký toàn hệ thống
// Phụ thuộc: assets/js/api.js (object Api)
// ============================================================
'use strict';

(function () {

    // ============================================================
    // SHARED HELPERS
    // ============================================================

    const ROLE_BADGES = {
        'Admin': '<span class="badge bg-primary">👑 Admin</span>',
        'Staff': '<span class="badge bg-secondary">👤 Staff</span>',
    };

    const STATUS_BADGES = {
        'Active': '<span class="badge bg-success">Hoạt động</span>',
        'Locked': '<span class="badge bg-danger">Đã khoá</span>',
    };

    const SESSION_STATUS_BADGES = {
        'Pending':   '<span class="badge bg-warning text-dark">⏳ Đang chờ</span>',
        'Purchased': '<span class="badge bg-success">✅ Đã thu mua</span>',
        'Declined':  '<span class="badge bg-danger">❌ Từ chối</span>',
    };

    const GADGET_STATUS_BADGES = {
        'Stored':       '<span class="badge bg-primary">📦 Đang lưu kho</span>',
        'Refurbishing': '<span class="badge bg-warning text-dark">🔧 Đang tân trang</span>',
        'Sold':         '<span class="badge bg-success">✅ Đã bán</span>',
    };

    function currentPage() {
        return window.location.pathname;
    }

    function isPage(keyword) {
        return currentPage().includes(keyword);
    }


    // ============================================================
    // TRANG: admin/accounts.php
    // ============================================================

    const tbody = document.getElementById('accounts-tbody');

    /* ----------------------------------------------------------
     * LOAD DANH SÁCH TÀI KHOẢN
     * ---------------------------------------------------------- */
    async function loadAccounts() {
        if (!tbody) return;

        tbody.innerHTML = `
            <tr><td colspan="6" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const fd = new FormData();
        fd.append('search',        '');
        fd.append('role_filter',   '');
        fd.append('status_filter', '');
        fd.append('page',          '1');
        fd.append('per_page',      '50');

        const res = await Api.post('account_api.php', 'get_list', fd);

        if (!res.success || !Array.isArray(res.users)) {
            tbody.innerHTML = `
                <tr><td colspan="6" class="text-center text-danger py-4">
                    ${Api.esc(res.message || 'Không thể tải danh sách tài khoản.')}
                </td></tr>`;
            return;
        }

        if (res.users.length === 0) {
            tbody.innerHTML = `
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản nào.</td></tr>`;
            return;
        }

        tbody.innerHTML = res.users.map(u => {
            const roleBadge   = ROLE_BADGES[u.role]     || Api.esc(u.role || '—');
            const statusBadge = STATUS_BADGES[u.status] || Api.esc(u.status || '—');
            const isActive    = u.status === 'Active';
            const toggleLabel = isActive ? '🔒 Khoá' : '🔓 Mở khoá';
            const toggleClass = isActive ? 'btn-outline-danger' : 'btn-outline-success';

            return `
                <tr data-id="${u.user_id}">
                    <td>${u.user_id}</td>
                    <td>${Api.esc(u.full_name)}</td>
                    <td>${Api.esc(u.email)}</td>
                    <td>${roleBadge}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button type="button"
                                class="btn btn-sm ${toggleClass} btn-toggle-status"
                                data-id="${u.user_id}"
                                data-email="${Api.esc(u.email)}">
                            ${toggleLabel}
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning btn-reset-pass"
                                data-id="${u.user_id}"
                                data-email="${Api.esc(u.email)}">
                            🔑 Đổi mật khẩu
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        bindAccountRowActions();
    }

    /* ----------------------------------------------------------
     * THÊM NHÂN VIÊN
     * ---------------------------------------------------------- */
    const formCreate = document.getElementById('form-create');
    if (formCreate) {
        formCreate.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd  = new FormData(formCreate);
            const res = await Api.post('account_api.php', 'create', fd);

            if (res.success) {
                alert(res.message || 'Tạo tài khoản thành công.');
                bootstrap.Modal.getInstance(document.getElementById('modal-create'))?.hide();
                formCreate.reset();
                loadAccounts();
            } else {
                alert(res.message || 'Tạo tài khoản thất bại.');
            }
        });
    }

    /* ----------------------------------------------------------
     * KHOÁ / MỞ KHOÁ + ĐỔI MẬT KHẨU
     * ---------------------------------------------------------- */
    function bindAccountRowActions() {
        document.querySelectorAll('.btn-toggle-status').forEach(btn => {
            btn.addEventListener('click', async function () {
                const id    = this.dataset.id;
                const email = this.dataset.email;
                const isLocked = this.classList.contains('btn-outline-success');
                const verb = isLocked ? 'mở khoá' : 'khoá';

                if (!confirm(`Bạn có chắc muốn ${verb} tài khoản "${email}"?`)) return;

                this.disabled = true;
                const fd = new FormData();
                fd.append('user_id', id);
                const res = await Api.post('account_api.php', 'toggle_status', fd);
                this.disabled = false;

                if (res.success) {
                    alert(res.message || 'Cập nhật trạng thái thành công.');
                    loadAccounts();
                } else {
                    alert(res.message || 'Cập nhật trạng thái thất bại.');
                }
            });
        });

        document.querySelectorAll('.btn-reset-pass').forEach(btn => {
            btn.addEventListener('click', function () {
                const id    = this.dataset.id;
                const email = this.dataset.email;

                document.getElementById('reset-user-id').value      = id;
                document.getElementById('reset-target-label').textContent = `Tài khoản: ${email}`;

                const formReset = document.getElementById('form-reset');
                if (formReset) formReset.reset();
                document.getElementById('reset-user-id').value = id;

                const modal = new bootstrap.Modal(document.getElementById('modal-reset'));
                modal.show();
            });
        });
    }

    /* ----------------------------------------------------------
     * ĐỔI MẬT KHẨU
     * ---------------------------------------------------------- */
    const formReset = document.getElementById('form-reset');
    if (formReset) {
        formReset.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd  = new FormData(formReset);
            const res = await Api.post('account_api.php', 'reset_password', fd);

            if (res.success) {
                alert(res.message || 'Đổi mật khẩu thành công.');
                bootstrap.Modal.getInstance(document.getElementById('modal-reset'))?.hide();
                formReset.reset();
            } else {
                alert(res.message || 'Đổi mật khẩu thất bại.');
            }
        });
    }


    // ============================================================
    // TRANG: admin/inventory.php — KHO TỔNG
    // ============================================================

    const adminInvTbody    = document.getElementById('admin-inv-tbody');
    const adminInvSearch   = document.getElementById('admin-inv-search');
    const adminInvStatus   = document.getElementById('admin-inv-status');
    const adminInvCount    = document.getElementById('admin-inv-count');
    const btnInvSearch     = document.getElementById('btn-inv-search');
    const btnInvReset      = document.getElementById('btn-inv-reset');

    // Lưu toàn bộ data để filter client-side
    let _allInventory = [];

    /**
     * Tải toàn bộ kho từ API admin_list
     */
    async function loadAdminInventory() {
        if (!adminInvTbody) return;

        adminInvTbody.innerHTML = `
            <tr><td colspan="10" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const res = await Api.get('inventory_api.php', 'admin_list');

        if (!res.ok || !Array.isArray(res.data)) {
            adminInvTbody.innerHTML = `
                <tr><td colspan="10" class="text-center text-danger py-4">
                    ${Api.esc(res.msg || 'Không thể tải dữ liệu kho.')}
                </td></tr>`;
            return;
        }

        _allInventory = res.data;
        renderInventoryTable(_allInventory);
    }

    /**
     * Render bảng kho từ mảng data
     */
    function renderInventoryTable(data) {
        if (!adminInvTbody) return;

        if (adminInvCount) {
            adminInvCount.textContent = `${data.length} thiết bị`;
        }

        if (data.length === 0) {
            adminInvTbody.innerHTML = `
                <tr><td colspan="10" class="text-center text-muted py-4">Không tìm thấy thiết bị nào.</td></tr>`;
            return;
        }

        adminInvTbody.innerHTML = data.map(item => {
            const deviceName   = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim();
            const config       = `${item.ram_gb ?? '—'}GB / ${item.rom_gb ?? '—'}GB`;
            const battery      = item.battery_health != null ? `${item.battery_health}%` : '—';
            const price        = item.price != null ? Api.vnd(item.price) : '—';
            const customer     = item.customer_name
                ? `${Api.esc(item.customer_name)}<br><small class="text-muted">${Api.esc(item.phone_number || '')}</small>`
                : '<span class="text-muted">—</span>';
            const staffName    = Api.esc(item.staff_name || '—');
            const receivedAt   = Api.esc(item.received_at || '—');
            const statusBadge  = GADGET_STATUS_BADGES[item.status] || Api.esc(item.status || '—');
            const imei         = item.imei || '';

            return `
                <tr data-imei="${Api.esc(imei)}"
                    data-search="${Api.esc((imei + ' ' + deviceName).toLowerCase())}">
                    <td class="font-monospace small">${Api.esc(imei || '—')}</td>
                    <td>
                        <div class="fw-semibold">${deviceName || '—'}</div>
                        <small class="text-muted">#${item.session_id || ''}</small>
                    </td>
                    <td class="small text-muted">${config}</td>
                    <td>
                        <span class="${getBatteryClass(item.battery_health)}">${battery}</span>
                    </td>
                    <td class="fw-semibold text-primary">${price}</td>
                    <td class="small">${customer}</td>
                    <td class="small">${staffName}</td>
                    <td class="small text-muted">${receivedAt}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button type="button"
                                class="btn btn-sm btn-danger btn-delete-gadget"
                                data-imei="${Api.esc(imei)}"
                                data-name="${Api.esc(deviceName)}">
                            🗑 Xóa
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        bindDeleteGadgetButtons();
    }

    function getBatteryClass(pct) {
        if (pct == null) return '';
        if (pct >= 80) return 'text-success fw-semibold';
        if (pct >= 60) return 'text-warning fw-semibold';
        return 'text-danger fw-semibold';
    }

    /**
     * Gắn sự kiện nút Xóa thiết bị
     */
    function bindDeleteGadgetButtons() {
        document.querySelectorAll('.btn-delete-gadget').forEach(btn => {
            btn.addEventListener('click', async function () {
                const imei = this.dataset.imei;
                const name = this.dataset.name;

                if (!confirm(`Bạn có chắc chắn muốn xóa thiết bị "${name}" (IMEI: ${imei}) khỏi kho?\n\nHành động này không thể hoàn tác!`)) {
                    return;
                }

                this.disabled    = true;
                this.textContent = '⏳ Đang xóa...';

                const res = await Api.postJson('inventory_api.php', 'delete', { imei: imei });

                if (res.ok) {
                    alert(res.msg || 'Đã xóa thiết bị thành công.');
                    loadAdminInventory();
                } else {
                    alert('Lỗi: ' + (res.msg || 'Không thể xóa thiết bị.'));
                    this.disabled    = false;
                    this.textContent = '🗑 Xóa';
                }
            });
        });
    }

    /**
     * Filter client-side
     */
    function applyInventoryFilter() {
        const keyword = (adminInvSearch ? adminInvSearch.value.trim().toLowerCase() : '');
        const status  = (adminInvStatus ? adminInvStatus.value : '');

        const filtered = _allInventory.filter(item => {
            const haystack = ((item.imei || '') + ' ' + (item.brand_name || '') + ' ' + (item.model_name || '') + ' ' + (item.customer_name || '') + ' ' + (item.staff_name || '')).toLowerCase();
            const matchKeyword = !keyword || haystack.includes(keyword);
            const matchStatus  = !status  || item.status === status;
            return matchKeyword && matchStatus;
        });

        renderInventoryTable(filtered);
    }

    if (btnInvSearch) {
        btnInvSearch.addEventListener('click', applyInventoryFilter);
    }
    if (adminInvSearch) {
        adminInvSearch.addEventListener('keydown', e => { if (e.key === 'Enter') applyInventoryFilter(); });
    }
    if (btnInvReset) {
        btnInvReset.addEventListener('click', () => {
            if (adminInvSearch) adminInvSearch.value = '';
            if (adminInvStatus) adminInvStatus.value = '';
            renderInventoryTable(_allInventory);
        });
    }


    // ============================================================
    // TRANG: admin/history.php — NHẬT KÝ TOÀN HỆ THỐNG
    // ============================================================

    const adminHistoryTbody  = document.getElementById('admin-history-tbody');
    const adminHistorySearch = document.getElementById('admin-history-search');
    const adminHistoryStatus = document.getElementById('admin-history-status');
    const adminHistoryCount  = document.getElementById('admin-history-count');
    const btnHistorySearch   = document.getElementById('btn-history-search');
    const btnHistoryReset    = document.getElementById('btn-history-reset');

    let _allHistory = [];

    /**
     * Tải toàn bộ nhật ký định giá (Admin view)
     */
    async function loadGlobalHistory() {
        if (!adminHistoryTbody) return;

        adminHistoryTbody.innerHTML = `
            <tr><td colspan="10" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const res = await Api.get('valuation_api.php', 'all_sessions');

        if (!res.ok) {
            adminHistoryTbody.innerHTML = `
                <tr><td colspan="10" class="text-center text-danger py-4">
                    ${Api.esc(res.msg || 'Không thể tải nhật ký định giá.')}
                </td></tr>`;
            return;
        }

        // all_sessions trả về { sessions, total, page, per_page, stats }
        const sessions = Array.isArray(res.data?.sessions) ? res.data.sessions
                       : Array.isArray(res.data)           ? res.data
                       : [];

        _allHistory = sessions;
        renderHistoryTable(_allHistory);
    }

    /**
     * Render bảng nhật ký
     */
    function renderHistoryTable(data) {
        if (!adminHistoryTbody) return;

        if (adminHistoryCount) {
            adminHistoryCount.textContent = `${data.length} phiên`;
        }

        if (data.length === 0) {
            adminHistoryTbody.innerHTML = `
                <tr><td colspan="10" class="text-center text-muted py-4">Không tìm thấy phiên định giá nào.</td></tr>`;
            return;
        }

        adminHistoryTbody.innerHTML = data.map((item, idx) => {
            const deviceName  = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim();
            const config      = `${item.ram_gb ?? '—'}GB / ${item.rom_gb ?? '—'}GB`;
            const battery     = item.battery_health != null ? `${item.battery_health}%` : '—';
            const aiPrice     = item.ai_suggested_price != null ? Api.vnd(item.ai_suggested_price) : '—';
            const finalPrice  = item.final_status === 'Purchased' && item.ai_suggested_price != null
                              ? Api.vnd(item.ai_suggested_price)
                              : '<span class="text-muted">—</span>';
            const statusBadge = SESSION_STATUS_BADGES[item.final_status] || Api.esc(item.final_status || '—');
            const staffName   = Api.esc(item.staff_name || '—');
            const createdAt   = Api.esc(item.created_at || '—');
            const imei        = item.imei ? Api.esc(item.imei) : '<span class="text-muted">Chưa nhập</span>';

            return `
                <tr class="${item.final_status === 'Declined' ? 'table-secondary' : ''}">
                    <td class="text-muted small">${item.session_id}</td>
                    <td class="small">${createdAt}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                 style="width:28px;height:28px;font-size:12px;flex-shrink:0">
                                ${Api.esc((item.staff_name || '?')[0].toUpperCase())}
                            </div>
                            <span class="small">${staffName}</span>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold small">${deviceName || '—'}</div>
                    </td>
                    <td class="small text-muted">${config}</td>
                    <td>
                        <span class="${getBatteryClass(item.battery_health)}">${battery}</span>
                    </td>
                    <td class="font-monospace small">${imei}</td>
                    <td class="fw-semibold text-primary small">${aiPrice}</td>
                    <td class="small">${finalPrice}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Filter nhật ký client-side
     */
    function applyHistoryFilter() {
        const keyword = (adminHistorySearch ? adminHistorySearch.value.trim().toLowerCase() : '');
        const status  = (adminHistoryStatus ? adminHistoryStatus.value : '');

        const filtered = _allHistory.filter(item => {
            const haystack = ((item.brand_name || '') + ' ' + (item.model_name || '') + ' ' + (item.staff_name || '') + ' ' + (item.imei || '')).toLowerCase();
            const matchKeyword = !keyword || haystack.includes(keyword);
            const matchStatus  = !status  || item.final_status === status;
            return matchKeyword && matchStatus;
        });

        renderHistoryTable(filtered);
    }

    if (btnHistorySearch) {
        btnHistorySearch.addEventListener('click', applyHistoryFilter);
    }
    if (adminHistorySearch) {
        adminHistorySearch.addEventListener('keydown', e => { if (e.key === 'Enter') applyHistoryFilter(); });
    }
    if (btnHistoryReset) {
        btnHistoryReset.addEventListener('click', () => {
            if (adminHistorySearch) adminHistorySearch.value = '';
            if (adminHistoryStatus) adminHistoryStatus.value = '';
            renderHistoryTable(_allHistory);
        });
    }


    // ============================================================
    // INIT — Gọi đúng hàm theo trang hiện tại
    // ============================================================

    if (isPage('accounts')) {
        loadAccounts();
    }

    if (isPage('inventory')) {
        loadAdminInventory();
    }

    if (isPage('history')) {
        loadGlobalHistory();
    }

    // Fallback: nếu có tbody tương ứng mà chưa load thì cũng load
    if (tbody && !isPage('accounts')) {
        loadAccounts();
    }

}());