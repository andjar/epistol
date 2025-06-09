<?php

require_once __DIR__ . '/BaseApiTest.php';

class SetEmailStatusApiTest extends BaseApiTest
{
    private $testUserIds = [];
    private $testThreadIds = [];
    private $testEmailIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Create a default user and an email for testing status changes
        $this->createTestUserWithPerson("StatusUser", $userId, $personId);
        $this->testUserIds[] = $userId;

        $threadId = $this->createTestThread("Status Thread", $userId, date('Y-m-d H:i:s'));
        $this->testThreadIds[] = $threadId;

        $emailId = $this->createTestEmail($threadId, $userId, "Email for Status Test", date('Y-m-d H:i:s'));
        $this->testEmailIds[] = $emailId;
    }

    protected function tearDown(): void
    {
        // Order of deletion
        $this->executeSql("DELETE FROM email_statuses WHERE email_id IN (" . implode(',', array_map('intval', $this->testEmailIds)) . ")");
        $this->executeSql("DELETE FROM emails WHERE id IN (" . implode(',', array_map('intval', $this->testEmailIds)) . ")", $this->testEmailIds);
        $this->executeSql("DELETE FROM threads WHERE id IN (" . implode(',', array_map('intval', $this->testThreadIds)) . ")", $this->testThreadIds);

        $this->executeSql("UPDATE users SET person_id = NULL WHERE id IN (" . implode(',', array_map('intval', $this->testUserIds)) . ")");
        // Assuming persons are cleaned up by BaseApiTest or elsewhere if shared, or add personId tracking too
        $this->executeSql("DELETE FROM users WHERE id IN (" . implode(',', array_map('intval', array_filter($this->testUserIds, fn($id) => $id > 0))) . ")", array_filter($this->testUserIds, fn($id) => $id > 0));

        $this->testUserIds = [];
        $this->testThreadIds = [];
        $this->testEmailIds = [];
        parent::tearDown();
    }

    // Helpers to create minimal data, could be expanded or use BaseApiTest ones if more suitable
    private function createTestUserWithPerson(string $usernameSuffix, &$userId, &$personId)
    {
        $personName = "Test Person " . $usernameSuffix;
        $this->executeSql("INSERT INTO persons (name) VALUES (?)", [$personName]);
        $personId = $this->getLastInsertId();

        $email = "user{$usernameSuffix}@example.com";
        $this->executeSql(
            "INSERT INTO users (username, email, password_hash, person_id, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            ["user{$usernameSuffix}", $email, password_hash("password", PASSWORD_DEFAULT), $personId]
        );
        $userId = $this->getLastInsertId();
    }

    private function createTestThread(string $subject, int $creatorId, string $lastActivityAt): int {
        $this->executeSql(
            "INSERT INTO threads (subject, created_by_user_id, created_at, last_activity_at) VALUES (?, ?, datetime('now'), ?)",
            [$subject, $creatorId, $lastActivityAt]
        );
        return $this->getLastInsertId();
    }

    private function createTestEmail(int $threadId, int $userId, string $subject, string $createdAt): int {
        $body = "Body for email " . $subject;
        $this->executeSql(
            "INSERT INTO emails (thread_id, user_id, subject, body_text, created_at, message_id_header) VALUES (?, ?, ?, ?, ?, ?)",
            [$threadId, $userId, $subject, $body, $createdAt, "<msg".uniqid()."@test.com>"]
        );
        return $this->getLastInsertId();
    }

    public function testSetNewEmailStatus()
    {
        $userIdToTest = $this->testUserIds[0];
        $emailIdToTest = $this->testEmailIds[0];
        $statusToSet = 'read';

        $requestData = [
            'email_id' => $emailIdToTest, // Changed from post_id
            'user_id' => $userIdToTest,
            'status' => $statusToSet
        ];

        // Assuming the API script is still named set_post_status.php but logic refers to emails
        $response = $this->executeApiScript('set_post_status.php', 'POST', [], $requestData);

        $this->assertEquals(201, $response['code'], "API call failed or returned unexpected code: {$response['raw_output']}");
        $this->assertEquals('success', $response['body']['status']);
        $this->assertArrayHasKey('id', $response['body']['data'], "Response should contain the ID of the new status entry.");
        $statusEntryId = $response['body']['data']['id'];

        // Verify in database
        $statusEntry = $this->fetchOne("SELECT * FROM email_statuses WHERE id = ?", [$statusEntryId]);
        $this->assertNotEmpty($statusEntry);
        $this->assertEquals($emailIdToTest, $statusEntry['email_id']);
        $this->assertEquals($userIdToTest, $statusEntry['user_id']);
        $this->assertEquals($statusToSet, $statusEntry['status']);
    }

    public function testUpdateExistingEmailStatus()
    {
        $userIdToTest = $this->testUserIds[0];
        $emailIdToTest = $this->testEmailIds[0];
        $initialStatus = 'unread';
        $updatedStatus = 'archived';

        // Set initial status
        $this->executeSql("INSERT INTO email_statuses (email_id, user_id, status, created_at) VALUES (?, ?, ?, datetime('now'))",
            [$emailIdToTest, $userIdToTest, $initialStatus]
        );
        $initialStatusEntryId = $this->getLastInsertId();

        $requestData = [
            'email_id' => $emailIdToTest,
            'user_id' => $userIdToTest,
            'status' => $updatedStatus
        ];

        $response = $this->executeApiScript('set_post_status.php', 'POST', [], $requestData);

        $this->assertEquals(200, $response['code'], "API call failed or returned unexpected code: {$response['raw_output']}");
        $this->assertEquals('success', $response['body']['status']);
        // The ID in response data for update might be the same as initial, or not present, depending on script's response for update
        // Current set_post_status.php returns "Post status updated successfully." without ID on update.
        // Let's ensure message is correct.
        $this->assertEquals("Post status updated successfully.", $response['body']['data']['message']);


        // Verify in database
        $statusEntry = $this->fetchOne("SELECT * FROM email_statuses WHERE id = ?", [$initialStatusEntryId]);
        $this->assertNotEmpty($statusEntry);
        $this->assertEquals($updatedStatus, $statusEntry['status']);
    }

    public function testSetInvalidStatusValue()
    {
        $userIdToTest = $this->testUserIds[0];
        $emailIdToTest = $this->testEmailIds[0];

        $requestData = [
            'email_id' => $emailIdToTest,
            'user_id' => $userIdToTest,
            'status' => 'invalid-status-value'
        ];

        $response = $this->executeApiScript('set_post_status.php', 'POST', [], $requestData);

        // Assuming the API validates the status string against allowed values
        $this->assertEquals(400, $response['code'], "API should reject invalid status values: {$response['raw_output']}");
        $this->assertEquals('error', $response['body']['status']);
        // The exact error message depends on set_post_status.php implementation
        $this->assertStringContainsStringIgnoringCase("invalid status value", $response['body']['message']);
    }
}
?>
