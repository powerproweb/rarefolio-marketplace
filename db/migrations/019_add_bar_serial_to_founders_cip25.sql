-- After minting, upsertQdToken overwrites cip25_json with the wrapped CIP-25 format
-- {"policy_id": {"asset_name": {"name":...,"image":...}}}. The bars_show.php
-- endpoint looks for $.bar_serial at the top level of cip25_json and doesn't find it.
-- This migration adds bar_serial at the top level so the bars API resolves correctly.
-- JSON_SET is idempotent — safe to re-run.

UPDATE qd_tokens
SET
    cip25_json = JSON_SET(cip25_json, '$.bar_serial', 'E101837'),
    updated_at = NOW()
WHERE collection_slug = 'silverbar-01-founders';

UPDATE qd_mint_queue
SET
    cip25_json = JSON_SET(cip25_json, '$.bar_serial', 'E101837'),
    updated_at = NOW()
WHERE collection_slug = 'silverbar-01-founders';
