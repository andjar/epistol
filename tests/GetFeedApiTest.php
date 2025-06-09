<?php

use PHPUnit\Framework\TestCase;

// It's good practice to have a BaseTestCase that handles DB setup.
// For now, repeating some logic.
// require_once __DIR__ . '/BaseApiTestCase.php';

class GetFeedApiTest extends TestCase
{
    private static $pdo;

    public static function setUpBeforeClass(): void
    {
        // This setup assumes that config.php will be included by the API script
        // and DB_PATH will be defined. Tests run on this DB.
        // Ideally, use a separate test DB configuration.
        require_once __DIR__ . '/../config/config.php'; // To define DB_PATH etc.
        require_once __DIR__ . '/../src/db.php';      // For get_db_connection()
        require_once __DIR__ . '/../db/inject_test_data.php'; // For inject_initial_data

        // Ensure a clean state by deleting and recreating the DB
        if (defined('DB_PATH')) {
            if (file_exists(DB_PATH)) {
                unlink(DB_PATH);
            }
            self::$pdo = get_db_connection(); // This will create schema and run inject_initial_data
        } else {
            throw new \Exception("DB_PATH is not defined. Ensure config.php is loaded.");
        }
    }

    protected function setUp(): void
    {
        // Clear post_statuses before each test for this API
        self::$pdo->exec("DELETE FROM post_statuses;");
        // Data from inject_initial_data.php is assumed to be:
        // Users: user1 (id=1), user2 (id=2)
        // Emails: email1 (id=1, thread_id=1, from_person_id=1 (user1's person_id)), email2 (id=2, thread_id=1, from_person_id=2), email3 (id=3, thread_id=2, from_person_id=1)
        // Groups: group1 (id=1)
        // EmailGroups: email1 in group1, email2 in group1
        // We need to map users to person_ids based on inject_test_data.php
        // Let's assume person_id for user1 is 'person_user1_id' and for user2 is 'person_user2_id'
        // And post_id in post_statuses corresponds to email_id from the feed.
    }

    private function captureOutputAndHeaders(callable $callback, &$http_response_code) {
        ob_start();
        $callback();
        $output = ob_get_clean();
        // Note: Can't directly get http_response_code set by script without more complex setup.
        // We'll infer based on JSON error presence.
        return $output;
    }

    private function makeApiCall(array $params)
    {
        $_GET = $params;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        // $_SERVER['QUERY_STRING'] = http_build_query($params);

        return $this->captureOutputAndHeaders(function () {
            require __DIR__ . '/../api/get_feed.php';
        }, $http_response_code);
    }

    public function testMissingUserIdParameter()
    {
        $output = $this->makeApiCall([]); // No user_id
        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Invalid or missing user_id', $response['error']);
    }

    public function testPostStatusPresenceAndDefault()
    {
        // Assuming user1 (person_id from user1) has email1 (id=1) and email3 (id=3)
        // User ID 1 for these tests will correspond to the user who owns person_user1_id
        $output = $this->makeApiCall(['user_id' => 1]);
        $response = json_decode($output, true);

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('threads', $response['data']);
        $this->assertNotEmpty($response['data']['threads']);

        $foundEmail1 = false;
        $foundEmail3 = false;
        foreach ($response['data']['threads'] as $thread) {
            foreach ($thread['emails'] as $email) {
                $this->assertArrayHasKey('status', $email);
                $this->assertEquals('unread', $email['status'], "Email ID {$email['email_id']} should default to unread.");
                if ($email['email_id'] == 1) $foundEmail1 = true;
                if ($email['email_id'] == 3) $foundEmail3 = true;
            }
        }
        $this->assertTrue($foundEmail1, "Email 1 not found in feed for user 1.");
        $this->assertTrue($foundEmail3, "Email 3 not found in feed for user 1.");
    }

    public function testFilterByReadStatus()
    {
        // Set email1 to 'read' for user_id 1
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 1, 'read')");
        // email3 for user_id 1 remains unread

        $output = $this->makeApiCall(['user_id' => 1, 'status' => 'read']);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('data', $response);

        $emailsInResponse = [];
        if(isset($response['data']['threads'])) {
            foreach ($response['data']['threads'] as $thread) {
                foreach ($thread['emails'] as $email) {
                    $emailsInResponse[$email['email_id']] = $email;
                }
            }
        }

