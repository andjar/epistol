<?php
require_once '../../src/db.php';
require_once '../../src/api_utils.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error('Method not allowed', 405);
}

try {
    $pdo = get_db_connection();
    
    // Get type parameter (sender or recipient)
    $type = $_GET['type'] ?? 'all';
    
    // Build query based on type
    if ($type === 'sender') {
        // Get users who have sent emails
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                p.id,
                p.name,
                ea.email_address as email,
                COUNT(e.id) as email_count
            FROM persons p
            INNER JOIN email_addresses ea ON p.id = ea.person_id
            INNER JOIN users u ON p.id = u.person_id
            INNER JOIN emails e ON u.id = e.user_id
            GROUP BY p.id, p.name, ea.email_address
            ORDER BY email_count DESC, p.name ASC
            LIMIT 50
        ");
        $stmt->execute();
    } elseif ($type === 'recipient') {
        // Get users who have received emails
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                p.id,
                p.name,
                ea.email_address as email,
                COUNT(er.email_id) as email_count
            FROM persons p
            INNER JOIN email_addresses ea ON p.id = ea.person_id
            INNER JOIN email_recipients er ON p.id = er.person_id
            GROUP BY p.id, p.name, ea.email_address
            ORDER BY email_count DESC, p.name ASC
            LIMIT 50
        ");
        $stmt->execute();
    } else {
        // Get all users
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                ea.email_address as email,
                COALESCE(sent_count, 0) + COALESCE(received_count, 0) as total_emails
            FROM persons p
            INNER JOIN email_addresses ea ON p.id = ea.person_id
            LEFT JOIN (
                SELECT u.person_id, COUNT(*) as sent_count
                FROM emails e
                INNER JOIN users u ON e.user_id = u.id
                GROUP BY u.person_id
            ) sent ON p.id = sent.person_id
            LEFT JOIN (
                SELECT er.person_id, COUNT(*) as received_count
                FROM email_recipients er
                GROUP BY er.person_id
            ) received ON p.id = received.person_id
            ORDER BY total_emails DESC, p.name ASC
            LIMIT 50
        ");
        $stmt->execute();
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formatted_users = array_map(function($user) {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'email_count' => $user['email_count'] ?? $user['total_emails'] ?? 0
        ];
    }, $users);
    
    send_json_success($formatted_users);
    
} catch (PDOException $e) {
    error_log('Database error in get_users.php: ' . $e->getMessage());
    send_json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Error in get_users.php: ' . $e->getMessage());
    send_json_error('Server error: ' . $e->getMessage(), 500);
}
?> 