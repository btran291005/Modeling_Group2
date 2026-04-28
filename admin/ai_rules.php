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

requireRole('Admin');
$currentUser = getCurrentUser();


renderHtmlHead('Admin Dashboard', [
    '../assets/css/pages/admin/ai_rules.css'
]);

renderSidebar('ai rules');
renderMainOpen();
renderTopbar(
    'Quản lý Quy tắc cAI',
    '<a ref="#">Home</a> / Quy tắc AI'
);
?>

// ============================================================
// XỬ LÝ ACTION (POST)
// ============================================================
$flash = null; // ['type' => 'success|error', 'msg' => '...']

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
            // Kiểm tra xem rule có đang được dùng trong session_rules không
            $usageCount = (int) $pdo->prepare("SELECT COUNT(*) FROM session_rules WHERE rule_id = ?")->execute([$ruleId]) ? 0 : 0;
            $checkStmt  = $pdo->prepare("SELECT COUNT(*) FROM session_rules WHERE rule_id = ?");
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
            $newState = (int) $pdo->prepare("SELECT is_active FROM ai_pricing_rules WHERE rule_id = ?")
                                   ->execute([$ruleId]) ? 1 : 0;
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

// ---- Lấy flash từ session ----
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ============================================================
// LẤY DỮ LIỆU
// ============================================================
$rules = $pdo->query("
    SELECT rule_id, condition_name, deduction_percent, is_active,
           (SELECT COUNT(*) FROM session_rules sr WHERE sr.rule_id = ai_pricing_rules.rule_id) AS usage_count
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

    <!-- ===== FLASH MESSAGE ===== -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible">
        <span class="alert-icon"><?= $flash['type'] === 'success' ? '✓' : '✕' ?></span>
        <span><?= e($flash['msg']) ?></span>
        <button type="button" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>

    <!-- ===== PAGE HEADER ===== -->
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

    <!-- ===== STATS CARDS ===== -->
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

    <!-- ===== AI FLOW INFO CARD ===== -->
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

    <!-- ===== RULES TABLE ===== -->
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

</div><!-- end .page-content -->


<!-- ============================================================
     MODAL: THÊM / CHỈNH SỬA QUY TẮC
     ============================================================ -->
<div class="modal-overlay" id="rule-modal" onclick="closeRuleModal(event)">
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

                <!-- Preview tính toán -->
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

<!-- MODAL XÁC NHẬN XÓA -->
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

<style>
/* ============================================================
   Styles riêng cho trang AI Rules - inline để độc lập
   ============================================================ */

/* Page header */
.air-page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.air-header-left .page-subtitle { color:var(--text-secondary); font-size:0.875rem; margin-top:0.25rem; }

/* Stats row */
.air-stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.air-stat-card { display:flex; align-items:center; gap:1rem; padding:1rem 1.25rem; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:var(--radius-md); }
.air-stat-icon { font-size:1.5rem; opacity:0.9; }
.air-stat-value { display:block; font-size:1.5rem; font-weight:700; font-family:var(--font-display); line-height:1.1; }
.air-stat-label { font-size:0.75rem; color:var(--text-secondary); }
.air-stat-blue .air-stat-value  { color:var(--primary-blue); }
.air-stat-green .air-stat-value { color:var(--success); }
.air-stat-gray .air-stat-value  { color:var(--text-muted); }
.air-stat-orange .air-stat-value{ color:var(--warning); }

/* Info card */
.air-info-card { background:var(--primary-subtle); border:1px solid var(--border-primary); border-radius:var(--radius-md); padding:1rem 1.25rem; margin-bottom:1.5rem; }
.air-info-header { display:flex; align-items:center; gap:0.5rem; color:var(--primary-blue); font-weight:600; margin-bottom:0.5rem; }
.air-info-text { font-size:0.875rem; color:var(--text-secondary); line-height:1.6; }

/* Deduction badge */
.deduction-badge { display:inline-block; padding:0.25rem 0.6rem; border-radius:var(--radius-full); font-size:0.8rem; font-weight:600; }
.deduction-low  { background:var(--success-subtle); color:var(--success); }
.deduction-mid  { background:var(--warning-subtle); color:var(--warning); }
.deduction-high { background:var(--danger-subtle);  color:var(--danger); }

/* Toggle switch */
.toggle-btn { position:relative; width:42px; height:24px; border-radius:12px; border:none; cursor:pointer; transition:background var(--transition-fast); }
.toggle-on  { background:var(--primary-blue); }
.toggle-off { background:var(--bg-elevated); border:1px solid var(--border-default); }
.toggle-knob { position:absolute; top:3px; width:18px; height:18px; border-radius:50%; background:#fff; transition:left var(--transition-fast); }
.toggle-on  .toggle-knob { left:21px; }
.toggle-off .toggle-knob { left:3px; }

/* Inactive row */
.row-inactive { opacity:0.55; }
.rule-name { font-weight:500; }

/* Preview card */
.air-preview-card { background:var(--bg-elevated); border:1px solid var(--border-default); border-radius:var(--radius-md); padding:0.875rem 1rem; margin-top:-0.5rem; }
.air-preview-title { display:flex; align-items:center; gap:0.35rem; font-size:0.8rem; font-weight:600; color:var(--text-secondary); margin-bottom:0.35rem; }
.air-preview-text { font-size:0.875rem; color:var(--text-light); }

/* Toggle label */
.toggle-label { display:flex; align-items:center; gap:0.5rem; cursor:pointer; }
.toggle-label input[type=checkbox] { width:16px; height:16px; accent-color:var(--primary-blue); cursor:pointer; }
.toggle-label-text { font-size:0.9rem; font-weight:500; }
</style>

<script>
// ============================================================
// MODAL: THÊM QUY TẮC
// ============================================================
function openCreateModal() {
    document.getElementById('modal-title').textContent   = 'Thêm quy tắc mới';
    document.getElementById('form-action').value         = 'create';
    document.getElementById('form-rule-id').value        = '';
    document.getElementById('condition_name').value      = '';
    document.getElementById('deduction_percent').value   = '';
    document.getElementById('is_active').checked         = true;
    document.getElementById('submit-btn').innerHTML      = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Thêm quy tắc';
    document.getElementById('preview-card').style.display = 'none';
    document.getElementById('rule-modal').classList.add('visible');
    setTimeout(() => document.getElementById('condition_name').focus(), 100);
}

// ============================================================
// MODAL: CHỈNH SỬA QUY TẮC
// ============================================================
function openEditModal(rule) {
    document.getElementById('modal-title').textContent          = 'Chỉnh sửa quy tắc';
    document.getElementById('form-action').value                = 'update';
    document.getElementById('form-rule-id').value               = rule.rule_id;
    document.getElementById('condition_name').value             = rule.condition_name;
    document.getElementById('deduction_percent').value          = rule.deduction_percent;
    document.getElementById('is_active').checked                = rule.is_active == 1;
    document.getElementById('submit-btn').innerHTML             = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Lưu thay đổi';
    updatePreview(parseFloat(rule.deduction_percent));
    document.getElementById('rule-modal').classList.add('visible');
}

function closeRuleModal(e) {
    if (e && e.target !== document.getElementById('rule-modal')) return;
    document.getElementById('rule-modal').classList.remove('visible');
}

// ============================================================
// MODAL: XÓA
// ============================================================
function confirmDelete(ruleId, ruleName) {
    document.getElementById('delete-rule-name').textContent = ruleName;
    document.getElementById('delete-rule-id').value         = ruleId;
    document.getElementById('delete-modal').classList.add('visible');
}

function closeDeleteModal(e) {
    if (e && e.target !== document.getElementById('delete-modal')) return;
    document.getElementById('delete-modal').classList.remove('visible');
}

// ============================================================
// PREVIEW TÍNH TOÁN
// ============================================================
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
if (alertEl) setTimeout(() => alertEl.style.opacity = '0', 4000);
</script>

<?php renderLayoutClose(); ?>