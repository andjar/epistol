<?php

// 1. Include necessary files
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../config/config.php';

// Set default content type early
header('Content-Type: application/json');

// 2. Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
}

try {
    // 3. Input Parameters (from JSON body)
    $input_data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_error('Invalid JSON input: ' . json_last_error_msg(), 400);
    }

    // Validate email_id
    if (!isset($input_data['email_id']) || !filter_var($input_data['email_id'], FILTER_VALIDATE_INT) || $input_data['email_id'] <= 0) {
        send_json_error('Valid integer email_id is required.', 400);
    }
    $email_id_to_split = (int)$input_data['email_id'];

    // Validate user_id (performing the action)
    if (!isset($input_data['user_id']) || !filter_var($input_data['user_id'], FILTER_VALIDATE_INT) || $input_data['user_id'] <= 0) {
        send_json_error('Valid integer user_id (performing the action) is required.', 400);
    }
    $action_user_id = (int)$input_data['user_id'];

    // 4. Database Operations
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    try {
        // Fetch the Email to be Split
        $stmt_fetch_email = $pdo->prepare(
            "SELECT thread_id, subject, parent_email_id FROM emails WHERE id = :email_id"
        );
        $stmt_fetch_email->bindParam(':email_id', $email_id_to_split, PDO::PARAM_INT);
        $stmt_fetch_email->execute();
        $email_to_split = $stmt_fetch_email->fetch(PDO::FETCH_ASSOC);

        if (!$email_to_split) {
            $pdo->rollBack();
            send_json_error('Email to be split not found.', 404);
        }

        $old_thread_id = $email_to_split['thread_id'];
        $email_subject_for_new_thread = $email_to_split['subject'];

        // As per spec: "allow splitting even if parent_email_id is null"
        // If we wanted to prevent splitting root emails:
        // if ($email_to_split['parent_email_id'] === null) {
        //    $pdo->rollBack();
        //    send_json_error('Email is already a main post and cannot be split further by this logic.', 400);
        // }


        // Create a New Thread
        $current_timestamp = date('Y-m-d H:i:s');
        $stmt_create_thread = $pdo->prepare(
            "INSERT INTO threads (subject, created_by_user_id, created_at, last_activity_at)
             VALUES (:subject, :creator_id, :created_at, :last_activity_at)"
        );
        $stmt_create_thread->execute([
            ':subject' => $email_subject_for_new_thread ?: 'New Thread from Split Email', // Fallback subject
            ':creator_id' => $action_user_id,
            ':created_at' => $current_timestamp,
            ':last_activity_at' => $current_timestamp // The new thread's activity is this email's time
        ]);
        $new_thread_id = $pdo->lastInsertId();

        // Update the Email Record
        $stmt_update_email = $pdo->prepare(
            "UPDATE emails SET thread_id = :new_thread_id, parent_email_id = NULL
             WHERE id = :email_id"
        );
        $stmt_update_email->execute([
            ':new_thread_id' => $new_thread_id,
            ':email_id' => $email_id_to_split
        ]);

        // Update last_activity_at for the Old Thread (if it's a different thread)
        if ($old_thread_id !== null && $old_thread_id != $new_thread_id) { // Check old_thread_id was not null
            $stmt_latest_old_thread_email = $pdo->prepare(
                "SELECT MAX(created_at) AS max_ts FROM emails WHERE thread_id = :old_thread_id"
            );
            $stmt_latest_old_thread_email->bindParam(':old_thread_id', $old_thread_id, PDO::PARAM_INT);
            $stmt_latest_old_thread_email->execute();
            $latest_email_ts_old_thread = $stmt_latest_old_thread_email->fetchColumn();

            $new_last_activity_for_old_thread = null;
            if ($latest_email_ts_old_thread) {
                $new_last_activity_for_old_thread = $latest_email_ts_old_thread;
            } else {
                // No emails left in old thread, set its activity to its creation time or current time
                $stmt_old_thread_created_at = $pdo->prepare("SELECT created_at FROM threads WHERE id = :old_thread_id");
                $stmt_old_thread_created_at->bindParam(':old_thread_id', $old_thread_id, PDO::PARAM_INT);
                $stmt_old_thread_created_at->execute();
                $old_thread_created_at = $stmt_old_thread_created_at->fetchColumn();
                // Use old thread's creation_at, or if somehow that's missing, current time as last resort
                $new_last_activity_for_old_thread = $old_thread_created_at ?: $current_timestamp;
            }

            $stmt_update_old_thread = $pdo->prepare(
                "UPDATE threads SET last_activity_at = :last_activity_at WHERE id = :old_thread_id"
            );
            $stmt_update_old_thread->execute([
                ':last_activity_at' => $new_last_activity_for_old_thread,
                ':old_thread_id' => $old_thread_id
            ]);
        }
        // If old_thread_id was NULL, the email was already effectively orphaned or a root post in a conceptual sense,
        // and now it's formally a root post in its own new thread. No "old thread" to update.

        $pdo->commit();

        // Success Response
        send_json_success([
            "message" => "Email successfully split into a new thread.",
            "new_thread_id" => (int)$new_thread_id,
            "updated_email_id" => $email_id_to_split
        ]);

    } catch (PDOException $db_exception) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database transaction failed in split_reply_to_post.php: " . $db_exception->getMessage() . "\nTrace: " . $db_exception->getTraceAsString());
        send_json_error('A database error occurred during the operation. Details: ' . $db_exception->getMessage(), 500);
    } catch (Exception $e) { // Catch other specific exceptions
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error during split_reply_to_post.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        send_json_error('An error occurred: ' . $e->getMessage(), 500);
    }

} catch (Exception $e) { // Catch input validation or other early errors
    error_log("General Exception in split_reply_to_post.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    // Potentially redundant if send_json_error was already called, but good for catching unforeseen issues
    // Ensure not to send headers twice if send_json_error already did.
    if (!headers_sent()) {
         send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
    }
}

?>
