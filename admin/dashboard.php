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

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.8rem;color:#e6f0fa;letter-spacing:-.3px;">
            📊 Bảng điều khiển Tổng quan
        </h2>
        <p class="mb-0" style="color:#8fa8be;font-size:1rem;">
            Toàn cảnh hoạt động hệ thống &mdash; dữ liệu cập nhật theo thời gian thực.
        </p>
    </div>
    <a href="history.php" class="btn btn-sm btn-outline-info d-flex align-items-center gap-2 mt-1" style="font-size:.9rem;padding:6px 12px;">
        🕘 Xem nhật ký
    </a>
</div>

<div class="row g-3 mb-4">

    <div class="col-xl-3 col-md-6">
        <div class="rounded-3 p-4 h-100 position-relative overflow-hidden"
             style="background:linear-gradient(135deg,#0d3060 0%,#061830 100%);border:1px solid rgba(13,202,240,.18);">
            <div style="position:absolute;top:-18px;right:-18px;font-size:4.5rem;opacity:.08;line-height:1;">👥</div>
            <div class="text-uppercase fw-bold mb-2" style="font-size:.85rem;letter-spacing:1.2px;color:rgba(13,202,240,.9);">
                Tổng nhân sự
            </div>
            <div class="fw-bold mb-1" style="font-size:1.8rem;color:#0dcaf0;line-height:1;" id="card-total-staff">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div style="font-size:.85rem;color:#8fa8be;">Admin + Staff đang hoạt động</div>
            <div class="mt-2">
                <span class="badge rounded-pill" style="background:rgba(13,202,240,.12);color:#0dcaf0;font-size:.85rem;padding:4px 10px;">
                    🟢 Hệ thống hoạt động
                </span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="rounded-3 p-4 h-100 position-relative overflow-hidden"
             style="background:linear-gradient(135deg,#0a3040 0%,#051820 100%);border:1px solid rgba(13,202,240,.12);">
            <div style="position:absolute;top:-18px;right:-18px;font-size:4.5rem;opacity:.08;line-height:1;">📦</div>
            <div class="text-uppercase fw-bold mb-2" style="font-size:.85rem;letter-spacing:1.2px;color:rgba(13,202,240,.9);">
                Máy tồn kho
            </div>
            <div class="fw-bold mb-1" style="font-size:1.8rem;color:#4dd9ff;line-height:1;" id="card-in-stock">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div style="font-size:.85rem;color:#8fa8be;">Stored + Refurbishing</div>
            <div class="mt-2">
                <a href="inventory.php" style="font-size:.85rem;color:#0dcaf0;text-decoration:none;font-weight:600;">
                    Xem kho → 
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="rounded-3 p-4 h-100 position-relative overflow-hidden"
             style="background:linear-gradient(135deg,#083028 0%,#041814 100%);border:1px solid rgba(32,201,151,.18);">
            <div style="position:absolute;top:-18px;right:-18px;font-size:4.5rem;opacity:.08;line-height:1;">✅</div>
            <div class="text-uppercase fw-bold mb-2" style="font-size:.85rem;letter-spacing:1.2px;color:rgba(32,201,151,.9);">
                Đã bán
            </div>
            <div class="fw-bold mb-1" style="font-size:1.8rem;color:#20c997;line-height:1;" id="card-sold">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div style="font-size:.85rem;color:#8fa8be;">Tổng thiết bị đã xuất kho</div>
            <div class="mt-2">
                <span class="badge rounded-pill" style="background:rgba(32,201,151,.1);color:#20c997;font-size:.85rem;padding:4px 10px;">
                    📈 Thiết bị đã bán ra
                </span>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="rounded-3 p-4 h-100 position-relative overflow-hidden"
             style="background:linear-gradient(135deg,#2e2400 0%,#181200 100%);border:1px solid rgba(255,193,7,.18);">
            <div style="position:absolute;top:-18px;right:-18px;font-size:4.5rem;opacity:.08;line-height:1;">💰</div>
            <div class="text-uppercase fw-bold mb-2" style="font-size:.85rem;letter-spacing:1.2px;color:rgba(255,193,7,.9);">
                Tổng vốn đã chi
            </div>
            <div class="fw-bold mb-1" style="font-size:1.8rem;color:#ffc107;line-height:1.2;" id="card-total-spent">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div style="font-size:.85rem;color:#8fa8be;">Tổng giá AI đề xuất (đã mua)</div>
            <div class="mt-2">
                <span class="badge rounded-pill" style="background:rgba(255,193,7,.1);color:#ffc107;font-size:.85rem;padding:4px 10px;">
                    💵 Tổng vốn đầu tư
                </span>
            </div>
        </div>
    </div>

</div>

