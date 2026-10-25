<?php
define('APP_RUNNING', true);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'DocumentConverter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_error('Metode permintaan tidak diizinkan.', 405);
}
if (!verify_csrf()) {
    app_error('Sesi formulir berakhir. Muat ulang halaman dan coba lagi.', 403);
}
if (!check_rate_limit('document-processing')) {
    app_error('Batas proses sementara tercapai. Tunggu sebentar lalu coba lagi.', 429);
}

$files = isset($_FILES['files']) && is_array($_FILES['files']) ? normalize_uploaded_files($_FILES['files']) : [];
if (count($files) !== 1) {
    app_error('Pilih satu file PDF atau dokumen untuk dikonversi.', 400);
}
$targetFormat = $_POST['target_format'] ?? '';
if (!is_string($targetFormat) || !in_array($targetFormat, ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png', 'compress-pdf'], true)) {
    app_error('Pilih format hasil konversi yang valid.', 400);
}
$isPdfCompression = $targetFormat === 'compress-pdf';

set_time_limit(150);
$lock = false;
$workDirectory = null;
$sourcePath = null;
$outputPath = null;
$token = null;
$savedJob = false;
$responsePayload = null;
$responseStatus = 200;
try {
    $lock = acquire_processing_lock();
    if ($lock === false) {
        app_error('Server sedang memproses banyak file. Silakan coba lagi sebentar.', 429);
    }
    ensure_storage_directories();
    $pdo = get_db_connection();
    FileCleanup::removeExpiredJobs($pdo);
    $validated = FileValidator::validateDocumentUpload($files[0]);
    if ($isPdfCompression && $validated['format'] !== 'pdf') {
        throw new RuntimeException('Pilih file PDF untuk dikompresi.');
    }
    if (!$isPdfCompression && (($validated['format'] === 'pdf' && $targetFormat === 'pdf')
        || ($validated['format'] !== 'pdf' && $targetFormat !== 'pdf'))) {
        throw new RuntimeException('PDF hanya dapat dikonversi ke DOCX, XLSX, PPTX, JPG, atau PNG. Dokumen Office dikonversi ke PDF.');
    }
    $converterAvailable = $isPdfCompression
        ? DocumentConverter::canCompressPdf()
        : ($validated['format'] === 'pdf'
        ? DocumentConverter::canConvertPdfToOffice()
        : DocumentConverter::canConvertOfficeToPdf());
    if (!$converterAvailable) {
        throw new RuntimeException($isPdfCompression
            ? 'Kompresor PDF belum siap. Pastikan Python dan PyMuPDF sudah terpasang.'
            : ($validated['format'] === 'pdf'
            ? 'Konverter PDF lokal belum siap. Periksa instalasi Python dan dependensi PDF.'
            : 'LibreOffice belum terpasang. Instal LibreOffice untuk mengubah dokumen Office ke PDF.'));
    }

    $workDirectory = TEMP_DIR . DIRECTORY_SEPARATOR . 'document-' . bin2hex(random_bytes(16));
    if (!mkdir($workDirectory, 0700)) {
        throw new RuntimeException('Server gagal menyiapkan ruang kerja sementara.');
    }
    $sourcePath = $workDirectory . DIRECTORY_SEPARATOR . 'source.' . $validated['format'];
    if (!move_uploaded_file($validated['tmp_name'], $sourcePath)) {
        throw new RuntimeException('Server gagal menyimpan file sementara.');
    }

    $token = bin2hex(random_bytes(32));
    $storedFormat = in_array($targetFormat, ['jpg', 'png'], true) ? 'zip' : ($isPdfCompression ? 'pdf' : $targetFormat);
    $outputPath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $token . '.' . $storedFormat;
    if ($isPdfCompression) {
        DocumentConverter::compressPdf($sourcePath, $workDirectory, $outputPath);
    } else {
        DocumentConverter::convert($sourcePath, $validated['format'], $targetFormat, $workDirectory, $outputPath);
    }
    $outputSize = filesize($outputPath);
    if ($outputSize === false || $outputSize < 1) {
        throw new RuntimeException('File hasil kosong atau tidak dapat dibuat.');
    }
    if ($outputSize > MAX_DOCUMENT_OUTPUT_SIZE) {
        throw new RuntimeException('File hasil melebihi batas 100 MB.');
    }

    $downloadName = safe_download_name(
        $validated['name'],
        $isPdfCompression ? 'dikompres' : (in_array($targetFormat, ['jpg', 'png'], true) ? 'halaman-' . $targetFormat : 'dikonversi'),
        $storedFormat
    );
    $statement = $pdo->prepare(
        'INSERT INTO conversion_jobs (job_token, operation_type, source_format, target_format, status, original_size, output_size, download_name, created_at, expires_at)
         VALUES (:token, :operation, :source, :target, :status, :original_size, :output_size, :download_name, CURRENT_TIMESTAMP, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :lifetime SECOND))'
    );
    $statement->bindValue(':token', $token);
    $statement->bindValue(':operation', $isPdfCompression ? 'compress' : 'convert');
    $statement->bindValue(':source', $validated['format']);
    $statement->bindValue(':target', $storedFormat);
    $statement->bindValue(':status', 'completed');
    $statement->bindValue(':original_size', $validated['size'], PDO::PARAM_INT);
    $statement->bindValue(':output_size', $outputSize, PDO::PARAM_INT);
    $statement->bindValue(':download_name', $downloadName);
    $statement->bindValue(':lifetime', JOB_LIFETIME, PDO::PARAM_INT);
    $statement->execute();
    $savedJob = true;

    $responsePayload = [
        'success' => true,
        'results' => [[
            'success' => true,
            'token' => $token,
            'name' => $downloadName,
            'sourceFormat' => strtoupper($validated['format']),
            'targetFormat' => $isPdfCompression
                ? 'PDF (terkompresi)'
                : (in_array($targetFormat, ['jpg', 'png'], true)
                    ? 'Gambar ' . strtoupper($targetFormat) . ' (ZIP)'
                    : strtoupper($targetFormat)),
            'originalSizeLabel' => format_file_size($validated['size']),
            'outputSizeLabel' => format_file_size($outputSize),
            'reduction' => $isPdfCompression && $validated['size'] > 0
                ? max(0, (int) round((1 - ($outputSize / $validated['size'])) * 100))
                : 0,
            'message' => $isPdfCompression
                ? ($outputSize < $validated['size']
                    ? 'PDF berhasil dikompresi tanpa merasterisasi halaman.'
                    : 'PDF sudah cukup optimal; ukuran file tidak dapat diperkecil lebih lanjut tanpa mengurangi kualitas lebih jauh.')
                : (in_array($targetFormat, ['jpg', 'png'], true)
                    ? 'Setiap halaman PDF disimpan sebagai gambar terpisah dalam arsip ZIP.'
                    : ($validated['format'] === 'pdf' && $targetFormat === 'pptx'
                    ? 'Setiap halaman PDF menjadi gambar slide; teks pada slide tidak dapat diedit.'
                    : ($validated['format'] === 'pdf' && $targetFormat === 'xlsx'
                        ? 'Teks dan tabel PDF telah diekstrak; tata letak yang rumit mungkin perlu dirapikan.'
                        : 'Dokumen berhasil dikonversi.'))),
        ]],
    ];
} catch (PDOException $exception) {
    app_log($exception);
    $responseStatus = 503;
    $responsePayload = ['success' => false, 'message' => 'Layanan belum siap. Pastikan database convertify_db sudah diimpor.'];
} catch (RuntimeException $exception) {
    $responseStatus = 422;
    $responsePayload = ['success' => false, 'message' => $exception->getMessage()];
} catch (Throwable $exception) {
    app_log($exception);
    $responseStatus = 500;
    $responsePayload = ['success' => false, 'message' => 'Dokumen gagal dikonversi. Periksa file dan coba lagi.'];
} finally {
    if ($token !== null && !$savedJob && $outputPath !== null && is_file($outputPath)) {
        unlink($outputPath);
    }
    if ($workDirectory !== null && is_dir($workDirectory)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($workDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($workDirectory);
    }
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
json_response($responsePayload ?? ['success' => false, 'message' => 'Dokumen gagal dikonversi. Periksa file dan coba lagi.'], $responseStatus);
