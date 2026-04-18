<?php
declare(strict_types=1);

namespace RareFolio\Api;

use RareFolio\Config;

/**
 * Minimal per-IP token-bucket rate limiter, file-backed.
 *
 * - No external dependencies (no Redis, no APCu).
 * - Uses sys_get_temp_dir()/rf_ratelimit/ for storage (or RATE_LIMIT_DIR env).
 * - Default: 60 requests per 60 seconds per client IP.
 *
 * Upgrade path: swap storage to Redis/APCu later by replacing the read/write
 * helpers in this class only. Endpoint call sites do not change.
 */
final class RateLimit
{
    public static function enforce(string $bucket = 'default'): void
    {
        $capacity   = Config::int('RATE_LIMIT_CAPACITY', 60);
        $windowSecs = Config::int('RATE_LIMIT_WINDOW_SECONDS', 60);

        if ($capacity <= 0 || $windowSecs <= 0) {
            return; // limiter disabled
        }

        $ip  = self::clientIp();
        $key = substr(hash('sha256', $bucket . '|' . $ip), 0, 40);

        [$count, $windowStart] = self::read($key);
        $now = time();

        if ($now - $windowStart >= $windowSecs) {
            $count       = 0;
            $windowStart = $now;
        }

        $count++;

        if ($count > $capacity) {
            $retryAfter = max(1, $windowSecs - ($now - $windowStart));
            // Expose remaining headers before aborting
            self::sendHeaders($capacity, 0, $windowStart + $windowSecs);
            Response::rateLimited($retryAfter);
            exit;
        }

        self::write($key, $count, $windowStart);
        self::sendHeaders($capacity, max(0, $capacity - $count), $windowStart + $windowSecs);
    }

    private static function sendHeaders(int $limit, int $remaining, int $resetEpoch): void
    {
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetEpoch);
    }

    private static function clientIp(): string
    {
        // Use REMOTE_ADDR by default. If you are behind a trusted proxy, set
        // TRUSTED_PROXY_HEADER=X-Forwarded-For in env to honor it.
        $header = Config::get('TRUSTED_PROXY_HEADER', '');
        if ($header !== null && $header !== '') {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
            if (!empty($_SERVER[$key])) {
                $parts = explode(',', (string) $_SERVER[$key]);
                $ip    = trim($parts[0]);
                if ($ip !== '') {
                    return $ip;
                }
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private static function storageDir(): string
    {
        $dir = Config::get('RATE_LIMIT_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rf_ratelimit');
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /** @return array{0:int,1:int} [count, windowStart] */
    private static function read(string $key): array
    {
        $f = self::storageDir() . DIRECTORY_SEPARATOR . $key;
        if (!is_file($f)) {
            return [0, time()];
        }
        $raw = @file_get_contents($f);
        if ($raw === false) {
            return [0, time()];
        }
        $parts = explode('|', trim($raw));
        if (count($parts) !== 2) {
            return [0, time()];
        }
        return [(int) $parts[0], (int) $parts[1]];
    }

    private static function write(string $key, int $count, int $windowStart): void
    {
        $f = self::storageDir() . DIRECTORY_SEPARATOR . $key;
        @file_put_contents($f, $count . '|' . $windowStart, LOCK_EX);
    }
}
