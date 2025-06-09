<?php
/**
 * API Endpoint: /api/v1/get_thread.php
 *
 * Description:
 * Fetches all emails within a specific thread, along with thread metadata (subject)
 * and a list of participants. Emails are ordered by their timestamp.
 * User-specific email status (e.g., 'read', 'unread') is included for each email.
 *
 * Request Method:
 * GET
 *
 * Input Parameters ($_GET):
 *  - thread_id (int, required): The ID of the thread to fetch (from threads.id).
 *  - user_id (int, required): The ID of the user requesting the thread (from users.id). This is used
 *                             to determine the status of emails (e.g., read/unread) for this specific user.
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "id": 123, // Integer thread_id
 *      "subject": "Important Discussion",
 *      "participants": [
 *        {"id": 1, "user_id": 101, "name": "Sender One", "avatar_url": "/avatars/sender1.png"}, // person_id, user_id
 *        {"id": 2, "user_id": 102, "name": "Current User", "avatar_url": "/avatars/user123.png"}
 *      ],
 *      "emails": [
 *        {
 *          "id": 789, // Integer email_id
 *          "parent_email_id": null, // or integer parent_email_id
 *          "subject": "Re: Important Discussion", // Email's own subject
 *          "sender": {
 *            "id": 1, // Integer person_id (nullable)
 *            "user_id": 101, // Integer user_id
 *            "name": "Sender One",
 *            "avatar_url": "/avatars/sender1.png"
 *          },
 *          "body_html": "<p>Hello world!</p>",
 *          "body_text": "Hello world!",
 *          "timestamp": "YYYY-MM-DD HH:MM:SS", // from emails.created_at
 *          "status": "read", // Status for the requesting user_id
 *          "attachments": [
 *            {"id": 1, "filename": "doc.pdf", "mimetype": "application/pdf", "filesize_bytes": 12345}
 *          ]
 *        },
 *        // ... more emails in the thread, sorted by created_at ASC
 *      ]
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Missing or empty 'thread_id' or 'user_id'.
 *    JSON Response: {"status": "error", "message": "Specific error message."}
 *  - 404 Not Found: If the specified 'thread_id' does not exist.
 *    JSON Response: {"status": "error", "message": "Thread not found."}
 *  - 405 Method Not Allowed: If the request method is not GET.
 *    JSON Response: {"status": "error", "message": "Only GET requests are allowed."}
 *  - 500 Internal Server Error: Database errors or other unexpected server issues.
 *    JSON Response: {"status": "error", "message": "Database error..."} or {"status": "error", "message": "Unexpected error..."}
 *
 * Database Interaction:
 * - Fetches the thread's subject from the 'threads' table using its integer ID.
 * - Fetches all emails associated with the 'thread_id' from the 'emails' table.
 * - For each email, joins with 'users' and then 'persons' to get sender details (name, avatar).
 * - Left joins with 'email_statuses' to determine the email status for the current 'user_id'.
 * - Fetches attachments for each email from the 'attachments' table.
 * - Derives a list of unique participants from the senders of emails within the thread.
 */

// 1. Include necessary files
require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

// Set default content type early
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error('Only GET requests are allowed.', 405);
}

