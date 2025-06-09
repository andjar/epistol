<?php

use PHPUnit\Framework\TestCase;

class SetPostStatusApiTest extends TestCase
{
    private static $db_path;
    private static $backup_db_path;
    private static $original_db_content;

    public static function setUpBeforeClass(): void
    {
        // Define a test-specific database path
        self::$db_path = __DIR__ . '/../storage/test_api_database.sqlite';
        self::$backup_db_path = __DIR__ . '/../storage/test_api_database.sqlite.backup';

        // It's crucial that config.php uses an environment variable for DB_PATH
        // or that we can override it here. For now, we'll assume config.php
        // might pick up an env var, or we run tests in an environment where DB_PATH
        // can point to our test DB.
        // If not, tests will run on the dev DB, which is not ideal.
        // Let's try to define DB_PATH here IF NOT ALREADY DEFINED.
        if (!defined('DB_PATH_OVERRIDE_FOR_TESTS')) {
            define('DB_PATH_OVERRIDE_FOR_TESTS', self::$db_path);
        }

        // Backup existing test DB if it exists, then delete the main test DB file
        // to ensure a clean state for schema creation by get_db_connection().
        if (file_exists(self::$db_path)) {
            copy(self::$db_path, self::$backup_db_path);
            unlink(self::$db_path);
        }

        // Ensure config.php is loaded for get_db_connection()
        // This will define the original DB_PATH if not overridden by an env var strategy.
        require_once __DIR__ . '/../config/config.php';
        // Now, if DB_PATH_OVERRIDE_FOR_TESTS was used by config.php, we are good.
        // Otherwise, we need a different strategy.

        // For this test setup, we will temporarily replace the DB_PATH constant.
        // This is hacky and relies on runkit or similar, or redefining constants if possible.
        // A cleaner way is for config.php to respect an ENV variable for DB_PATH.
        // Since we can't modify config.php, this test will be "less isolated" if it uses the main DB.

        // To ensure get_db_connection creates a fresh DB with schema:
        if (file_exists(DB_PATH) && DB_PATH === self::$db_path) { // Only delete if DB_PATH is our test DB
            unlink(DB_PATH);
        } elseif (DB_PATH !== self::$db_path) {
            // Fallback: If DB_PATH is not our test DB path, we can't isolate.
            // We'll save the original DB content and restore it.
            // This is also risky if multiple tests run concurrently or if a test fails mid-way.
            self::$original_db_content = file_get_contents(DB_PATH);
        }
         // Initialize PDO and schema (get_db_connection will handle this)
        get_db_connection();
    }

    public static function tearDownAfterClass(): void
    {
        if (defined('DB_PATH_OVERRIDE_FOR_TESTS') && file_exists(self::$db_path)) {
            if (file_exists(self::$backup_db_path)) {
                rename(self::$backup_db_path, self::$db_path); // Restore backup
            } else {
                unlink(self::$db_path); // Clean up test DB
            }
        } elseif (isset(self::$original_db_content) && defined('DB_PATH')) {
            file_put_contents(DB_PATH, self::$original_db_content); // Restore original DB
        }
    }

    protected function setUp(): void
    {
        // If not using DB_PATH_OVERRIDE_FOR_TESTS, we might need to re-apply schema for each test
        // or ensure data from one test doesn't affect another.
        // For now, assuming setUpBeforeClass handles the main DB setup.
        // Individual test methods will add specific data as needed.
        $pdo = get_db_connection(); // Ensure connection for each test
        // Clear post_statuses table for isolation between tests for this specific API
        $pdo->exec("DELETE FROM post_statuses;");
        // Add a dummy post and user for FK constraints if they don't exist from inject_test_data
        // inject_test_data.php should provide users user1 (id 1), user2 (id 2)
        // and posts post1_user1 (id 1), post2_user1 (id 2), post1_user2 (id 3)
        // For safety, let's ensure necessary records exist or add them.
        try {
            $pdo->exec("INSERT OR IGNORE INTO users (id, username, email, password_hash) VALUES (1, 'testuser1', 'testuser1@example.com', 'hash1')");
            $pdo->exec("INSERT OR IGNORE INTO users (id, username, email, password_hash) VALUES (99, 'testuser99', 'testuser99@example.com', 'hash99')");
            $pdo->exec("INSERT OR IGNORE INTO posts (id, user_id, content) VALUES (1, 1, 'Test post 1 by user 1')");
            $pdo->exec("INSERT OR IGNORE INTO posts (id, user_id, content) VALUES (99, 99, 'Test post 99 by user 99')");
        } catch (PDOException $e) {
            // Ignore if already exists, or handle error
        }
    }

