<?php
declare(strict_types=1);

/**
 * Unit test: RareFolio\Webhook\Signer produces the expected payload format,
 * headers, and deterministic signatures given fixed inputs. Also exercises
 * the main-site verifier logic inline to prove end-to-end compatibility.
 *
 * Run:  php tests/test_webhook_signer.php
 * Exit codes: 0 on pass, 1 on fail.
 */

require __DIR__ . '/../src/Webhook/Signer.php';

use RareFolio\Webhook\Signer;

$pass = 0; $fail = 0;

function t(string $name, callable $fn): void {
    global $pass, $fail;
    echo "• $name ... ";
    try { $fn(); $pass++; echo "ok\n"; }
    catch (Throwable $e) { $fail++; echo "FAIL — " . $e->getMessage() . "\n"; }
}

function assertEq($a, $b, string $m = ''): void {
    if ($a !== $b) {
        $aa = is_scalar($a) ? (string)$a : json_encode($a);
        $bb = is_scalar($b) ? (string)$b : json_encode($b);
        throw new RuntimeException(($m ?: 'not equal') . " got=$aa expected=$bb");
    }
}

echo "Webhook Signer tests\n====================\n";

t('sign() returns headers + body with expected keys', function () {
    [$headers, $body] = Signer::sign('secret', '{"hello":"world"}', 1700000000, 'nonce_abc123');
    assertEq($body, '{"hello":"world"}');
    foreach (['Content-Type', 'X-RF-Timestamp', 'X-RF-Nonce', 'X-RF-Signature', 'User-Agent'] as $k) {
        if (!array_key_exists($k, $headers)) throw new RuntimeException("missing header: $k");
    }
    assertEq($headers['X-RF-Timestamp'], '1700000000');
    assertEq($headers['X-RF-Nonce'],     'nonce_abc123');
});

t('signature is deterministic for fixed inputs', function () {
    [$h1, ] = Signer::sign('secret', '{"a":1}', 1700000000, 'n1');
    [$h2, ] = Signer::sign('secret', '{"a":1}', 1700000000, 'n1');
    assertEq($h1['X-RF-Signature'], $h2['X-RF-Signature']);
});

t('signature changes when body changes', function () {
    [$h1, ] = Signer::sign('secret', '{"a":1}', 1700000000, 'n1');
    [$h2, ] = Signer::sign('secret', '{"a":2}', 1700000000, 'n1');
    if ($h1['X-RF-Signature'] === $h2['X-RF-Signature']) {
        throw new RuntimeException('signature must differ when body differs');
    }
});

t('signature matches main-site verifier formula', function () {
    $secret = 'shared-secret-xyz';
    $body   = '{"event":"mint.complete","cnft_id":"qd-silver-0000001"}';
    $ts     = 1700000123;
    $nonce  = 'nonce-1700000123';

    [$headers, $signedBody] = Signer::sign($secret, $body, $ts, $nonce);

    // Recompute what the receiver would compute
    $payload   = $ts . '.' . $nonce . '.' . $signedBody;
    $expected  = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    assertEq($headers['X-RF-Signature'], $expected, 'signer/verifier formulas diverge');
});

t('nonce is URL-safe and of reasonable length', function () {
    for ($i = 0; $i < 20; $i++) {
        $n = Signer::newNonce();
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $n)) {
            throw new RuntimeException("bad nonce: $n");
        }
    }
});

t('empty secret throws', function () {
    try { Signer::sign('', '{}'); }
    catch (RuntimeException) { return; }
    throw new RuntimeException('expected RuntimeException');
});

echo "\nResults: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
