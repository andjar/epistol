<?php

// Define the path to the SQLite database file.
// It's placed in the 'storage' directory to keep writable files separate.
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../storage/database.sqlite');
}

// Define other configurations if needed, for example:
// define('DEBUG_MODE', true);
// define('LOG_PATH', __DIR__ . '/../storage/logs/app.log');

// SMTP Configuration (Placeholder values)
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.example.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587); // Common for TLS
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'user@example.com');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', 'smtp_password');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls'); // 'ssl' or 'tls'
}

// Application Specific Configuration
if (!defined('APP_DOMAIN')) {
    define('APP_DOMAIN', 'epistol.example.com'); // Used for Message-ID header
}
if (!defined('DEFAULT_USER_PERSON_ID')) {
    // In a real app, this would come from an auth system.
    // For now, a fixed UUID or identifier for the default/system user sending the email.
    define('DEFAULT_USER_PERSON_ID', 'person_01HXZC8W05101S5YZXG5J8Y0R8'); // Example fixed ID
}
if (!defined('DEFAULT_SENDER_EMAIL')) {
    // This should be an email address associated with DEFAULT_USER_PERSON_ID
    // or a general 'noreply' address for the application.
    define('DEFAULT_SENDER_EMAIL', 'noreply@epistol.example.com');
}

// Storage Configuration
if (!defined('STORAGE_PATH_ATTACHMENTS')) {
    // Ensure this directory exists and is writable by the web server.
    define('STORAGE_PATH_ATTACHMENTS', __DIR__ . '/../storage/attachments');
}

?>
