<?php
/**
 * Sales API
 * Returns recent sales summary (used by dashboard widget etc.)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'summary':
        $stmt = $db->prepare("
            SELECT
                COUNT(*)                                  AS total_sales,
                COALESCE(SUM(grand_total), 0)             AS total_revenue,
                COALESCE(SUM(CASE WHEN DATE(sale_date)=CURDATE() THEN grand_total END), 0) AS today_revenue,
                COALESCE(COUNT(CASE WHEN DATE(sale_date)=CURDATE() THEN 1 END), 0) AS today_orders
            FROM sales
        ");
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $row]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
