<?php
/**
 * Sidebar navigation.
 * Automatically highlights the active menu item based on the current script name.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

/**
 * Helper to output "active" class if the page matches.
 */
function navActive(string $page, string $current): string {
    return ($page === $current) ? 'active' : '';
}
$basePath = getBasePath();
?>
<nav class="sidebar bg-dark text-white" id="sidebar">
    <div class="sidebar-header py-3 px-3 border-bottom border-secondary">
        <h6 class="text-uppercase text-muted small fw-bold mb-0">Main Menu</h6>
    </div>

    <ul class="nav flex-column mt-2">

        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('dashboard.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>

        <!-- Products -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('products.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/products.php">
                <i class="bi bi-camera-video me-2"></i> Products
            </a>
        </li>

        <!-- Customers -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('customers.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/customers.php">
                <i class="bi bi-people me-2"></i> Customers
            </a>
        </li>

        <!-- Sales -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('sales.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/sales.php">
                <i class="bi bi-receipt me-2"></i> Sales / Invoices
            </a>
        </li>

        <!-- Inventory -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('inventory.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/inventory.php">
                <i class="bi bi-box-seam me-2"></i> Inventory
            </a>
        </li>

        <!-- Service Requests -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('services.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/services.php">
                <i class="bi bi-tools me-2"></i> Service Requests
            </a>
        </li>

        <!-- Employees (Admin only) -->
        <?php if (isAdmin()): ?>
        <li class="nav-item">
            <a class="nav-link <?= navActive('employees.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/employees.php">
                <i class="bi bi-person-badge me-2"></i> Employees
            </a>
        </li>
        <?php endif; ?>

        <!-- Reports -->
        <li class="nav-item">
            <a class="nav-link <?= navActive('reports.php', $currentPage) ?>"
               href="<?= $basePath ?>/admin/reports.php">
                <i class="bi bi-bar-chart-line me-2"></i> Reports
            </a>
        </li>

        <li class="nav-item mt-3">
            <hr class="border-secondary">
        </li>

        <!-- Logout -->
        <li class="nav-item">
            <a class="nav-link text-danger" href="<?= $basePath ?>/auth/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </li>
    </ul>

    <div class="sidebar-footer p-3 mt-auto border-top border-secondary">
        <small class="text-muted">Logged in as<br>
            <strong class="text-white"><?= e($_SESSION['user_name'] ?? '') ?></strong>
        </small>
    </div>
</nav>
