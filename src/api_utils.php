<?php
/**
 * API Utility Functions
 * Common functions used across API endpoints
 */

/**
 * Send a JSON success response
 * @param mixed $data The data to send
 * @param int $status_code HTTP status code (default: 200)
 */
function send_json_success($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}

/**
 * Send a JSON error response
 * @param string $message Error message
 * @param int $status_code HTTP status code (default: 400)
 */
function send_json_error($message, $status_code = 400) {
    http_response_code($status_code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
} 