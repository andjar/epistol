<?php
require_once 'config/config.php';
require_once 'src/db.php';

try {
    $pdo = get_db_connection();
    
    echo "Database connection successful!\n\n";
    
    // Check if email_statuses table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='email_statuses'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ email_statuses table exists\n";
        
        // Check table structure
        $stmt = $pdo->query("PRAGMA table_info(email_statuses)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Table structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column['name']} ({$column['type']})\n";
        }
        
        // Check if there are any records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_statuses");
        $count = $stmt->fetch()['count'];
        echo "\nRecords in email_statuses: {$count}\n";
        
    } else {
        echo "✗ email_statuses table does NOT exist\n";
        
        // List all tables
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Available tables:\n";
        foreach ($tables as $table) {
            echo "  - {$table['name']}\n";
        }
    }
    
    // Check if groups table exists and has data
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='groups'");
    $groupsTableExists = $stmt->fetch();
    
    if ($groupsTableExists) {
        echo "\n✓ groups table exists\n";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM groups");
        $count = $stmt->fetch()['count'];
        echo "Records in groups: {$count}\n";
        
        if ($count > 0) {
            $stmt = $pdo->query("SELECT id, name FROM groups LIMIT 5");
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Sample groups:\n";
            foreach ($groups as $group) {
                echo "  - ID: {$group['id']}, Name: {$group['name']}\n";
            }
        }
    } else {
        echo "\n✗ groups table does NOT exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 