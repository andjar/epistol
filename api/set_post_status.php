<?php

require_once '../src/helpers.php';
require_once '../src/db.php';
require_once '../config/config.php';

header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Get input data
// Allow for testing override of php://input
$raw_input = isset($GLOBALS['mock_php_input']) ? $GLOBALS['mock_php_input'] : file_get_contents('php://input');
$data = json_decode($raw_input, true);

if ($data === null && empty($GLOBALS['mock_php_input'])) { // Allow empty mock_php_input to simulate null json_decode for specific tests if needed
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validate input parameters
$post_id = $data['post_id'] ?? null;
$user_id = $data['user_id'] ?? null;
$status = $data['status'] ?? null;

$allowed_statuses = ['read', 'follow-up', 'important-info', 'unread'];

if (empty($post_id) || !is_numeric($post_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'post_id is required and must be a number.']);
    exit;
}

if (empty($user_id) || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id is required and must be a number.']);
    exit;
}

if (empty($status) || !in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'status is required and must be one of: ' . implode(', ', $allowed_statuses)]);
    exit;
}

// Database connection
$pdo = get_db_connection();

try {
    // Check if post and user exist (optional, but good practice)
    // For brevity, we'll assume they exist based on foreign key constraints

    // Check if a status already exists for this post_id and user_id
    $stmt_check = $pdo->prepare("SELECT id FROM post_statuses WHERE post_id = :post_id AND user_id = :user_id");
    $stmt_check->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $existing_status = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existing_status) {
        // Update existing status
        $stmt_update = $pdo->prepare("UPDATE post_statuses SET status = :status, created_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt_update->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt_update->bindParam(':id', $existing_status['id'], PDO::PARAM_INT);
        if ($stmt_update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Post status updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update post status.']);
        }
    } else {
        // Insert new status
        $stmt_insert = $pdo->prepare("INSERT INTO post_statuses (post_id, user_id, status) VALUES (:post_id, :user_id, :status)");
        $stmt_insert->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':status', $status, PDO::PARAM_STR);
        if ($stmt_insert->execute()) {
            echo json_encode(['success' => true, 'message' => 'Post status created successfully.', 'id' => $pdo->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create post status.']);
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    // Log error to a file or monitoring system for production
    // error_log("Database error in set_post_status.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    // error_log("General error in set_post_status.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}

?>
