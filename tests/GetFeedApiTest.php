<?php

require_once __DIR__ . '/BaseApiTest.php';

class GetFeedApiTest extends BaseApiTest
{
    private $testUserIds = [];
    private $testPersonIds = [];
    private $testThreadIds = [];
    private $testEmailIds = [];
    private $testGroupIds = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Order of deletion matters
        $this->executeSql("DELETE FROM email_statuses WHERE email_id IN (" . implode(',', array_map('intval', $this->testEmailIds)) . ")");
        $this->executeSql("DELETE FROM emails WHERE id IN (" . implode(',', array_map('intval', $this->testEmailIds)) . ")", $this->testEmailIds);
        $this->executeSql("DELETE FROM threads WHERE id IN (" . implode(',', array_map('intval', $this->testThreadIds)) . ")", $this->testThreadIds);
        $this->executeSql("DELETE FROM groups WHERE id IN (" . implode(',', array_map('intval', $this->testGroupIds)) . ")", $this->testGroupIds);

        $this->executeSql("UPDATE users SET person_id = NULL WHERE id IN (" . implode(',', array_map('intval', $this->testUserIds)) . ")");
        $this->executeSql("DELETE FROM persons WHERE id IN (" . implode(',', array_map('intval', $this->testPersonIds)) . ")", $this->testPersonIds);
        $this->executeSql("DELETE FROM users WHERE id IN (" . implode(',', array_map('intval', array_filter($this->testUserIds, fn($id) => $id > 0))) . ")", array_filter($this->testUserIds, fn($id) => $id > 0));

