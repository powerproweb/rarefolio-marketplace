# RareFolio Marketplace — Architecture

## System overview

```
Browser (collector / admin)
        │
        │  HTTPS
        ▼
┌───────────────────────────────────────────────────────────┐
│  Web Server (Apache / Nginx + PHP 8.1+)                   │
│                                                           │
│  admin/         Admin dashboard (PHP, session auth)       │
│  api/v1/        Public read-only JSON API (PHP)           │
│  src/           PHP application core                      │
│    Config.php   .env loader                               │
│    Db.php       PDO singleton                             │
│    Blockfrost/  Direct Blockfrost HTTP calls (read-only)  │
│    Sidecar/     HTTP client → Node sidecar                │
│    Api/         CORS, rate-limit, response, validator     │
│    Webhook/     HMAC-signed outbound webhooks             │
│    Cip25/       CIP-25 metadata validator                 │
└───────────────────┬───────────────────────────────────────┘
                    │
          ┌─────────┴──────────┐
          │                    │
          ▼                    ▼
  ┌───────────────┐    ┌──────────────────────────────────┐
  │  MySQL 8+     │    │  Node.js/TypeScript Sidecar       │
  │  (rarefolio   │    │  (port 4000, ideally off web root)│
  │   database)   │    │                                  │
  │               │    │  Routes:                         │
  │  qd_tokens    │    │  GET  /health                    │
  │  qd_mint_queue│    │  GET  /mint/policy-id            │
  │  qd_presales  │    │  POST /mint/prepare  ← Mesh SDK  │
  │  qd_users     │    │  POST /mint/submit               │
  │  qd_wallets   │    │  GET  /sync/token/:unit          │
  │  qd_listings  │    │  GET  /sync/policy/:policyId     │
  │  qd_orders    │    │  GET  /asset/:unit               │
  │  qd_nft_      │    │  GET  /handle/:handle            │
  │    activity   │    └──────────────┬───────────────────┘
  │  gifts        │                   │
  │  ada_handles  │                   │  HTTPS
  │  royalty_     │                   ▼
  │    ledger     │    ┌──────────────────────────────────┐
  └───────────────┘    │  Blockfrost API                  │
                       │  (Cardano chain data + tx submit) │
                       └──────────────┬───────────────────┘
                                      │
                                      ▼
                       ┌──────────────────────────────────┐
                       │  Cardano blockchain               │
                       │  (preprod testnet / mainnet)      │
                       └──────────────────────────────────┘

                       ┌──────────────────────────────────┐
PHP Webhook sender ──► │  rarefolio.io (main static site) │
(Webhook/Sender.php)   │  api/webhook/mint-complete.php   │
HMAC-SHA256 signed     │  api/webhook/ownership-change.php│
                       └──────────────────────────────────┘
```

## Component responsibilities

### PHP application (`src/`)
The PHP layer is the control plane. It handles all auth, admin workflows, API
responses, and database reads/writes. It never builds Cardano transactions
directly — all chain operations go through the sidecar.

### Node.js sidecar (`sidecar/`)
The only component that talks to Cardano in a write capacity. Responsible for:
- Building minting transactions using `@meshsdk/core`
- Signing with the server-side policy wallet (`POLICY_MNEMONIC`)
- Submitting signed transactions to Blockfrost
- Resolving current asset ownership from Blockfrost

The sidecar should run as a persistent process (`pm2` / systemd) and should
NOT be web-accessible directly — the PHP app proxies to it via localhost.

### MySQL database
The platform's internal record of truth. The on-chain state is the ultimate
truth, but the database is what drives the marketplace UI and API responses.
Ownership and chain data are synced from Blockfrost via the sidecar's
`/sync/` routes and persisted here.

### Blockfrost
Read-only chain queries (asset metadata, addresses, UTxOs) plus the submission
endpoint for signed transactions. Both the PHP `Blockfrost/Client.php` and the
Node sidecar use separate Blockfrost connections with the same API key.

### Outbound webhooks
When a mint confirms or ownership changes, `Webhook/Sender.php` POSTs a
HMAC-SHA256 signed JSON payload to the main `rarefolio.io` site so it can
update `verify.html` and `nft.html` in real time.

## Data flow: admin mint
```
Admin browser
  │  clicks "Build & sign"
  ▼
mint-detail.php (JS)
  │  POST /admin/mint-action.php  { action: prepare_json }
  ▼
mint-action.php (PHP)
  │  SidecarClient::prepareMint()
  ▼
Sidecar POST /mint/prepare
  │  getPolicyWallet() → AppWallet (POLICY_MNEMONIC)
  │  getForgingScript() → native script (RequireSignature or RequireAllOf)
  │  Transaction.build() → unsigned CBOR
  │  wallet.signTx()    → signed CBOR
  │  return { cbor_hex, policy_id }
  ▼
Admin browser
  │  clicks "Submit to chain"
  ▼
mint-action.php (PHP)
  │  SidecarClient::submitMint(cbor_hex)
  ▼
Sidecar POST /mint/submit
  │  BlockFrostAPI.txSubmit(cbor_hex)
  │  return { tx_hash }
  ▼
mint-action.php (PHP)
  │  UPDATE qd_mint_queue SET status='submitted', tx_hash=...
  ▼
Admin clicks "Check confirmation"
  ▼
mint-action.php (PHP) action=confirm
  │  Blockfrost\Client::tx(tx_hash) — polls until confirmed
  │  upsertQdToken() — updates qd_tokens
  │  (future) INSERT qd_nft_activity (event_type='mint')
  ▼
Webhook\Sender::send('mint-complete', ...)
  ▼
rarefolio.io webhook receiver
```

## Environment variables summary

See `.env.example` for the full annotated list. Key variables:

| Variable | Where used | Purpose |
|---|---|---|
| `DB_*` | PHP | MySQL connection |
| `BLOCKFROST_API_KEY` | PHP + Sidecar | Chain queries |
| `SIDECAR_BASE_URL` | PHP | Sidecar HTTP address |
| `ADMIN_USER` / `ADMIN_PASS` | PHP | Admin login |
| `CORS_ALLOWED_ORIGINS` | PHP | Public API origin whitelist |
| `PUBLIC_SITE_WEBHOOK_SECRET` | PHP | Outbound webhook HMAC key |
| `POLICY_MNEMONIC` | Sidecar | Policy wallet (minting key) |
| `POLICY_LOCK_SLOT` | Sidecar | Optional time-lock slot |
