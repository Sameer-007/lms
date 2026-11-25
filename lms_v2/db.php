<?php
/**
 * Single Database Connection for LMS v2
 * Configured with environment variable overrides and PDO singleton.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = getenv('DB_HOST') ?: '193.203.184.248';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'u457767862_lms';
    $user = getenv('DB_USER') ?: 'u457767862_lms';
    $pass = getenv('DB_PASS') ?: 'Sajja@Sri@123';

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// Expose $pdo globally for scripts expecting a direct $pdo variable
$pdo = db();

function get_recursive_id(): string
{
    return "(SELECT folder_id FROM full_path WHERE parent_folder_id = :parent)";
}
