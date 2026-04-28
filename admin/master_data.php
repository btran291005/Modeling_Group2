<?php
/**
 * admin/master_data.php
 * UC05: Quản lý danh mục thiết bị nền (Master Data)
 *
 * Tính năng:
 *   - Tab 1: Quản lý Hãng (Brands) — CRUD đầy đủ
 *   - Tab 2: Quản lý Dòng máy (Device Models) — CRUD đầy đủ, liên kết Hãng
 *   - Search + filter realtime (AJAX)
 *   - Modal thêm / sửa / xóa cho từng entity
 *   - Tất cả xử lý nghiệp vụ đẩy về api/devices.php
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();
$currentUser = getCurrentUser();

renderHtmlHead('Dữ liệu nền', [
    '../assets/css/pages/admin/master_data.css'
]);
renderSidebar('master_data');
renderMainOpen();
renderTopbar(
    'Dữ liệu nền',
    '<a href="../admin/dashboard.php">Dashboard</a> / Dữ liệu nền'
);
?>

<!-- ===== NỘI DUNG TRANG ===== -->
<div class="page-content">

    <!-- ---------- Page Header ---------- -->
    <div class="md-page-header">
        <div>
            <h2 class="section-title">Quản lý dữ liệu nền</h2>
            <p class="section-desc">Cập nhật hãng sản xuất và dòng thiết bị — dữ liệu nền cho toàn bộ quy trình định giá.</p>
        </div>
    </div>

    <!-- ---------- Tab Navigation ---------- -->
    <div class="md-tabs">
        <button class="md-tab-btn active" data-tab="brands">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            Hãng sản xuất
            <span class="md-tab-count" id="brand-count">—</span>
        </button>
        <button class="md-tab-btn" data-tab="models">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                <line x1="12" y1="18" x2="12.01" y2="18"/>
            </svg>
            Dòng thiết bị
            <span class="md-tab-count" id="model-count">—</span>
        </button>
    </div>

    <!-- ============================================================
         TAB 1: BRANDS
         ============================================================ -->
    <div class="md-tab-panel active" id="panel-brands">

        <!-- Filter + Add -->
        <div class="card md-filter-card">
            <div class="card-body">
                <div class="md-filter-row">
                    <div class="filter-search">
                        <span class="filter-search-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </span>
                        <input class="form-input filter-search-input" type="text" id="brand-search"
                               placeholder="Tìm tên hãng..." autocomplete="off">
                    </div>
                    <button class="btn btn-primary" id="btn-add-brand">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Thêm hãng
                    </button>
                </div>
            </div>
        </div>

        <!-- Brands Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table" id="brands-table">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Tên hãng</th>
                                <th>Số dòng máy</th>
                                <th style="width:160px">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="brands-tbody">
                            <tr>
                                <td colspan="4" class="table-loading">
                                    <div class="loading-spinner-sm"></div> Đang tải...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- end panel-brands -->

    <!-- ============================================================
         TAB 2: DEVICE MODELS
         ============================================================ -->
    <div class="md-tab-panel" id="panel-models">

        <!-- Filter + Add -->
        <div class="card md-filter-card">
            <div class="card-body">
                <div class="md-filter-row">
                    <div class="filter-search">
                        <span class="filter-search-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </span>
                        <input class="form-input filter-search-input" type="text" id="model-search"
                               placeholder="Tìm tên dòng máy..." autocomplete="off">
                    </div>
                    <select class="form-select md-filter-select" id="model-filter-brand">
                        <option value="">Tất cả hãng</option>
                    </select>
                    <button class="btn btn-secondary" id="btn-reset-model-filter">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/>
                        </svg>
                        Đặt lại
                    </button>
                    <button class="btn btn-primary" id="btn-add-model">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Thêm dòng máy
                    </button>
                </div>
            </div>
        </div>

        <!-- Models Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table" id="models-table">
                        <thead>
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Dòng máy</th>
                                <th>Hãng</th>
                                <th>RAM</th>
                                <th>ROM</th>
                                <th>Giá cơ sở</th>
                                <th style="width:160px">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="models-tbody">
                            <tr>
                                <td colspan="7" class="table-loading">
                                    <div class="loading-spinner-sm"></div> Đang tải...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- end panel-models -->

</div><!-- end .page-content -->


<!-- ============================================================
     MODAL: THÊM / SỬA HÃNG
     ============================================================ -->
<div class="modal-overlay" id="modal-brand" role="dialog" aria-modal="true" aria-labelledby="modal-brand-title">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-brand-title">Thêm hãng mới</h3>
            <button class="modal-close" data-close="modal-brand" aria-label="Đóng">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="brand-id-input" value="">
            <div class="form-group">
                <label class="form-label" for="brand-name-input">
                    Tên hãng <span class="required-star">*</span>
                </label>
                <input class="form-input" type="text" id="brand-name-input"
                       placeholder="Ví dụ: Apple, Samsung, Xiaomi..." maxlength="80">
                <span class="form-hint">Tối đa 80 ký tự. Phải là duy nhất trong hệ thống.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-close="modal-brand">Hủy</button>
            <button class="btn btn-primary" id="btn-save-brand">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                </svg>
                Lưu
            </button>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL: THÊM / SỬA DÒNG MÁY
     ============================================================ -->
<div class="modal-overlay" id="modal-model" role="dialog" aria-modal="true" aria-labelledby="modal-model-title">
    <div class="modal-box modal-md">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-model-title">Thêm dòng máy mới</h3>
            <button class="modal-close" data-close="modal-model" aria-label="Đóng">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="model-id-input" value="">
            <div class="form-grid-2">
                <!-- Tên dòng máy -->
                <div class="form-group col-span-2">
                    <label class="form-label" for="model-name-input">
                        Tên dòng máy <span class="required-star">*</span>
                    </label>
                    <input class="form-input" type="text" id="model-name-input"
                           placeholder="Ví dụ: iPhone 14 Pro Max, Galaxy S24 Ultra..." maxlength="120">
                </div>
                <!-- Hãng -->
                <div class="form-group">
                    <label class="form-label" for="model-brand-select">
                        Hãng sản xuất <span class="required-star">*</span>
                    </label>
                    <select class="form-select" id="model-brand-select">
                        <option value="">-- Chọn hãng --</option>
                    </select>
                </div>
                <!-- Giá cơ sở -->
                <div class="form-group">
                    <label class="form-label" for="model-price-input">
                        Giá cơ sở (VNĐ) <span class="required-star">*</span>
                    </label>
                    <div class="input-with-suffix">
                        <input class="form-input" type="number" id="model-price-input"
                               placeholder="15000000" min="0" max="99999999" step="100000">
                        <span class="input-suffix">₫</span>
                    </div>
                    <span class="form-hint" id="price-preview">—</span>
                </div>
                <!-- RAM -->
                <div class="form-group">
                    <label class="form-label" for="model-ram-input">
                        RAM (GB) <span class="required-star">*</span>
                    </label>
                    <select class="form-select" id="model-ram-input">
                        <option value="">-- Chọn RAM --</option>
                        <option value="2">2 GB</option>
                        <option value="3">3 GB</option>
                        <option value="4">4 GB</option>
                        <option value="6">6 GB</option>
                        <option value="8">8 GB</option>
                        <option value="12">12 GB</option>
                        <option value="16">16 GB</option>
                    </select>
                </div>
                <!-- ROM -->
                <div class="form-group">
                    <label class="form-label" for="model-rom-input">
                        ROM / Bộ nhớ (GB) <span class="required-star">*</span>
                    </label>
                    <select class="form-select" id="model-rom-input">
                        <option value="">-- Chọn ROM --</option>
                        <option value="32">32 GB</option>
                        <option value="64">64 GB</option>
                        <option value="128">128 GB</option>
                        <option value="256">256 GB</option>
                        <option value="512">512 GB</option>
                        <option value="1024">1 TB</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-close="modal-model">Hủy</button>
            <button class="btn btn-primary" id="btn-save-model">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                </svg>
                Lưu
            </button>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL: XÁC NHẬN XÓA
     ============================================================ -->
<div class="modal-overlay" id="modal-delete" role="dialog" aria-modal="true">
    <div class="modal-box modal-sm">
        <div class="modal-header modal-header-danger">
            <h3 class="modal-title">Xác nhận xóa</h3>
            <button class="modal-close" data-close="modal-delete" aria-label="Đóng">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="delete-confirm-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
            </div>
            <p class="delete-confirm-text" id="delete-confirm-text">
                Bạn có chắc muốn xóa mục này không?
            </p>
            <p class="delete-confirm-sub">Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-close="modal-delete">Hủy</button>
            <button class="btn btn-danger" id="btn-confirm-delete">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
                Xóa
            </button>
        </div>
    </div>
</div>


<!-- ============================================================
     TOAST CONTAINER
     ============================================================ -->
<div class="toast-container" id="toast-container"></div>


<?php
renderLayoutClose(['../assets/js/main.js']);
?>

<!-- ============================================================
     JAVASCRIPT — Master Data
     ============================================================ -->
<script>
(function () {
    'use strict';

    /* ----------------------------------------------------------
     * STATE
     * ---------------------------------------------------------- */
    const API = '../api/devices.php';
    let allBrands  = [];  // Cache danh sách hãng
    let allModels  = [];  // Cache danh sách dòng máy

    // Xóa: lưu tạm thông tin cần xóa
    let pendingDelete = { type: '', id: null };


    /* ----------------------------------------------------------
     * HELPERS
     * ---------------------------------------------------------- */
    async function apiFetch(body) {
        const fd = new FormData();
        Object.entries(body).forEach(([k, v]) => fd.append(k, v));
        const res  = await fetch(API, { method: 'POST', body: fd });
        return res.json();
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const iconMap = {
            success: '<polyline points="20 6 9 17 4 12"/>',
            error:   '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>',
        };
        toast.innerHTML = `
            <svg class="toast-icon" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                ${iconMap[type] || iconMap.success}
            </svg>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3500);
    }

    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }

    // Đóng modal qua nút data-close
    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });
    // Đóng modal khi click overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    function setLoading(btnEl, loading) {
        btnEl.disabled = loading;
        btnEl.style.opacity = loading ? '0.7' : '';
    }


    /* ----------------------------------------------------------
     * TAB SWITCHING
     * ---------------------------------------------------------- */
    document.querySelectorAll('.md-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.md-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.md-tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(`panel-${btn.dataset.tab}`).classList.add('active');
        });
    });


    /* ----------------------------------------------------------
     * BRANDS MODULE
     * ---------------------------------------------------------- */
    async function loadBrands() {
        const data = await apiFetch({ action: 'get_brands' });
        if (!data.success) { showToast('Không tải được danh sách hãng.', 'error'); return; }

        allBrands = data.data;
        document.getElementById('brand-count').textContent = allBrands.length;
        renderBrands(allBrands);
        populateBrandDropdowns();
    }

    function renderBrands(list) {
        const tbody = document.getElementById('brands-tbody');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="table-empty">Không có hãng nào.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map((b, idx) => `
            <tr data-brand-id="${b.brand_id}">
                <td class="text-muted">${idx + 1}</td>
                <td>
                    <div class="brand-name-cell">
                        <span class="brand-dot"></span>
                        <strong>${escHtml(b.brand_name)}</strong>
                    </div>
                </td>
                <td>
                    <span class="badge badge-default">${b.model_count} dòng máy</span>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn-action btn-action-edit" title="Sửa"
                                onclick="editBrand(${b.brand_id}, '${escHtml(b.brand_name)}')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="btn-action btn-action-delete" title="Xóa"
                                onclick="deleteBrand(${b.brand_id}, '${escHtml(b.brand_name)}', ${b.model_count})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Search brands
    document.getElementById('brand-search').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        renderBrands(allBrands.filter(b => b.brand_name.toLowerCase().includes(q)));
    });

    // Mở modal thêm hãng
    document.getElementById('btn-add-brand').addEventListener('click', () => {
        document.getElementById('modal-brand-title').textContent = 'Thêm hãng mới';
        document.getElementById('brand-id-input').value = '';
        document.getElementById('brand-name-input').value = '';
        openModal('modal-brand');
        document.getElementById('brand-name-input').focus();
    });

    // Sửa hãng (global function cho onclick trong HTML)
    window.editBrand = function (id, name) {
        document.getElementById('modal-brand-title').textContent = 'Chỉnh sửa hãng';
        document.getElementById('brand-id-input').value = id;
        document.getElementById('brand-name-input').value = name;
        openModal('modal-brand');
        document.getElementById('brand-name-input').focus();
    };

    // Xóa hãng
    window.deleteBrand = function (id, name, modelCount) {
        if (modelCount > 0) {
            showToast(`Không thể xóa hãng "${name}" vì còn ${modelCount} dòng máy liên kết.`, 'warning');
            return;
        }
        pendingDelete = { type: 'brand', id };
        document.getElementById('delete-confirm-text').textContent =
            `Bạn có chắc muốn xóa hãng "${name}"?`;
        openModal('modal-delete');
    };

    // Lưu hãng (tạo / cập nhật)
    document.getElementById('btn-save-brand').addEventListener('click', async function () {
        const name = document.getElementById('brand-name-input').value.trim();
        const id   = document.getElementById('brand-id-input').value;

        if (!name) { showToast('Vui lòng nhập tên hãng.', 'warning'); return; }

        setLoading(this, true);
        const action = id ? 'update_brand' : 'create_brand';
        const body   = { action, brand_name: name };
        if (id) body.brand_id = id;

        const data = await apiFetch(body);
        setLoading(this, false);

        if (data.success) {
            showToast(data.message);
            closeModal('modal-brand');
            loadBrands();
        } else {
            showToast(data.message, 'error');
        }
    });


    /* ----------------------------------------------------------
     * MODELS MODULE
     * ---------------------------------------------------------- */
    async function loadModels() {
        const data = await apiFetch({ action: 'get_models' });
        if (!data.success) { showToast('Không tải được danh sách dòng máy.', 'error'); return; }

        allModels = data.data;
        document.getElementById('model-count').textContent = allModels.length;
        renderModels(allModels);
    }

    function renderModels(list) {
        const tbody = document.getElementById('models-tbody');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="table-empty">Không có dòng máy nào.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map((m, idx) => `
            <tr data-model-id="${m.model_id}">
                <td class="text-muted">${idx + 1}</td>
                <td><strong>${escHtml(m.model_name)}</strong></td>
                <td>
                    <span class="badge badge-default">${escHtml(m.brand_name)}</span>
                </td>
                <td>${m.ram_gb} GB</td>
                <td>${m.rom_gb >= 1024 ? (m.rom_gb / 1024) + ' TB' : m.rom_gb + ' GB'}</td>
                <td data-vnd="${m.base_price}" class="price-cell"></td>
                <td>
                    <div class="action-btns">
                        <button class="btn-action btn-action-edit" title="Sửa"
                                onclick="editModel(${m.model_id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="btn-action btn-action-delete" title="Xóa"
                                onclick="deleteModel(${m.model_id}, '${escHtml(m.model_name)}')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        // Format giá VNĐ
        document.querySelectorAll('#models-tbody [data-vnd]').forEach(el => {
            el.textContent = formatVND(parseInt(el.dataset.vnd, 10));
        });
    }

    function populateBrandDropdowns() {
        // Filter dropdown trong tab models
        const filterSel  = document.getElementById('model-filter-brand');
        const current    = filterSel.value;
        filterSel.innerHTML = '<option value="">Tất cả hãng</option>' +
            allBrands.map(b => `<option value="${b.brand_id}">${escHtml(b.brand_name)}</option>`).join('');
        filterSel.value = current;

        // Select trong modal dòng máy
        const modalSel = document.getElementById('model-brand-select');
        const cur2     = modalSel.value;
        modalSel.innerHTML = '<option value="">-- Chọn hãng --</option>' +
            allBrands.map(b => `<option value="${b.brand_id}">${escHtml(b.brand_name)}</option>`).join('');
        modalSel.value = cur2;
    }

    // Search + filter models
    function applyModelFilters() {
        const q       = document.getElementById('model-search').value.toLowerCase();
        const brandId = document.getElementById('model-filter-brand').value;
        renderModels(allModels.filter(m => {
            const matchQ = !q || m.model_name.toLowerCase().includes(q)
                              || m.brand_name.toLowerCase().includes(q);
            const matchB = !brandId || String(m.brand_id) === brandId;
            return matchQ && matchB;
        }));
    }
    document.getElementById('model-search').addEventListener('input', applyModelFilters);
    document.getElementById('model-filter-brand').addEventListener('change', applyModelFilters);
    document.getElementById('btn-reset-model-filter').addEventListener('click', () => {
        document.getElementById('model-search').value = '';
        document.getElementById('model-filter-brand').value = '';
        renderModels(allModels);
    });

    // Preview giá khi nhập
    document.getElementById('model-price-input').addEventListener('input', function () {
        const v = parseInt(this.value, 10);
        document.getElementById('price-preview').textContent = isNaN(v) ? '—' : '≈ ' + formatVND(v);
    });

    // Mở modal thêm dòng máy
    document.getElementById('btn-add-model').addEventListener('click', () => {
        document.getElementById('modal-model-title').textContent = 'Thêm dòng máy mới';
        document.getElementById('model-id-input').value = '';
        document.getElementById('model-name-input').value = '';
        document.getElementById('model-brand-select').value = '';
        document.getElementById('model-price-input').value = '';
        document.getElementById('model-ram-input').value = '';
        document.getElementById('model-rom-input').value = '';
        document.getElementById('price-preview').textContent = '—';
        openModal('modal-model');
        document.getElementById('model-name-input').focus();
    });

    // Sửa dòng máy
    window.editModel = function (id) {
        const m = allModels.find(x => x.model_id == id);
        if (!m) return;
        document.getElementById('modal-model-title').textContent = 'Chỉnh sửa dòng máy';
        document.getElementById('model-id-input').value  = m.model_id;
        document.getElementById('model-name-input').value = m.model_name;
        document.getElementById('model-brand-select').value = m.brand_id;
        document.getElementById('model-price-input').value = m.base_price;
        document.getElementById('model-ram-input').value = m.ram_gb;
        document.getElementById('model-rom-input').value = m.rom_gb;
        document.getElementById('price-preview').textContent = '≈ ' + formatVND(parseInt(m.base_price, 10));
        openModal('modal-model');
    };

    // Xóa dòng máy
    window.deleteModel = function (id, name) {
        pendingDelete = { type: 'model', id };
        document.getElementById('delete-confirm-text').textContent =
            `Bạn có chắc muốn xóa dòng máy "${name}"?`;
        openModal('modal-delete');
    };

    // Lưu dòng máy
    document.getElementById('btn-save-model').addEventListener('click', async function () {
        const id       = document.getElementById('model-id-input').value;
        const name     = document.getElementById('model-name-input').value.trim();
        const brandId  = document.getElementById('model-brand-select').value;
        const price    = document.getElementById('model-price-input').value;
        const ram      = document.getElementById('model-ram-input').value;
        const rom      = document.getElementById('model-rom-input').value;

        if (!name)    { showToast('Vui lòng nhập tên dòng máy.', 'warning'); return; }
        if (!brandId) { showToast('Vui lòng chọn hãng sản xuất.', 'warning'); return; }
        if (!price || parseInt(price) <= 0) { showToast('Vui lòng nhập giá cơ sở hợp lệ.', 'warning'); return; }
        if (!ram)     { showToast('Vui lòng chọn RAM.', 'warning'); return; }
        if (!rom)     { showToast('Vui lòng chọn ROM.', 'warning'); return; }

        setLoading(this, true);
        const action = id ? 'update_model' : 'create_model';
        const body = { action, model_name: name, brand_id: brandId, base_price: price, ram_gb: ram, rom_gb: rom };
        if (id) body.model_id = id;

        const data = await apiFetch(body);
        setLoading(this, false);

        if (data.success) {
            showToast(data.message);
            closeModal('modal-model');
            loadModels();
        } else {
            showToast(data.message, 'error');
        }
    });


    /* ----------------------------------------------------------
     * DELETE CONFIRM
     * ---------------------------------------------------------- */
    document.getElementById('btn-confirm-delete').addEventListener('click', async function () {
        const { type, id } = pendingDelete;
        if (!id) return;

        setLoading(this, true);
        const actionMap = { brand: 'delete_brand', model: 'delete_model' };
        const idMap     = { brand: 'brand_id',     model: 'model_id' };
        const body = { action: actionMap[type], [idMap[type]]: id };

        const data = await apiFetch(body);
        setLoading(this, false);

        if (data.success) {
            showToast(data.message);
            closeModal('modal-delete');
            if (type === 'brand')  loadBrands();
            else                   loadModels();
        } else {
            showToast(data.message, 'error');
            closeModal('modal-delete');
        }
        pendingDelete = { type: '', id: null };
    });


    /* ----------------------------------------------------------
     * UTILS
     * ---------------------------------------------------------- */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }


    /* ----------------------------------------------------------
     * INIT
     * ---------------------------------------------------------- */
    loadBrands();
    loadModels();

}());
</script>