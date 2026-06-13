<?php

declare(strict_types=1);

/**
 * services/AuthService.php
 *
 * Business logic cho xác thực người dùng.
 * Nguyên tắc: Chỉ nhận input → query DB → trả Array hoặc ném Exception.
 * KHÔNG echo, KHÔNG header(), KHÔNG $_SESSION.
 */
class AuthService
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Xác thực đăng nhập bằng email + password.
     *
     * @param  string $email
     * @param  string $password  Mật khẩu thô (chưa hash)
     *
     * @return array{
     *   user_id:   int,
     *   full_name: string,
     *   email:     string,
     *   role:      string,
     *   status:    string
     * }
     *
     * @throws InvalidArgumentException  Input rỗng hoặc sai định dạng.
     * @throws RuntimeException          Email không tồn tại, sai mật khẩu, hoặc tài khoản bị khoá.
     */
    public function login(string $email, string $password): array
    {
        // ── 1. Validate input ────────────────────────────────
        $email    = trim($email);
        $password = trim($password);

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Vui lòng nhập đầy đủ Email và Mật khẩu.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Địa chỉ Email không hợp lệ.');
        }

        // ── 2. Tìm user theo email ───────────────────────────
        $stmt = $this->pdo->prepare(
            "SELECT user_id, full_name, email, password_hash, role, status
             FROM   users
             WHERE  email = :email
             LIMIT  1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ── 3. Kiểm tra mật khẩu (timing-safe) ──────────────
        // Dùng password_verify() dù user không tồn tại để tránh timing attack
        $hash = $user['password_hash'] ?? '$2y$10$invalidhashpadding000000000000000000000000000000000000';
        if (!$user || !password_verify($password, $hash)) {
            throw new RuntimeException('Email hoặc mật khẩu không chính xác.');
        }

        // ── 4. Kiểm tra trạng thái tài khoản ────────────────
        if ($user['status'] === 'Locked') {
            throw new RuntimeException('Tài khoản của bạn đã bị khoá. Vui lòng liên hệ Admin.');
        }

        // ── 5. Trả về dữ liệu an toàn (bỏ password_hash) ────
        return [
            'user_id'   => (int) $user['user_id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],   // 'Admin' | 'Staff'
            'status'    => $user['status'],
        ];
    }

    /**
     * Ghi audit log sau khi đăng nhập thành công.
     * Tách riêng để API layer kiểm soát việc có ghi hay không.
     *
     * @param int $userId
     */
    public function logLoginSuccess(int $userId): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, target_table)
                 VALUES (:uid, 'Đăng nhập thành công', 'users')"
            )->execute([':uid' => $userId]);
        } catch (Throwable) {
            // Không để lỗi log phá vỡ luồng chính
        }
    }
}