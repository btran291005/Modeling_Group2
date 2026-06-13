<?php

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// api/inventory_api.php
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../services/InventoryService.php';

// Dùng apiRequireLogin() để trả JSON 401 thay vì redirect khi chưa đăng nhập
apiRequireLogin();

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
    // Body   : { "imei": "<string>", "status": "Stored" }
    // Quyền  : Đã đăng nhập (Staff được đổi trạng thái, không xóa)
    // Trả về : { success info }
    // ══════════════════════════════════════════════════════════
    case 'update_status':
        require_method('POST');

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $imei   = trim((string) ($body['imei']   ?? ''));
        $status = trim((string) ($body['status'] ?? ''));

        if ($imei === '') {
            json_err('Thiếu hoặc sai IMEI thiết bị.');
        }

        $allowedStatuses = ['Stored', 'Refurbishing', 'Sold'];
        if (!in_array($status, $allowedStatuses, true)) {
            json_err('Trạng thái không hợp lệ.');
        }

        $ok = $svc->updateStatus($imei, $status);

        if (!$ok) {
            json_err('Không thể cập nhật trạng thái. Vui lòng kiểm tra lại IMEI.', 404);
        }

        json_ok(['imei' => $imei, 'status' => $status], 'Cập nhật trạng thái thành công.');


    // ══════════════════════════════════════════════════════════
    // default — action không tồn tại
    // ══════════════════════════════════════════════════════════
    default:
        $safeAction = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safeAction}' không hợp lệ.", 400);
}