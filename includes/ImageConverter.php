<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

final class ImageConverter
{
    public static function convert(
        string $source,
        string $destination,
        string $format,
        int $quality = 85,
        string $background = '#ffffff',
        ?int $maxDimension = null
    ): void {
        $formats = FileValidator::availableOutputFormats();
        if (!isset($formats[$format])) {
            throw new RuntimeException('Format output tidak didukung oleh GD pada server ini.');
        }
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            throw new RuntimeException('Gambar rusak atau codec yang diperlukan tidak tersedia.');
        }

        try {
            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);
            $width = $sourceWidth;
            $height = $sourceHeight;
            if ($maxDimension !== null && max($width, $height) > $maxDimension) {
                $scale = $maxDimension / max($width, $height);
                $width = max(1, (int) round($width * $scale));
                $height = max(1, (int) round($height * $scale));
            }

            $output = imagecreatetruecolor($width, $height);
            if ($output === false) {
                throw new RuntimeException('Server tidak memiliki memori cukup untuk memproses gambar ini.');
            }
            try {
                if ($format === 'jpeg') {
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $background)) {
                        throw new RuntimeException('Warna latar tidak valid.');
                    }
                    $red = hexdec(substr($background, 1, 2));
                    $green = hexdec(substr($background, 3, 2));
                    $blue = hexdec(substr($background, 5, 2));
                    imagefill($output, 0, 0, imagecolorallocate($output, $red, $green, $blue));
                } else {
                    imagealphablending($output, false);
                    imagesavealpha($output, true);
                    imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
                }
                if (!imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
                    throw new RuntimeException('Gambar tidak dapat diproses.');
                }

                $saved = match ($format) {
                    'jpeg' => imagejpeg($output, $destination, max(20, min(95, $quality))),
                    'png' => imagepng($output, $destination, max(0, min(9, (int) round((100 - $quality) * 9 / 100)))),
                    'webp' => imagewebp($output, $destination, max(20, min(95, $quality))),
                    'bmp' => imagebmp($output, $destination, true),
                    'gif' => imagegif($output, $destination),
                };
                if (!$saved || !is_file($destination) || filesize($destination) < 1) {
                    throw new RuntimeException('Server gagal membuat file hasil. Pastikan ruang penyimpanan tersedia.');
                }
            } finally {
                imagedestroy($output);
            }
        } finally {
            imagedestroy($image);
        }
    }
}
