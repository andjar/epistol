<?php

/**
 * Search API Endpoint
 * 
 * Searches through emails, threads, and persons based on query terms.
 * Supports searching in subjects, email bodies, and sender names.
 * Enhanced with date range filtering, sender/recipient filtering, and result highlighting.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/db.php';

try {
    $db = new Database();
    
    // Get search parameters
    $query = $_GET['q'] ?? $_POST['q'] ?? '';
    $type = $_GET['type'] ?? $_POST['type'] ?? 'all'; // all, emails, threads, persons
    $limit = min((int)($_GET['limit'] ?? $_POST['limit'] ?? 50), 100); // Max 100 results
    $offset = max((int)($_GET['offset'] ?? $_POST['offset'] ?? 0), 0);
    
    // Enhanced filtering parameters
    $dateFrom = $_GET['date_from'] ?? $_POST['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? $_POST['date_to'] ?? null;
    $senderFilter = $_GET['sender'] ?? $_POST['sender'] ?? null;
    $recipientFilter = $_GET['recipient'] ?? $_POST['recipient'] ?? null;
    $hasAttachments = $_GET['has_attachments'] ?? $_POST['has_attachments'] ?? null;
    $sentByMe = $_GET['sent_by_me'] ?? $_POST['sent_by_me'] ?? null;
    $receivedByMe = $_GET['received_by_me'] ?? $_POST['received_by_me'] ?? null;
    
    if (empty($query)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Search query is required',
            'message' => 'Please provide a search query using the "q" parameter'
        ]);
        exit;
    }
    
    $searchTerm = '%' . $query . '%';
    $results = [];
    
    switch ($type) {
        case 'emails':
            $results = searchEmails($db, $searchTerm, $limit, $offset, $dateFrom, $dateTo, $senderFilter, $recipientFilter, $hasAttachments, $sentByMe, $receivedByMe);
            break;
        case 'threads':
            $results = searchThreads($db, $searchTerm, $limit, $offset, $dateFrom, $dateTo);
            break;
        case 'persons':
            $results = searchPersons($db, $searchTerm, $limit, $offset);
            break;
        case 'all':
        default:
            $results = searchAll($db, $searchTerm, $limit, $offset, $dateFrom, $dateTo, $senderFilter, $recipientFilter, $hasAttachments, $sentByMe, $receivedByMe);
            break;
    }
    
    // Highlight search terms in results
    $results = highlightSearchTerms($results, $query);
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'type' => $type,
        'total' => count($results),
        'limit' => $limit,
        'offset' => $offset,
        'filters' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sender' => $senderFilter,
            'recipient' => $recipientFilter,
            'has_attachments' => $hasAttachments,
            'sent_by_me' => $sentByMe,
            'received_by_me' => $receivedByMe
        ],
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Search in emails with enhanced filtering
 */
