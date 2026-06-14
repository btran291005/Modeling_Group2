<?php
// ============================================================
// FILE: services/ai_rule_service.php  (class: AiRuleService)
// ============================================================

declare(strict_types=1);

class AiRuleService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ----------------------------------------------------------
    // Lấy danh sách quy tắc kèm usage_count
    // ----------------------------------------------------------
    public function getAllRules(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                r.rule_id            AS id,
                r.condition_name     AS rule_name,
                r.deduction_percent  AS deduction_pct,
                r.is_active,
                COUNT(srd.rule_id)   AS usage_count
            FROM ai_pricing_rules r
            LEFT JOIN session_rule_details srd ON srd.rule_id = r.rule_id
            GROUP BY r.rule_id, r.condition_name, r.deduction_percent, r.is_active
            ORDER BY r.rule_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------
    // Thêm quy tắc mới
    // ----------------------------------------------------------
    public function createRule(string $name, float $pct, int $isActive): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_pricing_rules (condition_name, deduction_percent, is_active)
            VALUES (:name, :pct, :is_active)
        ");

        return $stmt->execute([
            ':name'      => trim($name),
            ':pct'       => $pct,
            ':is_active' => $isActive,
        ]);
    }

    // ----------------------------------------------------------
    // Cập nhật quy tắc
    // ----------------------------------------------------------
    public function updateRule(int $id, string $name, float $pct, int $isActive): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ai_pricing_rules
            SET condition_name    = :name,
                deduction_percent = :pct,
                is_active         = :is_active
            WHERE rule_id = :id
        ");

        return $stmt->execute([
            ':name'      => trim($name),
            ':pct'       => $pct,
            ':is_active' => $isActive,
            ':id'        => $id,
        ]);
    }

    // ----------------------------------------------------------
    // Đảo trạng thái is_active
    // ----------------------------------------------------------
    public function toggleRule(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ai_pricing_rules
            SET is_active = 1 - is_active
            WHERE rule_id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    // ----------------------------------------------------------
    // Xóa quy tắc — ném Exception nếu đã có lịch sử sử dụng
    // ----------------------------------------------------------
    public function deleteRule(int $id): bool
    {
        $check = $this->pdo->prepare(
            "SELECT COUNT(*) FROM session_rule_details WHERE rule_id = :id"
        );
        $check->execute([':id' => $id]);
        $count = (int) $check->fetchColumn();

        if ($count > 0) {
            throw new RuntimeException(
                "Không thể xóa: Quy tắc này đã được sử dụng trong {$count} phiên định giá."
            );
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM ai_pricing_rules WHERE rule_id = :id"
        );

        return $stmt->execute([':id' => $id]);
    }
}