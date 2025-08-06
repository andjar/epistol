<?php

/**
 * Download Attachment API Endpoint
 * 
 * Downloads an attachment by its ID.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/db.php';

try {
    $db = new Database();
    
    // Get attachment ID
    $attachmentId = $_GET['id'] ?? null;
    
    if (!$attachmentId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Attachment ID is required',
            'message' => 'Please provide an attachment ID using the "id" parameter'
        ]);
        exit;
    }
    
    // Get attachment information
    $sql = "SELECT * FROM attachments WHERE id = ?";
    $stmt = $db->query($sql, [$attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attachment) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Attachment not found',
            'message' => 'The requested attachment does not exist'
        ]);
        exit;
    }
    
    $filepath = $attachment['filepath_on_disk'];
    
    // Check if file exists
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode([
            'error' => 'File not found',
            'message' => 'The attachment file does not exist on disk'
        ]);
        exit;
    }
    
    // Get file info
    $filesize = filesize($filepath);
    $filename = $attachment['filename'];
    $mimetype = $attachment['mimetype'];
    
    // Set headers for file download
    header('Content-Type: ' . $mimetype);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Output file contents
    readfile($filepath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} 