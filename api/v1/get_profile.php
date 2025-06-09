<?php
/**
 * API Endpoint: /api/v1/get_profile.php
 *
 * Description:
 * Fetches the profile information for a specified person.
 * This includes basic details, associated email addresses, and a placeholder for associated threads.
 *
 * Request Method:
 * GET
 *
 * Input Parameters ($_GET):
 *  - person_id (string, required): The ID of the person whose profile is to be fetched.
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "id": "psn_xyz789",
 *      "name": "John Doe",
 *      "avatar_url": "/avatars/psn_xyz789.png",
 *      "bio": "Software developer and coffee enthusiast.",
 *      "created_at": "YYYY-MM-DD HH:MM:SS",
 *      "email_addresses": [
 *        {"email": "john.doe@example.com", "is_primary": true},
 *        {"email": "jd@work.com", "is_primary": false}
 *      ],
 *      "threads": [] // Placeholder; actual implementation for threads would list associated thread summaries.
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Missing or empty 'person_id'.
 *    JSON Response: {"status": "error", "message": "Person ID is required."}
 *  - 404 Not Found: If the specified 'person_id' does not exist in the 'persons' table.
 *    JSON Response: {"status": "error", "message": "Profile not found."}
 *  - 405 Method Not Allowed: If the request method is not GET.
 *    JSON Response: {"status": "error", "message": "Only GET requests are allowed."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred while fetching the profile."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Fetches core details (person_id, name, bio, avatar_url, created_at) from the 'persons' table for the given 'person_id'.
 * - Fetches associated email addresses (email_address, is_primary) from the 'email_addresses' table.
 * - Currently, 'threads' data is a placeholder. A full implementation would query threads involving this person.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error('Only GET requests are allowed.', 405);
}

if (!isset($_GET['person_id']) || empty(trim($_GET['person_id']))) {
    send_json_error('Person ID is required.', 400);
}
$person_id = trim($_GET['person_id']);

try {
    $pdo = get_db_connection();

    // Fetch person details
    $stmt_person = $pdo->prepare(
        "SELECT person_id, name, bio, avatar_url, created_at
         FROM persons
         WHERE person_id = :person_id"
    );
    $stmt_person->bindParam(':person_id', $person_id);
    $stmt_person->execute();
    $person_data = $stmt_person->fetch(PDO::FETCH_ASSOC);

    if (!$person_data) {
        send_json_error('Profile not found.', 404);
    }

    // Fetch email addresses for the person
    $stmt_emails = $pdo->prepare(
        "SELECT email_address, is_primary
         FROM email_addresses
         WHERE person_id = :person_id
         ORDER BY is_primary DESC, email_address ASC"
    );
    $stmt_emails->bindParam(':person_id', $person_id);
    $stmt_emails->execute();
    $email_addresses = $stmt_emails->fetchAll(PDO::FETCH_ASSOC);

    // Prepare the final profile data
    $profile_output = [
        'id' => $person_data['person_id'],
        'name' => $person_data['name'],
        'avatar_url' => $person_data['avatar_url'],
        'bio' => $person_data['bio'],
        'created_at' => $person_data['created_at'],
        'email_addresses' => array_map(function($email) {
            return [
                'email' => $email['email_address'],
                'is_primary' => (bool)$email['is_primary']
            ];
        }, $email_addresses),
        'threads' => [] // Placeholder for associated threads
                         // TODO: Implement fetching associated threads
    ];

    send_json_success($profile_output);

} catch (PDOException $e) {
    error_log("PDOException in get_profile.php: " . $e->getMessage());
    send_json_error('A database error occurred while fetching the profile.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_profile.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}
?>
