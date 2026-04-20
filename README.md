# RareFolio.io Marketplace

A Cardano-first curated NFT marketplace. PHP + MySQL backend, Node.js/TypeScript
Cardano sidecar (Mesh SDK), read-only public API, and signed webhook bridge to
the main `rarefolio.io` site.

## Quick start

```powershell
# 1. Configure
Copy-Item .env.example .env            # edit with DB creds + BLOCKFROST_API_KEY
Copy-Item sidecar\.env.example sidecar\.env  # edit with BLOCKFROST_API_KEY + POLICY_MNEMONIC

# 2. Create DB + run all migrations
php db/migrate.php

# 3. Start the sidecar (separate terminal)
cd sidecar && npm install && npm run dev

# 4. Start the PHP dev server
php -S localhost:8080 -t . tests/cli_router.php
# then visit http://localhost:8080/admin/login.php
```

## Documentation

| Document | Purpose |
|---|---|
| `docs/STATUS.md` | **Start here** — what is shipped, blockers, next steps |
| `docs/ARCHITECTURE.md` | System diagram + component responsibilities |
| `docs/CARDANO.md` | Policy setup, mint flow, ownership sync, preprod → mainnet |
| `docs/MEDIA.md` | Artwork pinning, IPFS CID workflow, Founders seed update |
| `docs/CONTRIBUTING.md` | Local setup, migrations, tests, sidecar dev, conventions |
| `docs/API.md` | Public API v1 endpoints + error envelope |
| `docs/WEBHOOKS.md` | Signed outbound webhook format + events |
| `docs/CONFIG.md` | End-to-end config walkthrough (marketplace ↔ main site) |
| `rarefolio_marketplace_php_site_plan.md` | Full product blueprint (Unified Blueprint v2) |

## Stack

- **PHP 8.1+** — admin dashboard, public API, webhook bridge
- **MySQL 8 / MariaDB 10.6+** — primary database (12 migrations)
- **Node.js 20+ / TypeScript** — Cardano sidecar (Mesh SDK, Blockfrost)
- **No Composer dependencies** — pure PHP, no vendor directory
