<?php
// ============================================================
// FILE: services/InventoryService.php
// ============================================================

declare(strict_types=1);

/**
 * services/InventoryService.php
 *
 * Business logic cho tính năng Quản lý Kho (Inventory).
 * Nguyên tắc:
 *   - Chỉ nhận input, gọi DB, trả về array hoặc bool.
 *   - Không echo, không header().
 *   - Dùng PDO prepared statement cho mọi truy vấn có tham số.
 */
class InventoryService
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Danh sách thiết bị trong kho (dành cho Staff xem).
     * Trả về: id, IMEI, tên máy, cấu hình, pin, giá, trạng thái, khách hàng...
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStaffInventory(): array
    {
        $sql = "
            SELECT
                ii.id             AS id,
                ii.imei           AS imei,
                ii.status         AS status,
                ii.created_at     AS received_at,
                vs.session_id,
                vs.battery_health,
                vs.ai_suggested_price AS price,
                dm.model_name,
                dm.ram_gb,
                dm.rom_gb,
                b.brand_name,
                c.full_name       AS customer_name,
                c.phone_number
            FROM inventory_items ii
            JOIN valuation_sessions vs ON ii.session_id  = vs.session_id
            JOIN device_models dm      ON vs.model_id    = dm.model_id
            JOIN brands b               ON dm.brand_id    = b.brand_id
            LEFT JOIN customers c       ON vs.customer_id = c.customer_id
            ORDER BY ii.created_at DESC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cập nhật trạng thái thiết bị theo ID.
     * Staff được phép đổi trạng thái nhưng KHÔNG được xóa.
     *
     * @param  int    $id        ID của thiết bị (inventory_items.id)
     * @param  string $newStatus Trạng thái mới
     * @return bool   true nếu update thành công
     */
    public function updateStatus(int $id, string $newStatus): bool
    {
        $allowedStatuses = ['Pending', 'Stored', 'Refurbishing', 'Sold'];

        if ($id <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            return false;
        }

        // Kiểm tra thiết bị tồn tại
        $check = $this->pdo->prepare("SELECT id FROM inventory_items WHERE id = :id LIMIT 1");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE inventory_items SET status = :status WHERE id = :id"
        );
        return $stmt->execute([
            ':status' => $newStatus,
            ':id'     => $id,
        ]);
    }
}