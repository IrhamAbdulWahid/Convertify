<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/DocumentConverter.php';

$pageTitle = 'Kompres PDF & Dokumen';
$pageDescription = 'Kurangi ukuran file PDF menggunakan mesin PyMuPDF lokal.';
$pageScript = 'pdf-compressor.js';
$pdfCompressionAvailable = DocumentConverter::canCompressPdf();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page">
    <header class="tool-heading">
        <a href="<?= h(APP_BASE_URL) ?>/" class="back-link"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        <span class="tool-heading-icon pdf-category-icon"><i class="fa-regular fa-file-pdf" aria-hidden="true"></i></span>
        <h1>Kompres PDF &amp; Dokumen</h1>
        <p>Kurangi ukuran file PDF sambil mempertahankan teks yang dapat dipilih dan konten vektor.</p>
    </header>
    <section class="tool-panel">
        <?php if ($pdfCompressionAvailable): ?>
        <form id="pdfCompressionForm" enctype="multipart/form-data" data-endpoint="<?= h(APP_BASE_URL) ?>/actions/process-document.php">
            <?= csrf_field() ?>
            <input type="hidden" name="target_format" value="compress-pdf">
            <div class="form-group mb-4">
                <label class="form-label fw-semibold" for="pdfCompressionFile">Pilih file PDF</label>
                <label class="upload-dropzone compact-dropzone" for="pdfCompressionFile" data-dropzone>
                    <i class="fa-regular fa-file-pdf upload-icon" aria-hidden="true"></i>
                    <strong>Letakkan file PDF Anda di sini</strong>
                    <span class="muted">atau <span class="text-primary fw-semibold">telusuri file</span></span>
                    <span class="upload-formats">PDF · maksimal 10 MB · hingga 100 halaman</span>
                    <input type="file" id="pdfCompressionFile" name="files[]" accept=".pdf,application/pdf" required>
                </label>
                <div id="pdfCompressionFileInfo" class="file-list" aria-live="polite"></div>
            </div>
            <p class="tool-note">Kompresor mengoptimalkan gambar yang disisipkan dan aliran data PDF secara lokal. Gambar beresolusi tinggi mungkin diperkecil dan dikompres ulang sehingga kualitasnya sedikit menurun; teks dan elemen vektor tetap dapat dipilih.</p>
            <button class="btn btn-primary btn-lg process-button" type="submit" id="pdfCompressionButton" disabled><i class="fa-solid fa-compress" aria-hidden="true"></i> Kompres PDF</button>
            <div class="process-progress" id="pdfCompressionProgress" hidden><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Sedang diproses...</span></div><span>PDF Anda sedang dikompres dengan aman...</span></div>
            <div class="alert alert-danger mt-3" id="pdfCompressionError" role="alert" hidden></div>
        </form>
        <section class="results-section" id="pdfCompressionResults" aria-live="polite" hidden>
            <div class="results-title"><h2>Hasil kompresi</h2><button class="btn btn-outline-secondary btn-sm" type="button" data-pdf-compression-reset>Kompres PDF lain</button></div>
            <div id="pdfCompressionResultList" class="result-list"></div>
        </section>
        <?php else: ?>
        <div class="dependency-panel" role="status">
            <span class="dependency-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
            <h2>Kompresor PDF belum dikonfigurasi</h2>
            <p>Pasang PyMuPDF untuk interpreter Python yang digunakan Laragon, lalu muat ulang halaman ini. Lihat langkah instalasi di README.md.</p>
        </div>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
