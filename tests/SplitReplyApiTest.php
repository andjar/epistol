<?php

require_once __DIR__ . '/BaseApiTest.php';

class SplitReplyApiTest extends BaseApiTest
{
    private $testUserIds = [];
    private $testThreadIds = [];
    private $testEmailIds = [];
    private $originalSenderId; // User ID for the original sender of emails
    private $actionUserId;     // User ID for the user performing the split action

    protected function setUp(): void
    {
        parent::setUp(); // Call parent setUp if it has common logic

        // Create users needed for tests
        // User 1: Original sender of emails
        $this->originalSenderId = 1; // Matches SENDER_USER_ID from BaseApiTest
        $user1 = $this->fetchOne("SELECT id FROM users WHERE id = ?", [$this->originalSenderId]);
        if (!$user1) {
            $this->createUserForTest("originalsender", "original@example.com", "password", $this->originalSenderId);
        }
        $this->testUserIds[] = $this->originalSenderId;

        // User 2: User performing the split action
        $this->actionUserId = 2;
         $user2 = $this->fetchOne("SELECT id FROM users WHERE id = ?", [$this->actionUserId]);
        if (!$user2) {
            $this->createUserForTest("actionuser", "action@example.com", "password", $this->actionUserId);
        }
        $this->testUserIds[] = $this->actionUserId;
    }

