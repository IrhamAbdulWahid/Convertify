<?php
define('APP_RUNNING', true);
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_error('Metode permintaan tidak diizinkan.', 405);
}
if (!verify_csrf()) {
    app_error('Sesi formulir berakhir. Muat ulang halaman dan coba lagi.', 403);
}
if (!check_rate_limit('image-processing')) {
    app_error('Batas proses sementara tercapai. Tunggu sebentar lalu coba lagi.', 429);
}

$files = isset($_FILES['files']) && is_array($_FILES['files']) ? normalize_uploaded_files($_FILES['files']) : [];
if (!$files || count($files) > MAX_UPLOAD_FILES) {
    app_error('Pilih antara 1 sampai 10 file gambar untuk diproses.', 400);
}
$operation = $_POST['operation'] ?? '';
if (!is_string($operation) || !in_array($operation, ['convert', 'compress'], true)) {
    app_error('Jenis proses tidak valid.', 400);
}
$format = 'jpeg';
if ($operation === 'convert') {
    $format = $_POST['format'] ?? '';
    if (!is_string($format) || !preg_match('/^(jpeg|png|webp|bmp|gif)$/', $format)) {
        app_error('Pilih format gambar output yang valid.', 400);
    }
}
$qualityInput = $_POST['quality'] ?? '82';
$quality = filter_var($qualityInput, FILTER_VALIDATE_INT);
if ($quality === false || $quality < 20 || $quality > 95) {
    app_error('Kualitas harus antara 20 sampai 95.', 400);
}
$background = $_POST['background'] ?? '#ffffff';
if (!is_string($background) || !preg_match('/^#[a-fA-F0-9]{6}$/', $background)) {
    app_error('Warna latar tidak valid.', 400);
}
$maxDimensionInput = $_POST['max_dimension'] ?? '';
$maxDimension = null;
if ($operation === 'compress' && $maxDimensionInput !== '' && $maxDimensionInput !== 'none') {
    if (!is_string($maxDimensionInput) || !in_array($maxDimensionInput, ['2048', '1024'], true)) {
        app_error('Pilihan ukuran gambar tidak valid.', 400);
    }
    $maxDimension = (int) $maxDimensionInput;
}

set_time_limit(30);
$lock = false;
try {
    $lock = acquire_processing_lock();
    if ($lock === false) {
        app_error('Server sedang memproses banyak file. Silakan coba lagi sebentar.', 429);
    }
    $pdo = get_db_connection();
    FileCleanup::removeExpiredJobs($pdo);

    $results = [];
    foreach ($files as $file) {
        try {
            $results[] = process_image_job($pdo, $file, $operation, $format, $quality, $background, $maxDimension);
        } catch (PDOException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            $results[] = ['success' => false, 'name' => is_string($file['name'] ?? null) ? basename($file['name']) : 'File', 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            app_log($exception);
            $results[] = ['success' => false, 'name' => 'File', 'message' => 'File gagal diproses karena terjadi kesalahan. Periksa format file atau coba lagi.'];
        }
    }
    $successCount = count(array_filter($results, static function (array $result): bool {
        return $result['success'] ?? false;
    }));
    json_response([
        'success' => $successCount > 0,
        'results' => $results,
        'message' => $successCount > 0 ? 'Pemrosesan file selesai.' : 'Tidak ada file yang berhasil diproses.',
    ], $successCount > 0 ? 200 : 422);
} catch (PDOException $exception) {
    app_log($exception);
    app_error('Layanan belum siap. Pastikan database convertify_db telah dibuat dan file database/convertify.sql sudah diimpor.', 503);
} catch (Throwable $exception) {
    app_log($exception);
    app_error('Layanan pemrosesan tidak tersedia. Periksa konfigurasi penyimpanan server.', 500);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
