<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
http_response_code(404);
$pageTitle = 'Halaman tidak ditemukan';
$pageDescription = 'Halaman Convertify yang Anda cari tidak dapat ditemukan.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page text-center">
    <span class="tool-heading-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
    <h1 class="display-4 fw-bold">404</h1>
    <p class="text-secondary mb-4">Maaf, halaman tersebut tidak dapat ditemukan.</p>
    <a href="<?= h(APP_BASE_URL) ?>/index.php" class="btn btn-primary">Kembali ke Convertify</a>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
