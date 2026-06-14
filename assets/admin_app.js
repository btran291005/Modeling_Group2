// ============================================================
// FILE: assets/js/admin_app.js
// Quản lý Admin — Tài khoản + Kho + Lịch sử + Dashboard + AI Rules
// Phụ thuộc: assets/api.js (object Api)
// ============================================================
'use strict';

(function () {

    // ══════════════════════════════════════════════════════════
    // SHARED HELPERS
    // ══════════════════════════════════════════════════════════

    const API_BASE = '../api/';

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

    function fmtVnd(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency', currency: 'VND', maximumFractionDigits: 0,
        }).format(amount ?? 0);
    }


    // ══════════════════════════════════════════════════════════
    // SECTION 1: QUẢN LÝ TÀI KHOẢN (accounts.php)
    // ══════════════════════════════════════════════════════════

    const tbodyAccounts = document.getElementById('accounts-tbody');

    async function loadAccounts() {
        if (!tbodyAccounts) return;

        tbodyAccounts.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>`;

        const fd = new FormData();
        fd.append('search',        '');
        fd.append('role_filter',   '');
        fd.append('status_filter', '');
        fd.append('page',          '1');
        fd.append('per_page',      '50');

        const res = await Api.post(API_BASE + 'account_api.php', 'get_list', fd);

        if (!res.success || !Array.isArray(res.users)) {
            tbodyAccounts.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${Api.esc(res.message || 'Không thể tải danh sách tài khoản.')}</td></tr>`;
            return;
        }

        if (res.users.length === 0) {
            tbodyAccounts.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản nào.</td></tr>`;
            return;
        }

        tbodyAccounts.innerHTML = res.users.map(u => {
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
                        <button type="button" class="btn btn-sm ${toggleClass} btn-toggle-status"
                                data-id="${u.user_id}" data-email="${Api.esc(u.email)}">
                            ${toggleLabel}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-reset-pass"
                                data-id="${u.user_id}" data-email="${Api.esc(u.email)}">
                            🔑 Đổi mật khẩu
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-account"
                                data-id="${u.user_id}" data-email="${Api.esc(u.email)}">
                            🗑 Xóa
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        bindAccountRowActions();
    }

    const formCreate = document.getElementById('form-create');
    if (formCreate) {
        formCreate.addEventListener('submit', async function (e) {
            e.preventDefault();
            const res = await Api.post(API_BASE + 'account_api.php', 'create', new FormData(formCreate));

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

    function bindAccountRowActions() {
        document.querySelectorAll('.btn-toggle-status').forEach(btn => {
            btn.addEventListener('click', async function () {
                const id    = this.dataset.id;
                const email = this.dataset.email;
                const verb  = this.classList.contains('btn-outline-danger') ? 'khoá' : 'mở khoá';

                if (!confirm(`Bạn có chắc muốn ${verb} tài khoản "${email}"?`)) return;

                this.disabled = true;
                const fd = new FormData();
                fd.append('user_id', id);
                const res = await Api.post(API_BASE + 'account_api.php', 'toggle_status', fd);
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

                new bootstrap.Modal(document.getElementById('modal-reset')).show();
            });
        });

        document.querySelectorAll('.btn-delete-account').forEach(btn => {
            btn.addEventListener('click', function () {
                const id    = this.dataset.id;
                const email = this.dataset.email;

                const elId    = document.getElementById('delete-account-id');
                const elEmail = document.getElementById('delete-account-email');
                if (elId)    elId.value = id;
                if (elEmail) elEmail.textContent = email;

                const modalEl = document.getElementById('modal-delete-account');
                if (modalEl) new bootstrap.Modal(modalEl).show();
            });
        });
    }

    const btnConfirmDeleteAccount = document.getElementById('btn-confirm-delete-account');
    if (btnConfirmDeleteAccount) {
        btnConfirmDeleteAccount.addEventListener('click', async function () {
            const id = parseInt(document.getElementById('delete-account-id').value, 10);
            if (!id) return;

            this.disabled = true;
            const fd = new FormData();
            fd.append('user_id', id);
            const res = await Api.post(API_BASE + 'account_api.php', 'delete', fd);
            this.disabled = false;

            const modalEl = document.getElementById('modal-delete-account');
            bootstrap.Modal.getInstance(modalEl)?.hide();

            alert(res.message || (res.success ? 'Đã xóa tài khoản.' : 'Xóa tài khoản thất bại.'));

            if (res.success) loadAccounts();
        });
    }

    const formReset = document.getElementById('form-reset');
    if (formReset) {
        formReset.addEventListener('submit', async function (e) {
            e.preventDefault();
            const res = await Api.post(API_BASE + 'account_api.php', 'reset_password', new FormData(formReset));

            if (res.success) {
                alert(res.message || 'Đổi mật khẩu thành công.');
                bootstrap.Modal.getInstance(document.getElementById('modal-reset'))?.hide();
                formReset.reset();
            } else {
                alert(res.message || 'Đổi mật khẩu thất bại.');
            }
        });
    }


    // ══════════════════════════════════════════════════════════
    // SECTION 2: KHO TỔNG (admin/inventory.php)
    // ══════════════════════════════════════════════════════════

    const adminInvTbody    = document.getElementById('admin-inv-tbody');
    const adminInvSearch   = document.getElementById('admin-inv-search');
    const adminInvStatus   = document.getElementById('admin-inv-status');
    const adminInvCount    = document.getElementById('admin-inv-count');
    const btnInvSearch     = document.getElementById('btn-inv-search');
    const btnInvReset      = document.getElementById('btn-inv-reset');

    let _allInventory = [];

    async function loadAdminInventory() {
        if (!adminInvTbody) return;

        adminInvTbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>`;
        const res = await Api.get(API_BASE + 'inventory_api.php', 'admin_list');

        if (!res.ok || !Array.isArray(res.data)) {
            adminInvTbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${Api.esc(res.msg || 'Không thể tải dữ liệu kho.')}</td></tr>`;
            return;
        }

        _allInventory = res.data;
        renderInventoryTable(_allInventory);
    }

    function renderInventoryTable(data) {
        if (!adminInvTbody) return;

        if (adminInvCount) adminInvCount.textContent = `${data.length} thiết bị`;

        if (data.length === 0) {
            adminInvTbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">Không tìm thấy thiết bị nào.</td></tr>`;
            return;
        }

        adminInvTbody.innerHTML = data.map(item => {
            const deviceName   = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim();
            const config       = `${item.ram_gb ?? '—'}GB / ${item.rom_gb ?? '—'}GB`;
            const battery      = item.battery_health != null ? `${item.battery_health}%` : '—';
            const price        = item.price != null ? fmtVnd(item.price) : '—';
            const customer     = item.customer_name ? `${Api.esc(item.customer_name)}<br><small class="text-muted">${Api.esc(item.phone_number || '')}</small>` : '<span class="text-muted">—</span>';
            const staffName    = Api.esc(item.staff_name || '—');
            const receivedAt   = Api.esc(item.received_at || '—');
            const statusBadge  = GADGET_STATUS_BADGES[item.status] || Api.esc(item.status || '—');
            const imei         = item.imei || '';

            return `
                <tr data-imei="${Api.esc(imei)}">
                    <td class="font-monospace small">${Api.esc(imei || '—')}</td>
                    <td>
                        <div class="fw-semibold">${deviceName || '—'}</div>
                        <small class="text-muted">#${item.session_id || ''}</small>
                    </td>
                    <td class="small text-muted">${config}</td>
                    <td><span class="${getBatteryClass(item.battery_health)}">${battery}</span></td>
                    <td class="fw-semibold text-primary">${price}</td>
                    <td class="small">${customer}</td>
                    <td class="small">${staffName}</td>
                    <td class="small text-muted">${receivedAt}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger btn-delete-gadget" data-imei="${Api.esc(imei)}" data-name="${Api.esc(deviceName)}">
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

    function bindDeleteGadgetButtons() {
        document.querySelectorAll('.btn-delete-gadget').forEach(btn => {
            btn.addEventListener('click', async function () {
                const imei = this.dataset.imei;
                const name = this.dataset.name;

                if (!confirm(`Bạn có chắc chắn muốn xóa thiết bị "${name}" (IMEI: ${imei}) khỏi kho?\n\nHành động này không thể hoàn tác!`)) return;

                this.disabled = true;
                this.textContent = '⏳ Đang xóa...';

                const res = await Api.postJson(API_BASE + 'inventory_api.php', 'delete', { imei: imei });

                if (res.ok) {
                    alert(res.msg || 'Đã xóa thiết bị thành công.');
                    loadAdminInventory();
                } else {
                    alert('Lỗi: ' + (res.msg || 'Không thể xóa thiết bị.'));
                    this.disabled = false;
                    this.textContent = '🗑 Xóa';
                }
            });
        });
    }

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

    if (btnInvSearch) btnInvSearch.addEventListener('click', applyInventoryFilter);
    if (adminInvSearch) adminInvSearch.addEventListener('keydown', e => { if (e.key === 'Enter') applyInventoryFilter(); });
    if (btnInvReset) {
        btnInvReset.addEventListener('click', () => {
            if (adminInvSearch) adminInvSearch.value = '';
            if (adminInvStatus) adminInvStatus.value = '';
            renderInventoryTable(_allInventory);
        });
    }


    // ══════════════════════════════════════════════════════════
    // SECTION 3: NHẬT KÝ TOÀN HỆ THỐNG (admin/history.php)
    // ══════════════════════════════════════════════════════════

    const adminHistoryTbody  = document.getElementById('admin-history-tbody');
    const adminHistorySearch = document.getElementById('admin-history-search');
    const adminHistoryStatus = document.getElementById('admin-history-status');
    const adminHistoryCount  = document.getElementById('admin-history-count');
    const btnHistorySearch   = document.getElementById('btn-history-search');
    const btnHistoryReset    = document.getElementById('btn-history-reset');

    let _allHistory = [];

    async function loadGlobalHistory() {
        if (!adminHistoryTbody) return;

        adminHistoryTbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>`;
        const res = await Api.get(API_BASE + 'valuation_api.php', 'all_sessions');

        if (!res.ok) {
            adminHistoryTbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${Api.esc(res.msg || 'Không thể tải nhật ký định giá.')}</td></tr>`;
            return;
        }

        const sessions = Array.isArray(res.data?.sessions) ? res.data.sessions : (Array.isArray(res.data) ? res.data : []);
        _allHistory = sessions;
        renderHistoryTable(_allHistory);
    }

    function renderHistoryTable(data) {
        if (!adminHistoryTbody) return;

        if (adminHistoryCount) adminHistoryCount.textContent = `${data.length} phiên`;

        if (data.length === 0) {
            adminHistoryTbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">Không tìm thấy phiên định giá nào.</td></tr>`;
            return;
        }

        adminHistoryTbody.innerHTML = data.map(item => {
            const deviceName  = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim();
            const config      = `${item.ram_gb ?? '—'}GB / ${item.rom_gb ?? '—'}GB`;
            const battery     = item.battery_health != null ? `${item.battery_health}%` : '—';
            const aiPrice     = item.ai_suggested_price != null ? fmtVnd(item.ai_suggested_price) : '—';
            const finalPrice  = item.final_status === 'Purchased' && item.ai_suggested_price != null ? fmtVnd(item.ai_suggested_price) : '<span class="text-muted">—</span>';
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
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width:28px;height:28px;font-size:12px;flex-shrink:0">
                                ${Api.esc((item.staff_name || '?')[0].toUpperCase())}
                            </div>
                            <span class="small">${staffName}</span>
                        </div>
                    </td>
                    <td><div class="fw-semibold small">${deviceName || '—'}</div></td>
                    <td class="small text-muted">${config}</td>
                    <td><span class="${getBatteryClass(item.battery_health)}">${battery}</span></td>
                    <td class="font-monospace small">${imei}</td>
                    <td class="fw-semibold text-primary small">${aiPrice}</td>
                    <td class="small">${finalPrice}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        }).join('');
    }

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

    if (btnHistorySearch) btnHistorySearch.addEventListener('click', applyHistoryFilter);
    if (adminHistorySearch) adminHistorySearch.addEventListener('keydown', e => { if (e.key === 'Enter') applyHistoryFilter(); });
    if (btnHistoryReset) {
        btnHistoryReset.addEventListener('click', () => {
            if (adminHistorySearch) adminHistorySearch.value = '';
            if (adminHistoryStatus) adminHistoryStatus.value = '';
            renderHistoryTable(_allHistory);
        });
    }


    // ══════════════════════════════════════════════════════════
    // SECTION 4: DASHBOARD METRICS (admin/dashboard.php)
    // ══════════════════════════════════════════════════════════

    // Màu sắc cho từng trạng thái thiết bị trong donut chart
    const STOCK_STATUS_COLORS = {
        'Stored':       '#0d6efd', // primary (xanh dương)
        'Refurbishing': '#ffc107', // warning (vàng)
        'Sold':         '#198754', // success (xanh lá)
    };

    const STOCK_STATUS_LABELS_VI = {
        'Stored':       '📦 Đang lưu kho',
        'Refurbishing': '🔧 Đang tân trang',
        'Sold':         '✅ Đã bán',
    };

    /**
     * Vẽ donut chart thuần bằng Canvas 2D — không cần thư viện ngoài.
     * @param {HTMLCanvasElement} canvas
     * @param {Array<{label:string, value:number, color:string}>} segments
     */
    function drawDonutChart(canvas, segments) {
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        // Hỗ trợ màn hình retina/high-DPI
        const dpr  = window.devicePixelRatio || 1;
        const size = Math.min(canvas.clientWidth || 220, canvas.clientHeight || 220) || 220;

        canvas.width  = size * dpr;
        canvas.height = size * dpr;
        canvas.style.width  = size + 'px';
        canvas.style.height = size + 'px';
        ctx.scale(dpr, dpr);

        const cx = size / 2;
        const cy = size / 2;
        const radius      = size / 2 - 6;
        const innerRadius = radius * 0.6;

        ctx.clearRect(0, 0, size, size);

        const total = segments.reduce((sum, s) => sum + s.value, 0);

        if (total === 0) {
            // Vẽ vòng tròn xám rỗng khi chưa có dữ liệu
            ctx.beginPath();
            ctx.arc(cx, cy, radius, 0, Math.PI * 2);
            ctx.arc(cx, cy, innerRadius, 0, Math.PI * 2, true);
            ctx.closePath();
            ctx.fillStyle = 'rgba(255,255,255,0.08)';
            ctx.fill();

            ctx.fillStyle = '#8a96a3';
            ctx.font = '600 14px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Chưa có dữ liệu', cx, cy);
            return;
        }

        let startAngle = -Math.PI / 2; // bắt đầu từ vị trí 12 giờ

        segments.forEach(seg => {
            if (seg.value <= 0) return;
            const sliceAngle = (seg.value / total) * Math.PI * 2;
            const endAngle   = startAngle + sliceAngle;

            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = seg.color;
            ctx.fill();

            startAngle = endAngle;
        });

        // Khoét lỗ giữa để tạo hiệu ứng donut
        ctx.beginPath();
        ctx.arc(cx, cy, innerRadius, 0, Math.PI * 2);
        ctx.fillStyle = getComputedStyle(document.body).backgroundColor || '#121212';
        ctx.fill();

        // Tổng số ở giữa donut
        ctx.fillStyle = '#e7eaee';
        ctx.font = '700 22px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(total), cx, cy - 8);

        ctx.fillStyle = '#8a96a3';
        ctx.font = '500 11px system-ui, sans-serif';
        ctx.fillText('thiết bị', cx, cy + 12);
    }

    /**
     * Render chú giải (legend) bên dưới donut chart.
     */
    function renderStockStatusLegend(el, segments, total) {
        if (!el) return;

        if (total === 0) {
            el.innerHTML = '<div class="text-center text-muted small">Chưa có thiết bị nào trong hệ thống.</div>';
            return;
        }

        el.innerHTML = segments.map(seg => {
            const pct = total > 0 ? ((seg.value / total) * 100).toFixed(1) : '0.0';
            return `
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-block rounded-circle" style="width:10px;height:10px;background-color:${seg.color};"></span>
                        <span>${Api.esc(seg.label)}</span>
                    </div>
                    <div class="text-end">
                        <span class="fw-semibold">${seg.value}</span>
                        <span class="text-muted ms-1">(${pct}%)</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Màu badge cho từng loại lý do "cần lưu ý"
    const ATTENTION_REASON_BADGES = {
        'battery':   'bg-danger',
        'stock_age': 'bg-warning text-dark',
        'damage':    'bg-secondary',
    };

    /**
     * Render danh sách thiết bị "Cần lưu ý" — mỗi máy kèm các badge lý do
     * (pin yếu / tồn kho lâu / có lỗi hư hại) dựa trên điểm ưu tiên tổng hợp.
     */
    function renderAttentionList(el, data) {
        if (!el) return;

        if (!Array.isArray(data) || data.length === 0) {
            el.innerHTML = `
                <li class="list-group-item text-center text-muted py-3 px-0 bg-transparent">
                    🎉 Không có thiết bị nào cần lưu ý.
                </li>`;
            return;
        }

        el.innerHTML = data.map(item => {
            const deviceName  = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim() || '—';
            const statusBadge = GADGET_STATUS_BADGES[item.status] || Api.esc(item.status || '—');
            const imei        = item.imei ? `<code class="small">${Api.esc(item.imei)}</code>` : '<span class="text-muted small">—</span>';

            const reasons = Array.isArray(item.reasons) ? item.reasons : [];
            const reasonBadges = reasons.length > 0
                ? reasons.map(r => {
                    const cls = ATTENTION_REASON_BADGES[r.type] || 'bg-secondary';
                    return `<span class="badge ${cls}">${Api.esc(r.label)}</span>`;
                }).join(' ')
                : '<span class="badge bg-light text-muted">Tình trạng tốt</span>';

            return `
                <li class="list-group-item px-0 bg-transparent">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold small">${deviceName}</div>
                            <div class="mb-1">${imei} ${statusBadge}</div>
                            <div class="d-flex flex-wrap gap-1">${reasonBadges}</div>
                        </div>
                    </div>
                </li>
            `;
        }).join('');
    }

    async function loadDashboardMetrics() {
        const elStaff     = document.getElementById('card-total-staff');
        const elStock     = document.getElementById('card-in-stock');
        const elSold      = document.getElementById('card-sold');
        const elSpent     = document.getElementById('card-total-spent');
        const elChart     = document.getElementById('stock-status-chart');
        const elLegend    = document.getElementById('stock-status-legend');
        const elBadge     = document.getElementById('badge-total-gadgets');
        const elAttention = document.getElementById('attention-list');
        const elRecent    = document.getElementById('recent-tbody');
        const elError     = document.getElementById('dashboard-error');

        if (!elStaff && !elChart && !elRecent) return;

        const res = await Api.get(API_BASE + 'admin_dashboard_api.php', 'get_metrics');

        if (!res.ok) {
            if (elError) elError.classList.remove('d-none');
            [elStaff, elStock, elSold, elSpent].forEach(el => { if (el) el.textContent = '—'; });
            if (elLegend)    elLegend.innerHTML    = '<div class="text-center text-danger small py-2">Lỗi tải dữ liệu.</div>';
            if (elAttention) elAttention.innerHTML = '<li class="list-group-item text-center text-danger py-3 px-0 bg-transparent">Lỗi tải dữ liệu.</li>';
            if (elRecent) {
                elRecent.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Lỗi tải dữ liệu.</td></tr>';
            }
            return;
        }

        const { cards, stock_status, attention_devices, recent } = res.data;

        if (elStaff) elStaff.textContent = cards?.total_staff  ?? '—';
        if (elStock) elStock.textContent = cards?.in_stock      ?? '—';
        if (elSold)  elSold.textContent  = cards?.total_sold    ?? '—';
        if (elSpent) elSpent.textContent = fmtVnd(cards?.total_spent ?? 0);

        // ---- Donut chart: Trạng thái Kho ----
        const statusList = Array.isArray(stock_status) ? stock_status : [];
        const segments = statusList.map(s => ({
            label: STOCK_STATUS_LABELS_VI[s.status] || s.status,
            value: parseInt(s.quantity, 10) || 0,
            color: STOCK_STATUS_COLORS[s.status] || '#6c757d',
        }));
        const total = segments.reduce((sum, s) => sum + s.value, 0);

        if (elChart) drawDonutChart(elChart, segments);
        if (elLegend) renderStockStatusLegend(elLegend, segments, total);
        if (elBadge) elBadge.textContent = `${total} thiết bị`;

        // ---- Thiết bị cần lưu ý ----
        if (elAttention) renderAttentionList(elAttention, attention_devices);

        // ---- Giao dịch mới nhất ----
        if (elRecent) {
            if (!Array.isArray(recent) || recent.length === 0) {
                elRecent.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Chưa có giao dịch nào.</td></tr>';
            } else {
                elRecent.innerHTML = recent.map(r => {
                    const deviceName = `${Api.esc(r.brand_name || '')} ${Api.esc(r.model_name || '')}`.trim() || '—';
                    const imei       = r.imei ? `<code class="small">${Api.esc(r.imei)}</code>` : '<span class="text-muted">Chưa nhập</span>';
                    const createdAt  = r.created_at ? new Date(r.created_at).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '—';
                    const sBadge     = SESSION_STATUS_BADGES[r.final_status] || `<span class="badge bg-secondary">${Api.esc(r.final_status || '—')}</span>`;
                    return `
                        <tr>
                            <td class="text-muted small">${createdAt}</td>
                            <td>${deviceName}</td>
                            <td>${Api.esc(r.staff_name || '—')}</td>
                            <td>${imei}</td>
                            <td>${sBadge}</td>
                        </tr>`;
                }).join('');
            }
        }
    }


    // ══════════════════════════════════════════════════════════
    // SECTION 5: QUẢN LÝ QUY TẮC AI (admin/ai_rules.php)
    // ══════════════════════════════════════════════════════════

    const rulesPage = {
        tbody:          document.getElementById('rules-tbody'),
        statTotal:      document.getElementById('stat-total'),
        statActive:     document.getElementById('stat-active'),
        statInactive:   document.getElementById('stat-inactive'),
        statTotalPct:   document.getElementById('stat-total-pct'),
        modalRule:      document.getElementById('modal-rule')        ? new bootstrap.Modal(document.getElementById('modal-rule'))        : null,
        modalDelete:    document.getElementById('modal-delete-rule') ? new bootstrap.Modal(document.getElementById('modal-delete-rule')) : null,
        modalRuleTitle: document.getElementById('modal-rule-title'),
        ruleForm:       document.getElementById('rule-form'),
        ruleId:         document.getElementById('rule-id'),
        ruleName:       document.getElementById('rule-name'),
        rulePct:        document.getElementById('rule-pct'),
        ruleIsActive:   document.getElementById('rule-is-active'),
        deleteRuleId:   document.getElementById('delete-rule-id'),
        deleteRuleName: document.getElementById('delete-rule-name'),
        btnOpenCreate:  document.getElementById('btn-open-create'),
        btnConfirmDel:  document.getElementById('btn-confirm-delete'),
    };

    async function loadAiRules() {
        if (!rulesPage.tbody) return;

        rulesPage.tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Đang tải...</td></tr>`;
        const res = await Api.get(API_BASE + 'ai_rule_api.php', 'list');

        if (!res.ok || !Array.isArray(res.data)) {
            rulesPage.tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${Api.esc(res.message || 'Không thể tải danh sách quy tắc.')}</td></tr>`;
            return;
        }

        const rules = res.data;
        const total    = rules.length;
        const active   = rules.filter(r => parseInt(r.is_active) === 1);
        const inactive = rules.filter(r => parseInt(r.is_active) === 0);
        const totalPct = active.reduce((sum, r) => sum + parseFloat(r.deduction_pct || 0), 0);

        if (rulesPage.statTotal)    rulesPage.statTotal.textContent    = total;
        if (rulesPage.statActive)   rulesPage.statActive.textContent   = active.length;
        if (rulesPage.statInactive) rulesPage.statInactive.textContent = inactive.length;
        if (rulesPage.statTotalPct) rulesPage.statTotalPct.textContent = totalPct.toFixed(1) + '%';

        if (total === 0) {
            rulesPage.tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Chưa có quy tắc nào. Hãy thêm mới!</td></tr>`;
            return;
        }

        rulesPage.tbody.innerHTML = rules.map((r, i) => {
            const isActive   = parseInt(r.is_active) === 1;
            const usageCount = parseInt(r.usage_count) || 0;

            const toggleBtn = `
                <button type="button" class="btn btn-sm ${isActive ? 'btn-success' : 'btn-outline-secondary'} btn-rule-toggle"
                        data-id="${r.id}" title="${isActive ? 'Đang bật — nhấn để tắt' : 'Đang tắt — nhấn để bật'}">
                    ${isActive ? '✅ Bật' : '⏸ Tắt'}
                </button>`;
            const editBtn = `
                <button type="button" class="btn btn-sm btn-outline-primary btn-rule-edit"
                        data-id="${r.id}" data-name="${Api.esc(r.rule_name)}" data-pct="${r.deduction_pct}" data-active="${r.is_active}" title="Sửa">✏️</button>`;
            const deleteBtn = `
                <button type="button" class="btn btn-sm btn-outline-danger btn-rule-delete"
                        data-id="${r.id}" data-name="${Api.esc(r.rule_name)}" title="Xóa">🗑</button>`;

            return `
                <tr>
                    <td class="text-muted">${i + 1}</td>
                    <td>${Api.esc(r.rule_name)}</td>
                    <td class="text-center"><span class="badge bg-warning text-dark fs-6">${parseFloat(r.deduction_pct).toFixed(1)}%</span></td>
                    <td class="text-center"><span class="badge ${usageCount > 0 ? 'bg-info' : 'bg-light text-muted'}">${usageCount}</span></td>
                    <td class="text-center">${toggleBtn}</td>
                    <td class="text-center">${editBtn} ${deleteBtn}</td>
                </tr>`;
        }).join('');

        bindRuleRowActions();
    }

    function bindRuleRowActions() {
        document.querySelectorAll('.btn-rule-toggle').forEach(btn => {
            btn.addEventListener('click', async function () {
                this.disabled = true;
                const res = await Api.postJson(API_BASE + 'ai_rule_api.php', 'toggle', { id: parseInt(this.dataset.id) });
                this.disabled = false;
                if (!res.ok) alert(res.message || 'Đổi trạng thái thất bại.');
                loadAiRules();
            });
        });

        document.querySelectorAll('.btn-rule-edit').forEach(btn => {
            btn.addEventListener('click', function () {
                const p = rulesPage;
                if (!p.ruleForm) return;
                p.ruleId.value         = this.dataset.id;
                p.ruleName.value       = this.dataset.name;
                p.rulePct.value        = this.dataset.pct;
                p.ruleIsActive.checked = this.dataset.active === '1';
                p.modalRuleTitle.textContent = '✏️ Sửa quy tắc';
                p.ruleForm.classList.remove('was-validated');
                p.modalRule?.show();
            });
        });

        document.querySelectorAll('.btn-rule-delete').forEach(btn => {
            btn.addEventListener('click', function () {
                rulesPage.deleteRuleId.value         = this.dataset.id;
                rulesPage.deleteRuleName.textContent = this.dataset.name;
                rulesPage.modalDelete?.show();
            });
        });
    }

    if (rulesPage.btnOpenCreate) {
        rulesPage.btnOpenCreate.addEventListener('click', function () {
            const p = rulesPage;
            if (!p.ruleForm) return;
            p.ruleId.value         = '';
            p.ruleForm.reset();
            p.ruleIsActive.checked = true;
            p.modalRuleTitle.textContent = '+ Thêm quy tắc mới';
            p.ruleForm.classList.remove('was-validated');
            p.modalRule?.show();
        });
    }

    if (rulesPage.ruleForm) {
        rulesPage.ruleForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }

            const id     = rulesPage.ruleId.value;
            const isEdit = id !== '';
            const action = isEdit ? 'update' : 'create';
            const payload = {
                name:      rulesPage.ruleName.value.trim(),
                pct:       parseFloat(rulesPage.rulePct.value),
                is_active: rulesPage.ruleIsActive.checked ? 1 : 0,
            };
            if (isEdit) payload.id = parseInt(id);

            const btnSubmit = document.getElementById('btn-rule-submit');
            if (btnSubmit) btnSubmit.disabled = true;

            const res = await Api.postJson(API_BASE + 'ai_rule_api.php', action, payload);

            if (btnSubmit) btnSubmit.disabled = false;

            if (res.ok) {
                rulesPage.modalRule?.hide();
                rulesPage.ruleForm.reset();
                alert(res.message || (isEdit ? 'Cập nhật thành công.' : 'Thêm quy tắc thành công.'));
                loadAiRules();
            } else {
                alert(res.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
            }
        });
    }

    if (rulesPage.btnConfirmDel) {
        rulesPage.btnConfirmDel.addEventListener('click', async function () {
            const id = parseInt(rulesPage.deleteRuleId.value);
            if (!id) return;
            this.disabled = true;
            const res = await Api.postJson(API_BASE + 'ai_rule_api.php', 'delete', { id });
            this.disabled = false;
            rulesPage.modalDelete?.hide();
            alert(res.message || (res.ok ? 'Đã xóa quy tắc.' : 'Xóa thất bại.'));
            if (res.ok) loadAiRules();
        });
    }


    // ══════════════════════════════════════════════════════════
    // SECTION 6: DỮ LIỆU CẤU HÌNH & GIÁ (admin/master_data.php)
    // ══════════════════════════════════════════════════════════

    const newBrandNameInput = document.getElementById('new-brand-name');
    const btnAddBrand       = document.getElementById('btn-add-brand');
    const brandSelectMD     = document.getElementById('brand-select');
    const modelsTbody       = document.getElementById('models-tbody');
    const modalAddModelEl   = document.getElementById('modal-add-model');
    const formAddModel      = document.getElementById('form-add-model');
    const modelBrandDisplay = document.getElementById('model-brand-display');

    async function loadBrandsForMasterData() {
        if (!brandSelectMD) return;

        const res = await Api.get(API_BASE + 'master_data_api.php', 'get_brands');

        if (!res.ok || !Array.isArray(res.data)) {
            brandSelectMD.innerHTML = '<option value="">Lỗi tải danh sách hãng</option>';
            return;
        }

        const currentVal = brandSelectMD.value;

        brandSelectMD.innerHTML = '<option value="">-- Chọn hãng --</option>' +
            res.data.map(b => `<option value="${b.brand_id}">${Api.esc(b.brand_name)} (${b.model_count})</option>`).join('');

        if (currentVal && res.data.some(b => String(b.brand_id) === currentVal)) {
            brandSelectMD.value = currentVal;
            loadModelsForBrand(currentVal);
        }
    }

    async function loadModelsForBrand(brandId) {
        if (!modelsTbody) return;

        if (!brandId) {
            modelsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Vui lòng chọn hãng.</td></tr>';
            return;
        }

        modelsTbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>`;

        const res = await Api.get(API_BASE + 'master_data_api.php', 'get_models', { brand_id: brandId });

        if (!res.ok || !Array.isArray(res.data)) {
            modelsTbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${Api.esc(res.msg || 'Không thể tải dòng máy.')}</td></tr>`;
            return;
        }

        if (res.data.length === 0) {
            modelsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Hãng này chưa có dòng máy nào.</td></tr>';
            return;
        }

        modelsTbody.innerHTML = res.data.map(m => `
            <tr data-id="${m.model_id}">
                <td>${m.model_id}</td>
                <td>${Api.esc(m.model_name)}</td>
                <td>${m.ram_gb}GB / ${m.rom_gb}GB</td>
                <td>
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <input type="number" min="0" step="100000" class="form-control model-price-input" value="${m.base_price}">
                        <button type="button" class="btn btn-outline-primary btn-save-price" data-id="${m.model_id}">💾</button>
                    </div>
                </td>
                <td class="text-muted small">—</td>
            </tr>
        `).join('');

        bindModelPriceSaveButtons();
    }

    function bindModelPriceSaveButtons() {
        document.querySelectorAll('.btn-save-price').forEach(btn => {
            btn.addEventListener('click', async function () {
                const tr    = this.closest('tr');
                const input = tr.querySelector('.model-price-input');
                const modelId   = parseInt(this.dataset.id, 10);
                const basePrice = parseInt(input.value, 10) || 0;

                this.disabled = true;
                const res = await Api.postJson(API_BASE + 'master_data_api.php', 'update_price', {
                    model_id: modelId,
                    base_price: basePrice,
                });
                this.disabled = false;

                if (res.ok) {
                    alert(res.msg || 'Đã cập nhật giá sàn thành công.');
                } else {
                    alert('Lỗi: ' + (res.msg || 'Không thể cập nhật giá sàn.'));
                }
            });
        });
    }

    if (btnAddBrand) {
        btnAddBrand.addEventListener('click', async function () {
            const name = newBrandNameInput ? newBrandNameInput.value.trim() : '';
            if (!name) {
                alert('Vui lòng nhập tên hãng.');
                return;
            }

            this.disabled = true;
            const res = await Api.postJson(API_BASE + 'master_data_api.php', 'add_brand', { name });
            this.disabled = false;

            if (res.ok) {
                alert(res.msg || 'Đã thêm hãng mới thành công.');
                if (newBrandNameInput) newBrandNameInput.value = '';
                loadBrandsForMasterData();
            } else {
                alert('Lỗi: ' + (res.msg || 'Không thể thêm hãng mới.'));
            }
        });
    }

    if (brandSelectMD) {
        brandSelectMD.addEventListener('change', function () {
            loadModelsForBrand(this.value);
            if (modelBrandDisplay) {
                const opt = this.options[this.selectedIndex];
                modelBrandDisplay.value = opt && opt.value ? opt.textContent.replace(/\s*\(\d+\)$/, '') : '';
            }
        });
    }

    if (modalAddModelEl) {
        modalAddModelEl.addEventListener('show.bs.modal', function () {
            if (modelBrandDisplay) {
                const opt = brandSelectMD.options[brandSelectMD.selectedIndex];
                modelBrandDisplay.value = opt && opt.value ? opt.textContent.replace(/\s*\(\d+\)$/, '') : '';
            }
        });
    }

    if (formAddModel) {
        formAddModel.addEventListener('submit', async function (e) {
            e.preventDefault();

            const brandId = brandSelectMD ? parseInt(brandSelectMD.value, 10) : 0;
            if (!brandId) {
                alert('Vui lòng chọn hãng trước khi thêm dòng máy.');
                return;
            }

            const name      = document.getElementById('model-name-input').value.trim();
            const ramGb     = parseInt(document.getElementById('model-ram-input').value, 10) || 0;
            const romGb     = parseInt(document.getElementById('model-rom-input').value, 10) || 0;
            const basePrice = parseInt(document.getElementById('model-price-input').value, 10) || 0;

            const res = await Api.postJson(API_BASE + 'master_data_api.php', 'add_model', {
                brand_id:   brandId,
                name:       name,
                base_price: basePrice,
                ram_gb:     ramGb,
                rom_gb:     romGb,
            });

            if (res.ok) {
                alert(res.msg || 'Đã thêm dòng máy mới thành công.');
                bootstrap.Modal.getInstance(modalAddModelEl)?.hide();
                formAddModel.reset();
                loadBrandsForMasterData();
                loadModelsForBrand(brandId);
            } else {
                alert('Lỗi: ' + (res.msg || 'Không thể thêm dòng máy mới.'));
            }
        });
    }


    // ══════════════════════════════════════════════════════════
    // INIT — Tự động định tuyến (Routing)
    // ══════════════════════════════════════════════════════════

    const path = window.location.pathname;

    if (path.includes('accounts'))    loadAccounts();
    if (path.includes('inventory'))   loadAdminInventory();
    if (path.includes('history'))     loadGlobalHistory();
    if (path.includes('dashboard'))   loadDashboardMetrics();
    if (path.includes('ai_rules'))    loadAiRules();
    if (path.includes('master_data')) loadBrandsForMasterData();

}());