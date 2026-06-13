<?php

declare(strict_types=1);

/**
 * services/InventoryService.php
 *
 * Business logic cho Quản lý Kho.
 * Dùng đúng bảng `gadgets` theo schema db.sql.
 * Nguyên tắc: Chỉ nhận input, gọi DB, trả array hoặc bool.
 */
class InventoryService
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Danh sách thiết bị trong kho cho Staff xem.
     * JOIN đúng bảng gadgets (PK = imei) theo db.sql.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStaffInventory(): array
    {
        $sql = "
            SELECT
                g.imei,
                g.status,
                g.created_at     AS received_at,
                vs.session_id,
                vs.battery_health,
                vs.ai_suggested_price AS price,
                dm.model_name,
                dm.ram_gb,
                dm.rom_gb,
                b.brand_name,
                c.full_name      AS customer_name,
                c.phone_number
            FROM gadgets g
            JOIN valuation_sessions vs ON g.session_id  = vs.session_id
            JOIN device_models dm      ON vs.model_id   = dm.model_id
            JOIN brands b              ON dm.brand_id   = b.brand_id
            LEFT JOIN customers c      ON vs.customer_id = c.customer_id
            ORDER BY g.created_at DESC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cập nhật trạng thái thiết bị theo IMEI.
     * Staff được phép đổi trạng thái nhưng KHÔNG được xóa.
     *
     * @param  string $imei      IMEI thiết bị (PK của bảng gadgets)
     * @param  string $newStatus Trạng thái mới
     * @return bool   true nếu update thành công (rowCount > 0)
     */
    public function updateStatus(string $imei, string $newStatus): bool
    {
        $allowedStatuses = ['Stored', 'Refurbishing', 'Sold'];

        if (empty($imei) || !in_array($newStatus, $allowedStatuses, true)) {
            return false;
        }

        // Kiểm tra tồn tại
        $check = $this->pdo->prepare(
            "SELECT imei FROM gadgets WHERE imei = :imei LIMIT 1"
        );
        $check->execute([':imei' => $imei]);
        if (!$check->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE gadgets SET status = :status WHERE imei = :imei"
        );
        $stmt->execute([':status' => $newStatus, ':imei' => $imei]);

        return $stmt->rowCount() > 0;
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM ADMIN: Quản lý Kho tổng
    // ═══════════════════════════════════════════════════════════

    /**
     * getAdminInventory — Toàn bộ thiết bị trong kho cho Admin.
     * Kèm hãng, dòng máy, cấu hình, giá chốt mua, khách hàng,
     * và thông tin nhân viên đã thực hiện phiên định giá / nhập kho.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminInventory(): array
    {
        $sql = "
            SELECT
                g.imei,
                g.status,
                g.created_at         AS received_at,
                vs.session_id,
                vs.battery_health,
                vs.ai_suggested_price AS final_price,
                dm.model_id,
                dm.model_name,
                dm.ram_gb,
                dm.rom_gb,
                b.brand_id,
                b.brand_name,
                u.user_id            AS staff_id,
                u.full_name          AS staff_name,
                c.customer_id,
                c.full_name          AS customer_name,
                c.phone_number
            FROM gadgets g
            JOIN valuation_sessions vs ON g.session_id   = vs.session_id
            JOIN device_models dm      ON vs.model_id    = dm.model_id
            JOIN brands b               ON dm.brand_id   = b.brand_id
            JOIN users u                ON vs.user_id    = u.user_id
            LEFT JOIN customers c       ON vs.customer_id = c.customer_id
            ORDER BY g.created_at DESC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * deleteItem — Xóa vĩnh viễn một thiết bị khỏi kho (Admin only).
     * Chỉ xóa bản ghi trong bảng `gadgets`. Không xóa session định giá
     * gốc (giữ lại để tham chiếu lịch sử).
     *
     * @param  string $imei  IMEI thiết bị (PK của bảng gadgets)
     * @return bool   true nếu xóa thành công (rowCount > 0)
     * @throws InvalidArgumentException  IMEI rỗng / không hợp lệ
     * @throws RuntimeException          Thiết bị không tồn tại
     */
    public function deleteItem(string $imei): bool
    {
        $imei = trim($imei);

        if ($imei === '') {
            throw new InvalidArgumentException('IMEI không hợp lệ.');
        }

        $check = $this->pdo->prepare(
            "SELECT imei FROM gadgets WHERE imei = :imei LIMIT 1"
        );
        $check->execute([':imei' => $imei]);
        if (!$check->fetch()) {
            throw new RuntimeException('Thiết bị không tồn tại trong kho.');
        }

        $stmt = $this->pdo->prepare("DELETE FROM gadgets WHERE imei = :imei");
        $stmt->execute([':imei' => $imei]);

        $ok = $stmt->rowCount() > 0;

        if ($ok) {
            $this->_audit("Xóa thiết bị khỏi kho: IMEI {$imei}", 'gadgets');
        }

        return $ok;
    }

    /**
     * updateItemDetail — Admin sửa thông tin cơ bản của thiết bị.
     * Cho phép sửa:
     *   - imei         (gadgets.imei — PK, đổi sang IMEI mới)
     *   - status        (gadgets.status)
     *   - final_price   (valuation_sessions.ai_suggested_price, qua session_id liên kết)
     *
     * @param  string $imei  IMEI hiện tại của thiết bị (định danh bản ghi cần sửa)
     * @param  array  $data  ['imei' => ?string, 'status' => ?string, 'final_price' => ?int|float]
     * @return bool   true nếu có ít nhất 1 thay đổi được áp dụng
     * @throws InvalidArgumentException  Dữ liệu không hợp lệ
     * @throws RuntimeException          Thiết bị không tồn tại / IMEI mới đã bị trùng
     */
    public function updateItemDetail(string $imei, array $data): bool
    {
        $imei = trim($imei);

        if ($imei === '') {
            throw new InvalidArgumentException('IMEI không hợp lệ.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT imei, session_id, status FROM gadgets WHERE imei = :imei LIMIT 1"
        );
        $stmt->execute([':imei' => $imei]);
        $gadget = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gadget) {
            throw new RuntimeException('Thiết bị không tồn tại trong kho.');
        }

        $newImei   = isset($data['imei']) ? trim((string) $data['imei']) : null;
        $newStatus = isset($data['status']) ? trim((string) $data['status']) : null;
        $hasPrice  = array_key_exists('final_price', $data) && $data['final_price'] !== null && $data['final_price'] !== '';
        $newPrice  = $hasPrice ? (int) $data['final_price'] : null;

        if ($newImei !== null && $newImei !== '' && !preg_match('/^\d{15}$/', $newImei)) {
            throw new InvalidArgumentException('IMEI phải gồm đúng 15 chữ số.');
        }
        if ($newStatus !== null && $newStatus !== '' && !in_array($newStatus, ['Stored', 'Refurbishing', 'Sold'], true)) {
            throw new InvalidArgumentException('Trạng thái không hợp lệ.');
        }
        if ($hasPrice && $newPrice < 0) {
            throw new InvalidArgumentException('Giá chốt mua không được âm.');
        }

        $changed = false;

        $this->pdo->beginTransaction();
        try {
            // 1. Đổi IMEI (PK) nếu khác và chưa bị trùng
            if ($newImei !== null && $newImei !== '' && $newImei !== $gadget['imei']) {
                $dup = $this->pdo->prepare("SELECT imei FROM gadgets WHERE imei = :imei LIMIT 1");
                $dup->execute([':imei' => $newImei]);
                if ($dup->fetch()) {
                    throw new RuntimeException("IMEI {$newImei} đã tồn tại trong kho.");
                }

                $this->pdo->prepare(
                    "UPDATE gadgets SET imei = :new_imei WHERE imei = :old_imei"
                )->execute([':new_imei' => $newImei, ':old_imei' => $gadget['imei']]);

                $changed = true;
            }

            // 2. Đổi trạng thái nếu có gửi và khác giá trị hiện tại
            $targetImei = ($newImei !== null && $newImei !== '') ? $newImei : $gadget['imei'];

            if ($newStatus !== null && $newStatus !== '' && $newStatus !== $gadget['status']) {
                $this->pdo->prepare(
                    "UPDATE gadgets SET status = :status WHERE imei = :imei"
                )->execute([':status' => $newStatus, ':imei' => $targetImei]);

                $changed = true;
            }

            // 3. Đổi giá chốt mua (ai_suggested_price của valuation_sessions liên kết)
            if ($hasPrice) {
                $this->pdo->prepare(
                    "UPDATE valuation_sessions SET ai_suggested_price = :price WHERE session_id = :sid"
                )->execute([':price' => $newPrice, ':sid' => $gadget['session_id']]);

                $changed = true;
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Lỗi cập nhật thiết bị: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if ($changed) {
            $this->_audit(
                "Admin sửa thiết bị IMEI {$gadget['imei']} (session #{$gadget['session_id']})",
                'gadgets'
            );
        }

        return $changed;
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
        } catch (Throwable) {}
    }
}