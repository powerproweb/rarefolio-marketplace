<?php
/**
 * Router for `php -S` built-in server used by tests/test_api_router.php.
 * Real files pass through; anything under /api/v1/ is dispatched via the
 * front-controller at api/v1/index.php.
 */
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$root = dirname(__DIR__);

// Real static file on disk — let the server handle it.
$candidate = $root . $uri;
if ($uri !== '/' && is_file($candidate)) {
    return false;
}

// Route all /api/v1/* (including /api/v1 and /api/v1/) through the front controller.
if ($uri === '/api/v1' || str_starts_with($uri, '/api/v1/')) {
    require $root . '/api/v1/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'not found';
return true;
