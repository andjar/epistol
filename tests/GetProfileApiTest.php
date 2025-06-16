<?php

require_once __DIR__ . '/BaseApiTest.php';

class GetProfileApiTest extends BaseApiTest
{
    // Define IDs for test data to ensure consistency and avoid collisions
    // Person IDs
    private const PERSON_A_ID = 'psn_test_prof_a';
    private const PERSON_B_ID = 'psn_test_prof_b';
    private const PERSON_C_ID = 'psn_test_prof_c'; // For no correspondence test
    private const PERSON_D_ID = 'psn_test_prof_d'; // For email not involving Person A

    // User IDs (linked to persons)
    private const USER_A_ID = 'usr_test_prof_a';
    private const USER_B_ID = 'usr_test_prof_b';
    private const USER_D_ID = 'usr_test_prof_d';


    // Email Address IDs
    private const EA_A_ID = 'ea_test_prof_a';
    private const EA_B_ID = 'ea_test_prof_b';
    private const EA_C_ID = 'ea_test_prof_c';
    private const EA_D_ID = 'ea_test_prof_d';

    // Thread IDs
    private const THREAD_1_ID = 'thd_test_prof_1';
    private const THREAD_2_ID = 'thd_test_prof_2';
    private const THREAD_3_ID = 'thd_test_prof_3'; // Other thread

    // Email IDs
    private const EMAIL_1_ID = 'eml_test_prof_1'; // A to B (Thread 1)
    private const EMAIL_2_ID = 'eml_test_prof_2'; // B to A (Thread 1)
    private const EMAIL_3_ID = 'eml_test_prof_3'; // A to B (Thread 2)
    private const EMAIL_4_ID = 'eml_test_prof_4'; // D to B (Thread 3, not involving A)

