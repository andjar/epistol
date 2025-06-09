<?php

require_once __DIR__ . '/BaseApiTest.php';

class GetThreadApiTest extends BaseApiTest
{
    private $testUserIds = [];
    private $testPersonIds = [];
    private $testThreadIds = [];
    private $testEmailIds = [];
    private $testAttachmentIds = [];
    private $testGroupIds = []; // Though not directly used in get_thread, good for consistency

    protected function setUp(): void
    {
        parent::setUp(); // Important to call parent setUp
        // Clean up specific tables before each test, if needed, or rely on tearDown
        // $this->executeSql("DELETE FROM attachments");
        // $this->executeSql("DELETE FROM email_statuses");
        // $this->executeSql("DELETE FROM emails");
        // $this->executeSql("DELETE FROM threads");
        // $this->executeSql("DELETE FROM persons");
        // $this->executeSql("DELETE FROM users WHERE id > 2"); // Keep initial users if any
    }

    protected function tearDown(): void
    {
        // Order of deletion matters due to foreign key constraints.
        $this->executeSql("DELETE FROM attachments WHERE id IN (" . implode(',', array_map('intval', $this->testAttachmentIds)) . ")", $this->testAttachmentIds);
        $this->executeSql("DELETE FROM email_statuses WHERE email_id IN (" . implode(',', array_map('intval', $this->testEmailIds)) . ")");
        $this->executeSql("DELETE FROM emails WHERE id IN (" . implode(',', array_map('intval', $this->testEmailIds)) . ")", $this->testEmailIds);
        $this->executeSql("DELETE FROM threads WHERE id IN (" . implode(',', array_map('intval', $this->testThreadIds)) . ")", $this->testThreadIds);

        // Clean up persons and users created by tests, be careful with shared users.
        // Example: if users 1 and 2 are standard test users from BaseApiTest, don't delete them here unless specifically managed.
        // For users created in these tests with dynamic IDs:
        $this->executeSql("UPDATE users SET person_id = NULL WHERE id IN (" . implode(',', array_map('intval', $this->testUserIds)) . ")");
        $this->executeSql("DELETE FROM persons WHERE id IN (" . implode(',', array_map('intval', $this->testPersonIds)) . ")", $this->testPersonIds);
        $this->executeSql("DELETE FROM users WHERE id IN (" . implode(',', array_map('intval', array_filter($this->testUserIds, fn($id) => $id > 0))) . ")", array_filter($this->testUserIds, fn($id) => $id > 0));


        $this->testUserIds = [];
        $this->testPersonIds = [];
        $this->testThreadIds = [];
        $this->testEmailIds = [];
        $this->testAttachmentIds = [];
        parent::tearDown();
    }

    private function createTestUserWithPerson(string $usernameSuffix, &$userId, &$personId)
    {
        // Create Person
        $personName = "Test Person " . $usernameSuffix;
        $this->executeSql("INSERT INTO persons (name, avatar_url) VALUES (?, ?)", [$personName, "/avatars/test{$usernameSuffix}.png"]);
        $personId = $this->getLastInsertId();
        $this->testPersonIds[] = $personId;

        // Create User linked to Person
        $email = "user{$usernameSuffix}@example.com";
        $this->executeSql(
            "INSERT INTO users (username, email, password_hash, person_id, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            ["user{$usernameSuffix}", $email, password_hash("password", PASSWORD_DEFAULT), $personId]
        );
        $userId = $this->getLastInsertId();
        $this->testUserIds[] = $userId;
    }

