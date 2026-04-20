# RareFolio — Media & IPFS Workflow

## Overview

Every CNFT's artwork must be pinned to IPFS before minting. The IPFS CID
becomes part of the CIP-25 `image` field and is immutable once minted on-chain.

## Recommended IPFS pinning services

| Service | Free tier | Notes |
|---|---|---|
| Pinata | 1 GB | Best UX, reliable gateway |
| nft.storage | Unlimited (filecoin-backed) | Free but slower |
| Self-hosted IPFS | N/A | Full control; requires infra |

## File format for Founders Block 88

- File format: JPEG (`.jpg`) — declared in `mediaType: image/jpeg` in CIP-25
- Recommended resolution: 2000×2000px minimum
- Colour profile: sRGB
- File size: target < 5 MB per token for gateway performance

## Pinning workflow (Pinata example)

```bash
# 1. Upload via Pinata web UI or API
curl -X POST https://api.pinata.cloud/pinning/pinFileToIPFS \
  -H "Authorization: Bearer $PINATA_JWT" \
  -F "file=@qd-silver-0000705.jpg" \
  -F "pinataMetadata={\"name\":\"qd-silver-0000705\"}"

# Response includes IpfsHash — this IS the CID.
# e.g. { "IpfsHash": "QmXyz...", ... }
```

The IPFS URI to use in CIP-25 metadata is: `ipfs://QmXyz...`

## Updating the Founders seed migration

After pinning all 8 artwork files, update
`db/migrations/007_seed_founders_block88_tokens.sql` by replacing every
occurrence of `REPLACE_WITH_CID/qd-silver-XXXXXXX.jpg` with the real URI:

```sql
-- Before:
'image', 'ipfs://REPLACE_WITH_CID/qd-silver-0000705.jpg',

-- After (example):
'image', 'ipfs://QmXyz.../qd-silver-0000705.jpg',
-- OR if each file is pinned individually (different CIDs):
'image', 'ipfs://QmAbc123...',
```

After editing, re-run the migration runner. The migration uses
`ON DUPLICATE KEY UPDATE` so it is safe to re-apply:

```powershell
php db/migrate.php
```

The `migrate.php` runner records each filename in `schema_migrations`. Since
`007_seed_founders_block88_tokens.sql` was already applied, you must either:

**Option A** (clean slate preprod only): Delete migration 007's record and re-run
```sql
DELETE FROM schema_migrations WHERE filename = '007_seed_founders_block88_tokens.sql';
```

**Option B**: Apply the CID update directly via the admin or a standalone SQL script
without going through the migration runner.

## Also update qd_mint_queue

If the mint queue rows were created before pinning, update `image_ipfs_cid`:

```sql
UPDATE qd_mint_queue SET image_ipfs_cid = 'QmXyz...' WHERE rarefolio_token_id = 'qd-silver-0000705';
-- repeat for each token
```

## Verify the IPFS link before minting

Always verify your CID resolves before minting — once on-chain the image URI
cannot be changed.

```bash
# Verify via Pinata gateway:
curl -I https://gateway.pinata.cloud/ipfs/QmXyz...

# Or via public IPFS gateway:
curl -I https://ipfs.io/ipfs/QmXyz...

# Expect HTTP 200 with Content-Type: image/jpeg
```

## IPFS URI format in CIP-25

CIP-25 accepts `ipfs://` URIs directly. The standard format is:

```json
{
  "name": "Founders #1 — The Archivist",
  "image": "ipfs://QmXyz...",
  "mediaType": "image/jpeg",
  "description": "..."
}
```

Do NOT use `https://ipfs.io/ipfs/...` as the on-chain image value — use the
`ipfs://` scheme so wallets and explorers can resolve via their preferred gateway.

## Naming convention

Pin each artwork file under its `rarefolio_token_id` for easy reference:

| Token ID | Filename | Character |
|---|---|---|
| qd-silver-0000705 | qd-silver-0000705.jpg | The Archivist |
| qd-silver-0000706 | qd-silver-0000706.jpg | The Cartographer |
| qd-silver-0000707 | qd-silver-0000707.jpg | The Sentinel |
| qd-silver-0000708 | qd-silver-0000708.jpg | The Artisan |
| qd-silver-0000709 | qd-silver-0000709.jpg | The Scholar |
| qd-silver-0000710 | qd-silver-0000710.jpg | The Ambassador |
| qd-silver-0000711 | qd-silver-0000711.jpg | The Mentor |
| qd-silver-0000712 | qd-silver-0000712.jpg | The Architect |
