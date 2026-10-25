<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';
$pageTitle = 'Ketentuan Layanan';
$pageDescription = 'Ketentuan penggunaan layanan konversi dan kompresi file Convertify.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main id="main-content" class="container tool-page">
    <header class="tool-heading"><h1>Ketentuan Layanan</h1><p>Ketentuan dasar penggunaan Convertify.</p></header>
    <article class="tool-panel prose">
        <h2>Penggunaan layanan</h2>
        <p>Convertify disediakan apa adanya untuk konversi dan kompresi file yang sah. Anda bertanggung jawab untuk memastikan bahwa Anda memiliki hak untuk mengunggah dan memproses file yang dipilih.</p>
        <h2>File dan ketersediaan</h2>
        <p>Hanya format dan batas ukuran file yang ditampilkan di aplikasi yang didukung. Pemrosesan dapat gagal karena codec yang tidak didukung, file rusak, atau ketersediaan layanan. Tautan unduhan kedaluwarsa setelah 24 jam. File hasil dihapus saat ada permintaan pemrosesan atau unduhan, serta oleh tugas pembersihan terjadwal jika telah dikonfigurasi. Segera unduh hasil Anda.</p>
        <h2>Hasil</h2>
        <p>Konversi dan kompresi gambar dapat mengubah kualitas, transparansi, metadata, atau animasi gambar. Gambar yang disisipkan ke PDF dikonversi ke JPEG, sehingga area transparan akan diisi warna putih. Periksa setiap hasil sebelum menggunakannya. Convertify tidak menjamin bahwa setiap hasil berukuran lebih kecil atau tampak sama persis dengan sumbernya.</p>
        <p>Konversi dokumen dapat mengubah tata letak, font, rumus, atau konten lainnya. Rekonstruksi PDF ke DOCX hanya perkiraan, PDF ke XLSX mengekstrak teks dan tabel, sedangkan PDF ke PPTX menggunakan gambar halaman yang tidak dapat diedit. PDF hasil pemindaian tidak diproses dengan OCR. Periksa hasil dokumen sebelum menggunakannya.</p>
        <h2>Penggunaan yang diperbolehkan</h2>
        <p>Jangan mencoba mengunggah file yang dapat dieksekusi, mengeksploitasi layanan, atau mengganggu operasinya. Convertify dapat membatasi permintaan untuk melindungi layanan dan penggunanya.</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
