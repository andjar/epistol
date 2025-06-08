<?php

// Define the path to the SQLite database file.
// It's placed in the 'storage' directory to keep writable files separate.
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../storage/database.sqlite');
}

// Define other configurations if needed, for example:
// define('DEBUG_MODE', true);
// define('LOG_PATH', __DIR__ . '/../storage/logs/app.log');

?>