function searchEmails($db, $searchTerm, $limit, $offset, $dateFrom = null, $dateTo = null, $senderFilter = null, $recipientFilter = null, $hasAttachments = null, $sentByMe = null, $receivedByMe = null) {
    $sql = "SELECT DISTINCT 
                e.id,
                e.subject,
                e.body_text,
                e.body_html,
                e.created_at,
                e.thread_id,
                t.subject as thread_subject,
                p.name as sender_name,
                ea.email_address as sender_email,
                u.id as sender_user_id,
                (SELECT COUNT(*) FROM attachments WHERE email_id = e.id) as attachment_count
            FROM emails e
            LEFT JOIN threads t ON e.thread_id = t.id
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN persons p ON u.person_id = p.id
            LEFT JOIN email_addresses ea ON p.id = ea.person_id AND ea.is_primary = 1
            LEFT JOIN email_recipients er ON e.id = er.email_id
            LEFT JOIN email_addresses recipient_ea ON er.email_address_id = recipient_ea.id
            LEFT JOIN persons recipient_p ON recipient_ea.person_id = recipient_p.id
            WHERE (e.subject LIKE ? OR e.body_text LIKE ? OR p.name LIKE ? OR ea.email_address LIKE ?)";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    // Date range filtering
    if ($dateFrom) {
        $sql .= " AND e.created_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $sql .= " AND e.created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }
    
    // Sender filtering
    if ($senderFilter) {
        $sql .= " AND (p.name LIKE ? OR ea.email_address LIKE ?)";
        $params[] = '%' . $senderFilter . '%';
        $params[] = '%' . $senderFilter . '%';
    }
    
    // Recipient filtering
    if ($recipientFilter) {
        $sql .= " AND (recipient_p.name LIKE ? OR recipient_ea.email_address LIKE ?)";
        $params[] = '%' . $recipientFilter . '%';
        $params[] = '%' . $recipientFilter . '%';
    }
    
    // Attachment filtering
    if ($hasAttachments !== null) {
        if ($hasAttachments) {
            $sql .= " AND EXISTS (SELECT 1 FROM attachments WHERE email_id = e.id)";
        } else {
            $sql .= " AND NOT EXISTS (SELECT 1 FROM attachments WHERE email_id = e.id)";
        }
    }
    
    // Sent by me filtering (assuming current user ID is 1 for now)
    if ($sentByMe) {
        $sql .= " AND e.user_id = 1";
    }
    
    // Received by me filtering (assuming current user ID is 1 for now)
    if ($receivedByMe) {
        $sql .= " AND EXISTS (SELECT 1 FROM email_recipients er2 JOIN email_addresses ea2 ON er2.email_address_id = ea2.id WHERE er2.email_id = e.id AND ea2.person_id = 1)";
    }
    
    $sql .= " ORDER BY e.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->query($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Search in threads with date filtering
 */
function searchThreads($db, $searchTerm, $limit, $offset, $dateFrom = null, $dateTo = null) {
    $sql = "SELECT 
                t.id,
                t.subject,
                t.created_at,
                t.last_activity_at,
                COUNT(e.id) as email_count,
                p.name as creator_name
            FROM threads t
            LEFT JOIN emails e ON t.id = e.thread_id
            LEFT JOIN users u ON t.created_by_user_id = u.id
            LEFT JOIN persons p ON u.person_id = p.id
            WHERE t.subject LIKE ?";
    
    $params = [$searchTerm];
    
    // Date range filtering
    if ($dateFrom) {
        $sql .= " AND t.created_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $sql .= " AND t.created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }
    
    $sql .= " GROUP BY t.id ORDER BY t.last_activity_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->query($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Search in persons (name and email)
 */
function searchPersons($db, $searchTerm, $limit, $offset) {
    $sql = "SELECT DISTINCT
                p.id,
                p.name,
                p.created_at,
                ea.email_address,
                COUNT(DISTINCT e.id) as email_count
            FROM persons p
            LEFT JOIN email_addresses ea ON p.id = ea.person_id
            LEFT JOIN email_recipients er ON ea.person_id = er.person_id
            LEFT JOIN emails e ON er.email_id = e.id
            WHERE p.name LIKE ? OR ea.email_address LIKE ?
            GROUP BY p.id
            ORDER BY p.name ASC
            LIMIT ? OFFSET ?";
    
    $stmt = $db->query($sql, [$searchTerm, $searchTerm, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Search across all types with enhanced filtering
 */
function searchAll($db, $searchTerm, $limit, $offset, $dateFrom = null, $dateTo = null, $senderFilter = null, $recipientFilter = null, $hasAttachments = null, $sentByMe = null, $receivedByMe = null) {
    $results = [];
    
    // Search emails
    $emails = searchEmails($db, $searchTerm, $limit / 3, 0, $dateFrom, $dateTo, $senderFilter, $recipientFilter, $hasAttachments, $sentByMe, $receivedByMe);
    foreach ($emails as $email) {
        $email['type'] = 'email';
        $results[] = $email;
    }
    
    // Search threads
    $threads = searchThreads($db, $searchTerm, $limit / 3, 0, $dateFrom, $dateTo);
    foreach ($threads as $thread) {
        $thread['type'] = 'thread';
        $results[] = $thread;
    }
    
    // Search persons
    $persons = searchPersons($db, $searchTerm, $limit / 3, 0);
    foreach ($persons as $person) {
        $person['type'] = 'person';
        $results[] = $person;
    }
    
    // Sort by relevance (emails first, then threads, then persons)
    usort($results, function($a, $b) {
        $typeOrder = ['email' => 1, 'thread' => 2, 'person' => 3];
        return $typeOrder[$a['type']] - $typeOrder[$b['type']];
    });
    
    // Apply limit and offset
    $results = array_slice($results, $offset, $limit);
    
    return $results;
}

/**
 * Highlight search terms in results
 */
function highlightSearchTerms($results, $query) {
    $terms = explode(' ', $query);
    $highlighted = [];
    
    foreach ($results as $result) {
        $highlightedResult = $result;
        
        // Highlight in subject
        if (isset($result['subject'])) {
            $highlightedResult['subject_highlighted'] = highlightText($result['subject'], $terms);
        }
        
        // Highlight in body text
        if (isset($result['body_text'])) {
            $highlightedResult['body_highlighted'] = highlightText($result['body_text'], $terms);
        }
        
        // Highlight in sender name
        if (isset($result['sender_name'])) {
            $highlightedResult['sender_name_highlighted'] = highlightText($result['sender_name'], $terms);
        }
        
        // Highlight in thread subject
        if (isset($result['thread_subject'])) {
            $highlightedResult['thread_subject_highlighted'] = highlightText($result['thread_subject'], $terms);
        }
        
        $highlighted[] = $highlightedResult;
    }
    
    return $highlighted;
}

/**
 * Highlight text with search terms
 */
function highlightText($text, $terms) {
    if (empty($text) || empty($terms)) {
        return $text;
    }
    
    $highlighted = $text;
    foreach ($terms as $term) {
        $term = trim($term);
        if (!empty($term)) {
            $pattern = '/(' . preg_quote($term, '/') . ')/i';
            $highlighted = preg_replace($pattern, '<mark>$1</mark>', $highlighted);
        }
    }
    
    return $highlighted;
} 