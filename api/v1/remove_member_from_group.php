<?php
/**
 * API Endpoint: /api/v1/remove_member_from_group.php
 *
 * Description:
 * Removes a specified person (member) from a specified group.
 * This endpoint uses POST for the operation to allow a JSON body.
 *
 * Request Method:
 * POST
 *
 * Input Parameters (JSON Body):
 *  - group_id (string, required): The ID of the group from which to remove the member.
 *  - person_id (string, required): The ID of the person to remove from the group.
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
 *  Indicates the member was successfully removed or was not in the group to begin with.
 *  The operation is idempotent in terms of the member's absence from the group.
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "message": "Member removed or was not in group."
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Invalid JSON input, or missing/empty 'group_id' or 'person_id'.
 *    JSON Response: {"status": "error", "message": "Specific error message."}
 *  - 404 Not Found: If the specified 'group_id' or 'person_id' itself does not exist in their respective tables.
 *    JSON Response: {"status": "error", "message": "Group not found."} or {"status": "error", "message": "Person not found."}
 *  - 405 Method Not Allowed: If the request method is not POST.
 *    JSON Response: {"status": "error", "message": "Invalid request method. Only POST requests are allowed."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred while removing member."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Validates the existence of the 'group_id' in the 'groups' table.
 * - Validates the existence of the 'person_id' in the 'persons' table.
 * - Executes a DELETE query on the 'group_members' table for the matching 'group_id' and 'person_id'.
 * - The operation is considered successful even if the member was not found in the group (rowCount is 0 for the DELETE).
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

// Set content type
header('Content-Type: application/json');

// 1. Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
}

try {
    // 2. Decode JSON input
    $input_data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_error('Invalid JSON input: ' . json_last_error_msg(), 400);
    }

    // 3. Validate presence of group_id and person_id
    if (!isset($input_data['group_id']) || trim($input_data['group_id']) === '') {
        send_json_error('Group ID is required.', 400);
    }
    $group_id = trim($input_data['group_id']);

    if (!isset($input_data['person_id']) || trim($input_data['person_id']) === '') {
        send_json_error('Person ID is required.', 400);
    }
    $person_id = trim($input_data['person_id']);

    // 4. Connect to DB
    $pdo = get_db_connection();

    // 5. (Optional but good practice) Validate group_id and person_id existence
    $stmt_check_group = $pdo->prepare("SELECT group_id FROM groups WHERE group_id = :group_id");
    $stmt_check_group->execute(['group_id' => $group_id]);
    if (!$stmt_check_group->fetch()) {
        send_json_error('Group not found.', 404);
    }

    $stmt_check_person = $pdo->prepare("SELECT person_id FROM persons WHERE person_id = :person_id");
    $stmt_check_person->execute(['person_id' => $person_id]);
    if (!$stmt_check_person->fetch()) {
        send_json_error('Person not found.', 404);
    }

    // 6. Execute a DELETE query on group_members table
    $stmt_remove_member = $pdo->prepare(
        "DELETE FROM group_members WHERE group_id = :group_id AND person_id = :person_id"
    );
    $stmt_remove_member->execute(['group_id' => $group_id, 'person_id' => $person_id]);

    // 7. Check the number of affected rows. If zero, member was not in the group.
    // This is treated as a success ("Member removed or was not in group.").
    // $affected_rows = $stmt_remove_member->rowCount();

    // 8. Use send_json_success
    send_json_success(['message' => 'Member removed or was not in group.']);

} catch (PDOException $e) {
    error_log("PDOException in remove_member_from_group.php: " . $e->getMessage());
    // 9. Handle PDOExceptions
    send_json_error('A database error occurred while removing member.', 500);
} catch (Exception $e) {
    error_log("General Exception in remove_member_from_group.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

?>