<div class="row g-3 mb-4">

    <div class="col-xl-8">
        <div class="rounded-3 h-100" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">

            <div class="d-flex align-items-center justify-content-between px-4 py-3"
                 style="border-bottom:1px solid rgba(255,255,255,.06);">
                <div class="d-flex align-items-center gap-2">
                    <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">📊 Trạng thái Kho hàng</span>
                    <span class="badge rounded-pill" id="badge-total-gadgets"
                          style="background:rgba(13,202,240,.1);color:#0dcaf0;border:1px solid rgba(13,202,240,.2);font-size:.9rem;padding:5px 12px;">
                        — thiết bị
                    </span>
                </div>
                <a href="inventory.php" class="btn btn-sm btn-outline-secondary" style="font-size:.9rem;padding:6px 12px;color:#c8d8ea;">
                    Kho tổng →
                </a>
            </div>

            <div class="p-4">
                <div class="row g-4 align-items-center">

                    <div class="col-md-4 d-flex justify-content-center">
                        <canvas id="stock-status-chart" width="200" height="200"
                                style="max-width:200px;max-height:200px;"></canvas>
                    </div>

                    <div class="col-md-4">
                        <div id="stock-status-legend" class="d-flex flex-column gap-2">
                            <div class="text-center text-muted py-3" style="font-size:1rem;color:#8fa8be !important;">
                                <span class="spinner-border spinner-border-sm me-1"></span> Đang tải...
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="rounded-3 p-3 text-center"
                                 style="background:rgba(13,202,240,.05);border:1px solid rgba(13,202,240,.12);">
                                <div style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:1.2px;color:#0dcaf0;">
                                    Tổng thiết bị
                                </div>
                                <div class="fw-bold" id="quick-total" style="font-size:1.8rem;color:#0dcaf0;">—</div>
                            </div>
                            <div class="rounded-3 p-3 text-center"
                                 style="background:rgba(255,193,7,.04);border:1px solid rgba(255,193,7,.1);">
                                <div style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:1.2px;color:#ffc107;">
                                    Đang xử lý
                                </div>
                                <div class="fw-bold" id="quick-refurb" style="font-size:1.8rem;color:#ffc107;">—</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="rounded-3 h-100" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,193,7,.12);">

            <div class="px-4 py-3 d-flex align-items-center gap-2"
                 style="border-bottom:1px solid rgba(255,255,255,.06);">
                <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">⚠️ Cần lưu ý</span>
                <span class="badge rounded-pill"
                      style="background:rgba(255,193,7,.1);color:#ffc107;border:1px solid rgba(255,193,7,.2);font-size:.9rem;padding:5px 12px;">
                    Ưu tiên cao
                </span>
            </div>

            <div id="attention-list" class="p-3" style="max-height:300px;overflow-y:auto;">
                <div class="text-center text-muted py-4" style="font-size:1rem;color:#8fa8be !important;">
                    <span class="spinner-border spinner-border-sm me-1"></span> Đang tải...
                </div>
            </div>

        </div>
    </div>

</div>

<div class="rounded-3 mb-4" style="background:rgba(10,16,28,.9);border:1px solid rgba(255,255,255,.07);">

    <div class="d-flex align-items-center justify-content-between px-4 py-3"
         style="border-bottom:1px solid rgba(255,255,255,.06);">
        <span style="font-size:1rem;font-weight:700;color:#e6f0fa;">🕘 Giao dịch mới nhất</span>
        <a href="history.php" class="btn btn-sm btn-outline-info" style="font-size:.9rem;padding:6px 12px;">
            Xem tất cả →
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-borderless table-hover align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <th class="px-4 py-3" style="font-size:.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">Ngày giờ</th>
                    <th class="py-3"      style="font-size:.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">Tên máy</th>
                    <th class="py-3"      style="font-size:.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">Nhân viên</th>
                    <th class="py-3"      style="font-size:.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">IMEI</th>
                    <th class="py-3"      style="font-size:.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">Giá AI đề xuất</th>
                    <th class="py-3 pe-4" style="font-size:.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(13,202,240,.9);">Trạng thái</th>
                </tr>
            </thead>
            <tbody id="recent-tbody">
                <tr>
                    <td colspan="6" class="text-center py-5 px-4" style="font-size:1rem;color:#8fa8be;">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        Đang tải dữ liệu...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<div id="dashboard-error" class="alert alert-danger d-none mt-3 rounded-3" role="alert" style="font-size:1rem;">
    ⚠️ Không thể tải dữ liệu bảng điều khiển. Vui lòng tải lại trang.
</div>

