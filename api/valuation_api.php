<?php

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────
require_once __DIR__ . '/../config/db_connect.php';     // $pdo
require_once __DIR__ . '/../config/constants.php';       // Constants
require_once __DIR__ . '/../config/ai_module.php';       // getAISuggestedPrice()
require_once __DIR__ . '/../includes/auth.php';          // isLoggedIn(), isAdmin(), v.v.
require_once __DIR__ . '/../api/_helpers.php';           // json_ok(), json_err(), get_action(), ...
require_once __DIR__ . '/../services/ValuationService.php';

// ── Khởi tạo service ───────────────────────────────────────
$svc = new ValuationService($pdo);


// ══════════════════════════════════════════════════════════════
// ROUTER — Switch theo ?action=
// ══════════════════════════════════════════════════════════════
$action = get_action();

switch ($action) {

    // ──────────────────────────────────────────────────────────
    // GET brands
    // Ai được gọi: Staff + Admin (đã đăng nhập)
    // Trả về: danh sách hãng để render dropdown
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_auth() → $svc->getBrands() → json_ok($data)
    // ──────────────────────────────────────────────────────────
    case 'brands':
        // TODO: require_auth()
        // TODO: $brands = $svc->getBrands();
        // TODO: json_ok($brands);
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // GET models?brand_id=N
    // Ai được gọi: Staff + Admin (đã đăng nhập)
    // Trả về: danh sách model của hãng
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_auth()
    //   $brandId = (int)$_GET['brand_id'] → validate > 0
    //   $models = $svc->getModelsByBrand($brandId)
    //   json_ok($models)
    // ──────────────────────────────────────────────────────────
    case 'models':
        // TODO: require_auth()
        // TODO: $brandId = (int)($_GET['brand_id'] ?? 0);
        // TODO: if (!$brandId) json_err('Thiếu brand_id.');
        // TODO: $models = $svc->getModelsByBrand($brandId);
        // TODO: json_ok($models);
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // GET rules
    // Ai được gọi: Staff (để render checklist tình trạng vật lý)
    // Trả về: danh sách quy tắc AI đang bật
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_role('Staff')
    //   $rules = $svc->getActiveRules()
    //   json_ok($rules)
    // ──────────────────────────────────────────────────────────
    case 'rules':
        // TODO: require_role('Staff')
        // TODO: $rules = $svc->getActiveRules();
        // TODO: json_ok($rules);
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // POST valuate
    // Ai được gọi: Staff (UC11/UC12)
    // Body: model_id, battery_health, rule_ids[] (optional)
    // Trả về: session_id, price, price_formatted, reasoning, ...
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_method('POST')
    //   require_role('Staff')
    //   $modelId       = post_int('model_id')     → validate > 0
    //   $batteryHealth = post_int('battery_health', 100)
    //   $ruleIds       = post_int_array('rule_ids')
    //   try {
    //     $result = $svc->valuate($_SESSION['user_id'], $modelId, $batteryHealth, $ruleIds)
    //     json_ok($result, 'Định giá thành công')
    //   } catch (InvalidArgumentException $e) { json_err($e->getMessage()) }
    //     catch (RuntimeException $e)         { json_err($e->getMessage(), 500) }
    // ──────────────────────────────────────────────────────────
    case 'valuate':
        // TODO: require_method('POST')
        // TODO: require_role('Staff')
        // TODO: parse + validate input
        // TODO: call $svc->valuate(...)
        // TODO: json_ok($result)
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // POST confirm_purchase
    // Ai được gọi: Staff (UC13)
    // Body: session_id, imei, customer_name, customer_phone
    // Trả về: { imei, customer_id, session_id }
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_method('POST')
    //   require_role('Staff')
    //   $sessionId     = post_int('session_id')    → validate > 0
    //   $imei          = post_str('imei')           → is_valid_imei()
    //   $customerName  = post_str('customer_name')  → not empty
    //   $customerPhone = post_str('customer_phone') → is_valid_vn_phone()
    //   try {
    //     $result = $svc->confirmPurchase($_SESSION['user_id'], $sessionId, $imei, ...)
    //     json_ok($result, 'Thu mua thành công! Thiết bị đã nhập kho.')
    //   } catch (...) { json_err(...) }
    // ──────────────────────────────────────────────────────────
    case 'confirm_purchase':
        // TODO: require_method('POST')
        // TODO: require_role('Staff')
        // TODO: parse + validate input
        // TODO: call $svc->confirmPurchase(...)
        // TODO: json_ok($result, '...')
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // POST decline
    // Ai được gọi: Staff
    // Body: session_id
    // Trả về: { session_id, new_status }
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_method('POST')
    //   require_role('Staff')
    //   $sessionId = post_int('session_id') → validate > 0
    //   $result = $svc->declineSession($_SESSION['user_id'], $sessionId)
    //   json_ok($result, 'Đã ghi nhận từ chối.')
    // ──────────────────────────────────────────────────────────
    case 'decline':
        // TODO: require_method('POST')
        // TODO: require_role('Staff')
        // TODO: $sessionId = post_int('session_id');
        // TODO: if (!$sessionId) json_err('Thiếu session_id.');
        // TODO: $result = $svc->declineSession($_SESSION['user_id'], $sessionId);
        // TODO: json_ok($result, 'Đã ghi nhận từ chối.');
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // GET history
    // Ai được gọi: Staff (xem lịch sử CỦA MÌNH, UC14)
    // Params: page, per_page, status, q
    // Trả về: { sessions, total, stats }
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_role('Staff')
    //   $page    = (int)($_GET['page']     ?? 1)
    //   $perPage = (int)($_GET['per_page'] ?? 15)
    //   $status  = $_GET['status'] ?? ''
    //   $search  = trim($_GET['q'] ?? '')
    //   $result  = $svc->getHistory($_SESSION['user_id'], $page, $perPage, $status, $search)
    //   json_ok($result)
    // ──────────────────────────────────────────────────────────
    case 'history':
        // TODO: require_role('Staff')
        // TODO: parse pagination params
        // TODO: $result = $svc->getHistory($_SESSION['user_id'], ...);
        // TODO: json_ok($result);
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // GET all_sessions  (Admin only)
    // Ai được gọi: Admin (nhật ký định giá toàn hệ thống)
    // Params: page, per_page, status, q, staff_id, date_from, date_to
    // Trả về: { sessions, total, stats }
    //
    // Logic (CHƯA IMPLEMENT):
    //   require_admin()
    //   parse tất cả filter params
    //   $result = $svc->getAllSessions(...)
    //   json_ok($result)
    // ──────────────────────────────────────────────────────────
    case 'all_sessions':
        // TODO: require_admin()
        // TODO: parse pagination + filter params
        // TODO: $result = $svc->getAllSessions(...);
        // TODO: json_ok($result);
        json_err('Not implemented yet.', 501);


    // ──────────────────────────────────────────────────────────
    // DEFAULT — action không hợp lệ
    // ──────────────────────────────────────────────────────────
    default:
        json_err("Action '{$action}' không hợp lệ.", 400);
}