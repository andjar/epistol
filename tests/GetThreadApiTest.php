<?php

use PHPUnit\Framework\TestCase;

class GetThreadApiTest extends TestCase
{
    private static $pdo;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../src/db.php';
        require_once __DIR__ . '/../db/inject_test_data.php';

        if (defined('DB_PATH')) {
            if (file_exists(DB_PATH)) {
                unlink(DB_PATH);
            }
            self::$pdo = get_db_connection(); // Creates schema & injects initial data
        } else {
            throw new \Exception("DB_PATH is not defined.");
        }
    }

    protected function setUp(): void
    {
        self::$pdo->exec("DELETE FROM post_statuses;");
        // Assumed data from inject_test_data.php:
        // User 1 (id=1, person_id='person_user1_id'), User 2 (id=2, person_id='person_user2_id')
        // Thread 1 (id=1) contains Email 1 (id=1, from person_user1_id) and Email 2 (id=2, from person_user2_id)
        // Thread 2 (id=2) contains Email 3 (id=3, from person_user1_id)
    }

    private function makeApiCall(array $params)
    {
        $_GET = $params;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        require __DIR__ . '/../api/get_thread.php';
        return ob_get_clean();
    }

    public function testMissingThreadIdParameter()
    {
        $output = $this->makeApiCall(['user_id' => 1]);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Valid thread_id is required.', $response['error']);
    }

    public function testMissingUserIdParameter()
    {
        $output = $this->makeApiCall(['thread_id' => 'thread_1']); // Assuming thread_1 is a valid ID from test data
        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Invalid or missing user_id. User ID must be a positive integer.', $response['error']);
    }

    public function testThreadNotFound()
    {
        $output = $this->makeApiCall(['thread_id' => 'non_existent_thread_id_12345', 'user_id' => 1]);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Thread not found.', $response['error']);
    }

    public function testPostStatusPresenceAndDefaultInThread()
    {
        // Fetch Thread 1 for User 1. Email 1 and Email 2 are in this thread.
        // Email 1 is by user 1 (person_user1_id), Email 2 by user 2 (person_user2_id)
        // User 1 is viewing.
        $output = $this->makeApiCall(['thread_id' => 'thread1_id', 'user_id' => 1]); // Assuming thread1_id exists
        $response = json_decode($output, true);

        $this->assertArrayHasKey('data', $response, "Response data missing: $output");
        $this->assertEquals('thread1_id', $response['data']['id']);
        $this->assertNotEmpty($response['data']['emails']);

        foreach ($response['data']['emails'] as $email) {
            $this->assertArrayHasKey('status', $email, "Email ID {$email['id']} missing status field.");
            // All emails in the thread should show a status relevant to user_id 1.
            // If no status is set for email_id X for user_id 1, it defaults to 'unread'.
            $this->assertEquals('unread', $email['status'], "Email ID {$email['id']} should default to unread for user 1.");
        }
    }

    public function testSpecificPostStatusInThreadForViewingUser()
    {
        // Set status for Email 1 (id=1) to 'read' for User 1 (id=1)
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 1, 'read')");
        // Set status for Email 2 (id=2) to 'follow-up' for User 1 (id=1)
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (2, 1, 'follow-up')");

        // User 1 views Thread 1 (which contains Email 1 and Email 2)
        $output = $this->makeApiCall(['thread_id' => 'thread1_id', 'user_id' => 1]);
        $response = json_decode($output, true);

        $this->assertArrayHasKey('data', $response);
        $this->assertNotEmpty($response['data']['emails']);

        $statusesFound = [];
        foreach ($response['data']['emails'] as $email) {
            $statusesFound[$email['id']] = $email['status'];
        }

        $this->assertEquals('read', $statusesFound[1], "Email 1 should be 'read' for user 1.");
        $this->assertEquals('follow-up', $statusesFound[2], "Email 2 should be 'follow-up' for user 1.");
    }

    public function testStatusInThreadForDifferentUser()
    {
        // User 1 marks Email 1 (id=1) as 'important-info'
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 1, 'important-info')");
        // User 2 marks Email 1 (id=1) as 'read'
        self::$pdo->exec("INSERT INTO post_statuses (post_id, user_id, status) VALUES (1, 2, 'read')");

        // User 2 views Thread 1 (which contains Email 1)
        $output = $this->makeApiCall(['thread_id' => 'thread1_id', 'user_id' => 2]);
        $response = json_decode($output, true);
        $this->assertArrayHasKey('data', $response);
        $email1StatusForUser2 = null;
        foreach ($response['data']['emails'] as $email) {
            if ($email['id'] == 1) {
                $email1StatusForUser2 = $email['status'];
                break;
            }
        }
        $this->assertEquals('read', $email1StatusForUser2, "Email 1 should be 'read' for user 2.");

        // User 1 views Thread 1
        $outputUser1 = $this->makeApiCall(['thread_id' => 'thread1_id', 'user_id' => 1]);
        $responseUser1 = json_decode($outputUser1, true);
        $this->assertArrayHasKey('data', $responseUser1);
        $email1StatusForUser1 = null;
        foreach ($responseUser1['data']['emails'] as $email) {
            if ($email['id'] == 1) {
                $email1StatusForUser1 = $email['status'];
                break;
            }
        }
        $this->assertEquals('important-info', $email1StatusForUser1, "Email 1 should be 'important-info' for user 1.");
    }
}
?>
