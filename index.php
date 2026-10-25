<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';

$pageTitle = 'Tentang Convertify';
$pageDescription = 'Kenali Convertify, layanan daring untuk mengonversi dan mengompres gambar.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page">
    <header class="tool-heading">
        <h1>Tentang Convertify</h1>
        <p>Cara yang lebih mudah untuk menyiapkan file gambar.</p>
    </header>
    <article class="tool-panel prose">
        <p>Convertify adalah layanan publik untuk mengonversi dan mengompres format gambar yang didukung tanpa perlu membuat akun. Pilih Konversi untuk mengubah format file gambar, atau Kompres untuk mengurangi ukuran gambar jika format dan gambar yang dipilih mendukungnya.</p>
        <h2>Format gambar yang didukung</h2>
        <p>JPEG, PNG, WebP, BMP, dan GIF hanya tersedia jika PHP GD memiliki fungsi gambar yang sesuai. HEIC dan TIFF belum didukung dalam versi ini.</p>
        <h2>Privasi</h2>
        <p>File gambar diunggah ke server untuk diproses dan file hasil sementara disimpan paling lama 24 jam. Baca <a href="<?= h(APP_BASE_URL) ?>/privacy-policy.php">Kebijakan Privasi</a> untuk informasi selengkapnya.</p>
        <h2>Fitur PDF</h2>
        <p>Anda dapat menggabungkan gambar menjadi PDF beberapa halaman, mengekspor halaman PDF sebagai gambar JPG atau PNG dalam arsip ZIP, dan mengompres file PDF sambil mempertahankan teks yang dapat dipilih serta konten vektor. Konversi PDF ke Office memerlukan instalasi perangkat Python lokal.</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
