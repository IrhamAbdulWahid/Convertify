<?php
define('APP_RUNNING', true);
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    app_error('Permintaan penghapusan tidak valid. Muat ulang halaman dan coba lagi.', 403);
}
$token = $_POST['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    app_error('File tidak ditemukan.', 404);
}
try {
    $pdo = get_db_connection();
    $statement = $pdo->prepare('SELECT target_format FROM conversion_jobs WHERE job_token = :token LIMIT 1');
    $statement->execute(['token' => $token]);
    $job = $statement->fetch();
    if ($job && preg_match('/^(jpg|png|webp|bmp|gif|pdf|docx|xlsx|pptx|zip)$/', (string) $job['target_format'])) {
        $path = OUTPUT_DIR . DIRECTORY_SEPARATOR . $token . '.' . $job['target_format'];
        if (is_file($path)) {
            unlink($path);
        }
        $delete = $pdo->prepare('DELETE FROM conversion_jobs WHERE job_token = :token');
        $delete->execute(['token' => $token]);
    }
    json_response(['success' => true]);
} catch (Throwable $exception) {
    app_log($exception);
    app_error('File belum dapat dihapus. Coba lagi nanti.', 503);
}
