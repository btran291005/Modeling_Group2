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
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Danh sách thiết bị trong kho cho Staff xem.
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
     *
     * @param  string $imei
     * @param  string $newStatus
     * @return bool
     */
    public function updateStatus(string $imei, string $newStatus): bool
    {
        $allowedStatuses = ['Stored', 'Refurbishing', 'Sold'];

        if (empty($imei) || !in_array($newStatus, $allowedStatuses, true)) {
            return false;
        }

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
     *
     * @param  string $imei
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
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
     *
     * @param  string $imei
     * @param  array  $data
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
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

            $targetImei = ($newImei !== null && $newImei !== '') ? $newImei : $gadget['imei'];

            if ($newStatus !== null && $newStatus !== '' && $newStatus !== $gadget['status']) {
                $this->pdo->prepare(
                    "UPDATE gadgets SET status = :status WHERE imei = :imei"
                )->execute([':status' => $newStatus, ':imei' => $targetImei]);

                $changed = true;
            }

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
        } catch (Throwable $e) {}
    }
}