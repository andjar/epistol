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

    $user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
    if ($user_id === false || $user_id === null || $user_id <= 0) {
        send_json_error('Invalid or missing user_id. User ID must be a positive integer.', 400);
    }

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

    // Fetch Thread Metadata (Subject)
    $stmt_thread_meta = $pdo->prepare("SELECT subject FROM threads WHERE id = :thread_id");
    $stmt_thread_meta->bindParam(':thread_id', $thread_id, PDO::PARAM_STR); // Assuming thread_id can be non-numeric
    $stmt_thread_meta->execute();
    $thread_info = $stmt_thread_meta->fetch(PDO::FETCH_ASSOC);

    if (!$thread_info) {
        send_json_error('Thread not found.', 404);
    }

    // Fetch Emails in Thread with Senders and Post Status
    // Note: Attachments are not included in this version for simplicity, focusing on post_status.
    // The original placeholder had attachment logic; this would need to be re-integrated carefully if required.
    $sql_emails = "
        SELECT
            e.id AS email_id,
            e.body_html,
            e.body_text,
            e.timestamp,
            COALESCE(ps.status, 'unread') AS post_status, -- Get post status for the current user
            p.id AS sender_id,
            p.name AS sender_name,
            p.profile_picture_url AS sender_avatar_url
        FROM
            emails e
        JOIN
            persons p ON e.from_person_id = p.id
        LEFT JOIN
            post_statuses ps ON e.id = ps.post_id AND ps.user_id = :current_user_id
        WHERE
            e.thread_id = :thread_id
        ORDER BY
            e.timestamp ASC;
    ";

    $stmt_emails = $pdo->prepare($sql_emails);
    $stmt_emails->bindParam(':thread_id', $thread_id, PDO::PARAM_STR);
    $stmt_emails->bindParam(':current_user_id', $user_id, PDO::PARAM_INT);
    $stmt_emails->execute();
    $email_rows = $stmt_emails->fetchAll(PDO::FETCH_ASSOC);

    // Process emails
    $emails_array = [];
    $participant_ids = []; // To gather unique participant IDs from emails

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
            "timestamp" => $row['timestamp'], // Consider formatting if needed
            "status" => $row['post_status'], // Added post status
            "attachments" => [] // Placeholder, as attachments are not fetched in this version
        ];
        if (!in_array($row['sender_id'], $participant_ids)) {
            $participant_ids[] = $row['sender_id'];
        }
    }

    // Fetch participant details (simplified: uses only senders from the emails in the thread)
    // A more robust participant list might come from a dedicated `thread_participants` table if it existed.
    $participants_array = [];
    if (!empty($participant_ids)) {
        $placeholders = implode(',', array_fill(0, count($participant_ids), '?'));
        $stmt_participants = $pdo->prepare("SELECT id, name, profile_picture_url FROM persons WHERE id IN ($placeholders)");
        $stmt_participants->execute($participant_ids);
        $participant_rows = $stmt_participants->fetchAll(PDO::FETCH_ASSOC);
        foreach ($participant_rows as $p_row) {
            $participants_array[] = [
                'id' => $p_row['id'],
                'name' => $p_row['name'],
                'avatar_url' => $p_row['profile_picture_url'] ?: '/avatars/default.png'
            ];
        }
    }


    // 6. Data Processing and Response
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
    send_json_error('An unexpected error occurred. Please try again.', 500);
}

?>
