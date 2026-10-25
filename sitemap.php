<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/config/app.php';

$configuredBase = getenv('CONVERTIFY_CANONICAL_URL');
$parsedBase = is_string($configuredBase) ? parse_url($configuredBase) : false;
$validBase = is_array($parsedBase)
    && isset($parsedBase['scheme'], $parsedBase['host'])
    && in_array(strtolower($parsedBase['scheme']), ['http', 'https'], true)
    && filter_var($configuredBase, FILTER_VALIDATE_URL)
    && !isset($parsedBase['user'])
    && !isset($parsedBase['pass']);
$baseUrl = $validBase
    ? strtolower($parsedBase['scheme']) . '://' . $parsedBase['host']
        . (isset($parsedBase['port']) ? ':' . (int) $parsedBase['port'] : '')
        . ($parsedBase['path'] ?? '')
    : 'http://localhost' . APP_BASE_URL;
$baseUrl = rtrim($baseUrl, '/');
$pages = [
    '',
    '/convert-images.php',
    '/convert-pdf.php',
    '/compress-images.php',
    '/compress-pdf.php',
    '/privacy-policy.php',
    '/terms.php',
];

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($pages as $page) {
    echo '<url><loc>' . htmlspecialchars($baseUrl . $page, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
}
echo '</urlset>';
