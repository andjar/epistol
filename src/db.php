<?php

// Ensure configuration is loaded
require_once __DIR__ . '/../config/config.php';

function get_db_connection()
{
    if (!defined('DB_PATH')) {
        throw new Exception("DB_PATH is not defined. Please check your configuration in config/config.php.");
    }

    $db_path = DB_PATH;
    $db_dir = dirname($db_path);

    // Ensure the storage directory exists
    if (!is_dir($db_dir)) {
        if (!mkdir($db_dir, 0755, true)) {
            throw new Exception("Failed to create database directory: {$db_dir}");
        }
    }

    $db_exists = file_exists($db_path);

    $dsn = 'sqlite:' . $db_path;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, null, null, $options);

        if (!$db_exists) {
            // Database file did not exist, so create schema
            try {
                // Attempt to create the file first (though PDO might do this, explicit is better for schema exec)
                if (!touch($db_path)) {
                     // This might not be strictly necessary if PDO creates it, but good for clarity
                    error_log("Notice: Attempted to touch new DB file at {$db_path}");
                }

                $schema_path = __DIR__ . '/../db/schema.sql';
                if (!file_exists($schema_path)) {
                    throw new Exception("Database schema file not found at {$schema_path}");
                }
                $schema = file_get_contents($schema_path);
                if ($schema === false) {
                    throw new Exception("Failed to read schema file from {$schema_path}");
                }
                $pdo->exec($schema);
            } catch (Exception $e) {
                // If schema creation fails, it's good to remove the potentially empty/partial db file
                if (file_exists($db_path)) {
                    unlink($db_path);
                }
                throw new Exception("Failed to create and initialize database schema: " . $e->getMessage(), 0, $e);
            }
        }
        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException("Database connection or initialization failed: " . $e->getMessage(), (int)$e->getCode());
    } catch (Exception $e) { // Catch other exceptions like file access issues
        // Log this error or handle it as per application requirements
        throw new Exception("A general error occurred during database setup: " . $e->getMessage(), 0, $e);
    }
}
?>
