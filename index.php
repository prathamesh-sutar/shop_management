<?php
/**
 * Entry point – redirect to login or dashboard
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: admin/dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;
