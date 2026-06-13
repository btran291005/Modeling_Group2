<?php
// ============================================================
// FILE: admin/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('admin');

renderHeader('Admin Dashboard');
?>

<h2 class="mb-4">Tổng quan hệ thống</h2>

<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="card text-bg-primary h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Tổng phiên định giá</div>
                <div class="fs-2 fw-bold">128</div>
                <div class="small opacity-75">Tất cả thời gian</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-success h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Thu mua hôm nay</div>
                <div class="fs-2 fw-bold">7</div>
                <div class="small opacity-75">Thiết bị thu mua</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-info h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Thiết bị trong kho</div>
                <div class="fs-2 fw-bold">42</div>
                <div class="small opacity-75">Đang lưu kho</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-warning h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75">Staff hoạt động</div>
                <div class="fs-2 fw-bold">9</div>
                <div class="small opacity-75">Nhân viên đang hoạt động</div>
            </div>
        </div>
    </div>

</div>

<div class="row g-3">

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Hiệu suất nhân viên (Top 5)</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span>Nguyen Van Staff</span>
                    <span class="badge bg-success">12 phiên</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Tran Thi Lan</span>
                    <span class="badge bg-success">9 phiên</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Le Van Duc</span>
                    <span class="badge bg-success">8 phiên</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Pham Thi Hoa</span>
                    <span class="badge bg-success">6 phiên</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Hoang Minh Tuan</span>
                    <span class="badge bg-success">5 phiên</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Hoạt động hệ thống gần đây</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <strong>Admin</strong> đã thêm tài khoản mới — <span class="text-muted">5 phút trước</span>
                </li>
                <li class="list-group-item">
                    <strong>Nguyen Van Staff</strong> chốt thu mua phiên #128 — <span class="text-muted">20 phút trước</span>
                </li>
                <li class="list-group-item">
                    <strong>Admin</strong> cập nhật quy tắc định giá AI — <span class="text-muted">1 giờ trước</span>
                </li>
                <li class="list-group-item">
                    <strong>Tran Thi Lan</strong> tạo phiên định giá mới — <span class="text-muted">2 giờ trước</span>
                </li>
            </ul>
        </div>
    </div>

</div>

<?php
renderFooter();