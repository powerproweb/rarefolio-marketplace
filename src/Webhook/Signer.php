<?php
declare(strict_types=1);

namespace RareFolio\Webhook;

/**
 * Builds the timestamped + nonce'd HMAC signature headers expected by the
 * main-site webhook receivers.
 *
 * Payload format (signed):
 *   timestamp + "." + nonce + "." + body
 *
 * Sender MUST send:
 *   X-RF-Timestamp : epoch seconds
 *   X-RF-Nonce     : per-request random string (8..64 safe chars)
 *   X-RF-Signature : "sha256=<hex>"
 */
final class Signer
{
    /**
     * @return array{0:array<string,string>,1:string} [headers, body]
     */
    public static function sign(string $secret, string $body, ?int $timestamp = null, ?string $nonce = null): array
    {
        if ($secret === '') {
            throw new \RuntimeException('webhook secret is empty');
        }
        $ts     = $timestamp ?? time();
        $nonce  = $nonce ?? self::newNonce();
        $payload = $ts . '.' . $nonce . '.' . $body;
        $sig    = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return [[
            'Content-Type'    => 'application/json; charset=utf-8',
            'X-RF-Timestamp'  => (string) $ts,
            'X-RF-Nonce'      => $nonce,
            'X-RF-Signature'  => $sig,
            'User-Agent'      => 'rarefolio-marketplace-webhook/1.0',
        ], $body];
    }

    public static function newNonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
}
