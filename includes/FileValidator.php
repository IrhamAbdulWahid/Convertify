<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

final class FileValidator
{
    private const FORMAT_BY_EXTENSION = [
        'jpg' => 'jpeg',
        'jpeg' => 'jpeg',
        'png' => 'png',
        'webp' => 'webp',
        'bmp' => 'bmp',
        'gif' => 'gif',
    ];

    private const MIME_FORMATS = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/gif' => 'gif',
    ];

    private const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    public static function validateUpload(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'File melebihi batas ukuran upload server.',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar.',
                UPLOAD_ERR_PARTIAL => 'Upload file tidak selesai. Silakan coba lagi.',
                UPLOAD_ERR_NO_FILE => 'Pilih file gambar terlebih dahulu.',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder upload sementara server tidak tersedia.',
                UPLOAD_ERR_CANT_WRITE => 'Server gagal menyimpan upload. Silakan coba lagi.',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh konfigurasi server.',
            ];
            throw new RuntimeException($messages[$error] ?? 'Upload gagal. Silakan coba lagi.');
        }

        $tmpName = $file['tmp_name'] ?? '';
        $size = (int) ($file['size'] ?? 0);
        $originalName = $file['name'] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('File upload tidak valid. Silakan pilih file kembali.');
        }
        if ($size < 1) {
            throw new RuntimeException('File kosong tidak dapat diproses.');
        }
        if ($size > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('Ukuran maksimum setiap file adalah 10 MB.');
        }
        if (!is_string($originalName) || strlen($originalName) > 240) {
            throw new RuntimeException('Nama file terlalu panjang.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::FORMAT_BY_EXTENSION[$extension])) {
            throw new RuntimeException('Format tidak didukung. Gunakan JPEG, PNG, WebP, BMP, atau GIF.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        $format = self::MIME_FORMATS[$mime] ?? null;
        if ($format === null || self::FORMAT_BY_EXTENSION[$extension] !== $format) {
            throw new RuntimeException('Isi file tidak cocok dengan format gambar yang diizinkan.');
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1], $imageInfo[2])) {
            throw new RuntimeException('File gambar rusak atau tidak dapat dibaca.');
        }
        $expectedType = [
            'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1,
            'bmp' => defined('IMAGETYPE_BMP') ? IMAGETYPE_BMP : -1,
            'gif' => IMAGETYPE_GIF,
        ][$format];
        if ($imageInfo[2] !== $expectedType) {
            throw new RuntimeException('Tipe gambar tidak konsisten dan tidak dapat diproses.');
        }
        if ($imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > intdiv(MAX_IMAGE_PIXELS, $imageInfo[1])) {
            throw new RuntimeException('Resolusi gambar terlalu besar. Batasnya 10 megapiksel.');
        }

        return [
            'tmp_name' => $tmpName,
            'size' => $size,
            'name' => basename(str_replace('\\', '/', $originalName)),
            'format' => $format,
            'mime' => $mime,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }

    public static function validateDocumentUpload(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'File melebihi batas ukuran upload server.',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar.',
                UPLOAD_ERR_PARTIAL => 'Upload file tidak selesai. Silakan coba lagi.',
                UPLOAD_ERR_NO_FILE => 'Pilih file PDF atau dokumen terlebih dahulu.',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder upload sementara server tidak tersedia.',
                UPLOAD_ERR_CANT_WRITE => 'Server gagal menyimpan upload. Silakan coba lagi.',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh konfigurasi server.',
            ];
            throw new RuntimeException($messages[$error] ?? 'Upload gagal. Silakan coba lagi.');
        }

        $tmpName = $file['tmp_name'] ?? '';
        $size = (int) ($file['size'] ?? 0);
        $originalName = $file['name'] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('File upload tidak valid. Silakan pilih file kembali.');
        }
        if ($size < 1 || $size > MAX_UPLOAD_SIZE) {
            throw new RuntimeException($size < 1 ? 'File kosong tidak dapat diproses.' : 'Ukuran maksimum file adalah 10 MB.');
        }
        if (!is_string($originalName) || strlen($originalName) > 240) {
            throw new RuntimeException('Nama file terlalu panjang.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::DOCUMENT_EXTENSIONS, true)) {
            throw new RuntimeException('Format dokumen tidak didukung. Gunakan PDF, DOC, DOCX, XLS, XLSX, PPT, atau PPTX.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        $signature = file_get_contents($tmpName, false, null, 0, 8);
        if (!is_string($signature)) {
            throw new RuntimeException('Server gagal membaca file yang diunggah.');
        }

        if ($extension === 'pdf') {
            if ($mime !== 'application/pdf' || strpos($signature, '%PDF-') !== 0) {
                throw new RuntimeException('Isi file tidak cocok dengan format PDF.');
            }
        } elseif (in_array($extension, ['doc', 'xls', 'ppt'], true)) {
            $legacyMimeFormats = [
                'application/x-ole-storage' => null,
                'application/vnd.ms-office' => null,
                'application/CDFV2' => null,
                'application/msword' => 'doc',
                'application/vnd.ms-word' => 'doc',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.ms-powerpoint' => 'ppt',
            ];
            $detectedFormat = $legacyMimeFormats[$mime] ?? null;
            if (!array_key_exists($mime, $legacyMimeFormats)
                || ($detectedFormat !== null && $detectedFormat !== $extension)
                || substr($signature, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                throw new RuntimeException('Isi file tidak cocok dengan format Office lama yang dipilih.');
            }
        } else {
            if (!in_array($mime, ['application/zip', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'], true)
                || substr($signature, 0, 2) !== 'PK' || !class_exists(ZipArchive::class)) {
                throw new RuntimeException('Isi file tidak cocok dengan format Office yang dipilih.');
            }
            $archive = new ZipArchive();
            if ($archive->open($tmpName) !== true) {
                throw new RuntimeException('File Office rusak atau tidak dapat dibaca.');
            }
            $requiredEntry = [
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/workbook.xml',
                'pptx' => 'ppt/presentation.xml',
            ][$extension];
            $validArchive = $archive->locateName($requiredEntry) !== false;
            $archive->close();
            if (!$validArchive) {
                throw new RuntimeException('Struktur file tidak cocok dengan format Office yang dipilih.');
            }
        }

        return [
            'tmp_name' => $tmpName,
            'size' => $size,
            'name' => basename(str_replace('\\', '/', $originalName)),
            'format' => $extension,
            'mime' => $mime,
        ];
    }

    public static function availableOutputFormats(): array
    {
        $formats = [
            'jpeg' => ['extension' => 'jpg', 'mime' => 'image/jpeg', 'function' => 'imagejpeg'],
            'png' => ['extension' => 'png', 'mime' => 'image/png', 'function' => 'imagepng'],
            'webp' => ['extension' => 'webp', 'mime' => 'image/webp', 'function' => 'imagewebp'],
            'bmp' => ['extension' => 'bmp', 'mime' => 'image/bmp', 'function' => 'imagebmp'],
            'gif' => ['extension' => 'gif', 'mime' => 'image/gif', 'function' => 'imagegif'],
        ];
        return array_filter($formats, static function (array $format): bool {
            return function_exists($format['function']);
        });
    }
}
