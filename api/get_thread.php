<?php

// 1. Include necessary files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php'; // For potential global settings

// Set default content type early
header('Content-Type: application/json');

try {
    // 2. Input Parameters
    if (!isset($_GET['thread_id']) || empty(trim($_GET['thread_id']))) {
        send_json_error('Valid thread_id is required.', 400);
    }
    $thread_id = trim($_GET['thread_id']);
    // Further validation for thread_id format could be added here (e.g., UUID regex)

    // 3. Database Interaction
    $pdo = null;
    try {
        $pdo = get_db_connection();
    } catch (PDOException $e) {
        error_log("Database connection failed in get_thread.php: " . $e->getMessage());
        send_json_error('Database connection error. Please try again later.', 500);
    } catch (Exception $e) { // Catch other exceptions from get_db_connection
        error_log("Configuration error in get_thread.php: " . $e->getMessage());
        send_json_error($e->getMessage(), 500);
    }

    // --- Fetch Thread Metadata and Participants ---
    // 4. Placeholder for DB Query (Thread Details)
    /*
    Conceptual SQL for thread metadata:
    SELECT id, subject FROM threads WHERE id = :thread_id;

    Conceptual SQL for thread participants:
    SELECT p.id, p.name, p.profile_picture_url AS avatar_url
    FROM persons p
    JOIN thread_participants tp ON p.id = tp.person_id
    WHERE tp.thread_id = :thread_id;
    */
    // $stmt_thread_meta = $pdo->prepare("SELECT id, subject FROM threads WHERE id = :thread_id");
    // $stmt_thread_meta->execute(['thread_id' => $thread_id]);
    // $thread_info = $stmt_thread_meta->fetch(PDO::FETCH_ASSOC);
    //
    // if (!$thread_info) {
    //     send_json_error('Thread not found.', 404);
    // }
    //
    // $stmt_participants = $pdo->prepare("SELECT p.id, p.name, p.profile_picture_url FROM persons p JOIN thread_participants tp ON p.id = tp.person_id WHERE tp.thread_id = :thread_id");
    // $stmt_participants->execute(['thread_id' => $thread_id]);
    // $participant_rows = $stmt_participants->fetchAll(PDO::FETCH_ASSOC);


    // --- Fetch Emails in Thread with Senders and Attachments ---
    // 5. Placeholder for DB Query (Emails in Thread)
    /*
    Conceptual SQL for emails (ordered chronologically ASC):
    SELECT
        e.id AS email_id, e.body_html, e.body_text, e.timestamp, e.is_read,
        p.id AS sender_id, p.name AS sender_name, p.profile_picture_url AS sender_avatar_url,
        a.id AS attachment_id, a.filename, a.filesize, a.mimetype, a.download_url
    FROM emails e
    JOIN persons p ON e.from_person_id = p.id
    LEFT JOIN email_attachments ea ON e.id = ea.email_id
    LEFT JOIN attachments a ON ea.attachment_id = a.id
    WHERE e.thread_id = :thread_id
    ORDER BY e.timestamp ASC;
    */
    // This query structure (joining emails with attachments) results in one row per attachment,
    // meaning email details are duplicated if an email has multiple attachments.
    // Data aggregation is needed in PHP to group attachments under their respective emails.

    // $stmt_emails_attachments = $pdo->prepare("/* SQL_QUERY_FOR_EMAILS_AND_ATTACHMENTS */");
    // $stmt_emails_attachments->execute(['thread_id' => $thread_id]);
    // $email_data_rows = $stmt_emails_attachments->fetchAll(PDO::FETCH_ASSOC);

    // Simulate "Not Found" for specific ID for testing purposes
    if ($thread_id === "non_existent_thread_id") {
        send_json_error('Thread not found.', 404);
    }

    // 6. Data Processing (Placeholder)
    // $participants_array = [];
    // foreach ($participant_rows as $p_row) {
    //     $participants_array[] = [
    //         'id' => $p_row['id'],
    //         'name' => $p_row['name'],
    //         'avatar_url' => $p_row['profile_picture_url'] ?: '/avatars/default.png'
    //     ];
    // }
    //
    // $emails_map = []; // Helper to group attachments by email_id
    // foreach ($email_data_rows as $row) {
    //     $email_id = $row['email_id'];
    //     if (!isset($emails_map[$email_id])) {
    //         $emails_map[$email_id] = [
    //             "id" => $email_id,
    //             "sender" => [
    //                 "id" => $row['sender_id'],
    //                 "name" => $row['sender_name'],
    //                 "avatar_url" => $row['sender_avatar_url'] ?: '/avatars/default.png'
    //             ],
    //             "body_html" => $row['body_html'],
    //             "body_text" => $row['body_text'],
    //             "timestamp" => date('Y-m-d H:i:s', strtotime($row['timestamp'])), // Format for consistency
    //             "is_read" => (bool)$row['is_read'], // Ensure boolean
    //             "attachments" => []
    //         ];
    //     }
    //     if ($row['attachment_id']) { // If there's an attachment in this row
    //         $emails_map[$email_id]['attachments'][] = [
    //             "id" => $row['attachment_id'],
    //             "filename" => $row['filename'],
    //             "filesize" => (int)$row['filesize'], // Ensure integer
    //             "mimetype" => $row['mimetype'],
    //             "url" => $row['download_url'] // Or construct as needed, e.g., "/download.php?id=" . $row['attachment_id']
    //         ];
    //     }
    // }
    // $final_emails_list = array_values($emails_map); // Convert map to list

    // Placeholder data for a successful response:
    $response_data = [
        "id" => $thread_id, // From $thread_info['id']
        "subject" => "Subject for thread ID: " . $thread_id, // From $thread_info['subject']
        "participants" => [ // From $participants_array
            ["id" => "user_alpha_id", "name" => "User Alpha", "avatar_url" => "/avatars/alpha.png"],
            ["id" => "user_beta_id", "name" => "User Beta", "avatar_url" => "/avatars/beta.png"]
        ],
        "emails" => [ // From $final_emails_list
            [
                "id" => "email_1_for_" . $thread_id,
                "sender" => ["id" => "user_alpha_id", "name" => "User Alpha", "avatar_url" => "/avatars/alpha.png"],
                "body_html" => "<p>Hello User Beta,</p><p>This is the first email in thread " . $thread_id . ".</p>",
                "body_text" => "Hello User Beta,\nThis is the first email in thread " . $thread_id . ".",
                "timestamp" => "2024-08-01 14:30:00",
                "is_read" => true,
                "attachments" => [
                    ["id" => "attach_x1", "filename" => "brief.pdf", "filesize" => 123456, "mimetype" => "application/pdf", "url" => "/download/attach_x1"]
                ]
            ],
            [
                "id" => "email_2_for_" . $thread_id,
                "sender" => ["id" => "user_beta_id", "name" => "User Beta", "avatar_url" => "/avatars/beta.png"],
                "body_html" => "<p>Hi Alpha,</p><p>Thanks for the brief!</p>",
                "body_text" => "Hi Alpha,\nThanks for the brief!",
                "timestamp" => "2024-08-01 14:45:00",
                "is_read" => false,
                "attachments" => []
            ]
        ]
    ];

    // 7. Success Response
    send_json_success($response_data);

} catch (PDOException $e) {
    error_log("PDOException in get_thread.php: " . $e->getMessage());
    send_json_error('A database error occurred while fetching thread details.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_thread.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred. Please try again.', 500);
}

