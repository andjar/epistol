<?php

// This function will be called by src/db.php when a new DB is created,
// or when this script is run directly.
function inject_initial_data(PDO $pdo): void
{
    echo "Starting test data injection via function...\n";

    // Truncate tables to start fresh (optional, but good for a test script)
    // Be cautious with this in a real environment. Order is important due to foreign keys.
    $tables_to_clear = [
        "attachments", "email_recipients", "email_statuses", "emails", 
        "threads", "group_members", "groups", "users", 
        "email_addresses", "persons"
    ];
    foreach ($tables_to_clear as $table) {
        try {
            $pdo->exec("DELETE FROM $table");
            // Reset auto-increment counter for SQLite
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
            }
        } catch (PDOException $e) {
            echo "Warning: Could not clear table $table or reset sequence: " . $e->getMessage() . "\n";
        }
    }
    echo "Existing data cleared (if any).\n";

    // 1. Insert Persons, Email Addresses, and Users
    echo "Injecting persons, email addresses, and users...\n";
    $users_data = [
        ['username' => 'alice_k', 'email' => 'alice@example.com', 'password' => 'password123', 'name' => 'Alice K.', 'avatar_url' => 'https://i.pravatar.cc/150?u=alice@example.com'],
        ['username' => 'bob_the_builder', 'email' => 'bob@example.com', 'password' => 'secureBobPass!', 'name' => 'Bob The Builder', 'avatar_url' => 'https://i.pravatar.cc/150?u=bob@example.com'],
        ['username' => 'charlie_brown', 'email' => 'charlie@example.com', 'password' => 'goodgrief', 'name' => 'Charlie Brown', 'avatar_url' => 'https://i.pravatar.cc/150?u=charlie@example.com'],
        ['username' => 'diana_prince', 'email' => 'diana@example.com', 'password' => 'wonderWoman', 'name' => 'Diana Prince', 'avatar_url' => 'https://i.pravatar.cc/150?u=diana@example.com'],
        ['username' => 'edward_nigma', 'email' => 'edward@example.com', 'password' => 'riddler', 'name' => 'Edward Nigma', 'avatar_url' => 'https://i.pravatar.cc/150?u=edward@example.com'],
    ];
    $user_ids = [];
    $person_ids_by_username = []; // To store person_id for email_recipients

    $person_stmt = $pdo->prepare("INSERT INTO persons (name, avatar_url) VALUES (:name, :avatar_url)");
    $email_addr_stmt = $pdo->prepare("INSERT INTO email_addresses (person_id, email_address, is_primary) VALUES (:person_id, :email_address, TRUE)");
    $user_stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, person_id) VALUES (:username, :email, :password_hash, :person_id)");

    foreach ($users_data as $user_data_item) {
        $pdo->beginTransaction();
        try {
            // Insert person
            $person_stmt->execute(['name' => $user_data_item['name'], 'avatar_url' => $user_data_item['avatar_url']]);
            $person_id = $pdo->lastInsertId();
            $person_ids_by_username[$user_data_item['username']] = $person_id;

            // Insert email address
            $email_addr_stmt->execute(['person_id' => $person_id, 'email_address' => $user_data_item['email']]);

            // Insert user
            $password_hash = password_hash($user_data_item['password'], PASSWORD_DEFAULT);
            $user_stmt->execute([
                'username' => $user_data_item['username'],
                'email' => $user_data_item['email'],
                'password_hash' => $password_hash,
                'person_id' => $person_id
            ]);
            $user_id = $pdo->lastInsertId();
            $user_ids[$user_data_item['username']] = $user_id;
            
            $pdo->commit();
            echo "  User '{$user_data_item['username']}' and associated person/email inserted with User ID: {$user_id}\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  Failed to insert user '{$user_data_item['username']}': " . $e->getMessage() . "\n";
        }
    }

    if (count($user_ids) < count($users_data)) {
        throw new Exception("Not all users were inserted successfully. Aborting further data injection.");
    }

    // 2. Insert Groups
    echo "Injecting groups...\n";
    $groups_data = [
        ['name' => 'Developers Corner', 'description' => 'A group for software developers.', 'created_by_username' => 'alice_k'],
        ['name' => 'Book Club', 'description' => 'Discussing interesting books.', 'created_by_username' => 'bob_the_builder'],
        ['name' => 'Photography Enthusiasts', 'description' => 'Sharing tips and photos.', 'created_by_username' => 'diana_prince'],
        ['name' => 'Gamers United', 'description' => 'For all things gaming.', 'created_by_username' => 'edward_nigma'],
        ['name' => 'Project Phoenix (secret)', 'description' => 'Top secret project group.', 'created_by_username' => 'alice_k'],
        ['name' => 'Empty Group', 'description' => 'A group with no members yet.', 'created_by_username' => 'charlie_brown'],
    ];
    $group_ids = [];

    $group_insert_stmt = $pdo->prepare("INSERT INTO groups (name, description, created_by_user_id) VALUES (:name, :description, :created_by_user_id)");
    foreach ($groups_data as $group_item) {
        $created_by_user_id = $user_ids[$group_item['created_by_username']];
        if ($group_insert_stmt->execute(['name' => $group_item['name'], 'description' => $group_item['description'], 'created_by_user_id' => $created_by_user_id])) {
            $group_id = $pdo->lastInsertId();
            $group_ids[$group_item['name']] = $group_id;
            echo "  Group '{$group_item['name']}' inserted with ID: {$group_id}\n";
        } else {
            echo "  Failed to insert group '{$group_item['name']}'.\n";
        }
    }
    if (count($group_ids) < count($groups_data)) {
        // Note: This might be too strict if some groups are intentionally not created.
        // For this script, we assume all defined groups should be created.
        echo "Warning: Not all groups were inserted successfully. Continuing...\n";
    }

    // 3. Insert Group Members
    echo "Injecting group members...\n";
    $group_memberships_data = [
        ['username' => 'alice_k', 'group_name' => 'Developers Corner'],
        ['username' => 'bob_the_builder', 'group_name' => 'Developers Corner'],
        ['username' => 'charlie_brown', 'group_name' => 'Developers Corner'],
        ['username' => 'diana_prince', 'group_name' => 'Developers Corner'], // Diana joins Developers
        ['username' => 'edward_nigma', 'group_name' => 'Developers Corner'], // Edward joins Developers

        ['username' => 'bob_the_builder', 'group_name' => 'Book Club'],
        ['username' => 'charlie_brown', 'group_name' => 'Book Club'],
        ['username' => 'diana_prince', 'group_name' => 'Book Club'], // Diana joins Book Club

        ['username' => 'diana_prince', 'group_name' => 'Photography Enthusiasts'],
        ['username' => 'alice_k', 'group_name' => 'Photography Enthusiasts'],

        ['username' => 'edward_nigma', 'group_name' => 'Gamers United'],
        ['username' => 'bob_the_builder', 'group_name' => 'Gamers United'],
        ['username' => 'charlie_brown', 'group_name' => 'Gamers United'],

        ['username' => 'alice_k', 'group_name' => 'Project Phoenix (secret)'],
        ['username' => 'edward_nigma', 'group_name' => 'Project Phoenix (secret)'],
        // 'Empty Group' has no members by design
    ];

    $group_member_insert_stmt = $pdo->prepare("INSERT INTO group_members (user_id, group_id) VALUES (:user_id, :group_id)");
    foreach ($group_memberships_data as $membership) {
        if (!isset($user_ids[$membership['username']]) || !isset($group_ids[$membership['group_name']])) {
            echo "  Skipping membership for '{$membership['username']}' in '{$membership['group_name']}' due to missing user or group ID.\n";
            continue;
        }
        $user_id = $user_ids[$membership['username']];
        $group_id = $group_ids[$membership['group_name']];
        try {
            if ($group_member_insert_stmt->execute(['user_id' => $user_id, 'group_id' => $group_id])) {
                echo "  User '{$membership['username']}' added to group '{$membership['group_name']}'.\n";
            } else {
                echo "  Failed to add user '{$membership['username']}' to group '{$membership['group_name']}'.\n";
            }
        } catch (PDOException $e) {
             echo "  Error adding user '{$membership['username']}' to group '{$membership['group_name']}': " . $e->getMessage() . "\n";
        }
    }

    // 4. Insert Threads and Emails
    echo "Injecting threads and emails...\n";
    // Prepare statements for email parts
    $email_recipients_stmt = $pdo->prepare("INSERT INTO email_recipients (email_id, person_id, type) VALUES (:email_id, :person_id, :type)");
    $attachments_stmt = $pdo->prepare("INSERT INTO attachments (email_id, filename, mimetype, filesize_bytes, filepath_on_disk) VALUES (:email_id, :filename, :mimetype, :filesize_bytes, :filepath_on_disk)");

    // Modified email_stmt to include body_html and created_at
    $email_stmt_with_html = $pdo->prepare("INSERT INTO emails (thread_id, user_id, group_id, subject, body_text, body_html, message_id_header, parent_email_id, created_at) VALUES (:thread_id, :user_id, :group_id, :subject, :body_text, :body_html, :message_id_header, :parent_email_id, :created_at)");
    // Original email_stmt for text-only emails (can be merged, but kept separate for clarity of example)
    $email_stmt_text_only = $pdo->prepare("INSERT INTO emails (thread_id, user_id, group_id, subject, body_text, message_id_header, parent_email_id, created_at) VALUES (:thread_id, :user_id, :group_id, :subject, :body_text, :message_id_header, :parent_email_id, :created_at)");


    $initial_posts_data = [
        [
            'username' => 'alice_k', 'group_name' => null, 'subject' => 'New Feature Deployed',
            'body_text' => 'Just deployed a new feature! #proud',
            'recipients' => ['to' => ['bob_the_builder'], 'cc' => ['diana_prince']],
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'status' => 'read'
        ],
        [
            'username' => 'bob_the_builder', 'group_name' => null, 'subject' => 'Next Big Project',
            'body_text' => 'Thinking about my next big project. It involves AI and lots of coffee.',
            'body_html' => '<p>Thinking about my <strong>next big project</strong>. It involves AI and lots of coffee.</p><img src="coffee.jpg" alt="Coffee cup">',
            'recipients' => ['to' => ['alice_k']],
            'attachments' => [
                ['filename' => 'project_brief.pdf', 'mimetype' => 'application/pdf', 'filesize' => 102400, 'filepath' => '/attachments/project_brief_bob.pdf']
            ],
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'status' => 'unread'
        ],
        [
            'username' => 'alice_k', 'group_name' => 'Developers Corner', 'subject' => 'PHP 8.3 Features Discussion',
            'body_text' => 'Anyone familiar with the new PHP 8.3 features? Let\'s discuss the JIT improvements and new functions.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'status' => 'read'
        ],
        [
            'username' => 'bob_the_builder', 'group_name' => 'Developers Corner', 'subject' => 'Library Updates and Security',
            'body_text' => 'Just pushed some updates to our main library. Please review the security patches ASAP.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'status' => 'unread'
        ],
        [
            'username' => 'charlie_brown', 'group_name' => 'Book Club', 'subject' => 'Finished "The Hitchhiker\'s Guide to the Galaxy"',
            'body_text' => 'Just finished reading "The Hitchhiker\'s Guide to the Galaxy". What a ride! 42!',
            'body_html' => '<p>Just finished reading "The Hitchhiker\'s Guide to the Galaxy". What a ride! <strong>42!</strong></p>',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'status' => 'read'
        ],
        [
            'username' => 'bob_the_builder', 'group_name' => 'Book Club', 'subject' => 'Next up: "Dune" by Frank Herbert',
            'body_text' => 'Next up: "Dune". Any fans here? The spice must flow!',
            'created_at' => date('Y-m-d H:i:s', strtotime('-4 days')),
            'status' => 'important-info'
        ],
        [
            'username' => 'diana_prince', 'group_name' => 'Photography Enthusiasts', 'subject' => 'Golden Hour Shots',
            'body_text' => 'Captured some amazing shots during golden hour yesterday. Will share soon!',
            'attachments' => [
                ['filename' => 'golden_hour_preview.jpg', 'mimetype' => 'image/jpeg', 'filesize' => 204800, 'filepath' => '/attachments/gh_preview_diana.jpg']
            ],
            'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
            'status' => 'unread'
        ],
        [
            'username' => 'edward_nigma', 'group_name' => 'Gamers United', 'subject' => 'New Co-op Game Night?',
            'body_text' => 'Riddle me this: What game has puzzles, adventure, and requires teamwork? Let\'s find one for our next game night!',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
            'status' => 'follow-up'
        ],
        [
            'username' => 'alice_k', 'group_name' => 'Project Phoenix (secret)', 'subject' => 'Phase 1 Complete',
            'body_text' => 'Team, Phase 1 of Project Phoenix is officially complete. Documentation is on the shared drive. Great work everyone!',
            'recipients' => ['bcc' => ['edward_nigma']],
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'status' => 'important-info'
        ],
        // Add more historical data
        [
            'username' => 'charlie_brown', 'group_name' => null, 'subject' => 'Weekend Plans',
            'body_text' => 'Anyone interested in a weekend hiking trip? I found a great trail.',
            'recipients' => ['to' => ['alice_k', 'bob_the_builder', 'diana_prince']],
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 weeks')),
            'status' => 'read'
        ],
        [
            'username' => 'diana_prince', 'group_name' => 'Photography Enthusiasts', 'subject' => 'Camera Equipment Sale',
            'body_text' => 'Found some great deals on camera equipment. Check out this link!',
            'body_html' => '<p>Found some great deals on camera equipment. <a href="#">Check out this link!</a></p>',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'status' => 'read'
        ],
        [
            'username' => 'edward_nigma', 'group_name' => 'Developers Corner', 'subject' => 'Code Review Request',
            'body_text' => 'Can someone review my latest pull request? It\'s a critical bug fix.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'status' => 'unread'
        ],
    ];

    $thread_stmt = $pdo->prepare("INSERT INTO threads (subject, created_by_user_id, group_id) VALUES (:subject, :created_by_user_id, :group_id)");
    $email_status_stmt = $pdo->prepare("INSERT INTO email_statuses (email_id, user_id, status) VALUES (:email_id, :user_id, :status)");
    $group_members_stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = :group_id");
    $update_thread_activity_stmt = $pdo->prepare("UPDATE threads SET last_activity_at = :timestamp WHERE id = :thread_id");

    $thread_ids_map = []; // Stores [subject_unique_key] => thread_id
    $email_ids_map = [];  // Stores [subject_unique_key] => email_id (for first email in thread)

    // Helper function for inserting emails and their components
    function insert_email_with_details(PDO $pdo, array $email_data, int $thread_id, ?int $parent_email_id, 
                                       array $user_ids, array $group_ids, array $person_ids_by_username,
                                       PDOStatement $email_stmt_text_only, PDOStatement $email_stmt_with_html,
                                       PDOStatement $email_recipients_stmt, PDOStatement $attachments_stmt, 
                                       PDOStatement $email_status_stmt, PDOStatement $group_members_stmt) {
        $sender_user_id = $user_ids[$email_data['username']];
        $current_group_id = isset($email_data['group_name']) && isset($group_ids[$email_data['group_name']]) ? $group_ids[$email_data['group_name']] : null;
        $message_id_header = "<" . uniqid('', true) . "@epistol.local>";
        $created_at = isset($email_data['created_at']) ? $email_data['created_at'] : date('Y-m-d H:i:s');

        $email_params = [
            'thread_id' => $thread_id,
            'user_id' => $sender_user_id,
            'group_id' => $current_group_id,
            'subject' => $email_data['subject'],
            'body_text' => $email_data['body_text'],
            'message_id_header' => $message_id_header,
            'parent_email_id' => $parent_email_id,
            'created_at' => $created_at
        ];

        if (isset($email_data['body_html'])) {
            $email_params['body_html'] = $email_data['body_html'];
            $email_stmt_with_html->execute($email_params);
        } else {
            // Need to remove body_html if not present, for text_only statement
            unset($email_params['body_html']); 
            $email_stmt_text_only->execute($email_params);
        }
        $email_id = $pdo->lastInsertId();

        // Insert attachments if any
        if (!empty($email_data['attachments'])) {
            foreach ($email_data['attachments'] as $attachment) {
                $attachments_stmt->execute([
                    'email_id' => $email_id,
                    'filename' => $attachment['filename'],
                    'mimetype' => $attachment['mimetype'],
                    'filesize_bytes' => $attachment['filesize'],
                    'filepath_on_disk' => $attachment['filepath']
                ]);
            }
        }
        
        // Determine recipients and create email statuses and email_recipients entries
        $all_recipient_user_ids = [];
        if ($current_group_id) {
            $group_members_stmt->execute(['group_id' => $current_group_id]);
            $all_recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
            // Add to email_recipients for group members
            foreach($all_recipient_user_ids as $recipient_uid) {
                 if ($recipient_uid !== $sender_user_id) { // Don't add sender as explicit recipient if it's a group post
                    // Find person_id for this user_id
                    $recipient_username = array_search($recipient_uid, $user_ids);
                    if ($recipient_username && isset($person_ids_by_username[$recipient_username])) {
                         $email_recipients_stmt->execute([
                            'email_id' => $email_id,
                            'person_id' => $person_ids_by_username[$recipient_username],
                            'type' => 'to' // Implicitly 'to' for group posts for now
                        ]);
                    }
                 }
            }
        }
        
        if (!empty($email_data['recipients'])) {
            foreach (['to', 'cc', 'bcc'] as $type) {
                if (!empty($email_data['recipients'][$type])) {
                    foreach ($email_data['recipients'][$type] as $recipient_username) {
                        if (isset($user_ids[$recipient_username])) {
                            $recipient_user_id = $user_ids[$recipient_username];
                            $all_recipient_user_ids[] = $recipient_user_id; // Add to list for statuses
                            if (isset($person_ids_by_username[$recipient_username])) {
                                $email_recipients_stmt->execute([
                                    'email_id' => $email_id,
                                    'person_id' => $person_ids_by_username[$recipient_username],
                                    'type' => $type
                                ]);
                            }
                        }
                    }
                }
            }
        }
        $all_recipient_user_ids = array_unique($all_recipient_user_ids); // Remove duplicates
        $email_status = isset($email_data['status']) ? $email_data['status'] : 'unread';

        foreach ($all_recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id !== $sender_user_id) { // Sender doesn't get a status for their own sent mail here
                $email_status_stmt->execute([
                    'email_id' => $email_id,
                    'user_id' => $recipient_user_id,
                    'status' => $email_status
                ]);
            }
        }
        // Status for sender (e.g. in their "sent" items, implicitly read)
         $email_status_stmt->execute([
            'email_id' => $email_id,
            'user_id' => $sender_user_id,
            'status' => 'sent' // Or 'read' if preferred for sender's view
        ]);


        return $email_id;
    }


    // Insert initial threads and emails
    foreach ($initial_posts_data as $key => $email_data_item) {
        $sender_user_id = $user_ids[$email_data_item['username']];
        $current_group_id = isset($email_data_item['group_name']) && isset($group_ids[$email_data_item['group_name']]) ? $group_ids[$email_data_item['group_name']] : null;
        $thread_subject_key = $email_data_item['subject'] . "_" . ($current_group_id ?? 'personal') . "_" . $sender_user_id; // more unique key
        $created_at = isset($email_data_item['created_at']) ? $email_data_item['created_at'] : date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $thread_stmt->execute([
                'subject' => $email_data_item['subject'],
                'created_by_user_id' => $sender_user_id,
                'group_id' => $current_group_id
            ]);
            $thread_id = $pdo->lastInsertId();
            $thread_ids_map[$thread_subject_key] = $thread_id;

            $email_id = insert_email_with_details($pdo, $email_data_item, $thread_id, null, $user_ids, $group_ids, $person_ids_by_username, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt);
            $email_ids_map[$thread_subject_key] = $email_id;
            
            // Use the custom created_at timestamp for thread activity
            $update_thread_activity_stmt->execute(['timestamp' => $created_at, 'thread_id' => $thread_id]);
            $pdo->commit();
            echo "  Thread and email by '{$email_data_item['username']}'" . ($email_data_item['group_name'] ? " in '{$email_data_item['group_name']}'" : "") . " inserted (Email ID: $email_id).\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  Failed to insert thread/email by '{$email_data_item['username']}': " . $e->getMessage() . "\n";
        }
    }


    // --- Additional test data: replies and forwards ---
    echo "Injecting replies and forwards...\n";

    // Helper for replies
    function add_reply(PDO $pdo, string $original_thread_key, string $replier_username, string $body_text, ?string $body_html,
                       array $user_ids, array $group_ids, array $person_ids_by_username, array $thread_ids_map, array $email_ids_map,
                       PDOStatement $email_stmt_text_only, PDOStatement $email_stmt_with_html, PDOStatement $email_recipients_stmt, 
                       PDOStatement $attachments_stmt, PDOStatement $email_status_stmt, PDOStatement $group_members_stmt, 
                       PDOStatement $update_thread_activity_stmt, string $subject_prefix = "Re: ") {
        if (!isset($thread_ids_map[$original_thread_key]) || !isset($email_ids_map[$original_thread_key])) {
            echo "  Cannot reply: Original thread/email for key '$original_thread_key' not found.\n";
            return null;
        }
        $thread_id = $thread_ids_map[$original_thread_key];
        $parent_email_id = $email_ids_map[$original_thread_key];
        
        // Fetch original email's group and subject to build reply data
        $orig_email_stmt = $pdo->prepare("SELECT subject, group_id FROM emails WHERE id = :id");
        $orig_email_stmt->execute(['id' => $parent_email_id]);
        $orig_email_info = $orig_email_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig_email_info) {
             echo "  Cannot reply: Original email info for ID '$parent_email_id' not found.\n";
            return null;
        }

        $reply_data = [
            'username' => $replier_username,
            'subject' => $subject_prefix . $orig_email_info['subject'],
            'body_text' => $body_text,
            'body_html' => $body_html,
            // group_name needs to be derived if group_id is present
            'group_name' => $orig_email_info['group_id'] ? array_search($orig_email_info['group_id'], $group_ids) : null,
            // Recipients for replies are typically derived from thread/group context or original recipients
        ];
        if ($reply_data['group_name'] === false) $reply_data['group_name'] = null; // Ensure null if not found

        $pdo->beginTransaction();
        try {
            $reply_email_id = insert_email_with_details($pdo, $reply_data, $thread_id, $parent_email_id, $user_ids, $group_ids, $person_ids_by_username, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt);
            usleep(10000); // 10ms delay
            $update_thread_activity_stmt->execute(['timestamp' => date('Y-m-d H:i:s'), 'thread_id' => $thread_id]);
            $pdo->commit();
            echo "  Reply by '$replier_username' to '$original_thread_key' inserted (Email ID: $reply_email_id).\n";
            return $reply_email_id; // Return new email ID to allow chaining replies
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  Failed to insert reply by '$replier_username' to '$original_thread_key': " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    // Helper for forwards
    function add_forward(PDO $pdo, string $original_thread_key_to_forward, string $forwarder_username, 
                         ?string $target_group_name, array $target_recipients, // ['to' => [...], 'cc' => [...]]
                         string $forward_intro_text, array $user_ids, array $group_ids, array $person_ids_by_username, 
                         array $thread_ids_map, array $email_ids_map,
                         PDOStatement $thread_stmt, PDOStatement $email_stmt_text_only, PDOStatement $email_stmt_with_html, 
                         PDOStatement $email_recipients_stmt, PDOStatement $attachments_stmt, PDOStatement $email_status_stmt, 
                         PDOStatement $group_members_stmt, PDOStatement $update_thread_activity_stmt) {

        if (!isset($email_ids_map[$original_thread_key_to_forward])) {
            echo "  Cannot forward: Original email for key '$original_thread_key_to_forward' not found.\n";
            return null;
        }
        $original_email_id = $email_ids_map[$original_thread_key_to_forward];

        // Fetch original email's subject and body to build forward data
        $orig_email_stmt = $pdo->prepare("SELECT subject, body_text, body_html FROM emails WHERE id = :id");
        $orig_email_stmt->execute(['id' => $original_email_id]);
        $original_email_content = $orig_email_stmt->fetch(PDO::FETCH_ASSOC);
         if (!$original_email_content) {
             echo "  Cannot forward: Original email content for ID '$original_email_id' not found.\n";
            return null;
        }

        $forward_subject = 'Fwd: ' . $original_email_content['subject'];
        $forward_body_text = $forward_intro_text . "\n---\n" . $original_email_content['body_text'];
        $forward_body_html = $original_email_content['body_html'] ? ('<p>' . nl2br(htmlspecialchars($forward_intro_text)) . '</p><hr>' . $original_email_content['body_html']) : null;

        $forward_data = [
            'username' => $forwarder_username,
            'subject' => $forward_subject,
            'body_text' => $forward_body_text,
            'body_html' => $forward_body_html,
            'group_name' => $target_group_name,
            'recipients' => $target_recipients
        ];

        $pdo->beginTransaction();
        try {
            $forwarder_user_id = $user_ids[$forwarder_username];
            $fwd_group_id = $target_group_name ? $group_ids[$target_group_name] : null;

            // New thread for the forward
            $thread_stmt->execute([
                'subject' => $forward_subject,
                'created_by_user_id' => $forwarder_user_id,
                'group_id' => $fwd_group_id
            ]);
            $new_thread_id = $pdo->lastInsertId();
            // Store this new thread in thread_ids_map if needed for subsequent replies to the forward
            $fwd_thread_key = $forward_subject . "_" . ($fwd_group_id ?? 'personal') . "_" . $forwarder_user_id . "_fwd_" . uniqid();
            $thread_ids_map[$fwd_thread_key] = $new_thread_id;


            $forward_email_id = insert_email_with_details($pdo, $forward_data, $new_thread_id, $original_email_id, $user_ids, $group_ids, $person_ids_by_username, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt);
            $email_ids_map[$fwd_thread_key] = $forward_email_id; // Store email_id of the forward itself

            usleep(10000); // 10ms delay
            $update_thread_activity_stmt->execute(['timestamp' => date('Y-m-d H:i:s'), 'thread_id' => $new_thread_id]);
            $pdo->commit();
            echo "  Forward by '$forwarder_username' of '$original_thread_key_to_forward' created (New Thread ID: $new_thread_id, Email ID: $forward_email_id).\n";
            return $fwd_thread_key; // Return the key for the new forwarded thread/email
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  Failed to insert forward by '$forwarder_username': " . $e->getMessage() . "\n";
            return null;
        }
    }

    // Key generation for initial posts (must match the one in the loop above)
    $php_features_key = 'PHP 8.3 Features Discussion_'. $group_ids['Developers Corner'] .'_' . $user_ids['alice_k'];
    $new_feature_key = 'New Feature Deployed_personal_' . $user_ids['alice_k'];
    $hitchhiker_key = 'Finished "The Hitchhiker\'s Guide to the Galaxy"_' . $group_ids['Book Club'] . '_' . $user_ids['charlie_brown'];
    $library_updates_key = 'Library Updates and Security_' . $group_ids['Developers Corner'] . '_' . $user_ids['bob_the_builder'];
    $dune_key = 'Next up: "Dune" by Frank Herbert_' . $group_ids['Book Club'] . '_' . $user_ids['bob_the_builder'];


    // 1. Reply to 'PHP 8.3 Features Discussion' by Bob
    $php_reply1_bob_id = add_reply($pdo, $php_features_key, 'bob_the_builder', 
        'I have checked out a few features, looks promising! Especially the new readonly properties.', 
        '<p>I have checked out a few features, looks promising! Especially the new <code>readonly</code> properties.</p>',
        $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $email_ids_map, 
        $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);
    // Update email_ids_map with the ID of Bob's reply so Charlie can reply to it
    if($php_reply1_bob_id) $email_ids_map[$php_features_key . "_reply_bob"] = $php_reply1_bob_id;


    // 2. Reply to 'New Feature Deployed' by Bob to Alice
    add_reply($pdo, $new_feature_key, 'bob_the_builder', 
        'Congrats Alice! Looking forward to trying it out. Is there any documentation available?', null,
        $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $email_ids_map, 
        $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);

    // 3. Forward 'Hitchhiker's Guide' to Diana (from Bob)
    $fwd_hitchhiker_to_diana_key = add_forward($pdo, $hitchhiker_key, 'bob_the_builder', null, ['to' => ['diana_prince']],
        'Diana, check out this book review from Charlie in the Book Club!',
        $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $email_ids_map,
        $thread_stmt, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);
    
    // 4. Forward 'Library Updates' to Book Club (from Charlie)
    add_forward($pdo, $library_updates_key, 'charlie_brown', 'Book Club', [], // Group implies recipients
        'Book Club, sharing this important update from Bob in Developers Corner regarding our shared libraries.',
        $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $email_ids_map,
        $thread_stmt, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);


    // --- Even more emails for richer test data ---
    echo "Injecting more complex interactions...\n";

    // 1. Charlie replies to Bob's reply in the "PHP 8.3 Features" thread
    // We need the key for Bob's reply. If it was successful, it's $php_features_key . "_reply_bob"
    if (isset($email_ids_map[$php_features_key . "_reply_bob"])) {
         $php_reply2_charlie_id = add_reply($pdo, $php_features_key, 'charlie_brown', // Charlie replies to the main thread, parented to Bob's reply
            'I am excited to try the new match expression! It looks much cleaner than switch for some cases.', 
            '<p>I am excited to try the new <code>match</code> expression! It looks much cleaner than <code>switch</code> for some cases.</p>',
            $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, [$php_features_key => $email_ids_map[$php_features_key . "_reply_bob"]], // Pass Bob's reply as parent
            $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);
        if($php_reply2_charlie_id) $email_ids_map[$php_features_key . "_reply_charlie"] = $php_reply2_charlie_id;
    }


    // 2. Alice replies again in the same thread (to Charlie's reply)
    if (isset($email_ids_map[$php_features_key . "_reply_charlie"])) {
        add_reply($pdo, $php_features_key, 'alice_k', 
            'Let\'s schedule a meeting to discuss how we can leverage these in Project Phoenix! I\'ll send out an invite.', null,
            $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, [$php_features_key => $email_ids_map[$php_features_key . "_reply_charlie"]], // Pass Charlie's reply as parent
            $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);
    }
    
    // 3. Alice forwards 'Next up: "Dune"' (Book Club) to Developers Corner (already exists, let's make a variation or skip)
    // Let's have Edward forward it to Gamers United instead
    add_forward($pdo, $dune_key, 'edward_nigma', 'Gamers United', [],
        'Gamers, any Dune fans here? The new movie was great, made me want to read the books. This came from the Book Club.',
        $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $email_ids_map,
        $thread_stmt, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);

    // 4. Bob sends a personal email to Alice, Charlie, and CCs Diana
    $lunch_plans_subject = 'Lunch Plans This Week';
    $lunch_thread_key = $lunch_plans_subject . "_personal_" . $user_ids['bob_the_builder'] . "_" . uniqid();
    $pdo->beginTransaction();
    try {
        $thread_stmt->execute([
            'subject' => $lunch_plans_subject,
            'created_by_user_id' => $user_ids['bob_the_builder'],
            'group_id' => null
        ]);
        $lunch_thread_id = $pdo->lastInsertId();
        $thread_ids_map[$lunch_thread_key] = $lunch_thread_id;

        $lunch_email_id = insert_email_with_details($pdo, [
            'username' => 'bob_the_builder', 'subject' => $lunch_plans_subject, 
            'body_text' => 'Anyone up for lunch Thursday or Friday? My treat!',
            'body_html' => '<p>Anyone up for lunch <b>Thursday</b> or <b>Friday</b>? My treat! &#x1F354;</p>',
            'recipients' => ['to' => ['alice_k', 'charlie_brown'], 'cc' => ['diana_prince'], 'bcc' => ['edward_nigma']] // Edward gets a BCC
        ], $lunch_thread_id, null, $user_ids, $group_ids, $person_ids_by_username, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt);
        $email_ids_map[$lunch_thread_key] = $lunch_email_id;
        
        $update_thread_activity_stmt->execute(['timestamp' => date('Y-m-d H:i:s'), 'thread_id' => $lunch_thread_id]);
        $pdo->commit();
        echo "  Bob sent a personal email for lunch plans (Email ID: $lunch_email_id).\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to send Bob's lunch plan email: " . $e->getMessage() . "\n";
    }

    // 5. Alice replies to Bob's forward about Hitchhiker's Guide (if the forward was successful)
    if ($fwd_hitchhiker_to_diana_key && isset($thread_ids_map[$fwd_hitchhiker_to_diana_key]) && isset($email_ids_map[$fwd_hitchhiker_to_diana_key])) {
        // Diana replies to Bob's forward (which was sent to Diana)
        add_reply($pdo, $fwd_hitchhiker_to_diana_key, 'diana_prince',
            'Thanks for sharing, Bob! I loved that book too. So witty!',
            '<p>Thanks for sharing, Bob! I loved that book too. So witty!</p>',
            $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $email_ids_map,
            $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt);
    }


    // 6. Create a very long thread in Developers Corner
    echo "Injecting a long thread in Developers Corner...\n";
    $long_thread_subject = "Brainstorm: Next Gen UI Framework";
    $long_thread_key = $long_thread_subject . "_" . $group_ids['Developers Corner'] . "_" . $user_ids['diana_prince'] . "_" . uniqid();
    $pdo->beginTransaction();
    try {
        $thread_stmt->execute([
            'subject' => $long_thread_subject,
            'created_by_user_id' => $user_ids['diana_prince'],
            'group_id' => $group_ids['Developers Corner']
        ]);
        $current_thread_id = $pdo->lastInsertId();
        $thread_ids_map[$long_thread_key] = $current_thread_id;

        $current_email_id = insert_email_with_details($pdo, [
            'username' => 'diana_prince', 'subject' => $long_thread_subject, 
            'group_name' => 'Developers Corner',
            'body_text' => 'Team, let\'s brainstorm ideas for our next-gen UI framework. What are the must-have features? What pain points should we address from the current one?',
            'body_html' => '<p>Team, let\'s brainstorm ideas for our <strong>next-gen UI framework</strong>. What are the must-have features? What pain points should we address from the current one?</p>',
        ], $current_thread_id, null, $user_ids, $group_ids, $person_ids_by_username, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt);
        $email_ids_map[$long_thread_key] = $current_email_id; // Store first email ID

        usleep(10000); // 10ms delay
        $update_thread_activity_stmt->execute(['timestamp' => date('Y-m-d H:i:s'),'thread_id' => $current_thread_id]);
        $pdo->commit();
        echo "  Long thread started by Diana (Email ID: $current_email_id).\n";

        $participants = ['alice_k', 'bob_the_builder', 'edward_nigma', 'charlie_brown'];
        $replies = [
            "Performance should be top priority. Maybe something Rust-based compiled to WASM?",
            "I agree on performance. Also, developer experience is key. Hot module reloading and great tooling.",
            "What about component styling? CSS-in-JS, utility classes, or something new?",
            "Accessibility (a11y) must be built-in from the ground up, not an afterthought.",
            "Let's consider a plugin architecture for extensibility.",
            "State management is always a challenge. Something simpler than Redux, perhaps like Signals?",
            "Server-side rendering (SSR) or static site generation (SSG) capabilities?",
            "Riddle me this: What has components, state, and makes developers' lives easier... or harder if done wrong?",
            "We need solid documentation and lots of examples.",
            "How about integration with existing backend APIs? Should be seamless."
        ];

        $last_email_id_in_long_thread = $current_email_id;
        // Create a chain of replies
        for ($i = 0; $i < 10; $i++) {
            $replier = $participants[$i % count($participants)];
            $reply_body = $replies[$i % count($replies)];
            if ($i == 7) $reply_body .= " (It's a UI Framework, of course!)"; // Edward's riddle answer

            // Use a temporary map for parent_email_id for this specific reply chain
            $temp_email_id_map_for_reply = [$long_thread_key => $last_email_id_in_long_thread];

            $new_reply_id = add_reply($pdo, $long_thread_key, $replier, $reply_body, "<p>" . htmlspecialchars($reply_body) . "</p>",
                $user_ids, $group_ids, $person_ids_by_username, $thread_ids_map, $temp_email_id_map_for_reply,
                $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt, $update_thread_activity_stmt, "Re: ");
            
            if ($new_reply_id) {
                $last_email_id_in_long_thread = $new_reply_id;
            } else {
                echo "  Stopping long thread generation due to error in reply.\n";
                break;
            }
             // Small delay to ensure timestamps are slightly different
            usleep(10000); // 10ms
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to create long thread: " . $e->getMessage() . "\n";
    }


    // 7. Charlie sends an email to himself with an attachment
    $self_note_subject = "My Brilliant Idea";
    $self_note_key = $self_note_subject . "_personal_" . $user_ids['charlie_brown'] . "_" . uniqid();
    $pdo->beginTransaction();
    try {
        $thread_stmt->execute([
            'subject' => $self_note_subject,
            'created_by_user_id' => $user_ids['charlie_brown'],
            'group_id' => null
        ]);
        $self_note_thread_id = $pdo->lastInsertId();
        $thread_ids_map[$self_note_key] = $self_note_thread_id;

        $self_note_email_id = insert_email_with_details($pdo, [
            'username' => 'charlie_brown', 'subject' => $self_note_subject,
            'body_text' => 'Remember this amazing idea for a peanut butter sandwich delivery service!',
            'attachments' => [
                ['filename' => 'business_plan_v0.1.txt', 'mimetype' => 'text/plain', 'filesize' => 1024, 'filepath' => '/attachments/pb_delivery_charlie.txt']
            ],
            'recipients' => ['to' => ['charlie_brown']] // Explicitly to self
        ], $self_note_thread_id, null, $user_ids, $group_ids, $person_ids_by_username, $email_stmt_text_only, $email_stmt_with_html, $email_recipients_stmt, $attachments_stmt, $email_status_stmt, $group_members_stmt);
        $email_ids_map[$self_note_key] = $self_note_email_id;
        
        $update_thread_activity_stmt->execute(['timestamp' => date('Y-m-d H:i:s'), 'thread_id' => $self_note_thread_id]);
        $pdo->commit();
        echo "  Charlie sent a note to himself with attachment (Email ID: $self_note_email_id).\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to send Charlie's self-note with attachment: " . $e->getMessage() . "\n";
    }


    echo "Complex test data injection completed.\n";
    echo "Test data injection via function completed successfully!\n";
}

// Standalone execution block:
// Only run this if the script is executed directly from the command line.
// The condition `basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])` ensures it only runs
// when this file is the main script, not when it's included.
if ((php_sapi_name() === 'cli' || php_sapi_name() === 'cgi-fcgi') && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Go to the project root directory
    // This chdir is problematic if the script is not in a subdirectory of the project root.
    // Assuming it's in `db/` relative to project root.
    $projectRoot = __DIR__ . '/..';
    chdir($projectRoot);

    // These are needed for standalone execution to establish DB connection and load config.
    if (file_exists('config/config.php')) {
        require_once 'config/config.php';
    } else {
        die("Error: config/config.php not found. Make sure you are in the project root or the path is correct.\n");
    }
    if (file_exists('src/db.php')) {
        require_once 'src/db.php';
    } else {
        die("Error: src/db.php not found. Make sure you are in the project root or the path is correct.\n");
    }
    
    echo "Running inject_test_data.php directly from CLI...\n";
    try {
        // Establish a new PDO connection for standalone execution
        $pdo = get_db_connection(); 
        echo "Database connection successful for direct execution.\n";
        
        // Call the main data injection function
        inject_initial_data($pdo);

    } catch (PDOException $e) {
        error_log("Database error during direct execution: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
        echo "Database error during direct execution: " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        error_log("Error during direct execution: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
        echo "Error during direct execution: " . $e->getMessage() . "\n";
    }
}

?>
