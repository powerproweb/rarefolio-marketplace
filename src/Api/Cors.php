<?php
declare(strict_types=1);

namespace RareFolio\Api;

use RareFolio\Config;

/**
 * CORS middleware for the public v1 API.
 *
 * Reads an exact-origin whitelist from env var CORS_ALLOWED_ORIGINS
 * (comma-separated, e.g. "https://rarefolio.io,https://www.rarefolio.io").
 *
 * Rules:
 *   - Only GET and OPTIONS are allowed through this surface.
 *   - No credentials, no cookies. Public read-only API.
 *   - Never echoes back "*" — always the matched origin or nothing.
 *   - Preflight responses short-circuit with 204.
 */
final class Cors
{
    public static function apply(): void
    {
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $allowed = self::allowedOrigins();

        $originOk = $origin !== '' && in_array($origin, $allowed, true);

        if ($originOk) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept');
            header('Access-Control-Max-Age: 600');
        }

        // Short-circuit preflight
        if ($method === 'OPTIONS') {
            http_response_code($originOk ? 204 : 403);
            exit;
        }
    }

    /** @return string[] */
    public static function allowedOrigins(): array
    {
        $raw = Config::get('CORS_ALLOWED_ORIGINS', '');
        if ($raw === null || $raw === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter($parts, fn ($v) => $v !== ''));
    }
}
