<?php
// api/get_groups.php

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php';

// Set content type
header('Content-Type: application/json');

// Purpose: Get a list of all groups.
// Request Method: GET.
// Inputs:
//   - Optional pagination parameters from $_GET (e.g., 'page', 'limit').
// Outputs:
//   - Success: JSON array of group objects. Each object could contain:
//     - group_id (string)
//     - group_name (string)
//     - member_count (integer) - (Requires a join or subquery)
//     - created_at (timestamp)
//   - Failure: JSON response with status "error" and an error message.
// DB Interaction:
//   - Query the 'groups' table.
//   - Optionally, join with 'group_members' table and use COUNT aggregate to get member_count for each group.
//   - Implement pagination using LIMIT and OFFSET if parameters are provided.
// Error Handling:
//   - Invalid request method (though less common to check for GET, good for consistency).
//   - Invalid pagination parameters (e.g., non-integer page/limit).
//   - Database errors during query (500).

// TODO: Implement API endpoint logic as per comments above
// - Request method validation (e.g., GET)
// - Input validation (optional pagination parameters from $_GET)
// - Database interaction (query groups, calculate member_count, implement pagination)
// - Success/Error responses using send_json_success/send_json_error

// Example placeholder response for now
send_json_success(['message' => 'API endpoint ' . basename(__FILE__) . ' placeholder.']);
?>
