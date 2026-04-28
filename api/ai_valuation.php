<?php
/**
 * api/ai_valuation.php
 * Endpoint AJAX: Nhận dữ liệu từ Staff → Gọi AI → Trả JSON giá đề xuất
 *
 * Method : POST
 * Auth   : Session Staff only
 * Input  : model_id (int), battery_health (int), rule_ids[] (int[])
 * Output : JSON { success, price, price_formatted, reasoning, session_id }
 */

declare(strict_types=1);

// ---- Bootstrap ----
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/ai_module.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Chỉ nhận POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method Not Allowed']));
}

// ---- Kiểm tra quyền Staff ----
requireRole('Staff');

// ---- Lấy & validate input ----
$modelId       = (int) ($_POST['model_id']       ?? 0);
$batteryHealth = (int) ($_POST['battery_health'] ?? 100);
$ruleIds       = array_map('intval', (array) ($_POST['rule_ids'] ?? []));

if ($modelId <= 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Vui lòng chọn mẫu thiết bị.']));
}

if ($batteryHealth < 0 || $batteryHealth > 100) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Tỷ lệ pin không hợp lệ (0-100).']));
}

// ---- Lấy thông tin thiết bị từ DB ----
$stmtDevice = $pdo->prepare("
    SELECT dm.model_id, dm.model_name, dm.ram_gb, dm.rom_gb, dm.base_price,
           b.brand_name
    FROM device_models dm
    JOIN brands b ON dm.brand_id = b.brand_id
    WHERE dm.model_id = :mid
");
$stmtDevice->execute([':mid' => $modelId]);
$device = $stmtDevice->fetch();

if (!$device) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Không tìm thấy thiết bị.']));
}

// ---- Lấy quy tắc AI đang hoạt động (lọc theo rule_ids nếu có) ----
if (!empty($ruleIds)) {
    $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
    $stmtRules = $pdo->prepare("
        SELECT rule_id, condition_name, deduction_percent
        FROM ai_pricing_rules
        WHERE is_active = 1 AND rule_id IN ({$placeholders})
    ");
    $stmtRules->execute($ruleIds);
} else {
    $stmtRules = $pdo->query("
        SELECT rule_id, condition_name, deduction_percent
        FROM ai_pricing_rules
        WHERE is_active = 1
    ");
}
$activeRules = $stmtRules->fetchAll();

// ---- Chuẩn bị dữ liệu gọi AI ----
$deviceInfo = [
    'brand_name'     => $device['brand_name'],
    'model_name'     => $device['model_name'],
    'ram_gb'         => $device['ram_gb'],
    'rom_gb'         => $device['rom_gb'],
    'base_price'     => $device['base_price'],
    'battery_health' => $batteryHealth,
];

// ---- Gọi AI Module ----
$aiResult = getAISuggestedPrice($deviceInfo, $activeRules);

if (!$aiResult['success']) {
    http_response_code(503);
    exit(json_encode([
        'success' => false,
        'message' => $aiResult['error'] ?? 'AI không khả dụng.',
    ]));
}

$suggestedPrice = $aiResult['price'];

// ---- Lưu phiên định giá vào DB ----
try {
    $pdo->beginTransaction();

    // Insert valuation_session (Pending)
    $stmtSession = $pdo->prepare("
        INSERT INTO valuation_sessions
            (user_id, model_id, customer_id, battery_health, ai_suggested_price, final_status)
        VALUES
            (:uid, :mid, NULL, :bat, :price, 'Pending')
    ");
    $stmtSession->execute([
        ':uid'   => $_SESSION['user_id'],
        ':mid'   => $modelId,
        ':bat'   => $batteryHealth,
        ':price' => $suggestedPrice,
    ]);
    $sessionId = (int) $pdo->lastInsertId();

    // Lưu quy tắc đã áp dụng vào bảng junction
    if (!empty($activeRules)) {
        $stmtJunction = $pdo->prepare("
            INSERT IGNORE INTO session_rules (session_id, rule_id) VALUES (:sid, :rid)
        ");
        foreach ($activeRules as $rule) {
            $stmtJunction->execute([':sid' => $sessionId, ':rid' => $rule['rule_id']]);
        }
    }

    // Ghi audit log
    $stmtLog = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, target_table)
        VALUES (:uid, :action, 'valuation_sessions')
    ");
    $stmtLog->execute([
        ':uid'    => $_SESSION['user_id'],
        ':action' => "Định giá thiết bị [{$device['brand_name']} {$device['model_name']}] - Session #{$sessionId} - Giá đề xuất: " . number_format($suggestedPrice, 0, ',', '.') . " VNĐ",
    ]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[AI VALUATION] DB Error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Lỗi lưu dữ liệu. Vui lòng thử lại.']));
}

// ---- Trả về kết quả ----
exit(json_encode([
    'success'         => true,
    'session_id'      => $sessionId,
    'price'           => $suggestedPrice,
    'price_formatted' => number_format($suggestedPrice, 0, ',', '.') . ' ₫',
    'reasoning'       => $aiResult['reasoning'],
    'device_name'     => $device['brand_name'] . ' ' . $device['model_name'],
    'battery_health'  => $batteryHealth,
    'rules_applied'   => count($activeRules),
]));