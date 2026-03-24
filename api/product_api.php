<?php
/**
 * Product API
 * Handles AJAX requests for product data (search for sales form, etc.)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'search':
        $term = trim($_GET['q'] ?? '');
        if ($term === '') {
            echo json_encode(['products' => []]);
            exit;
        }
        $like = "%$term%";
        $stmt = $db->prepare("
            SELECT product_id, product_name, brand, category, price, stock_quantity
            FROM   products
            WHERE  (product_name LIKE ? OR brand LIKE ? OR category LIKE ?)
              AND  stock_quantity > 0
            ORDER  BY product_name
            LIMIT  20
        ");
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['products' => $rows]);
        break;

    case 'get':
        $pid  = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($prod) {
            echo json_encode(['success' => true, 'product' => $prod]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
