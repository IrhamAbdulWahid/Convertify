<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
$pageTitle = 'Kebijakan Privasi';
$pageDescription = 'Cara Convertify memproses gambar, dokumen, dan file sementara yang diunggah.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page">
    <header class="tool-heading"><h1>Kebijakan Privasi</h1><p>Informasi tentang file yang Anda proses dengan Convertify.</p></header>
    <article class="tool-panel prose">
        <h2>File yang diproses</h2>
        <p>Konversi dan kompresi gambar menggunakan PHP GD. Konversi dokumen menggunakan LibreOffice dan pustaka Python yang dipasang secara lokal di server Convertify. File yang Anda pilih diunggah ke server untuk diproses; file tidak diproses hanya di browser dan tidak dikirim ke API konversi pihak ketiga. File sumber sementara dihapus setelah proses selesai. File hasil disimpan di direktori privat di luar direktori publik situs.</p>
        <h2>Penyimpanan dan tautan unduhan</h2>
        <p>File hasil dan metadata minimum terkait proses disimpan hingga 24 jam. Pembersihan dijalankan saat ada permintaan pemrosesan atau unduhan. Operator situs sebaiknya menjadwalkan tugas pembersihan CLI yang disertakan agar file kedaluwarsa tetap dihapus saat situs tidak aktif. Tautan unduhan berisi token akses acak. Anda dapat menghapus hasil dari layar hasil atau menunggu hingga masa penyimpanannya berakhir.</p>
        <h2>Metadata</h2>
        <p>Convertify menyimpan jenis operasi, format sumber dan tujuan, ukuran file, nama unduhan yang dibuat, token acak, status proses, serta waktu pembuatan dan kedaluwarsa. Isi file tidak disimpan di basis data. Anda tidak perlu membuat akun.</p>
        <h2>Pihak ketiga</h2>
        <p>Versi ini tidak mengirim file yang diunggah ke API konversi pihak ketiga. Halaman memuat Bootstrap dan Font Awesome dari CDN publiknya.</p>
        <h2>Fitur yang didukung</h2>
        <p>Konversi gambar mendukung JPEG, PNG, WebP, BMP, dan GIF jika codec GD terkait tersedia. Konversi GIF animasi menggunakan bingkai pertama; kompresi GIF mempertahankan file sumber tanpa perubahan. Fitur gambar ke PDF menggunakan GD untuk menggabungkan gambar menjadi beberapa halaman PDF. Dokumen Office dapat dikonversi ke PDF. PDF dengan teks yang dapat dipilih dapat dikonversi ke DOCX dengan hasil perkiraan atau teks dan tabelnya dapat diekstrak ke XLSX. Konversi PDF ke PPTX membuat slide dari gambar halaman; teks pada slide tidak dapat diedit. PDF hasil pemindaian tidak diproses dengan OCR. Halaman PDF dapat diekspor sebagai gambar JPG atau PNG. Kompresi PDF mengoptimalkan gambar dan aliran data yang tertanam secara lokal sambil mempertahankan teks dan konten vektor.</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
