<?php

// Go to the project root directory
chdir(__DIR__ . '/..');

require_once 'config/config.php';
require_once 'src/db.php';

echo "Starting test data injection...\n";

try {
    $pdo = get_db_connection();
    echo "Database connection successful.\n";

    // Truncate tables to start fresh (optional, but good for a test script)
    // Be cautious with this in a real environment
    $pdo->exec("DELETE FROM posts");
    $pdo->exec("DELETE FROM group_members");
    $pdo->exec("DELETE FROM groups");
    $pdo->exec("DELETE FROM users");
    echo "Existing data cleared (if any).\n";

    // 1. Insert Users
    echo "Injecting users...\n";
    $users = [
        ['username' => 'alice_k', 'email' => 'alice@example.com', 'password' => 'password123'],
        ['username' => 'bob_the_builder', 'email' => 'bob@example.com', 'password' => 'secureBobPass!'],
        ['username' => 'charlie_brown', 'email' => 'charlie@example.com', 'password' => 'goodgrief'],
    ];
    $user_ids = [];

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");
    foreach ($users as $user) {
        $password_hash = password_hash($user['password'], PASSWORD_DEFAULT);
        if ($stmt->execute(['username' => $user['username'], 'email' => $user['email'], 'password_hash' => $password_hash])) {
            $user_id = $pdo->lastInsertId();
            $user_ids[$user['username']] = $user_id;
            echo "  User '{$user['username']}' inserted with ID: {$user_id}\n";
        } else {
            echo "  Failed to insert user '{$user['username']}'.\n";
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

    // 4. Insert Posts
    echo "Injecting posts...\n";
    $posts = [
        ['username' => 'alice_k', 'group_name' => null, 'content' => 'Just deployed a new feature! #proud'],
        ['username' => 'bob_the_builder', 'group_name' => null, 'content' => 'Thinking about my next big project.'],
        ['username' => 'alice_k', 'group_name' => 'Developers Corner', 'content' => 'Anyone familiar with the new PHP 8.3 features?'],
        ['username' => 'bob_the_builder', 'group_name' => 'Developers Corner', 'content' => 'Just pushed some updates to our main library.'],
        ['username' => 'charlie_brown', 'group_name' => 'Book Club', 'content' => 'Just finished reading "The Hitchhiker\'s Guide to the Galaxy". What a ride!'],
        ['username' => 'bob_the_builder', 'group_name' => 'Book Club', 'content' => 'Next up: "Dune". Any fans here?'],
    ];

    $stmt = $pdo->prepare("INSERT INTO posts (user_id, group_id, content) VALUES (:user_id, :group_id, :content)");
    foreach ($posts as $post) {
        $user_id = $user_ids[$post['username']];
        $group_id = $post['group_name'] ? $group_ids[$post['group_name']] : null;
        if ($stmt->execute(['user_id' => $user_id, 'group_id' => $group_id, 'content' => $post['content']])) {
            echo "  Post by '{$post['username']}'" . ($post['group_name'] ? " in '{$post['group_name']}'" : "") . " inserted.\n";
        } else {
            echo "  Failed to insert post by '{$post['username']}'.\n";
        }
    }

    echo "Test data injection completed successfully!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>
