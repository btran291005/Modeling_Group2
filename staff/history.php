<?php
// ============================================================
// FILE: staff/history.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Staff');

renderHeader('Lịch sử Định giá cá nhân');
?>

<div class="mb-4">
    <h2 class="fw-bold mb-1">🕘 Lịch sử Định giá</h2>
    <p class="text-secondary mb-0">Nhật ký các thiết bị bạn đã định giá, kèm kết quả AI đề xuất và giá chốt thu mua.</p>
</div>

<div class="card mb-3 bg-dark-subtle rounded-3 shadow-sm border-secondary border-opacity-25">
    <div class="card-body p-4">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small text-info fw-semibold">🔍 Tìm kiếm</label>
                <input type="text" id="history-search" class="form-control"
                       placeholder="Tìm theo tên máy hoặc ngày (VD: iPhone, 2025-06)...">
            </div>
        </div>
    </div>
</div>

<div class="card bg-dark-subtle rounded-3 shadow-sm border-secondary border-opacity-25">
    <div class="card-header bg-transparent border-secondary border-opacity-25 fw-bold">
        🗂️ Nhật ký định giá của tôi
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-borderless table-hover align-middle mb-0">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25">
                        <th class="px-3 text-info text-uppercase small fw-bold">📅 Ngày giờ</th>
                        <th class="text-info text-uppercase small fw-bold">📱 Thiết bị (Hãng / Dòng máy)</th>
                        <th class="text-info text-uppercase small fw-bold">RAM/ROM</th>
                        <th class="text-info text-uppercase small fw-bold">🔋 Pin (%)</th>
                        <th class="text-info text-uppercase small fw-bold">🔍 Trầy xước / Quy tắc</th>
                        <th class="text-info text-uppercase small fw-bold">🤖 Giá AI đề xuất</th>
                        <th class="text-info text-uppercase small fw-bold">💰 Giá chốt thu mua</th>
                        <th class="px-3 text-info text-uppercase small fw-bold">Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            ⏳ Đang tải dữ liệu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/staff_app.js']);