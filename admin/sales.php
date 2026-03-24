<?php
/**
 * Sales / Invoice Management
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db        = getDB();
$pageTitle = 'Sales';

/* ---------- Create Invoice POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_invoice') {
    $customerId    = (int)($_POST['customer_id']     ?? 0);
    $paymentMethod = $_POST['payment_method']         ?? 'Cash';
    $paymentStatus = $_POST['payment_status']         ?? 'Paid';
    $discount      = (float)($_POST['discount']       ?? 0);
    $taxRate       = (float)($_POST['tax_rate']       ?? 0);
    $notes         = trim($_POST['notes']             ?? '');
    $items         = $_POST['items']                  ?? [];

    $validMethods  = ['Cash','UPI','Card'];
    $validStatuses = ['Paid','Pending','Partial'];

    if ($customerId <= 0 || !in_array($paymentMethod, $validMethods, true) || empty($items)) {
        setFlash('danger', 'Invalid invoice data. Ensure customer and at least one product are selected.');
        header('Location: sales.php?action=new');
        exit;
    }

    $paymentMethod = in_array($paymentMethod, $validMethods, true)  ? $paymentMethod : 'Cash';
    $paymentStatus = in_array($paymentStatus, $validStatuses, true) ? $paymentStatus : 'Paid';

    // Calculate totals
    $totalAmount = 0;
    $validItems  = [];

    foreach ($items as $item) {
        $pid  = (int)($item['product_id'] ?? 0);
        $qty  = (int)($item['quantity']   ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;

        $pStmt = $db->prepare("SELECT product_id, price, stock_quantity FROM products WHERE product_id = ?");
        $pStmt->bind_param('i', $pid);
        $pStmt->execute();
        $prod  = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        if (!$prod || $prod['stock_quantity'] < $qty) {
            setFlash('danger', "Insufficient stock for product ID $pid.");
            header('Location: sales.php?action=new');
            exit;
        }

        $sub          = $prod['price'] * $qty;
        $totalAmount += $sub;
        $validItems[] = ['product_id' => $pid, 'quantity' => $qty,
                         'unit_price' => $prod['price'], 'subtotal' => $sub];
    }

    if (empty($validItems)) {
        setFlash('danger', 'No valid items in the invoice.');
        header('Location: sales.php?action=new');
        exit;
    }

    $taxAmount  = ($totalAmount - $discount) * ($taxRate / 100);
    $grandTotal = $totalAmount - $discount + $taxAmount;
    $userId     = (int)$_SESSION['user_id'];

    $db->begin_transaction();
    try {
        $sStmt = $db->prepare("
            INSERT INTO sales (customer_id, user_id, total_amount, discount, tax, grand_total, payment_method, payment_status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sStmt->bind_param('iiddddsss',
            $customerId, $userId, $totalAmount, $discount,
            $taxAmount, $grandTotal, $paymentMethod, $paymentStatus, $notes);
        $sStmt->execute();
        $saleId = $db->insert_id;
        $sStmt->close();

        foreach ($validItems as $item) {
            $iStmt = $db->prepare(
                "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)"
            );
            $iStmt->bind_param('iiidd', $saleId, $item['product_id'], $item['quantity'],
                               $item['unit_price'], $item['subtotal']);
            $iStmt->execute();
            $iStmt->close();

            // Deduct stock
            $uStmt = $db->prepare(
                "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?"
            );
            $uStmt->bind_param('ii', $item['quantity'], $item['product_id']);
            $uStmt->execute();
            $uStmt->close();

            // Log inventory change
            $reason = 'Sale #' . $saleId;
            $lStmt  = $db->prepare(
                "INSERT INTO inventory_logs (product_id, change_qty, reason, reference_id, user_id) VALUES (?,?,?,?,?)"
            );
            $deduct = -$item['quantity'];
            $lStmt->bind_param('iisii', $item['product_id'], $deduct, $reason, $saleId, $userId);
            $lStmt->execute();
            $lStmt->close();
        }

        $db->commit();
        setFlash('success', "Invoice #$saleId created successfully.");
        header('Location: sales.php?view=' . $saleId);
        exit;
    } catch (Throwable $e) {
        $db->rollback();
        error_log($e->getMessage());
        setFlash('danger', 'Transaction failed. Please try again.');
        header('Location: sales.php?action=new');
        exit;
    }
}

/* ---------- View Invoice ---------- */
$viewId   = (int)($_GET['view'] ?? 0);
$invoice  = null;
$saleItems = [];