try {
    // 2. Input Parameters
    if (!isset($_GET['thread_id']) || !filter_var($_GET['thread_id'], FILTER_VALIDATE_INT)) {
        send_json_error('Valid integer thread_id is required.', 400);
    }
    $thread_id = (int)$_GET['thread_id'];

    if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
        send_json_error('Valid integer user_id is required for context.', 400);
    }
    $current_user_id = (int)$_GET['user_id']; // This is users.id

    // 3. Database Interaction
    $pdo = get_db_connection();

    // Fetch Thread Metadata (Subject)
    $stmt_thread_meta = $pdo->prepare("SELECT subject FROM threads WHERE id = :thread_id");
    $stmt_thread_meta->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
    $stmt_thread_meta->execute();
    $thread_info = $stmt_thread_meta->fetch(PDO::FETCH_ASSOC);

    if (!$thread_info) {
        send_json_error('Thread not found.', 404);
    }

    // Fetch Emails in Thread with Senders and Email Status
    $sql_emails = "
        SELECT
            e.id AS email_id,
            e.parent_email_id,
            e.subject AS email_subject,
            e.body_html,
            e.body_text,
            e.created_at AS timestamp,
            COALESCE(es.status, 'unread') AS email_status,
            u.id AS sender_user_id,
            p.id AS sender_person_id,
            COALESCE(p.name, u.username) AS sender_name, -- Fallback to username if person.name is NULL
            p.avatar_url AS sender_avatar_url
        FROM
            emails e
        JOIN
            users u ON e.user_id = u.id
        LEFT JOIN
            persons p ON u.person_id = p.id
        LEFT JOIN
            email_statuses es ON e.id = es.email_id AND es.user_id = :current_user_id
        WHERE
            e.thread_id = :thread_id
        ORDER BY
            e.created_at ASC;
    ";

    $stmt_emails = $pdo->prepare($sql_emails);
    $stmt_emails->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
    $stmt_emails->bindParam(':current_user_id', $current_user_id, PDO::PARAM_INT);
    $stmt_emails->execute();
    $email_rows = $stmt_emails->fetchAll(PDO::FETCH_ASSOC);

    // Process emails and fetch attachments
    $emails_array = [];
    $participant_ids_map = []; // Using sender_person_id as key if available, else sender_user_id

    $stmt_attachments = $pdo->prepare("SELECT id, filename, mimetype, filesize_bytes FROM attachments WHERE email_id = :email_id ORDER BY filename ASC");

    foreach ($email_rows as $row) {
        $email_id_int = (int)$row['email_id'];

        // Fetch attachments for this email
        $stmt_attachments->bindParam(':email_id', $email_id_int, PDO::PARAM_INT);
        $stmt_attachments->execute();
        $attachment_rows = $stmt_attachments->fetchAll(PDO::FETCH_ASSOC);
        $attachments_array = array_map(function($att_row) {
            return [
                'id' => (int)$att_row['id'],
                'filename' => $att_row['filename'],
                'mimetype' => $att_row['mimetype'],
                'filesize_bytes' => (int)$att_row['filesize_bytes']
            ];
        }, $attachment_rows);

        $sender_person_id_val = $row['sender_person_id'] ? (int)$row['sender_person_id'] : null;
        $sender_user_id_val = (int)$row['sender_user_id'];

        $emails_array[] = [
            "id" => $email_id_int,
            "parent_email_id" => $row['parent_email_id'] ? (int)$row['parent_email_id'] : null,
            "subject" => $row['email_subject'],
            "sender" => [
                "id" => $sender_person_id_val,
                "user_id" => $sender_user_id_val,
                "name" => $row['sender_name'],
                "avatar_url" => $row['sender_avatar_url'] ?: '/avatars/default.png'
            ],
            "body_html" => $row['body_html'],
            "body_text" => $row['body_text'],
            "timestamp" => $row['timestamp'],
            "status" => $row['email_status'],
            "attachments" => $attachments_array
        ];

        // Use person_id for participants map if available, otherwise user_id
        $participant_key = $sender_person_id_val ?? 'user_' . $sender_user_id_val;
        if (!isset($participant_ids_map[$participant_key])) {
            $participant_ids_map[$participant_key] = [
                'id' => $sender_person_id_val, // persons.id (can be null)
                'user_id' => $sender_user_id_val, // users.id (always present for a sender)
                'name' => $row['sender_name'],
                'avatar_url' => $row['sender_avatar_url'] ?: '/avatars/default.png'
            ];
        }
    }

    $participants_array = array_values($participant_ids_map);

    // Data Processing and Response
    $response_data = [
        "id" => $thread_id, // Integer
        "subject" => $thread_info['subject'],
        "participants" => $participants_array,
        "emails" => $emails_array
    ];

    send_json_success($response_data);

} catch (PDOException $e) {
    error_log("PDOException in get_thread.php: " . $e->getMessage());
    send_json_error('A database error occurred while fetching thread details.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_thread.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}

?>
