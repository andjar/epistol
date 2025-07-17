<?php
/**
 * API Endpoint: /api/v1/set_post_status.php
 *
 * Description:
 * Sets or updates the status for a specific post (typically an email) for a given user.
 * If a status record already exists for the post-user combination, it's updated.
 * Otherwise, a new status record is created.
 * This endpoint uses POST.
 *
 * Request Method:
 * POST
 *
 * Input Parameters (JSON Body):
 *  - post_id (integer, required): The ID of the post (email) for which to set the status.
 *  - user_id (integer, required): The ID of the user for whom this status applies.
 *  - status (string, required): The status to set. Allowed values: 'read', 'follow-up', 'important-info', 'unread'.
 *
 * Example JSON Input:
 * {
 *   "post_id": 123,
 *   "user_id": 456,
 *   "status": "read"
 * }
 *
 * Outputs:
 *
 *  Success:
 *  - 200 OK (if status updated):
 *    JSON Response: {"status": "success", "data": {"message": "Post status updated successfully."}}
 *  - 201 Created (if status created):
 *    JSON Response: {"status": "success", "data": {"message": "Post status created successfully.", "id": "generated_status_id"}}
 *
 *  Failure:
 *  - 400 Bad Request: Invalid JSON, missing required fields, invalid 'status' value, or non-numeric IDs.
 *    JSON Response: {"status": "error", "message": "Specific error message."}
 *  - 405 Method Not Allowed: If the request method is not POST.
 *    JSON Response: {"status": "error", "message": "Only POST requests are allowed"}
 *  - 500 Internal Server Error: Database errors (e.g., failed to update/insert) or other unexpected server issues.
 *    JSON Response: {"status": "error", "message": "Database error: Specific DB message"} or
 *                   {"status": "error", "message": "Failed to update post status."} or
 *                   {"status": "error", "message": "Failed to create post status."} or
 *                   {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Checks if a record exists in 'post_statuses' for the given 'post_id' and 'user_id'.
 * - If exists, UPDATEs the 'status' and 'updated_at' timestamp for that record.
 * - If not exists, INSERTs a new record with 'post_id', 'user_id', 'status', 'created_at', and 'updated_at'.
 * - Assumes 'post_id' links to an email's ID and 'user_id' links to a person's ID.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Only POST requests are allowed', 405);
}

// Get input data
$raw_input = isset($GLOBALS['mock_php_input']) ? $GLOBALS['mock_php_input'] : file_get_contents('php://input');
$data = json_decode($raw_input, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE && empty($GLOBALS['mock_php_input'])) {
    send_json_error('Invalid JSON input: ' . json_last_error_msg(), 400);
}

// Validate input parameters
$email_id = $data['email_id'] ?? null;
$user_id = $data['user_id'] ?? null; // Assuming user_id is integer as per validation
$status = $data['status'] ?? null;

$allowed_statuses = ['read', 'follow-up', 'important-info', 'unread'];

if (empty($email_id) || !is_numeric($email_id)) {
    send_json_error('email_id is required and must be a number.', 400);
}

if (empty($user_id) || !is_numeric($user_id)) {
    send_json_error('user_id is required and must be a number.', 400);
}

if (empty($status) || !in_array($status, $allowed_statuses)) {
    send_json_error('status is required and must be one of: ' . implode(', ', $allowed_statuses), 400);
}

// Database connection
try {
    $pdo = get_db_connection();

    // Check if a status already exists for this email_id and user_id
    // Assuming email_statuses table has 'id' as PK, 'email_id', 'user_id', 'status', 'created_at', 'updated_at'
    $stmt_check = $pdo->prepare("SELECT id FROM email_statuses WHERE email_id = :email_id AND user_id = :user_id");
    $stmt_check->bindParam(':email_id', $email_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $existing_status = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existing_status) {
        // Update existing status
        $stmt_update = $pdo->prepare("UPDATE email_statuses SET status = :status, updated_at = :updated_at WHERE id = :id");
        $stmt_update->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt_update->bindParam(':id', $existing_status['id'], PDO::PARAM_INT);
        $stmt_update->bindValue(':updated_at', date('Y-m-d H:i:s'));

        if ($stmt_update->execute()) {
            send_json_success(['message' => 'Post status updated successfully.']); // Defaults to 200 OK
        } else {
            send_json_error('Failed to update post status.', 500);
        }
    } else {
        // Insert new status
        $stmt_insert = $pdo->prepare("INSERT INTO email_statuses (email_id, user_id, status, created_at, updated_at) VALUES (:email_id, :user_id, :status, :created_at, :updated_at)");
        $stmt_insert->bindParam(':email_id', $email_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':status', $status, PDO::PARAM_STR);
        $current_time = date('Y-m-d H:i:s');
        $stmt_insert->bindParam(':created_at', $current_time);
        $stmt_insert->bindParam(':updated_at', $current_time);

        if ($stmt_insert->execute()) {
            send_json_success(['message' => 'Post status created successfully.', 'id' => $pdo->lastInsertId()], 201);
        } else {
            send_json_error('Failed to create post status.', 500);
        }
    }

} catch (PDOException $e) {
    error_log("Database error in set_post_status.php: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    send_json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("General error in set_post_status.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

?>
