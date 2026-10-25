<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

final class ImageCompressor
{
    public static function compress(
        string $source,
        string $destination,
        string $format,
        int $quality,
        ?int $maxDimension
    ): bool {
        $sourceSize = filesize($source);
        if ($format === 'gif') {
            if (!copy($source, $destination)) {
                throw new RuntimeException('File GIF asli tidak dapat dipertahankan.');
            }
            return false;
        }
        ImageConverter::convert($source, $destination, $format, $quality, '#ffffff', $maxDimension);
        if (filesize($destination) < $sourceSize) {
            return true;
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException('File asli tidak dapat dipertahankan saat hasil kompresi lebih besar.');
        }
        return false;
    }
}
