<?php
// ============================================================
// FILE: admin/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Bảng điều khiển Tổng quan');
?>

<!-- ─── PAGE HEADER ─── -->
<div class="page-header">
    <h2>📊 Bảng điều khiển Tổng quan</h2>
    <p>Toàn cảnh hoạt động hệ thống — dữ liệu cập nhật theo thời gian thực.</p>
</div>

<!-- ============================================================
     SECTION 1: 4 STAT CARDS
     ============================================================ -->
<div class="row g-3 mb-4" id="dashboard-cards">

    <div class="col-xl-3 col-md-6">
        <div class="card stat-card text-bg-primary">
            <div class="stat-label">Tổng nhân sự</div>
            <div class="stat-value" id="card-total-staff">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div class="stat-sub">Admin + Staff đang hoạt động</div>
            <div class="stat-icon">👥</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card stat-card text-bg-info">
            <div class="stat-label">Máy tồn kho</div>
            <div class="stat-value" id="card-in-stock">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div class="stat-sub">Stored + Refurbishing</div>
            <div class="stat-icon">📦</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card stat-card text-bg-success">
            <div class="stat-label">Đã bán</div>
            <div class="stat-value" id="card-sold">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div class="stat-sub">Tổng thiết bị đã xuất kho</div>
            <div class="stat-icon">✅</div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card stat-card text-bg-warning">
            <div class="stat-label">Tổng vốn đã chi</div>
            <div class="stat-value" id="card-total-spent" style="font-size: 1.35rem;">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div class="stat-sub">Tổng giá AI đề xuất (đã mua)</div>
            <div class="stat-icon">💰</div>
        </div>
    </div>

</div>

<!-- ============================================================
     SECTION 2: BIỂU ĐỒ KHO — FULL WIDTH
     ============================================================ -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>📊 Trạng thái Kho hàng</span>
        <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25 px-3 py-2"
              id="badge-total-gadgets">
            — thiết bị
        </span>
    </div>
    <div class="card-body">
        <div class="row g-4 align-items-center">

            <!-- Donut Chart -->
            <div class="col-md-4 d-flex justify-content-center">
                <div class="position-relative">
                    <canvas id="stock-status-chart"
                            width="220" height="220"
                            style="max-width: 220px; max-height: 220px;"></canvas>
                </div>
            </div>

            <!-- Legend + Stats -->
            <div class="col-md-4">
                <div id="stock-status-legend" class="d-flex flex-column gap-3">
                    <div class="text-center text-muted small py-4">
                        <span class="spinner-border spinner-border-sm me-1"></span> Đang tải...
                    </div>
                </div>
            </div>

            <!-- Attention Devices -->
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="fw-bold text-warning" style="font-size:0.85rem;">⚠️ THIẾT BỊ CẦN LƯU Ý</span>
                </div>
                <div id="attention-list"
                     style="max-height: 280px; overflow-y: auto;">
                    <div class="text-center text-muted small py-4">
                        <span class="spinner-border spinner-border-sm me-1"></span> Đang tải...
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ============================================================
     SECTION 3: GIAO DỊCH MỚI NHẤT — FULL WIDTH
     ============================================================ -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>🕘 Giao dịch mới nhất</span>
        <a href="history.php" class="btn btn-sm btn-outline-info">
            Xem tất cả →
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-borderless table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ngày giờ</th>
                        <th>Tên máy</th>
                        <th>Staff</th>
                        <th>IMEI</th>
                        <th>Giá AI đề xuất</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="recent-tbody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Đang tải dữ liệu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Thông báo lỗi (ẩn mặc định) -->
<div id="dashboard-error" class="alert alert-danger mt-3 d-none" role="alert">
    ⚠️ Không thể tải dữ liệu bảng điều khiển. Vui lòng tải lại trang.
</div>

