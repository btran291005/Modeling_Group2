<?php
// ============================================================
// FILE: services/DashboardService.php
// ============================================================

declare(strict_types=1);

class DashboardService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ----------------------------------------------------------
    // 4 con số tổng quan (Card Metrics)
    // ----------------------------------------------------------
    public function getCardMetrics(): array
    {
        // 1. Tổng số nhân viên (role = 'Staff')
        $stmtStaff = $this->pdo->query("
            SELECT COUNT(*) AS total
            FROM users
            WHERE role = 'Staff'
        ");
        $totalStaff = (int) $stmtStaff->fetchColumn();

        // 2. Tổng thiết bị đang trong kho (tất cả trạng thái)
        $stmtTotal = $this->pdo->query("
            SELECT COUNT(*) AS total
            FROM inventory_items
        ");
        $totalInventory = (int) $stmtTotal->fetchColumn();

        // 3. Tổng số máy đã bán
        $stmtSold = $this->pdo->query("
            SELECT COUNT(*) AS total
            FROM inventory_items
            WHERE status = 'Sold'
        ");
        $totalSold = (int) $stmtSold->fetchColumn();

        // 4. Tổng vốn đã chi (SUM final_price từ valuation_sessions thành công)
        $stmtCapital = $this->pdo->query("
            SELECT COALESCE(SUM(vs.final_price), 0) AS total_capital
            FROM valuation_sessions vs
            WHERE vs.final_status = 'Purchased'
              AND vs.final_price IS NOT NULL
        ");
        $totalCapital = (float) $stmtCapital->fetchColumn();

        return [
            'total_staff'     => $totalStaff,
            'total_inventory' => $totalInventory,
            'total_sold'      => $totalSold,
            'total_capital'   => $totalCapital,
        ];
    }

    // ----------------------------------------------------------
    // Phân bố thiết bị trong kho theo Hãng (Brand Distribution)
    // ----------------------------------------------------------
    public function getBrandDistribution(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                b.name        AS brand_name,
                COUNT(ii.id)  AS total
            FROM inventory_items ii
            INNER JOIN device_models dm ON dm.id = ii.model_id
            INNER JOIN brands b         ON b.id  = dm.brand_id
            GROUP BY b.id, b.name
            ORDER BY total DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------
    // 5 thiết bị mới nhất nhập kho (Recent Activities)
    // ----------------------------------------------------------
    public function getRecentActivities(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                ii.id            AS inventory_id,
                ii.imei,
                ii.received_at,
                ii.status,
                b.name           AS brand_name,
                dm.name          AS model_name,
                u.full_name      AS staff_name
            FROM inventory_items ii
            INNER JOIN valuation_sessions vs ON vs.id       = ii.session_id
            INNER JOIN device_models      dm ON dm.id       = ii.model_id
            INNER JOIN brands             b  ON b.id        = dm.brand_id
            INNER JOIN users              u  ON u.user_id   = vs.staff_id
            ORDER BY ii.received_at DESC
            LIMIT 5
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}