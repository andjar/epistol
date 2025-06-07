<?php

// 1. Include necessary files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php'; // For DB_PATH, SMTP settings (eventually)
// require_once __DIR__ . '/../src/classes/SmtpMailer.php'; // Placeholder for actual mailer class
// require_once __DIR__ . '/../vendor/autoload.php'; // If using libraries like Ramsey UUID

// Set default content type
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

    // Validate recipients
    if (empty($input_data['recipients']) || !is_array($input_data['recipients'])) {
        send_json_error('Recipients array is required and must not be empty.', 400);
    }
    $recipient_emails = [];
    foreach ($input_data['recipients'] as $recipient_email) {
        if (!is_string($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            send_json_error("Invalid email format in recipients: " . htmlspecialchars((string)$recipient_email), 400);
        }
        $recipient_emails[] = $recipient_email; // Store validated emails
    }
    if (empty($recipient_emails)) { // Should be caught by the first check, but as an extra safeguard
         send_json_error('Recipients array is required and must not be empty.', 400);
    }


    // Validate subject
    if (!isset($input_data['subject']) || trim($input_data['subject']) === '') {
        send_json_error('Subject is required.', 400);
    }
    $subject = trim($input_data['subject']);

    // Validate body (at least one type)
    $body_html = isset($input_data['body_html']) ? $input_data['body_html'] : null;
    $body_text = isset($input_data['body_text']) ? $input_data['body_text'] : null;
    if ($body_html === null && $body_text === null) { // Check if both are explicitly null or not set
        send_json_error('At least one of body_html or body_text must be provided.', 400);
    }
    // If one is empty string and other is null, it's also an error
    if (trim((string)$body_html) === '' && trim((string)$body_text) === '') {
         send_json_error('At least one of body_html or body_text must contain content.', 400);
    }


    $in_reply_to_email_id = isset($input_data['in_reply_to_email_id']) && !empty(trim($input_data['in_reply_to_email_id'])) ? trim($input_data['in_reply_to_email_id']) : null;
    $attachments_input = isset($input_data['attachments']) && is_array($input_data['attachments']) ? $input_data['attachments'] : [];

    $processed_attachments_for_mailer = [];
    if (!empty($attachments_input)) {
        foreach ($attachments_input as $att) {
            if (empty($att['filename']) || !isset($att['content_base64']) || empty($att['mimetype'])) {
                send_json_error('Invalid attachment object. Filename, content_base64, and mimetype are required for each attachment.', 400);
            }
            $decoded_content = base64_decode($att['content_base64'], true);
            if ($decoded_content === false) {
                send_json_error('Invalid base64 content for attachment: ' . htmlspecialchars($att['filename']), 400);
            }
            // $processed_attachments_for_mailer[] = [ 'filename' => $att['filename'], 'content' => $decoded_content, 'mimetype' => $att['mimetype'] ];
        }
    }


    // 4. SMTP Sending (Placeholder)
    // $mailMan = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION); // Example
    // $send_success = $mailMan->send(CURRENT_USER_EMAIL, $recipient_emails, $subject, $body_html, $body_text, $processed_attachments_for_mailer);
    $send_success = true; // Placeholder: Simulate successful SMTP sending for now.
                         // To test failure: $send_success = false;

    if (!$send_success) {
        error_log("SMTP sending failed for subject: " . $subject . " to recipients: " . implode(', ', $recipient_emails));
        send_json_error('Failed to send email via SMTP gateway.', 500); // Generic error for client
    }

    // 5. Database Interaction (Post-Send)
    $pdo = get_db_connection(); // This will throw an exception if it fails, caught by outer try-catch.

    // $user_person_id = get_current_user_person_id($pdo); // Placeholder for getting logged-in user's person_id
    $user_person_id = "user_abc_123"; // Fixed placeholder for now

    $thread_id = null;
    $pdo->beginTransaction(); // Start transaction for database operations

    if ($in_reply_to_email_id) {
        // $stmt = $pdo->prepare("SELECT thread_id FROM emails WHERE id = :in_reply_to_email_id");
        // $stmt->execute(['in_reply_to_email_id' => $in_reply_to_email_id]);
        // $replied_email_thread_info = $stmt->fetch(PDO::FETCH_ASSOC);
        // if (!$replied_email_thread_info) {
        //     $pdo->rollBack();
        //     send_json_error('Replied-to email not found in the database.', 404);
        // }
        // $thread_id = $replied_email_thread_info['thread_id'];

        // Placeholder logic for finding replied-to email's thread
        if ($in_reply_to_email_id === "non_existent_email_id") {
            $pdo->rollBack();
            send_json_error('Replied-to email not found in the database.', 404);
        }
        $thread_id = "thread_for_" . $in_reply_to_email_id; // Placeholder
    } else {
        // $thread_id = \Ramsey\Uuid\Uuid::uuid4()->toString(); // Generate new UUID for new thread
        $thread_id = "new_thread_" . bin2hex(random_bytes(8)); // Simpler placeholder UUID
        // $stmt = $pdo->prepare("INSERT INTO threads (id, subject, created_by_person_id, last_activity_at) VALUES (:id, :subject, :creator_id, :now)");
        // $stmt->execute(['id' => $thread_id, 'subject' => $subject, 'creator_id' => $user_person_id, 'now' => date('Y-m-d H:i:s')]);
    }

    // $new_email_id = \Ramsey\Uuid\Uuid::uuid4()->toString(); // Generate new UUID for this email
    $new_email_id = "email_" . bin2hex(random_bytes(8)); // Simpler placeholder UUID
    $current_timestamp = date('Y-m-d H:i:s');

    // $stmt_insert_email = $pdo->prepare("INSERT INTO emails (id, thread_id, from_person_id, subject, body_html, body_text, timestamp, is_read, message_id_header) VALUES (:id, :thread_id, :from_person_id, :subject, :body_html, :body_text, :timestamp, :is_read, :message_id_header)");
    // $message_id_header_value = "<" . $new_email_id . "@" . APP_DOMAIN . ">"; // Construct a unique Message-ID
    // $stmt_insert_email->execute([
    //     'id' => $new_email_id, 'thread_id' => $thread_id, 'from_person_id' => $user_person_id,
    //     'subject' => $subject, 'body_html' => $body_html, 'body_text' => $body_text,
    //     'timestamp' => $current_timestamp, 'is_read' => true, // Sent by user, so marked as read for them
    //     'message_id_header' => $message_id_header_value
    // ]);

    // Placeholder for recipient handling (find/create person, link to email in email_recipients)
    // foreach ($recipient_emails as $r_email) { /* ... */ }

    // Placeholder for attachment handling (save to disk/blob, insert into attachments table, link to email_attachments)
    // foreach ($processed_attachments_for_mailer as $att) { /* ... */ }

    // $stmt_update_thread_activity = $pdo->prepare("UPDATE threads SET last_activity_at = :now WHERE id = :thread_id");
    // $stmt_update_thread_activity->execute(['now' => $current_timestamp, 'thread_id' => $thread_id]);

    $pdo->commit(); // Commit transaction

    // 6. Success Response
    send_json_success([
        "message" => "Email sent and saved successfully.",
        "email_id" => $new_email_id, // The ID of the email record saved in DB
        "thread_id" => $thread_id   // The ID of the thread it belongs to
    ]);

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PDOException in send_email.php: " . $e->getMessage());
    // If SMTP succeeded but DB failed, this is a critical state.
    // The error message should be generic to the user but logged in detail.
    send_json_error('A database error occurred while saving the email. Please check server logs.', 500);
} catch (Exception $e) { // Catch any other unforeseen errors, including those from get_db_connection()
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General Exception in send_email.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

/*
// 7. Example of expected JSON input (POST body):
// {
//   "recipients": ["recipient1@example.com", "recipient2@example.com"],
//   "subject": "Hello from Epistol",
//   "body_html": "<p>This is a <b>test</b> email.</p>",
//   "body_text": "This is a test email.",
//   "in_reply_to_email_id": null, // or "previous_email_uuid_if_replying"
//   "attachments": [
//     { "filename": "report.pdf", "content_base64": "JVBERi0xLjQKJ...", "mimetype": "application/pdf" },
//     { "filename": "image.png", "content_base64": "iVBORw0KGgoAAA...", "mimetype": "image/png" }
//   ]
// }

// 8. Example of expected JSON output structure:

// SUCCESS:
// {
//   "status": "success",
//   "data": {
//      "message": "Email sent and saved successfully.",
//      "email_id": "email_xxxxxxxxxxxxxxxx", // e.g. email_randombytesgenerated
//      "thread_id": "new_thread_xxxxxxxxxxxxxxxx" // or existing thread id
//   }
// }

// ERROR (e.g., Validation):
// { "status": "error", "message": "Recipients array is required and must not be empty." }
// { "status": "error", "message": "Invalid email format in recipients: invalid-email" }
// { "status": "error", "message": "Subject is required." }
// { "status": "error", "message": "At least one of body_html or body_text must be provided." }
// { "status": "error", "message": "Invalid attachment object. Filename, content_base64, and mimetype are required for each attachment." }
// { "status": "error", "message": "Invalid base64 content for attachment: report.pdf" }


// ERROR (e.g., SMTP Failure from placeholder):
// { "status": "error", "message": "Failed to send email via SMTP gateway." }

// ERROR (e.g., Replied-to email not found from placeholder):
// { "status": "error", "message": "Replied-to email not found in the database." }

// ERROR (e.g., DB Error):
// { "status": "error", "message": "A database error occurred while saving the email. Please check server logs." }
*/

?>
