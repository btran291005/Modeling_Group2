<?php
// ============================================================
// FILE: api/inventory_api.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../services/InventoryService.php';

requireLogin(); // Staff + Admin đều xem được (Staff chỉ đổi status, không xóa)

$svc    = new InventoryService($pdo);
$action = get_action();

switch ($action) {

    // ══════════════════════════════════════════════════════════
    // case 'list'
    // GET /api/inventory_api.php?action=list
    // Quyền  : Đã đăng nhập
    // Trả về : [{id, imei, status, model_name, brand_name, ...}, ...]
    // ══════════════════════════════════════════════════════════
    case 'list':
        $items = $svc->getStaffInventory();
        json_ok($items);


    // ══════════════════════════════════════════════════════════
    // case 'update_status'
    // POST /api/inventory_api.php?action=update_status
    // Body   : { "id": <int>, "status": "Stored" }
    // Quyền  : Đã đăng nhập (Staff được đổi trạng thái, không xóa)
    // Trả về : { success info }
    // ══════════════════════════════════════════════════════════
    case 'update_status':
        require_method('POST');

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = (int) ($body['id']     ?? 0);
        $status = trim((string) ($body['status'] ?? ''));

        if ($id <= 0) {
            json_err('Thiếu hoặc sai ID thiết bị.');
        }

        $allowedStatuses = ['Pending', 'Stored', 'Refurbishing', 'Sold'];
        if (!in_array($status, $allowedStatuses, true)) {
            json_err('Trạng thái không hợp lệ.');
        }

        $ok = $svc->updateStatus($id, $status);

        if (!$ok) {
            json_err('Không thể cập nhật trạng thái. Vui lòng kiểm tra lại ID.', 404);
        }

        json_ok(['id' => $id, 'status' => $status], 'Cập nhật trạng thái thành công.');


    // ══════════════════════════════════════════════════════════
    // default — action không tồn tại
    // ══════════════════════════════════════════════════════════
    default:
        $safeAction = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safeAction}' không hợp lệ.", 400);
}