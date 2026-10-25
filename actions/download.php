<?php
define('APP_RUNNING', true);
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/FileCleanup.php';

$token = $_GET['token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('File tidak ditemukan atau sudah kedaluwarsa.');
}

try {
    $pdo = get_db_connection();
    FileCleanup::removeExpiredJobs($pdo);
    $statement = $pdo->prepare(
        'SELECT target_format, download_name FROM conversion_jobs
         WHERE job_token = :token AND status = :status AND expires_at > CURRENT_TIMESTAMP LIMIT 1'
    );
    $statement->execute(['token' => $token, 'status' => 'completed']);
    $job = $statement->fetch();
    if (!$job || !preg_match('/^(jpg|png|webp|bmp|gif|pdf|docx|xlsx|pptx|zip)$/', (string) $job['target_format'])) {
        http_response_code(404);
        exit('File tidak ditemukan atau sudah kedaluwarsa.');
    }
    $path = OUTPUT_DIR . DIRECTORY_SEPARATOR . $token . '.' . $job['target_format'];
    $realPath = realpath($path);
    $realOutputDirectory = realpath(OUTPUT_DIR);
    if ($realPath === false || $realOutputDirectory === false || dirname($realPath) !== $realOutputDirectory || !is_file($realPath)) {
        http_response_code(404);
        exit('File tidak ditemukan atau sudah kedaluwarsa.');
    }
    $downloadName = preg_replace('/[\r\n"\\\\]/', '_', (string) $job['download_name']) ?? 'convertify-file';
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
    ];
    header('Content-Type: ' . $mimeTypes[$job['target_format']]);
    header('Content-Length: ' . filesize($realPath));
    header('Content-Disposition: attachment; filename="' . addcslashes($downloadName, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($realPath);
    exit;
} catch (Throwable $exception) {
    app_log($exception);
    http_response_code(503);
    exit('Unduhan belum tersedia. Silakan coba lagi nanti.');
}
