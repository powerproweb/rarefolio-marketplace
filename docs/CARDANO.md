# RareFolio — Cardano Operations

## Policy wallet setup

The sidecar uses a server-side "policy wallet" to:
1. Derive the native script policy ID
2. Sign minting transactions
3. Pay minting fees from its own UTxOs

### Generate a mnemonic

```bash
# Option A: via Mesh CLI (after npm install in sidecar/)
npx @meshsdk/core generate-mnemonic

# Option B: via cardano-cli
cardano-cli address key-gen --normal-key --verification-key-file policy.vkey --signing-key-file policy.skey

# Option C: via any CIP-3 compatible wallet (Eternl, Nami, Lace)
# Use "create new wallet" and record the 24 words.
```

Add to `sidecar/.env`:
```
POLICY_MNEMONIC=word1 word2 ... word24
```

**Security**: Never commit the mnemonic. Keep only in `.env` on the server.
Back it up in a password manager alongside the DB credentials.

### Fund the policy wallet

```bash
# 1. Get the policy wallet address:
GET http://localhost:4000/mint/policy-id
# Response includes "policy_addr"

# 2. On preprod: use the Cardano faucet
#    https://docs.cardano.org/cardano-testnets/tools/faucet/
#    Send at least 5 ADA to the policy_addr

# 3. On mainnet: send ADA from your exchange or personal wallet
```

Recommended balance before minting: ≥ 5 ADA (each mint costs ~0.18–0.35 ADA in fees).

---

## Policy script and ID

### Derive the policy ID

```bash
# With the sidecar running:
curl http://localhost:4000/mint/policy-id
```

Response:
```json
{
  "policy_id": "abc123...56hex...",
  "policy_addr": "addr_test1...",
  "lock_slot": null,
  "script_type": "RequireSignature"
}
```

**The policy ID is permanent.** Record it before your first mint and update
`qd_tokens.policy_id` for all tokens in the collection via SQL:

```sql
UPDATE qd_tokens
SET    policy_id = 'abc123...56hex...'
WHERE  collection_slug = 'silverbar-01-founders';
```

Also update `qd_mint_queue.policy_id` for any queued mint rows:

```sql
UPDATE qd_mint_queue
SET    policy_id = 'abc123...56hex...'
WHERE  collection_slug = 'silverbar-01-founders';
```

### Time-lock policy (optional but recommended)

To fix the supply permanently after minting, add a slot-based time lock:

```bash
# Calculate slot for a future date (e.g. 2030-01-01):
node -e "
  const target  = new Date('2030-01-01').getTime() / 1000;
  const genesis = new Date('2022-04-01').getTime() / 1000; // preprod genesis approx
  const slot    = Math.floor(target - genesis + 86400);    // rough estimate
  console.log('POLICY_LOCK_SLOT=' + slot);
"
```

Add to `sidecar/.env`:
```
POLICY_LOCK_SLOT=123456789
```

Once the lock slot passes, no further tokens can be minted under this policy,
giving collectors a provable supply cap.

---

## Mint flow step-by-step

### Pre-flight checklist

1. `GET /health` on the sidecar — `policy_ready: true`
2. Policy wallet funded (≥ 5 ADA)
3. `policy_id` recorded in `qd_tokens` and `qd_mint_queue`
4. IPFS CID populated on the mint queue row (`image_ipfs_cid` column)
5. `status = 'ready'` on the `qd_mint_queue` row

### In the admin dashboard

1. Open `admin/mint.php`, find the token row, click into `mint-detail.php`
2. Verify on-chain identifiers are correct (policy_id, asset_name_hex, CID)
3. Click **"Build & sign tx (sidecar)"** — the sidecar builds a real Cardano tx and returns signed CBOR
4. Review the JSON response (policy_id, asset_name_hex, recipient_addr)
5. Click **"Submit to chain"** — CBOR is broadcast via Blockfrost; `tx_hash` is recorded
6. Click **"Check confirmation"** every ~30 seconds until confirmed on-chain
7. On confirmation, `qd_tokens` is upserted with the real `mint_tx_hash` and `minted_at`

### After confirmation

- The outbound webhook `mint-complete` fires to the main site
- Manually sync the ownership to verify: `GET http://localhost:4000/sync/token/<policy_id><asset_name_hex>`
- Insert a row into `qd_nft_activity` (event_type='mint') — automated in a future sprint

---

## CIP-27 royalty token (not yet implemented)

CIP-27 defines a convention for expressing royalty terms on-chain by minting
a "royalty token" under the same policy before any NFTs are minted.

The royalty token asset name is `royalty_token` (hex: `726f79616c74795f746f6b656e`).
Its CIP-25 metadata encodes `rate` (e.g. `0.08` for 8%) and `addr` (the royalty
payment address).

This is not yet implemented in the sidecar. It is tracked in `qd_mint_queue.royalty_token_ok`.
Until the royalty token is locked on-chain, RareFolio enforces royalties at
the marketplace policy level only (not verifiable by external marketplaces).

---

## Ownership sync

After any transfer, run the sync route to update `qd_tokens.current_owner_wallet`:

```bash
# Single token (unit = policy_id + asset_name_hex)
curl http://localhost:4000/sync/token/<unit>

# All tokens under a policy
curl http://localhost:4000/sync/policy/<policy_id>
```

Then apply the result to the database:

```sql
UPDATE qd_tokens
SET    current_owner_wallet = '<current_owner from response>',
       updated_at = NOW()
WHERE  policy_id = '<policy_id>'
  AND  asset_name_hex = '<asset_name_hex>';
```

A future admin UI or cron job should automate this for every confirmed block
that includes a transaction involving a RareFolio policy ID.

---

## Preprod vs mainnet switching

All configuration is per-environment. To switch networks:

1. Update `BLOCKFROST_NETWORK` and `BLOCKFROST_API_KEY` in both `.env` files
2. Generate a new `POLICY_MNEMONIC` (never reuse preprod keys on mainnet)
3. Call `GET /mint/policy-id` to derive the mainnet policy ID
4. Update `qd_tokens.policy_id` for mainnet rows
5. Fund the mainnet policy wallet address

The database should contain only one network's data per deployment. Run separate
MySQL databases for preprod testing and mainnet production.

---

## Standards reference

- CIP-25 (NFT metadata): https://cips.cardano.org/cip/CIP-25
- CIP-27 (royalty metadata): https://cips.cardano.org/cips/cip27
- CIP-30 (wallet bridge): https://cips.cardano.org/cip/CIP-30
- CIP-68 (datum metadata, future): https://cips.cardano.org/cip/CIP-68
- Mesh SDK docs: https://meshjs.dev/
- Blockfrost API docs: https://docs.blockfrost.io/
