<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}
?>
<footer class="site-footer">
    <div class="container py-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <span class="footer-brand"><i class="fa-solid fa-arrows-rotate me-2" aria-hidden="true"></i><?= h(APP_NAME) ?></span>
        <p class="mb-0">Konversi dan kompres gambar tanpa perlu membuat akun.</p>
        <div class="d-flex gap-3">
            <a href="<?= h(APP_BASE_URL) ?>/privacy-policy.php">Privasi</a>
            <a href="<?= h(APP_BASE_URL) ?>/terms.php">Ketentuan</a>
        </div>
    </div>
</footer>
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" aria-live="polite" aria-atomic="true"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= h(APP_BASE_URL) ?>/assets/js/app.js"></script>
<?php if (!empty($pageScript)): ?><script src="<?= h(APP_BASE_URL) ?>/assets/js/<?= h($pageScript) ?>"></script><?php endif; ?>
</body>
</html>
