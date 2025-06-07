<?php

// 1. Include necessary files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
// config.php is included to ensure DB_PATH is available for get_db_connection()
// and ITEMS_PER_PAGE for default limit.
require_once __DIR__ . '/../config/config.php';

// Set default content type early. helpers.php functions will also set it.
header('Content-Type: application/json');

try {
    // 2. Input Parameters
    $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) : 1;

    // Use ITEMS_PER_PAGE from config.php if defined, otherwise fallback to 20.
    $default_limit = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 20;
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT, ['options' => ['default' => $default_limit, 'min_range' => 1]]) : $default_limit;

    if ($page === false || $page <= 0) { // filter_var returns false on failure
        send_json_error('Invalid page number. Page must be a positive integer.', 400);
    }
    if ($limit === false || $limit <= 0) { // filter_var returns false on failure
        send_json_error('Invalid limit value. Limit must be a positive integer.', 400);
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

    // 4. Placeholder for DB Query
    // The actual SQL query will be complex, involving multiple joins and subqueries.
    // Key aspects:
    // - Select from 'threads' table (aliased as 't').
    // - Order by the timestamp of the latest email in each thread (descending).
    // - Paginate using 'LIMIT :limit OFFSET :offset'.
    // - Join with 'emails' (aliased 'e') to find the latest email for each thread.
    //   This often involves a subquery or a window function if supported and efficient.
    // - Join with 'persons' (aliased 'p_sender') for the sender of the latest email.
    // - Join with 'thread_participants' (aliased 'tp') and then 'persons' (aliased 'p_participant')
    //   to list participants. This might involve GROUP_CONCAT or fetching them in a separate query per thread.
    // - Calculate 'unread_count' per thread, specific to the requesting user (this adds complexity,
    //   often requiring a user_thread_status table or similar).

    // Conceptual main query structure:
    /*
    SELECT
        t.id AS thread_id,
        t.subject AS thread_subject,
        latest_email.timestamp AS last_reply_timestamp,
        latest_email.snippet AS latest_email_snippet,
        latest_email.id AS latest_email_id,
        latest_email_sender.name AS latest_email_sender_name,
        latest_email.is_read AS latest_email_is_read, -- This is user-specific and complex
        GROUP_CONCAT(DISTINCT p_participant.name SEPARATOR ', ') AS participants_names_str, -- Simplified participant list
        (SELECT COUNT(*) FROM emails e_unread WHERE e_unread.thread_id = t.id AND e_unread.is_read = 0) AS unread_count -- User-specific
    FROM
        threads t
    JOIN
        (SELECT -- Subquery to get the latest email per thread
             e_sub.thread_id, e_sub.id, e_sub.snippet, e_sub.timestamp, e_sub.sender_person_id, e_sub.is_read
         FROM emails e_sub
         INNER JOIN (
             SELECT thread_id, MAX(timestamp) AS max_timestamp
             FROM emails
             GROUP BY thread_id
         ) AS max_emails ON e_sub.thread_id = max_emails.thread_id AND e_sub.timestamp = max_emails.max_timestamp
        ) AS latest_email ON t.id = latest_email.thread_id
    LEFT JOIN
        persons latest_email_sender ON latest_email.sender_person_id = latest_email_sender.id
    LEFT JOIN
        thread_participants tp ON t.id = tp.thread_id
    LEFT JOIN
        persons p_participant ON tp.person_id = p_participant.id
    GROUP BY
        t.id, t.subject, latest_email.timestamp, latest_email.snippet, latest_email.id, latest_email_sender.name, latest_email.is_read
    ORDER BY
        latest_email.timestamp DESC
    LIMIT :limit OFFSET :offset;
    */

    // As this is a placeholder, we'll return an empty array for threads.
    $threads_from_db = []; // This would be $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Data Processing (Placeholder)
    $processed_threads = [];
    // foreach ($threads_from_db as $raw_thread) {
    //     $processed_threads[] = [
    //         "id" => $raw_thread['thread_id'],
    //         "subject" => $raw_thread['thread_subject'],
    //         "last_reply_at" => $raw_thread['last_reply_timestamp'], // Already formatted or format here
    //         "participant_avatars" => [], // Placeholder: Logic to fetch/determine avatars
    //         "participants_names" => $raw_thread['participants_names_str'], // Placeholder: Logic for "You, John & N others"
    //         "latest_email" => [
    //             "id" => $raw_thread['latest_email_id'],
    //             "snippet" => $raw_thread['latest_email_snippet'],
    //             "timestamp" => $raw_thread['last_reply_timestamp'], // Or specific latest_email_timestamp
    //             "sender_name" => $raw_thread['latest_email_sender_name'],
    //             "is_read" => (bool)$raw_thread['latest_email_is_read'] // User-specific
    //         ],
    //         "unread_count" => (int)$raw_thread['unread_count'] // User-specific
    //     ];
    // }

    // Placeholder for total items (requires a separate COUNT(*) query without LIMIT/OFFSET)
    // $count_stmt = $pdo->query("SELECT COUNT(*) FROM threads");
    // $total_items = $count_stmt->fetchColumn();
    $total_items = 0; // Placeholder
    $total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 1;
    if ($total_items == 0) $total_pages = 0;


    // 6. Success Response
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
    error_log("General Exception in get_feed.php: " . $e->getMessage());
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
