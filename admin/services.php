<?php
/**
 * Service Requests Management
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db        = getDB();
$isAdmin   = isAdmin();
$pageTitle = 'Service Requests';

/* ---------- Handle POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $customerId  = (int)($_POST['customer_id']    ?? 0);
        $serviceType = $_POST['service_type']          ?? '';
        $address     = trim($_POST['address']          ?? '');
        $description = trim($_POST['description']      ?? '');
        $status      = $_POST['status']                ?? 'Pending';
        $empId       = (int)($_POST['assigned_employee'] ?? 0) ?: null;
        $schedDate   = $_POST['scheduled_date']        ?? null;

        $validTypes    = ['CCTV Installation','Camera Repair','Maintenance','System Upgrade'];
        $validStatuses = ['Pending','Assigned','In Progress','Completed'];

        if ($customerId <= 0 || !in_array($serviceType, $validTypes, true)) {
            setFlash('danger', 'Invalid service request data.');
        } else {
            $status = in_array($status, $validStatuses, true) ? $status : 'Pending';
            if ($schedDate === '') $schedDate = null;

            if ($action === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO service_requests
                        (customer_id, service_type, address, description, status, assigned_employee, scheduled_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('issssis', $customerId, $serviceType, $address,
                                  $description, $status, $empId, $schedDate);
                if ($stmt->execute()) setFlash('success', 'Service request created.');
                else setFlash('danger', 'Failed to create service request.');
                $stmt->close();
            } else {
                $rid  = (int)($_POST['request_id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE service_requests
                    SET customer_id=?, service_type=?, address=?, description=?,
                        status=?, assigned_employee=?, scheduled_date=?
                    WHERE request_id=?
                ");
                $stmt->bind_param('issssisi', $customerId, $serviceType, $address,
                                  $description, $status, $empId, $schedDate, $rid);
                if ($stmt->execute()) setFlash('success', 'Service request updated.');
                else setFlash('danger', 'Failed to update service request.');
                $stmt->close();
            }
        }
    } elseif ($action === 'delete' && $isAdmin) {
        $rid  = (int)($_POST['request_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM service_requests WHERE request_id = ?");
        $stmt->bind_param('i', $rid);
        if ($stmt->execute()) setFlash('success', 'Service request deleted.');
        else setFlash('danger', 'Failed to delete.');
        $stmt->close();
    }
    header('Location: services.php');
    exit;
}

/* ---------- Fetch data ---------- */
$statusFilter = $_GET['status']  ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$validStatuses = ['Pending','Assigned','In Progress','Completed'];

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= ' AND (c.name LIKE ? OR sr.service_type LIKE ?)';
    $like     = "%$search%";
    $params   = [$like, $like];
    $types   .= 'ss';
}
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $where   .= ' AND sr.status = ?';
    $params[] = $statusFilter;
    $types   .= 's';
}

$baseQuery = "FROM service_requests sr JOIN customers c ON c.customer_id = sr.customer_id
              LEFT JOIN employees e ON e.employee_id = sr.assigned_employee $where";

$countStmt = $db->prepare("SELECT COUNT(*) $baseQuery");
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
    SELECT sr.*, c.name AS customer_name, c.phone AS customer_phone,
           e.name AS emp_name
    $baseQuery
    ORDER  BY sr.created_at DESC
    LIMIT  ? OFFSET ?
");
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$requests = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// For modal dropdowns
$customers = [];
$res = $db->query("SELECT customer_id, name, phone FROM customers ORDER BY name");
while ($r = $res->fetch_assoc()) $customers[] = $r;

$employees = [];
$res = $db->query("SELECT employee_id, name, role FROM employees WHERE status=1 ORDER BY name");
while ($r = $res->fetch_assoc()) $employees[] = $r;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-tools me-2 text-info"></i>Service Requests</h4>
    <button class="btn btn-info text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#serviceModal">
        <i class="bi bi-plus-lg me-1"></i> New Request
    </button>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Customer, service type..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-sm-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach ($validStatuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                        <?= e($st) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="services.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
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
                        <th>#</th><th>Customer</th><th>Service Type</th><th>Status</th>
                        <th>Assigned To</th><th>Scheduled</th><th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No service requests found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                    <?php
                        $sc  = ['Pending'=>'warning','Assigned'=>'info','In Progress'=>'primary','Completed'=>'success'];
                        $col = $sc[$r['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>#<?= $r['request_id'] ?></td>
                        <td>
                            <strong><?= e($r['customer_name']) ?></strong>
                            <small class="d-block text-muted"><?= e($r['customer_phone']) ?></small>
                        </td>
                        <td><?= e($r['service_type']) ?></td>
                        <td><span class="badge bg-<?= $col ?>"><?= e($r['status']) ?></span></td>
                        <td><?= e($r['emp_name'] ?? '—') ?></td>
                        <td><?= $r['scheduled_date'] ? date('d M Y', strtotime($r['scheduled_date'])) : '—' ?></td>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="editService(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($isAdmin): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action"     value="delete">
                                <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete service request #<?= $r['request_id'] ?>?">
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
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="serviceForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="serviceModalTitle">New Service Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action"     id="sAction" value="add">
                    <input type="hidden" name="request_id" id="sRid"    value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="sCustId" class="form-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>">
                                    <?= e($c['name']) ?> (<?= e($c['phone']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Type <span class="text-danger">*</span></label>
                            <select name="service_type" id="sType" class="form-select" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach (['CCTV Installation','Camera Repair','Maintenance','System Upgrade'] as $t): ?>
                                <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="sStatus" class="form-select">
                                <?php foreach ($validStatuses as $st): ?>
                                <option value="<?= e($st) ?>"><?= e($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign Employee</label>
                            <select name="assigned_employee" id="sEmp" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($employees as $em): ?>
                                <option value="<?= $em['employee_id'] ?>">
                                    <?= e($em['name']) ?> (<?= e($em['role']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" name="scheduled_date" id="sDate" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="sAddr" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="sDesc" class="form-control" rows="3"></textarea>
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

<script>
function editService(r) {
    document.getElementById('serviceModalTitle').textContent = 'Edit Service Request';
    document.getElementById('sAction').value  = 'edit';
    document.getElementById('sRid').value     = r.request_id;
    document.getElementById('sCustId').value  = r.customer_id;
    document.getElementById('sType').value    = r.service_type;
    document.getElementById('sStatus').value  = r.status;
    document.getElementById('sEmp').value     = r.assigned_employee || '';
    document.getElementById('sDate').value    = r.scheduled_date || '';
    document.getElementById('sAddr').value    = r.address || '';
    document.getElementById('sDesc').value    = r.description || '';
    new bootstrap.Modal(document.getElementById('serviceModal')).show();
}

document.getElementById('serviceModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('serviceModalTitle').textContent = 'New Service Request';
    document.getElementById('serviceForm').reset();
    document.getElementById('sAction').value = 'add';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
