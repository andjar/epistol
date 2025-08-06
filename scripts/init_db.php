<?php

/**
 * Database Initialization Script
 * 
 * This script creates the SQLite database and executes the schema SQL.
 * Run this script to set up the database for the first time.
 */

// Include the configuration
require_once __DIR__ . '/../config/config.php';

// Define the database path (using the path from config)
$dbPath = DB_PATH;

// Create the storage directory if it doesn't exist
$storageDir = dirname($dbPath);
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0755, true)) {
        die("Error: Could not create storage directory: $storageDir\n");
    }
    echo "Created storage directory: $storageDir\n";
}

// Check if database already exists
if (file_exists($dbPath)) {
    echo "Database already exists at: $dbPath\n";
    echo "Do you want to recreate it? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Database initialization cancelled.\n";
        exit(0);
    }
    
    // Remove existing database
    unlink($dbPath);
    echo "Removed existing database.\n";
}

try {
    // Create new SQLite database
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Created new SQLite database at: $dbPath\n";
    
    // Read and execute the schema SQL
    $schemaFile = __DIR__ . '/../db/schema.sql';
    if (!file_exists($schemaFile)) {
        die("Error: Schema file not found at: $schemaFile\n");
    }
    
    $schemaSQL = file_get_contents($schemaFile);
    
    // Execute the schema SQL
    $pdo->exec($schemaSQL);
    
    echo "Successfully executed database schema.\n";
    echo "Database initialization completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}

?> 