<?php

use PHPUnit\Framework\TestCase;

// Define constants if not already defined (e.g., in a bootstrap or phpunit.xml)
if (!defined('SENDER_USER_ID')) {
    define('SENDER_USER_ID', 1); // Default test sender
}
if (!defined('APP_DOMAIN')) {
    define('APP_DOMAIN', 'test.example.com');
}
if (!defined('DEFAULT_SENDER_EMAIL')) {
    define('DEFAULT_SENDER_EMAIL', 'noreply@test.example.com');
}
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 1025); // Mailhog/MailCatcher
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', ''); // 'tls', 'ssl', or empty

abstract class BaseApiTest extends TestCase
{
    protected static $pdo = null;
    protected $backupGlobalsBlacklist = ['user_defined_constants'];


    public static function setUpBeforeClass(): void
    {
        // Ensure config.php is loaded for DB_PATH etc.
        // Adjust path as necessary if tests are run from a different working directory.
        $configPath = __DIR__ . '/../config/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        } else {
            // Fallback or error if config isn't found - essential for DB_PATH
            // This might indicate tests are being run from an unexpected directory
            // For now, assume DB_PATH will be defined globally or via phpunit.xml bootstrap
            if(!defined('DB_PATH')) define('DB_PATH', __DIR__ . '/../db/app.db'); // Default for safety
        }

