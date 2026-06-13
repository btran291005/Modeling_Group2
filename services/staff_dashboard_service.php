<?php
// ============================================================
// FILE: services/StaffDashboardService.php
// ============================================================

declare(strict_types=1);

class StaffDashboardService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ----------------------------------------------------------
    // 4 con số tổng quan của nhân viên (Card Metrics)
    // ----------------------------------------------------------
    public function getCardMetrics(int $staffId): array
    {
        // 1. Số phiên định giá hôm nay
        $stmtToday = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM valuation_sessions
            WHERE user_id = :uid
              AND DATE(created_at) = CURDATE()
        ");
        $stmtToday->execute([':uid' => $staffId]);
        $todaySessions = (int) $stmtToday->fetchColumn();

        // 2. Số phiên định giá tuần này (ISO week)
        $stmtWeek = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM valuation_sessions
            WHERE user_id = :uid
              AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $stmtWeek->execute([':uid' => $staffId]);
        $weekSessions = (int) $stmtWeek->fetchColumn();

        // 3. Tổng số máy đã thu mua
        $stmtPurchased = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM valuation_sessions
            WHERE user_id    = :uid
              AND final_status = 'Purchased'
        ");
        $stmtPurchased->execute([':uid' => $staffId]);
        $totalPurchased = (int) $stmtPurchased->fetchColumn();

        // 4. Tổng vốn đã thu mua (theo ai_suggested_price)
        $stmtCapital = $this->pdo->prepare("
            SELECT COALESCE(SUM(ai_suggested_price), 0)
            FROM valuation_sessions
            WHERE user_id    = :uid
              AND final_status = 'Purchased'
        ");
        $stmtCapital->execute([':uid' => $staffId]);
        $totalCapital = (float) $stmtCapital->fetchColumn();

        return [
            'today_sessions'  => $todaySessions,
            'week_sessions'   => $weekSessions,
            'total_purchased' => $totalPurchased,
            'total_capital'   => $totalCapital,
        ];
    }

    // ----------------------------------------------------------
    // 10 phiên định giá gần nhất của nhân viên (Recent Activities)
    // ----------------------------------------------------------
    public function getRecentActivities(int $staffId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                vs.session_id,
                vs.created_at,
                vs.final_status,
                vs.ai_suggested_price,
                dm.model_name,
                b.brand_name,
                g.imei,
                CASE
                    WHEN vs.final_status = 'Purchased'
                    THEN vs.ai_suggested_price
                    ELSE NULL
                END AS final_price
            FROM valuation_sessions vs
            JOIN device_models dm ON vs.model_id  = dm.model_id
            JOIN brands        b  ON dm.brand_id  = b.brand_id
            LEFT JOIN gadgets  g  ON g.session_id = vs.session_id
            WHERE vs.user_id = :uid
            ORDER BY vs.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([':uid' => $staffId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}