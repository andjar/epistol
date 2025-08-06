<?php
/**
 * API Endpoint: /api/v1/update_profile.php
 * 
 * Description: Updates a user's profile information including name and email addresses
 * 
 * Request Method: POST
 * 
 * Input Parameters (JSON Body):
 * - person_id (string, required): The ID of the person to update
 * - name (string, optional): The person's name
 * - email_addresses (array, optional): Array of email addresses to add/update
 * 
 * Example JSON Input:
 * {
 *   "person_id": "psn_123",
 *   "name": "John Doe",
 *   "email_addresses": [
 *     {"email": "john@example.com", "is_primary": true},
 *     {"email": "john.doe@work.com", "is_primary": false}
 *   ]
 * }
 */

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
}

try {
    $input_data = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_error('Invalid JSON input: ' . json_last_error_msg(), 400);
    }
    
    // Validate required fields
    if (!isset($input_data['person_id']) || trim($input_data['person_id']) === '') {
        send_json_error('Person ID is required.', 400);
    }
    
    $person_id = trim($input_data['person_id']);
    $name = isset($input_data['name']) ? trim($input_data['name']) : null;
    $email_addresses = isset($input_data['email_addresses']) ? $input_data['email_addresses'] : [];
    
    // Validate email addresses
    if (!is_array($email_addresses)) {
        send_json_error('email_addresses must be an array.', 400);
    }
    
    foreach ($email_addresses as $email_data) {
        if (!isset($email_data['email']) || !filter_var($email_data['email'], FILTER_VALIDATE_EMAIL)) {
            send_json_error('Invalid email address provided.', 400);
        }
    }
    
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    // Check if person exists
    $stmt_check = $pdo->prepare("SELECT id FROM persons WHERE id = ?");
    $stmt_check->execute([$person_id]);
    if (!$stmt_check->fetch()) {
        $pdo->rollBack();
        send_json_error('Person not found.', 404);
    }
    
    // Update person name if provided
    if ($name !== null) {
        $stmt_update_name = $pdo->prepare("UPDATE persons SET name = ? WHERE id = ?");
        $stmt_update_name->execute([$name, $person_id]);
    }
    
    // Handle email addresses
    if (!empty($email_addresses)) {
        // First, remove existing email addresses for this person
        $stmt_delete_emails = $pdo->prepare("DELETE FROM email_addresses WHERE person_id = ?");
        $stmt_delete_emails->execute([$person_id]);
        
        // Add new email addresses
        $stmt_insert_email = $pdo->prepare("
            INSERT INTO email_addresses (person_id, email_address, is_primary, created_at) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($email_addresses as $email_data) {
            $is_primary = isset($email_data['is_primary']) ? (bool)$email_data['is_primary'] : false;
            $stmt_insert_email->execute([
                $person_id,
                $email_data['email'],
                $is_primary ? 1 : 0,
                date('Y-m-d H:i:s')
            ]);
        }
    }
    
    $pdo->commit();
    
    // Get updated profile data
    $stmt_get_profile = $pdo->prepare("
        SELECT p.id, p.name, p.avatar_url,
               ea.email_address, ea.is_primary
        FROM persons p
        LEFT JOIN email_addresses ea ON p.id = ea.person_id
        WHERE p.id = ?
        ORDER BY ea.is_primary DESC, ea.email_address ASC
    ");
    $stmt_get_profile->execute([$person_id]);
    $profile_data = $stmt_get_profile->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($profile_data)) {
        send_json_error('Failed to retrieve updated profile.', 500);
    }
    
    // Format the response
    $person = [
        'id' => $profile_data[0]['id'],
        'name' => $profile_data[0]['name'],
        'avatar_url' => $profile_data[0]['avatar_url'],
        'email_addresses' => []
    ];
    
    foreach ($profile_data as $row) {
        if ($row['email_address']) {
            $person['email_addresses'][] = [
                'email' => $row['email_address'],
                'is_primary' => (bool)$row['is_primary']
            ];
        }
    }
    
    send_json_success([
        'message' => 'Profile updated successfully.',
        'profile' => $person
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PDOException in update_profile.php: " . $e->getMessage());
    send_json_error('A database error occurred while updating the profile.', 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General Exception in update_profile.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}
?> 