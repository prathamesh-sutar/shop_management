<?php
/**
 * Reports & Analytics
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db        = getDB();
$pageTitle = 'Reports';

$reportType = $_GET['type']       ?? 'daily';
$dateFrom   = $_GET['date_from']  ?? date('Y-m-01');
$dateTo     = $_GET['date_to']    ?? date('Y-m-d');

/* ---- Daily sales ---- */
$dailySales = [];
if ($reportType === 'daily') {
    $stmt = $db->prepare("
        SELECT DATE(sale_date) AS sale_day,
               COUNT(*) AS orders,
               SUM(grand_total) AS revenue
        FROM sales
        WHERE DATE(sale_date) BETWEEN ? AND ?
        GROUP BY sale_day ORDER BY sale_day
    ");
    $stmt->bind_param('ss', $dateFrom, $dateTo);
    $stmt->execute();
    $dailySales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ---- Monthly sales ---- */
$monthlySales = [];
if ($reportType === 'monthly') {
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(sale_date,'%Y-%m') AS month_key,
               DATE_FORMAT(sale_date,'%b %Y')  AS month_label,
               COUNT(*) AS orders,
               SUM(grand_total) AS revenue
        FROM sales
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month_key ORDER BY month_key
    ");
    $stmt->execute();
    $monthlySales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ---- Top products ---- */
$topProducts = [];
if ($reportType === 'top_products') {
    $stmt = $db->prepare("
        SELECT p.product_name, p.category, p.brand,
               SUM(si.quantity) AS total_qty,
               SUM(si.subtotal) AS total_revenue
        FROM sale_items si
        JOIN products p ON p.product_id = si.product_id
        JOIN sales s     ON s.sale_id    = si.sale_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY si.product_id
        ORDER BY total_revenue DESC
        LIMIT 20
    ");
    $stmt->bind_param('ss', $dateFrom, $dateTo);
    $stmt->execute();
    $topProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ---- Inventory report ---- */
$inventoryData = [];
if ($reportType === 'inventory') {
    $res = $db->query("
        SELECT product_id, product_name, brand, category,
               price, stock_quantity, supplier,
               (price * stock_quantity) AS stock_value
        FROM products
        ORDER BY category, product_name
    ");
    while ($r = $res->fetch_assoc()) $inventoryData[] = $r;
}

/* ---- Service requests report ---- */
$serviceData = [];
if ($reportType === 'services') {
    $stmt = $db->prepare("
        SELECT sr.request_id, sr.service_type, sr.status,
               c.name AS customer_name, e.name AS emp_name,
               sr.scheduled_date, sr.created_at
        FROM service_requests sr
        JOIN customers c ON c.customer_id = sr.customer_id
        LEFT JOIN employees e ON e.employee_id = sr.assigned_employee
        WHERE DATE(sr.created_at) BETWEEN ? AND ?
        ORDER BY sr.created_at DESC
    ");
    $stmt->bind_param('ss', $dateFrom, $dateTo);
    $stmt->execute();
    $serviceData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ---- Summary totals for current range ---- */
$stmt = $db->prepare("
    SELECT COUNT(*) AS cnt, COALESCE(SUM(grand_total),0) AS rev
    FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$summaryRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2 text-info"></i>Reports & Analytics</h4>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">
        <i class="bi bi-printer me-1"></i> Print
    </button>
</div>

<!-- Filter form -->
<div class="card mb-4 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="daily"       <?= $reportType==='daily'        ?'selected':'' ?>>Daily Sales</option>
                    <option value="monthly"     <?= $reportType==='monthly'      ?'selected':'' ?>>Monthly Sales</option>
                    <option value="top_products"<?= $reportType==='top_products' ?'selected':'' ?>>Top Products</option>
                    <option value="inventory"   <?= $reportType==='inventory'    ?'selected':'' ?>>Inventory</option>
                    <option value="services"    <?= $reportType==='services'     ?'selected':'' ?>>Service Requests</option>
                </select>
            </div>
            <div class="col-sm-2">
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-sm-2">
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= e($dateTo) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary banner -->
<?php if (in_array($reportType, ['daily','top_products','services'], true)): ?>
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="card text-white" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-receipt fs-2"></i>
                <div>
                    <div class="fs-4 fw-bold"><?= $summaryRow['cnt'] ?></div>
                    <div class="small opacity-75">Total Orders</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card text-white" style="background:linear-gradient(135deg,#198754,#146c43)">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-currency-rupee fs-2"></i>
                <div>
                    <div class="fs-4 fw-bold">₹<?= number_format($summaryRow['rev'],2) ?></div>
                    <div class="small opacity-75">Total Revenue</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     Report tables & charts
     ============================================================ -->
<?php if ($reportType === 'daily'): ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-bold">Daily Sales – <?= e($dateFrom) ?> to <?= e($dateTo) ?></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Date</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                        <tbody>
                            <?php if (empty($dailySales)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No data</td></tr>
                            <?php else: foreach ($dailySales as $row): ?>
                            <tr>
                                <td><?= date('D, d M Y', strtotime($row['sale_day'])) ?></td>
                                <td class="text-end"><?= $row['orders'] ?></td>
                                <td class="text-end fw-bold text-success">₹<?= number_format($row['revenue'],2) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-bold">Revenue Chart</div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const labels   = <?= json_encode(array_column($dailySales, 'sale_day')) ?>;
    const revenues = <?= json_encode(array_map(fn($r)=>(float)$r['revenue'], $dailySales)) ?>;
    const ctx = document.getElementById('dailyChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Revenue (₹)',
                data: revenues,
                borderColor: '#0dcaf0',
                backgroundColor: 'rgba(13,202,240,0.15)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { responsive:true, maintainAspectRatio:false,
                   plugins:{legend:{display:false}},
                   scales:{ y:{beginAtZero:true} } }
    });
})();
</script>

<?php elseif ($reportType === 'monthly'): ?>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-bold">Monthly Sales (Last 12 Months)</div>
            <div class="card-body">
                <div class="chart-container" style="height:350px">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Month</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                        <tbody>
                            <?php if (empty($monthlySales)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No data</td></tr>
                            <?php else: foreach ($monthlySales as $row): ?>
                            <tr>
                                <td><?= e($row['month_label']) ?></td>
                                <td class="text-end"><?= $row['orders'] ?></td>
                                <td class="text-end fw-bold text-success">₹<?= number_format($row['revenue'],2) ?></td>
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
(function(){
    const labels   = <?= json_encode(array_column($monthlySales,'month_label')) ?>;
    const revenues = <?= json_encode(array_map(fn($r)=>(float)$r['revenue'],$monthlySales)) ?>;
    const orders   = <?= json_encode(array_map(fn($r)=>(int)$r['orders'],$monthlySales)) ?>;
    const ctx      = document.getElementById('monthlyChart');
    if (!ctx) return;
    new Chart(ctx,{
        type:'bar',
        data:{
            labels,
            datasets:[
                { label:'Revenue (₹)', data:revenues, backgroundColor:'rgba(13,202,240,0.7)', yAxisID:'y' },
                { label:'Orders', data:orders, type:'line', borderColor:'#ffc107',
                  backgroundColor:'rgba(255,193,7,0.2)', tension:0.4, yAxisID:'y1' }
            ]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{
                y:{beginAtZero:true, position:'left'},
                y1:{beginAtZero:true, position:'right', grid:{drawOnChartArea:false}}
            }
        }
    });
})();
</script>

<?php elseif ($reportType === 'top_products'): ?>

<div class="card">
    <div class="card-header fw-bold">Top Products – <?= e($dateFrom) ?> to <?= e($dateTo) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>#</th><th>Product</th><th>Brand</th><th>Category</th>
                    <th class="text-end">Qty Sold</th><th class="text-end">Revenue</th></tr></thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No data</td></tr>
                    <?php else: foreach ($topProducts as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($p['product_name']) ?></strong></td>
                        <td><?= e($p['brand'] ?? '–') ?></td>
                        <td><span class="badge bg-secondary"><?= e($p['category']) ?></span></td>
                        <td class="text-end"><?= $p['total_qty'] ?></td>
                        <td class="text-end fw-bold text-success">₹<?= number_format($p['total_revenue'],2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'inventory'): ?>

<?php
$totalStockValue = array_sum(array_column($inventoryData, 'stock_value'));
?>
<div class="alert alert-info py-2">
    <strong>Total Inventory Value: ₹<?= number_format($totalStockValue, 2) ?></strong>
</div>
<div class="card">
    <div class="card-header fw-bold">Inventory Report</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>#</th><th>Product</th><th>Category</th>
                    <th class="text-end">Price</th><th class="text-center">Stock</th>
                    <th class="text-end">Value</th></tr></thead>
                <tbody>
                    <?php foreach ($inventoryData as $p): ?>
                    <tr class="<?= $p['stock_quantity'] <= LOW_STOCK_THRESHOLD ? 'low-stock-row' : '' ?>">
                        <td><?= $p['product_id'] ?></td>
                        <td><?= e($p['product_name']) ?></td>
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
                        <td class="text-end fw-bold">₹<?= number_format($p['stock_value'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="5" class="text-end">Total Inventory Value</td>
                        <td class="text-end">₹<?= number_format($totalStockValue,2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'services'): ?>

<div class="card">
    <div class="card-header fw-bold">Service Requests – <?= e($dateFrom) ?> to <?= e($dateTo) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>#</th><th>Customer</th><th>Service</th><th>Status</th>
                    <th>Employee</th><th>Scheduled</th><th>Created</th></tr></thead>
                <tbody>
                    <?php if (empty($serviceData)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No data</td></tr>
                    <?php else: foreach ($serviceData as $sr): ?>
                    <?php $sc=['Pending'=>'warning','Assigned'=>'info','In Progress'=>'primary','Completed'=>'success'];
                          $col=$sc[$sr['status']]??'secondary'; ?>
                    <tr>
                        <td>#<?= $sr['request_id'] ?></td>
                        <td><?= e($sr['customer_name']) ?></td>
                        <td><?= e($sr['service_type']) ?></td>
                        <td><span class="badge bg-<?= $col ?>"><?= e($sr['status']) ?></span></td>
                        <td><?= e($sr['emp_name'] ?? '–') ?></td>
                        <td><?= $sr['scheduled_date'] ? date('d M Y',strtotime($sr['scheduled_date'])) : '–' ?></td>
                        <td><?= date('d M Y',strtotime($sr['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
