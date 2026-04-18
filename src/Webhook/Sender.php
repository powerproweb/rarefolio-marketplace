<?php
declare(strict_types=1);

namespace RareFolio\Webhook;

use RareFolio\Config;

/**
 * Simple curl-based webhook sender with retry and timeout.
 *
 * Resolves target URLs from env:
 *   PUBLIC_SITE_WEBHOOK_URL_BASE   e.g. "https://rarefolio.io/api/webhook"
 *   PUBLIC_SITE_WEBHOOK_SECRET     shared secret matching main site's RF_WEBHOOK_SECRET
 *
 * Usage:
 *   RareFolio\Webhook\Sender::send('mint-complete', [ ...payload... ]);
 */
final class Sender
{
    /** @return array{ok:bool,status:int,body:string} */
    public static function send(string $endpoint, array $payload, int $maxRetries = 2): array
    {
        $base   = Config::get('PUBLIC_SITE_WEBHOOK_URL_BASE', '');
        $secret = Config::get('PUBLIC_SITE_WEBHOOK_SECRET', '');
        if ($base === null || $base === '' || $secret === null || $secret === '') {
            return ['ok' => false, 'status' => 0, 'body' => 'webhook not configured'];
        }

        $url = rtrim((string) $base, '/') . '/' . ltrim($endpoint, '/') . '.php';
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'body' => 'json encode failed'];
        }

        [$headers, $signedBody] = Signer::sign($secret, $body);

        $attempt = 0;
        $last    = ['ok' => false, 'status' => 0, 'body' => ''];
        while ($attempt <= $maxRetries) {
            $attempt++;
            $last = self::curlPost($url, $headers, $signedBody);
            if ($last['ok']) return $last;
            // 4xx = don't retry (invalid auth/payload is permanent)
            if ($last['status'] >= 400 && $last['status'] < 500) return $last;
            usleep(250_000 * $attempt); // linear backoff
        }
        return $last;
    }

    /** @param array<string,string> $headers */
    private static function curlPost(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        $hdrArr = [];
        foreach ($headers as $k => $v) { $hdrArr[] = $k . ': ' . $v; }

        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $body,
            CURLOPT_HTTPHEADER      => $hdrArr,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 8,
            CURLOPT_CONNECTTIMEOUT  => 4,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'ok'     => $code >= 200 && $code < 300,
            'status' => $code,
            'body'   => is_string($resp) ? $resp : '',
        ];
    }
}
