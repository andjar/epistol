<?php
/**
 * API Endpoint: /api/v1/get_groups.php
 *
 * Description:
 * Fetches a paginated list of all groups.
 * Includes group details such as ID, name, creation date, and member count.
 * Groups are ordered by creation date in descending order.
 *
 * Request Method:
 * GET
 *
 * Input Parameters ($_GET):
 *  - page (integer, optional, default: 1): Page number for pagination.
 *  - limit (integer, optional, default: 10, max: 100): Number of groups per page.
 *
 * Outputs:
 *
 *  Success (200 OK):
 *  JSON Response:
 *  {
 *    "status": "success",
 *    "data": {
 *      "groups": [
 *        {
 *          "group_id": "grp_abc123",
 *          "group_name": "Administrators",
 *          "created_at": "YYYY-MM-DD HH:MM:SS",
 *          "member_count": 5
 *        },
 *        // ... more groups
 *      ],
 *      "pagination": {
 *        "current_page": 1,
 *        "per_page": 10,
 *        "total_pages": 7,
 *        "total_groups": 68
 *      }
 *    }
 *  }
 *
 *  Failure:
 *  - 405 Method Not Allowed: If the request method is not GET.
 *    JSON Response: {"status": "error", "message": "Only GET requests are allowed."}
 *  - 500 Internal Server Error: If a database error or other unexpected server error occurs.
 *    JSON Response: {"status": "error", "message": "A database error occurred."} or {"status": "error", "message": "An unexpected error occurred: Specific details."}
 *    (Note: Input validation for page/limit, if invalid, might result in default values rather than a 400 error,
 *     as per current implementation which clamps values to valid ranges.)
 *
 * Database Interaction:
 * - Queries the 'groups' table for group details.
 * - Uses a subquery to count members for each group from the 'group_members' table.
 * - Implements pagination using LIMIT and OFFSET.
 * - Calculates the total number of groups for pagination metadata.
 */

require_once __DIR__ . '/../../src/helpers.php'; // Corrected path
require_once __DIR__ . '/../../src/db.php';       // Corrected path
require_once __DIR__ . '/../../config/config.php'; // Corrected path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error('Only GET requests are allowed.', 405);
}

try {
    $pdo = get_db_connection();

    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default limit to 10

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100; // Max limit

    $offset = ($page - 1) * $limit;

    // Base query to fetch groups and their member counts
    // Using a subquery for member_count
    $sql = "SELECT g.group_id, g.name AS group_name, g.created_at,
                   (SELECT COUNT(gm.person_id) FROM group_members gm WHERE gm.group_id = g.group_id) as member_count
            FROM groups g
            ORDER BY g.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For total count for pagination metadata
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM groups");
    $total_groups = (int)$total_stmt->fetchColumn();
    $total_pages = ($limit > 0 && $total_groups > 0) ? ceil($total_groups / $limit) : 0;

    send_json_success([
        'groups' => $groups,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_pages' => $total_pages,
            'total_groups' => $total_groups
        ]
    ]);

} catch (PDOException $e) {
    error_log("PDOException in get_groups.php: " . $e->getMessage());
    send_json_error('A database error occurred.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_groups.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred.', 500);
}
?>
