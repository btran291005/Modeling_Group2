<?php

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// api/master_data_api.php
//
// Endpoint quản lý Dữ liệu nền: Hãng (brands) & Dòng máy (device_models).
// Admin only – mọi action đều gọi apiRequireAdmin().
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../services/master_data_service.php';

apiRequireAdmin();

$svc    = new MasterDataService($pdo);
$action = get_action();

switch ($action) {

    // ══════════════════════════════════════════════════════════
    // case 'get_brands'
    // GET /api/master_data_api.php?action=get_brands
    // Trả về : [{brand_id, brand_name, model_count}, ...]
    // ══════════════════════════════════════════════════════════
    case 'get_brands':
        json_ok($svc->getBrands());


    // ══════════════════════════════════════════════════════════
    // case 'get_models'
    // GET /api/master_data_api.php?action=get_models&brand_id=N (optional)
    // Trả về : [{model_id, model_name, brand_id, brand_name, ram_gb, rom_gb, base_price}, ...]
    // ══════════════════════════════════════════════════════════
    case 'get_models':
        $brandId = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : null;
        json_ok($svc->getModels($brandId));


    // ══════════════════════════════════════════════════════════
    // case 'add_brand'
    // POST /api/master_data_api.php?action=add_brand
    // Body JSON: { name }
    // ══════════════════════════════════════════════════════════
    case 'add_brand':
        require_method('POST');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim((string) ($body['name'] ?? ''));

        try {
            $brandId = $svc->addBrand($name);
            json_ok(['brand_id' => $brandId], 'Đã thêm hãng mới thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 409);
        }


    // ══════════════════════════════════════════════════════════
    // case 'add_model'
    // POST /api/master_data_api.php?action=add_model
    // Body JSON: { brand_id, name, base_price, ram_gb?, rom_gb? }
    // ══════════════════════════════════════════════════════════
    case 'add_model':
        require_method('POST');

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $brandId   = (int) ($body['brand_id']   ?? 0);
        $name      = trim((string) ($body['name'] ?? ''));
        $basePrice = (int) ($body['base_price'] ?? 0);
        $ramGb     = (int) ($body['ram_gb'] ?? 0);
        $romGb     = (int) ($body['rom_gb'] ?? 0);

        try {
            $modelId = $svc->addModel($brandId, $name, $basePrice, $ramGb, $romGb);
            json_ok(['model_id' => $modelId], 'Đã thêm dòng máy mới thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 409);
        }


    // ══════════════════════════════════════════════════════════
    // case 'update_price'
    // POST /api/master_data_api.php?action=update_price
    // Body JSON: { model_id, base_price }
    // ══════════════════════════════════════════════════════════
    case 'update_price':
        require_method('POST');

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $modelId   = (int) ($body['model_id']   ?? 0);
        $basePrice = (int) ($body['base_price'] ?? 0);

        try {
            $svc->updateBasePrice($modelId, $basePrice);
            json_ok(null, 'Đã cập nhật giá sàn thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 404);
        }


    // ══════════════════════════════════════════════════════════
    // default – action không tồn tại
    // ══════════════════════════════════════════════════════════
    default:
        $safeAction = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safeAction}' không hợp lệ.", 400);
}