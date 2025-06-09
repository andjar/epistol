<?php

// This function will be called by src/db.php when a new DB is created,
// or when this script is run directly.
function inject_initial_data(PDO $pdo): void
{
    echo "Starting test data injection via function...\n";

    // Truncate tables to start fresh (optional, but good for a test script)
    // Be cautious with this in a real environment. Order is important due to foreign keys.
    $pdo->exec("DELETE FROM attachments");
    $pdo->exec("DELETE FROM email_recipients");
    $pdo->exec("DELETE FROM email_statuses");
    $pdo->exec("DELETE FROM emails");
    $pdo->exec("DELETE FROM threads");
    $pdo->exec("DELETE FROM group_members");
    $pdo->exec("DELETE FROM groups");
    $pdo->exec("DELETE FROM users");
    $pdo->exec("DELETE FROM email_addresses");
    $pdo->exec("DELETE FROM persons");
    echo "Existing data cleared (if any).\n";

    // 1. Insert Persons, Email Addresses, and Users
    echo "Injecting persons, email addresses, and users...\n";
    $users = [
        ['username' => 'alice_k', 'email' => 'alice@example.com', 'password' => 'password123', 'name' => 'Alice K.'],
        ['username' => 'bob_the_builder', 'email' => 'bob@example.com', 'password' => 'secureBobPass!', 'name' => 'Bob The Builder'],
        ['username' => 'charlie_brown', 'email' => 'charlie@example.com', 'password' => 'goodgrief', 'name' => 'Charlie Brown'],
    ];
    $user_ids = [];

    $person_stmt = $pdo->prepare("INSERT INTO persons (name) VALUES (:name)");
    $email_addr_stmt = $pdo->prepare("INSERT INTO email_addresses (person_id, email_address, is_primary) VALUES (:person_id, :email_address, TRUE)");
    $user_stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, person_id) VALUES (:username, :email, :password_hash, :person_id)");

    foreach ($users as $user) {
        $pdo->beginTransaction();
        try {
            // Insert person
            $person_stmt->execute(['name' => $user['name']]);
            $person_id = $pdo->lastInsertId();

            // Insert email address
            $email_addr_stmt->execute(['person_id' => $person_id, 'email_address' => $user['email']]);

            // Insert user
            $password_hash = password_hash($user['password'], PASSWORD_DEFAULT);
            $user_stmt->execute([
                'username' => $user['username'],
                'email' => $user['email'],
                'password_hash' => $password_hash,
                'person_id' => $person_id
            ]);
            $user_id = $pdo->lastInsertId();
            $user_ids[$user['username']] = $user_id;
            
            $pdo->commit();
            echo "  User '{$user['username']}' and associated person/email inserted with User ID: {$user_id}\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  Failed to insert user '{$user['username']}': " . $e->getMessage() . "\n";
        }
    }

    if (count($user_ids) < count($users)) {
        throw new Exception("Not all users were inserted successfully. Aborting further data injection.");
    }

    // 2. Insert Groups
    echo "Injecting groups...\n";
    $groups = [
        ['name' => 'Developers Corner', 'description' => 'A group for software developers.', 'created_by_username' => 'alice_k'],
        ['name' => 'Book Club', 'description' => 'Discussing interesting books.', 'created_by_username' => 'bob_the_builder'],
    ];
    $group_ids = [];

    $stmt = $pdo->prepare("INSERT INTO groups (name, description, created_by_user_id) VALUES (:name, :description, :created_by_user_id)");
    foreach ($groups as $group) {
        $created_by_user_id = $user_ids[$group['created_by_username']];
        if ($stmt->execute(['name' => $group['name'], 'description' => $group['description'], 'created_by_user_id' => $created_by_user_id])) {
            $group_id = $pdo->lastInsertId();
            $group_ids[$group['name']] = $group_id;
            echo "  Group '{$group['name']}' inserted with ID: {$group_id}\n";
        } else {
            echo "  Failed to insert group '{$group['name']}'.\n";
        }
    }
    if (count($group_ids) < count($groups)) {
        throw new Exception("Not all groups were inserted successfully. Aborting further data injection.");
    }

    // 3. Insert Group Members
    echo "Injecting group members...\n";
    $group_memberships = [
        ['username' => 'alice_k', 'group_name' => 'Developers Corner'],
        ['username' => 'bob_the_builder', 'group_name' => 'Developers Corner'],
        ['username' => 'charlie_brown', 'group_name' => 'Developers Corner'],
        ['username' => 'bob_the_builder', 'group_name' => 'Book Club'],
        ['username' => 'charlie_brown', 'group_name' => 'Book Club'],
    ];

    $stmt = $pdo->prepare("INSERT INTO group_members (user_id, group_id) VALUES (:user_id, :group_id)");
    foreach ($group_memberships as $membership) {
        $user_id = $user_ids[$membership['username']];
        $group_id = $group_ids[$membership['group_name']];
        if ($stmt->execute(['user_id' => $user_id, 'group_id' => $group_id])) {
            echo "  User '{$membership['username']}' added to group '{$membership['group_name']}'.\n";
        } else {
            echo "  Failed to add user '{$membership['username']}' to group '{$membership['group_name']}'.\n";
        }
    }

    // 4. Insert Threads and Emails
    echo "Injecting threads and emails...\n";
    $posts_as_emails = [
        [
            'username' => 'alice_k', 'group_name' => null, 'subject' => 'New Feature Deployed',
            'body' => 'Just deployed a new feature! #proud',
            'recipients' => ['bob_the_builder']
        ],
        [
            'username' => 'bob_the_builder', 'group_name' => null, 'subject' => 'Next Big Project',
            'body' => 'Thinking about my next big project.',
            'recipients' => ['alice_k']
        ],
        [
            'username' => 'alice_k', 'group_name' => 'Developers Corner', 'subject' => 'PHP 8.3 Features',
            'body' => 'Anyone familiar with the new PHP 8.3 features?'
        ],
        [
            'username' => 'bob_the_builder', 'group_name' => 'Developers Corner', 'subject' => 'Library Updates',
            'body' => 'Just pushed some updates to our main library.'
        ],
        [
            'username' => 'charlie_brown', 'group_name' => 'Book Club', 'subject' => 'Finished "The Hitchhiker\'s Guide to the Galaxy"',
            'body' => 'Just finished reading "The Hitchhiker\'s Guide to the Galaxy". What a ride!'
        ],
        [
            'username' => 'bob_the_builder', 'group_name' => 'Book Club', 'subject' => 'Next up: "Dune"',
            'body' => 'Next up: "Dune". Any fans here?'
        ],
    ];

    $thread_stmt = $pdo->prepare("INSERT INTO threads (subject, created_by_user_id, group_id) VALUES (:subject, :created_by_user_id, :group_id)");
    $email_stmt = $pdo->prepare("INSERT INTO emails (thread_id, user_id, group_id, subject, body_text, message_id_header) VALUES (:thread_id, :user_id, :group_id, :subject, :body_text, :message_id_header)");
    $email_status_stmt = $pdo->prepare("INSERT INTO email_statuses (email_id, user_id, status) VALUES (:email_id, :user_id, :status)");
    $group_members_stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = :group_id");
    $update_thread_activity_stmt = $pdo->prepare("UPDATE threads SET last_activity_at = :timestamp WHERE id = :thread_id");

    $thread_ids = [];
    $email_ids = [];
    // Insert initial threads and emails, but track their IDs for replies/forwards
    foreach ($posts_as_emails as $email_data) {
        $user_id = $user_ids[$email_data['username']];
        $group_id = isset($email_data['group_name']) ? $group_ids[$email_data['group_name']] : null;

        $pdo->beginTransaction();
        try {
            // Insert thread
            $thread_stmt->execute([
                'subject' => $email_data['subject'],
                'created_by_user_id' => $user_id,
                'group_id' => $group_id
            ]);
            $thread_id = $pdo->lastInsertId();
            $thread_ids[$email_data['subject']] = $thread_id;

            // Insert email
            $message_id = "<" . uniqid('', true) . "@epistol.local>";
            $email_stmt->execute([
                'thread_id' => $thread_id,
                'user_id' => $user_id,
                'group_id' => $group_id,
                'subject' => $email_data['subject'],
                'body_text' => $email_data['body'],
                'message_id_header' => $message_id
            ]);
            $email_id = $pdo->lastInsertId();
            $email_ids[$email_data['subject']] = $email_id;

            // Determine recipients and create email statuses
            $recipient_user_ids = [];
            if ($group_id) {
                $group_members_stmt->execute(['group_id' => $group_id]);
                $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
            } else if (!empty($email_data['recipients'])) {
                foreach ($email_data['recipients'] as $recipient_username) {
                    if (isset($user_ids[$recipient_username])) {
                        $recipient_user_ids[] = $user_ids[$recipient_username];
                    }
                }
            }
            foreach ($recipient_user_ids as $recipient_user_id) {
                if ($recipient_user_id !== $user_id) {
                    $email_status_stmt->execute([
                        'email_id' => $email_id,
                        'user_id' => $recipient_user_id,
                        'status' => 'unread'
                    ]);
                }
            }
            $current_timestamp = date('Y-m-d H:i:s');
            $update_thread_activity_stmt->execute([
                'timestamp' => $current_timestamp,
                'thread_id' => $thread_id
            ]);
            $pdo->commit();
            echo "  Thread and email by '{$email_data['username']}'" . ($email_data['group_name'] ? " in '{$email_data['group_name']}'" : "") . " inserted.\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  Failed to insert thread/email by '{$email_data['username']}': " . $e->getMessage() . "\n";
        }
    }

    // --- Additional test data: replies and forwards ---
    echo "Injecting replies and forwards...\n";
    // 1. Reply to 'PHP 8.3 Features' by Bob in Developers Corner
    $pdo->beginTransaction();
    try {
        $thread_id = $thread_ids['PHP 8.3 Features'];
        $parent_email_id = $email_ids['PHP 8.3 Features'];
        $user_id = $user_ids['bob_the_builder'];
        $group_id = $group_ids['Developers Corner'];
        $subject = 'Re: PHP 8.3 Features';
        $body = 'I have checked out a few features, looks promising!';
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2 = $pdo->prepare("INSERT INTO emails (thread_id, parent_email_id, user_id, group_id, subject, body_text, message_id_header) VALUES (:thread_id, :parent_email_id, :user_id, :group_id, :subject, :body_text, :message_id_header)");
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $reply_email_id = $pdo->lastInsertId();
        // Recipients: all group members except sender
        $group_members_stmt->execute(['group_id' => $group_id]);
        $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $reply_email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Reply to 'PHP 8.3 Features' inserted.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to insert reply to 'PHP 8.3 Features': " . $e->getMessage() . "\n";
    }

    // 2. Reply to 'New Feature Deployed' by Bob to Alice
    $pdo->beginTransaction();
    try {
        $thread_id = $thread_ids['New Feature Deployed'];
        $parent_email_id = $email_ids['New Feature Deployed'];
        $user_id = $user_ids['bob_the_builder'];
        $group_id = null;
        $subject = 'Re: New Feature Deployed';
        $body = 'Congrats Alice! Looking forward to trying it out.';
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $reply_email_id = $pdo->lastInsertId();
        // Recipient: Alice
        $recipient_user_id = $user_ids['alice_k'];
        if ($recipient_user_id != $user_id) {
            $email_status_stmt->execute([
                'email_id' => $reply_email_id,
                'user_id' => $recipient_user_id,
                'status' => 'unread'
            ]);
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Reply to 'New Feature Deployed' inserted.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to insert reply to 'New Feature Deployed': " . $e->getMessage() . "\n";
    }

    // 3. Forward 'Finished "The Hitchhiker\'s Guide to the Galaxy"' to Alice (from Bob)
    $pdo->beginTransaction();
    try {
        $orig_subject = 'Finished "The Hitchhiker\'s Guide to the Galaxy"';
        $parent_email_id = $email_ids[$orig_subject];
        $orig_thread_id = $thread_ids[$orig_subject];
        $user_id = $user_ids['bob_the_builder'];
        $group_id = null; // Forward as a personal email
        $subject = 'Fwd: Finished "The Hitchhiker\'s Guide to the Galaxy"';
        $body = 'Alice, forwarding you this book review from Charlie.\n---\n' . 'Just finished reading "The Hitchhiker\'s Guide to the Galaxy". What a ride!';
        // New thread for forward
        $thread_stmt->execute([
            'subject' => $subject,
            'created_by_user_id' => $user_id,
            'group_id' => $group_id
        ]);
        $thread_id = $pdo->lastInsertId();
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $forward_email_id = $pdo->lastInsertId();
        // Recipient: Alice
        $recipient_user_id = $user_ids['alice_k'];
        if ($recipient_user_id != $user_id) {
            $email_status_stmt->execute([
                'email_id' => $forward_email_id,
                'user_id' => $recipient_user_id,
                'status' => 'unread'
            ]);
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Forward to Alice inserted.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to insert forward to Alice: " . $e->getMessage() . "\n";
    }

    // 4. Forward 'Library Updates' to Book Club (from Charlie)
    $pdo->beginTransaction();
    try {
        $orig_subject = 'Library Updates';
        $parent_email_id = $email_ids[$orig_subject];
        $user_id = $user_ids['charlie_brown'];
        $group_id = $group_ids['Book Club'];
        $subject = 'Fwd: Library Updates';
        $body = 'Book Club, sharing this update from Bob in Developers Corner.\n---\nJust pushed some updates to our main library.';
        // New thread for forward in Book Club
        $thread_stmt->execute([
            'subject' => $subject,
            'created_by_user_id' => $user_id,
            'group_id' => $group_id
        ]);
        $thread_id = $pdo->lastInsertId();
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $forward_email_id = $pdo->lastInsertId();
        // Recipients: all Book Club members except sender
        $group_members_stmt->execute(['group_id' => $group_id]);
        $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $forward_email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Forward to Book Club inserted.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to insert forward to Book Club: " . $e->getMessage() . "\n";
    }

    // --- Even more emails for richer test data ---
    echo "Injecting additional emails...\n";
    // 1. Charlie replies to Bob's reply in the "PHP 8.3 Features" thread
    $pdo->beginTransaction();
    try {
        $thread_id = $thread_ids['PHP 8.3 Features'];
        // Find Bob's reply email ID (previously inserted)
        $stmt = $pdo->query("SELECT id FROM emails WHERE thread_id = $thread_id AND subject = 'Re: PHP 8.3 Features' AND user_id = " . $user_ids['bob_the_builder']);
        $parent_email_id = $stmt->fetchColumn();
        $user_id = $user_ids['charlie_brown'];
        $group_id = $group_ids['Developers Corner'];
        $subject = 'Re: PHP 8.3 Features';
        $body = 'I am excited to try the new match expression!';
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $reply_email_id = $pdo->lastInsertId();
        $group_members_stmt->execute(['group_id' => $group_id]);
        $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $reply_email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Charlie replied in 'PHP 8.3 Features'.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to insert Charlie's reply: " . $e->getMessage() . "\n";
    }

    // 2. Alice replies again in the same thread
    $pdo->beginTransaction();
    try {
        $thread_id = $thread_ids['PHP 8.3 Features'];
        // Find Charlie's reply email ID
        $stmt = $pdo->query("SELECT id FROM emails WHERE thread_id = $thread_id AND user_id = " . $user_ids['charlie_brown'] . " ORDER BY id DESC LIMIT 1");
        $parent_email_id = $stmt->fetchColumn();
        $user_id = $user_ids['alice_k'];
        $group_id = $group_ids['Developers Corner'];
        $subject = 'Re: PHP 8.3 Features';
        $body = 'Let's schedule a meeting to discuss!';
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $reply_email_id = $pdo->lastInsertId();
        $group_members_stmt->execute(['group_id' => $group_id]);
        $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $reply_email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Alice replied again in 'PHP 8.3 Features'.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to insert Alice's second reply: " . $e->getMessage() . "\n";
    }

    // 3. Alice forwards 'Next up: "Dune"' (Book Club) to Developers Corner
    $pdo->beginTransaction();
    try {
        $orig_subject = 'Next up: "Dune"';
        $parent_email_id = $email_ids[$orig_subject];
        $user_id = $user_ids['alice_k'];
        $group_id = $group_ids['Developers Corner'];
        $subject = 'Fwd: Next up: "Dune"';
        $body = 'Sharing this from Book Club!\n---\nNext up: "Dune". Any fans here?';
        $thread_stmt->execute([
            'subject' => $subject,
            'created_by_user_id' => $user_id,
            'group_id' => $group_id
        ]);
        $thread_id = $pdo->lastInsertId();
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $forward_email_id = $pdo->lastInsertId();
        $group_members_stmt->execute(['group_id' => $group_id]);
        $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $forward_email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Alice forwarded 'Dune' to Developers Corner.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to forward 'Dune': " . $e->getMessage() . "\n";
    }

    // 4. Bob sends a personal email to both Alice and Charlie
    $pdo->beginTransaction();
    try {
        $user_id = $user_ids['bob_the_builder'];
        $group_id = null;
        $subject = 'Lunch Plans';
        $body = 'Anyone up for lunch tomorrow?';
        $thread_stmt->execute([
            'subject' => $subject,
            'created_by_user_id' => $user_id,
            'group_id' => $group_id
        ]);
        $thread_id = $pdo->lastInsertId();
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt->execute([
            'thread_id' => $thread_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $email_id = $pdo->lastInsertId();
        foreach (['alice_k', 'charlie_brown'] as $recipient) {
            $recipient_user_id = $user_ids[$recipient];
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Bob sent a personal email to Alice and Charlie.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to send Bob's personal email: " . $e->getMessage() . "\n";
    }

    // 5. Alice posts a new announcement in Developers Corner
    $pdo->beginTransaction();
    try {
        $user_id = $user_ids['alice_k'];
        $group_id = $group_ids['Developers Corner'];
        $subject = 'Team Meeting Announcement';
        $body = 'Reminder: Team meeting this Friday at 10am.';
        $thread_stmt->execute([
            'subject' => $subject,
            'created_by_user_id' => $user_id,
            'group_id' => $group_id
        ]);
        $thread_id = $pdo->lastInsertId();
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt->execute([
            'thread_id' => $thread_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $email_id = $pdo->lastInsertId();
        $group_members_stmt->execute(['group_id' => $group_id]);
        $recipient_user_ids = $group_members_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipient_user_ids as $recipient_user_id) {
            if ($recipient_user_id != $user_id) {
                $email_status_stmt->execute([
                    'email_id' => $email_id,
                    'user_id' => $recipient_user_id,
                    'status' => 'unread'
                ]);
            }
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Alice posted a group announcement.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to post group announcement: " . $e->getMessage() . "\n";
    }

    // 6. Charlie sends an email to himself
    $pdo->beginTransaction();
    try {
        $user_id = $user_ids['charlie_brown'];
        $group_id = null;
        $subject = 'Note to Self';
        $body = 'Remember to buy milk.';
        $thread_stmt->execute([
            'subject' => $subject,
            'created_by_user_id' => $user_id,
            'group_id' => $group_id
        ]);
        $thread_id = $pdo->lastInsertId();
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt->execute([
            'thread_id' => $thread_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $email_id = $pdo->lastInsertId();
        $email_status_stmt->execute([
            'email_id' => $email_id,
            'user_id' => $user_id,
            'status' => 'unread'
        ]);
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Charlie sent a note to himself.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to send self-message: " . $e->getMessage() . "\n";
    }

    // 7. Alice replies to the forward she received from Bob about "The Hitchhiker's Guide to the Galaxy"
    $pdo->beginTransaction();
    try {
        // Find the thread and email ID for the forward
        $stmt = $pdo->query("SELECT id, thread_id FROM emails WHERE subject = 'Fwd: Finished " . $pdo->quote('The Hitchhiker\'s Guide to the Galaxy') . "' AND user_id = " . $user_ids['bob_the_builder'] . " ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $parent_email_id = $row['id'];
        $thread_id = $row['thread_id'];
        $user_id = $user_ids['alice_k'];
        $group_id = null;
        $subject = 'Re: Fwd: Finished "The Hitchhiker\'s Guide to the Galaxy"';
        $body = 'Thanks for sharing, Bob! I loved that book too.';
        $message_id = "<" . uniqid('', true) . "@epistol.local>";
        $email_stmt2->execute([
            'thread_id' => $thread_id,
            'parent_email_id' => $parent_email_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'subject' => $subject,
            'body_text' => $body,
            'message_id_header' => $message_id
        ]);
        $reply_email_id = $pdo->lastInsertId();
        // Recipient: Bob
        $recipient_user_id = $user_ids['bob_the_builder'];
        if ($recipient_user_id != $user_id) {
            $email_status_stmt->execute([
                'email_id' => $reply_email_id,
                'user_id' => $recipient_user_id,
                'status' => 'unread'
            ]);
        }
        $update_thread_activity_stmt->execute([
            'timestamp' => date('Y-m-d H:i:s'),
            'thread_id' => $thread_id
        ]);
        $pdo->commit();
        echo "  Alice replied to Bob's forward.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  Failed to reply to Bob's forward: " . $e->getMessage() . "\n";
    }

    echo "Test data injection via function completed successfully!\n";
}

// Standalone execution block:
// Only run this if the script is executed directly from the command line.
// The condition `basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])` ensures it only runs
// when this file is the main script, not when it's included.
if ((php_sapi_name() === 'cli' || php_sapi_name() === 'cgi-fcgi') && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Go to the project root directory
    chdir(__DIR__ . '/..');

    // These are needed for standalone execution to establish DB connection and load config.
    require_once 'config/config.php';
    require_once 'src/db.php';

    echo "Running inject_test_data.php directly from CLI...\n";
    try {
        // Establish a new PDO connection for standalone execution
        $pdo = get_db_connection(); 
        echo "Database connection successful for direct execution.\n";
        
        // Call the main data injection function
        inject_initial_data($pdo);

    } catch (PDOException $e) {
        error_log("Database error during direct execution: " . $e->getMessage());
        echo "Database error during direct execution: " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        error_log("Error during direct execution: " . $e->getMessage());
        echo "Error during direct execution: " . $e->getMessage() . "\n";
    }
}

?>
