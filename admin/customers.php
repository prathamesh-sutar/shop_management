<?php
/**
 * Customer Management
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db        = getDB();
$isAdmin   = isAdmin();
$pageTitle = 'Customers';

/* ---------- Handle POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $email   = trim($_POST['email']   ?? '');
        $address = trim($_POST['address'] ?? '');
        $city    = trim($_POST['city']    ?? '');

        if ($name === '' || $phone === '') {
            setFlash('danger', 'Name and phone are required.');
        } else {
            if ($action === 'add') {
                $stmt = $db->prepare(
                    "INSERT INTO customers (name, phone, email, address, city) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssss', $name, $phone, $email, $address, $city);
                if ($stmt->execute()) {
                    setFlash('success', 'Customer added successfully.');
                } else {
                    setFlash('danger', 'Failed to add customer.');
                }
                $stmt->close();
            } else {
                $cid  = (int)($_POST['customer_id'] ?? 0);
                $stmt = $db->prepare(
                    "UPDATE customers SET name=?, phone=?, email=?, address=?, city=? WHERE customer_id=?"
                );
                $stmt->bind_param('sssssi', $name, $phone, $email, $address, $city, $cid);
                if ($stmt->execute()) {
                    setFlash('success', 'Customer updated.');
                } else {
                    setFlash('danger', 'Failed to update customer.');
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete' && $isAdmin) {
        $cid  = (int)($_POST['customer_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->bind_param('i', $cid);
        if ($stmt->execute()) {
            setFlash('success', 'Customer deleted.');
        } else {
            setFlash('danger', 'Cannot delete: customer has existing records.');
        }
        $stmt->close();
    }
    header('Location: customers.php');
    exit;
}

/* ---------- View customer history ---------- */
$viewId       = (int)($_GET['view'] ?? 0);
$viewCustomer = null;
$custSales    = [];
$custServices = [];

if ($viewId > 0) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param('i', $viewId);
    $stmt->execute();
    $viewCustomer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($viewCustomer) {
        $s = $db->prepare("
            SELECT s.sale_id, s.grand_total, s.payment_method, s.sale_date
            FROM sales s WHERE s.customer_id = ? ORDER BY s.sale_date DESC
        ");
        $s->bind_param('i', $viewId);
        $s->execute();
        $custSales = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();

        $s = $db->prepare("
            SELECT sr.*, e.name AS emp_name
            FROM service_requests sr
            LEFT JOIN employees e ON e.employee_id = sr.assigned_employee
            WHERE sr.customer_id = ? ORDER BY sr.created_at DESC
        ");
        $s->bind_param('i', $viewId);
        $s->execute();
        $custServices = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    }
}

/* ---------- Fetch customers ---------- */
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ?)';
    $like     = "%$search%";
    $params   = [$like, $like, $like, $like];
    $types   .= 'ssss';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM customers $where");
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = (int)ceil($totalRows / $perPage);

$dataParams   = $params;
$dataTypes    = $types . 'ii';
$dataParams[] = $perPage;
$dataParams[] = $offset;

$dataStmt = $db->prepare("SELECT * FROM customers $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$customers = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-info"></i>Customers</h4>
    <button class="btn btn-info text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#customerModal">
        <i class="bi bi-plus-lg me-1"></i> Add Customer
    </button>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Name, phone, email, city..."
                           value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Search</button>
                <a href="customers.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>City</th>
                        <th>Joined</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= $c['customer_id'] ?></td>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td><?= e($c['phone']) ?></td>
                        <td><?= e($c['email'] ?? '–') ?></td>
                        <td><?= e($c['city'] ?? '–') ?></td>
                        <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                        <td class="text-center">
                            <a href="?view=<?= $c['customer_id'] ?>"
                               class="btn btn-sm btn-outline-info me-1" title="History">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                    onclick="editCustomer(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($isAdmin): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action"      value="delete">
                                <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-confirm="Delete customer '<?= e($c['name']) ?>'?">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
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

<!-- Add/Edit Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="customerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action"      id="cAction"  value="add">
                    <input type="hidden" name="customer_id" id="cId"      value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text"  name="name"  id="cName"  class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text"  name="phone" id="cPhone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="cEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text"  name="city"  id="cCity"  class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="cAddress" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-dark fw-bold">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($viewCustomer): ?>
<!-- Customer History Modal (auto-open) -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-lines-fill me-2 text-info"></i>
                    History: <?= e($viewCustomer['name']) ?>
                </h5>
                <a href="customers.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-sm-4"><strong>Phone:</strong> <?= e($viewCustomer['phone']) ?></div>
                    <div class="col-sm-4"><strong>Email:</strong> <?= e($viewCustomer['email'] ?? '–') ?></div>
                    <div class="col-sm-4"><strong>City:</strong> <?= e($viewCustomer['city'] ?? '–') ?></div>
                </div>

                <h6 class="border-bottom pb-1 mb-2">Sales History</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>#</th><th>Amount</th><th>Payment</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (empty($custSales)): ?>
                            <tr><td colspan="4" class="text-muted text-center">No sales</td></tr>
                            <?php else: foreach ($custSales as $s): ?>
                            <tr>
                                <td><a href="sales.php?view=<?= $s['sale_id'] ?>">#<?= $s['sale_id'] ?></a></td>
                                <td>₹<?= number_format($s['grand_total'],2) ?></td>
                                <td><span class="badge bg-info text-dark"><?= e($s['payment_method']) ?></span></td>
                                <td><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <h6 class="border-bottom pb-1 mb-2">Service Requests</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>#</th><th>Service</th><th>Status</th><th>Assigned To</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (empty($custServices)): ?>
                            <tr><td colspan="5" class="text-muted text-center">No service requests</td></tr>
                            <?php else: foreach ($custServices as $sr): ?>
                            <tr>
                                <td>#<?= $sr['request_id'] ?></td>
                                <td><?= e($sr['service_type']) ?></td>
                                <td><?php
                                    $sc = ['Pending'=>'warning','Assigned'=>'info','In Progress'=>'primary','Completed'=>'success'];
                                    $col = $sc[$sr['status']] ?? 'secondary';
                                ?><span class="badge bg-<?= $col ?>"><?= e($sr['status']) ?></span></td>
                                <td><?= e($sr['emp_name'] ?? '–') ?></td>
                                <td><?= date('d M Y', strtotime($sr['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('historyModal')).show();
});
</script>
<?php endif; ?>

<script>
function editCustomer(c) {
    document.getElementById('customerModalTitle').textContent = 'Edit Customer';
    document.getElementById('cAction').value  = 'edit';
    document.getElementById('cId').value      = c.customer_id;
    document.getElementById('cName').value    = c.name;
    document.getElementById('cPhone').value   = c.phone;
    document.getElementById('cEmail').value   = c.email   || '';
    document.getElementById('cCity').value    = c.city    || '';
    document.getElementById('cAddress').value = c.address || '';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

document.getElementById('customerModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('customerModalTitle').textContent = 'Add Customer';
    document.getElementById('customerForm').reset();
    document.getElementById('cAction').value = 'add';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
