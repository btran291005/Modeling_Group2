<?php
// ============================================================
// FILE: services/admin_dashboard_service.php  (class: DashboardService)
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
        $totalStaff = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM users WHERE role = 'Staff'")
            ->fetchColumn();

        // 2. Tổng thiết bị đang trong kho (status Stored hoặc Refurbishing)
        $inStock = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM gadgets WHERE status IN ('Stored','Refurbishing')")
            ->fetchColumn();

        // 3. Tổng số máy đã bán
        $totalSold = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM gadgets WHERE status = 'Sold'")
            ->fetchColumn();

        // 4. Tổng vốn đã chi (SUM ai_suggested_price từ valuation_sessions Purchased)
        $totalSpent = (float) $this->pdo
            ->query("
                SELECT COALESCE(SUM(ai_suggested_price), 0)
                FROM valuation_sessions
                WHERE final_status = 'Purchased'
            ")
            ->fetchColumn();

        return [
            'total_staff' => $totalStaff,
            'in_stock'    => $inStock,
            'total_sold'  => $totalSold,
            'total_spent' => $totalSpent,
        ];
    }

    // ----------------------------------------------------------
    // Phân bố thiết bị trong kho theo Hãng (Brand Distribution)
    // ----------------------------------------------------------
    public function getBrandDistribution(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                b.brand_name,
                COUNT(g.imei) AS quantity
            FROM gadgets g
            JOIN valuation_sessions vs ON g.session_id  = vs.session_id
            JOIN device_models      dm ON vs.model_id   = dm.model_id
            JOIN brands             b  ON dm.brand_id   = b.brand_id
            WHERE g.status IN ('Stored', 'Refurbishing')
            GROUP BY b.brand_id, b.brand_name
            ORDER BY quantity DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------
    // 5 phiên định giá mới nhất (Recent Activities)
    // ----------------------------------------------------------
    public function getRecentActivities(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                vs.session_id,
                vs.created_at,
                vs.final_status,
                vs.ai_suggested_price,
                dm.model_name,
                b.brand_name,
                u.full_name  AS staff_name,
                g.imei
            FROM valuation_sessions vs
            JOIN device_models dm ON vs.model_id  = dm.model_id
            JOIN brands        b  ON dm.brand_id  = b.brand_id
            JOIN users         u  ON vs.user_id   = u.user_id
            LEFT JOIN gadgets  g  ON g.session_id = vs.session_id
            ORDER BY vs.created_at DESC
            LIMIT 5
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}