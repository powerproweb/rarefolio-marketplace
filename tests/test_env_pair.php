<?php
declare(strict_types=1);

/**
 * End-to-end test: the PUBLIC_SITE_WEBHOOK_SECRET in the marketplace .env
 * matches the RF_WEBHOOK_SECRET that the main site's _hmac.php would read
 * from api/webhook/.env.
 *
 * This proves the two sides are wired to the same secret without ever
 * printing the secret itself.
 *
 * Run:  php tests/test_env_pair.php
 */

$pass = 0; $fail = 0;

function t(string $name, callable $fn): void {
    global $pass, $fail;
    echo "• $name ... ";
    try { $fn(); $pass++; echo "ok\n"; }
    catch (Throwable $e) { $fail++; echo "FAIL — " . $e->getMessage() . "\n"; }
}

function readEnvValue(string $file, string $key): string {
    if (!is_file($file)) throw new RuntimeException("missing file: $file");
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $k = trim(substr($line, 0, $eq));
        if ($k !== $key) continue;
        $v = trim(substr($line, $eq + 1));
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        return $v;
    }
    throw new RuntimeException("key $key not found in $file");
}

$marketplace = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$mainsite    = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '01_rarefolio.io'
             . DIRECTORY_SEPARATOR . 'api'
             . DIRECTORY_SEPARATOR . 'webhook'
             . DIRECTORY_SEPARATOR . '.env';

echo "Env-pair integration test\n=========================\n";

t('marketplace .env has PUBLIC_SITE_WEBHOOK_SECRET', function () use ($marketplace) {
    $v = readEnvValue($marketplace, 'PUBLIC_SITE_WEBHOOK_SECRET');
    if ($v === '' || str_contains($v, 'REPLACE_WITH')) {
        throw new RuntimeException('secret still has placeholder value');
    }
    if (strlen($v) < 32) {
        throw new RuntimeException('secret suspiciously short: ' . strlen($v) . ' chars');
    }
});

t('main site api/webhook/.env has RF_WEBHOOK_SECRET', function () use ($mainsite) {
    $v = readEnvValue($mainsite, 'RF_WEBHOOK_SECRET');
    if ($v === '' || str_contains($v, 'change_me')) {
        throw new RuntimeException('secret still has placeholder value');
    }
    if (strlen($v) < 32) {
        throw new RuntimeException('secret suspiciously short: ' . strlen($v) . ' chars');
    }
});

t('both secrets match byte-for-byte', function () use ($marketplace, $mainsite) {
    $mp = readEnvValue($marketplace, 'PUBLIC_SITE_WEBHOOK_SECRET');
    $ms = readEnvValue($mainsite,    'RF_WEBHOOK_SECRET');
    if (!hash_equals($mp, $ms)) {
        throw new RuntimeException('secrets differ between sides');
    }
});

t('neither .env file is world-readable in raw text (header warning present)', function () use ($marketplace, $mainsite) {
    foreach ([$marketplace, $mainsite] as $f) {
        $contents = (string) file_get_contents($f);
        if (stripos($contents, 'do not commit') === false && stripos($contents, 'gitignored') === false) {
            throw new RuntimeException("missing 'Do NOT commit' / 'gitignored' header in $f");
        }
    }
});

t('main site _hmac.php can resolve RF_WEBHOOK_SECRET via rf_webhook_env()', function () use ($mainsite) {
    // Clear the OS env var in this process to force the file-based path
    putenv('RF_WEBHOOK_SECRET=');
    $_ENV['RF_WEBHOOK_SECRET'] = '';

    require_once dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . '01_rarefolio.io'
        . DIRECTORY_SEPARATOR . 'api'
        . DIRECTORY_SEPARATOR . 'webhook'
        . DIRECTORY_SEPARATOR . '_hmac.php';

    $v = rf_webhook_env('RF_WEBHOOK_SECRET');
    if ($v === '') {
        throw new RuntimeException('rf_webhook_env() returned empty; file loader not working');
    }
    $expected = readEnvValue($mainsite, 'RF_WEBHOOK_SECRET');
    if (!hash_equals($expected, $v)) {
        throw new RuntimeException('rf_webhook_env() returned a value that differs from .env');
    }
});

echo "\nResults: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
