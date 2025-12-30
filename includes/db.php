<?php
/**
 * includes/db.php
 * Legacy compatibility wrapper for getDB()
 * Delegates DB access to config.php (MYSQL_URL)
 */

if (!function_exists('getDB')) {

    function getDB(): PDO
    {
        // Ensure main config is loaded
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            require_once __DIR__ . '/../config.php';
        }

        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            throw new RuntimeException('PDO not initialized in config.php');
        }

        return $GLOBALS['pdo'];
    }

}
