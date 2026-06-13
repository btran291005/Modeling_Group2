<?php
// ============================================================
// FILE: api/staff_dashboard_api.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/StaffDashboardService.php';

apiRequireLogin(); // Nhân viên đăng nhập là được, không cần Admin

$staffId = (int) ($_SESSION['user']['user_id'] ?? 0);

if ($staffId <= 0) {
    json_err('Không xác định được phiên đăng nhập.', 401);
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // GET /api/staff_dashboard_api.php?action=get_metrics
    // ----------------------------------------------------------
    case 'get_metrics':
        try {
            $service = new StaffDashboardService($pdo);
            $cards   = $service->getCardMetrics($staffId);
            $recent  = $service->getRecentActivities($staffId);

            json_ok([
                'cards'  => $cards,
                'recent' => $recent,
            ]);
        } catch (PDOException $e) {
            json_err('Lỗi truy vấn: ' . $e->getMessage());
        }
        break;

    default:
        json_err('Action không hợp lệ.', 404);
        break;
}