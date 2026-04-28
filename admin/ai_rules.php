<?php
/**
 * admin/ai_rules.php
 * UC06 - Quản lý quy tắc định giá AI (Admin only)
 * Cho phép: Xem, Thêm, Sửa, Kích hoạt/Tắt, Xóa quy tắc
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();
$currentUser = getCurrentUser();

// ============================================================
// XỬ LÝ ACTION (POST)
// ============================================================
// Khởi tạo biến $flash an toàn
$flash = null;

// Lấy flash từ session (để hiện thông báo sau khi redirect)
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- THÊM QUY TẮC ----
    if ($action === 'create') {
        $conditionName    = trim($_POST['condition_name'] ?? '');
        $deductionPercent = (float) ($_POST['deduction_percent'] ?? 0);
        $isActive         = isset($_POST['is_active']) ? 1 : 0;

        if (empty($conditionName)) {
            $flash = ['type' => 'error', 'msg' => 'Tên điều kiện không được để trống.'];
        } elseif ($deductionPercent < 0 || $deductionPercent > 100) {
            $flash = ['type' => 'error', 'msg' => 'Phần trăm khấu trừ phải trong khoảng 0 - 100.'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ai_pricing_rules (condition_name, deduction_percent, is_active)
                VALUES (:name, :pct, :active)
            ");
            $stmt->execute([':name' => $conditionName, ':pct' => $deductionPercent, ':active' => $isActive]);

            $newId = $pdo->lastInsertId();
            // Audit log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                ->execute([$_SESSION['user_id'], "Thêm quy tắc AI: [{$conditionName}] - {$deductionPercent}%", 'ai_pricing_rules']);

            $flash = ['type' => 'success', 'msg' => "Đã thêm quy tắc «{$conditionName}» thành công."];
        }
    }

    // ---- CẬP NHẬT QUY TẮC ----
    elseif ($action === 'update') {
        $ruleId           = (int) ($_POST['rule_id'] ?? 0);
        $conditionName    = trim($_POST['condition_name'] ?? '');
        $deductionPercent = (float) ($_POST['deduction_percent'] ?? 0);
        $isActive         = isset($_POST['is_active']) ? 1 : 0;

        if ($ruleId <= 0 || empty($conditionName)) {
            $flash = ['type' => 'error', 'msg' => 'Dữ liệu không hợp lệ.'];
        } elseif ($deductionPercent < 0 || $deductionPercent > 100) {
            $flash = ['type' => 'error', 'msg' => 'Phần trăm khấu trừ phải trong khoảng 0 - 100.'];
        } else {
            $stmt = $pdo->prepare("
                UPDATE ai_pricing_rules
                SET condition_name = :name, deduction_percent = :pct, is_active = :active
                WHERE rule_id = :id
            ");
            $stmt->execute([':name' => $conditionName, ':pct' => $deductionPercent, ':active' => $isActive, ':id' => $ruleId]);

            $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                ->execute([$_SESSION['user_id'], "Cập nhật quy tắc AI #{$ruleId}: [{$conditionName}] - {$deductionPercent}%", 'ai_pricing_rules']);

            $flash = ['type' => 'success', 'msg' => 'Đã cập nhật quy tắc thành công.'];
        }
    }

    // ---- XÓA QUY TẮC ----
    elseif ($action === 'delete') {
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        if ($ruleId > 0) {
            // SỬA LỖI Ở ĐÂY: session_rule_details
            $checkStmt  = $pdo->prepare("SELECT COUNT(*) FROM session_rule_details WHERE rule_id = ?");
            $checkStmt->execute([$ruleId]);
            $usageCount = (int) $checkStmt->fetchColumn();

            if ($usageCount > 0) {
                $flash = ['type' => 'error', 'msg' => "Không thể xóa: Quy tắc này đã được dùng trong {$usageCount} phiên định giá."];
            } else {
                $pdo->prepare("DELETE FROM ai_pricing_rules WHERE rule_id = ?")->execute([$ruleId]);
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                    ->execute([$_SESSION['user_id'], "Xóa quy tắc AI #{$ruleId}", 'ai_pricing_rules']);
                $flash = ['type' => 'success', 'msg' => 'Đã xóa quy tắc thành công.'];
            }
        }
    }

    // ---- TOGGLE ACTIVE ----
    elseif ($action === 'toggle') {
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        if ($ruleId > 0) {
            $pdo->prepare("UPDATE ai_pricing_rules SET is_active = 1 - is_active WHERE rule_id = ?")
                ->execute([$ruleId]);
            $checkS = $pdo->prepare("SELECT is_active FROM ai_pricing_rules WHERE rule_id = ?");
            $checkS->execute([$ruleId]);
            $newState = (int) $checkS->fetchColumn();

            $stateLabel = $newState ? 'Kích hoạt' : 'Tắt';
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                ->execute([$_SESSION['user_id'], "{$stateLabel} quy tắc AI #{$ruleId}", 'ai_pricing_rules']);

            $flash = ['type' => 'success', 'msg' => "Đã {$stateLabel} quy tắc thành công."];
        }
    }

    // Redirect để tránh resubmit
    if ($flash) {
        $_SESSION['flash'] = $flash;
        header('Location: ai_rules.php');
        exit;
    }
}

// ============================================================
// LẤY DỮ LIỆU
// ============================================================
// SỬA LỖI Ở ĐÂY: session_rule_details
$rules = $pdo->query("
    SELECT rule_id, condition_name, deduction_percent, is_active,
           (SELECT COUNT(*) FROM session_rule_details sr WHERE sr.rule_id = ai_pricing_rules.rule_id) AS usage_count
    FROM ai_pricing_rules
    ORDER BY is_active DESC, deduction_percent DESC
")->fetchAll();

$totalActive   = count(array_filter($rules, fn($r) => $r['is_active']));
$totalInactive = count($rules) - $totalActive;
$totalDeductionActive = array_sum(array_map(fn($r) => $r['is_active'] ? (float)$r['deduction_percent'] : 0, $rules));

// ---- Lấy rule cần edit (nếu có ?edit=) ----
$editRule = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM ai_pricing_rules WHERE rule_id = ?");
    $stmtEdit->execute([(int) $_GET['edit']]);
    $editRule = $stmtEdit->fetch();
}

// ============================================================
// RENDER
// ============================================================
renderHtmlHead('Quy tắc định giá AI', [
    '../assets/css/pages/admin/ai_rules.css'
]);
renderSidebar('ai_rules');
renderMainOpen();
renderTopbar('Quy tắc định giá AI', '<span>Admin</span> / <span>Quy tắc AI</span>');
?>

<div class="page-content">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible">
        <span class="alert-icon"><?= $flash['type'] === 'success' ? '✓' : '✕' ?></span>
        <span><?= e($flash['msg']) ?></span>
        <button type="button" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>

    <div class="air-page-header">
        <div class="air-header-left">
            <h2 class="page-title">Quản lý Quy tắc Định giá AI</h2>
            <p class="page-subtitle">Cấu hình các điều kiện trừ giá mà AI áp dụng khi định giá thiết bị</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Thêm quy tắc
        </button>
    </div>

    <div class="air-stats-row">
        <div class="air-stat-card air-stat-blue">
            <div class="air-stat-icon">⚡</div>
            <div class="air-stat-body">
                <span class="air-stat-value"><?= count($rules) ?></span>
                <span class="air-stat-label">Tổng quy tắc</span>
            </div>
        </div>
        <div class="air-stat-card air-stat-green">
            <div class="air-stat-icon">✓</div>
            <div class="air-stat-body">
                <span class="air-stat-value"><?= $totalActive ?></span>
                <span class="air-stat-label">Đang hoạt động</span>
            </div>
        </div>
        <div class="air-stat-card air-stat-gray">
            <div class="air-stat-icon">○</div>
            <div class="air-stat-body">
                <span class="air-stat-value"><?= $totalInactive ?></span>
                <span class="air-stat-label">Đã tắt</span>
            </div>
        </div>
        <div class="air-stat-card air-stat-orange">
            <div class="air-stat-icon">%</div>
            <div class="air-stat-body">
                <span class="air-stat-value"><?= number_format($totalDeductionActive, 1) ?>%</span>
                <span class="air-stat-label">Tổng trừ (đang bật)</span>
            </div>
        </div>
    </div>

    <div class="air-info-card">
        <div class="air-info-header">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <strong>Cách Gemini AI sử dụng các quy tắc này</strong>
        </div>
        <p class="air-info-text">
            Khi Staff thực hiện định giá, các quy tắc <strong>đang hoạt động</strong> sẽ được gửi kèm vào prompt của AI.
            AI sẽ áp dụng % khấu trừ tích lũy vào <em>giá cơ sở</em> của thiết bị (được cấu hình trong Dữ liệu nền).
            Ngoài ra, hệ thống tự động trừ thêm <strong>0.3%/mỗi % chai pin dưới 80%</strong>.
        </p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Danh sách quy tắc</h3>
            <span class="card-badge"><?= count($rules) ?> quy tắc</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rules)): ?>
                <div class="empty-state">
                    <div class="empty-icon">⚡</div>
                    <p>Chưa có quy tắc nào. Hãy thêm quy tắc đầu tiên.</p>
                    <button class="btn btn-primary" onclick="openCreateModal()">Thêm ngay</button>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tên điều kiện</th>
                            <th class="text-center">Khấu trừ (%)</th>
                            <th class="text-center">Trạng thái</th>
                            <th class="text-center">Lần dùng</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $i => $rule): ?>
                        <tr class="<?= !$rule['is_active'] ? 'row-inactive' : '' ?>">
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td>
                                <span class="rule-name <?= !$rule['is_active'] ? 'text-muted' : '' ?>">
                                    <?= e($rule['condition_name']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="deduction-badge <?= (float)$rule['deduction_percent'] >= 20 ? 'deduction-high' : ((float)$rule['deduction_percent'] >= 10 ? 'deduction-mid' : 'deduction-low') ?>">
                                    −<?= number_format((float)$rule['deduction_percent'], 1) ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action"  value="toggle">
                                    <input type="hidden" name="rule_id" value="<?= $rule['rule_id'] ?>">
                                    <button type="submit" class="toggle-btn <?= $rule['is_active'] ? 'toggle-on' : 'toggle-off' ?>"
                                            title="<?= $rule['is_active'] ? 'Nhấn để tắt' : 'Nhấn để bật' ?>">
                                        <span class="toggle-knob"></span>
                                    </button>
                                </form>
                            </td>
                            <td class="text-center">
                                <span class="text-secondary text-sm"><?= $rule['usage_count'] ?> phiên</span>
                            </td>
                            <td class="text-right">
                                <div class="action-group">
                                    <button class="btn-icon btn-icon-blue"
                                            onclick='openEditModal(<?= json_encode($rule) ?>)'
                                            title="Chỉnh sửa">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <?php if ((int)$rule['usage_count'] === 0): ?>
                                    <button class="btn-icon btn-icon-red"
                                            onclick="confirmDelete(<?= $rule['rule_id'] ?>, '<?= e($rule['condition_name']) ?>')"
                                            title="Xóa">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-icon btn-icon-disabled" disabled title="Đang được sử dụng, không thể xóa">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><div class="modal-overlay" id="rule-modal" onclick="closeRuleModal(event)">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">

        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">Thêm quy tắc mới</h3>
            <button class="modal-close" onclick="closeRuleModal()">&times;</button>
        </div>

        <form method="POST" id="rule-form">
            <input type="hidden" name="action"  id="form-action"  value="create">
            <input type="hidden" name="rule_id" id="form-rule-id" value="">

            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label" for="condition_name">
                        Tên điều kiện <span class="required">*</span>
                    </label>
                    <input type="text" id="condition_name" name="condition_name"
                           class="form-control" maxlength="150"
                           placeholder="VD: Màn hình bị trầy xước nhẹ"
                           required>
                    <p class="form-hint">Mô tả ngắn gọn tình trạng/điều kiện gây khấu trừ giá.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="deduction_percent">
                        Phần trăm khấu trừ (%) <span class="required">*</span>
                    </label>
                    <div class="input-with-suffix">
                        <input type="number" id="deduction_percent" name="deduction_percent"
                               class="form-control" min="0" max="100" step="0.5"
                               placeholder="10.0"
                               required>
                        <span class="input-suffix">%</span>
                    </div>
                    <p class="form-hint">Phần trăm trừ vào giá cơ sở của thiết bị (0 - 100%).</p>
                </div>

                <div class="air-preview-card" id="preview-card" style="display:none;">
                    <div class="air-preview-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Ví dụ tính toán
                    </div>
                    <p class="air-preview-text">
                        Thiết bị giá cơ sở <strong>10.000.000₫</strong> bị trừ
                        <strong id="preview-pct">0</strong>% →
                        còn <strong id="preview-result" class="text-primary">–</strong>
                    </p>
                </div>

                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                        <span class="toggle-label-text">Kích hoạt ngay</span>
                    </label>
                    <p class="form-hint">Chỉ các quy tắc đang bật mới được gửi đến AI.</p>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeRuleModal()">Hủy</button>
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Lưu quy tắc
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="delete-modal" onclick="closeDeleteModal(event)">
    <div class="modal modal-sm" role="dialog">
        <div class="modal-header">
            <h3 class="modal-title">Xác nhận xóa</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Bạn có chắc chắn muốn xóa quy tắc <strong id="delete-rule-name"></strong>?</p>
            <p class="text-muted text-sm mt-2">Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()">Hủy</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="rule_id" id="delete-rule-id">
                <button type="submit" class="btn btn-danger">Xóa</button>
            </form>
        </div>
    </div>
</div>

<?php
renderLayoutClose(['../assets/js/main.js']);
?>


<link rel="stylesheet" href="../assets/css/pages/admin/ai_rules.css">


<script>
// ============================================================
// MODAL: THÊM QUY TẮC
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. THÊM QUY TẮC MỚI
    if ($action === 'create') {
        $name = trim($_POST['condition_name'] ?? '');
        $pct  = (float)($_POST['deduction_percent'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($name) && $pct >= 0) {
            $stmt = $pdo->prepare("INSERT INTO ai_pricing_rules (condition_name, deduction_percent, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$name, $pct, $active]);
            
            // Ghi log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                ->execute([$_SESSION['user_id'], "Thêm quy tắc AI: [{$name}] - {$pct}%", 'ai_pricing_rules']);
            
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã thêm quy tắc mới thành công!'];
        }
    }

    // 2. CẬP NHẬT QUY TẮC (EDIT)
    elseif ($action === 'update') {
        $id   = (int)($_POST['rule_id'] ?? 0);
        $name = trim($_POST['condition_name'] ?? '');
        $pct  = (float)($_POST['deduction_percent'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0 && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE ai_pricing_rules SET condition_name = ?, deduction_percent = ?, is_active = ? WHERE rule_id = ?");
            $stmt->execute([$name, $pct, $active, $id]);
            
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                ->execute([$_SESSION['user_id'], "Cập nhật quy tắc AI #{$id}", 'ai_pricing_rules']);
            
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cập nhật thay đổi thành công!'];
        }
    }

    // 3. XÓA QUY TẮC
    elseif ($action === 'delete') {
        $id = (int)($_POST['rule_id'] ?? 0);
        if ($id > 0) {
            // Kiểm tra xem quy tắc đã được dùng trong lịch sử chưa (chuẩn hóa tên bảng session_rule_details)
            $check = $pdo->prepare("SELECT COUNT(*) FROM session_rule_details WHERE rule_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Không thể xóa quy tắc này vì đã có dữ liệu thu mua liên quan! Hãy dùng tính năng Tắt thay vì Xóa.'];
            } else {
                $pdo->prepare("DELETE FROM ai_pricing_rules WHERE rule_id = ?")->execute([$id]);
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
                    ->execute([$_SESSION['user_id'], "Xóa vĩnh viễn quy tắc AI #{$id}", 'ai_pricing_rules']);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xóa quy tắc thành công!'];
            }
        }
    }

    // 4. BẬT/TẮT NHANH (TOGGLE)
    elseif ($action === 'toggle') {
        $id = (int)($_POST['rule_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE ai_pricing_rules SET is_active = 1 - is_active WHERE rule_id = ?")->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã thay đổi trạng thái quy tắc!'];
        }
    }

    header('Location: ai_rules.php');
    exit;
}

// Lấy danh sách quy tắc hiện có
$rules = $pdo->query("
    SELECT *, (SELECT COUNT(*) FROM session_rule_details sr WHERE sr.rule_id = ai_pricing_rules.rule_id) as usage_count 
    FROM ai_pricing_rules 
    ORDER BY is_active DESC, deduction_percent DESC
")->fetchAll();

renderHtmlHead('Quy tắc định giá AI', ['../assets/css/pages/admin/ai_rules.css']);
renderSidebar('ai_rules');
renderMainOpen();
renderTopbar('Quy tắc định giá AI', 'Admin / AI Rules');
?>

<div class="page-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
            <?= e($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="air-page-header">
        <h2 class="page-title">Cấu hình Quy tắc AI</h2>
        <button class="btn btn-primary" onclick="openCreateModal()">+ Thêm quy tắc</button>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Điều kiện</th>
                    <th class="text-center">Khấu trừ (%)</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $r): ?>
                <tr class="<?= !$r['is_active'] ? 'row-inactive' : '' ?>">
                    <td><strong><?= e($r['condition_name']) ?></strong></td>
                    <td class="text-center"><span class="badge">-<?= $r['deduction_percent'] ?>%</span></td>
                    <td class="text-center">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="rule_id" value="<?= $r['rule_id'] ?>">
                            <button type="submit" class="toggle-btn <?= $r['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></button>
                        </form>
                    </td>
                    <td class="text-right">
                        <div class="action-group">
                            <button class="btn-icon btn-icon-blue" onclick='openEditModal(<?= json_encode($r) ?>)'>✎</button>
                            
                            <?php if ($r['usage_count'] == 0): ?>
                                <button class="btn-icon btn-icon-red" onclick="confirmDelete(<?= $r['rule_id'] ?>, '<?= e($r['condition_name']) ?>')">🗑</button>
                            <?php else: ?>
                                <button class="btn-icon btn-icon-disabled" title="Đã có dữ liệu, không thể xóa">🔒</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="rule-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Thêm quy tắc mới</h3>
            <button class="modal-close" onclick="closeModal('rule-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="rule_id" id="form-rule-id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tên điều kiện (VD: Màn hình trầy xước)</label>
                    <input type="text" name="condition_name" id="field-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Khấu trừ (%)</label>
                    <input type="number" name="deduction_percent" id="field-pct" class="form-control" step="0.1" required>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="is_active" id="field-active" value="1" checked> Kích hoạt ngay</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Lưu quy tắc</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="delete-modal">
    <div class="modal modal-sm">
        <div class="modal-header"><h3>Xác nhận xóa</h3></div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="rule_id" id="delete-rule-id">
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa quy tắc <strong id="delete-rule-name"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('delete-modal')">Hủy</button>
                <button type="submit" class="btn btn-danger">Xóa luôn</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Thêm quy tắc mới';
    document.getElementById('form-action').value = 'create';
    document.getElementById('form-rule-id').value = '';
    document.getElementById('field-name').value = '';
    document.getElementById('field-pct').value = '';
    document.getElementById('field-active').checked = true;
    document.getElementById('rule-modal').classList.add('visible');
}

function openEditModal(rule) {
    document.getElementById('modal-title').textContent = 'Chỉnh sửa quy tắc';
    document.getElementById('form-action').value = 'update';
    document.getElementById('form-rule-id').value = rule.rule_id;
    document.getElementById('field-name').value = rule.condition_name;
    document.getElementById('field-pct').value = rule.deduction_percent;
    document.getElementById('field-active').checked = (rule.is_active == 1);
    document.getElementById('rule-modal').classList.add('visible');
}

function confirmDelete(id, name) {
    document.getElementById('delete-rule-id').value = id;
    document.getElementById('delete-rule-name').textContent = name;
    document.getElementById('delete-modal').classList.add('visible');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('visible');

// PREVIEW TÍNH TOÁN
function updatePreview(pct) {
    const previewCard = document.getElementById('preview-card');
    if (!pct || pct <= 0) { previewCard.style.display = 'none'; return; }
    const base   = 10000000;
    const result = base * (1 - pct / 100);
    document.getElementById('preview-pct').textContent    = pct.toFixed(1);
    document.getElementById('preview-result').textContent = new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND', maximumFractionDigits:0 }).format(result);
    previewCard.style.display = 'block';
}

document.getElementById('deduction_percent').addEventListener('input', function() {
    updatePreview(parseFloat(this.value));
});

// ============================================================
// ESC để đóng modal
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('rule-modal').classList.remove('visible');
        document.getElementById('delete-modal').classList.remove('visible');
    }
});

// Tự ẩn flash sau 4 giây
const alertEl = document.querySelector('.alert-dismissible');
if (alertEl) setTimeout(() => alertEl.remove(), 4000);
}
</script>

<?php renderLayoutClose(); ?>