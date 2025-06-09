<?php
/**
 * API Endpoint: /api/v1/delete_group.php
 *
 * Description:
 * Deletes a specified group and removes all its members from the group_members table.
 * This endpoint uses POST for the delete operation to allow a JSON body.
 *
 * Request Method:
 * POST
 *
 * Input Parameters (JSON Body):
 *  - group_id (string, required): The ID of the group to delete.
 *
 * Example JSON Input:
 * {
 *   "group_id": "grp_abc123"
 * }
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  Indicates the group and its member associations were successfully deleted.
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "message": "Group deleted successfully."
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Invalid JSON input, or missing/empty 'group_id'.
 *    JSON Response: {"status": "error", "message": "Specific error message."}
 *  - 404 Not Found: If the specified 'group_id' does not exist in the 'groups' table.
 *    JSON Response: {"status": "error", "message": "Group not found."}
 *  - 405 Method Not Allowed: If the request method is not POST.
 *    JSON Response: {"status": "error", "message": "Method Not Allowed"} (Note: actual message might vary based on implementation)
 *  - 500 Internal Server Error: If a database error occurs during the transaction.
 *    JSON Response: {"status": "error", "message": "Database error occurred while deleting group."}
 *
 * Database Interaction:
 * - Begins a database transaction.
 * - Deletes all records from 'group_members' associated with the given 'group_id'.
 * - Deletes the record from the 'groups' table corresponding to the 'group_id'.
 * - If the group is not found in 'groups' (rowCount is 0), the transaction is rolled back.
 * - Commits the transaction if both deletions are successful. Rolls back on any PDOException.
 */

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../config/config.php';

// Set content type
header('Content-Type: application/json');

// 1. Ensure that the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Method Not Allowed', 405);
    exit; // send_json_error usually exits, but good to be explicit if changing its behavior
}

// 2. Decode the JSON input from php://input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error('Invalid JSON input', 400);
    exit;
}

// 3. Check for the presence of group_id
if (empty($input['group_id'])) {
    send_json_error('Group ID is required.', 400);
    exit;
}

$group_id = $input['group_id'];

// 4. Establish a database connection
$pdo = get_db_connection();

// 11. Wrap database operations in a try-catch block (already present)
try {
    // 5. Begin a database transaction
    $pdo->beginTransaction();

    // 6. Delete from group_members table
    $stmt_members = $pdo->prepare("DELETE FROM group_members WHERE group_id = :group_id");
    $stmt_members->bindParam(':group_id', $group_id);
    $stmt_members->execute();

    // 7. Delete from groups table
    $stmt_group = $pdo->prepare("DELETE FROM groups WHERE group_id = :group_id");
    $stmt_group->bindParam(':group_id', $group_id);
    $stmt_group->execute();

    // 8. Check affected rows for group deletion
    if ($stmt_group->rowCount() === 0) {
        // Group not found, roll back
        $pdo->rollBack();
        send_json_error('Group not found.', 404);
        exit;
    }

    // 9. Commit the transaction
    $pdo->commit();

    // 10. Send success response
    send_json_success(['message' => 'Group deleted successfully.'], 200);

} catch (PDOException $e) {
    // Roll back the transaction if active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log error details (optional, for server-side logging)
    // error_log("Database error in delete_group.php: " . $e->getMessage());
    send_json_error('Database error occurred while deleting group.', 500);
    // No exit needed if send_json_error handles it
}
?>
