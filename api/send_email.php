<?php

// 1. Include necessary files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php'; // For DB_PATH, SMTP settings
require_once __DIR__ . '/../src/classes/SmtpMailer.php'; // Actual mailer class
// require_once __DIR__ . '/../vendor/autoload.php'; // If using libraries like Ramsey UUID for IDs

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
            $processed_attachments_for_mailer[] = [ 'filename' => $att['filename'], 'content' => $decoded_content, 'mimetype' => $att['mimetype'] ];
        }
    }

    // 4. SMTP Sending
    $mailMan = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION);
    // Use DEFAULT_SENDER_EMAIL as the from address for the email envelope
    $send_success = $mailMan->send(DEFAULT_SENDER_EMAIL, $recipient_emails, $subject, $body_html, $body_text, $processed_attachments_for_mailer);

    if (!$send_success) {
        error_log("SMTP sending failed for subject: " . $subject . " to recipients: " . implode(', ', $recipient_emails) . " from: " . DEFAULT_SENDER_EMAIL);
        send_json_error('Failed to send email via SMTP gateway.', 500); // Generic error for client
    }

    // 5. Database Interaction (Post-Send)
    $pdo = get_db_connection();
    $user_person_id = DEFAULT_USER_PERSON_ID; // Use defined constant
    $current_timestamp = date('Y-m-d H:i:s');
    $new_email_id = "eml_" . bin2hex(random_bytes(16)); // Generate new ID for this email
    $thread_id = null;

    $pdo->beginTransaction(); // Start transaction

    try {
        if ($in_reply_to_email_id) {
            $stmt = $pdo->prepare("SELECT thread_id FROM emails WHERE id = :in_reply_to_email_id");
            $stmt->execute([':in_reply_to_email_id' => $in_reply_to_email_id]);
            $replied_email_thread_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$replied_email_thread_info) {
                $pdo->rollBack();
                send_json_error('Replied-to email not found in the database.', 404);
            }
            $thread_id = $replied_email_thread_info['thread_id'];
        } else {
            $thread_id = "thr_" . bin2hex(random_bytes(16)); // Generate new UUID for new thread
            $stmt = $pdo->prepare("INSERT INTO threads (id, subject, created_by_person_id, last_activity_at) VALUES (:id, :subject, :creator_id, :now)");
            $stmt->execute([
                ':id' => $thread_id,
                ':subject' => $subject, // Use subject of the first email as thread subject
                ':creator_id' => $user_person_id,
                ':now' => $current_timestamp
            ]);
        }

        $message_id_header_value = "<" . $new_email_id . "@" . APP_DOMAIN . ">";
        $stmt_insert_email = $pdo->prepare(
            "INSERT INTO emails (id, thread_id, from_person_id, subject, body_html, body_text, timestamp, is_read, message_id_header)
             VALUES (:id, :thread_id, :from_person_id, :subject, :body_html, :body_text, :timestamp, :is_read, :message_id_header)"
        );
        $stmt_insert_email->execute([
            ':id' => $new_email_id,
            ':thread_id' => $thread_id,
            ':from_person_id' => $user_person_id, // The sender of the email
            ':subject' => $subject,
            ':body_html' => $body_html,
            ':body_text' => $body_text,
            ':timestamp' => $current_timestamp,
            ':is_read' => true, // Sent by user, so marked as read for them
            ':message_id_header' => $message_id_header_value
        ]);

        // Handle recipients
        foreach ($recipient_emails as $r_email_address_str) {
            // Find or create person
            $stmt_find_person = $pdo->prepare("SELECT id FROM persons WHERE primary_email_address = :email_address");
            $stmt_find_person->execute([':email_address' => $r_email_address_str]);
            $person_info = $stmt_find_person->fetch(PDO::FETCH_ASSOC);
            $recipient_person_id = null;

            if ($person_info) {
                $recipient_person_id = $person_info['id'];
            } else {
                $recipient_person_id = "psn_" . bin2hex(random_bytes(16));
                $stmt_create_person = $pdo->prepare("INSERT INTO persons (id, name, primary_email_address) VALUES (:id, :name, :email_address)");
                // For simplicity, using email address as name if name not otherwise known
                $stmt_create_person->execute([
                    ':id' => $recipient_person_id,
                    ':name' => $r_email_address_str,
                    ':email_address' => $r_email_address_str
                ]);
            }

            // Find or create email_address record
            $stmt_find_email_addr = $pdo->prepare("SELECT id FROM email_addresses WHERE person_id = :person_id AND email_address = :email_address");
            $stmt_find_email_addr->execute([':person_id' => $recipient_person_id, ':email_address' => $r_email_address_str]);
            $email_addr_info = $stmt_find_email_addr->fetch(PDO::FETCH_ASSOC);
            $recipient_email_address_id = null;

            if ($email_addr_info) {
                $recipient_email_address_id = $email_addr_info['id'];
            } else {
                $recipient_email_address_id = "emladr_" . bin2hex(random_bytes(16));
                $stmt_create_email_addr = $pdo->prepare("INSERT INTO email_addresses (id, person_id, email_address) VALUES (:id, :person_id, :email_address)");
                $stmt_create_email_addr->execute([
                    ':id' => $recipient_email_address_id,
                    ':person_id' => $recipient_person_id,
                    ':email_address' => $r_email_address_str
                ]);
            }

            // Insert into email_recipients
            $stmt_insert_recipient = $pdo->prepare(
                "INSERT INTO email_recipients (email_id, email_address_id, person_id, type)
                 VALUES (:email_id, :email_address_id, :person_id, :type)"
            );
            $stmt_insert_recipient->execute([
                ':email_id' => $new_email_id,
                ':email_address_id' => $recipient_email_address_id,
                ':person_id' => $recipient_person_id, // Can be null if person not known/created, but we try to create.
                ':type' => 'to' // Assuming 'to' for now. CC/BCC would need more input fields.
            ]);
        }

        // Handle attachments: save to disk and insert into attachments table
        if (!empty($processed_attachments_for_mailer)) {
            if (!file_exists(STORAGE_PATH_ATTACHMENTS) && !is_dir(STORAGE_PATH_ATTACHMENTS)) {
                if (!mkdir(STORAGE_PATH_ATTACHMENTS, 0755, true)) {
                     error_log("Failed to create attachment directory: " . STORAGE_PATH_ATTACHMENTS);
                     throw new Exception("Failed to create attachment directory.");
                }
            }

            foreach ($processed_attachments_for_mailer as $att) {
                $attachment_id = "att_" . bin2hex(random_bytes(16));
                // Sanitize filename and ensure uniqueness to prevent overwrites and path traversal
                $safe_filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($att['filename']));
                $file_extension = pathinfo($safe_filename, PATHINFO_EXTENSION);
                $base_filename = pathinfo($safe_filename, PATHINFO_FILENAME);
                $unique_filename_on_disk = $base_filename . "_" . bin2hex(random_bytes(8)) . "." . $file_extension;
                $filepath_on_disk = STORAGE_PATH_ATTACHMENTS . '/' . $unique_filename_on_disk;

                if (file_put_contents($filepath_on_disk, $att['content']) === false) {
                    error_log("Failed to save attachment to disk: " . $filepath_on_disk);
                    throw new Exception("Failed to save attachment: " . htmlspecialchars($att['filename']));
                }

                $stmt_insert_attachment = $pdo->prepare(
                    "INSERT INTO attachments (id, email_id, filename, mimetype, filepath_on_disk, filesize_bytes)
                     VALUES (:id, :email_id, :filename, :mimetype, :filepath_on_disk, :filesize_bytes)"
                );
                $stmt_insert_attachment->execute([
                    ':id' => $attachment_id,
                    ':email_id' => $new_email_id,
                    ':filename' => $att['filename'], // Original filename for display
                    ':mimetype' => $att['mimetype'],
                    ':filepath_on_disk' => $unique_filename_on_disk, // Store only relative path or unique name
                    ':filesize_bytes' => strlen($att['content'])
                ]);
            }
        }

        // Update last_activity_at for the thread
        $stmt_update_thread_activity = $pdo->prepare("UPDATE threads SET last_activity_at = :now WHERE id = :thread_id");
        $stmt_update_thread_activity->execute([':now' => $current_timestamp, ':thread_id' => $thread_id]);

        $pdo->commit(); // Commit transaction
    } catch (Exception $db_exception) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // If attachments were saved to disk before DB error, they are now orphaned.
        // A more robust system might schedule them for cleanup.
        // For now, just log and rethrow or send error.
        error_log("Database transaction failed in send_email.php: " . $db_exception->getMessage());
        throw $db_exception; // Rethrow to be caught by the outer general exception handler
    }

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
    if (isset($pdo) && $pdo && $pdo->inTransaction()) { // Ensure $pdo is set before using
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