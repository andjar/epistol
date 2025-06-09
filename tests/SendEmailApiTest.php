<?php

require_once __DIR__ . '/BaseApiTest.php';

class SendEmailApiTest extends BaseApiTest
{
    private $testUserIds = [];
    private $testThreadIds = [];
    private 'testEmailIds' = [];

    protected function setUp(): void
    {
        // SENDER_USER_ID is defined in BaseApiTest.php as 1.
        // Ensure this user exists for testing, or create it.
        $user = $this->fetchOne("SELECT id FROM users WHERE id = ?", [SENDER_USER_ID]);
        if (!$user) {
            $this->testUserIds[] = $this->createUser("senderuser", "sender@example.com", "password", SENDER_USER_ID);
        } else {
            $this->testUserIds[] = SENDER_USER_ID; // Add to cleanup list if it was pre-existing but part of test scope
        }
    }

    // Helper to create user with specific ID if it doesn't exist
    private function createUser(string $username, string $email, string $password, int $id): int
    {
        $existingUser = $this->fetchOne("SELECT id FROM users WHERE id = ?", [$id]);
        if ($existingUser) {
            return $id;
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Temporarily disable foreign key checks for SQLite if needed to insert specific ID
        // For other DBs, this might be different or auto-increment might be harder to control
        $this->executeSql("INSERT INTO users (id, username, email, password_hash, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$id, $username, $email, $hashed_password]
        );
        return $id;
    }


    protected function tearDown(): void
    {
        // Order of cleanup matters due to foreign keys
        foreach ($this->testEmailIds as $id) { $this->cleanupEmail($id); }
        foreach ($this->testThreadIds as $id) { $this->cleanupThread($id); }
        // Be careful cleaning up users if they are shared or pre-existing and not specific to this test method
        // For SENDER_USER_ID, if it was pre-existing, we might not want to delete it.
        // If it was created by a test, it should be cleaned.
        // $this->executeSql("DELETE FROM users WHERE id = ?", [SENDER_USER_ID]);

        $this->testUserIds = [];
        $this->testThreadIds = [];
        $this->testEmailIds = [];
        parent::tearDown();
    }

    public function testSendNewEmailCreatesNewThread()
    {
        $requestData = [
            'recipients' => ['testrecipient@example.com'],
            'subject' => 'Test New Email Subject',
            'body_text' => 'This is the plain text body of a new email.',
            'body_html' => '<p>This is the HTML body of a new email.</p>'
        ];

        // SENDER_USER_ID is used by send_email.php internally
        $response = $this->executeApiScript('send_email.php', 'POST', [], $requestData);

        $this->assertEquals(200, $response['code'], "Output: {$response['raw_output']}");
        $this->assertEquals('success', $response['body']['status']);
        $this->assertArrayHasKey('email_id', $response['body']['data']);
        $this->assertArrayHasKey('thread_id', $response['body']['data']);

        $newEmailId = $response['body']['data']['email_id'];
        $newThreadId = $response['body']['data']['thread_id'];
        $this->testEmailIds[] = $newEmailId;
        $this->testThreadIds[] = $newThreadId;


        // Assert new thread exists
        $thread = $this->fetchOne("SELECT * FROM threads WHERE id = ?", [$newThreadId]);
        $this->assertNotEmpty($thread);
        $this->assertEquals($requestData['subject'], $thread['subject']);
        $this->assertEquals(SENDER_USER_ID, $thread['created_by_user_id']);

        // Assert new email exists
        $email = $this->fetchOne("SELECT * FROM emails WHERE id = ?", [$newEmailId]);
        $this->assertNotEmpty($email);
        $this->assertEquals($newThreadId, $email['thread_id']);
        $this->assertNull($email['parent_email_id']);
        $this->assertEquals(SENDER_USER_ID, $email['user_id']); // user_id is sender
        $this->assertEquals($requestData['subject'], $email['subject']);
        $this->assertEquals($requestData['body_text'], $email['body_text']);
        $this->assertEquals($requestData['body_html'], $email['body_html']);

        // Assert thread last_activity_at is updated (should be close to email's created_at)
        $this->assertNotNull($thread['last_activity_at']);
        $this->assertNotNull($email['created_at']);
        // Could compare timestamps if precision allows, or just check it's set

        // Assert email status for sender
        $status = $this->fetchOne("SELECT * FROM email_statuses WHERE email_id = ? AND user_id = ?", [$newEmailId, SENDER_USER_ID]);
        $this->assertNotEmpty($status);
        $this->assertEquals('sent', $status['status']);

        // Assert recipient handling (simplified check)
        $recipientEntry = $this->fetchOne(
            "SELECT er.* FROM email_recipients er
             JOIN email_addresses ea ON er.email_address_id = ea.id
             WHERE er.email_id = ? AND ea.email_address = ?",
            [$newEmailId, 'testrecipient@example.com']
        );
        $this->assertNotEmpty($recipientEntry);
        $this->assertEquals('to', $recipientEntry['type']);
    }

    public function testSendReplyToExistingEmail()
    {
        // 1. Setup: Create an initial email and thread
        $initialSubject = 'Initial Email for Reply Test';
        $initialRequest = [
            'recipients' => ['initialrecipient@example.com'],
            'subject' => $initialSubject,
            'body_text' => 'Initial email body.'
        ];
        $initialResponse = $this->executeApiScript('send_email.php', 'POST', [], $initialRequest);
        $this->assertEquals(200, $initialResponse['code'], "Initial send failed: {$initialResponse['raw_output']}");
        $initialEmailId = $initialResponse['body']['data']['email_id'];
        $initialThreadId = $initialResponse['body']['data']['thread_id'];
        $this->testEmailIds[] = $initialEmailId;
        $this->testThreadIds[] = $initialThreadId;


        // 2. Prepare and send the reply
        $replySubject = 'Re: ' . $initialSubject;
        $replyRequest = [
            'recipients' => ['originalsender@example.com'], // Assuming SENDER_USER_ID's email is this
            'subject' => $replySubject,
            'body_text' => 'This is a reply to the initial email.',
            'in_reply_to_email_id' => $initialEmailId
        ];

        // SENDER_USER_ID (1) sends the reply
        $replyResponse = $this->executeApiScript('send_email.php', 'POST', [], $replyRequest);

        $this->assertEquals(200, $replyResponse['code'], "Reply send failed: {$replyResponse['raw_output']}");
        $this->assertEquals('success', $replyResponse['body']['status']);
        $this->assertArrayHasKey('email_id', $replyResponse['body']['data']);
        $replyEmailId = $replyResponse['body']['data']['email_id'];
        $replyThreadId = $replyResponse['body']['data']['thread_id'];
        $this->testEmailIds[] = $replyEmailId;
        // $this->testThreadIds[] = $replyThreadId; // Should be same as initialThreadId

        // Assert email record for the reply
        $replyEmail = $this->fetchOne("SELECT * FROM emails WHERE id = ?", [$replyEmailId]);
        $this->assertNotEmpty($replyEmail);
        $this->assertEquals($initialThreadId, $replyEmail['thread_id'], "Reply should be in the same thread.");
        $this->assertEquals($initialEmailId, $replyEmail['parent_email_id'], "Reply's parent_email_id should be the initial email's ID.");
        $this->assertEquals(SENDER_USER_ID, $replyEmail['user_id']);
        $this->assertEquals($replySubject, $replyEmail['subject']); // Or "Re: Initial Email for Reply Test" if server modifies

        // Assert thread last_activity_at is updated
        $thread = $this->fetchOne("SELECT last_activity_at FROM threads WHERE id = ?", [$initialThreadId]);
        $this->assertNotNull($thread['last_activity_at']);
        // Check it's greater than or equal to reply email's created_at
        $this->assertGreaterThanOrEqual($replyEmail['created_at'], $thread['last_activity_at']);


        // Assert email status for the sender of the reply
        $status = $this->fetchOne("SELECT * FROM email_statuses WHERE email_id = ? AND user_id = ?", [$replyEmailId, SENDER_USER_ID]);
        $this->assertNotEmpty($status);
        $this->assertEquals('sent', $status['status']);
    }
}
?>
