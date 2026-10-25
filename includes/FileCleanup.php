<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

final class FileCleanup
{
    public static function removeExpiredJobs(PDO $pdo): void
    {
        $statement = $pdo->query('SELECT job_token, target_format FROM conversion_jobs WHERE expires_at <= CURRENT_TIMESTAMP');
        foreach ($statement->fetchAll() as $job) {
            if (preg_match('/^[a-f0-9]{64}$/', (string) $job['job_token'])) {
                $format = preg_replace('/[^a-z0-9]/', '', strtolower((string) $job['target_format']));
                if (in_array($format, ['jpg', 'png', 'webp', 'bmp', 'gif', 'pdf', 'docx', 'xlsx', 'pptx', 'zip'], true)) {
                    $path = OUTPUT_DIR . DIRECTORY_SEPARATOR . $job['job_token'] . '.' . $format;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        }
        $pdo->exec('DELETE FROM conversion_jobs WHERE expires_at <= CURRENT_TIMESTAMP');
        self::removeOrphanedFiles();
    }

    public static function removeOrphanedFiles(): void
    {
        $cutoff = time() - JOB_LIFETIME;
        foreach ([OUTPUT_DIR, TEMP_DIR] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            foreach (new DirectoryIterator($directory) as $file) {
                $name = $file->getFilename();
                $temporaryDocument = $directory === TEMP_DIR
                    && $file->isDir()
                    && preg_match('/^document-[a-f0-9]{32}$/', $name);
                if ($temporaryDocument && $file->getMTime() < time() - 3600) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($file->getPathname(), FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($iterator as $item) {
                        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
                    }
                    rmdir($file->getPathname());
                    continue;
                }
                $temporaryUpload = $directory === TEMP_DIR && preg_match('/^[a-f0-9]{32}\.upload$/', $name);
                $expiredOutput = $directory === OUTPUT_DIR;
                $fileCutoff = $temporaryUpload ? time() - 3600 : $cutoff;
                if ($file->isFile() && !$file->isDot() && ($temporaryUpload || $expiredOutput) && $file->getMTime() < $fileCutoff) {
                    unlink($file->getPathname());
                }
            }
        }
    }
}
