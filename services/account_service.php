<?php

declare(strict_types=1);

/**
 * services/AccountService.php
 *
 * Business logic cho toàn bộ nghiệp vụ quản lý tài khoản.
 * Nguyên tắc: Chỉ nhận input → query DB → trả Array hoặc ném Exception.
 * KHÔNG echo, KHÔNG header(), KHÔNG $_SESSION (trừ đọc user_id để audit).
 */
class AccountService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    // ═══════════════════════════════════════════════════════════
    // getList – Lấy danh sách tài khoản có lọc + phân trang
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{ users: array, pagination: array }
     */
    public function getList(
        string $search   = '',
        string $role     = '',
        string $status   = '',
        int    $page     = 1,
        int    $perPage  = 10
    ): array {
        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]      = '(full_name LIKE :s OR email LIKE :s)';
            $params[':s'] = "%{$search}%";
        }
        if (in_array($role, ['Admin', 'Staff'], true)) {
            $where[]         = 'role = :role';
            $params[':role'] = $role;
        }
        if (in_array($status, ['Active', 'Locked'], true)) {
            $where[]           = 'status = :status';
            $params[':status'] = $status;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM users {$whereSQL}");
        $stmtCount->execute($params);
        $total      = (int) $stmtCount->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        // Fetch
        $stmtList = $this->pdo->prepare(
            "SELECT user_id, full_name, email, role, status, created_at
             FROM   users
             {$whereSQL}
             ORDER  BY created_at DESC
             LIMIT  :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmtList->bindValue($k, $v);
        }
        $stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmtList->execute();
        $users = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users'      => $users,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'total_pages'  => $totalPages,
            ],
        ];
    }


    // ═══════════════════════════════════════════════════════════
    // getAllAccounts – Lấy toàn bộ danh sách user (không phân trang)
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array<int, array{user_id:int, full_name:string, email:string, role:string, status:string, created_at:string}>
     */
    public function getAllAccounts(): array
    {
        return $this->pdo
            ->query(
                "SELECT user_id, full_name, email, role, status, created_at
                 FROM   users
                 ORDER  BY created_at DESC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    // ═══════════════════════════════════════════════════════════
    // create – Tạo tài khoản mới
    // ═══════════════════════════════════════════════════════════

    /**
     * @return int  user_id vừa tạo
     * @throws InvalidArgumentException  Dữ liệu không hợp lệ
     * @throws RuntimeException          Email trùng
     */
    public function create(
        string $fullName,
        string $email,
        string $password,
        string $role = 'Staff'
    ): int {
        $fullName = trim($fullName);
        $email    = trim($email);
        $password = trim($password);

        $errors = [];
        if (mb_strlen($fullName) < 2)                  $errors[] = 'Họ tên phải có ít nhất 2 ký tự.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
        if (mb_strlen($password) < 6)                  $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        if (!in_array($role, ['Admin', 'Staff'], true)) $errors[] = 'Role không hợp lệ.';
        if ($errors) throw new InvalidArgumentException(implode(' ', $errors));

        // Kiểm tra email trùng
        $dup = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
        $dup->execute([':e' => $email]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new RuntimeException('Email này đã được sử dụng.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role, status)
             VALUES (:n, :e, :h, :r, 'Active')"
        );
        $stmt->execute([
            ':n' => $fullName,
            ':e' => $email,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
            ':r' => $role,
        ]);

        $newId = (int) $this->pdo->lastInsertId();
        $this->_audit("Tạo tài khoản mới: {$email} (Role: {$role})", 'users');

        return $newId;
    }


    // ═══════════════════════════════════════════════════════════
    // update – Cập nhật thông tin tài khoản
    // ═══════════════════════════════════════════════════════════

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException  Email trùng với user khác
     */
    public function update(
        int    $userId,
        string $fullName,
        string $email,
        string $role
    ): void {
        $fullName = trim($fullName);
        $email    = trim($email);

        if ($userId <= 0 || mb_strlen($fullName) < 2
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !in_array($role, ['Admin', 'Staff'], true)) {
            throw new InvalidArgumentException('Dữ liệu không hợp lệ.');
        }

        $dup = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE email = :e AND user_id != :id"
        );
        $dup->execute([':e' => $email, ':id' => $userId]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new RuntimeException('Email đã được dùng bởi tài khoản khác.');
        }

        $this->pdo->prepare(
            "UPDATE users SET full_name = :n, email = :e, role = :r WHERE user_id = :id"
        )->execute([':n' => $fullName, ':e' => $email, ':r' => $role, ':id' => $userId]);

        $this->_audit("Cập nhật tài khoản ID#{$userId}: {$email}", 'users');
    }


    // ═══════════════════════════════════════════════════════════
    // toggleStatus – Khoá / mở khoá tài khoản (đảo trạng thái hiện tại)
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{ new_status: string, email: string }
     * @throws InvalidArgumentException
     * @throws RuntimeException  Tự khoá chính mình hoặc không tồn tại
     */
    public function toggleStatus(int $userId, int $currentUserId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id không hợp lệ.');
        }
        if ($userId === $currentUserId) {
            throw new RuntimeException('Bạn không thể khoá tài khoản của chính mình.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT status, email FROM users WHERE user_id = :id"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('Tài khoản không tồn tại.');
        }

        $newStatus = $user['status'] === 'Active' ? 'Locked' : 'Active';

        $this->pdo->prepare(
            "UPDATE users SET status = :s WHERE user_id = :id"
        )->execute([':s' => $newStatus, ':id' => $userId]);

        $verb = $newStatus === 'Locked' ? 'Khoá' : 'Mở khoá';
        $this->_audit("{$verb} tài khoản: {$user['email']}", 'users');

        return ['new_status' => $newStatus, 'email' => $user['email']];
    }


    // ═══════════════════════════════════════════════════════════
    // updateAccountStatus – Set trạng thái tài khoản theo giá trị chỉ định
    // ═══════════════════════════════════════════════════════════

    /**
     * @param  int    $userId
     * @param  string $status  'Active' | 'Locked'
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException  Không tồn tại / tự khoá chính mình
     */
    public function updateAccountStatus(int $userId, string $status, int $currentUserId = 0): bool
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id không hợp lệ.');
        }
        if (!in_array($status, ['Active', 'Locked'], true)) {
            throw new InvalidArgumentException('Trạng thái không hợp lệ. Chỉ nhận "Active" hoặc "Locked".');
        }
        if ($status === 'Locked' && $userId === $currentUserId) {
            throw new RuntimeException('Bạn không thể khoá tài khoản của chính mình.');
        }

        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('Tài khoản không tồn tại.');
        }

        $ok = $this->pdo->prepare(
            "UPDATE users SET status = :s WHERE user_id = :id"
        )->execute([':s' => $status, ':id' => $userId]);

        if ($ok) {
            $verb = $status === 'Locked' ? 'Khoá' : 'Mở khoá';
            $this->_audit("{$verb} tài khoản: {$user['email']}", 'users');
        }

        return (bool) $ok;
    }


    // ═══════════════════════════════════════════════════════════
    // resetPassword – Reset mật khẩu
    // ═══════════════════════════════════════════════════════════

    /**
     * @return string  Email của user được reset
     * @throws InvalidArgumentException
     * @throws RuntimeException  User không tồn tại
     */
    public function resetPassword(int $userId, string $newPassword): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id không hợp lệ.');
        }
        if (mb_strlen(trim($newPassword)) < 6) {
            throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT email FROM users WHERE user_id = :id"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('Tài khoản không tồn tại.');
        }

        $this->pdo->prepare(
            "UPDATE users SET password_hash = :h WHERE user_id = :id"
        )->execute([
            ':h'  => password_hash(trim($newPassword), PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);

        $this->_audit("Reset mật khẩu cho: {$user['email']}", 'users');

        return $user['email'];
    }


    // ═══════════════════════════════════════════════════════════
    // delete – Xoá tài khoản
    // ═══════════════════════════════════════════════════════════

    /**
     * @return string  Email của user bị xoá
     * @throws InvalidArgumentException
     * @throws RuntimeException  Tự xoá / có phiên định giá liên quan / không tồn tại
     */
    public function delete(int $userId, int $currentUserId): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id không hợp lệ.');
        }
        if ($userId === $currentUserId) {
            throw new RuntimeException('Bạn không thể xoá tài khoản của chính mình.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT email, role FROM users WHERE user_id = :id"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('Tài khoản không tồn tại.');
        }

        // Kiểm tra ràng buộc phiên định giá
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM valuation_sessions WHERE user_id = :id"
        );
        $countStmt->execute([':id' => $userId]);
        $count = (int) $countStmt->fetchColumn();

        if ($count > 0) {
            throw new RuntimeException(
                "Không thể xoá: tài khoản có {$count} phiên định giá liên quan. Hãy khoá thay vì xoá."
            );
        }

        // Xoá các bản ghi phụ thuộc trước
        $this->pdo->prepare(
            "DELETE FROM audit_logs WHERE user_id = :id"
        )->execute([':id' => $userId]);

        $this->pdo->prepare(
            "DELETE FROM notifications WHERE user_id = :id"
        )->execute([':id' => $userId]);

        $this->pdo->prepare(
            "DELETE FROM users WHERE user_id = :id"
        )->execute([':id' => $userId]);

        $this->_audit("Xoá tài khoản: {$user['email']} (Role: {$user['role']})", 'users');

        return $user['email'];
    }


    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPER
    // ═══════════════════════════════════════════════════════════

    private function _audit(string $action, string $table): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, target_table)
                 VALUES (:u, :a, :t)"
            )->execute([
                ':u' => $_SESSION['user']['user_id'] ?? 0,
                ':a' => $action,
                ':t' => $table,
            ]);
        } catch (Throwable $e) {}
    }
}