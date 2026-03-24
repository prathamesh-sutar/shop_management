<?php
/**
 * Inventory API
 * Returns low stock count (used by the navbar badge)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'low_stock_count':
        $res   = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= " . (int)LOW_STOCK_THRESHOLD);
        $count = (int)$res->fetch_row()[0];
        echo json_encode(['count' => $count]);
        break;

    case 'low_stock_list':
        $res  = $db->query("
            SELECT product_id, product_name, category, stock_quantity
            FROM   products
            WHERE  stock_quantity <= " . (int)LOW_STOCK_THRESHOLD . "
            ORDER  BY stock_quantity ASC
        ");
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'products' => $rows]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
