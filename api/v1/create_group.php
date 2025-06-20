<?php
/**
 * API Endpoint: /api/v1/create_group.php
 *
 * Description:
 * Creates a new group with a specified name and optionally adds initial members.
 * A unique group_id is generated by the server.
 *
 * Request Method:
 * POST
 *
 * Input Parameters (JSON Body):
 *  - group_name (string, required): The name for the new group.
 *  - member_person_ids (array of strings, optional): An array of person_ids to initially add to the group.
 *    Each person_id should be a non-empty string.
 *
 * Example JSON Input:
 * {
 *   "group_name": "Project Alpha Team",
 *   "member_person_ids": ["psn_xyz789", "psn_abc123"]
 * }
 * OR (minimal):
 * {
 *   "group_name": "Book Club"
 * }
 *
 * Outputs:
 *
 *  Success (201 Created):
 *  Indicates the group was successfully created.
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "message": "Group created successfully.",
 *      "group_id": "grp_generated_uuid_string",
 *      "group_name": "The Group Name Provided"
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Invalid JSON input, missing/empty 'group_name', or invalid 'member_person_ids' structure/content.
 *    JSON Response: {"status": "error", "message": "Specific error message."}
 *  - 405 Method Not Allowed: If the request method is not POST.
 *    JSON Response: {"status": "error", "message": "Invalid request method. Only POST requests are allowed."}
 *  - 409 Conflict: If a group with the same 'group_name' already exists.
 *    JSON Response: {"status": "error", "message": "A group with the name 'Provided Group Name' already exists."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred while creating the group."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Begins a database transaction.
 * - Inserts a new record into the 'groups' table with the provided name, a generated group_id, and timestamps.
 *   'created_by_person_id' is currently set to NULL.
 * - If 'member_person_ids' are provided:
 *   - Validates each person_id against the 'persons' table. If a person_id is not found, it's logged and skipped.
 *   - Inserts records into the 'group_members' table to link valid persons to the new group.
 *   - Handles unique constraint violations for existing members silently (logs and continues).
 * - Commits the transaction if all operations are successful. Rolls back on error.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

// Set default content type
header('Content-Type: application/json');

// Ensure the script only accepts POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
}

$pdo = null; // Initialize PDO variable for visibility in catch block

try {
    // Input Parameters (from JSON body)
    $input_data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_error('Invalid JSON input: ' . json_last_error_msg(), 400);
    }

    // Validate group_name
    if (!isset($input_data['group_name']) || trim($input_data['group_name']) === '') {
        send_json_error('Group name is required.', 400);
    }
    $group_name = trim($input_data['group_name']);

    // Validate member_person_ids (optional)
    $member_person_ids = [];
    if (isset($input_data['member_person_ids'])) {
        if (!is_array($input_data['member_person_ids'])) {
            send_json_error('member_person_ids must be an array if provided.', 400);
        }
        foreach ($input_data['member_person_ids'] as $pid) {
            if (!is_string($pid) || trim($pid) === '') { // Assuming person_id is a string (like UUID)
                send_json_error('Each person_id in member_person_ids must be a non-empty string.', 400);
            }
            $member_person_ids[] = trim($pid);
        }
        $member_person_ids = array_unique($member_person_ids); // Remove duplicates
    }

    // Database Interaction
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Placeholder for current user ID
    $current_user_id = null; // Set to NULL or actual user ID if available. Schema dependent.
                             // Assuming groups.created_by_person_id allows NULL or has a default.

    // Create Group
    $group_id = "grp_" . bin2hex(random_bytes(16));
    $created_at = date('Y-m-d H:i:s');
    $updated_at = $created_at;

    $stmt_create_group = $pdo->prepare(
        "INSERT INTO groups (group_id, name, created_by_person_id, created_at, updated_at)
         VALUES (:group_id, :name, :creator_id, :created_at, :updated_at)"
    );
    try {
        $stmt_create_group->execute([
            'group_id' => $group_id,
            'name' => $group_name,
            'creator_id' => $current_user_id,
            'created_at' => $created_at,
            'updated_at' => $updated_at
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == '23000' || $e->getCode() == '23505') {
            $pdo->rollBack();
            send_json_error("A group with the name '" . htmlspecialchars($group_name) . "' already exists.", 409);
        } else {
            throw $e;
        }
    }

    // Add Members (if provided)
    if (!empty($member_person_ids)) {
        $stmt_add_member = $pdo->prepare(
            "INSERT INTO group_members (group_id, person_id, joined_at) VALUES (:group_id, :person_id, :joined_at)"
        );
        $stmt_check_person = $pdo->prepare("SELECT person_id FROM persons WHERE person_id = :person_id");
        $member_joined_at = date('Y-m-d H:i:s');

        foreach ($member_person_ids as $person_id) {
            $stmt_check_person->execute(['person_id' => $person_id]);
            if (!$stmt_check_person->fetch()) {
                error_log("Attempted to add non-existent person_id '{$person_id}' to group '{$group_id}'. Skipping.");
                continue;
            }

            try {
                $stmt_add_member->execute(['group_id' => $group_id, 'person_id' => $person_id, 'joined_at' => $member_joined_at]);
            } catch (PDOException $e) {
                if ($e->getCode() == '23000' || $e->getCode() == '23505') {
                    error_log("Person_id '{$person_id}' already in group '{$group_id}' or other unique constraint: " . $e->getMessage());
                } else {
                    throw $e;
                }
            }
        }
    }

    $pdo->commit();

    // Success Response
    send_json_success([
        "message" => "Group created successfully.",
        "group_id" => $group_id,
        "group_name" => $group_name
    ], 201); // HTTP 201 Created

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PDOException in create_group.php: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    send_json_error('A database error occurred while creating the group.', 500);
} catch (Exception $e) {
    error_log("General Exception in create_group.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

?>
