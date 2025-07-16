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

    // Main SQL Query using CTEs
    $sql = "
        WITH LatestEmailInThread AS (
            SELECT
                e.thread_id,
                e.id AS email_id,
                e.subject AS email_subject,
                SUBSTRING(e.body_text, 1, 100) AS body_preview,
                e.created_at AS email_timestamp,
                u.id AS sender_user_id,
                p.id AS sender_person_id,
                COALESCE(p.name, u.username) AS sender_name,
                p.avatar_url AS sender_avatar_url,
                COALESCE(es.status, 'unread') AS email_status,
                ROW_NUMBER() OVER(PARTITION BY e.thread_id ORDER BY e.created_at DESC) as rn
            FROM emails e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN persons p ON u.person_id = p.id
            LEFT JOIN email_statuses es ON e.id = es.email_id AND es.user_id = :current_user_id
        ),
        ThreadParticipantsAgg AS (
            SELECT
                e.thread_id,
                GROUP_CONCAT(DISTINCT COALESCE(p.name, u.username)) as participant_names_str
            FROM emails e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN persons p ON u.person_id = p.id
            GROUP BY e.thread_id
        )
        SELECT
            t.id AS thread_id,
            t.subject AS thread_subject,
            COALESCE(le.email_timestamp, t.last_activity_at) AS last_activity_at, -- Use email timestamp if available
            le.email_id, 
            le.email_subject, 
            le.body_preview, 
            le.email_timestamp,
            le.sender_user_id, 
            le.sender_person_id, 
            le.sender_name, 
            le.sender_avatar_url,
            le.email_status,
            tp.participant_names_str
        FROM threads t
        JOIN LatestEmailInThread le ON t.id = le.thread_id AND le.rn = 1
        LEFT JOIN ThreadParticipantsAgg tp ON t.id = tp.thread_id
        WHERE 1=1";

    $bindings = [':current_user_id' => $user_id];

    if ($group_id_filter !== null) {
        $sql .= " AND t.group_id = :group_id_filter";
        $bindings[':group_id_filter'] = $group_id_filter;
    }

    if ($person_id_filter !== null) {
        $sql .= " AND le.sender_person_id = :person_id_filter";
        $bindings[':person_id_filter'] = $person_id_filter;
    }

    if ($status_filter !== null) {
        if ($status_filter === 'unread') {
            $sql .= " AND (le.email_status = 'unread' OR le.email_status IS NULL)";
        } else {
            $sql .= " AND le.email_status = :status_filter";
            $bindings[':status_filter'] = $status_filter;
        }
    }
    
    // Ensure thread has at least one email (implicitly handled by JOIN with LatestEmailInThread)
    // but explicitly checking can be clearer or useful if JOIN type changes.
    // $sql .= " AND EXISTS (SELECT 1 FROM emails e_check WHERE e_check.thread_id = t.id)";


    // Count Query (must reflect the same filtering logic)
    $count_sql_outer = "SELECT COUNT(DISTINCT ft.id) FROM (
        SELECT t_outer.id
        FROM threads t_outer
        JOIN (
            SELECT e_inner.thread_id, e_inner.id AS latest_email_id,
                   p_inner.id as sender_person_id,
                   COALESCE(es_inner.status, 'unread') AS latest_email_status,
                   ROW_NUMBER() OVER(PARTITION BY e_inner.thread_id ORDER BY e_inner.created_at DESC) as rn
            FROM emails e_inner
            JOIN users u_inner ON e_inner.user_id = u_inner.id
            LEFT JOIN persons p_inner ON u_inner.person_id = p_inner.id
            LEFT JOIN email_statuses es_inner ON e_inner.id = es_inner.email_id AND es_inner.user_id = :current_user_id_count_outer
        ) le_check ON t_outer.id = le_check.thread_id AND le_check.rn = 1
        WHERE 1=1";

    $count_bindings_outer = [':current_user_id_count_outer' => $user_id];

    if ($group_id_filter !== null) {
        $count_sql_outer .= " AND t_outer.group_id = :group_id_filter_count_outer";
        $count_bindings_outer[':group_id_filter_count_outer'] = $group_id_filter;
    }

    if ($person_id_filter !== null) {
        $count_sql_outer .= " AND le_check.sender_person_id = :person_id_filter_count_outer";
        $count_bindings_outer[':person_id_filter_count_outer'] = $person_id_filter;
    }

    if ($status_filter !== null) {
        if ($status_filter === 'unread') {
            $count_sql_outer .= " AND (le_check.latest_email_status = 'unread' OR le_check.latest_email_status IS NULL)";
        } else {
            $count_sql_outer .= " AND le_check.latest_email_status = :status_filter_count_outer";
            $count_bindings_outer[':status_filter_count_outer'] = $status_filter;
        }
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
        $participants = [];
        if (!empty($row['participant_names_str'])) {
            $participants = array_unique(explode(',', $row['participant_names_str']));
        }

        $processed_threads[] = [
            'thread_id' => (int)$row['thread_id'],
            'subject' => $row['thread_subject'],
            'participants' => $participants, // Array of names
            'last_reply_time' => $row['last_activity_at'], // This is now correctly the latest email's timestamp or thread's last activity
            'emails' => [ // Emails array will contain only the latest email
                [
                    'email_id' => (int)$row['email_id'],
                    'parent_email_id' => null, // The CTE doesn't fetch parent_email_id for the latest email, adjust if needed
                    'subject' => $row['email_subject'],
                    'sender_user_id' => (int)$row['sender_user_id'],
                    'sender_person_id' => $row['sender_person_id'] ? (int)$row['sender_person_id'] : null,
                    'sender_name' => $row['sender_name'],
                    'sender_avatar_url' => $row['sender_avatar_url'] ?: '/avatars/default.png',
                    'body_preview' => $row['body_preview'],
                    'timestamp' => $row['email_timestamp'],
                    'status' => $row['email_status']
                ]
            ]
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