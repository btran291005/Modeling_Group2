// ============================================================
// FILE: assets/js/admin_app.js
// Quản lý Tài khoản — Admin
// Phụ thuộc: assets/js/api.js (object Api)
// ============================================================
'use strict';

(function () {

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
     * GET account_api.php?action=list_all -> { ok, data, msg }
     * ---------------------------------------------------------- */
    async function loadAccounts() {
        if (!tbody) return;

        tbody.innerHTML = `
            <tr><td colspan="6" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const res = await Api.get('account_api.php', 'list_all');

        if (!res.ok || !Array.isArray(res.data)) {
            tbody.innerHTML = `
                <tr><td colspan="6" class="text-center text-danger py-4">
                    ${Api.esc(res.msg || 'Không thể tải danh sách tài khoản.')}
                </td></tr>`;
            return;
        }

        if (res.data.length === 0) {
            tbody.innerHTML = `
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản nào.</td></tr>`;
            return;
        }

        tbody.innerHTML = res.data.map(u => {
            const roleBadge   = ROLE_BADGES[u.role]     || Api.esc(u.role || '—');
            const statusBadge = STATUS_BADGES[u.status] || Api.esc(u.status || '—');
            const isActive    = u.status === 'Active';
            const toggleLabel = isActive ? '🔒 Khoá' : '🔓 Mở khoá';
            const toggleClass = isActive ? 'btn-outline-danger' : 'btn-outline-success';
            const nextStatus  = isActive ? 'Locked' : 'Active';

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
                                data-status="${nextStatus}"
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
     * THÊM NHÂN VIÊN — FormData
     * POST account_api.php?action=create
     * Response format: { success, message }
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
     * KHOÁ / MỞ KHOÁ — JSON body
     * POST account_api.php?action=update_status
     * Body: { id, status } -> { ok, data, msg }
     * ---------------------------------------------------------- */
    function bindRowActions() {
        document.querySelectorAll('.btn-toggle-status').forEach(btn => {
            btn.addEventListener('click', async function () {
                const id     = this.dataset.id;
                const status = this.dataset.status;
                const email  = this.dataset.email;

                const verb = status === 'Locked' ? 'khoá' : 'mở khoá';
                if (!confirm(`Bạn có chắc muốn ${verb} tài khoản "${email}"?`)) return;

                this.disabled = true;

                const res = await Api.postJson('account_api.php', 'update_status', {
                    id:     parseInt(id, 10),
                    status: status,
                });

                this.disabled = false;

                if (res.ok) {
                    alert(res.msg || 'Cập nhật trạng thái thành công.');
                    loadAccounts();
                } else {
                    alert(res.msg || 'Cập nhật trạng thái thất bại.');
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
     * ĐỔI MẬT KHẨU — FormData
     * POST account_api.php?action=reset_password
     * Response format: { success, message }
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


    /* ----------------------------------------------------------
     * INIT
     * ---------------------------------------------------------- */
    loadAccounts();

}());