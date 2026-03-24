<?php
/**
 * Database Configuration
 * Surveillance Shop Management System
 */

define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'surveillance_shop');
define('DB_CHARSET',  'utf8mb4');

// Application settings
define('APP_NAME',    'Surveillance Shop');
define('APP_URL',     'http://localhost/surveillance-shop');
define('LOW_STOCK_THRESHOLD', 5);

/**
 * Get a MySQLi database connection (singleton pattern).
 *
 * @return mysqli
 */
function getDB(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            // In production, log this error rather than displaying it
            error_log('Database connection failed: ' . $conn->connect_error);
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please contact the administrator.'
            ]));
        }

        $conn->set_charset(DB_CHARSET);
    }

    return $conn;
}
