<?php
declare(strict_types=1);

namespace RareFolio\Api;

/**
 * Uniform JSON response helpers for the public v1 API.
 *
 * Every endpoint should return through one of these methods so the response
 * envelope, content-type, cache headers, and error shape stay consistent.
 *
 * Success:
 *   { "ok": true,  "data": { ... } }
 *
 * Error:
 *   { "ok": false, "error": { "code": "not_found", "message": "..." } }
 */
final class Response
{
    /** HTTP status -> short code used in the JSON envelope */
    private const ERROR_CODES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        422 => 'unprocessable',
        429 => 'rate_limited',
        500 => 'internal_error',
        503 => 'unavailable',
    ];

    public static function json(mixed $data, int $status = 200, array $extraHeaders = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($extraHeaders as $k => $v) {
            header("$k: $v");
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function ok(mixed $data, int $status = 200, array $extraHeaders = []): void
    {
        self::json(['ok' => true, 'data' => $data], $status, $extraHeaders);
    }

    public static function error(int $status, string $message, ?string $code = null, array $extra = []): void
    {
        $body = [
            'ok'    => false,
            'error' => [
                'code'    => $code ?? (self::ERROR_CODES[$status] ?? 'error'),
                'message' => $message,
            ],
        ];
        if (!empty($extra)) {
            $body['error']['details'] = $extra;
        }
        self::json($body, $status);
    }

    public static function notFound(string $message = 'resource not found'): void
    {
        self::error(404, $message);
    }

    public static function badRequest(string $message, array $details = []): void
    {
        self::error(400, $message, null, $details);
    }

    public static function methodNotAllowed(array $allowed): void
    {
        header('Allow: ' . implode(', ', $allowed));
        self::error(405, 'method not allowed');
    }

    public static function rateLimited(int $retryAfterSeconds): void
    {
        header('Retry-After: ' . $retryAfterSeconds);
        self::error(429, 'rate limit exceeded, slow down');
    }
}