    private function createUserForTest(string $username, string $email, string $password, int $id): int
    {
        // Simplified user creation for test setup, assuming IDs can be manually set or conflicts are handled.
        // This is a helper specific to this test class if BaseApiTest's createUser is not suitable.
        $existingUser = $this->fetchOne("SELECT id FROM users WHERE id = ?", [$id]);
        if ($existingUser) {
            // Potentially update if needed, or just return id
            return $id;
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // For SQLite, specific ID insertion works like this. Other DBs might need different handling.
        $this->executeSql(
            "INSERT INTO users (id, username, email, password_hash, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$id, $username, $email, $hashed_password]
        );
        return $this->getLastInsertId(); // Should return $id if insertion was specific
    }


    protected function tearDown(): void
    {
        // Order of cleanup matters due to foreign keys
        foreach ($this->testEmailIds as $id) { $this->cleanupEmail($id); }
        foreach ($this->testThreadIds as $id) { $this->cleanupThread($id); }

        // Cleanup users created specifically for these tests, avoid deleting shared users like ID 1 if not managed by this class
        // if ($this->actionUserId !== SENDER_USER_ID) { // SENDER_USER_ID is often 1
        //     $this->executeSql("DELETE FROM users WHERE id = ?", [$this->actionUserId]);
        // }
        // The BaseApiTest::SENDER_USER_ID might be used as originalSenderId.
        // Careful cleanup is needed. For now, assuming BaseApiTest::tearDown or manual cleanup handles shared users.
        // Only explicitly clean users created uniquely by this test class if they have fixed IDs different from shared ones.
        // For this example, let's assume users 1 and 2 might be general test users.
        // If specific cleanup is needed:
        // $this->executeSql("DELETE FROM users WHERE id IN (?, ?)", [$this->originalSenderId, $this->actionUserId]);

        $this->testUserIds = [];
        $this->testThreadIds = [];
        $this->testEmailIds = [];
        parent::tearDown();
    }

    public function testSplitReplySuccessfully()
    {
        // 1. Setup: Create an initial email and thread (email1 by originalSenderId)
        $initialSubject = 'Original Thread Subject for Split Test';
        $sendEmailRequest1 = [
            'recipients' => ['recipient1@example.com'],
            'subject' => $initialSubject,
            'body_text' => 'This is the first email in the original thread.'
        ];
        // originalSenderId (user 1) sends this email
        // Temporarily override SENDER_USER_ID for this call if BaseApiTest uses it globally for send_email.php
        // Or, ensure send_email.php can take sender_id from request (not current design)
        // For now, assuming send_email.php uses the globally defined SENDER_USER_ID, which is $this->originalSenderId (1)

        $initialResponse = $this->executeApiScript('send_email.php', 'POST', [], $sendEmailRequest1);
        $this->assertEquals(200, $initialResponse['code'], "Setup email1 failed: {$initialResponse['raw_output']}");
        $email1_id = $initialResponse['body']['data']['email_id'];
        $original_thread_id = $initialResponse['body']['data']['thread_id'];
        $this->testEmailIds[] = $email1_id;
        $this->testThreadIds[] = $original_thread_id;

        // Create a reply (email2 by originalSenderId to email1)
        $replySubject = 'Re: ' . $initialSubject;
        $sendEmailRequest2 = [
            'recipients' => ['recipient2@example.com'],
            'subject' => $replySubject,
            'body_text' => 'This is the reply email that will be split.',
            'in_reply_to_email_id' => $email1_id
        ];
        $replyResponse = $this->executeApiScript('send_email.php', 'POST', [], $sendEmailRequest2);
        $this->assertEquals(200, $replyResponse['code'], "Setup email2 (reply) failed: {$replyResponse['raw_output']}");
        $email2_id_to_split = $replyResponse['body']['data']['email_id'];
        $this->testEmailIds[] = $email2_id_to_split;


        // 2. Prepare request data to split email2
        $splitRequestData = [
            'email_id' => $email2_id_to_split,
            'user_id' => $this->actionUserId // User 2 performs the split
        ];

        // Execute split_reply_to_post.php
        $splitApiResponse = $this->executeApiScript('split_reply_to_post.php', 'POST', [], $splitRequestData);

        // Assert successful JSON response
        $this->assertEquals(200, $splitApiResponse['code'], "Split API call failed: {$splitApiResponse['raw_output']}");
        $this->assertEquals('success', $splitApiResponse['body']['status']);
        $this->assertArrayHasKey('new_thread_id', $splitApiResponse['body']['data']);
        $this->assertArrayHasKey('updated_email_id', $splitApiResponse['body']['data']);
        $this->assertEquals($email2_id_to_split, $splitApiResponse['body']['data']['updated_email_id']);

        $new_thread_id = $splitApiResponse['body']['data']['new_thread_id'];
        $this->testThreadIds[] = $new_thread_id; // Add new thread to cleanup list

        // Assert email2 (the split email) is updated
        $splitEmail = $this->fetchOne("SELECT * FROM emails WHERE id = ?", [$email2_id_to_split]);
        $this->assertNotEmpty($splitEmail);
        $this->assertEquals($new_thread_id, $splitEmail['thread_id'], "Split email should now belong to the new thread.");
        $this->assertNull($splitEmail['parent_email_id'], "Split email should now be a root email (parent_email_id is NULL).");
        $this->assertEquals($replySubject, $splitEmail['subject']); // Subject should remain

        // Assert new thread exists
        $newThread = $this->fetchOne("SELECT * FROM threads WHERE id = ?", [$new_thread_id]);
        $this->assertNotEmpty($newThread);
        $this->assertEquals($replySubject, $newThread['subject'], "New thread subject should match the split email's subject.");
        $this->assertEquals($this->actionUserId, $newThread['created_by_user_id'], "New thread creator should be the action user.");
        $this->assertNotNull($newThread['last_activity_at']);
        // New thread's last_activity_at should be the split email's creation time
        $this->assertEquals($splitEmail['created_at'], $newThread['last_activity_at']);


        // Assert last_activity_at for the original thread is correctly updated
        $originalThread = $this->fetchOne("SELECT * FROM threads WHERE id = ?", [$original_thread_id]);
        $this->assertNotEmpty($originalThread);

        // Email1 is the only one left in the original thread
        $email1 = $this->fetchOne("SELECT created_at FROM emails WHERE id = ?", [$email1_id]);
        $this->assertEquals($email1['created_at'], $originalThread['last_activity_at'], "Original thread's last_activity_at should be email1's timestamp.");
    }

    public function testSplitNonExistentEmail()
    {
        $splitRequestData = [
            'email_id' => 99999, // Non-existent email ID
            'user_id' => $this->actionUserId
        ];

        $response = $this->executeApiScript('split_reply_to_post.php', 'POST', [], $splitRequestData);

        $this->assertEquals(404, $response['code'], "API should return 404 for non-existent email: {$response['raw_output']}");
        $this->assertEquals('error', $response['body']['status']);
        $this->assertEquals('Email to be split not found.', $response['body']['message']);
    }
}
?>