<style>
/* Attention items */
.att-item {
    background: rgba(255,193,7,.04);
    border: 1px solid rgba(255,193,7,.1);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
}
.att-item:hover { background: rgba(255,193,7,.07); }
.att-item:last-child { margin-bottom: 0; }
.att-name { font-size:1rem;font-weight:600;color:#e6f0fa;margin-bottom:4px; }
.att-imei { font-size:.95rem;color:#8fa8be;font-family:'Courier New',monospace; }

/* Legend cards */
.legend-card {
    display:flex;align-items:center;gap:10px;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.06);
    border-radius:10px;
    padding:11px 14px;
}
.legend-dot {
    width:10px;height:10px;border-radius:50%;flex-shrink:0;
}
.legend-lbl { font-size:1rem;color:#c8d8ea;font-weight:600; }
.legend-val { font-size:1.8rem;font-weight:800; }
.legend-pct { font-size:.85rem;color:#8fa8be; }

/* Recent table */
#recent-tbody td { 
    font-size: 1rem; 
    color: #c8d8ea; /* Tăng độ sáng text so với #7a90a8 */
    padding-top: 16px !important;
    padding-bottom: 16px !important;
    border-bottom: 1px solid rgba(255,255,255,.04) !important;
}
#recent-tbody td:first-child { padding-left:1.5rem !important; }
#recent-tbody td:last-child  { padding-right:1.5rem !important; }
#recent-tbody tr:last-child td { border-bottom: none !important; }
#recent-tbody tr:hover td { background: rgba(13,202,240,.03) !important; }
#recent-tbody code {
    font-size:.95rem;color:#0dcaf0;
    background:rgba(13,202,240,.15); /* Làm nền code block rõ hơn chút */
    padding:4px 8px;border-radius:5px;
}
</style>

<?php
renderFooter(['../assets/admin_app.js']);
?>

<script>
/*
 * Dashboard patch — overrides admin_app.js renderers
 * with the new DOM ids & styles used in this template.
 *
 * DOM ids are identical to admin_app.js expectations:
 * #card-total-staff, #card-in-stock, #card-sold, #card-total-spent
 * #stock-status-chart, #stock-status-legend, #badge-total-gadgets
 * #attention-list, #recent-tbody, #dashboard-error
 *
 * Extra ids added here: #quick-total, #quick-refurb
 */
(function patchDashboard() {

    const STOCK_COLORS = {
        Stored:       '#0dcaf0',
        Refurbishing: '#ffc107',
        Sold:         '#20c997',
    };
    const STOCK_LABELS = {
        Stored:       '📦 Đang lưu kho',
        Refurbishing: '🔧 Tân trang',
        Sold:         '✅ Đã bán',
    };
    const SESSION_BADGES = {
        Pending:   '<span class="badge rounded-pill" style="background:rgba(255,193,7,.15);color:#ffc107;border:1px solid rgba(255,193,7,.3);font-size:.9rem;padding:5px 12px;">⏳ Đang chờ</span>',
        Purchased: '<span class="badge rounded-pill" style="background:rgba(32,201,151,.15);color:#20c997;border:1px solid rgba(32,201,151,.3);font-size:.9rem;padding:5px 12px;">✅ Đã thu mua</span>',
        Declined:  '<span class="badge rounded-pill" style="background:rgba(220,53,69,.15);color:#f08080;border:1px solid rgba(220,53,69,.3);font-size:.9rem;padding:5px 12px;">❌ Từ chối</span>',
    };
    const GADGET_BADGES = {
        Stored:       '<span class="badge rounded-pill" style="background:rgba(13,202,240,.15);color:#0dcaf0;font-size:.85rem;padding:4px 10px;">📦 Lưu kho</span>',
        Refurbishing: '<span class="badge rounded-pill" style="background:rgba(255,193,7,.15);color:#ffc107;font-size:.85rem;padding:4px 10px;">🔧 Tân trang</span>',
        Sold:         '<span class="badge rounded-pill" style="background:rgba(32,201,151,.15);color:#20c997;font-size:.85rem;padding:4px 10px;">✅ Đã bán</span>',
    };
    const REASON_CLS = {
        battery:   'style="background:rgba(220,53,69,.15);color:#f08080;font-size:.85rem;padding:4px 10px;"',
        stock_age: 'style="background:rgba(255,193,7,.15);color:#ffc107;font-size:.85rem;padding:4px 10px;"',
        damage:    'style="background:rgba(108,117,125,.2);color:#c8d8ea;font-size:.85rem;padding:4px 10px;"',
    };

    function fmtVnd(v) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(v ?? 0);
    }

    /* Attention list */
    window.__patchAttentionList = function (el, data) {
        if (!el) return;
        if (!Array.isArray(data) || data.length === 0) {
            el.innerHTML = '<div class="text-center py-3" style="color:#8fa8be;font-size:1rem;">🎉 Không có thiết bị cần lưu ý.</div>';
            return;
        }
        el.innerHTML = data.map(item => {
            const name    = ((item.brand_name || '') + ' ' + (item.model_name || '')).trim() || '—';
            const badge   = GADGET_BADGES[item.status] || item.status;
            const imei    = item.imei || '—';
            const reasons = Array.isArray(item.reasons) ? item.reasons : [];
            const rbadges = reasons.length
                ? reasons.map(r => `<span class="badge rounded-pill me-1" ${REASON_CLS[r.type] || ''}>${r.label}</span>`).join('')
                : '<span class="badge rounded-pill" style="background:rgba(255,255,255,.1);color:#8fa8be;font-size:.85rem;padding:4px 10px;">Tình trạng tốt</span>';
            return `
                <div class="att-item">
                    <div class="att-name">${name}</div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="att-imei">${imei}</span>${badge}
                    </div>
                    <div>${rbadges}</div>
                </div>`;
        }).join('');
    };

    /* Legend */
    window.__patchLegend = function (el, segs, total) {
        if (!el) return;
        if (total === 0) {
            el.innerHTML = '<div class="text-center py-2" style="color:#8fa8be;font-size:1rem;">Chưa có thiết bị nào.</div>';
            return;
        }
        el.innerHTML = segs.map(s => {
            const pct = total > 0 ? ((s.value / total) * 100).toFixed(1) : '0.0';
            return `
                <div class="legend-card">
                    <span class="legend-dot" style="background:${s.color};box-shadow:0 0 6px ${s.color}66;"></span>
                    <div class="flex-grow-1">
                        <div class="legend-lbl">${s.label}</div>
                        <div class="legend-pct">${pct}% tổng kho</div>
                    </div>
                    <div class="text-end">
                        <div class="legend-val" style="color:${s.color};">${s.value}</div>
                    </div>
                </div>`;
        }).join('');
    };

    /* Recent tbody */
    window.__patchRecentTbody = function (el, recent) {
        if (!el) return;
        if (!Array.isArray(recent) || recent.length === 0) {
            el.innerHTML = '<tr><td colspan="6" class="text-center py-5" style="color:#8fa8be;font-size:1rem;">Chưa có giao dịch nào.</td></tr>';
            return;
        }
        el.innerHTML = recent.map(r => {
            const name  = ((r.brand_name || '') + ' ' + (r.model_name || '')).trim() || '—';
            const imei  = r.imei ? `<code>${r.imei}</code>` : '<span style="color:#8fa8be;font-style:italic;">Chưa nhập</span>';
            const date  = r.created_at ? new Date(r.created_at).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '—';
            const badge = SESSION_BADGES[r.final_status] || `<span class="badge bg-secondary" style="font-size:.9rem;padding:5px 12px;">${r.final_status || '—'}</span>`;
            const price = r.ai_suggested_price
                ? `<span class="fw-bold" style="color:#0dcaf0;">${fmtVnd(r.ai_suggested_price)}</span>`
                : '<span style="color:#8fa8be;">—</span>';
            return `
                <tr>
                    <td class="px-4">${date}</td>
                    <td class="fw-semibold" style="color:#e6f0fa;">${name}</td>
                    <td>${r.staff_name || '—'}</td>
                    <td>${imei}</td>
                    <td>${price}</td>
                    <td class="pe-4">${badge}</td>
                </tr>`;
        }).join('');
    };

    /* Fetch & override */
    document.addEventListener('DOMContentLoaded', () => {
        fetch('../api/admin_dashboard_api.php?action=get_metrics')
            .then(r => r.json())
            .then(res => {
                if (!res.ok || !res.data) return;
                const { stock_status, attention_devices, recent } = res.data;

                const statusList = Array.isArray(stock_status) ? stock_status : [];
                const segs = statusList.map(s => ({
                    label: STOCK_LABELS[s.status] || s.status,
                    value: parseInt(s.quantity, 10) || 0,
                    color: STOCK_COLORS[s.status] || '#6c757d',
                }));
                const total    = segs.reduce((a, s) => a + s.value, 0);
                const refurb   = segs.find(s => s.label.includes('Tân'))?.value ?? 0;

                const elQuickTotal = document.getElementById('quick-total');
                const elQuickRef   = document.getElementById('quick-refurb');
                if (elQuickTotal) elQuickTotal.textContent = total;
                if (elQuickRef)   elQuickRef.textContent   = refurb;

                setTimeout(() => {
                    window.__patchLegend(document.getElementById('stock-status-legend'), segs, total);
                    window.__patchAttentionList(document.getElementById('attention-list'), attention_devices);
                    window.__patchRecentTbody(document.getElementById('recent-tbody'), recent);

                    const badge = document.getElementById('badge-total-gadgets');
                    if (badge) badge.textContent = total + ' thiết bị';
                }, 500);
            })
            .catch(() => {});
    });
}());
</script>