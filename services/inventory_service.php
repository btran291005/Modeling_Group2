<?php
// ============================================================
// FILE: services/InventoryService.php
// ============================================================

declare(strict_types=1);

/**
 * services/InventoryService.php
 *
 * Business logic cho tính năng Quản lý Kho.
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
     * Trả về: IMEI, tên máy, cấu hình, pin, giá, trạng thái, khách hàng...
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStaffInventory(): array
    {
        $sql = "
            SELECT
                g.imei,
                g.status         AS gadget_status,
                g.created_at     AS received_at,
                vs.session_id,
                vs.battery_health,
                vs.ai_suggested_price,
                dm.model_name,
                dm.ram_gb,
                dm.rom_gb,
                b.brand_name,
                c.full_name       AS customer_name,
                c.phone_number
            FROM gadgets g
            JOIN valuation_sessions vs ON g.session_id  = vs.session_id
            JOIN device_models dm      ON vs.model_id   = dm.model_id
            JOIN brands b               ON dm.brand_id   = b.brand_id
            LEFT JOIN customers c       ON vs.customer_id = c.customer_id
            ORDER BY g.created_at DESC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cập nhật trạng thái thiết bị theo IMEI.
     * Staff được phép đổi trạng thái nhưng KHÔNG được xóa (R03).
     *
     * @param  string $imei      IMEI thiết bị (PK của bảng gadgets)
     * @param  string $newStatus Trạng thái mới
     * @return bool   true nếu update thành công (>=0 rows affected & hợp lệ)
     */
    public function updateStatus(string $imei, string $newStatus): bool
    {
        $allowedStatuses = ['Pending', 'Stored', 'Refurbishing', 'Sold'];

        if ($imei === '' || !in_array($newStatus, $allowedStatuses, true)) {
            return false;
        }

        // Kiểm tra thiết bị tồn tại
        $check = $this->pdo->prepare("SELECT imei FROM gadgets WHERE imei = :imei LIMIT 1");
        $check->execute([':imei' => $imei]);
        if (!$check->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE gadgets SET status = :status WHERE imei = :imei"
        );
        return $stmt->execute([
            ':status' => $newStatus,
            ':imei'   => $imei,
        ]);
    }
}