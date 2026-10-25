<?php
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

define('APP_NAME', 'Convertify');
define('APP_TAGLINE', 'Konversi dan kompres file dengan mudah.');
define('APP_VERSION', '1.1.0');
define('APP_DEBUG', false);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
define('MAX_UPLOAD_FILES', 10);
define('MAX_DOCUMENT_OUTPUT_SIZE', 100 * 1024 * 1024);
define('MAX_IMAGE_PIXELS', 10000000);
define('JOB_LIFETIME', 24 * 60 * 60);
define('RATE_LIMIT_MAX_REQUEST', 20);
define('RATE_LIMIT_WINDOW', 60);

$projectRoot = realpath(dirname(__DIR__));
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$baseUrl = '';
if ($projectRoot !== false && $documentRoot !== false) {
    $rootPrefix = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (stripos($projectRoot, $rootPrefix) === 0) {
        $relativePath = substr($projectRoot, strlen($rootPrefix));
        $baseUrl = '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    } elseif (strcasecmp($projectRoot, $documentRoot) !== 0) {
        throw new RuntimeException('Project directory must be inside the configured document root.');
    }
}
define('APP_BASE_URL', rtrim($baseUrl, '/'));
$storageParent = $documentRoot !== false ? dirname($documentRoot) : dirname(__DIR__);
define('STORAGE_ROOT', $storageParent . DIRECTORY_SEPARATOR . 'convertify-storage');
define('TEMP_DIR', STORAGE_ROOT . DIRECTORY_SEPARATOR . 'temp');
define('OUTPUT_DIR', STORAGE_ROOT . DIRECTORY_SEPARATOR . 'output');

date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function check_rate_limit(string $key): bool
{
    $now = time();
    $bucketKey = 'rate_' . $key;
    $bucket = $_SESSION[$bucketKey] ?? ['count' => 0, 'start' => $now];

    if (!isset($_SESSION[$bucketKey]) || !is_array($bucket) || !isset($bucket['count'], $bucket['start']) || $now - (int) $bucket['start'] >= RATE_LIMIT_WINDOW) {
        $_SESSION[$bucketKey] = ['count' => 1, 'start' => $now];
        return true;
    }
    if ((int) $bucket['count'] >= RATE_LIMIT_MAX_REQUEST) {
        return false;
    }
    $_SESSION[$bucketKey]['count']++;
    return true;
}

function format_file_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function app_error(string $message, int $status = 400): never
{
    json_response(['success' => false, 'message' => $message], $status);
}

function app_log(Throwable $exception): void
{
    error_log(sprintf('[%s] %s in %s:%d', APP_NAME, $exception->getMessage(), $exception->getFile(), $exception->getLine()));
}
