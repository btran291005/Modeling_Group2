// ============================================================
// FILE: assets/js/staff_app.js
// Logic xử lý giao diện Định giá (Staff) + Quản lý Kho + Lịch sử
// Phụ thuộc: assets/js/api.js (object Api)
// ============================================================
'use strict';

(function () {

    /* ----------------------------------------------------------
     * DOM REFS — Trang Định giá (valuation.php)
     * ---------------------------------------------------------- */
    const brandSelect   = document.getElementById('brand-select');
    const modelSelect   = document.getElementById('model-select');
    const infoRam       = document.getElementById('info-ram');
    const infoRom       = document.getElementById('info-rom');
    const infoBasePrice = document.getElementById('info-base-price');

    const batteryInput  = document.getElementById('battery-health');
    const scratchSelect = document.getElementById('scratch-level');
    const rulesChecklist= document.getElementById('rules-checklist');

    const btnRunAi      = document.getElementById('btn-run-ai');
    const btnConfirm    = document.getElementById('btn-confirm');
    const btnDecline    = document.getElementById('btn-decline');

    const placeholderEl = document.getElementById('result-placeholder');
    const resultBox     = document.getElementById('result-box');
    const resultPrice   = document.getElementById('result-price');
    const resultReason  = document.getElementById('result-reasoning');
    const resultDone    = document.getElementById('result-done');

    const customerPhone = document.getElementById('customer-phone');
    const customerName  = document.getElementById('customer-name');
    const deviceImei    = document.getElementById('device-imei');

    /* ----------------------------------------------------------
     * STATE (valuation page)
     * ---------------------------------------------------------- */
    let currentSessionId = null;
    let currentPrice     = null;

    /* ----------------------------------------------------------
     * INIT — Load Brands + Active Rules (chỉ chạy nếu có form)
     * ---------------------------------------------------------- */
    async function initValuation() {
        if (!brandSelect) return; // không phải trang định giá -> bỏ qua

        // Load brands
        const brandRes = await Api.get('valuation_api.php', 'brands');
        if (brandRes.ok && Array.isArray(brandRes.data)) {
            brandSelect.innerHTML = '<option value="">-- Chọn hãng --</option>' +
                brandRes.data.map(b => `<option value="${b.brand_id}">${Api.esc(b.brand_name)}</option>`).join('');
        } else {
            brandSelect.innerHTML = '<option value="">Lỗi tải dữ liệu hãng</option>';
        }

        // Load active rules
        const rulesRes = await Api.get('valuation_api.php', 'rules');
        if (rulesRes.ok && Array.isArray(rulesRes.data) && rulesRes.data.length > 0) {
            rulesChecklist.innerHTML = rulesRes.data.map(r => `
                <div class="form-check">
                    <input class="form-check-input rule-checkbox" type="checkbox"
                           value="${r.rule_id}" data-pct="${r.deduction_percent}" id="rule-${r.rule_id}">
                    <label class="form-check-label small" for="rule-${r.rule_id}">
                        ${Api.esc(r.condition_name)}
                        <span class="badge bg-danger-subtle text-danger">-${parseFloat(r.deduction_percent).toFixed(1)}%</span>
                    </label>
                </div>
            `).join('');
        } else {
            rulesChecklist.innerHTML = '<div class="text-muted small">Không có quy tắc nào đang hoạt động.</div>';
        }
    }

    if (brandSelect) {
        /* ------------------------------------------------------
         * EVENT: Chọn hãng -> Load models
         * ------------------------------------------------------ */
        brandSelect.addEventListener('change', async function () {
            const brandId = this.value;

            resetDeviceInfo();
            btnRunAi.disabled = true;

            if (!brandId) {
                modelSelect.innerHTML = '<option value="">-- Chọn hãng trước --</option>';
                modelSelect.disabled = true;
                return;
            }

            modelSelect.disabled = true;
            modelSelect.innerHTML = '<option value="">Đang tải...</option>';

            const res = await Api.get('valuation_api.php', 'models', { brand_id: brandId });

            if (res.ok && Array.isArray(res.data) && res.data.length > 0) {
                modelSelect.innerHTML = '<option value="">-- Chọn dòng máy --</option>' +
                    res.data.map(m => `
                        <option value="${m.model_id}"
                                data-ram="${m.ram_gb}"
                                data-rom="${m.rom_gb}"
                                data-price="${m.base_price}">
                            ${Api.esc(m.model_name)} (${m.ram_gb}GB/${m.rom_gb}GB)
                        </option>
                    `).join('');
                modelSelect.disabled = false;
            } else {
                modelSelect.innerHTML = '<option value="">Không có dòng máy nào</option>';
            }
        });

        /* ------------------------------------------------------
         * EVENT: Chọn model -> Hiện cấu hình
         * ------------------------------------------------------ */
        modelSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) {
                resetDeviceInfo();
                btnRunAi.disabled = true;
                return;
            }

            infoRam.value       = opt.dataset.ram + ' GB';
            infoRom.value       = opt.dataset.rom + ' GB';
            infoBasePrice.value = Api.vnd(parseInt(opt.dataset.price, 10));

            btnRunAi.disabled = false;
        });

        /* ------------------------------------------------------
         * EVENT: Click "Chạy AI Định Giá"
         * ------------------------------------------------------ */
        btnRunAi.addEventListener('click', async function () {
            const modelId       = modelSelect.value;
            const batteryHealth = batteryInput.value;
            const scratchLevel  = scratchSelect.value;

            if (!modelId) {
                alert('Vui lòng chọn dòng máy.');
                return;
            }
            if (!batteryHealth || batteryHealth < 1 || batteryHealth > 100) {
                alert('Vui lòng nhập tình trạng pin hợp lệ (1-100).');
                return;
            }

            const ruleIds = Array.from(document.querySelectorAll('.rule-checkbox:checked'))
                .map(cb => parseInt(cb.value, 10));

            const payload = {
                model_id:       parseInt(modelId, 10),
                battery_health: parseInt(batteryHealth, 10),
                scratch_level:  parseInt(scratchLevel, 10),
                rule_ids:       ruleIds,
            };

            btnRunAi.disabled = true;
            btnRunAi.textContent = '⏳ AI đang phân tích...';

            const res = await Api.post('valuation_api.php', 'run_ai', payload);

            btnRunAi.disabled = false;
            btnRunAi.textContent = '🤖 Chạy AI Định Giá';

            if (!res.ok) {
                alert('Lỗi: ' + (res.msg || 'Không thể định giá. Vui lòng thử lại.'));
                return;
            }

            currentSessionId = res.data.session_id;
            currentPrice     = res.data.price;

            resultPrice.textContent  = res.data.price_formatted || Api.vnd(res.data.price);
            resultReason.textContent = res.data.reasoning || '';

            resultDone.classList.add('d-none');
            resultDone.textContent = '';
            btnConfirm.disabled = false;
            btnDecline.disabled = false;

            placeholderEl.classList.add('d-none');
            resultBox.classList.remove('d-none');
        });

        /* ------------------------------------------------------
         * EVENT: Click "Chốt thu mua & Nhập kho"
         * ------------------------------------------------------ */
        btnConfirm.addEventListener('click', async function () {
            const phone = customerPhone.value.trim();
            const name  = customerName.value.trim();
            const imei  = deviceImei.value.trim();

            if (!phone || !name || !imei) {
                alert('Vui lòng điền đầy đủ Số điện thoại, Tên khách hàng và IMEI.');
                return;
            }
            if (!/^\d{15}$/.test(imei)) {
                alert('IMEI phải gồm đúng 15 chữ số.');
                return;
            }
            if (!currentSessionId) {
                alert('Vui lòng chạy AI Định giá trước khi chốt thu mua.');
                return;
            }

            const payload = {
                session_id:     currentSessionId,
                imei:           imei,
                customer_name:  name,
                customer_phone: phone,
            };

            btnConfirm.disabled = true;
            btnConfirm.textContent = '⏳ Đang xử lý...';

            const res = await Api.post('valuation_api.php', 'confirm', payload);

            btnConfirm.disabled = false;
            btnConfirm.textContent = '✅ Chốt thu mua & Nhập kho';

            if (!res.ok) {
                alert('Lỗi: ' + (res.msg || 'Không thể chốt thu mua.'));
                return;
            }

            alert('Nhập kho thành công! IMEI: ' + (res.data.imei || imei));

            resultDone.classList.remove('d-none');
            resultDone.textContent = '✅ Đã hoàn tất phiên định giá #' + currentSessionId + '. IMEI: ' + imei;

            resetForm();
        });

        /* ------------------------------------------------------
         * EVENT: Click "Từ chối"
         * ------------------------------------------------------ */
        btnDecline.addEventListener('click', async function () {
            if (!currentSessionId) return;
            if (!confirm('Xác nhận khách từ chối bán với mức giá này?')) return;

            const res = await Api.post('valuation_api.php', 'decline', { session_id: currentSessionId });

            if (!res.ok) {
                alert('Lỗi: ' + (res.msg || 'Không thể cập nhật.'));
                return;
            }

            resultDone.classList.remove('d-none');
            resultDone.classList.replace('alert-success', 'alert-secondary');
            resultDone.textContent = '❌ Đã ghi nhận từ chối phiên #' + currentSessionId + '.';

            resetForm();
        });
    }

    /* ----------------------------------------------------------
     * RESET toàn bộ form sau khi xử lý xong (valuation page)
     * ---------------------------------------------------------- */
    function resetDeviceInfo() {
        if (!infoRam) return;
        infoRam.value       = '';
        infoRom.value       = '';
        infoBasePrice.value = '';
    }

    function resetForm() {
        currentSessionId = null;
        currentPrice     = null;

        document.getElementById('valuation-form').reset();
        resetDeviceInfo();

        modelSelect.innerHTML = '<option value="">-- Chọn hãng trước --</option>';
        modelSelect.disabled  = true;

        document.querySelectorAll('.rule-checkbox').forEach(cb => cb.checked = false);

        btnRunAi.disabled = true;
        btnConfirm.disabled = true;
        btnDecline.disabled = true;

        customerPhone.value = '';
        customerName.value  = '';
        deviceImei.value    = '';

        setTimeout(() => {
            resultBox.classList.add('d-none');
            placeholderEl.classList.remove('d-none');
            resultDone.classList.add('d-none');
            resultDone.classList.replace('alert-secondary', 'alert-success');
        }, 2500);
    }


    /* ============================================================
     * QUẢN LÝ KHO (staff/inventory.php)
     * ============================================================ */

    const invTableBody = document.getElementById('inventory-tbody');
    const invSearch    = document.getElementById('inv-search');

    const STATUS_LABELS = {
        'Pending':       'Chờ kiểm định',
        'Stored':        'Đang lưu kho',
        'Refurbishing':  'Đang tân trang',
        'Sold':          'Đã bán',
    };

    /**
     * Render select trạng thái cho 1 dòng
     */
    function buildStatusSelect(id, currentStatus) {
        const options = Object.entries(STATUS_LABELS).map(([value, label]) => {
            const selected = value === currentStatus ? 'selected' : '';
            return `<option value="${value}" ${selected}>${label}</option>`;
        }).join('');

        return `<select class="form-select form-select-sm status-select" data-id="${id}">${options}</select>`;
    }

    /**
     * Load danh sách thiết bị trong kho
     */
    async function loadInventory() {
        if (!invTableBody) return;

        invTableBody.innerHTML = `
            <tr><td colspan="8" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const res = await Api.get('../api/inventory_api.php', 'list');

        if (!res.ok || !Array.isArray(res.data)) {
            invTableBody.innerHTML = `
                <tr><td colspan="8" class="text-center text-danger py-4">
                    ${Api.esc(res.msg || 'Không thể tải dữ liệu kho.')}
                </td></tr>`;
            return;
        }

        if (res.data.length === 0) {
            invTableBody.innerHTML = `
                <tr><td colspan="8" class="text-center text-muted py-4">Kho đang trống.</td></tr>`;
            return;
        }

        invTableBody.innerHTML = res.data.map(item => {
            const deviceName = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim();
            const config      = `${item.ram_gb ?? '-'}GB / ${item.rom_gb ?? '-'}GB`;
            const battery     = (item.battery_health !== undefined && item.battery_health !== null)
                ? `${item.battery_health}%` : '—';
            const price       = Api.vnd(item.price);
            const customer    = item.customer_name
                ? `${Api.esc(item.customer_name)}<br><small class="text-muted">${Api.esc(item.phone_number || '')}</small>`
                : '<span class="text-muted">—</span>';
            const receivedAt  = item.received_at || '—';

            return `
                <tr data-search="${Api.esc((item.imei || '') + ' ' + deviceName).toLowerCase()}">
                    <td class="text-monospace">${Api.esc(item.imei || '—')}</td>
                    <td>${deviceName || '—'}</td>
                    <td>${config}</td>
                    <td>${battery}</td>
                    <td>${price}</td>
                    <td>${customer}</td>
                    <td>${Api.esc(receivedAt)}</td>
                    <td>${buildStatusSelect(item.id, item.status)}</td>
                </tr>
            `;
        }).join('');

        // Gắn lại event change cho các select trạng thái
        bindStatusSelectEvents();

        // Áp dụng lại filter tìm kiếm hiện tại (nếu có)
        applyInventorySearch();
    }

    /**
     * Gắn sự kiện change cho .status-select
     */
    function bindStatusSelectEvents() {
        document.querySelectorAll('.status-select').forEach(sel => {
            sel.addEventListener('change', async function () {
                const id     = this.dataset.id;
                const status = this.value;

                this.disabled = true;

                const res = await Api.postJson('../api/inventory_api.php', 'update_status', {
                    id:     parseInt(id, 10),
                    status: status,
                });

                this.disabled = false;

                if (res.ok) {
                    alert('Cập nhật thành công!');
                    loadInventory();
                } else {
                    alert('Lỗi: ' + (res.msg || 'Không thể cập nhật trạng thái.'));
                }
            });
        });
    }

    /**
     * Tìm kiếm client-side: lọc các <tr> theo IMEI / tên máy
     */
    function applyInventorySearch() {
        if (!invSearch) return;
        const keyword = invSearch.value.trim().toLowerCase();

        document.querySelectorAll('#inventory-tbody tr').forEach(tr => {
            const haystack = tr.dataset.search || '';
            tr.style.display = (!keyword || haystack.includes(keyword)) ? '' : 'none';
        });
    }

    if (invSearch) {
        invSearch.addEventListener('input', applyInventorySearch);
    }


    /* ============================================================
     * LỊCH SỬ ĐỊNH GIÁ (staff/history.php)
     * ============================================================ */

    const historyTableBody = document.getElementById('history-tbody');
    const historySearch    = document.getElementById('history-search');

    const SESSION_STATUS_LABELS = {
        'Pending':   '<span class="badge bg-warning text-dark">Đang chờ</span>',
        'Purchased': '<span class="badge bg-success">Đã mua</span>',
        'Declined':  '<span class="badge bg-danger">Từ chối</span>',
    };

    /**
     * Load nhật ký định giá của Staff hiện tại
     */
    async function loadHistory() {
        if (!historyTableBody) return;

        historyTableBody.innerHTML = `
            <tr><td colspan="8" class="text-center text-muted py-4">Đang tải dữ liệu...</td></tr>
        `;

        const res = await Api.get('../api/valuation_api.php', 'history');

        if (!res.ok || !Array.isArray(res.data)) {
            historyTableBody.innerHTML = `
                <tr><td colspan="8" class="text-center text-danger py-4">
                    ${Api.esc(res.msg || 'Không thể tải lịch sử định giá.')}
                </td></tr>`;
            return;
        }

        if (res.data.length === 0) {
            historyTableBody.innerHTML = `
                <tr><td colspan="8" class="text-center text-muted py-4">Bạn chưa có phiên định giá nào.</td></tr>`;
            return;
        }

        historyTableBody.innerHTML = res.data.map(item => {
            const deviceName = `${Api.esc(item.brand_name || '')} ${Api.esc(item.model_name || '')}`.trim();
            const config      = `${item.ram_gb ?? '-'}GB / ${item.rom_gb ?? '-'}GB`;
            const battery     = (item.battery_health !== undefined && item.battery_health !== null)
                ? `${item.battery_health}%` : '—';
            const aiPrice     = Api.vnd(item.ai_suggested_price);
            const finalPrice  = (item.final_price !== undefined && item.final_price !== null)
                ? Api.vnd(item.final_price)
                : '<span class="text-muted">—</span>';
            const rules       = item.applied_rules
                ? Api.esc(item.applied_rules)
                : '<span class="text-muted">—</span>';
            const statusBadge = SESSION_STATUS_LABELS[item.final_status] || Api.esc(item.final_status || '—');
            const createdAt   = item.created_at || '—';

            const searchText = `${(item.created_at || '')} ${deviceName}`.toLowerCase();

            return `
                <tr data-search="${Api.esc(searchText)}">
                    <td class="text-monospace">${Api.esc(createdAt)}</td>
                    <td>${deviceName || '—'}</td>
                    <td>${config}</td>
                    <td>${battery}</td>
                    <td>${rules}</td>
                    <td>${aiPrice}</td>
                    <td>${finalPrice}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        }).join('');

        // Áp dụng lại filter tìm kiếm hiện tại (nếu có)
        applyHistorySearch();
    }

    /**
     * Tìm kiếm client-side: lọc các <tr> theo tên máy hoặc ngày tháng
     */
    function applyHistorySearch() {
        if (!historySearch) return;
        const keyword = historySearch.value.trim().toLowerCase();

        document.querySelectorAll('#history-tbody tr').forEach(tr => {
            const haystack = tr.dataset.search || '';
            tr.style.display = (!keyword || haystack.includes(keyword)) ? '' : 'none';
        });
    }

    if (historySearch) {
        historySearch.addEventListener('keyup', applyHistorySearch);
    }


    /* ----------------------------------------------------------
     * START
     * ---------------------------------------------------------- */
    initValuation();
    loadInventory();
    loadHistory();

})();