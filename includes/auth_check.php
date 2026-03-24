<?php
/**
 * Authentication check middleware.
 * Include at the top of every protected page.
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/auth_check.php';
 *   requireLogin();          // any logged-in user
 *   requireRole('admin');    // admin only
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect to login page if the user is not authenticated.
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . getBasePath() . '/auth/login.php');
        exit;
    }
}

/**
 * Require a specific role; redirect to dashboard with an error if insufficient.
 *
 * @param string $role  'admin' | 'employee'
 */
function requireRole(string $role): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        $_SESSION['error'] = 'Access denied. Insufficient permissions.';
        header('Location: ' . getBasePath() . '/admin/dashboard.php');
        exit;
    }
}

/**
 * Return the application base path for redirect URLs.
 */
function getBasePath(): string {
    // Works when the project is placed in /surveillance-shop/ under document root
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up until we find the project root marker (index.php or auth/)
    $parts  = explode('/', trim($script, '/'));
    // Use the first folder segment as the base
    if (!empty($parts[0]) && $parts[0] !== 'index.php') {
        return '/' . $parts[0];
    }
    return '';
}

/**
 * Helper: is the current user an admin?
 */
function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Sanitize a string for output in HTML (XSS prevention).
 *
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Flash message helper – store a one-time message.
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message.
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