        $this->testUserIds = [];
        $this->testPersonIds = [];
        $this->testThreadIds = [];
        $this->testEmailIds = [];
        $this->testGroupIds = [];
        parent::tearDown();
    }

    private function createTestUserWithPerson(string $usernameSuffix, &$userId, &$personId)
    {
        $personName = "Test Person " . $usernameSuffix;
        $this->executeSql("INSERT INTO persons (name, avatar_url) VALUES (?, ?)", [$personName, "/avatars/test{$usernameSuffix}.png"]);
        $personId = $this->getLastInsertId();
        $this->testPersonIds[] = $personId;

        $email = "user{$usernameSuffix}@example.com";
        $this->executeSql(
            "INSERT INTO users (username, email, password_hash, person_id, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            ["user{$usernameSuffix}", $email, password_hash("password", PASSWORD_DEFAULT), $personId]
        );
        $userId = $this->getLastInsertId();
        $this->testUserIds[] = $userId;
    }

    private function createGroup(string $name, int $creatorId): int {
        $this->executeSql("INSERT INTO groups (name, created_by_user_id, created_at) VALUES (?, ?, datetime('now'))", [$name, $creatorId]);
        $groupId = $this->getLastInsertId();
        $this->testGroupIds[] = $groupId;
        return $groupId;
    }

    private function createThread(string $subject, int $creatorId, ?int $groupId, string $lastActivityAt): int {
        $this->executeSql(
            "INSERT INTO threads (subject, created_by_user_id, group_id, created_at, last_activity_at) VALUES (?, ?, ?, datetime('now'), ?)",
            [$subject, $creatorId, $groupId, $lastActivityAt]
        );
        $threadId = $this->getLastInsertId();
        $this->testThreadIds[] = $threadId;
        return $threadId;
    }

    private function createEmail(int $threadId, int $userId, string $subject, string $createdAt, ?int $parentId = null, ?int $groupId = null): int {
        $body = "Body for email " . $subject;
        $this->executeSql(
            "INSERT INTO emails (thread_id, user_id, subject, body_text, body_html, created_at, parent_email_id, group_id, message_id_header) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$threadId, $userId, $subject, $body, "<p>$body</p>", $createdAt, $parentId, $groupId, "<msg".uniqid()."@test.com>"]
        );
        $emailId = $this->getLastInsertId();
        $this->testEmailIds[] = $emailId;
        return $emailId;
    }

    private function setEmailStatus(int $emailId, int $userId, string $status) {
        $this->executeSql("INSERT INTO email_statuses (email_id, user_id, status, created_at) VALUES (?, ?, ?, datetime('now'))", [$emailId, $userId, $status]);
    }


    public function testGetFeedPaginationAndOrder()
    {
        $this->createTestUserWithPerson("FeedUser", $user1Id, $person1Id);
        $groupId1 = $this->createGroup("Group Alpha", $user1Id);

        // Create threads with varying last_activity_at
        $thread1Id = $this->createThread("Thread 1", $user1Id, $groupId1, '2024-01-01 10:00:00'); // oldest
        $this->createEmail($thread1Id, $user1Id, "Email T1E1", '2024-01-01 10:00:00');

        $thread2Id = $this->createThread("Thread 2", $user1Id, null, '2024-01-01 12:00:00'); // newest
        $this->createEmail($thread2Id, $user1Id, "Email T2E1", '2024-01-01 12:00:00');

        $thread3Id = $this->createThread("Thread 3", $user1Id, $groupId1, '2024-01-01 11:00:00'); // middle
        $this->createEmail($thread3Id, $user1Id, "Email T3E1", '2024-01-01 11:00:00');

        // Scenario 1: No filters, page 1, limit 2
        $response = $this->executeApiScript('get_feed.php', 'GET', ['user_id' => $user1Id, 'page' => 1, 'limit' => 2]);
        $this->assertEquals(200, $response['code'], "Raw: {$response['raw_output']}");
        $this->assertEquals('success', $response['body']['status']);
        $this->assertCount(2, $response['body']['data']['threads']);
        $this->assertEquals($thread2Id, $response['body']['data']['threads'][0]['thread_id'], "Thread 2 (newest) should be first.");
        $this->assertEquals($thread3Id, $response['body']['data']['threads'][1]['thread_id'], "Thread 3 (middle) should be second.");
        $this->assertEquals(3, $response['body']['data']['pagination']['total_items']);
        $this->assertEquals(2, $response['body']['data']['pagination']['total_pages']);

        // Scenario 1: No filters, page 2, limit 2
        $response = $this->executeApiScript('get_feed.php', 'GET', ['user_id' => $user1Id, 'page' => 2, 'limit' => 2]);
        $this->assertEquals(200, $response['code']);
        $this->assertCount(1, $response['body']['data']['threads']);
        $this->assertEquals($thread1Id, $response['body']['data']['threads'][0]['thread_id'], "Thread 1 (oldest) should be on page 2.");

        // Scenario 2: Filter by group_id
        $response = $this->executeApiScript('get_feed.php', 'GET', ['user_id' => $user1Id, 'page' => 1, 'limit' => 2, 'group_id' => $groupId1]);
        $this->assertEquals(200, $response['code']);
        $this->assertCount(2, $response['body']['data']['threads']); // T3 then T1
        $this->assertEquals($thread3Id, $response['body']['data']['threads'][0]['thread_id']);
        $this->assertEquals($thread1Id, $response['body']['data']['threads'][1]['thread_id']);
        $this->assertEquals(2, $response['body']['data']['pagination']['total_items']);
        $this->assertEquals(1, $response['body']['data']['pagination']['total_pages']);
    }

    public function testCombinedGroupAndStatusFilter()
    {
        $this->createTestUserWithPerson("ComboUser", $user1Id, $person1Id);
        $groupId1 = $this->createGroup("Combo Group", $user1Id);

        // Thread 1 (in group1)
        $thread1Id = $this->createThread("Combo T1", $user1Id, $groupId1, '2024-01-02 10:00:00');
        $email1T1 = $this->createEmail($thread1Id, $user1Id, "Email1 T1", '2024-01-02 09:00:00'); // Read
        $email2T1 = $this->createEmail($thread1Id, $user1Id, "Email2 T1", '2024-01-02 10:00:00'); // Unread
        $this->setEmailStatus($email1T1, $user1Id, 'read');

        // Thread 2 (not in group1)
        $thread2Id = $this->createThread("Combo T2", $user1Id, null, '2024-01-02 11:00:00');
        $email1T2 = $this->createEmail($thread2Id, $user1Id, "Email1 T2", '2024-01-02 11:00:00'); // Read
        $this->setEmailStatus($email1T2, $user1Id, 'read');

        // Thread 3 (in group1)
        $thread3Id = $this->createThread("Combo T3", $user1Id, $groupId1, '2024-01-02 12:00:00');
        $email1T3 = $this->createEmail($thread3Id, $user1Id, "Email1 T3", '2024-01-02 12:00:00'); // Read
        $this->setEmailStatus($email1T3, $user1Id, 'read');


        // Filter by group_id=$groupId1 AND status='read' for user_id=$user1Id
        $response = $this->executeApiScript('get_feed.php', 'GET', ['user_id' => $user1Id, 'group_id' => $groupId1, 'status' => 'read']);
        $this->assertEquals(200, $response['code'], "API Call failed: {$response['raw_output']}");
        $this->assertEquals('success', $response['body']['status']);

        $threadsInResponse = $response['body']['data']['threads'];
        $this->assertCount(2, $threadsInResponse, "Should be 2 threads matching group and having at least one 'read' email by user.");

        // Check Thread 3 (latest activity in group, has a read email)
        $this->assertEquals($thread3Id, $threadsInResponse[0]['thread_id']);
        $this->assertCount(1, $threadsInResponse[0]['emails'], "Thread 3 should only contain its read email.");
        $this->assertEquals($email1T3, $threadsInResponse[0]['emails'][0]['email_id']);
        $this->assertEquals('read', $threadsInResponse[0]['emails'][0]['status']);

        // Check Thread 1 (older activity in group, has a read email)
        $this->assertEquals($thread1Id, $threadsInResponse[1]['thread_id']);
        $this->assertCount(1, $threadsInResponse[1]['emails'], "Thread 1 should only contain its read email.");
        $this->assertEquals($email1T1, $threadsInResponse[1]['emails'][0]['email_id']);
        $this->assertEquals('read', $threadsInResponse[1]['emails'][0]['status']);

        // Total items should be 2 (threads in group1 that have read emails by user1)
        $this->assertEquals(2, $response['body']['data']['pagination']['total_items']);
    }
}
?>
