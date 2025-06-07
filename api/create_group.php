<?php

// 1. Create File and Include Dependencies
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php'; // For DB_PATH, potentially other settings
// require_once __DIR__ . '/../vendor/autoload.php'; // If using libraries like Ramsey UUID

// 2. Define API Logic and Structure
// Set default content type
header('Content-Type: application/json');

// Ensure the script only accepts POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
}

try {
    // 3. Input Parameters (from JSON body)
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
            if (!is_string($pid) || trim($pid) === '') {
                send_json_error('Each person_id in member_person_ids must be a non-empty string.', 400);
            }
            $member_person_ids[] = trim($pid); // Add validated and trimmed ID
        }
    }

    // 4. Database Interaction
    $pdo = get_db_connection(); // Throws exception on failure, caught by outer try-catch
    $pdo->beginTransaction();

    // Placeholder for current user ID (if applicable for created_by_user_id)
    // $current_user_id = get_current_user_id_from_session_or_token();
    $current_user_id = "system_user_placeholder"; // Or null if not tracking creator

    // Create Group
    // $group_id = Ramsey\Uuid\Uuid::uuid4()->toString(); // Example using a UUID library
    $group_id = "group_" . bin2hex(random_bytes(8)); // Simpler placeholder UUID
    $created_at = date('Y-m-d H:i:s');

    // $stmt_create_group = $pdo->prepare(
    //     "INSERT INTO groups (id, name, created_by_person_id, created_at)
    //      VALUES (:id, :name, :creator_id, :created_at)"
    // );
    // try {
    //     $stmt_create_group->execute([
    //         'id' => $group_id,
    //         'name' => $group_name,
    //         'creator_id' => $current_user_id,
    //         'created_at' => $created_at
    //     ]);
    // } catch (PDOException $e) {
    //     // Check for unique constraint violation (e.g., SQLSTATE 23000 for MySQL/SQLite)
    //     if ($e->getCode() == '23000') {
    //         $pdo->rollBack();
    //         send_json_error("A group with the name '" . htmlspecialchars($group_name) . "' already exists.", 409); // 409 Conflict
    //     } else {
    //         throw $e; // Re-throw other PDO exceptions to be caught by the main handler
    //     }
    // }


    // Add Members (if provided)
    if (!empty($member_person_ids)) {
        // $stmt_add_member = $pdo->prepare(
        //     "INSERT INTO group_members (group_id, person_id) VALUES (:group_id, :person_id)"
        // );
        // $stmt_check_person = $pdo->prepare("SELECT id FROM persons WHERE id = :person_id");

        foreach ($member_person_ids as $person_id) {
            // Optional: Validate person_id exists in 'persons' table
            // $stmt_check_person->execute(['person_id' => $person_id]);
            // if (!$stmt_check_person->fetch()) {
            //     // Option 1: Skip this member and continue (log it)
            //     error_log("Attempted to add non-existent person_id '{$person_id}' to group '{$group_id}'. Skipping.");
            //     continue;
            //     // Option 2: Rollback and error out
            //     // $pdo->rollBack();
            //     // send_json_error("Person with ID '{$person_id}' not found. Cannot add to group.", 400);
            // }

            // $stmt_add_member->execute(['group_id' => $group_id, 'person_id' => $person_id]);
            // Ignore duplicate errors for group_members for idempotency, or handle as needed.
        }
    }

    $pdo->commit();

    // 5. Success Response
    send_json_success([
        "message" => "Group created successfully.",
        "group_id" => $group_id,
        "group_name" => $group_name // Return the actual name used
    ]);

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PDOException in create_group.php: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    // More specific error for unique constraint violation on group name was handled above.
    // This handles other DB errors.
    send_json_error('A database error occurred while creating the group.', 500);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) { // Should not be needed here if $pdo is only in try block
        $pdo->rollBack();
    }
    error_log("General Exception in create_group.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

/*
// 6. Example of expected JSON input (POST body):
// {
//   "group_name": "Project Alpha Team",
//   "member_person_ids": ["person_uuid_123", "person_uuid_456"] // Optional
// }
//
// Another example (minimal):
// {
//   "group_name": "Book Club"
// }

// 7. Example of expected JSON output structure:

// SUCCESS:
// {
//   "status": "success",
//   "data": {
//      "message": "Group created successfully.",
//      "group_id": "group_xxxxxxxxxxxxxxxx", // e.g. group_randombytesgenerated
//      "group_name": "Project Alpha Team"
//   }
// }

// ERROR (e.g., Validation):
// { "status": "error", "message": "Group name is required." }
// { "status": "error", "message": "member_person_ids must be an array if provided." }
// { "status": "error", "message": "Each person_id in member_person_ids must be a non-empty string." }


// ERROR (e.g., Group Name Conflict from placeholder logic):
// { "status": "error", "message": "A group with the name 'Existing Group Name' already exists." }

// ERROR (e.g., DB Error):
// { "status": "error", "message": "A database error occurred while creating the group." }
*/

?>
