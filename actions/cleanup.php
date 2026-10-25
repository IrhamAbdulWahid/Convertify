<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = getenv('CONVERTIFY_DOCUMENT_ROOT') ?: dirname(dirname(__DIR__));
}
define('APP_RUNNING', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'FileCleanup.php';

try {
    FileCleanup::removeExpiredJobs(get_db_connection());
    fwrite(STDOUT, "Convertify cleanup completed.\n");
} catch (Throwable $exception) {
    app_log($exception);
    fwrite(STDERR, "Convertify cleanup failed. Check the PHP error log and database configuration.\n");
    exit(1);
}
