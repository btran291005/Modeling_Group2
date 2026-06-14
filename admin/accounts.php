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
<p class="text-muted mb-4">Thêm, khoá/mở khoá, đổi mật khẩu và xoá tài khoản trong hệ thống.</p>

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


<!-- ============================================================
     MODAL: XÁC NHẬN XÓA TÀI KHOẢN
     ============================================================ -->
<div class="modal fade" id="modal-delete-account" tabindex="-1" aria-labelledby="modal-delete-account-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modal-delete-account-title">⚠️ Xác nhận xóa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Bạn sắp xóa tài khoản:</p>
                <p class="fw-bold mb-0" id="delete-account-email">—</p>
                <input type="hidden" id="delete-account-id" value="">
                <p class="small text-muted mt-2 mb-0">
                    Hành động này không thể hoàn tác. Nếu tài khoản đã có phiên định giá liên quan,
                    hệ thống sẽ không cho xóa — hãy khoá tài khoản thay vì xóa.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-delete-account">
                    Xóa ngay
                </button>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter(['../assets/admin_app.js']);