<?php

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// api/inventory_api.php
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../services/inventory_service.php';

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
    // case 'admin_list'
    // GET /api/inventory_api.php?action=admin_list
    // Quyền  : Admin only
    // Trả về : [{imei, status, received_at, session_id, battery_health,
    //            final_price, model_id, model_name, ram_gb, rom_gb,
    //            brand_id, brand_name, staff_id, staff_name,
    //            customer_id, customer_name, phone_number}, ...]
    // ══════════════════════════════════════════════════════════
    case 'admin_list':
        apiRequireAdmin();

        $items = $svc->getAdminInventory();
        json_ok($items);


    // ══════════════════════════════════════════════════════════
    // case 'delete'
    // POST /api/inventory_api.php?action=delete
    // Body   : { "imei": "<string>" }
    // Quyền  : Admin only
    // Trả về : { imei }
    // ══════════════════════════════════════════════════════════
    case 'delete':
        require_method('POST');
        apiRequireAdmin();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $imei = trim((string) ($body['imei'] ?? $body['id'] ?? ''));

        if ($imei === '') {
            json_err('Thiếu IMEI thiết bị cần xóa.');
        }

        try {
            $svc->deleteItem($imei);
            json_ok(['imei' => $imei], 'Đã xóa thiết bị khỏi kho thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 404);
        }


    // ══════════════════════════════════════════════════════════
    // case 'update_detail'
    // POST /api/inventory_api.php?action=update_detail
    // Body   : { "imei": "<string hiện tại>",
    //            "new_imei": "<string mới, optional>",
    //            "status": "Stored|Refurbishing|Sold, optional",
    //            "final_price": <number, optional> }
    // Quyền  : Admin only
    // Trả về : { imei }
    // ══════════════════════════════════════════════════════════
    case 'update_detail':
        require_method('POST');
        apiRequireAdmin();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $imei = trim((string) ($body['imei'] ?? ''));

        if ($imei === '') {
            json_err('Thiếu IMEI thiết bị cần sửa.');
        }

        $data = [
            'imei'        => isset($body['new_imei'])    ? $body['new_imei']    : null,
            'status'      => isset($body['status'])      ? $body['status']      : null,
            'final_price' => isset($body['final_price']) ? $body['final_price'] : null,
        ];

        try {
            $svc->updateItemDetail($imei, $data);
            json_ok(['imei' => $data['imei'] ?? $imei], 'Đã cập nhật thông tin thiết bị thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 409);
        }


    // ══════════════════════════════════════════════════════════
    // default — action không tồn tại
    // ══════════════════════════════════════════════════════════
    default:
        $safeAction = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safeAction}' không hợp lệ.", 400);
}