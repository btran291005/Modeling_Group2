<?php
// ============================================================
// FILE: staff/history.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ✅ Chữ hoa 'Staff' – khớp với giá trị lưu trong DB và session
requireRole('Staff');

renderHeader('Lịch sử Định giá cá nhân');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-secondary fw-bold mb-1"><i class="bi bi-clock-history"></i> Lịch sử Định giá</h2>
            <p class="text-muted mb-0">Nhật ký các thiết bị bạn đã định giá, kèm kết quả AI đề xuất và giá chốt thu mua.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Quay lại</a>
    </div>

    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-3">
            <input type="text" id="history-search" class="form-control form-control-lg border-2" placeholder="🔍 Tìm theo tên máy hoặc ngày (VD: iPhone, 2026-06)...">
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-secondary text-white fw-bold border-0 rounded-top">
            <i class="bi bi-journal-text"></i> Nhật ký định giá của tôi
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ngày giờ</th>
                            <th class="text-start">Thiết bị (Hãng / Dòng máy)</th>
                            <th>RAM/ROM</th>
                            <th>Pin (%)</th>
                            <th>Trầy xước / Lỗi</th>
                            <th>Giá AI đề xuất</th>
                            <th>Giá chốt thu mua</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div> Đang tải dữ liệu...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/staff_app.js']);
?>