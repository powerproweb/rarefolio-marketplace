<?php
/**
 * Mint queue — detail view for a single row.
 *
 * Actions (via mint-action.php):
 *   - Prepare payload (sidecar /mint/prepare) and render in JSON viewer
 *   - Mark signed (stub, until Phase 2 tx builder)
 *   - Mark submitted (stub)
 *   - Mark confirmed (calls Blockfrost tx lookup when tx_hash present)
 *   - Mark failed
 *   - Delete draft
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'missing id';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM qd_mint_queue WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'not found';
    exit;
}

$flash = $_GET['flash'] ?? null;
$flashKind = $_GET['kind'] ?? 'ok';

$pageTitle = 'Mint #' . $row['id'] . ' — RareFolio';
require __DIR__ . '/includes/header.php';
?>

<div class="rf-toolbar">
    <a href="/admin/mint.php" class="rf-btn rf-btn-ghost">&larr; Back to queue</a>
    <div class="rf-spacer"></div>
    <span class="rf-pill rf-pill-<?= h($row['status']) ?>"><?= h($row['status']) ?></span>
</div>

<?php if ($flash): ?>
    <div class="rf-alert rf-alert-<?= h($flashKind) ?>"><?= h($flash) ?></div>
<?php endif; ?>

<h1><?= h($row['title']) ?> <small class="rf-mono">#<?= (int)$row['id'] ?></small></h1>
<p class="rf-mono">
    token_id: <strong><?= h($row['rarefolio_token_id']) ?></strong> ·
    collection: <?= h($row['collection_slug']) ?> ·
    edition: <?= h($row['edition'] ?? '—') ?>
    <?php if (!empty($row['character_name'])): ?>
        · character: <?= h($row['character_name']) ?>
    <?php endif; ?>
</p>

<h2>On-chain identifiers</h2>
<table class="rf-table">
    <tr><th>policy_id</th>     <td class="rf-mono"><?= h($row['policy_id'] ?? '(not yet assigned)') ?></td></tr>
    <tr><th>asset_name_hex</th><td class="rf-mono"><?= h($row['asset_name_hex']) ?></td></tr>
    <tr><th>asset_name_utf8</th><td class="rf-mono"><?= h(@hex2bin($row['asset_name_hex']) ?: '') ?></td></tr>
    <tr><th>image CID</th>     <td class="rf-mono"><?= h($row['image_ipfs_cid'] ?? '—') ?></td></tr>
    <tr><th>royalty token</th> <td><?= $row['royalty_token_ok'] ? '<span style="color:var(--rf-ok)">locked</span>' : '<span style="color:var(--rf-warn)">not locked</span>' ?></td></tr>
    <tr><th>tx_hash</th>       <td class="rf-mono"><?= h($row['tx_hash'] ?? '—') ?></td></tr>
    <tr><th>attempts</th>      <td><?= (int)$row['attempts'] ?></td></tr>
    <?php if (!empty($row['error_message'])): ?>
        <tr><th>error</th><td style="color:var(--rf-error)"><?= h($row['error_message']) ?></td></tr>
    <?php endif; ?>
</table>

<h2>CIP-25 metadata (label 721)</h2>
<pre class="rf-code" id="cip25-json"><?= h(json_encode(
    json_decode($row['cip25_json'], true),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
)) ?></pre>

<h2>Actions</h2>
<div class="rf-toolbar">
    <form method="post" action="/admin/mint-action.php" style="display:inline">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="action" value="prepare">
        <button class="rf-btn" type="submit" <?= $row['status'] !== 'ready' ? 'disabled' : '' ?>>
            1) Ask sidecar to prepare
        </button>
    </form>
    <button class="rf-btn" id="btn-sign" <?= $row['status'] !== 'ready' ? 'disabled' : '' ?>>
        1) Build &amp; sign tx (sidecar)
    </button>
    <button class="rf-btn" id="btn-submit" disabled>
        2) Submit to chain
    </button>
    <form method="post" action="/admin/mint-action.php" style="display:inline">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="action" value="confirm">
        <button class="rf-btn rf-btn-ghost" type="submit" <?= empty($row['tx_hash']) ? 'disabled' : '' ?>>
            3) Check confirmation
        </button>
    </form>
    <div class="rf-spacer"></div>
    <form method="post" action="/admin/mint-action.php" style="display:inline"
          onsubmit="return confirm('Mark as failed?')">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="action" value="fail">
        <button class="rf-btn rf-btn-ghost" type="submit">Mark failed</button>
    </form>
    <?php if ($row['status'] === 'draft' || $row['status'] === 'failed'): ?>
        <form method="post" action="/admin/mint-action.php" style="display:inline"
              onsubmit="return confirm('Delete this queued mint? This cannot be undone.')">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button class="rf-btn rf-btn-ghost" type="submit" style="color:var(--rf-error)">Delete</button>
        </form>
    <?php endif; ?>
</div>

<h3>1) Sidecar — build &amp; sign tx</h3>
<pre class="rf-code" id="sidecar-output">(click "1) Build &amp; sign" to ask the sidecar to prepare a real tx)</pre>

<h3>2) Submit to chain</h3>
<pre class="rf-code" id="submit-output">(awaiting a signed CBOR from step 1)</pre>

<script>
/**
 * Phase 2 mint flow — server-side signing via POLICY_MNEMONIC.
 *
 * Step 1  "Build & sign":
 *   Calls POST /admin/mint-action.php (action=prepare_json).
 *   The PHP proxies to the sidecar POST /mint/prepare, which builds and
 *   signs the tx with the policy wallet.  Response: { cbor_hex, policy_id }.
 *
 * Step 2  "Submit":
 *   Calls POST /admin/mint-action.php (action=submit_json) with the cbor_hex.
 *   The PHP proxies to the sidecar POST /mint/submit, which broadcasts via
 *   Blockfrost.  Response: { tx_hash }.  tx_hash is then recorded server-side.
 *
 * (Legacy CIP-30 path: if you ever want the admin wallet to sign instead,
 *  wire api.signTx(cbor_hex, false) before the submit step.)
 */
