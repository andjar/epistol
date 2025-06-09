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
    $user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null; // This is users.id
    $status_filter_val = isset($_GET['status']) && !empty($_GET['status']) ? trim($_GET['status']) : null; // Renamed to avoid conflict
    $group_id_filter_val = isset($_GET['group_id']) && !empty($_GET['group_id']) ? filter_var($_GET['group_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;

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
    if (isset($_GET['group_id']) && !empty($_GET['group_id']) && ($group_id_filter_val === false || $group_id_filter_val <=0)) {
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

    // Base SQL parts
    $select_sql = "SELECT
            t.id AS thread_id,
            t.subject AS thread_subject,
            t.last_activity_at AS last_reply_time,
            e.id AS email_id,
            e.parent_email_id,
            e.subject AS email_subject,
            SUBSTRING(e.body_text, 1, 100) AS body_preview,
            e.created_at AS email_timestamp,
            u.id AS sender_user_id,
            p.id AS sender_person_id,
            COALESCE(p.name, u.username) AS sender_name,
            p.avatar_url AS sender_avatar_url,
            COALESCE(es.status, 'unread') AS email_status";

    $from_sql = "FROM threads t
        JOIN emails e ON t.id = e.thread_id
        JOIN users u ON e.user_id = u.id
        LEFT JOIN persons p ON u.person_id = p.id
        LEFT JOIN email_statuses es ON e.id = es.email_id AND es.user_id = :current_user_id";

    // WHERE clause construction for the main query
    // The subquery for paginated_thread_ids determines *which threads* appear on the page.
    // The outer WHERE clause then filters *emails within those threads*.
    // This could lead to threads appearing with zero emails if all are filtered out by status.

    $where_conditions = [];
    $bindings = [ // Bindings for the main query
        ':current_user_id' => $user_id
    ];

    // Subquery for paginated thread IDs
    $paginated_threads_sql_parts = ["SELECT t_inner.id FROM threads t_inner"];
    $paginated_threads_bindings = [ // Bindings for the subquery
        ':limit_sub' => $limit,
        ':offset_sub' => $offset
    ];

    if ($group_id_filter_val) {
        $paginated_threads_sql_parts[] = "WHERE t_inner.group_id = :group_id_filter_sub";
        $paginated_threads_bindings[':group_id_filter_sub'] = $group_id_filter_val;
    }
    // Note: Status filter is not applied to thread pagination for simplicity, matching original behavior mostly.
    // A more complex status filter on threads would require an EXISTS subquery here.

    $paginated_threads_sql_parts[] = "ORDER BY t_inner.last_activity_at DESC LIMIT :limit_sub OFFSET :offset_sub";
    $paginated_threads_subquery = implode(" ", $paginated_threads_sql_parts);

    // Execute subquery to get paginated thread IDs
    $stmt_paginated_ids = $pdo->prepare($paginated_threads_subquery);
    foreach ($paginated_threads_bindings as $key_sub => $value_sub) {
        $stmt_paginated_ids->bindValue($key_sub, $value_sub, is_int($value_sub) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt_paginated_ids->execute();
    $paginated_thread_ids = $stmt_paginated_ids->fetchAll(PDO::FETCH_COLUMN);

    if (empty($paginated_thread_ids)) {
        // No threads found for this page, send empty response with pagination info
        send_json_success([
            'threads' => [],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => 0, // Will be updated by count query later if needed, but 0 for now
                'total_pages' => 0
            ]
        ]);
        // Exiting here as no further processing is needed
    }

    // Build placeholder string for IN clause
    $in_clause_placeholders = implode(',', array_fill(0, count($paginated_thread_ids), '?'));
    $where_conditions[] = "t.id IN ({$in_clause_placeholders})";
    // Add paginated thread IDs to the main query bindings
    // PDO does not directly support binding an array to IN(), so we add them one by one.
    // The actual values will be passed during execute() by merging.

    // Status filter for emails within the selected threads
    if ($status_filter_val) {
        if ($status_filter_val === 'unread') {
            $where_conditions[] = "(es.id IS NULL OR es.status = 'unread')";
        } else {
            $where_conditions[] = "es.status = :status_filter_main";
            $bindings[':status_filter_main'] = $status_filter_val;
        }
    }

    $sql = $select_sql . " " . $from_sql;
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    // Order emails within each thread by their creation time
    $sql .= " ORDER BY t.last_activity_at DESC, e.created_at ASC;";

    $stmt = $pdo->prepare($sql);

    // Merge main bindings with thread ID placeholders for IN clause
    $execute_bindings = $bindings;
    // PDO requires positional placeholders for IN clause to be passed in execute array
    $i = 1;
    foreach($paginated_thread_ids as $tid) {
        // This is incorrect way to handle IN for prepared statements.
        // Placeholders were already added as '?'. Values should be in execute array.
        // The $execute_bindings array should just contain $paginated_thread_ids appended.
    }
    // Correct way: $execute_bindings = array_merge(array_values($bindings), $paginated_thread_ids);
    // However, named parameters and positional parameters cannot be mixed.
    // So, we will use named parameters for thread IDs as well.

    // Rebuild IN clause with named placeholders for thread IDs
    $in_clause_named_placeholders = [];
    $thread_id_bindings = [];
    foreach ($paginated_thread_ids as $idx => $tid_val) {
        $ph = ":thread_id_in_" . $idx;
        $in_clause_named_placeholders[] = $ph;
        $thread_id_bindings[$ph] = $tid_val;
    }
    // Update the WHERE clause for t.id IN ()
    // Find and replace the t.id IN (...) part
    foreach ($where_conditions as $k_wc => $wc_val) {
        if (strpos($wc_val, "t.id IN (") === 0) {
            $where_conditions[$k_wc] = "t.id IN (" . implode(',', $in_clause_named_placeholders) . ")";
            break;
        }
    }
    $sql = $select_sql . " " . $from_sql . " WHERE " . implode(" AND ", $where_conditions) . " ORDER BY t.last_activity_at DESC, e.created_at ASC;";
    $stmt = $pdo->prepare($sql); // Re-prepare with new SQL
    $execute_bindings = array_merge($bindings, $thread_id_bindings);


    foreach ($execute_bindings as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count query for total items
    $count_sql_parts = ["SELECT COUNT(DISTINCT t.id) FROM threads t"];
    $count_bindings = [];

    if ($group_id_filter_val) {
        $count_sql_parts[] = "WHERE t.group_id = :group_id_filter_count";
        $count_bindings[':group_id_filter_count'] = $group_id_filter_val;
    }
    $count_sql = implode(" ", $count_sql_parts);
    // Status filter is not applied to total count, maintaining original behavior.

    $count_stmt = $pdo->prepare($count_sql);
    foreach ($count_bindings as $key_count => $value_count) {
        $count_stmt->bindValue($key_count, $value_count, is_int($value_count) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_items = (int)$count_stmt->fetchColumn();

    // 5. Data Processing
    $processed_threads = [];
    $threads_map = [];

    foreach ($results as $row) {
        $thread_id_val = (int)$row['thread_id'];

        if (!isset($threads_map[$thread_id_val])) {
            $threads_map[$thread_id_val] = [
                'thread_id' => $thread_id_val,
                'subject' => $row['thread_subject'],
                'participants' => [],
                'last_reply_time' => $row['last_reply_time'],
                'emails' => []
            ];
        }

        $threads_map[$thread_id_val]['emails'][] = [
            'email_id' => (int)$row['email_id'],
            'parent_email_id' => $row['parent_email_id'] ? (int)$row['parent_email_id'] : null,
            'subject' => $row['email_subject'],
            'sender_user_id' => (int)$row['sender_user_id'],
            'sender_person_id' => $row['sender_person_id'] ? (int)$row['sender_person_id'] : null,
            'sender_name' => $row['sender_name'],
            'sender_avatar_url' => $row['sender_avatar_url'] ?: '/avatars/default.png',
            'body_preview' => $row['body_preview'],
            'timestamp' => $row['email_timestamp'],
            'status' => $row['email_status']
        ];
    }
    // The rest of the PHP processing for participants and final array structure...
    // This part is complex and error-prone to change in one go.
    // The key changes are in SQL and initial data mapping.

    // Populate participants and structure the final array
    foreach ($threads_map as $thread_id_map_key => $thread_data_map_val) {
        $participant_names = [];
        foreach ($thread_data_map_val['emails'] as $email_item) {
            if (!in_array($email_item['sender_name'], $participant_names)) {
                $participant_names[] = $email_item['sender_name'];
            }
        }
        // Assigning to a new variable to avoid modifying iterable $threads_map directly if it causes issues
        $current_thread_processed_data = $thread_data_map_val;
        $current_thread_processed_data['participants'] = $participant_names;
        $processed_threads[] = $current_thread_processed_data;
    }

    // The total_items and total_pages calculation was moved up for early exit.
    // Recalculate total_pages based on potentially new total_items
    $total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 0;
    if ($total_items == 0) {
        $page = 0; // If no items, current page can be considered 0 or 1.
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