        if (self::$pdo === null) {
            try {
                // Use the get_db_connection function from db.php
                require_once __DIR__ . '/../src/db.php';
                self::$pdo = get_db_connection();
            } catch (Exception $e) {
                // Log or handle connection error
                echo "Database connection failed in test setup: " . $e->getMessage();
                exit(1); // Stop tests if DB connection fails
            }
        }
        // Consider running db/inject_test_data.php or a similar script here
        // to ensure a consistent starting state for tests.
        // For now, manual setup per test or test class.
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo = null; // Close connection
    }

    protected function executeApiScript(string $scriptPath, string $method, array $getData = [], array $postDataJson = [])
    {
        // Backup superglobals
        $backupGet = $_GET;
        $backupPost = $_POST;
        $backupServer = $_SERVER;
        $backupFiles = $_FILES; // For file uploads, if any endpoint uses it

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_GET = $getData;

        $inputStream = null;
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            if (!empty($postDataJson)) {
                $inputStream = 'php://temp'; // Use temp stream for php://input
                file_put_contents($inputStream, json_encode($postDataJson));
                // The 'php://input' stream needs to be rewinded before it's read by the script
                // However, direct inclusion does not re-open it. Need to mock file_get_contents('php://input') if used.
                // For simplicity, if scripts use json_decode(file_get_contents('php://input')), this approach is harder.
                // An alternative: set $_POST if it's form data, or handle JSON input within this helper.
                // For now, assuming scripts might use $_POST or can be adapted.
                // If scripts directly use file_get_contents('php://input'), tests need to be more advanced (e.g. overriding file_get_contents via a stream wrapper or mocking).
                // Let's assume for now that we can set $_POST for simple cases or the script is refactored.
                // For JSON body, it's more complex. A common pattern is to use a real HTTP client to call the local server.
                // Or, we can try to simulate it.

                // For this project, scripts use: json_decode(file_get_contents('php://input'), true);
                // So we need a way to mock file_get_contents or use a different approach.
                // A simpler way for testing is to have scripts check a global variable or a test-specific function for input.
                // Or, use a light wrapper around the script execution that sets up a stream.
            }
             // If testing file uploads, $_FILES would be populated here.
        }

        // Capture output
        ob_start();

        // This is a simplified way to "include" the script and simulate its execution context.
        // It has limitations, especially around global state and headers.
        // For php://input, we'll rely on a trick: defining a global var that the script can check in test mode.
        // Or, for this iteration, let's assume the script can be slightly modified for testability,
        // or we accept the limitation that file_get_contents('php://input') won't work directly this way.
        // Let's try to make it work by providing a custom stream wrapper if necessary, or by directly calling a main function if scripts are refactored.

        // A common pattern for testing legacy PHP applications:
        // 1. Create a test bootstrap that defines a function for file_get_contents
        // 2. In tests, override this function.

        // For now, let's try a direct include and see which scripts fail.
        // For `send_email.php` it uses `file_get_contents('php://input')`.
        // We'll need a better solution for that.
        // Let's assume we'll pass $postDataJson to the script via a global or a modified include.

        global $mock_php_input_data; // Used by the script in test mode
        if (!empty($postDataJson)) {
            $mock_php_input_data = json_encode($postDataJson);
        } else {
            $mock_php_input_data = null;
        }

        $scriptFullPath = __DIR__ . '/../api/v1/' . $scriptPath;
        if (!file_exists($scriptFullPath)) {
            ob_end_clean();
            throw new Exception("API script not found: {$scriptFullPath}");
        }

        // Store current http_response_code to restore it later, as PHPUnit might run multiple tests
        $original_response_code = http_response_code();

        try {
            include $scriptFullPath;
        } catch (Exception $e) {
            // Catch exceptions from the script itself to allow cleanup
            error_log("Exception during script execution in test: " . $e->getMessage());
        }

        $output = ob_get_clean();
        $response_code = http_response_code(); // Get the response code set by the script

        // Restore http_response_code to what it was before this script ran,
        // or to 200 if it was at a default/false state.
        http_response_code($original_response_code ?: 200);


        // Restore superglobals
        $_GET = $backupGet;
        $_POST = $backupPost;
        $_SERVER = $backupServer;
        $_FILES = $backupFiles;
        unset($GLOBALS['mock_php_input_data']);


        return [
            'body' => json_decode($output, true),
            'code' => $response_code,
            'raw_output' => $output
        ];
    }

    // Helper to get last inserted ID, specific to SQLite
    protected function getLastInsertId() {
        return self::$pdo->lastInsertId();
    }

    // DB helpers
    protected function fetchOne(string $sql, array $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function fetchAll(string $sql, array $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function executeSql(string $sql, array $params = []) {
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // Data cleanup helper (example)
    protected function cleanupUser(int $userId) {
        $this->executeSql("DELETE FROM users WHERE id = ?", [$userId]);
        // Add deletions from related tables if necessary (persons, email_addresses, etc.)
    }
    protected function cleanupThread(int $threadId) {
        $this->executeSql("DELETE FROM emails WHERE thread_id = ?", [$threadId]);
        $this->executeSql("DELETE FROM threads WHERE id = ?", [$threadId]);
        // Also consider email_statuses, attachments etc.
    }
     protected function cleanupEmail(int $emailId) {
        $this->executeSql("DELETE FROM email_statuses WHERE email_id = ?", [$emailId]);
        $this->executeSql("DELETE FROM attachments WHERE email_id = ?", [$emailId]);
        $this->executeSql("DELETE FROM email_recipients WHERE email_id = ?", [$emailId]);
        $this->executeSql("DELETE FROM emails WHERE id = ?", [$emailId]);
    }

    // Helper to create a user for testing
    protected function createUser(string $username, string $email, string $password = 'password'): int
    {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $this->executeSql(
            "INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, datetime('now'))",
            [$username, $email, $hashed_password]
        );
        return $this->getLastInsertId();
    }
}

// Modify API scripts to use $mock_php_input_data in test environment
// Example for a script like send_email.php:
/*
if (getenv('PHPUNIT_RUNNING') === 'true' && isset($GLOBALS['mock_php_input_data'])) {
    $input_data = json_decode($GLOBALS['mock_php_input_data'], true);
} else {
    $input_data = json_decode(file_get_contents('php://input'), true);
}
*/
// This requires setting PHPUNIT_RUNNING in phpunit.xml or bootstrap.
// <env name="PHPUNIT_RUNNING" value="true"/>

?>
