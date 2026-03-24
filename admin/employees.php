<?php
/**
 * Employee Management (Admin only)
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$db        = getDB();
$pageTitle = 'Employees';

/* ---------- Handle POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = trim($_POST['name']      ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $email    = trim($_POST['email']     ?? '');
        $role     = trim($_POST['role']      ?? '');
        $joinDate = $_POST['join_date']       ?? date('Y-m-d');
        $createUser = !empty($_POST['create_user']);
        $password   = trim($_POST['password'] ?? '');

        if ($name === '' || $phone === '' || $role === '') {
            setFlash('danger', 'Name, phone, and role are required.');
        } else {
            if ($action === 'add') {
                $stmt = $db->prepare(
                    "INSERT INTO employees (name, phone, email, role, join_date) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssss', $name, $phone, $email, $role, $joinDate);
                if ($stmt->execute()) {
                    $empId = $db->insert_id;
                    // Optionally create a user account for this employee
                    if ($createUser && $email !== '' && $password !== '') {
                        $hash  = password_hash($password, PASSWORD_DEFAULT);
                        $uStmt = $db->prepare(
                            "INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'employee', ?)"
                        );
                        $uStmt->bind_param('ssss', $name, $email, $hash, $phone);
                        if ($uStmt->execute()) {
                            $uid   = $db->insert_id;
                            $uLink = $db->prepare("UPDATE employees SET user_id=? WHERE employee_id=?");
                            $uLink->bind_param('ii', $uid, $empId);
                            $uLink->execute();
                            $uLink->close();
                        }
                        $uStmt->close();
                    }
                    setFlash('success', 'Employee added successfully.');
                } else {
                    setFlash('danger', 'Failed to add employee.');
                }
                $stmt->close();
            } else {
                $eid  = (int)($_POST['employee_id'] ?? 0);
                $stmt = $db->prepare(
                    "UPDATE employees SET name=?, phone=?, email=?, role=?, join_date=? WHERE employee_id=?"
                );
                $stmt->bind_param('sssssi', $name, $phone, $email, $role, $joinDate, $eid);
                if ($stmt->execute()) {
                    setFlash('success', 'Employee updated.');
                } else {
                    setFlash('danger', 'Failed to update employee.');
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $eid  = (int)($_POST['employee_id'] ?? 0);
        $stmt = $db->prepare("UPDATE employees SET status=0 WHERE employee_id=?");
        $stmt->bind_param('i', $eid);
        if ($stmt->execute()) setFlash('success', 'Employee deactivated.');
        else setFlash('danger', 'Failed to deactivate employee.');
        $stmt->close();
    }
    header('Location: employees.php');
    exit;
}

/* ---------- Fetch employees ---------- */
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = 'WHERE status=1';
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= ' AND (name LIKE ? OR phone LIKE ? OR role LIKE ?)';
    $like     = "%$search%";
    $params   = [$like, $like, $like];
    $types   .= 'sss';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM employees $where");
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
    SELECT e.*,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_employee = e.employee_id
            AND sr.status != 'Completed') AS active_requests
    FROM employees e
    $where ORDER BY e.created_at DESC LIMIT ? OFFSET ?
");
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$employees = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-info"></i>Employees</h4>
    <button class="btn btn-info text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#employeeModal">
        <i class="bi bi-plus-lg me-1"></i> Add Employee
    </button>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Name, phone, role..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Search</button>
                <a href="employees.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
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
                        <th>#</th><th>Name</th><th>Phone</th><th>Email</th>
                        <th>Role</th><th>Join Date</th><th class="text-center">Active SRs</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?= $emp['employee_id'] ?></td>
                        <td><strong><?= e($emp['name']) ?></strong></td>
                        <td><?= e($emp['phone']) ?></td>
                        <td><?= e($emp['email'] ?? '–') ?></td>
                        <td><span class="badge bg-primary"><?= e($emp['role']) ?></span></td>
                        <td><?= date('d M Y', strtotime($emp['join_date'])) ?></td>
                        <td class="text-center">
                            <?php if ($emp['active_requests'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $emp['active_requests'] ?></span>
                            <?php else: ?>
                            <span class="badge bg-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="editEmployee(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action"      value="delete">
                                <input type="hidden" name="employee_id" value="<?= $emp['employee_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Deactivate employee '<?= e($emp['name']) ?>'?">
                                    <i class="bi bi-person-x"></i>
                                </button>
                            </form>
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

<!-- Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="employeeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action"      id="eAction" value="add">
                    <input type="hidden" name="employee_id" id="eId"     value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="eName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="ePhone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="eEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <input type="text" name="role" id="eRole" class="form-control"
                                   placeholder="Technician, Engineer..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Join Date</label>
                            <input type="date" name="join_date" id="eJoinDate" class="form-control"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <!-- Create user account (only for new employees) -->
                    <div id="createUserSection" class="mt-3 border-top pt-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="create_user"
                                   id="createUserCheck" value="1"
                                   onchange="document.getElementById('userPasswordSection').classList.toggle('d-none',!this.checked)">
                            <label class="form-check-label" for="createUserCheck">
                                Create login account for this employee
                            </label>
                        </div>
                        <div id="userPasswordSection" class="d-none">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control"
                                   placeholder="Set a strong password">
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
function editEmployee(emp) {
    document.getElementById('empModalTitle').textContent = 'Edit Employee';
    document.getElementById('eAction').value   = 'edit';
    document.getElementById('eId').value       = emp.employee_id;
    document.getElementById('eName').value     = emp.name;
    document.getElementById('ePhone').value    = emp.phone;
    document.getElementById('eEmail').value    = emp.email   || '';
    document.getElementById('eRole').value     = emp.role;
    document.getElementById('eJoinDate').value = emp.join_date;
    document.getElementById('createUserSection').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('employeeModal')).show();
}

document.getElementById('employeeModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('empModalTitle').textContent = 'Add Employee';
    document.getElementById('employeeForm').reset();
    document.getElementById('eAction').value = 'add';
    document.getElementById('createUserSection').classList.remove('d-none');
    document.getElementById('userPasswordSection').classList.add('d-none');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