<style>
    /* Attention list items */
    .attention-item {
        background: rgba(255, 193, 7, 0.04);
        border: 1px solid rgba(255, 193, 7, 0.12);
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 8px;
        transition: background 0.15s;
    }
    .attention-item:hover { background: rgba(255, 193, 7, 0.08); }
    .attention-item:last-child { margin-bottom: 0; }
    .attention-item .device-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: #d4e5f7;
        margin-bottom: 4px;
    }
    .attention-item .device-imei {
        font-size: 0.72rem;
        color: #6c8a9e;
        font-family: 'Courier New', monospace;
    }

    /* Legend items */
    .legend-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 10px;
    }
    .legend-dot {
        width: 12px; height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .legend-label { font-size: 0.85rem; color: #8fa8be; }
    .legend-value { font-size: 1.3rem; font-weight: 800; }
    .legend-pct { font-size: 0.75rem; color: #576a7e; }

    /* Recent table badge overrides */
    #recent-tbody code {
        font-size: 0.78rem;
        color: #0dcaf0;
        background: rgba(13,202,240,0.08);
        padding: 2px 6px;
        border-radius: 4px;
    }
</style>

<?php
renderFooter(['../assets/admin_app.js']);
?>

<script>
/*
 * Patch cho admin_app.js: Override phần renderAttentionList và loadDashboardMetrics
 * để phù hợp với layout mới (full-width chart + transaction riêng).
 *
 * Các id DOM đã khớp với admin_app.js gốc:
 *   #card-total-staff, #card-in-stock, #card-sold, #card-total-spent
 *   #stock-status-chart, #stock-status-legend, #badge-total-gadgets
 *   #attention-list, #recent-tbody, #dashboard-error
 *
 * Bảng recent-tbody mới có 6 cột (thêm cột "Giá AI đề xuất"),
 * nên patch lại hàm render recent để thêm cột giá.
 */
(function patchDashboard() {
    // Chờ admin_app.js load xong, sau đó patch hàm render attention + recent
    // bằng cách override trực tiếp vào DOM sau khi loadDashboardMetrics chạy.
    const _origFetch = window.fetch;

    // Override renderAttentionList để dùng design mới (attention-item div)
    window.__patchAttentionList = function (el, data) {
        if (!el) return;
        if (!Array.isArray(data) || data.length === 0) {
            el.innerHTML = `<div class="text-center text-muted small py-3">🎉 Không có thiết bị nào cần lưu ý.</div>`;
            return;
        }

        const ATTENTION_REASON_BADGES = {
            'battery':   'bg-danger text-white',
            'stock_age': 'bg-warning text-dark',
            'damage':    'bg-secondary text-white',
        };

        const GADGET_STATUS_BADGES = {
            'Stored':       '<span class="badge bg-primary bg-opacity-25 text-info border border-info border-opacity-25">📦 Lưu kho</span>',
            'Refurbishing': '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">🔧 Tân trang</span>',
            'Sold':         '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">✅ Đã bán</span>',
        };

        el.innerHTML = data.map(item => {
            const deviceName = ((item.brand_name || '') + ' ' + (item.model_name || '')).trim() || '—';
            const statusBadge = GADGET_STATUS_BADGES[item.status] || item.status || '—';
            const imei = item.imei || '—';
            const reasons = Array.isArray(item.reasons) ? item.reasons : [];
            const reasonBadges = reasons.length > 0
                ? reasons.map(r => {
                    const cls = ATTENTION_REASON_BADGES[r.type] || 'bg-secondary text-white';
                    return `<span class="badge ${cls} me-1">${r.label}</span>`;
                }).join('')
                : '<span class="badge bg-dark text-muted">Tình trạng tốt</span>';

            return `
                <div class="attention-item">
                    <div class="device-name">${deviceName}</div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="device-imei">${imei}</span>
                        ${statusBadge}
                    </div>
                    <div>${reasonBadges}</div>
                </div>`;
        }).join('');
    };

    // Override renderStockStatusLegend để dùng legend-item card style
    window.__patchLegend = function (el, segments, total) {
        if (!el) return;
        if (total === 0) {
            el.innerHTML = '<div class="text-center text-muted small">Chưa có thiết bị nào.</div>';
            return;
        }
        el.innerHTML = segments.map(seg => {
            const pct = total > 0 ? ((seg.value / total) * 100).toFixed(1) : '0.0';
            return `
                <div class="legend-item">
                    <span class="legend-dot" style="background:${seg.color}; box-shadow: 0 0 6px ${seg.color}55;"></span>
                    <div class="flex-grow-1">
                        <div class="legend-label">${seg.label}</div>
                        <div class="legend-pct">${pct}% tổng kho</div>
                    </div>
                    <div class="text-end">
                        <div class="legend-value" style="color:${seg.color};">${seg.value}</div>
                    </div>
                </div>`;
        }).join('');
    };

    // Patch recent tbody để thêm cột giá AI
    window.__patchRecentTbody = function (elRecent, recent) {
        if (!elRecent) return;

        const SESSION_STATUS_BADGES = {
            'Pending':   '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">⏳ Đang chờ</span>',
            'Purchased': '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">✅ Đã thu mua</span>',
            'Declined':  '<span class="badge bg-danger  bg-opacity-25 text-danger  border border-danger  border-opacity-25">❌ Từ chối</span>',
        };

        function fmtVnd(v) {
            return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(v ?? 0);
        }

        if (!Array.isArray(recent) || recent.length === 0) {
            elRecent.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Chưa có giao dịch nào.</td></tr>';
            return;
        }

        elRecent.innerHTML = recent.map(r => {
            const deviceName = ((r.brand_name || '') + ' ' + (r.model_name || '')).trim() || '—';
            const imei = r.imei
                ? `<code>${r.imei}</code>`
                : '<span class="text-muted fst-italic">Chưa nhập</span>';
            const createdAt = r.created_at
                ? new Date(r.created_at).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
                : '—';
            const sBadge = SESSION_STATUS_BADGES[r.final_status]
                || `<span class="badge bg-secondary">${r.final_status || '—'}</span>`;
            const aiPrice = r.ai_suggested_price
                ? `<span class="fw-semibold text-info">${fmtVnd(r.ai_suggested_price)}</span>`
                : '<span class="text-muted">—</span>';

            return `
                <tr>
                    <td class="text-muted small">${createdAt}</td>
                    <td class="fw-semibold">${deviceName}</td>
                    <td class="small">${r.staff_name || '—'}</td>
                    <td>${imei}</td>
                    <td>${aiPrice}</td>
                    <td>${sBadge}</td>
                </tr>`;
        }).join('');
    };

    // Monkey-patch: sau khi DOM load xong, hook vào kết quả của loadDashboardMetrics
    // bằng cách observe sự thay đổi của #recent-tbody và #attention-list
    document.addEventListener('DOMContentLoaded', function () {
        const elLegend    = document.getElementById('stock-status-legend');
        const elAttention = document.getElementById('attention-list');
        const elRecent    = document.getElementById('recent-tbody');

        // Dùng MutationObserver để detect khi admin_app.js đổ dữ liệu vào,
        // sau đó ta override lại bằng style mới.
        // Tuy nhiên, cách đơn giản hơn: delay nhỏ rồi fetch lại data.

        // Fetch data trực tiếp (song song với admin_app.js, dùng chung API)
        fetch('../api/admin_dashboard_api.php?action=get_metrics')
            .then(r => r.json())
            .then(res => {
                if (!res.ok || !res.data) return;

                const { stock_status, attention_devices, recent } = res.data;

                // Override legend
                const STOCK_STATUS_COLORS = {
                    'Stored': '#0d6efd',
                    'Refurbishing': '#ffc107',
                    'Sold': '#198754',
                };
                const STOCK_STATUS_LABELS_VI = {
                    'Stored': '📦 Đang lưu kho',
                    'Refurbishing': '🔧 Đang tân trang',
                    'Sold': '✅ Đã bán',
                };

                const statusList = Array.isArray(stock_status) ? stock_status : [];
                const segments = statusList.map(s => ({
                    label: STOCK_STATUS_LABELS_VI[s.status] || s.status,
                    value: parseInt(s.quantity, 10) || 0,
                    color: STOCK_STATUS_COLORS[s.status] || '#6c757d',
                }));
                const total = segments.reduce((sum, s) => sum + s.value, 0);

                // Small delay để admin_app.js chạy trước, rồi ta override
                setTimeout(() => {
                    if (elLegend) window.__patchLegend(elLegend, segments, total);
                    if (elAttention) window.__patchAttentionList(elAttention, attention_devices);
                    if (elRecent) window.__patchRecentTbody(elRecent, recent);

                    const elBadge = document.getElementById('badge-total-gadgets');
                    if (elBadge) elBadge.textContent = total + ' thiết bị';
                }, 600);
            })
            .catch(() => {});
    });
})();
</script>