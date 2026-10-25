# Convertify

PHP website for converting and compressing images and documents. No user accounts are required.

## Current implementation

- Convert and Compress navigation menus link directly to their image and PDF/document tools.
- Convert JPEG, PNG, WebP, BMP, and GIF images to supported image formats.
- Compress JPEG, PNG, WebP, and BMP images; GIF is preserved unchanged to avoid dropping animation.
- Combine one or more supported images into a multi-page PDF with A4 or image-sized pages.
- Convert DOC, DOCX, XLS, XLSX, PPT, and PPTX to PDF with local LibreOffice.
- Convert selectable-text PDFs to DOCX, extract PDF text/tables to XLSX, and create image-based PPTX slides from PDF pages using local Python libraries.
- Convert PDF pages to JPG or PNG images, packaged together in a ZIP archive.
- Compress PDFs locally with PyMuPDF, optimizing embedded images and streams while preserving selectable text and vector content. Images above 300 DPI may be downsampled to 150 DPI and recompressed at JPEG quality 75.
- Process up to 10 images per request, 10 MB per image, and 10 megapixels per image.
- Keep output and job metadata for up to 24 hours; file data is stored outside the document root. Expired files are cleaned on requests and by the scheduled cleanup task described below.
- PDF-to-image conversion and PDF compression use the local PyMuPDF renderer.

Image inputs are checked using PHP Fileinfo and image decoding, not just the file extension. Office uploads are checked for their expected document structure. PDF-to-DOCX does not include OCR; PDF-to-XLSX extracts text and tables and may need layout cleanup; PDF-to-PPTX makes each page a non-editable slide image. Animated GIF conversion currently uses the first frame. TIFF and HEIC are not enabled.

## Run locally with Laragon

1. Install and start Laragon with Apache, MySQL, and PHP 8.1 or newer.
2. Put this project in `C:\laragon\www\Konversiku`.
3. In Laragon, start MySQL and open phpMyAdmin. Import `database\convertify.sql`. The script creates `convertify_db` and the `conversion_jobs` table.
4. The default database settings are `127.0.0.1`, database `convertify_db`, user `root`, and an empty password. Override these with `CONVERTIFY_DB_HOST`, `CONVERTIFY_DB_NAME`, `CONVERTIFY_DB_USER`, and `CONVERTIFY_DB_PASS` environment variables when needed.
5. Install the local document converters. On Windows, install LibreOffice from its official winget package and install the Python requirements with Laragon's Python:

   ```powershell
   winget install --id TheDocumentFoundation.LibreOffice --exact
   C:\laragon\bin\python\python-3.10\python.exe -m pip install -r C:\laragon\www\Konversiku\requirements.txt
   ```

   If either executable is installed elsewhere, set `CONVERTIFY_LIBREOFFICE` to the full `soffice.com`/`soffice.exe` path and `CONVERTIFY_PYTHON` to the full `python.exe` path in the Apache environment, then restart Apache. PDF conversion features require Python and the requirements listed above; Office-to-PDF also requires LibreOffice.
6. Check the CLI PHP modules:

   ```powershell
   php -m
   php -r "print_r(gd_info());"
   ```

   Enable `fileinfo`, `mbstring`, `pdo_mysql`, `gd`, `zip`, and `proc_open` in the PHP version selected by Laragon. JPEG, PNG, WebP, BMP, and GIF support is determined by the GD functions available on that PHP build. Image-to-PDF creation uses GD and needs no external PDF binary.
7. Ensure the PHP/Apache account can create and write to `C:\laragon\convertify-storage`. This private directory is a sibling of Laragon's `www` document root, not a public URL. The app creates its `temp` and `output` directories automatically.
8. Open `http://localhost/Konversiku/`. Test image conversion/compression, Office-to-PDF, PDF compression, and PDF-to-DOCX/XLSX/PPTX/JPG/PNG with representative files; check the output before relying on it. PDF page images are downloaded together in a ZIP archive. Output files are removed after 24 hours or when you choose another batch.
9. If processing fails, check Laragon's Apache/PHP error log. Public error responses do not include server paths or stack traces.
10. For automatic cleanup even when the site is idle, create a Windows Task Scheduler task that runs at least hourly. Set the program to Laragon's `php.exe` and the argument to `C:\laragon\www\Konversiku\actions\cleanup.php`. The script only runs in CLI mode. On Linux hosting, schedule the equivalent PHP CLI command with cron.

## Deployment notes

- Set the four `CONVERTIFY_DB_*` environment variables for the production MySQL account.
- Place the storage directory outside the public document root and grant the PHP worker exclusive write access to it. Configure the server so PHP can create the directory if it does not exist.
- Set HTTPS and use a current PHP version with `gd`, `fileinfo`, and `pdo_mysql`. Set PHP upload/post limits at least as high as the application's 10 MB per-file limit, while considering multipart overhead and memory limits.
- Document conversion additionally requires `zip`, `proc_open`, LibreOffice, Python, and the pinned packages in `requirements.txt`. Install them on the host and configure `CONVERTIFY_LIBREOFFICE`/`CONVERTIFY_PYTHON` for the web-server account. Conversion runs locally with isolated temporary profiles and a 120-second timeout; PDF rendering is limited to 100 pages and 40 megapixels per page. Do not expose this feature on shared hosting unless its process and resource limits are configured.
- Use a least-privilege database account with access only to `convertify_db`. Disable detailed error display in production.
- Bootstrap and Font Awesome are loaded from public CDNs. Self-host them if deployment policy requires no third-party requests.
- Set `CONVERTIFY_CANONICAL_URL` to the public HTTPS site URL (including the project subdirectory, if any) so `sitemap.php` emits production URLs instead of its local-development fallback.
- Poppler, Imagick, Composer packages, and Ghostscript are not required for the implemented image/document conversions.
