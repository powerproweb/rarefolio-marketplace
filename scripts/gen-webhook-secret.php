<?php
declare(strict_types=1);

/**
 * gen-webhook-secret.php
 *
 * Generates a fresh 32-byte (256-bit) secret as 64 hex characters, suitable
 * for the webhook HMAC shared secret. Cross-platform — requires only PHP 8.1+,
 * no openssl CLI, no Node, no bash.
 *
 * Usage:
 *      php scripts/gen-webhook-secret.php
 *      php scripts/gen-webhook-secret.php --bytes=64     # longer secret
 *      php scripts/gen-webhook-secret.php --format=base64 # base64url instead of hex
 *
 * Paste the output into BOTH:
 *      marketplace .env               -> PUBLIC_SITE_WEBHOOK_SECRET
 *      main site environment          -> RF_WEBHOOK_SECRET
 * They MUST match exactly.
 */

$bytes  = 32;
$format = 'hex';

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--bytes=(\d+)$/', $arg, $m)) {
        $bytes = max(16, min(128, (int) $m[1]));
    } elseif (preg_match('/^--format=(hex|base64)$/', $arg, $m)) {
        $format = $m[1];
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php scripts/gen-webhook-secret.php [--bytes=32] [--format=hex|base64]\n");
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: $arg\n");
        exit(2);
    }
}

$raw = random_bytes($bytes);

if ($format === 'hex') {
    $secret = bin2hex($raw);
} else {
    $secret = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

echo "\n";
echo "Generated webhook secret (" . strlen($secret) . " chars, $format)\n";
echo str_repeat('-', 70) . "\n";
echo $secret . "\n";
echo str_repeat('-', 70) . "\n";
echo "\n";
echo "Next steps:\n";
echo "  1. In the marketplace .env, set:\n";
echo "       PUBLIC_SITE_WEBHOOK_SECRET=$secret\n\n";
echo "  2. On the main site, set the same value in the environment as:\n";
echo "       RF_WEBHOOK_SECRET=$secret\n\n";
echo "     (cPanel/Plesk: use the 'Environment Variables' panel.\n";
echo "      Apache: add `SetEnv RF_WEBHOOK_SECRET <value>` in api/webhook/.htaccess.\n";
echo "      nginx+php-fpm: add to the pool config with `env[RF_WEBHOOK_SECRET] = <value>`.)\n\n";
echo "  3. NEVER commit either value to git. Both .env files are gitignored.\n";
