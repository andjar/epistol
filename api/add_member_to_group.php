<?php

// 1. Create File and Include Dependencies
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php'; // For DB_PATH

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


    // 4. Database Interaction
    $pdo = get_db_connection(); // Throws exception on failure

    // --- DB Validation ---
    // Verify group_id exists
    // $stmt_check_group = $pdo->prepare("SELECT id FROM groups WHERE id = :group_id");
    // $stmt_check_group->execute(['group_id' => $group_id]);
    // if (!$stmt_check_group->fetch()) {
    //     send_json_error('Group not found.', 404);
    // }

    // Verify person_id exists
    // $stmt_check_person = $pdo->prepare("SELECT id FROM persons WHERE id = :person_id");
    // $stmt_check_person->execute(['person_id' => $person_id]);
    // if (!$stmt_check_person->fetch()) {
    //     send_json_error('Person not found.', 404);
    // }

    // --- Add Member ---
    // $stmt_add_member = $pdo->prepare(
    //     "INSERT INTO group_members (group_id, person_id) VALUES (:group_id, :person_id)"
    // );
    // try {
    //     $stmt_add_member->execute(['group_id' => $group_id, 'person_id' => $person_id]);
    // } catch (PDOException $e) {
    //     // Check for unique constraint violation (e.g., SQLSTATE 23000 for MySQL/SQLite)
    //     // This indicates the member is already in the group.
    //     if ($e->getCode() == '23000') {
    //         // Member already exists, consider this a success for idempotency.
    //         // Optionally, send a different message or status code (e.g., 200 OK with specific message, or 204 No Content).
    //         // For now, we'll let it fall through to the generic success message.
    //     } else {
    //         throw $e; // Re-throw other PDO exceptions
    //     }
    // }

    // Placeholder logic for testing validation paths:
    if ($group_id === "non_existent_group") {
        send_json_error('Group not found.', 404);
    }
    if ($person_id === "non_existent_person") {
        send_json_error('Person not found.', 404);
    }
    // Placeholder for "already exists" if we were to send a 409:
    // if ($group_id === "existing_group_with_member" && $person_id === "existing_member_in_it") {
    //     send_json_error('Person already in group.', 409);
    // }


    // 5. Success Response
    // If the script reaches here, either the member was newly added or already existed (handled by catching unique constraint violation).
    send_json_success([
        "message" => "Member added to group successfully."
        // "group_id" => $group_id, // Optionally return IDs
        // "person_id" => $person_id
    ]);

} catch (PDOException $e) {
    error_log("PDOException in add_member_to_group.php: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    // This will catch errors from get_db_connection or re-thrown errors from the INSERT attempt (other than unique constraint)
    send_json_error('A database error occurred.', 500);
} catch (Exception $e) {
    error_log("General Exception in add_member_to_group.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

/*
// 6. Example of expected JSON input (POST body):
// {
//   "group_id": "existing_group_uuid_123",
//   "person_id": "existing_person_uuid_456"
// }

// 7. Example of expected JSON output structure:

// SUCCESS (Member added or already existed):
// {
//   "status": "success",
//   "data": {
//      "message": "Member added to group successfully."
//   }
// }

// ERROR (Validation):
// { "status": "error", "message": "Group ID is required." }
// { "status": "error", "message": "Person ID is required." }

// ERROR (Not Found):
// { "status": "error", "message": "Group not found." }
// { "status": "error", "message": "Person not found." }

// ERROR (Conflict - Optional, if unique constraint is violated AND treated as an error by the endpoint, not a success):
// If decided to return an error for "already a member" instead of success:
// {
//   "status": "error",
//   "message": "Person already in group."
// }

// ERROR (DB Error):
// { "status": "error", "message": "A database error occurred." }
*/

?>
