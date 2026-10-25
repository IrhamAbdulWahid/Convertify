<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$convertPages = ['convert-images.php', 'convert-pdf.php'];
$compressPages = ['compress-images.php', 'compress-pdf.php'];
?>
<header class="site-header">
    <nav class="navbar navbar-expand-lg container" aria-label="Navigasi utama">
        <div class="navbar-brand">
            <span class="brand-mark"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></span>
            <span><?= h(APP_NAME) ?></span>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-controls="navbarMenu" aria-expanded="false" aria-label="Buka atau tutup navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarMenu">
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link<?= $currentPage === 'index.php' ? ' active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/">Beranda</a></li>
                <li class="nav-item dropdown">
                    <button class="nav-link dropdown-toggle<?= in_array($currentPage, $convertPages, true) ? ' active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Konversi
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item<?= $currentPage === 'convert-images.php' ? ' active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/convert-images.php">Konversi Gambar</a></li>
                        <li><a class="dropdown-item<?= $currentPage === 'convert-pdf.php' ? ' active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/convert-pdf.php">Konversi Dokumen (PDF)</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <button class="nav-link dropdown-toggle<?= in_array($currentPage, $compressPages, true) ? ' active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Kompres
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item<?= $currentPage === 'compress-images.php' ? ' active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/compress-images.php">Kompres Gambar</a></li>
                        <li><a class="dropdown-item<?= $currentPage === 'compress-pdf.php' ? ' active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/compress-pdf.php">Kompres Dokumen (PDF)</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</header>
