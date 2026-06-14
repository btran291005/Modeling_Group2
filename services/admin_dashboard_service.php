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
    // Phân bố thiết bị trong kho theo Trạng thái (Stock Status Distribution)
    // Dùng cho donut chart: Stored / Refurbishing / Sold
    // ----------------------------------------------------------
    public function getStockStatusDistribution(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                status,
                COUNT(*) AS quantity
            FROM gadgets
            GROUP BY status
            ORDER BY
                CASE status
                    WHEN 'Stored'       THEN 1
                    WHEN 'Refurbishing' THEN 2
                    WHEN 'Sold'         THEN 3
                    ELSE 4
                END
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------
    // Top thiết bị "Cần lưu ý" — chỉ tính máy còn trong kho
    // (Stored hoặc Refurbishing), tính điểm ưu tiên tổng hợp từ
    // 3 trọng số rồi sắp theo điểm cao nhất trước:
    //
    //   - Pin yếu      : (80 - battery_health), tối đa 80 điểm
    //                     (pin >= 80% thì không bị trừ điểm)
    //   - Tồn kho lâu  : số ngày kể từ khi nhập kho, tối đa ~60 điểm
    //                     (mỗi ngày ~2 điểm, cap ở 30 ngày)
    //   - Lỗi/hư hại   : tổng % khấu trừ từ các rule AI đã áp dụng
    //                     cho phiên định giá đó (vd: vỡ màn 25%, ...)
    //
    // Mỗi máy trả kèm "reasons[]" để hiển thị badge lý do trên UI.
    // ----------------------------------------------------------
    public function getPriorityAttentionDevices(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                g.imei,
                g.status,
                g.created_at AS received_at,
                vs.battery_health,
                dm.model_name,
                b.brand_name,
                DATEDIFF(NOW(), g.created_at) AS days_in_stock,
                COALESCE((
                    SELECT SUM(apr.deduction_percent)
                    FROM session_rule_details srd
                    JOIN ai_pricing_rules apr ON apr.rule_id = srd.rule_id
                    WHERE srd.session_id = g.session_id
                ), 0) AS damage_pct,
                COALESCE((
                    SELECT COUNT(*)
                    FROM session_rule_details srd
                    WHERE srd.session_id = g.session_id
                ), 0) AS rule_count
            FROM gadgets g
            JOIN valuation_sessions vs ON g.session_id = vs.session_id
            JOIN device_models      dm ON vs.model_id  = dm.model_id
            JOIN brands             b  ON dm.brand_id  = b.brand_id
            WHERE g.status IN ('Stored', 'Refurbishing')
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $battery = (int) $row['battery_health'];
            $days    = (int) $row['days_in_stock'];
            $damage  = (float) $row['damage_pct'];

            // Điểm pin: pin dưới 80% mới bắt đầu tính điểm
            $batteryScore = max(0, 80 - $battery);

            // Điểm tồn kho: mỗi ngày 2 điểm, tối đa 30 ngày (60 điểm)
            $stockScore = min($days, 30) * 2;

            // Điểm hư hại: lấy trực tiếp tổng % khấu trừ từ các rule
            $damageScore = $damage;

            $row['priority_score'] = round($batteryScore + $stockScore + $damageScore, 1);

            // Danh sách lý do để hiển thị badge trên UI
            $reasons = [];
            if ($battery < 80) {
                $reasons[] = ['type' => 'battery', 'label' => "Pin {$battery}%"];
            }
            if ($days >= 14) {
                $reasons[] = ['type' => 'stock_age', 'label' => "Tồn {$days} ngày"];
            }
            if ((int) $row['rule_count'] > 0) {
                $reasons[] = [
                    'type'  => 'damage',
                    'label' => "Lỗi: -" . rtrim(rtrim(number_format($damage, 1, '.', ''), '0'), '.') . "%",
                ];
            }
            $row['reasons'] = $reasons;
        }
        unset($row);

        // Sắp theo điểm ưu tiên giảm dần, lấy top N
        usort($rows, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        return array_slice($rows, 0, $limit);
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