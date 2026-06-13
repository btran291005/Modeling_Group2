<?php
// ============================================================
// FILE: services/AiRuleService.php
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
        $sql = "
            SELECT
                r.id,
                r.rule_name,
                r.deduction_pct,
                r.is_active,
                r.created_at,
                COUNT(srd.id) AS usage_count
            FROM ai_rules r
            LEFT JOIN session_rule_details srd ON srd.rule_id = r.id
            GROUP BY r.id
            ORDER BY r.id ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------
    // Thêm quy tắc mới
    // ----------------------------------------------------------
    public function createRule(string $name, float $pct, int $isActive): bool
    {
        $sql  = "INSERT INTO ai_rules (rule_name, deduction_pct, is_active) VALUES (:name, :pct, :is_active)";
        $stmt = $this->pdo->prepare($sql);
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
        $sql  = "
            UPDATE ai_rules
            SET rule_name = :name, deduction_pct = :pct, is_active = :is_active
            WHERE id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
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
        $sql  = "UPDATE ai_rules SET is_active = 1 - is_active WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // ----------------------------------------------------------
    // Xóa quy tắc — ném Exception nếu đã có lịch sử sử dụng
    // ----------------------------------------------------------
    public function deleteRule(int $id): bool
    {
        // Kiểm tra usage_count
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

        $stmt = $this->pdo->prepare("DELETE FROM ai_rules WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}