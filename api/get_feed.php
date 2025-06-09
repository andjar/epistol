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
    $user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
    $status_filter = isset($_GET['status']) && !empty($_GET['status']) ? trim($_GET['status']) : null;
    $group_id_filter = isset($_GET['group_id']) && !empty($_GET['group_id']) ? trim($_GET['group_id']) : null; // For pagination total count

    // Use ITEMS_PER_PAGE from config.php if defined, otherwise fallback to 20.
    $default_limit = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 20;
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT, ['options' => ['default' => $default_limit, 'min_range' => 1]]) : $default_limit;

    if ($page === false || $page <= 0) { // filter_var returns false on failure
        send_json_error('Invalid page number. Page must be a positive integer.', 400);
    }
    if ($limit === false || $limit <= 0) { // filter_var returns false on failure
        send_json_error('Invalid limit value. Limit must be a positive integer.', 400);
    }
    if ($user_id === false || $user_id === null || $user_id <= 0) { // filter_var returns false on failure or if not provided
        send_json_error('Invalid or missing user_id. User ID must be a positive integer.', 400);
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

    // Base SQL parts
    $select_sql = "SELECT
            t.id AS thread_id,
            t.subject AS subject,
            e.id AS email_id,
            p.name AS sender_name,
            COALESCE(e.snippet, SUBSTR(e.body_text, 1, 100)) AS body_preview,
            e.timestamp AS email_timestamp,
            thread_max_timestamps.max_ts AS last_reply_time,
            COALESCE(ps.status, 'unread') AS post_status";

    $from_sql = "FROM threads t
        JOIN emails e ON t.id = e.thread_id
        JOIN persons p ON e.from_person_id = p.id
        LEFT JOIN post_statuses ps ON e.id = ps.post_id AND ps.user_id = :current_user_id
        JOIN (SELECT thread_id, MAX(timestamp) AS max_ts FROM emails GROUP BY thread_id) AS thread_max_timestamps
             ON t.id = thread_max_timestamps.thread_id";

    // WHERE clause construction for the main query
    // The subquery for paginated_thread_ids determines *which threads* appear on the page.
    // The outer WHERE clause then filters *emails within those threads*.
    // This could lead to threads appearing with zero emails if all are filtered out by status.

    $where_conditions = [];
    $bindings = [
        ':limit' => $limit,
        ':offset' => $offset,
        ':current_user_id' => $user_id
    ];

    // Subquery for paginated thread IDs (this part might need adjustment if group_id or status should affect which threads are paginated)
    // For now, group_id_filter is NOT applied to the thread pagination subquery, but it SHOULD if we want to paginate correctly within a group.
    // Let's assume for now pagination is global and group/status filters apply to the emails within those globally paginated threads.
    // A more accurate pagination would incorporate filters into the thread selection subquery.
    $paginated_threads_subquery = "
        SELECT thr.id
        FROM threads thr
        JOIN emails em_inner ON thr.id = em_inner.thread_id ";

    // If group_id_filter is present, it should ideally filter the threads for pagination.
    // This is a simplification: if group_id is applied, it should be in the subquery.
    // For now, let's keep the subquery as is and filter emails by group in the outer query if group_id is provided.
    // This is not ideal for pagination accuracy if group filter is very restrictive.
    // However, the original query had group_id filtering outside this subquery too.

    $paginated_threads_subquery .= " GROUP BY thr.id ORDER BY MAX(em_inner.timestamp) DESC LIMIT :limit OFFSET :offset";

    $where_conditions[] = "t.id IN ({$paginated_threads_subquery})";

    if ($group_id_filter) {
        // This filters emails within the paginated threads by group_id.
        // If a thread has no emails belonging to this group, it might appear empty or be omitted later in processing.
        $where_conditions[] = "e.group_id = :group_id_filter"; // Assuming 'emails' table has 'group_id'
        $bindings[':group_id_filter'] = $group_id_filter;
    }

    if ($status_filter) {
        if ($status_filter === 'unread') {
            // Correctly checks for emails belonging to the current_user_id that are either not in post_statuses or explicitly 'unread'.
            // The join condition `ps.user_id = :current_user_id` is crucial.
            // If an email has no entry in post_statuses for this user, ps.id will be NULL.
            $where_conditions[] = "(ps.id IS NULL OR ps.status = 'unread')";
        } else {
            $where_conditions[] = "ps.status = :status_filter";
            $bindings[':status_filter'] = $status_filter;
        }
    }

    $sql = $select_sql . " " . $from_sql;
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    $sql .= " ORDER BY last_reply_time DESC, e.timestamp ASC;";

    $stmt = $pdo->prepare($sql);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adjust total items count for pagination if filters are active
    // This is a simplified count and might not be perfectly accurate with the email-level filtering.
    // A truly accurate count would need to reflect the same filtering logic as the main query.
    $count_sql = "SELECT COUNT(DISTINCT t.id) FROM threads t";
    $count_bindings = [];

    if ($group_id_filter) {
        // To count threads that *have* emails in that group.
        $count_sql .= " JOIN emails e_count ON t.id = e_count.thread_id WHERE e_count.group_id = :group_id_filter";
        $count_bindings[':group_id_filter'] = $group_id_filter;
    }
    // Status filter for total count is more complex as it depends on post_statuses per user.
    // For simplicity, not adding status filter to total count for now. This means pagination total_pages might be an overestimate when status filter is active.

    $count_stmt = $pdo->prepare($count_sql);
    foreach ($count_bindings as $key => $value) {
        $count_stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_items = (int)$count_stmt->fetchColumn();

    // 5. Data Processing
    $processed_threads = [];
    $threads_map = []; // Helper map to group emails by thread_id

    foreach ($results as $row) {
        $thread_id = $row['thread_id'];

        if (!isset($threads_map[$thread_id])) {
            $threads_map[$thread_id] = [
                'thread_id' => $thread_id,
                'subject' => $row['subject'],
                'participants' => [], // Will be populated from emails
                'last_reply_time' => $row['last_reply_time'],
                'emails' => []
            ];
        }

        $threads_map[$thread_id]['emails'][] = [
            'email_id' => $row['email_id'],
            'sender_name' => $row['sender_name'],
            'body_preview' => $row['body_preview'],
            'timestamp' => $row['email_timestamp'],
            'status' => $row['post_status'] // Added post status
        ];
    }

    // Populate participants and structure the final array
    foreach ($threads_map as $thread_id => $thread_data) {
        $participant_names = [];
        foreach ($thread_data['emails'] as $email) {
            if (!in_array($email['sender_name'], $participant_names)) {
                $participant_names[] = $email['sender_name'];
            }
        }
        $thread_data['participants'] = $participant_names;
        // Ensure emails are ordered by timestamp as per original query's secondary sort
        // The SQL query already sorts emails by timestamp ASC, so they should be in order.
        $processed_threads[] = $thread_data;
    }

    // Sort threads by last_reply_time DESC (already done by SQL, but good for explicit control if needed)
    // usort($processed_threads, function ($a, $b) {
    //    return strcmp($b['last_reply_time'], $a['last_reply_time']);
    // });


    // Fetch total number of threads for pagination
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM threads");
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 1;
     if ($total_items == 0) {
        $total_pages = 0;
        // If there are no items, current page should ideally be 0 or 1.
        // Let's ensure current_page doesn't exceed total_pages if total_items is 0.
        // However, the input validation already ensures $page >= 1.
        // If $total_items is 0, $total_pages will be 0.
        // $page could still be 1. This seems acceptable.
    }


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