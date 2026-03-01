<?php
/**
 * PHPUnit Bootstrap file
 *
 * Sets up an in-memory SQLite database that mirrors the Wallos schema so unit tests can run without depending on a live database file
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load stats_calculations.php function definitions only.
// The file contains inline code after the functions that requires database context, so only the function definitions is extracted
if (!function_exists('getPricePerMonth')) {
    $source = file_get_contents(__DIR__ . '/../includes/stats_calculations.php');
    $marker = strpos($source, '// Get categories');
    if ($marker !== false) {
        $functionsOnly = substr($source, 0, $marker);
    } else {
        $functionsOnly = $source;
    }
    $tempFile = tempnam(sys_get_temp_dir(), 'wallos_test_');
    file_put_contents($tempFile, $functionsOnly);
    require_once $tempFile;
    unlink($tempFile);
}

 // Creates an in-memory SQLite3 database with the currencies table.
 
function createTestDatabase(): SQLite3
{
    $db = new SQLite3(':memory:');
    $db->enableExceptions(true);

    $db->exec("
        CREATE TABLE currencies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            symbol TEXT NOT NULL,
            code TEXT NOT NULL,
            rate REAL NOT NULL DEFAULT 1.0,
            user_id INTEGER NOT NULL DEFAULT 1
        )
    ");

    $db->exec("INSERT INTO currencies (id, name, symbol, code, rate, user_id) VALUES (1, 'US Dollar', '$', 'USD', 1.0, 1)");
    $db->exec("INSERT INTO currencies (id, name, symbol, code, rate, user_id) VALUES (2, 'Euro', '€', 'EUR', 0.85, 1)");
    $db->exec("INSERT INTO currencies (id, name, symbol, code, rate, user_id) VALUES (3, 'British Pound', '£', 'GBP', 0.73, 1)");
    $db->exec("INSERT INTO currencies (id, name, symbol, code, rate, user_id) VALUES (4, 'Japanese Yen', '¥', 'JPY', 149.50, 1)");

    return $db;
}
