<?php
define('APP_RUNNING', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'PdfImageConverter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_error('Metode permintaan tidak diizinkan.', 405);
}
if (!verify_csrf()) {
    app_error('Sesi formulir berakhir. Muat ulang halaman dan coba lagi.', 403);
}
if (!check_rate_limit('pdf-processing')) {
    app_error('Batas proses sementara tercapai. Tunggu sebentar lalu coba lagi.', 429);
}

$files = isset($_FILES['files']) && is_array($_FILES['files']) ? normalize_uploaded_files($_FILES['files']) : [];
if (!$files || count($files) > MAX_UPLOAD_FILES) {
    app_error('Pilih antara 1 sampai 10 file gambar.', 400);
}
$pageSize = $_POST['page_size'] ?? '';
$orientation = $_POST['orientation'] ?? '';
if (!is_string($pageSize) || !in_array($pageSize, ['a4', 'image'], true)) {
    app_error('Pilih ukuran halaman PDF yang valid.', 400);
}
if (!is_string($orientation) || !in_array($orientation, ['portrait', 'landscape'], true)) {
    app_error('Pilih orientasi halaman yang valid.', 400);
}

set_time_limit(30);
$lock = false;
$sourcePaths = [];
$outputPath = null;
$token = null;
$savedJob = false;
try {
    $lock = acquire_processing_lock();
    if ($lock === false) {
        app_error('Server sedang memproses banyak file. Silakan coba lagi sebentar.', 429);
    }
    $pdo = get_db_connection();
    FileCleanup::removeExpiredJobs($pdo);
    $totalSize = 0;
    foreach ($files as $file) {
        $validated = FileValidator::validateUpload($file);
        $totalSize += $validated['size'];
        if ($totalSize > MAX_UPLOAD_FILES * MAX_UPLOAD_SIZE) {
            throw new RuntimeException('Total ukuran semua gambar tidak boleh lebih dari 100 MB.');
        }
        $sourcePath = TEMP_DIR . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.upload';
        if (!move_uploaded_file($validated['tmp_name'], $sourcePath)) {
            throw new RuntimeException('Server gagal menyimpan gambar sementara.');
        }
        $sourcePaths[] = $sourcePath;
        if ($validated['format'] === 'gif' || $validated['format'] === 'jpeg' || $validated['format'] === 'png' || $validated['format'] === 'webp' || $validated['format'] === 'bmp') {
            continue;
        }
        throw new RuntimeException('Format gambar tidak didukung untuk pembuatan PDF.');
    }

    $token = bin2hex(random_bytes(32));
    $outputPath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $token . '.pdf';
    PdfImageConverter::create($sourcePaths, $outputPath, $pageSize, $orientation);
    $outputSize = filesize($outputPath);
    if ($outputSize === false || $outputSize < 1) {
        throw new RuntimeException('File PDF kosong atau tidak dapat dibuat.');
    }
    $downloadName = safe_download_name($files[0]['name'] ?? 'gambar', 'digabungkan', 'pdf');
    $statement = $pdo->prepare(
        'INSERT INTO conversion_jobs (job_token, operation_type, source_format, target_format, status, original_size, output_size, download_name, created_at, expires_at)
         VALUES (:token, :operation, :source, :target, :status, :original_size, :output_size, :download_name, CURRENT_TIMESTAMP, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :lifetime SECOND))'
    );
    $statement->bindValue(':token', $token);
    $statement->bindValue(':operation', 'convert');
    $statement->bindValue(':source', 'image-set');
    $statement->bindValue(':target', 'pdf');
    $statement->bindValue(':status', 'completed');
    $statement->bindValue(':original_size', $totalSize, PDO::PARAM_INT);
    $statement->bindValue(':output_size', $outputSize, PDO::PARAM_INT);
    $statement->bindValue(':download_name', $downloadName);
    $statement->bindValue(':lifetime', JOB_LIFETIME, PDO::PARAM_INT);
    $statement->execute();
    $savedJob = true;

    json_response([
        'success' => true,
        'results' => [[
            'success' => true,
            'token' => $token,
            'name' => $downloadName,
            'sourceFormat' => count($files) . ' gambar',
            'targetFormat' => 'PDF',
            'originalSizeLabel' => format_file_size($totalSize),
            'outputSizeLabel' => format_file_size($outputSize),
            'reduction' => 0,
            'message' => count($files) . ' gambar berhasil digabungkan menjadi PDF ' . strtoupper($pageSize) . '.',
        ]],
    ]);
} catch (PDOException $exception) {
    if ($token !== null && !$savedJob && $outputPath !== null && is_file($outputPath)) {
        unlink($outputPath);
    }
    app_log($exception);
    app_error('Layanan belum siap. Pastikan database convertify_db sudah diimpor.', 503);
} catch (RuntimeException $exception) {
    if ($token !== null && !$savedJob && $outputPath !== null && is_file($outputPath)) {
        unlink($outputPath);
    }
    app_error($exception->getMessage(), 422);
} catch (Throwable $exception) {
    if ($token !== null && !$savedJob && $outputPath !== null && is_file($outputPath)) {
        unlink($outputPath);
    }
    app_log($exception);
    app_error('PDF gagal dibuat. Periksa file dan coba lagi.', 500);
} finally {
    foreach ($sourcePaths as $sourcePath) {
        if (is_file($sourcePath)) {
            unlink($sourcePath);
        }
    }
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
