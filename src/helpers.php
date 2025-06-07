<?php

function send_json_error($message, $http_status_code)
{
    http_response_code($http_status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}

function send_json_success($data, $http_status_code = 200)
{
    http_response_code($http_status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}
?>
