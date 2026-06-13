<?php
// ============================================================
// FILE: staff/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ✅ Chữ hoa 'Staff' — khớp với giá trị lưu trong DB và session
requireRole('Staff');

$fullName = $_SESSION['user']['full_name'] ?? 'Staff';

renderHeader('Staff Dashboard');
?>

<h2 class="mb-1">Chào, <?= htmlspecialchars($fullName) ?> 👋</h2>
<p class="text-muted mb-4">Đây là tổng quan công việc của bạn hôm nay.</p>

<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="card text-bg-primary h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Tổng phiên của tôi</div>
                <div class="fs-2 fw-bold">24</div>
                <div class="small opacity-75">Từ khi bắt đầu làm việc</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-warning h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Phiên hôm nay</div>
                <div class="fs-2 fw-bold">3</div>
                <div class="small opacity-75">Đã định giá hôm nay</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-success h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Đã thu mua</div>
                <div class="fs-2 fw-bold">18</div>
                <div class="small opacity-75">Thiết bị đã chốt mua</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-danger h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Từ chối</div>
                <div class="fs-2 fw-bold">6</div>
                <div class="small opacity-75">Không đạt yêu cầu</div>
            </div>
        </div>
    </div>

</div>

<div class="card">
    <div class="card-body text-center py-5">
        <h4 class="mb-2">⚡ Bắt đầu định giá mới</h4>
        <p class="text-muted mb-4">Nhập thông tin thiết bị, để AI tự động tính giá thu mua tối ưu.</p>
        <a href="valuation.php" class="btn btn-primary btn-lg">
            Tạo phiên định giá
        </a>
    </div>
</div>

<?php
renderFooter();