<?php
// ============================================================
// FILE: api/admin_dashboard_api.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/admin_dashboard_service.php';

apiRequireAdmin();

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // GET /api/admin_dashboard_api.php?action=get_metrics
    // Trả về cards + brands + recent activities trong 1 JSON
    // ----------------------------------------------------------
    case 'get_metrics':
        try {
            $service = new DashboardService($pdo);

            $cards  = $service->getCardMetrics();
            $brands = $service->getBrandDistribution();
            $recent = $service->getRecentActivities();

            json_ok([
                'cards'  => $cards,
                'brands' => $brands,
                'recent' => $recent,
            ]);
        } catch (PDOException $e) {
            json_fail('Lỗi truy vấn dữ liệu: ' . $e->getMessage());
        }
        break;

    default:
        json_fail('Action không hợp lệ.', 404);
        break;
}