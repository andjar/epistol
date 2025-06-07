<?php

// It's crucial that config/config.php is included before this function is called.
// We'll assume it's included by any script that uses get_db_connection.
// require_once __DIR__ . '/../config/config.php'; // Or handle this in the calling script

function get_db_connection()
{
    // Ensure DB_PATH is defined (should come from config/config.php)
    if (!defined('DB_PATH')) {
        // This is a critical configuration error.
        // In a real application, you might log this or handle it more gracefully.
        throw new Exception("DB_PATH is not defined. Please check your configuration.");
    }

    $dsn = 'sqlite:' . DB_PATH;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Optional: set default fetch mode
        PDO::ATTR_EMULATE_PREPARES   => false, // Optional: use native prepared statements
    ];

    try {
        $pdo = new PDO($dsn, null, null, $options);
        return $pdo;
    } catch (PDOException $e) {
        // In a real application, you might log the error message ($e->getMessage())
        // and show a more user-friendly error.
        // For now, re-throwing the exception is fine for this subtask.
        throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
    }
}
?>