    protected function setUp(): void
    {
        parent::setUp();
        // Clean up data before each test to ensure isolation
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up data after each test
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData()
    {
        $personIds = [self::PERSON_A_ID, self::PERSON_B_ID, self::PERSON_C_ID, self::PERSON_D_ID];
        $userIds = [self::USER_A_ID, self::USER_B_ID, self::USER_D_ID];
        $emailAddrIds = [self::EA_A_ID, self::EA_B_ID, self::EA_C_ID, self::EA_D_ID];
        $threadIds = [self::THREAD_1_ID, self::THREAD_2_ID, self::THREAD_3_ID];
        $emailIds = [self::EMAIL_1_ID, self::EMAIL_2_ID, self::EMAIL_3_ID, self::EMAIL_4_ID];

        foreach ($emailIds as $id) {
            $this->executeSql("DELETE FROM email_recipients WHERE email_id = ?", [$id]);
            // Add other related tables like email_statuses, attachments if they were populated
        }

        foreach ($threadIds as $id) {
             $this->executeSql("DELETE FROM emails WHERE thread_id = ?", [$id]);
        }

        $this->executeSql("DELETE FROM emails WHERE id IN (" . implode(',', array_fill(0, count($emailIds), '?')) . ")", $emailIds);
        $this->executeSql("DELETE FROM threads WHERE id IN (" . implode(',', array_fill(0, count($threadIds), '?')) . ")", $threadIds);
        $this->executeSql("DELETE FROM users WHERE id IN (" . implode(',', array_fill(0, count($userIds), '?')) . ")", $userIds);
        $this->executeSql("DELETE FROM email_addresses WHERE id IN (" . implode(',', array_fill(0, count($emailAddrIds), '?')) . ")", $emailAddrIds);
        $this->executeSql("DELETE FROM persons WHERE person_id IN (" . implode(',', array_fill(0, count($personIds), '?')) . ")", $personIds);
    }

    private function seedPerson(string $personId, string $name, string $bio = 'Test bio')
    {
        $this->executeSql(
            "INSERT INTO persons (person_id, name, bio, avatar_url, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$personId, $name, $bio, "/avatars/{$personId}.png"]
        );
    }

    private function seedEmailAddress(string $eaId, string $personId, string $email, bool $isPrimary = true)
    {
        $this->executeSql(
            "INSERT INTO email_addresses (id, person_id, email_address, is_primary, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$eaId, $personId, $email, (int)$isPrimary]
        );
    }

    private function seedUser(string $userId, string $personId, string $username)
    {
        // Assuming users table has person_id link and a simple structure for tests
        // BaseApiTest::createUser is for a different users table structure.
        $this->executeSql(
            "INSERT INTO users (id, person_id, username, created_at) VALUES (?, ?, ?, datetime('now'))",
            [$userId, $personId, $username]
        );
    }

    private function seedThread(string $threadId, string $subject)
    {
        $this->executeSql(
            "INSERT INTO threads (id, subject, created_at, last_activity_at) VALUES (?, ?, datetime('now'), datetime('now'))",
            [$threadId, $subject]
        );
    }

    private function seedEmail(string $emailId, string $threadId, string $userId, string $subject, string $bodyText, string $createdAtOffset = "+0 minutes")
    {
        $this->executeSql(
            "INSERT INTO emails (id, thread_id, user_id, subject, body_text, body_html, created_at, parent_email_id)
             VALUES (?, ?, ?, ?, ?, ?, datetime('now', ?), NULL)",
            [$emailId, $threadId, $userId, $subject, $bodyText, "<p>{$bodyText}</p>", $createdAtOffset]
        );
    }

    private function seedEmailRecipient(string $emailId, string $personId, string $emailAddressId, string $type = 'to')
    {
        $this->executeSql(
            "INSERT INTO email_recipients (email_id, person_id, email_address_id, type) VALUES (?, ?, ?, ?)",
            [$emailId, $personId, $emailAddressId, $type]
        );
    }

    public function testGetProfileWithCorrespondence()
    {
        // 1. Setup: Inject test data
        // Person A (target)
        $this->seedPerson(self::PERSON_A_ID, 'Person A Profile');
        $this->seedEmailAddress(self::EA_A_ID, self::PERSON_A_ID, 'persona@example.com', true);
        $this->seedUser(self::USER_A_ID, self::PERSON_A_ID, 'usera_profile');

        // Person B (correspondent)
        $this->seedPerson(self::PERSON_B_ID, 'Person B Profile');
        $this->seedEmailAddress(self::EA_B_ID, self::PERSON_B_ID, 'personb@example.com', true);
        $this->seedUser(self::USER_B_ID, self::PERSON_B_ID, 'userb_profile');

        // Person D (for unrelated email)
        $this->seedPerson(self::PERSON_D_ID, 'Person D Profile');
        $this->seedEmailAddress(self::EA_D_ID, self::PERSON_D_ID, 'persond@example.com', true);
        $this->seedUser(self::USER_D_ID, self::PERSON_D_ID, 'userd_profile');

        // Thread 1
        $this->seedThread(self::THREAD_1_ID, 'Thread Subject 1 Profile');
        // Email 1 in Thread 1: Sent by User A to Person B
        $this->seedEmail(self::EMAIL_1_ID, self::THREAD_1_ID, self::USER_A_ID, 'Email 1 Subject A->B', 'Body of Email 1 from A to B.', '+0 minutes');
        $this->seedEmailRecipient(self::EMAIL_1_ID, self::PERSON_B_ID, self::EA_B_ID, 'to');

        // Email 2 in Thread 1: Reply sent by User B to Person A
        $this->seedEmail(self::EMAIL_2_ID, self::THREAD_1_ID, self::USER_B_ID, 'Email 2 Subject B->A', 'Body of Email 2 from B to A (reply).', '+5 minutes');
        $this->seedEmailRecipient(self::EMAIL_2_ID, self::PERSON_A_ID, self::EA_A_ID, 'to');

        // Thread 2
        $this->seedThread(self::THREAD_2_ID, 'Thread Subject 2 Profile');
        // Email 3 in Thread 2: Sent by User A to Person B
        $this->seedEmail(self::EMAIL_3_ID, self::THREAD_2_ID, self::USER_A_ID, 'Email 3 Subject A->B', 'Body of Email 3 from A to B (Thread 2).', '+10 minutes');
        $this->seedEmailRecipient(self::EMAIL_3_ID, self::PERSON_B_ID, self::EA_B_ID, 'to');

        // Email in another thread not involving Person A (User D to User B)
        $this->seedThread(self::THREAD_3_ID, 'Unrelated Thread Subject Profile');
        $this->seedEmail(self::EMAIL_4_ID, self::THREAD_3_ID, self::USER_D_ID, 'Email 4 Subject D->B', 'Body of Email 4 (unrelated).', '+15 minutes');
        $this->seedEmailRecipient(self::EMAIL_4_ID, self::PERSON_B_ID, self::EA_B_ID, 'to');


        // 2. Action: Make API call
        $response = $this->executeApiScript('get_profile.php', 'GET', ['person_id' => self::PERSON_A_ID]);

        // 3. Assertions
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['body']['status']);
        $data = $response['body']['data'];
        $this->assertEquals(self::PERSON_A_ID, $data['id']);
        $this->assertEquals('Person A Profile', $data['name']);

        $this->assertIsArray($data['threads']);
        $this->assertCount(2, $data['threads'], 'Should be 2 threads for Person A');

        // Sort threads by ID to ensure consistent order for assertion
        usort($data['threads'], fn($a, $b) => strcmp($a['id'], $b['id']));

        $thread1 = null;
        $thread2 = null;

        foreach($data['threads'] as $thread) {
            if ($thread['id'] === self::THREAD_1_ID) $thread1 = $thread;
            if ($thread['id'] === self::THREAD_2_ID) $thread2 = $thread;
        }
        $this->assertNotNull($thread1, "Thread 1 not found");
        $this->assertNotNull($thread2, "Thread 2 not found");


        // Assertions for Thread 1
        $this->assertEquals(self::THREAD_1_ID, $thread1['id']);
        $this->assertEquals('Thread Subject 1 Profile', $thread1['subject']);
        $this->assertCount(2, $thread1['emails'], 'Thread 1 should have 2 emails');
        // Emails are ordered by created_at ASC by the API
        $email1_t1 = $thread1['emails'][0];
        $email2_t1 = $thread1['emails'][1];

        $this->assertEquals(self::EMAIL_1_ID, $email1_t1['id']);
        $this->assertEquals('Person A Profile', $email1_t1['sender']['name']);
        $this->assertEquals('persona@example.com', $email1_t1['sender']['email']);
        $this->assertCount(1, $email1_t1['recipients']);
        $this->assertEquals('Person B Profile', $email1_t1['recipients'][0]['name']);
        $this->assertEquals('personb@example.com', $email1_t1['recipients'][0]['email']);
        $this->assertEquals('to', $email1_t1['recipients'][0]['type']);
        $this->assertEquals('Email 1 Subject A->B', $email1_t1['subject']);
        $this->assertStringContainsString('Body of Email 1', $email1_t1['body_text']);

        $this->assertEquals(self::EMAIL_2_ID, $email2_t1['id']);
        $this->assertEquals('Person B Profile', $email2_t1['sender']['name']);
        $this->assertEquals('personb@example.com', $email2_t1['sender']['email']);
        $this->assertCount(1, $email2_t1['recipients']);
        $this->assertEquals('Person A Profile', $email2_t1['recipients'][0]['name']);
        $this->assertEquals('persona@example.com', $email2_t1['recipients'][0]['email']);
        $this->assertEquals('to', $email2_t1['recipients'][0]['type']);
        $this->assertEquals('Email 2 Subject B->A', $email2_t1['subject']);
        $this->assertStringContainsString('Body of Email 2', $email2_t1['body_text']);

        // Assertions for Thread 2
        $this->assertEquals(self::THREAD_2_ID, $thread2['id']);
        $this->assertEquals('Thread Subject 2 Profile', $thread2['subject']);
        $this->assertCount(1, $thread2['emails'], 'Thread 2 should have 1 email');
        $email1_t2 = $thread2['emails'][0];

        $this->assertEquals(self::EMAIL_3_ID, $email1_t2['id']);
        $this->assertEquals('Person A Profile', $email1_t2['sender']['name']);
        $this->assertEquals('persona@example.com', $email1_t2['sender']['email']);
        $this->assertCount(1, $email1_t2['recipients']);
        $this->assertEquals('Person B Profile', $email1_t2['recipients'][0]['name']);
        $this->assertEquals('personb@example.com', $email1_t2['recipients'][0]['email']);
        $this->assertEquals('to', $email1_t2['recipients'][0]['type']);
        $this->assertEquals('Email 3 Subject A->B', $email1_t2['subject']);
        $this->assertStringContainsString('Body of Email 3', $email1_t2['body_text']);
    }

