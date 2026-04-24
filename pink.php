<?php
/**
 * pink.php — authorized health probe only (read-only JSON).
 *
 * NOT a remote shell: no command execution, no file manager, no eval.
 * Use only on servers you own or have explicit permission to test.
 *
 * Setup:
 *   1. Change PINK_PROBE_KEY to a long random string.
 *   2. Upload to your site (or place in docroot during local dev).
 *   3. Open: https://yoursite.example/pink.php?key=YOUR_SECRET
 *
 * Remove this file when testing is done.
 */
declare(strict_types=1);

const PINK_PROBE_KEY = 'change-this-to-a-long-random-secret';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
$expected = PINK_PROBE_KEY;
$ok = $expected !== ''
    && $expected !== 'change-this-to-a-long-random-secret'
    && hash_equals($expected, $key);

// Wrong/missing key: generic 404 (no hint that this endpoint exists).
if (!$ok) {
    http_response_code(404);
    echo '{}';
    exit;
}

$extensions = get_loaded_extensions();
sort($extensions, SORT_STRING);

$payload = [
    'ok' => true,
    'probe' => 'pink',
    'time_utc' => gmdate('c'),
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'os' => PHP_OS_FAMILY,
    'extensions' => $extensions,
    'ini' => [
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
    ],
];

if (isset($_SERVER['SERVER_SOFTWARE'])) {
    $payload['server_software'] = (string) $_SERVER['SERVER_SOFTWARE'];
}

// Document root helps verify which vhost answered (your own server only).
if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
    $payload['document_root'] = $_SERVER['DOCUMENT_ROOT'];
}

if (PINK_PROBE_KEY === 'change-this-to-a-long-random-secret') {
    $payload['warning'] = 'Set PINK_PROBE_KEY in pink.php before any real deployment.';
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
