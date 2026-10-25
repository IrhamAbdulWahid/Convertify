<?php
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

final class DocumentConverter
{
    private const SUPPORTED_SOURCE_FORMATS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    private const SUPPORTED_TARGET_FORMATS = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'];

    public static function convert(string $sourcePath, string $sourceFormat, string $targetFormat, string $workDirectory, string $outputPath): void
    {
        if (!in_array($sourceFormat, self::SUPPORTED_SOURCE_FORMATS, true)
            || !in_array($targetFormat, self::SUPPORTED_TARGET_FORMATS, true)
            || ($sourceFormat === 'pdf' && $targetFormat === 'pdf')
            || ($sourceFormat !== 'pdf' && $targetFormat !== 'pdf')) {
            throw new RuntimeException('Kombinasi format dokumen tidak didukung.');
        }

        if ($sourceFormat === 'pdf') {
            $python = self::pythonExecutable();
            $helper = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'convert_document.py';
            if (!is_file($python) || !is_file($helper)) {
                throw new RuntimeException('Konverter PDF ke dokumen belum terpasang. Periksa konfigurasi Python dan dependensinya.');
            }
            self::runProcess([$python, $helper, 'pdf-to-' . $targetFormat, $sourcePath, $outputPath], $workDirectory, 120);
        } else {
            $office = self::officeExecutable();
            if ($office === null) {
                throw new RuntimeException('LibreOffice belum terpasang. Instal LibreOffice untuk mengubah file Office ke PDF.');
            }
            $profile = self::fileUri($workDirectory . DIRECTORY_SEPARATOR . 'lo-profile');
            self::runProcess([
                $office,
                '--headless',
                '--nologo',
                '--nodefault',
                '--nofirststartwizard',
                '-env:UserInstallation=' . $profile,
                '--convert-to',
                'pdf',
                '--outdir',
                $workDirectory,
                $sourcePath,
            ], $workDirectory, 120);

            $generatedPdf = $workDirectory . DIRECTORY_SEPARATOR . pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';
            if (!is_file($generatedPdf)) {
                throw new RuntimeException('LibreOffice tidak menghasilkan file PDF. Periksa dokumen sumber.');
            }
            if (!copy($generatedPdf, $outputPath)) {
                throw new RuntimeException('Server gagal menyimpan hasil konversi.');
            }
        }

        self::verifyOutput($outputPath, $targetFormat);
    }

    public static function canConvertPdfToOffice(): bool
    {
        $helper = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'convert_document.py';
        return function_exists('proc_open')
            && class_exists(ZipArchive::class)
            && is_file(self::pythonExecutable())
            && is_file($helper);
    }

    public static function canConvertOfficeToPdf(): bool
    {
        return function_exists('proc_open')
            && class_exists(ZipArchive::class)
            && self::officeExecutable() !== null;
    }

