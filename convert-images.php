<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'FileValidator.php';

$pageTitle = 'Konversi Gambar';
$pageDescription = 'Konversi gambar JPEG, PNG, WebP, BMP, dan GIF ke format yang didukung.';
$pageScript = 'processor.js';
$formats = FileValidator::availableOutputFormats();
$selectedFiles = isset($_GET['tool']) && $_GET['tool'] === 'convert';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page" data-operation="convert" data-endpoint="<?= h(APP_BASE_URL) ?>/actions/process-image.php">
    <header class="tool-heading">
        <a href="<?= h(APP_BASE_URL) ?>/index.php" class="back-link"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        <span class="tool-heading-icon"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></span>
        <h1>Konversi Gambar</h1>
        <p>Ubah format gambar tanpa mengubah file aslinya.</p>
    </header>

    <section class="tool-panel">
        <form id="processorForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="convert">
            <div class="form-group mb-4">
                <label class="form-label fw-semibold" for="imageFiles">Pilih file gambar</label>
                <label class="upload-dropzone compact-dropzone<?= $selectedFiles ? ' dropzone-highlight' : '' ?>" for="imageFiles" data-dropzone>
                    <i class="fa-solid fa-cloud-arrow-up upload-icon" aria-hidden="true"></i>
                    <strong>Letakkan file Anda di sini</strong>
                    <span class="muted">atau <span class="text-primary fw-semibold">telusuri file</span></span>
                    <span class="upload-formats">JPEG, PNG, WebP, BMP, GIF · maksimal 10 MB per file · hingga 10 file</span>
                    <input type="file" id="imageFiles" name="files[]" accept=".jpg,.jpeg,.png,.webp,.bmp,.gif,image/jpeg,image/png,image/webp,image/bmp,image/gif" multiple required>
                </label>
                <div id="fileList" class="file-list" aria-live="polite"></div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="outputFormat">Format keluaran</label>
                    <select class="form-select" id="outputFormat" name="format">
                        <?php foreach ($formats as $key => $format): ?>
                            <option value="<?= h($key) ?>"<?= $key === 'webp' ? ' selected' : '' ?>><?= h(strtoupper($format['extension'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6" id="qualityGroup">
                    <label class="form-label fw-semibold" for="quality">Kualitas <span id="qualityValue">85%</span></label>
                    <input class="form-range" type="range" id="quality" name="quality" min="20" max="95" value="85">
                    <small class="form-text text-secondary" id="qualityHelp">Kualitas JPEG/WebP dan tingkat kompresi PNG; tidak digunakan untuk BMP atau GIF.</small>
                </div>
                <div class="col-sm-6 d-none" id="backgroundGroup">
                    <label class="form-label fw-semibold" for="background">Warna latar JPEG</label>
                    <input class="form-control form-control-color" type="color" id="background" name="background" value="#ffffff" title="Pilih warna latar JPEG">
                </div>
            </div>
            <p class="tool-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> GIF animasi dikonversi menggunakan bingkai pertamanya. TIFF dan HEIC memerlukan codec tambahan yang belum diaktifkan.</p>
            <button class="btn btn-primary btn-lg process-button" type="submit" id="processButton" disabled><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Konversi gambar</button>
            <div class="process-progress" id="progressArea" hidden><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Sedang diproses...</span></div><span>Gambar Anda sedang diproses dengan aman...</span></div>
            <div class="alert alert-danger mt-3" id="formError" role="alert" hidden></div>
        </form>
        <section class="results-section" id="results" aria-live="polite" hidden>
            <div class="results-title"><h2>Hasil pemrosesan</h2><button class="btn btn-outline-secondary btn-sm" type="button" data-reset>Konversi file lain</button></div>
            <div id="resultList" class="result-list"></div>
        </section>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
