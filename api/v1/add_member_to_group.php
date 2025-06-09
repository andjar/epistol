<?php
/**
 * API Endpoint: /api/v1/add_member_to_group.php
 *
 * Description:
 * Adds a specified person (member) to a specified group.
 *
 * Request Method:
 * POST
 *
 * Input Parameters (JSON Body):
 *  - group_id (string, required): The ID of the group to add the member to.
 *  - person_id (string, required): The ID of the person to add to the group.
 *
 * Example JSON Input:
 * {
 *   "group_id": "grp_abc123",
 *   "person_id": "psn_xyz789"
 * }
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  Indicates the member was successfully added or was already a member of the group.
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "message": "Member added to group successfully."
 *    }
 *  }
 *  OR
 *  {
 *    "status": "success",
 *    "data": {
 *      "message": "Member already in group or added successfully."
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Invalid JSON input, or missing/empty 'group_id' or 'person_id'.
 *    JSON Response: {"status": "error", "message": "Specific error message."}
 *  - 404 Not Found: If the specified 'group_id' or 'person_id' does not exist.
 *    JSON Response: {"status": "error", "message": "Group not found."} or {"status": "error", "message": "Person not found."}
 *  - 405 Method Not Allowed: If the request method is not POST.
 *    JSON Response: {"status": "error", "message": "Invalid request method. Only POST requests are allowed."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Validates the existence of the group_id in the 'groups' table.
 * - Validates the existence of the person_id in the 'persons' table.
 * - Inserts a new record into the 'group_members' table, linking the person_id and group_id.
 * - Includes a 'joined_at' timestamp.
 * - Handles unique constraint violations (SQLSTATE 23000) by treating "member already exists" as a success.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

// Set default content type
header('Content-Type: application/json');

// Ensure the script only accepts POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
    // No exit needed here, send_json_error includes it.
}

try {
    // Input Parameters (from JSON body)
    $input_data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_error('Invalid JSON input: ' . json_last_error_msg(), 400);
    }

    // Validate group_id
    if (!isset($input_data['group_id']) || trim($input_data['group_id']) === '') {
        send_json_error('Group ID is required.', 400);
    }
    $group_id = trim($input_data['group_id']);

    // Validate person_id
    if (!isset($input_data['person_id']) || trim($input_data['person_id']) === '') {
        send_json_error('Person ID is required.', 400);
    }
    $person_id = trim($input_data['person_id']);


    // Database Interaction
    $pdo = get_db_connection(); // Throws exception on failure

    // --- DB Validation ---
    // Verify group_id exists
    $stmt_check_group = $pdo->prepare("SELECT group_id FROM groups WHERE group_id = :group_id");
    $stmt_check_group->execute(['group_id' => $group_id]);
    if (!$stmt_check_group->fetch()) {
        send_json_error('Group not found.', 404);
    }

    // Verify person_id exists
    $stmt_check_person = $pdo->prepare("SELECT person_id FROM persons WHERE person_id = :person_id");
    $stmt_check_person->execute(['person_id' => $person_id]);
    if (!$stmt_check_person->fetch()) {
        send_json_error('Person not found.', 404);
    }

    // --- Add Member ---
    $stmt_add_member = $pdo->prepare(
        "INSERT INTO group_members (group_id, person_id, joined_at) VALUES (:group_id, :person_id, :joined_at)"
    );
    try {
        $joined_at = date('Y-m-d H:i:s'); // Current timestamp
        $stmt_add_member->execute(['group_id' => $group_id, 'person_id' => $person_id, 'joined_at' => $joined_at]);
        // Success Response
        send_json_success([
            "message" => "Member added to group successfully."
        ]);
    } catch (PDOException $e) {
        // Check for unique constraint violation (e.g., SQLSTATE 23000 for MySQL/SQLite)
        // This indicates the member is already in the group.
        if ($e->getCode() == '23000') {
            // Member already exists, consider this a success for idempotency.
            send_json_success([
                "message" => "Member already in group or added successfully."
            ]);
        } else {
            throw $e; // Re-throw other PDO exceptions to be caught by the outer catch block
        }
    }

} catch (PDOException $e) {
    error_log("PDOException in add_member_to_group.php: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    send_json_error('A database error occurred.', 500);
} catch (Exception $e) {
    error_log("General Exception in add_member_to_group.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

?>
