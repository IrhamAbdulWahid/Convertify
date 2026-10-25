<?php
define('APP_RUNNING', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'FileCleanup.php';

$token = $_GET['token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit;
}

try {
    $pdo = get_db_connection();
    FileCleanup::removeExpiredJobs($pdo);
    $statement = $pdo->prepare(
        'SELECT target_format FROM conversion_jobs
         WHERE job_token = :token AND status = :status AND expires_at > CURRENT_TIMESTAMP LIMIT 1'
    );
    $statement->execute(['token' => $token, 'status' => 'completed']);
    $job = $statement->fetch();
    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'bmp' => 'image/bmp', 'gif' => 'image/gif'];
    if (!$job || !isset($mimeTypes[$job['target_format']])) {
        http_response_code(404);
        exit;
    }
    $path = OUTPUT_DIR . DIRECTORY_SEPARATOR . $token . '.' . $job['target_format'];
    $realPath = realpath($path);
    $realOutputDirectory = realpath(OUTPUT_DIR);
    if ($realPath === false || $realOutputDirectory === false || dirname($realPath) !== $realOutputDirectory || !is_file($realPath)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mimeTypes[$job['target_format']]);
    header('Content-Length: ' . filesize($realPath));
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($realPath);
} catch (Throwable $exception) {
    app_log($exception);
    http_response_code(503);
}
