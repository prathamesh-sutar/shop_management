<?php
/**
 * Shared HTML <head> and top navbar.
 * Include at the top of every page (after session_start / auth checks).
 *
 * Expects:
 *   $pageTitle  – string, e.g. "Dashboard"
 */
$pageTitle = $pageTitle ?? 'Surveillance Shop';
$flash = getFlash();
$basePath = getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Custom styles -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/style.css">
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm" id="topNavbar">
    <div class="container-fluid">
        <!-- Sidebar toggle -->
        <button class="btn btn-outline-secondary me-2" id="sidebarToggle" title="Toggle sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>

        <a class="navbar-brand fw-bold" href="<?= $basePath ?>/admin/dashboard.php">
            <i class="bi bi-camera-video-fill text-info me-1"></i>
            <?= APP_NAME ?>
        </a>

        <div class="ms-auto d-flex align-items-center">
            <!-- Low stock badge -->
            <a href="<?= $basePath ?>/admin/products.php?filter=low_stock"
               class="btn btn-sm btn-outline-warning me-2 d-none d-md-inline-flex"
               id="lowStockBtn" title="Low Stock Alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <span id="lowStockCount">0</span> Low Stock
            </a>

            <!-- User menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= e($_SESSION['user_name'] ?? 'User') ?>
                    <span class="badge bg-info text-dark ms-1"><?= e(ucfirst($_SESSION['user_role'] ?? '')) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <span class="dropdown-item-text text-muted small">
                            <?= e($_SESSION['user_email'] ?? '') ?>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= $basePath ?>/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Page wrapper -->
<div class="wrapper d-flex" id="pageWrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main content -->
    <main class="main-content flex-grow-1 p-4" id="mainContent">

        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