    public function testGetThreadWithRepliesAndAttachments()
    {
        // 1. Setup
        $this->createTestUserWithPerson("ThreadSender", $user1Id, $person1Id);
        $this->createTestUserWithPerson("ThreadReplier", $user2Id, $person2Id);
        $viewingUserId = $user1Id; // User1 is viewing the thread

        // Create Thread
        $threadSubject = "Thread for API Test";
        $this->executeSql("INSERT INTO threads (subject, created_by_user_id, created_at, last_activity_at) VALUES (?, ?, datetime('now'), datetime('now'))",
            [$threadSubject, $user1Id]
        );
        $threadId = $this->getLastInsertId();
        $this->testThreadIds[] = $threadId;

        // Create Root Email (email1)
        $email1Subject = "Root Email Subject";
        $email1Body = "Body of root email.";
        $this->executeSql(
            "INSERT INTO emails (thread_id, user_id, subject, body_text, body_html, created_at, message_id_header) VALUES (?, ?, ?, ?, ?, datetime('now'), ?)",
            [$threadId, $user1Id, $email1Subject, $email1Body, "<p>{$email1Body}</p>", "<msg1@test.com>"]
        );
        $email1Id = $this->getLastInsertId();
        $this->testEmailIds[] = $email1Id;

        // Create Attachment for email1
        $this->executeSql(
            "INSERT INTO attachments (email_id, filename, mimetype, filesize_bytes, filepath_on_disk, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
            [$email1Id, "test_attachment.txt", "text/plain", 123, "unique_path_to_test_attachment.txt"]
        );
        $attachment1Id = $this->getLastInsertId();
        $this->testAttachmentIds[] = $attachment1Id;

        // Update thread last_activity_at based on email1 (send_email.php would do this)
        $this->executeSql("UPDATE threads SET last_activity_at = (SELECT created_at FROM emails WHERE id = ?) WHERE id = ?", [$email1Id, $threadId]);


        // Create Reply Email (email2)
        $email2Subject = "Reply Email Subject";
        $email2Body = "Body of reply email.";
        // Simulate a slight delay for created_at
        sleep(1); // To ensure created_at times are distinct for ordering if necessary
        $this->executeSql(
            "INSERT INTO emails (thread_id, parent_email_id, user_id, subject, body_text, body_html, created_at, message_id_header) VALUES (?, ?, ?, ?, ?, ?, datetime('now'), ?)",
            [$threadId, $email1Id, $user2Id, $email2Subject, $email2Body, "<p>{$email2Body}</p>", "<msg2@test.com>"]
        );
        $email2Id = $this->getLastInsertId();
        $this->testEmailIds[] = $email2Id;

        // Update thread last_activity_at based on email2
        $this->executeSql("UPDATE threads SET last_activity_at = (SELECT created_at FROM emails WHERE id = ?) WHERE id = ?", [$email2Id, $threadId]);


        // Set status for email2 for viewingUser (user1Id)
        $this->executeSql("INSERT INTO email_statuses (email_id, user_id, status, created_at) VALUES (?, ?, 'read', datetime('now'))",
            [$email2Id, $viewingUserId]
        );

        // 2. Execution
        $response = $this->executeApiScript('get_thread.php', 'GET', ['thread_id' => $threadId, 'user_id' => $viewingUserId]);

        // 3. Assertions
        $this->assertEquals(200, $response['code'], "API call failed: {$response['raw_output']}");
        $this->assertEquals('success', $response['body']['status']);
        $data = $response['body']['data'];

        $this->assertEquals($threadId, $data['id']);
        $this->assertEquals($threadSubject, $data['subject']);
        $this->assertCount(2, $data['emails'], "Should be 2 emails in the thread.");
        $this->assertCount(2, $data['participants'], "Should be 2 participants."); // user1 and user2

        // Check participants (order might vary, so check presence)
        $participantUserIds = array_map(fn($p) => $p['user_id'], $data['participants']);
        $this->assertContains($user1Id, $participantUserIds);
        $this->assertContains($user2Id, $participantUserIds);


        $responseEmail1 = null;
        $responseEmail2 = null;
        foreach($data['emails'] as $emailInResponse) {
            if ($emailInResponse['id'] === $email1Id) $responseEmail1 = $emailInResponse;
            if ($emailInResponse['id'] === $email2Id) $responseEmail2 = $emailInResponse;
        }
        $this->assertNotNull($responseEmail1, "Email 1 not found in response.");
        $this->assertNotNull($responseEmail2, "Email 2 not found in response.");

        // Assertions for Email 1 (root email)
        $this->assertEquals($email1Id, $responseEmail1['id']);
        $this->assertNull($responseEmail1['parent_email_id']);
        $this->assertEquals($email1Subject, $responseEmail1['subject']);
        $this->assertEquals($user1Id, $responseEmail1['sender']['user_id']);
        $this->assertEquals($person1Id, $responseEmail1['sender']['id']); // person_id
        $this->assertEquals("Test Person ThreadSender", $responseEmail1['sender']['name']);
        $this->assertCount(1, $responseEmail1['attachments']);
        $this->assertEquals($attachment1Id, $responseEmail1['attachments'][0]['id']);
        $this->assertEquals("test_attachment.txt", $responseEmail1['attachments'][0]['filename']);
        $this->assertEquals('unread', $responseEmail1['status'], "Email 1 status for user $viewingUserId should default to unread.");


        // Assertions for Email 2 (reply email)
        $this->assertEquals($email2Id, $responseEmail2['id']);
        $this->assertEquals($email1Id, $responseEmail2['parent_email_id']);
        $this->assertEquals($email2Subject, $responseEmail2['subject']);
        $this->assertEquals($user2Id, $responseEmail2['sender']['user_id']);
        $this->assertEquals($person2Id, $responseEmail2['sender']['id']); // person_id
        $this->assertEquals("Test Person ThreadReplier", $responseEmail2['sender']['name']);
        $this->assertCount(0, $responseEmail2['attachments']);
        $this->assertEquals('read', $responseEmail2['status'], "Email 2 status for user $viewingUserId should be 'read'.");
    }

    public function testGetThreadNotFound()
    {
        $response = $this->executeApiScript('get_thread.php', 'GET', ['thread_id' => 99999, 'user_id' => 1]);
        $this->assertEquals(404, $response['code'], "API should return 404 for non-existent thread: {$response['raw_output']}");
        $this->assertEquals('error', $response['body']['status']);
        $this->assertEquals('Thread not found.', $response['body']['message']);
    }
}
?>
