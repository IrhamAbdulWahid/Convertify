<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

require_once __DIR__ . '/FileValidator.php';
require_once __DIR__ . '/ImageConverter.php';
require_once __DIR__ . '/ImageCompressor.php';
require_once __DIR__ . '/FileCleanup.php';

function ensure_storage_directories(): void
{
    foreach ([STORAGE_ROOT, TEMP_DIR, OUTPUT_DIR] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Penyimpanan privat belum siap. Periksa izin folder penyimpanan Convertify.');
        }
    }
    if (!is_writable(TEMP_DIR) || !is_writable(OUTPUT_DIR)) {
        throw new RuntimeException('Penyimpanan privat tidak dapat ditulis. Periksa izin folder penyimpanan Convertify.');
    }
}

function acquire_processing_lock()
{
    ensure_storage_directories();
    for ($slot = 0; $slot < 3; $slot++) {
        $handle = fopen(TEMP_DIR . DIRECTORY_SEPARATOR . 'worker-' . $slot . '.lock', 'c');
        if ($handle !== false && flock($handle, LOCK_EX | LOCK_NB)) {
            return $handle;
        }
        if (is_resource($handle)) {
            fclose($handle);
        }
    }
    return false;
}

function normalize_uploaded_files(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [$files];
    }
    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $normalized;
}

function safe_download_name(string $originalName, string $suffix, string $extension): string
{
    $base = pathinfo(basename(str_replace('\\', '/', $originalName)), PATHINFO_FILENAME);
    $base = preg_replace('/[^\pL\pN._-]+/u', '-', $base) ?? '';
    $base = trim($base, '.-_');
    if ($base === '') {
        $base = 'file-convertify';
    }
    return mb_substr($base, 0, 150) . '_' . $suffix . '.' . $extension;
}

function process_image_job(PDO $pdo, array $file, string $operation, string $format, int $quality, string $background, ?int $maxDimension): array
{
    $validated = FileValidator::validateUpload($file);
    $formats = FileValidator::availableOutputFormats();
    if (!isset($formats[$format])) {
        throw new RuntimeException('Format output tidak tersedia di server ini.');
    }
    if ($operation === 'compress') {
        $format = $validated['format'];
        if (!isset($formats[$format])) {
            throw new RuntimeException('Format gambar ini tidak dapat dikompres oleh server.');
        }
    }

    ensure_storage_directories();
    $sourcePath = TEMP_DIR . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.upload';
    $token = bin2hex(random_bytes(32));
    $extension = $formats[$format]['extension'];
    $outputPath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $token . '.' . $extension;
    $sourceMoved = false;

    try {
        if (!move_uploaded_file($validated['tmp_name'], $sourcePath)) {
            throw new RuntimeException('Server gagal menyimpan file sementara. Silakan coba lagi.');
        }
        $sourceMoved = true;
        if ($operation === 'compress') {
            $smaller = ImageCompressor::compress($sourcePath, $outputPath, $format, $quality, $maxDimension);
        } else {
            ImageConverter::convert($sourcePath, $outputPath, $format, $quality, $background);
            $smaller = filesize($outputPath) < $validated['size'];
        }
        $outputSize = filesize($outputPath);
        if ($outputSize === false || $outputSize < 1) {
            throw new RuntimeException('File hasil kosong atau tidak dapat dibuat.');
        }

        $suffix = $operation === 'compress' ? 'dikompres' : 'dikonversi';
        $downloadName = safe_download_name($validated['name'], $suffix, $extension);
        $statement = $pdo->prepare(
            'INSERT INTO conversion_jobs (job_token, operation_type, source_format, target_format, status, original_size, output_size, download_name, created_at, expires_at)
             VALUES (:token, :operation, :source, :target, :status, :original_size, :output_size, :download_name, CURRENT_TIMESTAMP, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :lifetime SECOND))'
        );
        $statement->bindValue(':token', $token);
        $statement->bindValue(':operation', $operation);
        $statement->bindValue(':source', $validated['format']);
        $statement->bindValue(':target', $extension);
        $statement->bindValue(':status', 'completed');
        $statement->bindValue(':original_size', $validated['size'], PDO::PARAM_INT);
        $statement->bindValue(':output_size', $outputSize, PDO::PARAM_INT);
        $statement->bindValue(':download_name', $downloadName);
        $statement->bindValue(':lifetime', JOB_LIFETIME, PDO::PARAM_INT);
        $statement->execute();

        $reduction = $operation === 'compress' && $outputSize < $validated['size']
            ? round((1 - ($outputSize / $validated['size'])) * 100, 1)
            : 0;
        return [
            'success' => true,
            'token' => $token,
            'name' => $downloadName,
            'sourceFormat' => strtoupper($validated['format']),
            'targetFormat' => strtoupper($extension),
            'originalSize' => $validated['size'],
            'outputSize' => $outputSize,
            'originalSizeLabel' => format_file_size($validated['size']),
            'outputSizeLabel' => format_file_size($outputSize),
            'reduction' => $reduction,
            'reduced' => $outputSize < $validated['size'],
            'message' => $operation === 'compress' && !$smaller
                ? 'File hasil tidak lebih kecil, jadi file asli dipertahankan.'
                : 'Pemrosesan selesai.',
        ];
    } catch (Throwable $exception) {
        if (is_file($outputPath)) {
            unlink($outputPath);
        }
        throw $exception;
    } finally {
        if ($sourceMoved && is_file($sourcePath)) {
            unlink($sourcePath);
        }
    }
}
