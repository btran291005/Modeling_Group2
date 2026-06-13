<?php
// ============================================================
// FILE: staff/history.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ✅ Chữ hoa 'Staff' — khớp với giá trị lưu trong DB và session
requireRole('Staff');

renderHeader('Lịch sử Định giá cá nhân');
?>

<h2 class="mb-1">🕘 Lịch sử Định giá</h2>
<p class="text-muted mb-4">Nhật ký các thiết bị bạn đã định giá, kèm kết quả AI đề xuất và giá chốt thu mua.</p>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" id="history-search" class="form-control"
                       placeholder="Tìm theo tên máy hoặc ngày (VD: iPhone, 2025-06)...">
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Nhật ký định giá của tôi</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="history-table">
                <thead class="table-light">
                    <tr>
                        <th>Ngày giờ</th>
                        <th>Thiết bị (Hãng / Dòng máy)</th>
                        <th>RAM/ROM</th>
                        <th>Pin (%)</th>
                        <th>Trầy xước / Quy tắc</th>
                        <th>Giá AI đề xuất</th>
                        <th>Giá chốt thu mua</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Đang tải dữ liệu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
renderFooter();
?>
<script src="../assets/js/staff_app.js"></script>