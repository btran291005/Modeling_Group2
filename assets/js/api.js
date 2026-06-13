// ============================================================
// FILE: assets/js/admin_app.js
// Quản lý Tài khoản — Admin  +  Dashboard Metrics
// Phụ thuộc: assets/js/api.js (object Api)
// ============================================================
'use strict';

(function () {

    // ──────────────────────────────────────────────────────────
    // SECTION A: QUẢN LÝ TÀI KHOẢN (accounts.php)
    // ──────────────────────────────────────────────────────────

    const tbody = document.getElementById('accounts-tbody');

    const ROLE_BADGES = {
        'Admin': '<span class="badge bg-primary">👑 Admin</span>',
        'Staff': '<span class="badge bg-secondary">👤 Staff</span>',
    };

    const STATUS_BADGES = {
        'Active': '<span class="badge bg-success">Hoạt động</span>',
        'Locked': '<span class="badge bg-danger">Đã khoá</span>',
    };

    /* ----------------------------------------------------------
     * LOAD DANH SÁCH TÀI KHOẢN
     * POST account_api.php?action=get_list
     * Response: { success, users, pagination }
     * ---------------------------------------------------------- */
    async function loadAccounts() {
        if (!tbody) return;

        tbody.innerHTML = `
            <tr><td colspan="6" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const fd = new FormData();
        fd.append('page', '1');
        fd.append('per_page', '50');

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

        bindRowActions();
    }


    /* ----------------------------------------------------------
     * THÊM NHÂN VIÊN
     * POST account_api.php?action=create
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
     * KHOÁ / MỞ KHOÁ
     * POST account_api.php?action=toggle_status
     * ---------------------------------------------------------- */
    function bindRowActions() {
        document.querySelectorAll('.btn-toggle-status').forEach(btn => {
            btn.addEventListener('click', async function () {
                const id    = this.dataset.id;
                const email = this.dataset.email;
                const fd    = new FormData();
                fd.append('user_id', id);

                const verb = this.classList.contains('btn-outline-danger') ? 'khoá' : 'mở khoá';
                if (!confirm(`Bạn có chắc muốn ${verb} tài khoản "${email}"?`)) return;

                this.disabled = true;

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

                document.getElementById('reset-user-id').value = id;
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
     * POST account_api.php?action=reset_password
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


    // ──────────────────────────────────────────────────────────
    // SECTION B: DASHBOARD METRICS (admin/dashboard.php)
    // ──────────────────────────────────────────────────────────

    /* ----------------------------------------------------------
     * Hàm helper: format VNĐ — dùng cục bộ để tránh phụ thuộc Api.vnd
     * ---------------------------------------------------------- */
    function fmtVnd(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style:                 'currency',
            currency:              'VND',
            maximumFractionDigits: 0,
        }).format(amount ?? 0);
    }

    /* ----------------------------------------------------------
     * Hàm helper: badge trạng thái phiên
     * ---------------------------------------------------------- */
    function sessionBadge(status) {
        const map = {
            'Purchased': '<span class="badge bg-success">Đã mua</span>',
            'Declined':  '<span class="badge bg-danger">Từ chối</span>',
            'Pending':   '<span class="badge bg-warning text-dark">Chờ xử lý</span>',
        };
        return map[status] || `<span class="badge bg-secondary">${Api.esc(status || '—')}</span>`;
    }

    /* ----------------------------------------------------------
     * loadDashboardMetrics()
     * GET ../api/admin_dashboard_api.php?action=get_metrics
     * Trả về: json_ok({ cards: {...}, brands: [...], recent: [...] })
     * ---------------------------------------------------------- */
    async function loadDashboardMetrics() {
        const elStaff   = document.getElementById('card-total-staff');
        const elStock   = document.getElementById('card-in-stock');
        const elSold    = document.getElementById('card-sold');
        const elSpent   = document.getElementById('card-total-spent');
        const elBrands  = document.getElementById('brands-tbody');
        const elRecent  = document.getElementById('recent-tbody');
        const elBadge   = document.getElementById('badge-brands');
        const elError   = document.getElementById('dashboard-error');

        // Nếu không có phần tử nào → không phải trang dashboard, bỏ qua
        if (!elStaff && !elBrands && !elRecent) return;

        const res = await Api.get('../api/admin_dashboard_api.php', 'get_metrics');

        if (!res.ok) {
            if (elError) elError.classList.remove('d-none');
            // Xoá spinners nếu lỗi
            [elStaff, elStock, elSold, elSpent].forEach(el => {
                if (el) el.textContent = '—';
            });
            [elBrands, elRecent].forEach(el => {
                if (el) el.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Lỗi tải dữ liệu.</td></tr>';
            });
            return;
        }

        const { cards, brands, recent } = res.data;

        // ── 1. Đổ số liệu vào 4 Cards ────────────────────────
        if (elStaff) elStaff.textContent = cards?.total_staff    ?? '—';
        if (elStock) elStock.textContent = cards?.in_stock        ?? '—';
        if (elSold)  elSold.textContent  = cards?.total_sold      ?? '—';
        if (elSpent) elSpent.textContent = fmtVnd(cards?.total_spent ?? 0);

        // ── 2. Bảng Thống kê theo Hãng ───────────────────────
        if (elBrands) {
            if (!Array.isArray(brands) || brands.length === 0) {
                elBrands.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Chưa có dữ liệu.</td></tr>';
            } else {
                elBrands.innerHTML = brands.map((b, i) => `
                    <tr>
                        <td class="text-muted">${i + 1}</td>
                        <td>${Api.esc(b.brand_name)}</td>
                        <td class="text-end">
                            <span class="badge bg-primary rounded-pill">${b.quantity}</span>
                        </td>
                    </tr>
                `).join('');

                if (elBadge) elBadge.textContent = `${brands.length} hãng`;
            }
        }

        // ── 3. Bảng 5 Giao dịch mới nhất ─────────────────────
        if (elRecent) {
            if (!Array.isArray(recent) || recent.length === 0) {
                elRecent.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Chưa có giao dịch nào.</td></tr>';
            } else {
                elRecent.innerHTML = recent.map(r => {
                    const deviceName = `${Api.esc(r.brand_name || '')} ${Api.esc(r.model_name || '')}`.trim() || '—';
                    const imei       = r.imei
                        ? `<code class="small">${Api.esc(r.imei)}</code>`
                        : '<span class="text-muted">Chưa nhập</span>';
                    const createdAt  = r.created_at
                        ? new Date(r.created_at).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
                        : '—';

                    return `
                        <tr>
                            <td class="text-muted small">${createdAt}</td>
                            <td>${deviceName}</td>
                            <td>${Api.esc(r.staff_name || '—')}</td>
                            <td>${imei}</td>
                            <td>${sessionBadge(r.final_status)}</td>
                        </tr>
                    `;
                }).join('');
            }
        }
    }


    // ──────────────────────────────────────────────────────────
    // INIT — Phân luồng theo trang
    // ──────────────────────────────────────────────────────────

    const path = window.location.pathname;

    if (path.includes('accounts')) {
        loadAccounts();
    }

    if (path.includes('dashboard')) {
        loadDashboardMetrics();
    }

}());