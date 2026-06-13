<?php

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// api/valuation_api.php
//
// Endpoint định giá & thu mua thiết bị.
// Sử dụng switch($_GET['action']) để phân luồng.
// Mọi response đều qua json_ok() / json_err() và exit ngay.
// ══════════════════════════════════════════════════════════════

// ── Bootstrap ─────────────────────────────────────────────────
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/ai_module.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../services/ValuationService.php';

// ── Khởi tạo Service ──────────────────────────────────────────
$svc = new ValuationService($pdo);

// ── Router ────────────────────────────────────────────────────
$action = get_action();

switch ($action) {

    // ══════════════════════════════════════════════════════════
    // case 'brands'
    // GET /api/valuation_api.php?action=brands
    // Quyền  : Đã đăng nhập (Staff + Admin)
    // Trả về : [{brand_id, brand_name}, ...]
    // ══════════════════════════════════════════════════════════
    case 'brands':
        apiRequireLogin();

        $brands = $svc->getBrands();
        json_ok($brands);


    // ══════════════════════════════════════════════════════════
    // case 'models'
    // GET /api/valuation_api.php?action=models&brand_id=N
    // Quyền  : Đã đăng nhập
    // Trả về : [{model_id, model_name, ram_gb, rom_gb, base_price}, ...]
    // ══════════════════════════════════════════════════════════
    case 'models':
        apiRequireLogin();

        $brandId = (int) ($_GET['brand_id'] ?? 0);
        if ($brandId <= 0) {
            json_err('Thiếu hoặc sai brand_id.');
        }

        try {
            $models = $svc->getModelsByBrand($brandId);
            json_ok($models);
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage());
        }


    // ══════════════════════════════════════════════════════════
    // case 'rules'
    // GET /api/valuation_api.php?action=rules
    // Quyền  : Staff (checklist tình trạng vật lý khi định giá)
    // Trả về : [{rule_id, condition_name, deduction_percent}, ...]
    // ══════════════════════════════════════════════════════════
    case 'rules':
        apiRequireLogin();

        $rules = $svc->getActiveRules();
        json_ok($rules);


    // ══════════════════════════════════════════════════════════
    // case 'valuate'
    // POST /api/valuation_api.php?action=valuate
    // Quyền  : Staff
    // Body   : model_id, battery_health, rule_ids[] (optional)
    // Trả về : { session_id, price, price_formatted, reasoning,
    //            device_name, brand_name, battery_health,
    //            rules_applied, fallback }
    // ══════════════════════════════════════════════════════════
    case 'valuate':
        require_method('POST');
        apiRequireLogin();

        // ── Parse & Validate input ────────────────────────────
        $modelId       = post_int('model_id');
        $batteryHealth = post_int('battery_health', 100);

        // JS gửi FormData với key rule_ids[] → PHP nhận $_POST['rule_ids']
        $ruleIds = array_map('intval', $_POST['rule_ids'] ?? []);

        if ($modelId <= 0) {
            json_err('Thiếu model_id hoặc model_id không hợp lệ.');
        }
        if ($batteryHealth < 1 || $batteryHealth > 100) {
            json_err('battery_health phải nằm trong khoảng 1–100.');
        }

        // Session đúng chuẩn
        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        // ── Gọi Service ───────────────────────────────────────
        try {
            $result = $svc->valuate(
                $staffId,
                $modelId,
                $batteryHealth,
                $ruleIds
            );
            json_ok($result, 'Định giá thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 500);
        }


    // ══════════════════════════════════════════════════════════
    // case 'confirm_purchase'
    // POST /api/valuation_api.php?action=confirm_purchase
    // Quyền  : Staff
    // Body   : session_id, imei, customer_name, customer_phone
    // Trả về : { session_id, imei, customer_id, inventory_id }
    // ══════════════════════════════════════════════════════════
    case 'confirm_purchase':
        require_method('POST');
        apiRequireLogin();

        // ── Parse ─────────────────────────────────────────────
        $sessionId     = post_int('session_id');
        $imei          = post_str('imei');
        $customerName  = post_str('customer_name');
        $customerPhone = post_str('customer_phone');

        // ── Validate ──────────────────────────────────────────
        if ($sessionId <= 0) {
            json_err('Thiếu session_id.');
        }
        if (!is_valid_imei($imei)) {
            json_err('IMEI không hợp lệ (phải đúng 15 chữ số).');
        }
        if ($customerName === '') {
            json_err('Tên khách hàng không được để trống.');
        }
        if (!is_valid_vn_phone($customerPhone)) {
            json_err('Số điện thoại không hợp lệ (định dạng Việt Nam: 03x/05x/07x/08x/09x).');
        }

        // Session đúng chuẩn
        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        // ── Gọi Service ───────────────────────────────────────
        try {
            $result = $svc->confirmPurchase(
                $staffId,
                $sessionId,
                $imei,
                $customerName,
                $customerPhone
            );
            json_ok($result, 'Thu mua thành công! Thiết bị đã nhập kho.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'Lỗi') ? 500 : 409;
            json_err($e->getMessage(), $code);
        }


    // ══════════════════════════════════════════════════════════
    // case 'decline'
    // POST /api/valuation_api.php?action=decline
    // Quyền  : Staff
    // Body   : session_id
    // Trả về : { session_id, new_status }
    // ══════════════════════════════════════════════════════════
    case 'decline':
        require_method('POST');
        apiRequireLogin();

        $sessionId = post_int('session_id');
        if ($sessionId <= 0) {
            json_err('Thiếu hoặc sai session_id.');
        }

        // Session đúng chuẩn
        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        try {
            $result = $svc->declineSession(
                $staffId,
                $sessionId
            );
            json_ok($result, 'Đã ghi nhận từ chối.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 409);
        }


    // ══════════════════════════════════════════════════════════
    // case 'history'
    // GET /api/valuation_api.php?action=history
    // Quyền  : Staff (chỉ xem nhật ký định giá của chính mình)
    // Trả về : [{session_id, created_at, battery_health,
    //            ai_suggested_price, final_status, model_name,
    //            ram_gb, rom_gb, brand_name, imei, final_price,
    //            applied_rules}, ...]
    // ══════════════════════════════════════════════════════════
    case 'history':
        apiRequireLogin();

        // Session đúng chuẩn — key là 'user_id'
        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);

        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        $data = $svc->getStaffHistory($staffId);
        json_ok($data);


    // ══════════════════════════════════════════════════════════
    // case 'all_sessions'
    // GET /api/valuation_api.php?action=all_sessions
    //     &page=1&per_page=20&status=&q=&staff_id=0
    //     &date_from=2025-01-01&date_to=2025-12-31
    // Quyền  : Admin only
    // Trả về : { sessions, total, page, per_page, stats }
    // ══════════════════════════════════════════════════════════
    case 'all_sessions':
        apiRequireAdmin();

        $page     = max(1, (int) ($_GET['page']      ?? 1));
        $perPage  = max(1, (int) ($_GET['per_page']  ?? 20));
        $status   = trim($_GET['status']    ?? '');
        $search   = trim($_GET['q']         ?? '');
        $staffId  = (int) ($_GET['staff_id']  ?? 0);
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to']   ?? '');

        // Validate định dạng ngày nếu có
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if ($dateFrom !== '' && !preg_match($datePattern, $dateFrom)) {
            json_err('date_from không hợp lệ (định dạng YYYY-MM-DD).');
        }
        if ($dateTo !== '' && !preg_match($datePattern, $dateTo)) {
            json_err('date_to không hợp lệ (định dạng YYYY-MM-DD).');
        }

        $result = $svc->getAllSessions(
            $page,
            $perPage,
            $status,
            $search,
            $staffId,
            $dateFrom,
            $dateTo
        );
        json_ok($result);


    // ══════════════════════════════════════════════════════════
    // default — action không tồn tại
    // ══════════════════════════════════════════════════════════
    default:
        $safeAction = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safeAction}' không hợp lệ.", 400);
}