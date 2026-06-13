<?php
// ============================================================
// FILE: api/ai_rule_api.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/ai_rule_service.php';

apiRequireAdmin();

$service = new AiRuleService($pdo);
$action  = $_GET['action'] ?? '';

// Đọc JSON body một lần, dùng chung cho các action cần body
$body = [];
$raw  = file_get_contents('php://input');
if (!empty($raw)) {
    $body = json_decode($raw, true) ?? [];
}

switch ($action) {

    // ── LIST ──────────────────────────────────────────────────
    case 'list':
        $rules = $service->getAllRules();
        json_ok($rules);
        break;

    // ── CREATE ────────────────────────────────────────────────
    case 'create':
        $name     = trim($body['name']    ?? '');
        $pct      = (float) ($body['pct'] ?? 0);
        $isActive = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;

        if ($name === '') {
            json_err('Tên quy tắc không được để trống.');
            break;
        }
        if ($pct < 0 || $pct > 100) {
            json_err('Phần trăm khấu trừ phải từ 0 đến 100.');
            break;
        }

        $ok = $service->createRule($name, $pct, $isActive);
        $ok
            ? json_ok(null, 'Thêm quy tắc thành công.')
            : json_err('Thêm quy tắc thất bại.');
        break;

    // ── UPDATE ────────────────────────────────────────────────
    case 'update':
        $id       = (int)   ($body['id']  ?? 0);
        $name     = trim($body['name']    ?? '');
        $pct      = (float) ($body['pct'] ?? 0);
        $isActive = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;

        if ($id <= 0)         { json_err('ID không hợp lệ.');                              break; }
        if ($name === '')     { json_err('Tên quy tắc không được để trống.');              break; }
        if ($pct < 0 || $pct > 100) { json_err('Phần trăm khấu trừ phải từ 0 đến 100.'); break; }

        $ok = $service->updateRule($id, $name, $pct, $isActive);
        $ok
            ? json_ok(null, 'Cập nhật quy tắc thành công.')
            : json_err('Cập nhật quy tắc thất bại.');
        break;

    // ── TOGGLE ────────────────────────────────────────────────
    case 'toggle':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) { json_err('ID không hợp lệ.'); break; }

        $ok = $service->toggleRule($id);
        $ok
            ? json_ok(null, 'Đã đổi trạng thái quy tắc.')
            : json_err('Đổi trạng thái thất bại.');
        break;

    // ── DELETE ────────────────────────────────────────────────
    case 'delete':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) { json_err('ID không hợp lệ.'); break; }

        try {
            $ok = $service->deleteRule($id);
            $ok
                ? json_ok(null, 'Đã xóa quy tắc.')
                : json_err('Xóa quy tắc thất bại.');
        } catch (RuntimeException $e) {
            json_err($e->getMessage());
        }
        break;

    // ── DEFAULT ───────────────────────────────────────────────
    default:
        http_response_code(400);
        json_err('Action không hợp lệ.');
        break;
}