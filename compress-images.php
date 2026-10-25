<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'FileValidator.php';

$pageTitle = 'Kompres Gambar';
$pageDescription = 'Kompres gambar JPEG, PNG, WebP, BMP, dan GIF dengan ukuran file yang terukur.';
$pageScript = 'processor.js';
$formats = FileValidator::availableOutputFormats();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page" data-operation="compress" data-endpoint="<?= h(APP_BASE_URL) ?>/actions/process-image.php">
    <header class="tool-heading">
        <a href="<?= h(APP_BASE_URL) ?>/index.php" class="back-link"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        <span class="tool-heading-icon compress-icon"><i class="fa-solid fa-compress" aria-hidden="true"></i></span>
        <h1>Kompres Gambar</h1>
        <p>Atur kualitas gambar dan ubah ukurannya jika diperlukan. Ukuran hasil diukur langsung dari file.</p>
    </header>
    <section class="tool-panel">
        <form id="processorForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="compress">
            <div class="form-group mb-4">
                <label class="form-label fw-semibold" for="imageFiles">Pilih file gambar</label>
                <label class="upload-dropzone compact-dropzone" for="imageFiles" data-dropzone>
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
                    <label class="form-label fw-semibold" for="quality">Kualitas <span id="qualityValue">75%</span></label>
                    <input class="form-range" type="range" id="quality" name="quality" min="20" max="95" value="75">
                    <small class="form-text text-secondary">Kualitas JPEG/WebP dan tingkat kompresi PNG. BMP dikodekan ulang tanpa pengaturan kualitas.</small>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="maxDimension">Dimensi maksimum</label>
                    <select class="form-select" id="maxDimension" name="max_dimension">
                        <option value="none">Pertahankan dimensi asli</option>
                        <option value="2048">Sesuaikan dalam 2048 px</option>
                        <option value="1024">Sesuaikan dalam 1024 px</option>
                    </select>
                </div>
            </div>
            <p class="tool-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Kompresi JPEG/WebP dapat mengurangi kualitas. Kompresi PNG tidak mengurangi kualitas, tetapi ukuran file mungkin tidak berkurang. Jika hasilnya lebih besar, Convertify akan mempertahankan file asli. File GIF tidak diubah agar animasinya tetap terjaga.</p>
            <button class="btn btn-primary btn-lg process-button" type="submit" id="processButton" disabled><i class="fa-solid fa-compress" aria-hidden="true"></i> Kompres gambar</button>
            <div class="process-progress" id="progressArea" hidden><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Sedang diproses...</span></div><span>Gambar Anda sedang dikompres dengan aman...</span></div>
            <div class="alert alert-danger mt-3" id="formError" role="alert" hidden></div>
        </form>
        <section class="results-section" id="results" aria-live="polite" hidden>
            <div class="results-title"><h2>Hasil pemrosesan</h2><button class="btn btn-outline-secondary btn-sm" type="button" data-reset>Kompres file lain</button></div>
            <div id="resultList" class="result-list"></div>
        </section>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
