<?php
/**
 * pink.php — read-only JSON health probe (authorized use only).
 *
 * No command execution, uploads, or file browsing. Remove after testing.
 *
 * Configure:
 *   - Set PINK_PROBE_KEY below, OR set env PINK_PROBE_KEY (non-empty wins).
 * Request:
 *   GET/POST pink.php?key=SECRET
 *   Or header: Authorization: Bearer SECRET  or  X-Pink-Key: SECRET
 *
 * Options (after auth):
 *   lite=1     Faster/smaller JSON: extension count only, no full list.
 *   pretty=1   Pretty-print JSON (slower/larger; default is compact).
 */
declare(strict_types=1);

const PINK_PROBE_KEY = 'change-this-to-a-long-random-secret';
const PLACEHOLDER_KEY = 'change-this-to-a-long-random-secret';

/** @return array{0: float, 1: int} [seconds since request start (float), start ns (int)] */
function pink_timer_start(): array
{
    if (function_exists('hrtime')) {
        $ns = hrtime(true);
        return [$ns / 1e9, (int) $ns];
    }
    return [microtime(true), 0];
}

function pink_elapsed_seconds(float $t0): float
{
    if (function_exists('hrtime')) {
        return (hrtime(true) / 1e9) - $t0;
    }
    return microtime(true) - $t0;
}

function pink_expected_key(): string
{
    $fromEnv = getenv('PINK_PROBE_KEY');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }
    return PINK_PROBE_KEY;
}

function pink_extract_key(): string
{
    if (isset($_GET['key']) && is_string($_GET['key'])) {
        return $_GET['key'];
    }
    if (isset($_POST['key']) && is_string($_POST['key'])) {
        return $_POST['key'];
    }
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (is_string($auth) && strncasecmp($auth, 'Bearer ', 8) === 0) {
        return trim(substr($auth, 8));
    }
    $xh = $_SERVER['HTTP_X_PINK_KEY'] ?? '';
    return is_string($xh) ? $xh : '';
}

[$t0] = pink_timer_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$key = pink_extract_key();
$expected = pink_expected_key();
$configured = $expected !== '' && $expected !== PLACEHOLDER_KEY;
$ok = $configured && hash_equals($expected, $key);

if (!$ok) {
    http_response_code(404);
    echo '{}';
    exit;
}

$lite = isset($_REQUEST['lite']) && ($_REQUEST['lite'] === '1' || $_REQUEST['lite'] === 'true');
$pretty = isset($_REQUEST['pretty']) && ($_REQUEST['pretty'] === '1' || $_REQUEST['pretty'] === 'true');

$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
if ($pretty) {
    $flags |= JSON_PRETTY_PRINT;
}

$iniKeys = ['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size', 'display_errors', 'opcache.enable'];
$ini = [];
foreach ($iniKeys as $k) {
    $v = ini_get($k);
    if ($v !== false) {
        $ini[$k] = $v;
    }
}

$payload = [
    'ok' => true,
    'probe' => 'pink',
    'v' => 2,
    'time_utc' => gmdate('c'),
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'os' => PHP_OS_FAMILY,
    'zend' => zend_version(),
    'ini' => $ini,
];

if (function_exists('memory_get_peak_usage')) {
    $payload['memory_peak_bytes'] = memory_get_peak_usage(true);
}

if (!$lite) {
    $ext = get_loaded_extensions();
    sort($ext, SORT_STRING);
    $payload['extensions'] = $ext;
} else {
    $payload['extensions_count'] = count(get_loaded_extensions());
}

if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $payload['opcache'] = [
            'enabled' => (bool) ($st['opcache_enabled'] ?? false),
            'cache_full' => (bool) ($st['cache_full'] ?? false),
            'hits' => $st['opcache_statistics']['hits'] ?? null,
            'misses' => $st['opcache_statistics']['misses'] ?? null,
        ];
    }
}

if (isset($_SERVER['SERVER_SOFTWARE']) && is_string($_SERVER['SERVER_SOFTWARE'])) {
    $payload['server_software'] = $_SERVER['SERVER_SOFTWARE'];
}

if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])) {
    $payload['document_root'] = $_SERVER['DOCUMENT_ROOT'];
}

if (!$configured) {
    $payload['warning'] = 'Set PINK_PROBE_KEY (or env PINK_PROBE_KEY) before production use.';
}

$payload['gen_ms'] = round(pink_elapsed_seconds($t0) * 1000, 3);

echo json_encode($payload, $flags);
exit;
