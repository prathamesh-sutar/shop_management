<?php
/**
 * Admin Dashboard
 * Surveillance Shop Management System
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$db = getDB();

/* ---- Summary stats ---- */
function statCount(mysqli $db, string $sql): int {
    $res = $db->query($sql);
    return $res ? (int)$res->fetch_row()[0] : 0;
}

$totalProducts  = statCount($db, "SELECT COUNT(*) FROM products");
$totalCustomers = statCount($db, "SELECT COUNT(*) FROM customers");
$totalEmployees = statCount($db, "SELECT COUNT(*) FROM employees WHERE status=1");
$totalSales     = statCount($db, "SELECT COUNT(*) FROM sales");

$res = $db->query("SELECT COALESCE(SUM(grand_total),0) FROM sales");
$totalRevenue   = $res ? (float)$res->fetch_row()[0] : 0;

$pendingServices = statCount($db,
    "SELECT COUNT(*) FROM service_requests WHERE status IN ('Pending','Assigned','In Progress')");

$lowStockProducts = statCount($db,
    "SELECT COUNT(*) FROM products WHERE stock_quantity <= " . (int)LOW_STOCK_THRESHOLD);

/* ---- Monthly sales (last 6 months) ---- */
$monthlySalesData = [];
$stmt = $db->prepare("
    SELECT DATE_FORMAT(sale_date,'%b %Y') AS month_label,
           SUM(grand_total) AS revenue,
           COUNT(*) AS orders
    FROM   sales
    WHERE  sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP  BY YEAR(sale_date), MONTH(sale_date)
    ORDER  BY YEAR(sale_date), MONTH(sale_date)
");
$stmt->execute();
$monthlyResult = $stmt->get_result();
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlySalesData[] = $row;
}
$stmt->close();

/* ---- Top products ---- */
$topProducts = [];
$stmt = $db->prepare("
    SELECT p.product_name, p.category,
           SUM(si.quantity) AS total_qty,
           SUM(si.subtotal) AS total_revenue
    FROM   sale_items si
    JOIN   products p ON p.product_id = si.product_id
    GROUP  BY si.product_id
    ORDER  BY total_qty DESC
    LIMIT  5
");
$stmt->execute();
$topResult = $stmt->get_result();
while ($row = $topResult->fetch_assoc()) {
    $topProducts[] = $row;
}
$stmt->close();

/* ---- Recent sales ---- */
$recentSales = [];
$stmt = $db->prepare("
    SELECT s.sale_id, c.name AS customer_name,
           s.grand_total, s.payment_method, s.sale_date
    FROM   sales s
    JOIN   customers c ON c.customer_id = s.customer_id
    ORDER  BY s.sale_date DESC
    LIMIT  5
");
$stmt->execute();
$recentResult = $stmt->get_result();
while ($row = $recentResult->fetch_assoc()) {
    $recentSales[] = $row;
}
$stmt->close();

/* ---- Low stock items ---- */
$lowStockItems = [];
$res = $db->query("
    SELECT product_id, product_name, category, stock_quantity
    FROM   products
    WHERE  stock_quantity <= " . (int)LOW_STOCK_THRESHOLD . "
    ORDER  BY stock_quantity ASC
    LIMIT  8
");
while ($row = $res->fetch_assoc()) {
    $lowStockItems[] = $row;
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-info"></i>Dashboard</h4>
    <span class="text-muted small"><?= date('D, d M Y') ?></span>
</div>

<!-- ---- Stat cards ---- -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-white bg-opacity-25">
                    <i class="bi bi-camera-video text-white"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalProducts ?></div>
                    <div class="small opacity-75">Products</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#198754,#146c43)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-white bg-opacity-25">
                    <i class="bi bi-people text-white"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalCustomers ?></div>
                    <div class="small opacity-75">Customers</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-white bg-opacity-25">
                    <i class="bi bi-person-badge text-white"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalEmployees ?></div>
                    <div class="small opacity-75">Employees</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#0dcaf0,#087990)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-white bg-opacity-25">
                    <i class="bi bi-receipt text-white"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $totalSales ?></div>
                    <div class="small opacity-75">Sales</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#fd7e14,#ca6510)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-white bg-opacity-25">
                    <i class="bi bi-tools text-white"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $pendingServices ?></div>
                    <div class="small opacity-75">Pending SRs</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card text-white h-100" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon-box bg-white bg-opacity-25">
                    <i class="bi bi-exclamation-triangle text-white"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $lowStockProducts ?></div>
                    <div class="small opacity-75">Low Stock</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue banner -->
<div class="card mb-4 border-0" style="background:linear-gradient(135deg,#212529,#343a40)">
    <div class="card-body text-white text-center py-3">
        <span class="fs-5 text-muted">Total Revenue&nbsp;</span>
        <span class="fs-3 fw-bold text-info">₹<?= number_format($totalRevenue, 2) ?></span>
    </div>
</div>

<!-- ---- Charts + Top Products ---- -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-1 text-info"></i> Monthly Revenue (Last 6 Months)</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-trophy me-1 text-warning"></i> Top Selling Products
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topProducts)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No sales yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($topProducts as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <?= e($p['product_name']) ?>
                                <span class="badge bg-secondary ms-1 small"><?= e($p['category']) ?></span>
                            </td>
                            <td class="text-end"><?= $p['total_qty'] ?></td>
                            <td class="text-end text-success fw-bold">₹<?= number_format($p['total_revenue'], 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ---- Recent Sales + Low Stock ---- -->
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-receipt me-1 text-success"></i> Recent Sales</span>
                <a href="<?= getBasePath() ?>/admin/sales.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No sales yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentSales as $s): ?>
                            <tr>
                                <td><a href="sales.php?view=<?= $s['sale_id'] ?>">#<?= $s['sale_id'] ?></a></td>
                                <td><?= e($s['customer_name']) ?></td>
                                <td class="text-success fw-bold">₹<?= number_format($s['grand_total'], 2) ?></td>
                                <td><span class="badge bg-info text-dark"><?= e($s['payment_method']) ?></span></td>
                                <td><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> Low Stock Alert</span>
                <a href="<?= getBasePath() ?>/admin/products.php?filter=low_stock"
                   class="btn btn-sm btn-outline-warning">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-center">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lowStockItems)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">
                                <i class="bi bi-check-circle text-success"></i> All stocked up!
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($lowStockItems as $item): ?>
                            <tr class="low-stock-row">
                                <td><?= e($item['product_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= e($item['category']) ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $item['stock_quantity'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const labels   = <?= json_encode(array_column($monthlySalesData, 'month_label')) ?>;
    const revenues = <?= json_encode(array_map(fn($r) => (float)$r['revenue'], $monthlySalesData)) ?>;
    const orders   = <?= json_encode(array_map(fn($r) => (int)$r['orders'],  $monthlySalesData)) ?>;

    const ctx = document.getElementById('monthlySalesChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Revenue (₹)',
                    data: revenues,
                    backgroundColor: 'rgba(13,202,240,0.7)',
                    borderColor: '#0dcaf0',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: orders,
                    type: 'line',
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255,193,7,0.2)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'Revenue (₹)' } },
                y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Orders' },
                      grid: { drawOnChartArea: false } }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
