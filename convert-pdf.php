<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/DocumentConverter.php';
$pdfToOfficeAvailable = DocumentConverter::canConvertPdfToOffice();
$officeToPdfAvailable = DocumentConverter::canConvertOfficeToPdf();
$documentsAvailable = $pdfToOfficeAvailable || $officeToPdfAvailable;
$pageTitle = 'Konversi PDF & Dokumen';
$pageDescription = 'Konversi PDF dan dokumen Office secara lokal, serta gabungkan gambar menjadi PDF.';
$pageScript = 'processor.js';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page" data-operation="convert-pdf" data-endpoint="<?= h(APP_BASE_URL) ?>/actions/process-pdf.php">
    <header class="tool-heading">
        <a href="<?= h(APP_BASE_URL) ?>/" class="back-link"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        <span class="tool-heading-icon pdf-category-icon"><i class="fa-regular fa-file-pdf" aria-hidden="true"></i></span>
        <h1>PDF &amp; Dokumen</h1>
        <p>Gabungkan gambar menjadi PDF beberapa halaman. Urutan file mengikuti urutan pilihan Anda.</p>
    </header>
    <section class="tool-panel">
        <h2 class="tool-section-title">Gambar ke PDF</h2>
        <p class="text-secondary mb-4">Buat dokumen PDF dari satu gambar atau sekumpulan gambar.</p>
        <form id="processorForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="convert">
            <div class="form-group mb-4">
                <label class="form-label fw-semibold" for="imageFiles">Gambar untuk digabungkan menjadi PDF</label>
                <label class="upload-dropzone compact-dropzone" for="imageFiles" data-dropzone>
                    <i class="fa-solid fa-cloud-arrow-up upload-icon" aria-hidden="true"></i>
                    <strong>Letakkan gambar Anda di sini</strong>
                    <span class="muted">atau <span class="text-primary fw-semibold">telusuri file</span></span>
                    <span class="upload-formats">JPEG, PNG, WebP, BMP, GIF · maksimal 10 MB per file · hingga 10 gambar</span>
                    <input type="file" id="imageFiles" name="files[]" accept=".jpg,.jpeg,.png,.webp,.bmp,.gif,image/jpeg,image/png,image/webp,image/bmp,image/gif" multiple required>
                </label>
                <div id="fileList" class="file-list" aria-live="polite"></div>
            </div>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="pageSize">Ukuran halaman PDF</label>
                    <select class="form-select" id="pageSize" name="page_size">
                        <option value="a4">A4</option>
                        <option value="image">Sesuaikan halaman dengan setiap gambar</option>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="orientation">Orientasi A4</label>
                    <select class="form-select" id="orientation" name="orientation">
                        <option value="portrait">Potret</option>
                        <option value="landscape">Lanskap</option>
                    </select>
                </div>
            </div>
            <p class="tool-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Setiap gambar menjadi satu halaman PDF. Gambar dikonversi ke JPEG untuk disisipkan ke PDF, sehingga area transparan akan diisi warna putih. Sisi terpanjang gambar diubah ukurannya menjadi maksimal 1800 px.</p>
            <button class="btn btn-primary btn-lg process-button" type="submit" id="processButton" disabled><i class="fa-regular fa-file-pdf" aria-hidden="true"></i> Buat PDF</button>
            <div class="process-progress" id="progressArea" hidden><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Sedang diproses...</span></div><span>PDF Anda sedang dibuat dengan aman...</span></div>
            <div class="alert alert-danger mt-3" id="formError" role="alert" hidden></div>
        </form>
        <section class="results-section" id="results" aria-live="polite" hidden>
            <div class="results-title"><h2>Hasil PDF</h2><button class="btn btn-outline-secondary btn-sm" type="button" data-reset>Buat PDF lain</button></div>
            <div id="resultList" class="result-list"></div>
        </section>
    </section>
    <section class="tool-panel">
        <h2 class="tool-section-title">PDF ↔ Word, Excel, PowerPoint &amp; Gambar</h2>
        <p class="text-secondary">Konversi satu PDF atau dokumen Office dalam satu waktu menggunakan konverter lokal. File dihapus setelah 24 jam.</p>
        <?php if ($documentsAvailable): ?>
        <form id="documentForm" enctype="multipart/form-data" data-document-endpoint="<?= h(APP_BASE_URL) ?>/actions/process-document.php" data-office-to-pdf="<?= $officeToPdfAvailable ? 'true' : 'false' ?>">
            <?= csrf_field() ?>
            <div class="form-group mb-4">
                <label class="form-label fw-semibold" for="documentFile">Dokumen PDF atau Office</label>
                <label class="upload-dropzone compact-dropzone" for="documentFile">
                    <i class="fa-solid fa-cloud-arrow-up upload-icon" aria-hidden="true"></i>
                    <strong>Pilih file PDF atau Office</strong>
                    <span class="muted">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX · maksimal 10 MB</span>
                    <input type="file" id="documentFile" name="files[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,application/pdf" required>
                </label>
                <div id="documentFileInfo" class="file-list" aria-live="polite"></div>
            </div>
            <div class="form-group mb-4">
                <label class="form-label fw-semibold" for="documentTargetFormat">Konversi ke</label>
                <select class="form-select" id="documentTargetFormat" name="target_format">
                    <option value="pdf">PDF</option>
                    <option value="docx">Word (.docx)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="pptx">PowerPoint (.pptx)</option>
                    <option value="jpg">Gambar JPG (ZIP)</option>
                    <option value="png">Gambar PNG (ZIP)</option>
                </select>
            </div>
            <p class="tool-note" id="documentEngineNotice" hidden>Konversi Office ke PDF belum tersedia sampai LibreOffice dipasang dan dikonfigurasi di server ini.</p>
            <p class="tool-note">Konversi PDF ke JPG/PNG membuat satu gambar untuk setiap halaman PDF di dalam arsip ZIP. Konversi PDF ke Word mempertahankan teks yang dapat dipilih dan tata letak secara perkiraan; PDF hasil pemindaian memerlukan OCR dan belum didukung. Konversi PDF ke Excel mengekstrak teks dan tabel. Konversi PDF ke PowerPoint membuat satu slide berupa gambar untuk setiap halaman, bukan teks yang dapat diedit.</p>
            <button class="btn btn-primary btn-lg process-button" type="submit" id="documentProcessButton" disabled><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Konversi dokumen</button>
            <div class="process-progress" id="documentProgress" hidden><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Sedang diproses...</span></div><span>Dokumen Anda sedang dikonversi dengan aman...</span></div>
            <div class="alert alert-danger mt-3" id="documentError" role="alert" hidden></div>
        </form>
        <section class="results-section" id="documentResults" aria-live="polite" hidden>
            <div class="results-title"><h2>Hasil dokumen</h2><button class="btn btn-outline-secondary btn-sm" type="button" data-document-reset>Konversi file lain</button></div>
            <div id="documentResultList" class="result-list"></div>
        </section>
        <?php else: ?>
        <p class="tool-note mb-0">Konversi dokumen belum diaktifkan karena konverter Python lokal tidak tersedia. Pasang perangkat yang tercantum di README.md, lalu muat ulang halaman ini.</p>
        <?php endif; ?>
    </section>
</main>
<?php if ($documentsAvailable): ?><script src="<?= h(APP_BASE_URL) ?>/assets/js/document-processor.js"></script><?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