/*
// 8. Example of expected JSON output structure

// SUCCESS:
// {
//   "status": "success",
//   "data": {
//     "id": "thread_uuid_xyz",
//     "subject": "Important Update & Next Steps",
//     "participants": [
//       { "id": "person_uuid_123", "name": "Alice Wonderland", "avatar_url": "/avatars/alice.png" },
//       { "id": "person_uuid_456", "name": "Bob The Builder", "avatar_url": "/avatars/bob.png" }
//     ],
//     "emails": [
//       {
//         "id": "email_uuid_abc",
//         "sender": { "id": "person_uuid_123", "name": "Alice Wonderland", "avatar_url": "/avatars/alice.png" },
//         "body_html": "<p>Hello Bob, see attached.</p>",
//         "body_text": "Hello Bob, see attached.",
//         "timestamp": "2024-07-30 09:00:00",
//         "is_read": true,
//         "attachments": [
//           { "id": "attach_uuid_1", "filename": "document.pdf", "filesize": 102400, "mimetype": "application/pdf", "url": "/download.php?id=attach_uuid_1" }
//         ]
//       },
//       {
//         "id": "email_uuid_def",
//         "sender": { "id": "person_uuid_456", "name": "Bob The Builder", "avatar_url": "/avatars/bob.png" },
//         "body_html": "<p>Thanks Alice, looks good!</p>",
//         "body_text": "Thanks Alice, looks good!",
//         "timestamp": "2024-07-30 09:15:00",
//         "is_read": false,
//         "attachments": []
//       }
//       // ... more emails in chronological order ...
//     ]
//   }
// }

// ERROR (Not Found):
// {
//   "status": "error",
//   "message": "Thread not found."
// }

// ERROR (Bad Request):
// {
//   "status": "error",
//   "message": "Valid thread_id is required."
// }
*/

?>
