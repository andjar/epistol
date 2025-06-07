<?php

// 1. Include necessary files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config/config.php'; // For potential global settings, though not directly used yet.

// Set default content type early. helpers.php functions will also set it.
header('Content-Type: application/json');

try {
    // 2. Input Parameters
    if (!isset($_GET['person_id']) || $_GET['person_id'] === '') { // Check for empty string too
        send_json_error('Valid person_id is required.', 400);
    }
    $person_id = $_GET['person_id'];
    // Add further validation if person_id has a specific format (e.g., UUID, integer)
    // For now, any non-empty string is accepted.

    // 3. Database Interaction
    $pdo = null;
    try {
        $pdo = get_db_connection();
    } catch (PDOException $e) {
        error_log("Database connection failed in get_profile.php: " . $e->getMessage());
        send_json_error('Database connection error. Please try again later.', 500);
    } catch (Exception $e) { // Catch other exceptions from get_db_connection (e.g., DB_PATH not defined)
        error_log("Configuration error in get_profile.php: " . $e->getMessage());
        send_json_error($e->getMessage(), 500);
    }

    // --- Person Details ---
    // 4. Placeholder for DB Query (Person Details)
    // This query should fetch the main profile information from a 'persons' table
    // and associated email addresses from an 'email_addresses' table.
    /*
    Conceptual SQL for person's core details:
    SELECT
        p.id AS person_id,
        p.name,
        p.bio,
        p.profile_picture_url, -- Store as 'avatar_url' in output
        p.created_at, -- Could be join date or profile creation date
        p.notes -- If 'notes' are stored directly with the person and are public/semi-public.
                 -- If 'notes' are user-specific private notes ABOUT this person,
                 -- they would need to be fetched from a different table based on the logged-in user.
                 -- For this example, let's assume 'notes' are part of the person's own profile data.
    FROM
        persons p
    WHERE
        p.id = :person_id;

    Conceptual SQL for person's email addresses:
    SELECT
        ea.email_address,
        ea.is_primary
    FROM
        email_addresses ea
    WHERE
        ea.person_id = :person_id
    ORDER BY
        ea.is_primary DESC, ea.email_address ASC;
    */

    // Placeholder: Simulate fetching person data.
    // In a real implementation, you would execute the queries here.
    // $stmt_person = $pdo->prepare("SELECT id, name, bio, profile_picture_url, notes FROM persons WHERE id = :person_id");
    // $stmt_person->execute(['person_id' => $person_id]);
    // $person_row = $stmt_person->fetch(PDO::FETCH_ASSOC);
    //
    // if (!$person_row) {
    //     send_json_error('Profile not found.', 404);
    // }
    //
    // $stmt_emails = $pdo->prepare("SELECT email_address, is_primary FROM email_addresses WHERE person_id = :person_id ORDER BY is_primary DESC");
    // $stmt_emails->execute(['person_id' => $person_id]);
    // $email_rows = $stmt_emails->fetchAll(PDO::FETCH_ASSOC);

    // --- Associated Threads ---
    // 5. Placeholder for DB Query (Associated Threads)
    // Fetch threads this person is a participant in.
    // This query would be complex, similar to get_feed.php, involving joins with
    // 'thread_participants', 'threads', 'emails', 'persons' (for sender/other participants).
    // Pagination or limiting (e.g., N most recent) should be considered.
    /*
    Conceptual SQL for associated threads (simplified):
    SELECT
        t.id AS thread_id,
        t.subject,
        (SELECT MAX(e_latest.timestamp) FROM emails e_latest WHERE e_latest.thread_id = t.id) AS last_reply_timestamp,
        -- Further subqueries or joins needed for:
        -- participants_summary (e.g., "You, Person A, Person B")
        -- latest_email_snippet
        -- other fields as in get_feed.php
    FROM
        threads t
    JOIN
        thread_participants tp ON t.id = tp.thread_id
    WHERE
        tp.person_id = :person_id
    ORDER BY
        last_reply_timestamp DESC
    LIMIT 10; // Example: Limit to 10 most recent threads
    */

    // For now, using placeholder data and a simulated "not found" condition:
    if ($person_id === "nonexistent_user_id_example") { // Use this to test the 404 path
        send_json_error('Profile not found.', 404);
    }

    // 6. Data Processing (Placeholder)
    // $email_addresses_list = [];
    // foreach ($email_rows as $email) {
    //     $email_addresses_list[] = [
    //         'email' => $email['email_address'],
    //         'is_primary' => (bool)$email['is_primary']
    //     ];
    // }
    //
    // $processed_associated_threads = []; // Process results from the associated threads query

    // Placeholder data for a successful response:
    $profile_data = [
        "id" => $person_id, // Use the input person_id
        "name" => "Alex Doe (Placeholder)", // Fetched from $person_row['name']
        "avatar_url" => "/images/avatars/" . $person_id . ".png", // Fetched from $person_row['profile_picture_url']
        "bio" => "Loves coding and hiking. (Placeholder bio)", // Fetched from $person_row['bio']
        "email_addresses" => [ // Populated from $email_rows
            ["email" => $person_id . "@example.com", "is_primary" => true],
            ["email" => "contact@" . $person_id . ".dev", "is_primary" => false]
        ],
        // "notes" field: User's private notes about this person. This would typically come from
        // a separate table linking the viewing user to this profile with their notes.
        // For this example, if 'notes' were a public field on the person's profile itself:
        // "notes" => $person_row['notes'],
        "notes" => "Placeholder note: Met at dev conference.", // Placeholder
        "threads" => [ // Populated from $processed_associated_threads
            [
                "id" => "thread_xyz_789",
                "subject" => "Follow-up from conference",
                "last_reply_at" => "2024-07-28 15:00:00",
                "participants_names" => "Alex Doe, Organizer Name", // Example
                "latest_email_snippet" => "Great to connect with you, Alex!" // Example
                // ... other thread details similar to get_feed ...
            ]
        ]
    ];

    // 7. Success Response
    send_json_success($profile_data);

} catch (PDOException $e) {
    error_log("PDOException in get_profile.php: " . $e->getMessage());
    send_json_error('A database error occurred while fetching the profile.', 500);
} catch (Exception $e) {
    error_log("General Exception in get_profile.php: " . $e->getMessage());
    send_json_error('An unexpected error occurred. Please try again.', 500);
}

