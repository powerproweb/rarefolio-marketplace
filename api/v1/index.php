<?php
/**
 * Rarefolio Public API v1 — Front Controller
 *
 * All /api/v1/* requests enter here. This file:
 *   1. Bootstraps env + classes (no Composer; tiny hand-written autoloader).
 *   2. Applies CORS middleware (exact-origin whitelist).
 *   3. Applies per-IP rate limiting.
 *   4. Dispatches GET requests to the matching endpoint in ./routes/.
 *
 * Response envelope (success): { "ok": true,  "data": ... }
 * Response envelope (error):   { "ok": false, "error": { code, message } }
 */
declare(strict_types=1);

use RareFolio\Api\Cors;
use RareFolio\Api\RateLimit;
use RareFolio\Api\Response;
use RareFolio\Config;

// ─── Bootstrap ──────────────────────────────────────────────────────────────
$root = dirname(__DIR__, 2); // marketplace root

// Hand-written PSR-4-ish autoloader for RareFolio\* (no vendor deps)
spl_autoload_register(function (string $class) use ($root): void {
    if (!str_starts_with($class, 'RareFolio\\')) {
        return;
    }
    $rel  = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('RareFolio\\')));
    $file = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Load env (best-effort). Defaults keep the /health endpoint functional without a DB.
Config::load($root . DIRECTORY_SEPARATOR . '.env');

// ─── Security middleware ────────────────────────────────────────────────────
Cors::apply();            // exits on OPTIONS preflight
RateLimit::enforce('api-v1');

// ─── Method gate ────────────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    Response::methodNotAllowed(['GET', 'OPTIONS']);
    exit;
}

// ─── Path extraction ────────────────────────────────────────────────────────
// Normalize path: strip query, strip trailing slash, drop the prefix up to /api/v1.
$uri  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
$path = rtrim($uri, '/');

$apiPos = strpos($path, '/api/v1');
if ($apiPos === false) {
    Response::notFound('api path not recognized');
    exit;
}
$route = substr($path, $apiPos + strlen('/api/v1'));
if ($route === '' || $route === false) {
    $route = '/';
}

// ─── Route table ────────────────────────────────────────────────────────────
// Keep this list short and explicit. Each entry maps a path pattern to a
// file in ./routes/ that receives $params as a local variable.
$routes = [
    '/'                               => 'index.php',
    '/health'                         => 'health.php',
    '/tokens/{id}'                    => 'tokens_show.php',
    '/bars/{serial}'                  => 'bars_show.php',
    '/listings'                       => 'listings_index.php',
];

foreach ($routes as $pattern => $file) {
    $params = match_pattern($pattern, $route);
    if ($params !== null) {
        $target = __DIR__ . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $file;
        if (!is_file($target)) {
            Response::error(500, 'route handler missing');
            exit;
        }
        require $target;
        exit;
    }
}

Response::notFound('endpoint not found: ' . $route);
exit;

// ─── Path matcher ──────────────────────────────────────────────────────────
/**
 * Returns an associative array of captured params on match, or null on miss.
 * Pattern tokens like "{id}" match a single non-slash segment and become
 * keys in the returned array.
 */
function match_pattern(string $pattern, string $actual): ?array
{
    $regex = preg_replace_callback('/\{(\w+)\}/', fn ($m) => '(?P<' . $m[1] . '>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (!preg_match($regex, $actual, $m)) {
        return null;
    }
    $out = [];
    foreach ($m as $k => $v) {
        if (!is_int($k)) {
            $out[$k] = $v;
        }
    }
    return $out;
}
