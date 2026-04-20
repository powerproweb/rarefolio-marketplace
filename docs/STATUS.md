# RareFolio Marketplace — Project Status

**Last updated:** April 2026
**Branch:** `main`

---

## What is shipped

### Phase 1 — Scaffold (complete)
- Database schema: `qd_tokens`, `qd_mint_queue`, `qd_presales`, `gifts`, `ada_handles`, `royalty_ledger`
- Founders Block 88 seed: 8 CNFTs in `qd_tokens` (`qd-silver-0000705` through `0000712`), status `unminted`
- Admin dashboard: login gate (session auth, CSRF, lockout), mint queue, CIP-25 metadata builder
- PHP src layer: `Config`, `Db`, `Blockfrost/Client`, `Cip25/Validator`, `Sidecar/Client`
- Migration runner: `db/migrate.php`
- Test suite: `tests/test_api_router.php`, `test_cip25_validator.php`, `test_env_pair.php`, `test_founders_seed_static.php`, `test_webhook_signer.php`

### Phase 1.5 — Public API + Webhook Bridge (complete)
- Read-only public API at `api/v1/`: `health`, `tokens/{id}`, `bars/{serial}`, `listings`, route index
- CORS exact-origin whitelist, per-IP token-bucket rate limiting
- Signed outbound webhooks to main site (`mint-complete`, `ownership-change`)
- PHP src additions: `Api/Cors`, `Api/RateLimit`, `Api/Response`, `Api/Validator`, `Webhook/Sender`, `Webhook/Signer`
- Docs: `docs/API.md`, `docs/WEBHOOKS.md`, `docs/CONFIG.md`

### Phase 2 Foundation (complete as of this commit)
- **Sidecar upgraded to v0.2.0**: `@meshsdk/core` wired in; `POST /mint/prepare` builds and signs real Cardano minting transactions using the server-side `POLICY_MNEMONIC`; `POST /mint/submit` broadcasts via Blockfrost; `GET /mint/policy-id` derives the stable policy ID
- **Ownership sync**: `GET /sync/token/:unit` and `GET /sync/policy/:policyId` — PHP admin can call these to keep `qd_tokens.current_owner_wallet` current
- **New schema migrations** (008–012): `qd_users`, `qd_wallets`, `qd_listings`, `qd_orders`, `qd_nft_activity`
- **Listings API upgraded**: `GET /api/v1/listings` now queries `qd_listings` (with real price and format) when the table exists, falls back to `qd_tokens.listing_status` otherwise
- **Admin mint flow**: "Build & sign" → "Submit to chain" two-step flow; `mint-action.php` handles `submit_json` action
- **New PHP client methods**: `Sidecar\Client::submitMint()`, `Sidecar\Client::syncToken()`

---

## Blockers before first real mint

These items require real-world inputs that cannot be generated in code:

1. **Artwork** — Final artwork files for all 8 Founders Block 88 CNFTs must be produced
2. **IPFS pinning** — Each artwork file must be pinned (Pinata / nft.storage / self-hosted IPFS) and a CID obtained. See `docs/MEDIA.md`
3. **Update seed migration** — Replace `REPLACE_WITH_CID` placeholders in `db/migrations/007_seed_founders_block88_tokens.sql` with real `ipfs://Qm...` CIDs
4. **Policy mnemonic** — Generate a 24-word mnemonic for the policy wallet, add to `sidecar/.env` as `POLICY_MNEMONIC`; fund the wallet address with ~5 ADA on preprod. See `docs/CARDANO.md`
5. **Record policy ID** — Call `GET /mint/policy-id` on the running sidecar; update `policy_id` in `qd_tokens` for all 8 Founders rows (replace the zero-filled placeholder `0000...`)

---

## What is next (Phase 2 completion)

- [ ] Pin artwork and update CIDs
- [ ] Generate + fund policy wallet (preprod)
- [ ] Run `php db/migrate.php` to apply migrations 008–012
- [ ] Call `GET /mint/policy-id`; record the real policy ID in `qd_tokens` via the admin
- [ ] Mint Founders #1 (`qd-silver-0000705`) on preprod through the admin dashboard
- [ ] Verify: `GET /api/v1/tokens/qd-silver-0000705` returns correct chain data
- [ ] Add `mint` row to `qd_nft_activity` after confirmed mint
- [ ] Repeat for Founders #2–#8

---

## Phase 3 (not started)

- Secondary listings create/edit UI in admin + public
- Offer system and auction engine
- Real-time notifications
- Expanded webhook events for sale + transfer

## Phase 4 (not started)

- Editorial / CMS layer
- Rarity and trait views
- Watchlists, follows, collector achievements

## Phase 5 (not started)

- Fiat rails
- Multi-chain expansion
- CIP-68 rich metadata

---

## Known technical debt

- `qd_tokens.current_owner_user_id` FK to `qd_users` is not yet enforced (column exists, FK commented out pending user table migration run)
- `royalty_ledger.listing_id` and `royalty_ledger.order_id` FKs are placeholders until `qd_listings` and `qd_orders` are populated
- Admin auth (`ADMIN_USER` / `ADMIN_PASS`) is a single shared credential; should be replaced with per-user auth once `qd_users` is populated
- `api/v1/routes/bars_show.php` is a stub; needs real silver bar aggregation logic once tokens are minted
