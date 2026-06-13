<?php
// ============================================================
// FILE: api/staff_dashboard_api.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../services/StaffDashboardService.php';

apiRequireLogin(); // Nhân viên đăng nhập là được, không cần Admin

$staffId = (int) $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // GET /api/staff_dashboard_api.php?action=get_metrics
    // ----------------------------------------------------------
    case 'get_metrics':
        try {
            $service  = new StaffDashboardService($pdo);
            $cards    = $service->getCardMetrics($staffId);
            $recent   = $service->getRecentActivities($staffId);

            json_ok([
                'cards'  => $cards,
                'recent' => $recent,
            ]);
        } catch (PDOException $e) {
            json_fail('Lỗi truy vấn: ' . $e->getMessage());
        }
        break;

    default:
        json_fail('Action không hợp lệ.', 404);
        break;
}