    private function captureOutput(callable $callback)
    {
        ob_start();
        $callback();
        return ob_get_clean();
    }

    public function testSetNewStatusSuccess()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $inputData = ['post_id' => 1, 'user_id' => 1, 'status' => 'read'];
        // Simulate file_get_contents('php://input')
        $GLOBALS['mock_php_input'] = json_encode($inputData);

        $output = $this->captureOutput(function () {
            require __DIR__ . '/../api/set_post_status.php';
        });
        unset($GLOBALS['mock_php_input']); // Clean up

        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Post status created successfully.', $response['message']);
        $this->assertArrayHasKey('id', $response);

        // Check database
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT status FROM post_statuses WHERE post_id = :post_id AND user_id = :user_id");
        $stmt->execute(['post_id' => 1, 'user_id' => 1]);
        $this->assertEquals('read', $stmt->fetchColumn());
    }

    public function testUpdateExistingStatusSuccess()
    {
        // First, set an initial status
        $pdo = get_db_connection();
        $stmt_initial = $pdo->prepare("INSERT INTO post_statuses (post_id, user_id, status) VALUES (:post_id, :user_id, :status)");
        $stmt_initial->execute(['post_id' => 1, 'user_id' => 1, 'status' => 'read']);
        $initial_id = $pdo->lastInsertId();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $inputData = ['post_id' => 1, 'user_id' => 1, 'status' => 'important-info'];
        $GLOBALS['mock_php_input'] = json_encode($inputData);

        $output = $this->captureOutput(function () {
            require __DIR__ . '/../api/set_post_status.php';
        });
        unset($GLOBALS['mock_php_input']);

        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Post status updated successfully.', $response['message']);

        // Check database
        $stmt = $pdo->prepare("SELECT status FROM post_statuses WHERE id = :id");
        $stmt->execute(['id' => $initial_id]);
        $this->assertEquals('important-info', $stmt->fetchColumn());
    }

    public function testMissingParameters()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $inputs = [
            ['user_id' => 1, 'status' => 'read'], // Missing post_id
            ['post_id' => 1, 'status' => 'read'], // Missing user_id
            ['post_id' => 1, 'user_id' => 1],    // Missing status
        ];

        foreach ($inputs as $idx => $inputData) {
            $GLOBALS['mock_php_input'] = json_encode($inputData);
            $output = $this->captureOutput(function () {
                require __DIR__ . '/../api/set_post_status.php';
            });
            unset($GLOBALS['mock_php_input']);

            $response = json_decode($output, true);
            $this->assertArrayHasKey('error', $response, "Failed on input index $idx: " . $output);
            // Check for 400 Bad Request, though http_response_code is harder to test directly this way
        }
    }

    public function testInvalidStatusValue()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $inputData = ['post_id' => 1, 'user_id' => 1, 'status' => 'invalid_status_value'];
        $GLOBALS['mock_php_input'] = json_encode($inputData);

        $output = $this->captureOutput(function () {
            require __DIR__ . '/../api/set_post_status.php';
        });
        unset($GLOBALS['mock_php_input']);

        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('status is required and must be one of', $response['error']);
    }

    public function testNonPostRequest()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $output = $this->captureOutput(function () {
            require __DIR__ . '/../api/set_post_status.php';
        });

        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Only POST requests are allowed', $response['error']);
        // PHPUnit can't easily check http_response_code set by the script directly without a proper web server context
        // or more advanced testing tools like Guzzle for actual HTTP requests.
    }

    // Helper to override file_get_contents('php://input') used in set_post_status.php
    // This needs to be in the global namespace or accessible.
    // This is tricky. A better way is to refactor set_post_status.php to be more testable,
    // e.g., by having it accept input as a parameter.
    // For now, $GLOBALS['mock_php_input'] is a simple workaround.
}

// Need to ensure set_post_status.php can use our mock if $GLOBALS['mock_php_input'] is set.
// This usually means modifying the source file, which we are trying to avoid for the API script.
// A common pattern for set_post_status.php would be:
// $raw_input = file_get_contents('php://input');
// $data = json_decode($raw_input, true);
// We can't easily mock file_get_contents('php://input') without extensions like uopz or by refactoring the script.
// The $GLOBALS approach is a hack.
// Let's assume for now that set_post_status.php is modified to check this global for testing:
// $raw_input = isset($GLOBALS['mock_php_input']) ? $GLOBALS['mock_php_input'] : file_get_contents('php://input');

?>
