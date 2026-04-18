# Rarefolio Public API v1

The marketplace exposes a read-only JSON API at `/api/v1/*`. It is the bridge
the main site (`rarefolio.io`) uses to pull live token, bar, and listing data.

- **Auth**: none for reads. Public endpoints only.
- **CORS**: exact-origin whitelist (no wildcards).
- **Rate limit**: per-IP, file-backed token bucket (defaults to 60 req / 60 s).
- **Envelope**: `{ "ok": true, "data": ... }` on success, `{ "ok": false, "error": { code, message } }` on failure.

## Endpoints

| Method | Path                        | Purpose                                                  |
| ------ | --------------------------- | -------------------------------------------------------- |
| GET    | `/api/v1`                   | Lists available endpoints                                |
| GET    | `/api/v1/health`            | Service liveness + DB ping (never 500s)                  |
| GET    | `/api/v1/tokens/{cnft_id}`  | Single CNFT lookup (e.g. `qd-silver-0000001`)            |
| GET    | `/api/v1/bars/{bar_serial}` | Aggregate stats for a silver bar (e.g. `E101837`)        |
| GET    | `/api/v1/listings`          | Active listings. Query: `bar`, `limit` (1–100), `offset` |

### Example: token lookup

```
GET /api/v1/tokens/qd-silver-0000001

{
  "ok": true,
  "data": {
    "cnft_id": "qd-silver-0000001",
    "title": "Taurus — Grand Collector",
    "collection": "silverbar-01",
    "bar_serial": "E101837",
    "chain": {
      "network": "preprod",
      "policy_id": "…",
      "asset_name_hex": "…",
      "asset_fingerprint": "asset1…",
      "mint_tx_hash": "…",
      "minted_at": "2026-04-01T12:00:00Z"
    },
    "status": {
      "primary_sale": "minted",
      "listing": "none",
      "custody": "platform",
      "secondary_eligible": true
    },
    "owner_display": "addr1q8…xy9z",
    "updated_at": "2026-04-17T23:50:00Z"
  }
}
```

### Error codes

- `400 bad_request` — validation failed
- `404 not_found`   — resource missing
- `405 method_not_allowed` — only GET/OPTIONS supported
- `429 rate_limited` — includes `Retry-After`
- `503 unavailable` — DB not configured

## Config

See `.env.example` at the marketplace root. The API only cares about:

- `CORS_ALLOWED_ORIGINS` — comma-separated exact origins
- `RATE_LIMIT_CAPACITY`, `RATE_LIMIT_WINDOW_SECONDS`
- `TRUSTED_PROXY_HEADER` — if behind a CDN/reverse proxy
- `DB_*` — same DB the admin dashboard uses

## Webhooks (marketplace → main site)

Signed, one-way notifications. See `docs/WEBHOOKS.md`.
