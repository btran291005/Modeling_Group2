<?php
// ============================================================
// FILE: admin/accounts.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Admin');

renderHeader('Quản lý Tài khoản');
?>

<h2 class="mb-1">👥 Quản lý Tài khoản Nhân viên</h2>
<p class="text-muted mb-4">Thêm, khoá/mở khoá và đổi mật khẩu cho các tài khoản trong hệ thống.</p>

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create">
        ➕ Thêm Nhân viên
    </button>
</div>

<div class="card">
    <div class="card-header">Danh sách tài khoản</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Họ Tên</th>
                        <th>Email</th>
                        <th>Phân quyền</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="accounts-tbody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Đang tải dữ liệu...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL: THÊM NHÂN VIÊN
     ============================================================ -->
<div class="modal fade" id="modal-create" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-create">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm tài khoản mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Họ và tên</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phân quyền</label>
                        <select name="role" class="form-select">
                            <option value="Staff">Staff</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-primary">Tạo tài khoản</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL: ĐỔI MẬT KHẨU
     ============================================================ -->
<div class="modal fade" id="modal-reset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-reset">
                <div class="modal-header">
                    <h5 class="modal-title">Đổi mật khẩu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="reset-user-id">
                    <p class="text-muted small" id="reset-target-label"></p>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-warning">Đổi mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/js/admin_app.js']);