        $this->assertArrayHasKey(1, $emailsInResponse, "Email 1 (marked read) should be in the filtered response.");
        $this->assertEquals('read', $emailsInResponse[1]['status']);
        $this->assertArrayNotHasKey(3, $emailsInResponse, "Email 3 (unread) should NOT be in the 'read' filtered response.");
        // Also check that email 2 (belonging to user 2) is not there.
        $this->assertArrayNotHasKey(2, $emailsInResponse, "Email 2 (other user) should not be in user 1's feed.");
    }

    public function testFilterByUnreadStatus()
    {
        // email1 for user_id 1 is 'read'
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 1, 'read')");
        // email3 for user_id 1 has no entry in post_statuses, so it's 'unread' by default.
        // email_id 4 (for user 1) explicitly 'unread'
        self::$pdo->exec("INSERT OR IGNORE INTO emails (id, thread_id, from_person_id, subject, body_text, body_html, timestamp, snippet) VALUES (4, 2, 'person_user1_id', 'Subj4', 'Body4', '<p>Body4</p>', '2023-01-04 10:00:00', 'Snip4')");
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (4, 1, 'unread')");


        $output = $this->makeApiCall(['user_id' => 1, 'status' => 'unread']);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('data', $response);

        $emailsInResponse = [];
        if(isset($response['data']['threads'])) {
            foreach ($response['data']['threads'] as $thread) {
                foreach ($thread['emails'] as $email) {
                    $emailsInResponse[$email['email_id']] = $email;
                }
            }
        }

        $this->assertArrayNotHasKey(1, $emailsInResponse, "Email 1 (read) should not be in 'unread' filter.");
        $this->assertArrayHasKey(3, $emailsInResponse, "Email 3 (no status entry) should be in 'unread' filter.");
        $this->assertEquals('unread', $emailsInResponse[3]['status']);
        $this->assertArrayHasKey(4, $emailsInResponse, "Email 4 (explicitly unread) should be in 'unread' filter.");
        $this->assertEquals('unread', $emailsInResponse[4]['status']);
    }

    public function testFilterByFollowUpStatus()
    {
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 1, 'follow-up')");
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (3, 1, 'read')");

        $output = $this->makeApiCall(['user_id' => 1, 'status' => 'follow-up']);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('data', $response);

        $emailsInResponse = [];
        if(isset($response['data']['threads'])) {
            foreach ($response['data']['threads'] as $thread) {
                foreach ($thread['emails'] as $email) {
                    $emailsInResponse[$email['email_id']] = $email;
                }
            }
        }
        $this->assertArrayHasKey(1, $emailsInResponse, "Email 1 (follow-up) should be in the filtered response.");
        $this->assertEquals('follow-up', $emailsInResponse[1]['status']);
        $this->assertArrayNotHasKey(3, $emailsInResponse, "Email 3 (read) should NOT be in the 'follow-up' filtered response.");
    }

    public function testCombinedGroupAndStatusFilter()
    {
        // Assuming from inject_test_data.php:
        // Email 1 (id=1) is in group1 (group_id=1), by user1 (person_user1_id)
        // Email 3 (id=3) is NOT in any group, by user1
        // Email 5 (id=5, new) by user1, in group1
        self::$pdo->exec("INSERT OR IGNORE INTO emails (id, thread_id, from_person_id, subject, body_text, body_html, timestamp, snippet, group_id) VALUES (5, 2, 'person_user1_id', 'Subj5', 'Body5', '<p>Body5</p>', '2023-01-05 10:00:00', 'Snip5', 1)");

        // Statuses for user_id 1:
        // Email 1: read
        // Email 3: follow-up
        // Email 5: read
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 1, 'read')");
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (3, 1, 'follow-up')");
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (5, 1, 'read')");

        // Filter by group_id=1 AND status='read' for user_id=1
        $output = $this->makeApiCall(['user_id' => 1, 'group_id' => 1, 'status' => 'read']);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('data', $response);

        $emailsInResponse = [];
        $emailCount = 0;
        if(isset($response['data']['threads'])) {
            foreach ($response['data']['threads'] as $thread) {
                foreach ($thread['emails'] as $email) {
                    $emailsInResponse[$email['email_id']] = $email;
                    $emailCount++;
                }
            }
        }

        $this->assertArrayHasKey(1, $emailsInResponse, "Email 1 (group1, read) should be in response.");
        $this->assertEquals('read', $emailsInResponse[1]['status']);
        $this->assertArrayHasKey(5, $emailsInResponse, "Email 5 (group1, read) should be in response.");
        $this->assertEquals('read', $emailsInResponse[5]['status']);

        $this->assertArrayNotHasKey(3, $emailsInResponse, "Email 3 (no group, follow-up) should NOT be in response.");
        $this->assertEquals(2, $emailCount, "Should only be 2 emails matching group1 and status read for user 1.");
    }
}
?>
