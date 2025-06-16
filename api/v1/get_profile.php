<?php
/**
 * API Endpoint: /api/v1/get_profile.php
 *
 * Description:
 * Fetches the profile information for a specified person.
 * This includes basic details, associated email addresses, and a placeholder for associated threads.
 *
 * Request Method:
 * GET
 *
 * Input Parameters ($_GET):
 *  - person_id (string, required): The ID of the person whose profile is to be fetched.
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "id": "psn_xyz789",
 *      "name": "John Doe",
 *      "avatar_url": "/avatars/psn_xyz789.png",
 *      "bio": "Software developer and coffee enthusiast.",
 *      "created_at": "YYYY-MM-DD HH:MM:SS",
 *      "email_addresses": [
 *        {"email": "john.doe@example.com", "is_primary": true},
 *        {"email": "jd@work.com", "is_primary": false}
 *      ],
 *      "threads": [] // Placeholder; actual implementation for threads would list associated thread summaries.
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Missing or empty 'person_id'.
 *    JSON Response: {"status": "error", "message": "Person ID is required."}
 *  - 404 Not Found: If the specified 'person_id' does not exist in the 'persons' table.
 *    JSON Response: {"status": "error", "message": "Profile not found."}
 *  - 405 Method Not Allowed: If the request method is not GET.
 *    JSON Response: {"status": "error", "message": "Only GET requests are allowed."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred while fetching the profile."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Fetches core details (person_id, name, bio, avatar_url, created_at) from the 'persons' table for the given 'person_id'.
 * - Fetches associated email addresses (email_address, is_primary) from the 'email_addresses' table.
 * - Currently, 'threads' data is a placeholder. A full implementation would query threads involving this person.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error('Only GET requests are allowed.', 405);
}

if (!isset($_GET['person_id']) || empty(trim($_GET['person_id']))) {
    send_json_error('Person ID is required.', 400);
}
$person_id = trim($_GET['person_id']);

try {
    $pdo = get_db_connection();

    // Fetch person details
    $stmt_person = $pdo->prepare(
        "SELECT person_id, name, bio, avatar_url, created_at
         FROM persons
         WHERE person_id = :person_id"
    );
    $stmt_person->bindParam(':person_id', $person_id);
    $stmt_person->execute();
    $person_data = $stmt_person->fetch(PDO::FETCH_ASSOC);

    if (!$person_data) {
        send_json_error('Profile not found.', 404);
    }

    // Fetch email addresses for the person
    $stmt_emails = $pdo->prepare(
        "SELECT email_address, is_primary
         FROM email_addresses
         WHERE person_id = :person_id
         ORDER BY is_primary DESC, email_address ASC"
    );
    $stmt_emails->bindParam(':person_id', $person_id);
    $stmt_emails->execute();
    $email_addresses = $stmt_emails->fetchAll(PDO::FETCH_ASSOC);

    // Prepare the final profile data
    $profile_output = [
        'id' => $person_data['person_id'],
        'name' => $person_data['name'],
        'avatar_url' => $person_data['avatar_url'],
        'bio' => $person_data['bio'],
        'created_at' => $person_data['created_at'],
        'email_addresses' => array_map(function($email) {
            return [
                'email' => $email['email_address'],
                'is_primary' => (bool)$email['is_primary']
            ];
        }, $email_addresses),
        // 'threads' => [] // Placeholder for associated threads
                         // TODO: Implement fetching associated threads
    ];

    // Fetch associated thread IDs
    $stmt_thread_ids = $pdo->prepare(
        "SELECT DISTINCT t.id AS thread_id
         FROM threads t
         LEFT JOIN emails e ON t.id = e.thread_id
         LEFT JOIN users u ON e.user_id = u.id
         LEFT JOIN email_recipients er ON e.id = er.email_id
         WHERE u.person_id = :person_id OR er.person_id = :person_id"
    );
    $stmt_thread_ids->bindParam(':person_id', $person_id);
    $stmt_thread_ids->execute();
    $thread_ids = $stmt_thread_ids->fetchAll(PDO::FETCH_COLUMN);

    $threads_data = [];
    if (!empty($thread_ids)) {
        // Prepare statements outside the loop for efficiency
        $stmt_thread_details = $pdo->prepare(
            "SELECT id, subject, created_at, last_activity_at FROM threads WHERE id = :current_thread_id"
        );

        $stmt_emails_in_thread = $pdo->prepare(
            "SELECT
                e.id AS email_id,
                e.parent_email_id,
                e.subject AS email_subject,
                e.body_text,
                e.body_html,
                e.created_at AS email_created_at,
                s_p.name AS sender_name,
                s_ea.email_address AS sender_email
            FROM emails e
            JOIN users s_u ON e.user_id = s_u.id
            JOIN persons s_p ON s_u.person_id = s_p.person_id
            JOIN email_addresses s_ea ON s_p.person_id = s_ea.person_id AND s_ea.is_primary = 1
            WHERE e.thread_id = :current_thread_id
            ORDER BY e.created_at ASC"
        );

        $stmt_recipients_for_email = $pdo->prepare(
            "SELECT
                r_p.name AS recipient_name,
                r_ea.email_address AS recipient_email,
                er.type AS recipient_type
            FROM email_recipients er
            JOIN email_addresses r_ea ON er.email_address_id = r_ea.id
            JOIN persons r_p ON r_ea.person_id = r_p.person_id
            WHERE er.email_id = :current_email_id"
        );

        foreach ($thread_ids as $current_thread_id) {
            // Fetch thread details
            $stmt_thread_details->bindParam(':current_thread_id', $current_thread_id);
            $stmt_thread_details->execute();
            $thread_detail = $stmt_thread_details->fetch(PDO::FETCH_ASSOC);

            if ($thread_detail) {
                $current_thread_data = [
                    'id' => $thread_detail['id'],
                    'subject' => $thread_detail['subject'],
                    'created_at' => $thread_detail['created_at'],
                    'last_activity_at' => $thread_detail['last_activity_at'],
                    'emails' => []
                ];

                // Fetch emails for the current thread
                $stmt_emails_in_thread->bindParam(':current_thread_id', $current_thread_id);
                $stmt_emails_in_thread->execute();
                $emails_in_thread = $stmt_emails_in_thread->fetchAll(PDO::FETCH_ASSOC);

                foreach ($emails_in_thread as $email_data) {
                    $current_email_id = $email_data['email_id'];
                    $email_output = [
                        'id' => $current_email_id,
                        'parent_email_id' => $email_data['parent_email_id'],
                        'subject' => $email_data['email_subject'],
                        'body_text' => $email_data['body_text'],
                        'body_html' => $email_data['body_html'],
                        'created_at' => $email_data['email_created_at'],
                        'sender' => [
                            'name' => $email_data['sender_name'],
                            'email' => $email_data['sender_email']
                        ],
                        'recipients' => []
                    ];

                    // Fetch recipients for the current email
                    $stmt_recipients_for_email->bindParam(':current_email_id', $current_email_id);
                    $stmt_recipients_for_email->execute();
                    $recipients = $stmt_recipients_for_email->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($recipients as $recipient) {
                        $email_output['recipients'][] = [
                            'name' => $recipient['recipient_name'],
                            'email' => $recipient['recipient_email'],
                            'type' => $recipient['recipient_type']
                        ];
                    }
                    $current_thread_data['emails'][] = $email_output;
                }
                $threads_data[] = $current_thread_data;
            }
        }
    }
    $profile_output['threads'] = $threads_data;

    send_json_success($profile_output);

} catch (PDOException $e) {
    error_log("PDOException in get_profile.php: " . $e->getMessage());
    send_json_error('A database error occurred while fetching the profile.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_profile.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred: ' . $e->getMessage(), 500);
}
?>