/*
// 8. Example of expected JSON output structure

// SUCCESS:
// {
//   "status": "success",
//   "data": {
//     "id": "person_uuid_123",
//     "name": "John Doe",
//     "avatar_url": "/images/avatars/person_123.png",
//     "bio": "Software developer and coffee enthusiast.",
//     "email_addresses": [
//       { "email": "john.doe@example.com", "is_primary": true },
//       { "email": "jd@work.com", "is_primary": false }
//     ],
//     "notes": "Met at the conference.", // User's private notes about this person
//     "threads": [
//       {
//         "id": "thread_uuid_1",
//         "subject": "Project Discussion",
//         "last_reply_at": "2024-07-30 10:00:00",
//         // "participant_avatars": ["/images/avatar1.png", "/images/avatar2.png"], // Example
//         "participants_names": "Alice, Bob, John Doe", // Example
//         // "latest_email": { ... }, // Example, similar to get_feed
//         "latest_email_snippet": "Latest update on the project from John's perspective...", // Simplified for profile context
//         "unread_count": 0 // Example, may not be relevant in profile context or means unread for viewing user in this thread
//       }
//       // ... more threads ...
//     ]
//   }
// }

// ERROR (Not Found):
// {
//   "status": "error",
//   "message": "Profile not found."
// }

// ERROR (Bad Request):
// {
//   "status": "error",
//   "message": "Valid person_id is required."
// }
*/

?>