    public static function canCompressPdf(): bool
    {
        $python = self::pythonExecutable();
        $helper = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'convert_document.py';
        if (!function_exists('proc_open') || !is_file($python) || !is_file($helper)) {
            return false;
        }

        $pipes = [];
        $process = proc_open([$python, '-c', 'import pymupdf'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0;
    }

    public static function compressPdf(string $sourcePath, string $workDirectory, string $outputPath): void
    {
        if (!self::canCompressPdf()) {
            throw new RuntimeException('Kompresor PDF belum siap. Pastikan Python dan PyMuPDF sudah terpasang.');
        }
        $helper = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'convert_document.py';
        self::runProcess(
            [self::pythonExecutable(), $helper, 'compress-pdf', $sourcePath, $outputPath],
            $workDirectory,
            120
        );
        self::verifyOutput($outputPath, 'pdf');
    }

    private static function verifyOutput(string $path, string $format): void
    {
        if (!is_file($path) || filesize($path) < 1) {
            throw new RuntimeException('File hasil kosong atau gagal dibuat.');
        }
        $header = file_get_contents($path, false, null, 0, 8);
        if (!is_string($header)) {
            throw new RuntimeException('Server gagal memverifikasi file hasil.');
        }

        if ($format === 'pdf') {
            if (strpos($header, '%PDF-') !== 0) {
                throw new RuntimeException('Hasil konversi bukan file PDF yang valid.');
            }
            return;
        }

        if (in_array($format, ['jpg', 'png'], true)) {
            $archive = new ZipArchive();
            if (substr($header, 0, 2) !== 'PK' || $archive->open($path) !== true) {
                throw new RuntimeException('Hasil konversi bukan arsip gambar yang valid.');
            }
            $imageCount = 0;
            $signature = $format === 'jpg' ? "\xFF\xD8\xFF" : "\x89PNG\r\n\x1A\n";
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (!is_string($name) || !preg_match('/^page-\d{3}\.' . preg_quote($format, '/') . '$/', $name)) {
                    $archive->close();
                    throw new RuntimeException('Arsip hasil berisi file gambar yang tidak valid.');
                }
                $stream = $archive->getStream($name);
                if ($stream === false) {
                    $archive->close();
                    throw new RuntimeException('Gambar hasil tidak dapat dibaca dari arsip.');
                }
                $imageHeader = fread($stream, strlen($signature));
                fclose($stream);
                if ($imageHeader !== $signature) {
                    $archive->close();
                    throw new RuntimeException('Format gambar hasil tidak sesuai pilihan.');
                }
                $imageCount++;
            }
            $archive->close();
            if ($imageCount < 1 || $imageCount > 100) {
                throw new RuntimeException('Jumlah gambar hasil tidak valid.');
            }
            return;
        }

        $requiredEntry = [
            'docx' => 'word/document.xml',
            'xlsx' => 'xl/workbook.xml',
            'pptx' => 'ppt/presentation.xml',
        ][$format] ?? null;
        $archive = new ZipArchive();
        if ($requiredEntry === null || substr($header, 0, 2) !== 'PK' || $archive->open($path) !== true) {
            throw new RuntimeException('Hasil konversi bukan file Office yang valid.');
        }
        $validArchive = $archive->locateName($requiredEntry) !== false;
        $archive->close();
        if (!$validArchive) {
            throw new RuntimeException('Struktur file hasil tidak cocok dengan format Office yang diminta.');
        }
    }

    private static function runProcess(array $command, string $workingDirectory, int $timeout): void
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $workingDirectory);
        if (!is_resource($process)) {
            throw new RuntimeException('Server tidak dapat menjalankan konverter dokumen.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $errorOutput = '';
        $startedAt = microtime(true);
        $exitCode = -1;
        $timedOut = false;
        do {
            $output .= stream_get_contents($pipes[1]);
            $errorOutput .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (microtime(true) - $startedAt > $timeout) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }
            usleep(100000);
        } while (true);

        if ($timedOut) {
            proc_terminate($process, 9);
        }
        $output .= stream_get_contents($pipes[1]);
        $errorOutput .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($process);
        if ($exitCode === -1) {
            $exitCode = $closeCode;
        }

        if ($timedOut) {
            throw new RuntimeException('Konversi melewati batas waktu 120 detik. Coba file yang lebih sederhana.');
        }
        if ($exitCode !== 0) {
            $details = trim($errorOutput . PHP_EOL . $output);
            app_log(new RuntimeException('Document converter exited with code ' . $exitCode . ': ' . substr($details, 0, 4000)));
            throw new RuntimeException('Konversi gagal. Periksa file sumber atau coba dokumen lain.');
        }
    }

    private static function officeExecutable(): ?string
    {
        $configured = getenv('CONVERTIFY_LIBREOFFICE');
        $candidates = array_filter([
            is_string($configured) ? $configured : false,
            'C:\\Program Files\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ]);
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return self::findOnPath(['soffice.com', 'soffice.exe']);
    }

    private static function pythonExecutable(): string
    {
        $configured = getenv('CONVERTIFY_PYTHON');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        $laragonPython = 'C:\\laragon\\bin\\python\\python-3.10\\python.exe';
        if (is_file($laragonPython)) {
            return $laragonPython;
        }
        return self::findOnPath(['python.exe', 'python']) ?? 'python';
    }

    private static function findOnPath(array $names): ?string
    {
        $pathValue = getenv('PATH');
        if (!is_string($pathValue)) {
            return null;
        }
        foreach (explode(PATH_SEPARATOR, $pathValue) as $directory) {
            foreach ($names as $name) {
                $candidate = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }
        return null;
    }

    private static function fileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $prefix = preg_match('/^[a-zA-Z]:\//', $normalized) ? 'file:///' : 'file://';
        return $prefix . str_replace(' ', '%20', $normalized);
    }
}
