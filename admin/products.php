<?php
/**
 * Product Management
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db        = getDB();
$isAdmin   = isAdmin();
$pageTitle = 'Products';

/* ---------- Handle POST actions (admin only) ---------- */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = trim($_POST['product_name'] ?? '');
        $brand    = trim($_POST['brand']         ?? '');
        $category = $_POST['category']           ?? '';
        $price    = (float)($_POST['price']      ?? 0);
        $stock    = (int)($_POST['stock_quantity'] ?? 0);
        $supplier = trim($_POST['supplier']      ?? '');
        $desc     = trim($_POST['description']   ?? '');

        $validCategories = ['CCTV Camera','DVR','NVR','Hard Disk','Cable','Accessories'];

        if ($name === '' || !in_array($category, $validCategories, true) || $price < 0) {
            setFlash('danger', 'Invalid product data. Please check all fields.');
        } else {
            // Handle image upload
            $imageName = null;
            if (!empty($_FILES['product_image']['name'])) {
                $ext       = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                $allowed   = ['jpg','jpeg','png','webp'];
                if (in_array($ext, $allowed, true) && $_FILES['product_image']['size'] <= 2 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/../assets/images/products/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $imageName = 'prod_' . time() . '_' . random_int(100, 999) . '.' . $ext;
                    move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $imageName);
                } else {
                    setFlash('warning', 'Image not saved: invalid type or size > 2 MB.');
                }
            }

            if ($action === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO products
                        (product_name, brand, category, price, stock_quantity, supplier, description, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('sssdiiss', $name, $brand, $category, $price, $stock, $supplier, $desc, $imageName);
                if ($stmt->execute()) {
                    // Log initial inventory
                    $pid  = $db->insert_id;
                    $uid  = (int)$_SESSION['user_id'];
                    $log  = $db->prepare("INSERT INTO inventory_logs (product_id, change_qty, reason, user_id) VALUES (?,?,'Initial stock',?)");
                    $log->bind_param('iii', $pid, $stock, $uid);
                    $log->execute();
                    $log->close();
                    setFlash('success', 'Product added successfully.');
                } else {
                    setFlash('danger', 'Failed to add product.');
                }
                $stmt->close();
            } else {
                $pid = (int)($_POST['product_id'] ?? 0);
                if ($imageName) {
                    $stmt = $db->prepare("
                        UPDATE products SET product_name=?, brand=?, category=?, price=?, stock_quantity=?,
                               supplier=?, description=?, image=? WHERE product_id=?
                    ");
                    $stmt->bind_param('sssdisssi', $name, $brand, $category, $price, $stock, $supplier, $desc, $imageName, $pid);
                } else {
                    $stmt = $db->prepare("
                        UPDATE products SET product_name=?, brand=?, category=?, price=?, stock_quantity=?,
                               supplier=?, description=? WHERE product_id=?
                    ");
                    $stmt->bind_param('sssdiisi', $name, $brand, $category, $price, $stock, $supplier, $desc, $pid);
                }
                if ($stmt->execute()) {
                    setFlash('success', 'Product updated successfully.');
                } else {
                    setFlash('danger', 'Failed to update product.');
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $pid  = (int)($_POST['product_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param('i', $pid);
        if ($stmt->execute()) {
            setFlash('success', 'Product deleted.');
        } else {
            setFlash('danger', 'Cannot delete: product may be used in existing sales.');
        }
        $stmt->close();
    }
    header('Location: products.php');
    exit;
}

/* ---------- Fetch products with search/filter ---------- */
$search    = trim($_GET['search']   ?? '');
$category  = $_GET['category']      ?? '';
$filter    = $_GET['filter']        ?? '';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= ' AND (product_name LIKE ? OR brand LIKE ? OR supplier LIKE ?)';
    $like     = "%$search%";
    $params   = [$like, $like, $like];
    $types   .= 'sss';
}

$validCats = ['CCTV Camera','DVR','NVR','Hard Disk','Cable','Accessories'];
if ($category !== '' && in_array($category, $validCats, true)) {
    $where   .= ' AND category = ?';
    $params[] = $category;
    $types   .= 's';
}

if ($filter === 'low_stock') {
    $where .= ' AND stock_quantity <= ' . (int)LOW_STOCK_THRESHOLD;
}

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM products $where");
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = (int)ceil($totalRows / $perPage);

// Data
$dataParams  = $params;
$dataTypes   = $types . 'ii';
$dataParams[] = $perPage;
$dataParams[] = $offset;

$dataStmt = $db->prepare("SELECT * FROM products $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$products = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-camera-video me-2 text-info"></i>Products</h4>
    <?php if ($isAdmin): ?>
    <button class="btn btn-info text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="bi bi-plus-lg me-1"></i> Add Product
    </button>
    <?php endif; ?>
</div>

<!-- Search & Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search product, brand, supplier..."
                           value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-sm-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($validCats as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= e($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <select name="filter" class="form-select form-select-sm">
                    <option value="">All Stock</option>
                    <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                </select>
            </div>
            <div class="col-sm-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="products.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th>Supplier</th>
                        <?php if ($isAdmin): ?><th class="text-center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr class="<?= $p['stock_quantity'] <= LOW_STOCK_THRESHOLD ? 'low-stock-row' : '' ?>">
                        <td><?= $p['product_id'] ?></td>
                        <td>
                            <?php if ($p['image']): ?>
                            <img src="<?= getBasePath() ?>/assets/images/products/<?= e($p['image']) ?>"
                                 alt="" width="48" height="48" class="rounded object-fit-cover">
                            <?php else: ?>
                            <div class="bg-secondary rounded d-flex align-items-center justify-content-center"
                                 style="width:48px;height:48px">
                                <i class="bi bi-camera-video text-white"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($p['product_name']) ?></strong>
                            <?php if ($p['description']): ?>
                            <small class="d-block text-muted text-truncate" style="max-width:200px">
                                <?= e($p['description']) ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['brand'] ?? '–') ?></td>
                        <td><span class="badge bg-primary"><?= e($p['category']) ?></span></td>
                        <td class="text-end fw-bold">₹<?= number_format($p['price'], 2) ?></td>
                        <td class="text-center">
                            <?php if ($p['stock_quantity'] <= LOW_STOCK_THRESHOLD): ?>
                            <span class="badge bg-danger"><?= $p['stock_quantity'] ?></span>
                            <?php elseif ($p['stock_quantity'] <= LOW_STOCK_THRESHOLD * 3): ?>
                            <span class="badge bg-warning text-dark"><?= $p['stock_quantity'] ?></span>
                            <?php else: ?>
                            <span class="badge bg-success"><?= $p['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['supplier'] ?? '–') ?></td>
                        <?php if ($isAdmin): ?>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action"     value="delete">
                                <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete '<?= e($p['product_name']) ?>'? This cannot be undone.">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing <?= min($offset + 1, $totalRows) ?>–<?= min($offset + $perPage, $totalRows) ?>
            of <?= $totalRows ?> products
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&filter=<?= urlencode($filter) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<!-- Add/Edit Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action"     id="formAction" value="add">
                    <input type="hidden" name="product_id" id="formProductId" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" id="fProdName"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" id="fBrand" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="fCategory" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($validCats as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="price" id="fPrice"
                                   class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Stock Qty <span class="text-danger">*</span></label>
                            <input type="number" name="stock_quantity" id="fStock"
                                   class="form-control" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" id="fSupplier" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product Image</label>
                            <input type="file" name="product_image" class="form-control"
                                   accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text">Max 2 MB, JPG/PNG/WebP</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="fDesc"
                                      class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-dark fw-bold">
                        <i class="bi bi-save me-1"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProduct(p) {
    document.getElementById('productModalTitle').textContent = 'Edit Product';
    document.getElementById('formAction').value    = 'edit';
    document.getElementById('formProductId').value = p.product_id;
    document.getElementById('fProdName').value     = p.product_name;
    document.getElementById('fBrand').value        = p.brand    || '';
    document.getElementById('fCategory').value     = p.category;
    document.getElementById('fPrice').value        = p.price;
    document.getElementById('fStock').value        = p.stock_quantity;
    document.getElementById('fSupplier').value     = p.supplier || '';
    document.getElementById('fDesc').value         = p.description || '';
    new bootstrap.Modal(document.getElementById('productModal')).show();
}

document.getElementById('productModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('productModalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value              = 'add';
    document.getElementById('productForm').reset();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
