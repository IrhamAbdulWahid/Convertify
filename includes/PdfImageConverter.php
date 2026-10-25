<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

final class PdfImageConverter
{
    public static function create(
        array $imagePaths,
        string $destination,
        string $pageSize,
        string $orientation
    ): void {
        if (!$imagePaths || !in_array($pageSize, ['a4', 'image'], true) || !in_array($orientation, ['portrait', 'landscape'], true)) {
            throw new RuntimeException('Pengaturan PDF tidak valid.');
        }

        $jpegFiles = [];
        try {
            foreach ($imagePaths as $imagePath) {
                $jpegPath = TEMP_DIR . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.jpg';
                self::createJpeg($imagePath, $jpegPath);
                $jpegFiles[] = $jpegPath;
            }
            self::writePdf($jpegFiles, $destination, $pageSize, $orientation);
        } finally {
            foreach ($jpegFiles as $jpegFile) {
                if (is_file($jpegFile)) {
                    unlink($jpegFile);
                }
            }
        }
    }

    private static function createJpeg(string $source, string $destination): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            throw new RuntimeException('Salah satu gambar rusak dan tidak dapat dimasukkan ke PDF.');
        }
        try {
            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);
            $scale = min(1, 1800 / max($sourceWidth, $sourceHeight));
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
            $output = imagecreatetruecolor($width, $height);
            if ($output === false) {
                throw new RuntimeException('Server tidak memiliki memori cukup untuk membuat PDF.');
            }
            try {
                imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
                if (!imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
                    throw new RuntimeException('Gambar tidak dapat disiapkan untuk PDF.');
                }
                if (!imagejpeg($output, $destination, 88)) {
                    throw new RuntimeException('Server gagal menyiapkan gambar untuk PDF.');
                }
            } finally {
                imagedestroy($output);
            }
        } finally {
            imagedestroy($image);
        }
    }

    private static function writePdf(array $jpegFiles, string $destination, string $pageSize, string $orientation): void
    {
        $a4Width = 595.276;
        $a4Height = 841.89;
        $objects = [];
        $pageReferences = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        foreach ($jpegFiles as $index => $jpegFile) {
            $pageId = 3 + ($index * 3);
            $imageId = $pageId + 1;
            $contentId = $pageId + 2;
            $pageReferences[] = $pageId . ' 0 R';
            $jpeg = file_get_contents($jpegFile);
            if ($jpeg === false || $jpeg === '') {
                throw new RuntimeException('Gambar PDF kosong atau tidak terbaca.');
            }
            $imageInfo = getimagesize($jpegFile);
            if ($imageInfo === false) {
                throw new RuntimeException('Gambar PDF tidak memiliki dimensi yang valid.');
            }

            $pageWidth = $pageSize === 'a4' ? $a4Width : $imageInfo[0] * 0.75;
            $pageHeight = $pageSize === 'a4' ? $a4Height : $imageInfo[1] * 0.75;
            if ($pageSize === 'a4' && $orientation === 'landscape') {
                [$pageWidth, $pageHeight] = [$a4Height, $a4Width];
            }
            $scale = min($pageWidth / $imageInfo[0], $pageHeight / $imageInfo[1]);
            $drawWidth = $imageInfo[0] * $scale;
            $drawHeight = $imageInfo[1] * $scale;
            $x = ($pageWidth - $drawWidth) / 2;
            $y = ($pageHeight - $drawHeight) / 2;
            $content = sprintf("q %.4F 0 0 %.4F %.4F %.4F cm /Im0 Do Q\n", $drawWidth, $drawHeight, $x, $y);

            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.4F %.4F] /Resources << /XObject << /Im0 %d 0 R >> >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $imageId,
                $contentId
            );
            $objects[$imageId] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $imageInfo[0],
                $imageInfo[1],
                strlen($jpeg),
                $jpeg
            );
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageReferences) . '] /Count ' . count($pageReferences) . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $objectCount = count($objects) + 1;
        $pdf .= "xref\n0 " . $objectCount . "\n0000000000 65535 f \n";
        for ($id = 1; $id < $objectCount; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . $objectCount . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
        if (file_put_contents($destination, $pdf, LOCK_EX) === false || filesize($destination) < 1) {
            throw new RuntimeException('Server gagal membuat file PDF. Pastikan ruang penyimpanan tersedia.');
        }
    }
}