    public function testGetProfileNoCorrespondence()
    {
        // 1. Setup
        $this->seedPerson(self::PERSON_C_ID, 'Person C No Threads');
        $this->seedEmailAddress(self::EA_C_ID, self::PERSON_C_ID, 'personc@example.com', true);
        // No users, threads or emails needed for Person C

        // 2. Action
        $response = $this->executeApiScript('get_profile.php', 'GET', ['person_id' => self::PERSON_C_ID]);

        // 3. Assertions
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['body']['status']);
        $data = $response['body']['data'];
        $this->assertEquals(self::PERSON_C_ID, $data['id']);
        $this->assertEquals('Person C No Threads', $data['name']);
        $this->assertIsArray($data['threads']);
        $this->assertEmpty($data['threads'], 'Threads array should be empty for Person C');
    }

    public function testGetProfilePersonNotFound()
    {
        // 1. Action
        $response = $this->executeApiScript('get_profile.php', 'GET', ['person_id' => 'psn_non_existent_id']);

        // 2. Assertions
        $this->assertEquals(404, $response['code']);
        $this->assertEquals('error', $response['body']['status']);
        $this->assertEquals('Profile not found.', $response['body']['message']);
    }

    public function testGetProfileMissingPersonId()
    {
        // 1. Action
        $response = $this->executeApiScript('get_profile.php', 'GET', []); // No person_id

        // 2. Assertions
        $this->assertEquals(400, $response['code']);
        $this->assertEquals('error', $response['body']['status']);
        $this->assertEquals('Person ID is required.', $response['body']['message']);
    }
}

?>
