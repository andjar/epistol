<?php

// 1. Include necessary files
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../config/config.php'; // For DB_PATH, SMTP settings, APP_DOMAIN, STORAGE_PATH_ATTACHMENTS
require_once __DIR__ . '/../../src/classes/SmtpMailer.php'; // Actual mailer class

// Define SENDER_USER_ID for now, replace with actual session/auth user ID later
if (!defined('SENDER_USER_ID')) {
    define('SENDER_USER_ID', 1); // Assuming user with ID 1 exists and is the sender
}
if (!defined('APP_DOMAIN')) {
    define('APP_DOMAIN', 'epistol.local'); // Define a default app domain for message IDs
}


// Set default content type
header('Content-Type: application/json');

// 2. Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method. Only POST requests are allowed.', 405);
}

try {
    // 3. Input Parameters (from JSON body)
    // Check if running in PHPUnit test environment to use mocked input
    if (getenv('PHPUNIT_RUNNING') === 'true' && isset($GLOBALS['mock_php_input_data'])) {
        $input_data = json_decode($GLOBALS['mock_php_input_data'], true);
    } else {
        $input_data = json_decode(file_get_contents('php://input'), true);
    }

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
    $current_time = date('Y-m-d H:i:s'); // More descriptive variable name
    $email_subject = $subject; // Use validated $subject, may be overridden for replies

    $pdo->beginTransaction(); // Start transaction

    try {
        $thread_id = null;
        $parent_email_id_for_db = null; // For emails.parent_email_id

        if ($in_reply_to_email_id) {
            // This is a reply
            $parent_email_id_for_db = $in_reply_to_email_id;
            // Fetch parent email's thread_id and subject (to form "Re: [parent_subject]")
            $stmt_parent = $pdo->prepare("SELECT thread_id, subject FROM emails WHERE id = :parent_email_id");
            // Note: The original script used string IDs like "eml_...". The new schema uses SERIAL.
            // Assuming $in_reply_to_email_id is already an integer ID from the client.
            // If client sends "eml_...", it needs to be converted or client needs to send integer.
            // For now, assuming $in_reply_to_email_id is the integer ID.
            $stmt_parent->execute([':parent_email_id' => $in_reply_to_email_id]);
            $parent_email_info = $stmt_parent->fetch(PDO::FETCH_ASSOC);

            if (!$parent_email_info) {
                $pdo->rollBack();
                send_json_error('Replied-to email (parent_email_id) not found.', 404);
            }
            $thread_id = $parent_email_info['thread_id'];

            // If the new email's subject is empty or not significantly different, prepend "Re:"
            if (empty(trim($email_subject)) || stripos($email_subject, "Re: ") !== 0) {
                $email_subject = "Re: " . $parent_email_info['subject'];
            }

        } else {
            // This is a new thread
            $stmt_insert_thread = $pdo->prepare(
                "INSERT INTO threads (subject, created_by_user_id, created_at, last_activity_at)
                 VALUES (:subject, :creator_id, :created_at, :last_activity_at)"
            );
            $stmt_insert_thread->execute([
                ':subject' => $email_subject, // Use subject of the first email as thread subject
                ':creator_id' => SENDER_USER_ID,
                ':created_at' => $current_time,
                ':last_activity_at' => $current_time
            ]);
            $thread_id = $pdo->lastInsertId(); // Get the new thread ID (SERIAL)
        }

        // Insert the new email
        $message_id_header_value = "<" . bin2hex(random_bytes(16)) . "@" . APP_DOMAIN . ">"; // Generate a unique message ID

        $stmt_insert_email = $pdo->prepare(
            "INSERT INTO emails (thread_id, parent_email_id, user_id, subject, body_html, body_text, created_at, message_id_header)
             VALUES (:thread_id, :parent_email_id, :user_id, :subject, :body_html, :body_text, :created_at, :message_id_header)"
        );
        $stmt_insert_email->execute([
            ':thread_id' => $thread_id,
            ':parent_email_id' => $parent_email_id_for_db,
            ':user_id' => SENDER_USER_ID, // The sender of the email (from users.id)
            ':subject' => $email_subject,
            ':body_html' => $body_html,
            ':body_text' => $body_text,
            ':created_at' => $current_time,
            ':message_id_header' => $message_id_header_value
        ]);
        $new_email_id = $pdo->lastInsertId(); // Get the new email ID (SERIAL)

        // Add status for the sender (e.g., 'sent' or 'read')
        $stmt_add_status = $pdo->prepare(
            "INSERT INTO email_statuses (email_id, user_id, status, created_at)
             VALUES (:email_id, :user_id, :status, :created_at)"
        );
        $stmt_add_status->execute([
            ':email_id' => $new_email_id,
            ':user_id' => SENDER_USER_ID,
            ':status' => 'sent', // Or 'read' as the sender has "seen" it. 'sent' seems more appropriate.
            ':created_at' => $current_time
        ]);

        // Handle recipients (adapted for new schema with SERIAL IDs)
        foreach ($recipient_emails as $r_email_address_str) {
            $recipient_person_id = null;
            $recipient_email_address_id = null;

            // Check if email_address exists
            $stmt_find_email_addr = $pdo->prepare("SELECT id, person_id FROM email_addresses WHERE email_address = :email_address");
            $stmt_find_email_addr->execute([':email_address' => $r_email_address_str]);
            $email_addr_info = $stmt_find_email_addr->fetch(PDO::FETCH_ASSOC);

            if ($email_addr_info) {
                $recipient_email_address_id = $email_addr_info['id'];
                $recipient_person_id = $email_addr_info['person_id']; // Might be null if not linked
            } else {
                // Email address does not exist, so person likely doesn't either (or isn't linked this way)
                // Create person first (optional, could be an unpersoned email address)
                // For simplicity, creating a person for each new email address.
                $stmt_create_person = $pdo->prepare("INSERT INTO persons (name, created_at) VALUES (:name, :created_at)");
                $stmt_create_person->execute([
                    ':name' => $r_email_address_str, // Use email as name for simplicity
                    ':created_at' => $current_time
                ]);
                $recipient_person_id = $pdo->lastInsertId();

                // Create email_address record
                $stmt_create_email_addr = $pdo->prepare(
                    "INSERT INTO email_addresses (person_id, email_address, is_primary, created_at)
                     VALUES (:person_id, :email_address, :is_primary, :created_at)"
                );
                $stmt_create_email_addr->execute([
                    ':person_id' => $recipient_person_id,
                    ':email_address' => $r_email_address_str,
                    ':is_primary' => true, // First email for this person, mark as primary
                    ':created_at' => $current_time
                ]);
                $recipient_email_address_id = $pdo->lastInsertId();
            }

            // If person_id was found but is NULL from email_addresses, and we want to ensure a person exists:
            if (!$recipient_person_id) {
                 // This case implies an email_address record exists without a person_id, which is allowed by schema.
                 // Decide if we should create a person here or leave person_id null for the recipient.
                 // For now, let's assume we want to link to a person if possible, or create one if the email is new.
                 // The logic above already creates a person if email_addr_info is false.
                 // If email_addr_info is true but person_id is null, we might want to create a person and update email_addresses.
                 // This part can be complex. Current logic: new email -> new person & new email_address. Existing email_address -> use its person_id.
            }


            // Insert into email_recipients
            $stmt_insert_recipient = $pdo->prepare(
                "INSERT INTO email_recipients (email_id, email_address_id, person_id, type)
                 VALUES (:email_id, :email_address_id, :person_id, :type)"
            );
            // Note: person_id in email_recipients can be NULL if we don't have a corresponding person record.
            $stmt_insert_recipient->execute([
                ':email_id' => $new_email_id,
                ':email_address_id' => $recipient_email_address_id,
                ':person_id' => $recipient_person_id,
                ':type' => 'to' // Assuming 'to'. CC/BCC would need more input fields.
            ]);
        }

        // Handle attachments: save to disk and insert into attachments table
        if (!empty($processed_attachments_for_mailer)) {
            if (!file_exists(STORAGE_PATH_ATTACHMENTS) && !is_dir(STORAGE_PATH_ATTACHMENTS)) {
                if (!mkdir(STORAGE_PATH_ATTACHMENTS, 0755, true)) { // Added recursive true
                     error_log("Failed to create attachment directory: " . STORAGE_PATH_ATTACHMENTS);
                     throw new Exception("Failed to create attachment directory."); // Will be caught by transaction rollback
                }
            }

            foreach ($processed_attachments_for_mailer as $att) {
                // Sanitize filename and ensure uniqueness
                $safe_filename = preg_replace("/[^a-zA-Z0-9._-]/", "", basename($att['filename']));
                $file_extension = pathinfo($safe_filename, PATHINFO_EXTENSION);
                $base_filename = pathinfo($safe_filename, PATHINFO_FILENAME);
                // Make filename unique on disk to prevent collision. Store this unique name.
                $unique_filename_on_disk = $base_filename . "_" . bin2hex(random_bytes(8)) . ($file_extension ? "." . $file_extension : "");
                $filepath_on_disk = STORAGE_PATH_ATTACHMENTS . DIRECTORY_SEPARATOR . $unique_filename_on_disk;

                if (file_put_contents($filepath_on_disk, $att['content']) === false) {
                    error_log("Failed to save attachment to disk: " . $filepath_on_disk);
                    throw new Exception("Failed to save attachment: " . htmlspecialchars($att['filename']));
                }

                $stmt_insert_attachment = $pdo->prepare(
                    "INSERT INTO attachments (email_id, filename, mimetype, filesize_bytes, filepath_on_disk, created_at)
                     VALUES (:email_id, :filename, :mimetype, :filesize_bytes, :filepath_on_disk, :created_at)"
                );
                $stmt_insert_attachment->execute([
                    ':email_id' => $new_email_id,
                    ':filename' => $att['filename'], // Original filename for display
                    ':mimetype' => $att['mimetype'],
                    ':filesize_bytes' => strlen($att['content']),
                    ':filepath_on_disk' => $unique_filename_on_disk, // Store only the unique name, not full path
                    ':created_at' => $current_time
                ]);
                // $pdo->lastInsertId() for attachment ID if needed elsewhere, but not currently.
            }
        }

        // Update last_activity_at for the thread
        $stmt_update_thread_activity = $pdo->prepare("UPDATE threads SET last_activity_at = :now WHERE id = :thread_id");
        $stmt_update_thread_activity->execute([':now' => $current_time, ':thread_id' => $thread_id]);

        $pdo->commit(); // Commit transaction
    } catch (PDOException $db_exception) { // Changed variable name for clarity
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // If attachments were saved to disk before DB error, they are now orphaned.
        // A more robust system might schedule them for cleanup.
        // For now, just log and rethrow or send error.
        error_log("Database transaction failed in send_email.php: " . $db_exception->getMessage() . "\nTrace: " . $db_exception->getTraceAsString());
        // Do not rethrow generic Exception, handle it as a PDOException for consistency if it's from DB
        send_json_error('A database error occurred during the send operation. Details: ' . $db_exception->getMessage(), 500);
    } catch (Exception $e) { // Catch other specific exceptions like failed file writes for attachments
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error during send_email.php DB operations: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        send_json_error('An error occurred during the send operation: ' . $e->getMessage(), 500);
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