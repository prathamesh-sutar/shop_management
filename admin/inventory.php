<?php
/**
 * Inventory Management
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db        = getDB();
$isAdmin   = isAdmin();
$pageTitle = 'Inventory';

/* ---------- Handle stock adjustment (admin only) ---------- */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    $pid    = (int)($_POST['product_id']  ?? 0);
    $change = (int)($_POST['change_qty']  ?? 0);
    $reason = trim($_POST['reason']       ?? 'Manual adjustment');
    $uid    = (int)$_SESSION['user_id'];

    if ($pid > 0 && $change !== 0) {
        $db->begin_transaction();
        try {
            $uStmt = $db->prepare(
                "UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?"
            );
            $uStmt->bind_param('ii', $change, $pid);
            $uStmt->execute();
            $uStmt->close();

            $lStmt = $db->prepare(
                "INSERT INTO inventory_logs (product_id, change_qty, reason, user_id) VALUES (?,?,?,?)"
            );
            $lStmt->bind_param('iisi', $pid, $change, $reason, $uid);
            $lStmt->execute();
            $lStmt->close();

            $db->commit();
            setFlash('success', 'Stock adjusted successfully.');
        } catch (Throwable $e) {
            $db->rollback();
            setFlash('danger', 'Failed to adjust stock.');
        }
    }
    header('Location: inventory.php');
    exit;
}

/* ---------- Fetch products ---------- */
$filter  = $_GET['filter']  ?? '';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= ' AND (product_name LIKE ? OR category LIKE ?)';
    $like     = "%$search%";
    $params   = [$like, $like];
    $types   .= 'ss';
}
if ($filter === 'low') {
    $where .= ' AND stock_quantity <= ' . (int)LOW_STOCK_THRESHOLD;
} elseif ($filter === 'out') {
    $where .= ' AND stock_quantity = 0';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM products $where");
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows  = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = (int)ceil($totalRows / $perPage);

$dataParams   = $params;
$dataTypes    = $types . 'ii';
$dataParams[] = $perPage;
$dataParams[] = $offset;

$dataStmt = $db->prepare("
    SELECT product_id, product_name, brand, category, price, stock_quantity, supplier
    FROM products $where
    ORDER BY stock_quantity ASC, product_name ASC
    LIMIT ? OFFSET ?
");
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$products = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

/* ---------- Recent inventory log ---------- */
$logs = [];
$res  = $db->query("
    SELECT il.*, p.product_name, u.name AS user_name
    FROM inventory_logs il
    JOIN products p ON p.product_id = il.product_id
    LEFT JOIN users u ON u.user_id = il.user_id
    ORDER BY il.created_at DESC
    LIMIT 20
");
while ($r = $res->fetch_assoc()) $logs[] = $r;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-info"></i>Inventory</h4>
</div>

<!-- Summary cards -->
<?php
$res       = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0");
$outOfStock = (int)$res->fetch_row()[0];
$res       = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity > 0 AND stock_quantity <= " . (int)LOW_STOCK_THRESHOLD);
$lowStock  = (int)$res->fetch_row()[0];
$res       = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity > " . (int)LOW_STOCK_THRESHOLD);
$inStock   = (int)$res->fetch_row()[0];
?>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card text-center border-0" style="background:linear-gradient(135deg,#198754,#146c43)">
            <div class="card-body text-white">
                <div class="fs-2 fw-bold"><?= $inStock ?></div>
                <div class="small">In Stock</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0" style="background:linear-gradient(135deg,#ffc107,#e0a800)">
            <div class="card-body text-dark">
                <div class="fs-2 fw-bold"><?= $lowStock ?></div>
                <div class="small">Low Stock (≤ <?= LOW_STOCK_THRESHOLD ?>)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <div class="card-body text-white">
                <div class="fs-2 fw-bold"><?= $outOfStock ?></div>
                <div class="small">Out of Stock</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Product name or category" value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-sm-3">
                <select name="filter" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="low"  <?= $filter==='low' ?'selected':'' ?>>Low Stock</option>
                    <option value="out"  <?= $filter==='out' ?'selected':'' ?>>Out of Stock</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="inventory.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Products Stock Table -->
<div class="card mb-4">
    <div class="card-header fw-bold"><i class="bi bi-table me-1"></i> Stock Levels</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Product</th><th>Brand</th><th>Category</th>
                        <th class="text-end">Price</th><th class="text-center">Stock</th><th>Supplier</th>
                        <?php if ($isAdmin): ?><th class="text-center">Adjust</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No products.</td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr class="<?= $p['stock_quantity'] <= LOW_STOCK_THRESHOLD ? 'low-stock-row' : '' ?>">
                        <td><?= $p['product_id'] ?></td>
                        <td><strong><?= e($p['product_name']) ?></strong></td>
                        <td><?= e($p['brand'] ?? '–') ?></td>
                        <td><span class="badge bg-secondary"><?= e($p['category']) ?></span></td>
                        <td class="text-end">₹<?= number_format($p['price'],2) ?></td>
                        <td class="text-center">
                            <?php if ($p['stock_quantity'] === 0): ?>
                            <span class="badge bg-danger">Out</span>
                            <?php elseif ($p['stock_quantity'] <= LOW_STOCK_THRESHOLD): ?>
                            <span class="badge bg-warning text-dark"><?= $p['stock_quantity'] ?></span>
                            <?php else: ?>
                            <span class="badge bg-success"><?= $p['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['supplier'] ?? '–') ?></td>
                        <?php if ($isAdmin): ?>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="openAdjust(<?= $p['product_id'] ?>, '<?= e(addslashes($p['product_name'])) ?>', <?= $p['stock_quantity'] ?>)">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= min($offset+1,$totalRows) ?>–<?= min($offset+$perPage,$totalRows) ?> of <?= $totalRows ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Inventory Log -->
<div class="card">
    <div class="card-header fw-bold"><i class="bi bi-clock-history me-1"></i> Recent Inventory Changes</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr><th>Product</th><th>Change</th><th>Reason</th><th>By</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No log entries.</td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['product_name']) ?></td>
                        <td>
                            <?php if ($log['change_qty'] > 0): ?>
                            <span class="text-success fw-bold">+<?= $log['change_qty'] ?></span>
                            <?php else: ?>
                            <span class="text-danger fw-bold"><?= $log['change_qty'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($log['reason'] ?? '–') ?></td>
                        <td><?= e($log['user_name'] ?? '–') ?></td>
                        <td><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action"     value="adjust">
                <input type="hidden" name="product_id" id="adjPid">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="adjProductName" class="fw-bold text-info"></p>
                    <p>Current stock: <strong id="adjCurrentStock"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Change Quantity</label>
                        <input type="number" name="change_qty" class="form-control"
                               placeholder="+10 to add, -5 to deduct" required>
                        <div class="form-text">Use positive to add stock, negative to deduct.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control"
                               placeholder="Stock received, damaged goods, etc." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-dark fw-bold">
                        <i class="bi bi-save me-1"></i> Adjust
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openAdjust(pid, name, stock) {
    document.getElementById('adjPid').value          = pid;
    document.getElementById('adjProductName').textContent  = name;
    document.getElementById('adjCurrentStock').textContent = stock;
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
