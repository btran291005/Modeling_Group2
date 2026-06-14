<?php

declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../services/valuation_service.php';

// ── Khởi tạo Service ──────────────────────────────────────────
$svc = new ValuationService($pdo);

// ── Router ────────────────────────────────────────────────────
$action = get_action();

switch ($action) {

    case 'brands':
        apiRequireLogin();
        $brands = $svc->getBrands();
        json_ok($brands);

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

    case 'rules':
        apiRequireLogin();
        $rules = $svc->getActiveRules();
        json_ok($rules);

    case 'valuate':
        require_method('POST');
        apiRequireLogin();

        // Chỉ load ai_module khi thực sự cần định giá
        $aiModulePath = __DIR__ . '/../config/ai_module.php';
        if (!file_exists($aiModulePath)) {
            json_err('Module AI chưa được cấu hình. Vui lòng liên hệ Admin.', 500);
        }
        require_once $aiModulePath;

        $modelId       = post_int('model_id');
        $batteryHealth = post_int('battery_health', 100);
        $ruleIds       = array_map('intval', $_POST['rule_ids'] ?? []);

        if ($modelId <= 0) {
            json_err('Thiếu model_id hoặc model_id không hợp lệ.');
        }
        if ($batteryHealth < 1 || $batteryHealth > 100) {
            json_err('battery_health phải nằm trong khoảng 1–100.');
        }

        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        try {
            $result = $svc->valuate($staffId, $modelId, $batteryHealth, $ruleIds);
            json_ok($result, 'Định giá thành công.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 500);
        }

    case 'confirm_purchase':
        require_method('POST');
        apiRequireLogin();

        $sessionId     = post_int('session_id');
        $imei          = post_str('imei');
        $customerName  = post_str('customer_name');
        $customerPhone = post_str('customer_phone');

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

        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        try {
            $result = $svc->confirmPurchase($staffId, $sessionId, $imei, $customerName, $customerPhone);
            json_ok($result, 'Thu mua thành công! Thiết bị đã nhập kho.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'Lỗi') ? 500 : 409;
            json_err($e->getMessage(), $code);
        }

    case 'decline':
        require_method('POST');
        apiRequireLogin();

        $sessionId = post_int('session_id');
        if ($sessionId <= 0) {
            json_err('Thiếu hoặc sai session_id.');
        }

        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        try {
            $result = $svc->declineSession($staffId, $sessionId);
            json_ok($result, 'Đã ghi nhận từ chối.');
        } catch (InvalidArgumentException $e) {
            json_err($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            json_err($e->getMessage(), 409);
        }

    case 'history':
        apiRequireLogin();

        $staffId = (int) ($_SESSION['user']['user_id'] ?? 0);
        if ($staffId <= 0) {
            json_err('Không xác định được tài khoản đang đăng nhập.', 401);
        }

        $data = $svc->getStaffHistory($staffId);
        json_ok($data);

    case 'all_sessions':
        apiRequireAdmin();

        $page     = max(1, (int) ($_GET['page']      ?? 1));
        $perPage  = max(1, (int) ($_GET['per_page']  ?? 20));
        $status   = trim($_GET['status']    ?? '');
        $search   = trim($_GET['q']         ?? '');
        $staffId  = (int) ($_GET['staff_id']  ?? 0);
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to']   ?? '');

        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if ($dateFrom !== '' && !preg_match($datePattern, $dateFrom)) {
            json_err('date_from không hợp lệ (định dạng YYYY-MM-DD).');
        }
        if ($dateTo !== '' && !preg_match($datePattern, $dateTo)) {
            json_err('date_to không hợp lệ (định dạng YYYY-MM-DD).');
        }

        $result = $svc->getAllSessions($page, $perPage, $status, $search, $staffId, $dateFrom, $dateTo);
        json_ok($result);

    default:
        $safeAction = htmlspecialchars($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        json_err("Action '{$safeAction}' không hợp lệ.", 400);
}