if ($viewId > 0) {
    $stmt = $db->prepare("
        SELECT s.*, c.name AS customer_name, c.phone AS customer_phone,
               c.email AS customer_email, c.address AS customer_address,
               u.name AS created_by
        FROM   sales s
        JOIN   customers c ON c.customer_id = s.customer_id
        JOIN   users u     ON u.user_id     = s.user_id
        WHERE  s.sale_id = ?
    ");
    $stmt->bind_param('i', $viewId);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($invoice) {
        $stmt = $db->prepare("
            SELECT si.*, p.product_name, p.brand, p.category
            FROM   sale_items si
            JOIN   products p ON p.product_id = si.product_id
            WHERE  si.sale_id = ?
        ");
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $saleItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

/* ---------- New Invoice form ---------- */
$newInvoice = ($_GET['action'] ?? '') === 'new';
$customers  = [];
$products   = [];

if ($newInvoice) {
    $res = $db->query("SELECT customer_id, name, phone FROM customers ORDER BY name");
    while ($r = $res->fetch_assoc()) $customers[] = $r;

    $res = $db->query("SELECT product_id, product_name, brand, price, stock_quantity FROM products WHERE stock_quantity > 0 ORDER BY product_name");
    while ($r = $res->fetch_assoc()) $products[] = $r;
}

/* ---------- Fetch sales list ---------- */
$search  = trim($_GET['search']   ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= " AND (c.name LIKE ? OR s.sale_id LIKE ?)";
    $like     = "%$search%";
    $params   = [$like, $like];
    $types   .= 'ss';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM sales s JOIN customers c ON c.customer_id = s.customer_id $where");
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
    SELECT s.sale_id, c.name AS customer_name, s.grand_total,
           s.payment_method, s.payment_status, s.sale_date
    FROM   sales s
    JOIN   customers c ON c.customer_id = s.customer_id
    $where
    ORDER  BY s.sale_date DESC
    LIMIT  ? OFFSET ?
");
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$sales = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($invoice): ?>
<!-- ============================================================
     INVOICE VIEW
     ============================================================ -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-info"></i>Invoice #<?= $invoice['sale_id'] ?></h4>
    <div class="d-flex gap-2">
        <a href="sales.php" class="btn btn-secondary btn-sm no-print">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print
        </button>
    </div>
</div>

<div class="card p-4" id="invoicePrintArea">
    <div class="row mb-4">
        <div class="col-6">
            <h5 class="fw-bold text-info">
                <i class="bi bi-camera-video-fill me-1"></i><?= APP_NAME ?>
            </h5>
            <p class="mb-0 text-muted small">Surveillance Equipment Shop</p>
        </div>
        <div class="col-6 text-end">
            <h5 class="fw-bold">INVOICE</h5>
            <p class="mb-0"><strong>#<?= str_pad($invoice['sale_id'], 5, '0', STR_PAD_LEFT) ?></strong></p>
            <p class="mb-0 text-muted small"><?= date('d M Y, h:i A', strtotime($invoice['sale_date'])) ?></p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <h6 class="text-uppercase text-muted small fw-bold">Bill To</h6>
            <p class="mb-0 fw-bold"><?= e($invoice['customer_name']) ?></p>
            <p class="mb-0"><?= e($invoice['customer_phone']) ?></p>
            <p class="mb-0"><?= e($invoice['customer_email'] ?? '') ?></p>
            <p class="mb-0"><?= e($invoice['customer_address'] ?? '') ?></p>
        </div>
        <div class="col-6 text-end">
            <table class="ms-auto">
                <tr><td class="pe-3 text-muted">Payment:</td><td><strong><?= e($invoice['payment_method']) ?></strong></td></tr>
                <tr><td class="pe-3 text-muted">Status:</td>
                    <td><?php
                        $sc = ['Paid'=>'success','Pending'=>'warning','Partial'=>'info'];
                        $c  = $sc[$invoice['payment_status']] ?? 'secondary';
                    ?><span class="badge bg-<?= $c ?>"><?= e($invoice['payment_status']) ?></span></td></tr>
                <tr><td class="pe-3 text-muted">Created by:</td><td><?= e($invoice['created_by']) ?></td></tr>
            </table>
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th><th>Product</th><th>Category</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saleItems as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= e($item['product_name']) ?></strong>
                        <?php if ($item['brand']): ?>
                        <small class="text-muted d-block"><?= e($item['brand']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= e($item['category']) ?></span></td>
                    <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end fw-bold">₹<?= number_format($item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end text-muted">Subtotal</td>
                    <td class="text-end">₹<?= number_format($invoice['total_amount'], 2) ?></td>
                </tr>
                <?php if ($invoice['discount'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-end text-muted">Discount</td>
                    <td class="text-end text-danger">-₹<?= number_format($invoice['discount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($invoice['tax'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-end text-muted">Tax</td>
                    <td class="text-end">₹<?= number_format($invoice['tax'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="table-dark">
                    <td colspan="5" class="text-end fw-bold">Grand Total</td>
                    <td class="text-end fw-bold fs-5">₹<?= number_format($invoice['grand_total'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($invoice['notes']): ?>
    <p class="text-muted small"><strong>Notes:</strong> <?= e($invoice['notes']) ?></p>
    <?php endif; ?>

    <div class="text-center text-muted small mt-3 border-top pt-2">
        Thank you for your business! | <?= APP_NAME ?>
    </div>
</div>

<?php elseif ($newInvoice): ?>
<!-- ============================================================
     NEW INVOICE FORM
     ============================================================ -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-info"></i>New Invoice</h4>
    <a href="sales.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<form method="POST" id="invoiceForm">
    <input type="hidden" name="action" value="create_invoice">
    <div class="row g-3">
        <div class="col-lg-8">
            <!-- Customer + Items -->
            <div class="card mb-3">
                <div class="card-header fw-bold">Customer & Products</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>">
                                    <?= e($c['name']) ?> (<?= e($c['phone']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Add Product</label>
                            <select id="productSelect" class="form-select" onchange="addSelectedProduct()">
                                <option value="">-- Select Product to Add --</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['product_id'] ?>"
                                        data-name="<?= e($p['product_name']) ?>"
                                        data-brand="<?= e($p['brand'] ?? '') ?>"
                                        data-price="<?= $p['price'] ?>"
                                        data-stock="<?= $p['stock_quantity'] ?>">
                                    <?= e($p['product_name']) ?> – ₹<?= number_format($p['price'],2) ?>
                                    (Stock: <?= $p['stock_quantity'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="saleItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Unit Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="saleItemsBody">
                                <tr id="emptyRow">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        Select a product above to add it to the invoice.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Summary -->
            <div class="card mb-3">
                <div class="card-header fw-bold">Invoice Summary</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Discount (₹)</label>
                        <input type="number" name="discount" id="discountAmount"
                               class="form-control" value="0" min="0" step="0.01"
                               onchange="updateSaleTotals()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tax (%)</label>
                        <input type="number" name="tax_rate" id="taxAmount"
                               class="form-control" value="0" min="0" step="0.1"
                               onchange="updateSaleTotals()">
                    </div>

                    <table class="table table-sm mb-0">
                        <tr><td>Subtotal</td><td class="text-end" id="totalAmount">₹0.00</td></tr>
                        <tr><td>Tax</td><td class="text-end" id="taxAmountCalc">₹0.00</td></tr>
                        <tr class="table-dark fw-bold">
                            <td>Grand Total</td>
                            <td class="text-end" id="grandTotal">₹0.00</td>
                        </tr>
                    </table>
                    <input type="hidden" name="grand_total" id="grandTotalInput" value="0">
                </div>
            </div>

            <!-- Payment -->
            <div class="card mb-3">
                <div class="card-header fw-bold">Payment</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Partial">Partial</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Optional notes..."></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-info text-dark fw-bold w-100">
                <i class="bi bi-check-circle me-1"></i> Create Invoice
            </button>
        </div>
    </div>
</form>

<script>
function addSelectedProduct() {
    const sel = document.getElementById('productSelect');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();

    addSaleRow({
        product_id:     opt.value,
        product_name:   opt.dataset.name,
        brand:          opt.dataset.brand,
        price:          opt.dataset.price,
        stock_quantity: opt.dataset.stock
    });
    sel.value = '';
}
</script>

<?php else: ?>
<!-- ============================================================
     SALES LIST
     ============================================================ -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-info"></i>Sales / Invoices</h4>
    <a href="?action=new" class="btn btn-info text-dark fw-bold">
        <i class="bi bi-plus-lg me-1"></i> New Invoice
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Customer name or invoice #"
                           value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Search</button>
                <a href="sales.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#Invoice</th>
                        <th>Customer</th>
                        <th class="text-end">Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No sales found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><strong>#<?= str_pad($s['sale_id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= e($s['customer_name']) ?></td>
                        <td class="text-end fw-bold text-success">₹<?= number_format($s['grand_total'],2) ?></td>
                        <td><span class="badge bg-info text-dark"><?= e($s['payment_method']) ?></span></td>
                        <td><?php
                            $sc = ['Paid'=>'success','Pending'=>'warning','Partial'=>'primary'];
                            $c  = $sc[$s['payment_status']] ?? 'secondary';
                        ?><span class="badge bg-<?= $c ?>"><?= e($s['payment_status']) ?></span></td>
                        <td><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                        <td class="text-center">
                            <a href="?view=<?= $s['sale_id'] ?>"
                               class="btn btn-sm btn-outline-info">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
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
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
