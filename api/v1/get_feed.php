<?php

// 1. Include necessary files
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
// config.php is included to ensure DB_PATH is available for get_db_connection()
// and ITEMS_PER_PAGE for default limit.
require_once __DIR__ . '/../../config/config.php';

// Set default content type early. helpers.php functions will also set it.
header('Content-Type: application/json');

try {
    // 2. Input Parameters
    $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) : 1;
    $user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
    $status_filter = isset($_GET['status']) && !empty($_GET['status']) ? trim($_GET['status']) : null;
    $group_id_filter = isset($_GET['group_id']) && !empty($_GET['group_id']) ? filter_var($_GET['group_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
    $person_id_filter = isset($_GET['person_id']) && !empty($_GET['person_id']) ? filter_var($_GET['person_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;

    // Use ITEMS_PER_PAGE from config.php if defined, otherwise fallback to 20.
    $default_limit = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 20;
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT, ['options' => ['default' => $default_limit, 'min_range' => 1]]) : $default_limit;

    if ($page === false || $page <= 0) {
        send_json_error('Invalid page number. Page must be a positive integer.', 400);
    }
    if ($limit === false || $limit <= 0) {
        send_json_error('Invalid limit value. Limit must be a positive integer.', 400);
    }
    if ($user_id === false || $user_id === null || $user_id <= 0) {
        send_json_error('Invalid or missing user_id. User ID must be a positive integer.', 400);
    }
    if (isset($_GET['group_id']) && !empty($_GET['group_id']) && ($group_id_filter === false || $group_id_filter <=0)) {
         send_json_error('Invalid group_id. Group ID must be a positive integer.', 400);
    }

    // 3. Database Interaction
    $pdo = null;
    try {
        $pdo = get_db_connection();
    } catch (PDOException $e) {
        error_log("Database connection failed in get_feed.php: " . $e->getMessage());
        send_json_error('Database connection failed. Please try again later.', 500);
    } catch (Exception $e) { // Catch other exceptions from get_db_connection (e.g., DB_PATH not defined)
        error_log("Configuration error in get_feed.php: " . $e->getMessage());
        send_json_error($e->getMessage(), 500); // Send the specific configuration error message
    }

    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;

    // Main SQL Query - Simplified approach
    $sql = "
        SELECT
            t.id AS thread_id,
            t.subject AS thread_subject,
            t.last_activity_at,
            t.group_id,
            g.name AS group_name
        FROM threads t
        LEFT JOIN groups g ON t.group_id = g.id
        WHERE 1=1";

    $bindings = [];

    if ($group_id_filter !== null) {
        $sql .= " AND t.group_id = :group_id_filter";
        $bindings[':group_id_filter'] = $group_id_filter;
    }

    // Apply status filter at thread level
    if ($status_filter !== null) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM emails e 
            LEFT JOIN email_statuses es ON e.id = es.email_id AND es.user_id = :user_id_for_status
            WHERE e.thread_id = t.id 
            AND (es.status = :status_filter OR (es.status IS NULL AND :status_filter = 'unread'))
        )";
        $bindings[':user_id_for_status'] = $user_id;
        $bindings[':status_filter'] = $status_filter;
    }
    
    // Ensure thread has at least one email (implicitly handled by JOIN with LatestEmailInThread)
    // but explicitly checking can be clearer or useful if JOIN type changes.
    // $sql .= " AND EXISTS (SELECT 1 FROM emails e_check WHERE e_check.thread_id = t.id)";


    // Count Query (must reflect the same filtering logic)
    $count_sql_outer = "SELECT COUNT(DISTINCT ft.id) FROM (
        SELECT t_outer.id
        FROM threads t_outer
        LEFT JOIN groups g_outer ON t_outer.group_id = g_outer.id
        WHERE 1=1";

    $count_bindings_outer = [];

    if ($group_id_filter !== null) {
        $count_sql_outer .= " AND t_outer.group_id = :group_id_filter_count_outer";
        $count_bindings_outer[':group_id_filter_count_outer'] = $group_id_filter;
    }

    // Apply status filter at thread level for count query
    if ($status_filter !== null) {
        $count_sql_outer .= " AND EXISTS (
            SELECT 1 FROM emails e_outer 
            LEFT JOIN email_statuses es_outer ON e_outer.id = es_outer.email_id AND es_outer.user_id = :user_id_for_status_count
            WHERE e_outer.thread_id = t_outer.id 
            AND (es_outer.status = :status_filter_count OR (es_outer.status IS NULL AND :status_filter_count = 'unread'))
        )";
        $count_bindings_outer[':user_id_for_status_count'] = $user_id;
        $count_bindings_outer[':status_filter_count'] = $status_filter;
    }

    $count_sql_outer .= " ) ft";


    $count_stmt = $pdo->prepare($count_sql_outer);
    foreach ($count_bindings_outer as $key => $value) {
        $count_stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 0;


    // Add ordering and pagination to the main query
    $sql .= " ORDER BY last_activity_at DESC LIMIT :limit OFFSET :offset";
    $bindings[':limit'] = $limit;
    $bindings[':offset'] = $offset;

    $stmt = $pdo->prepare($sql);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Data Processing
    $processed_threads = [];
    foreach ($results as $row) {
        // Get all emails for this thread
        $emails_stmt = $pdo->prepare("
            SELECT 
                e.id AS email_id,
                e.parent_email_id,
                e.subject AS email_subject,
                e.body_text,
                e.body_html,
                SUBSTRING(e.body_text, 1, 100) AS body_preview,
                e.created_at AS email_timestamp,
                u.id AS sender_user_id,
                u.email AS sender_email,
                p.id AS sender_person_id,
                COALESCE(p.name, u.username) AS sender_name,
                p.avatar_url AS sender_avatar_url,
                COALESCE(es.status, 'unread') AS email_status
            FROM emails e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN persons p ON u.person_id = p.id
            LEFT JOIN email_statuses es ON e.id = es.email_id AND es.user_id = :current_user_id
            WHERE e.thread_id = :thread_id
            ORDER BY e.created_at ASC
        ");
        $emails_stmt->execute(['thread_id' => $row['thread_id'], 'current_user_id' => $user_id]);
        $thread_emails = $emails_stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed_emails = [];
        foreach ($thread_emails as $email) {
            // Fetch attachments for this email
            $attachments = [];
            $attachments_stmt = $pdo->prepare("SELECT id, filename, mimetype, filesize_bytes FROM attachments WHERE email_id = :email_id");
            $attachments_stmt->execute(['email_id' => $email['email_id']]);
            $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch recipients for this email
            $recipients = [];
            $recipients_stmt = $pdo->prepare("
                SELECT p.name, ea.email_address, er.type 
                FROM email_recipients er
                JOIN persons p ON er.person_id = p.id
                JOIN email_addresses ea ON p.id = ea.person_id AND ea.is_primary = 1
                WHERE er.email_id = :email_id
            ");
            $recipients_stmt->execute(['email_id' => $email['email_id']]);
            $recipients = $recipients_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch user-specific statuses for this email
            $user_statuses = [];
            $statuses_stmt = $pdo->prepare("SELECT user_id, status FROM email_statuses WHERE email_id = :email_id");
            $statuses_stmt->execute(['email_id' => $email['email_id']]);
            $user_statuses = $statuses_stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed_emails[] = [
                'email_id' => (int)$email['email_id'],
                'parent_email_id' => $email['parent_email_id'] ? (int)$email['parent_email_id'] : null,
                'subject' => $email['email_subject'],
                'sender_user_id' => (int)$email['sender_user_id'],
                'sender_email' => $email['sender_email'],
                'sender_person_id' => $email['sender_person_id'] ? (int)$email['sender_person_id'] : null,
                'sender_name' => $email['sender_name'],
                'sender_avatar_url' => $email['sender_avatar_url'] ?: '/avatars/default.png',
                'body_text' => $email['body_text'],
                'body_html' => $email['body_html'],
                'body_preview' => $email['body_preview'],
                'timestamp' => $email['email_timestamp'],
                'status' => $email['email_status'],
                'group_id' => $row['group_id'] ? (int)$row['group_id'] : null,
                'group_name' => $row['group_name'],
                'attachments' => $attachments,
                'recipients' => $recipients,
                'user_specific_statuses' => $user_statuses
            ];
        }

        $processed_threads[] = [
            'thread_id' => (int)$row['thread_id'],
            'subject' => $row['thread_subject'],
            'participants' => [], // Will be populated from emails
            'last_reply_time' => $row['last_activity_at'],
            'emails' => $processed_emails // All emails in the thread
        ];
    }

    // 5. Success Response
    $response_data = [
        'threads' => $processed_threads,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $total_items,
            'total_pages' => $total_pages
        ]
    ];

    send_json_success($response_data);

} catch (PDOException $e) {
    error_log("PDOException in get_feed.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    send_json_error('A database error occurred while fetching the feed. Please try again.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_feed.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    // Avoid exposing too much detail in generic errors for security.
    send_json_error('An unexpected error occurred. Please try again.', 500);
}

/*
// 7. Example of expected JSON output structure

    $response_data = [
        'threads' => $processed_threads,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $total_items,
            'total_pages' => $total_pages
        ]
    ];

    send_json_success($response_data);

} catch (PDOException $e) {
    error_log("PDOException in get_feed.php: " . $e->getMessage());
    send_json_error('A database error occurred while fetching the feed. Please try again.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_feed.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    // Avoid exposing too much detail in generic errors for security.
    send_json_error('An unexpected error occurred. Please try again.', 500);
}

/*
// 7. Example of expected JSON output structure

// SUCCESS:
// {
//   "status": "success",
//   "data": {
//     "threads": [
//       {
//         "id": "thread_uuid_1",
//         "subject": "Project Discussion",
//         "last_reply_at": "2024-07-30 10:00:00", // Formatted timestamp
//         "participant_avatars": ["/images/avatars/user1.png", "/images/avatars/user2.png"], // Array of URLs/paths
//         "participants_names": "You, John Doe & 2 others", // Formatted string
//         "latest_email": {
//           "id": "email_uuid_abc",
//           "snippet": "Here's the latest update on the project deliverables...",
//           "timestamp": "2024-07-30 10:00:00", // Formatted timestamp
//           "sender_name": "John Doe",
//           "is_read": false // Boolean, user-specific
//         },
//         "unread_count": 1 // Integer, user-specific
//       }
//       // ... more threads
//     ],
//     "pagination": {
//       "current_page": 1,
//       "per_page": 20,
//       "total_items": 123, // Total number of threads matching criteria
//       "total_pages": 7    // Calculated based on total_items and per_page
//     }
//   }
// }

// ERROR Examples:
// {
//   "status": "error",
//   "message": "Invalid page number. Page must be a positive integer."
// }
// {
//   "status": "error",
//   "message": "Database connection failed. Please try again later."
// }
// {
//   "status": "error",
//   "message": "An unexpected error occurred. Please try again."
// }
*/

?>