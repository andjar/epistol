<?php
/**
 * API Endpoint: /api/v1/get_group_members.php
 *
 * Description:
 * Fetches a paginated list of members for a specified group.
 * Member details are retrieved by joining with the 'persons' table.
 *
 * Request Method:
 * GET
 *
 * Input Parameters ($_GET):
 *  - group_id (string, required): The ID of the group for which to fetch members.
 *  - page (integer, optional, default: 1): Page number for pagination.
 *  - limit (integer, optional, default: 10, max: 100): Number of members per page.
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "group_id": "grp_abc123",
 *      "members": [
 *        {
 *          "person_id": "psn_xyz789",
 *          "name": "John Doe",
 *          "email": "john.doe@example.com",
 *          "avatar_url": "/avatars/psn_xyz789.png",
 *          "joined_at": "YYYY-MM-DD HH:MM:SS"
 *        },
 *        // ... more members
 *      ],
 *      "pagination": {
 *        "current_page": 1,
 *        "per_page": 10,
 *        "total_pages": 3,
 *        "total_members": 25
 *      }
 *    }
 *  }
 *
 *  Failure:
 *  - 400 Bad Request: Missing or empty 'group_id'.
 *    JSON Response: {"status": "error", "message": "Group ID is required."}
 *  - 404 Not Found: If the specified 'group_id' does not exist in the 'groups' table.
 *    JSON Response: {"status": "error", "message": "Group not found."}
 *  - 405 Method Not Allowed: If the request method is not GET.
 *    JSON Response: {"status": "error", "message": "Only GET requests are allowed."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *
 * Database Interaction:
 * - Validates the existence of the 'group_id' in the 'groups' table.
 * - Queries the 'group_members' table, filtering by 'group_id'.
 * - Joins with the 'persons' table to fetch member details (person_id, name, primary_email_address, avatar_url).
 * - Fetches 'joined_at' timestamp from 'group_members'.
 * - Implements pagination using LIMIT and OFFSET.
 * - Calculates total number of members in the group for pagination metadata.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error('Only GET requests are allowed.', 405);
}

// Validate group_id
if (!isset($_GET['group_id']) || empty(trim($_GET['group_id']))) {
    send_json_error('Group ID is required.', 400);
}
$group_id = trim($_GET['group_id']);

try {
    $pdo = get_db_connection();

    // Check if group exists
    $stmt_check_group = $pdo->prepare("SELECT group_id FROM groups WHERE group_id = :group_id");
    $stmt_check_group->bindParam(':group_id', $group_id);
    $stmt_check_group->execute();
    if (!$stmt_check_group->fetch()) {
        send_json_error('Group not found.', 404);
    }

    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100; // Max limit

    $offset = ($page - 1) * $limit;

    // Query to fetch group members with person details
    // Assuming 'persons' table has 'person_id', 'name', 'primary_email_address', 'avatar_url'
    // Assuming 'group_members' table has 'group_id', 'person_id', 'joined_at'
    $sql = "SELECT p.person_id, p.name, p.primary_email_address AS email, p.avatar_url, gm.joined_at
            FROM group_members gm
            JOIN persons p ON gm.person_id = p.person_id
            WHERE gm.group_id = :group_id
            ORDER BY p.name ASC
            LIMIT :limit OFFSET :offset";

    $stmt_members = $pdo->prepare($sql);
    $stmt_members->bindParam(':group_id', $group_id);
    $stmt_members->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt_members->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_members->execute();

    $members = $stmt_members->fetchAll(PDO::FETCH_ASSOC);

    // For total count for pagination metadata
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = :group_id");
    $total_stmt->bindParam(':group_id', $group_id);
    $total_stmt->execute();
    $total_members = (int)$total_stmt->fetchColumn();
    $total_pages = ($limit > 0 && $total_members > 0) ? ceil($total_members / $limit) : 0;
    if ($total_members == 0) $page = 0;


    send_json_success([
        'group_id' => $group_id,
        'members' => $members,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_pages' => $total_pages,
            'total_members' => $total_members
        ]
    ]);

} catch (PDOException $e) {
    error_log("PDOException in get_group_members.php: " . $e->getMessage());
    send_json_error('A database error occurred.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_group_members.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred.', 500);
}
?>
