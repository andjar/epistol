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
 *  - thread_id (string, required): The ID of the thread to fetch.
 *  - user_id (string, required): The ID of the user requesting the thread. This is used
 *                                 to determine the status of emails (e.g., read/unread)
 *                                 for this specific user.
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "id": "thr_abc123",
 *      "subject": "Important Discussion",
 *      "participants": [
 *        {"id": "psn_sender1", "name": "Sender One", "avatar_url": "/avatars/sender1.png"},
 *        {"id": "psn_user123", "name": "Current User", "avatar_url": "/avatars/user123.png"}
 *        // ... other participants derived from email senders and current user
 *      ],
 *      "emails": [
 *        {
 *          "id": "eml_xyz789",
 *          "sender": {
 *            "id": "psn_sender1",
 *            "name": "Sender One",
 *            "avatar_url": "/avatars/sender1.png"
 *          },
 *          "body_html": "<p>Hello world!</p>",
 *          "body_text": "Hello world!",
 *          "timestamp": "YYYY-MM-DD HH:MM:SS",
 *          "status": "read", // Status for the requesting user_id
 *          "attachments": [] // Placeholder, currently not fetching attachments
 *        },
 *        // ... more emails in the thread, sorted by timestamp
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
 * - Fetches the thread's subject from the 'threads' table.
 * - Fetches all emails associated with the 'thread_id' from the 'emails' table.
 * - For each email, joins with 'persons' to get sender details.
 * - Left joins with 'post_statuses' to determine the email status for the 'current_person_id'.
 * - Derives a list of participants from the senders of emails within the thread and includes the current user.
 *   (A more robust participant list might come from a dedicated 'thread_participants' table if available).
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
    if (!isset($_GET['thread_id']) || empty(trim($_GET['thread_id']))) {
        send_json_error('Valid thread_id is required.', 400);
    }
    $thread_id = trim($_GET['thread_id']);

    // Assuming user_id is person_id for context (e.g. post_status)
    $current_person_id = null;
    if (isset($_GET['user_id'])) {
        $current_person_id = trim($_GET['user_id']);
        if (empty($current_person_id)) {
            send_json_error('User ID (person_id) cannot be empty if provided.', 400);
        }
    } else {
        send_json_error('User ID (person_id) is required for context.', 400);
    }


    // 3. Database Interaction
    $pdo = get_db_connection();

    // Fetch Thread Metadata (Subject)
    $stmt_thread_meta = $pdo->prepare("SELECT subject FROM threads WHERE thread_id = :thread_id");
    $stmt_thread_meta->bindParam(':thread_id', $thread_id);
    $stmt_thread_meta->execute();
    $thread_info = $stmt_thread_meta->fetch(PDO::FETCH_ASSOC);

    if (!$thread_info) {
        send_json_error('Thread not found.', 404);
    }

    // Fetch Emails in Thread with Senders and Post Status
    $sql_emails = "
        SELECT
            e.email_id AS email_id,
            e.body_html,
            e.body_text,
            e.created_at AS timestamp,
            COALESCE(ps.status, 'unread') AS post_status,
            p.person_id AS sender_id,
            p.name AS sender_name,
            p.avatar_url AS sender_avatar_url
        FROM
            emails e
        JOIN
            persons p ON e.from_person_id = p.person_id
        LEFT JOIN
            post_statuses ps ON e.email_id = ps.post_id AND ps.user_id = :current_person_id
        WHERE
            e.thread_id = :thread_id
        ORDER BY
            e.created_at ASC;
    ";

    $stmt_emails = $pdo->prepare($sql_emails);
    $stmt_emails->bindParam(':thread_id', $thread_id);
    $stmt_emails->bindParam(':current_person_id', $current_person_id);
    $stmt_emails->execute();
    $email_rows = $stmt_emails->fetchAll(PDO::FETCH_ASSOC);

    // Process emails
    $emails_array = [];
    $participant_ids_map = [];

    foreach ($email_rows as $row) {
        $emails_array[] = [
            "id" => $row['email_id'],
            "sender" => [
                "id" => $row['sender_id'],
                "name" => $row['sender_name'],
                "avatar_url" => $row['sender_avatar_url'] ?: '/avatars/default.png'
            ],
            "body_html" => $row['body_html'],
            "body_text" => $row['body_text'],
            "timestamp" => $row['timestamp'],
            "status" => $row['post_status'],
            "attachments" => []
        ];
        if (!isset($participant_ids_map[$row['sender_id']])) {
            $participant_ids_map[$row['sender_id']] = [
                'id' => $row['sender_id'],
                'name' => $row['sender_name'],
                'avatar_url' => $row['sender_avatar_url'] ?: '/avatars/default.png'
            ];
        }
    }

    if (!isset($participant_ids_map[$current_person_id])) {
        $stmt_user = $pdo->prepare("SELECT person_id, name, avatar_url FROM persons WHERE person_id = :person_id");
        $stmt_user->bindParam(':person_id', $current_person_id);
        $stmt_user->execute();
        $user_details = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user_details) {
             $participant_ids_map[$user_details['person_id']] = [
                'id' => $user_details['person_id'],
                'name' => $user_details['name'],
                'avatar_url' => $user_details['avatar_url'] ?: '/avatars/default.png'
            ];
        }
    }
    $participants_array = array_values($participant_ids_map);


    // Data Processing and Response
    $response_data = [
        "id" => $thread_id,
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