(function () {
    const btnBuild  = document.getElementById('btn-sign');  // reuse existing button
    const outSc     = document.getElementById('sidecar-output');
    const outSubmit = document.getElementById('submit-output');
    const rowId     = <?= (int)$row['id'] ?>;

    let pendingCbor = null;

    // ------------------------------------------------------------------ step 1
    if (btnBuild) btnBuild.addEventListener('click', async () => {
        btnBuild.disabled = true;
        outSc.textContent = 'Calling sidecar to build + sign tx…';
        outSubmit.textContent = '(awaiting step 1)';
        pendingCbor = null;
        try {
            const resp = await fetch('/admin/mint-action.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id: rowId, action: 'prepare_json' }),
            });
            const data = await resp.json();
            outSc.textContent = JSON.stringify(data, null, 2);

            if (data.error) {
                outSubmit.textContent = 'Step 1 failed: ' + data.error;
                return;
            }
            if (data.cbor_hex) {
                pendingCbor = data.cbor_hex;
                outSubmit.textContent =
                    `Signed CBOR ready (${data.cbor_hex.length} chars).\n` +
                    `policy_id: ${data.policy_id ?? '(see above)'}\n\n` +
                    `Click "2) Submit to chain" to broadcast.`;
                document.getElementById('btn-submit')?.removeAttribute('disabled');
            } else {
                outSubmit.textContent = 'No cbor_hex in response — check sidecar logs.';
            }
        } catch (e) {
            outSc.textContent = 'ERROR: ' + (e?.message ?? String(e));
        } finally {
            btnBuild.disabled = false;
        }
    });

    // ------------------------------------------------------------------ step 2
    document.getElementById('btn-submit')?.addEventListener('click', async () => {
        if (!pendingCbor) {
            outSubmit.textContent = 'No signed CBOR available — run step 1 first.';
            return;
        }
        document.getElementById('btn-submit').disabled = true;
        outSubmit.textContent = 'Submitting to Cardano via sidecar…';
        try {
            const resp = await fetch('/admin/mint-action.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id: rowId, action: 'submit_json', cbor_hex: pendingCbor }),
            });
            const data = await resp.json();
            outSubmit.textContent = JSON.stringify(data, null, 2);
            if (data.tx_hash) {
                outSubmit.textContent += '\n\n✓ tx recorded. Use "Check confirmation" above to verify on-chain.';
                pendingCbor = null;
            }
        } catch (e) {
            outSubmit.textContent = 'ERROR: ' + (e?.message ?? String(e));
        } finally {
            document.getElementById('btn-submit').disabled = false;
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
