# Rarefolio Webhooks (marketplace → main site)

Signed, one-way HTTP notifications used to tell the main site
(`rarefolio.io`) when mints or ownership changes land.

## Transport

- POST JSON over HTTPS.
- Sender: marketplace (`RareFolio\Webhook\Sender`).
- Receiver: main site at `https://rarefolio.io/api/webhook/<event>.php`.

## Signature

Each request carries three headers. The payload signed is the concatenation
of the timestamp, the nonce, and the raw body, joined by dots.

```
X-RF-Timestamp : <unix_epoch_seconds>
X-RF-Nonce     : <8..64 safe chars>
X-RF-Signature : sha256=<hex_hmac_sha256(secret, ts + "." + nonce + "." + body)>
```

## Replay protection

The receiver:

1. Rejects requests whose `X-RF-Timestamp` is more than `RF_WEBHOOK_MAX_SKEW`
   seconds (default 300) away from its own clock.
2. Rejects any `X-RF-Nonce` it has already recorded.
3. Persists each accepted nonce for at least `4 * max_skew` seconds.

## Events

### `mint-complete`

Called when a CNFT mint confirms on-chain.

```
POST /api/webhook/mint-complete
Content-Type: application/json

{
  "event":             "mint.complete",
  "cnft_id":           "qd-silver-0000001",
  "bar_serial":        "E101837",
  "tx_hash":           "abcdef…",
  "policy_id":         "…",
  "asset_fingerprint": "asset1…",
  "minted_at":         "2026-04-17T23:50:00Z",
  "owner_display":     "addr1q8…xy9z"
}
```

### `ownership-change`

Called on secondary sales, transfers, and gifts.

```
POST /api/webhook/ownership-change
Content-Type: application/json

{
  "event":                  "ownership.change",
  "cnft_id":                "qd-silver-0000001",
  "previous_owner_display": "addr1q8…old0",
  "new_owner_display":      "addr1q8…new0",
  "tx_hash":                "abcdef…",
  "changed_at":             "2026-04-17T23:51:00Z"
}
```

## Sender usage (marketplace PHP)

```php
use RareFolio\Webhook\Sender;

$result = Sender::send('mint-complete', [
    'event'     => 'mint.complete',
    'cnft_id'   => 'qd-silver-0000001',
    'tx_hash'   => $txHash,
    'policy_id' => $policyId,
    'minted_at' => gmdate('c'),
]);

if (!$result['ok']) {
    error_log('[webhook] failed: ' . $result['status'] . ' ' . $result['body']);
}
```

## Secret rotation

1. Generate a new secret:  `openssl rand -hex 32`
2. Add it to the main site as `RF_WEBHOOK_SECRET_NEXT`.
3. Temporarily accept both old and new in `_hmac.php` (future work).
4. Flip marketplace `PUBLIC_SITE_WEBHOOK_SECRET` to the new value.
5. Remove the old value after one webhook retry cycle (~10 minutes).
