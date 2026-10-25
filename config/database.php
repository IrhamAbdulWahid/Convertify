<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

define('DB_HOST', getenv('CONVERTIFY_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('CONVERTIFY_DB_NAME') ?: 'convertify_db');
define('DB_USER', getenv('CONVERTIFY_DB_USER') ?: 'root');
define('DB_PASS', getenv('CONVERTIFY_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

function get_db_connection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
