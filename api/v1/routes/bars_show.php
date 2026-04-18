<?php
declare(strict_types=1);

use RareFolio\Api\Response;
use RareFolio\Api\Validator;
use RareFolio\Config;
use RareFolio\Db;

/**
 * GET /api/v1/bars/{serial}
 *
 * Returns aggregate stats for a physical silver bar identified by its serial
 * (e.g. "E101837"). Looks up tokens whose CIP-25 metadata or collection slug
 * references that bar.
 *
 * @var array{serial:string} $params supplied by the router
 */

try {
    $serial = Validator::barSerial((string) ($params['serial'] ?? ''));
} catch (InvalidArgumentException $e) {
    Response::badRequest($e->getMessage());
    exit;
}

if (!Config::get('DB_NAME') || !Config::get('DB_USER')) {
    Response::error(503, 'database not configured');
    exit;
}

try {
    $pdo = Db::pdo();

    // Match bar by either CIP-25 JSON attribute OR by collection_slug suffix.
    // This handles both modern tokens (JSON attribute present) and early seeds
    // where the slug is the only identifying marker.
    $slugLike = '%' . $serial . '%';

    $sql = "
        SELECT
            COUNT(*)                                                        AS total_tokens,
            SUM(primary_sale_status IN ('minted','sold','sold_pre_marketplace')) AS minted_tokens,
            SUM(listing_status IN ('listed_fixed','listed_auction'))        AS listed_tokens,
            MIN(minted_at)                                                  AS first_mint_at,
            MAX(updated_at)                                                 AS last_updated_at
        FROM qd_tokens
        WHERE
            JSON_UNQUOTE(JSON_EXTRACT(cip25_json, '$.bar_serial'))          = :serial_exact
            OR JSON_UNQUOTE(JSON_EXTRACT(cip25_json, '$.attributes.bar_serial')) = :serial_exact
            OR JSON_UNQUOTE(JSON_EXTRACT(cip25_json, '$.properties.bar_serial')) = :serial_exact
            OR collection_slug LIKE :slug_like
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':serial_exact' => $serial,
        ':slug_like'    => $slugLike,
    ]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[api v1 bars_show] ' . $e->getMessage());
    Response::error(500, 'database error');
    exit;
}

$total = (int) ($row['total_tokens'] ?? 0);

if ($total === 0) {
    Response::notFound('bar not found: ' . $serial);
    exit;
}

Response::ok([
    'bar_serial'      => $serial,
    'total_tokens'    => $total,
    'minted_tokens'   => (int) ($row['minted_tokens'] ?? 0),
    'listed_tokens'   => (int) ($row['listed_tokens'] ?? 0),
    'first_mint_at'   => $row['first_mint_at'],
    'last_updated_at' => $row['last_updated_at'],
    'physical' => [
        'weight_oz' => 100,
        'purity'    => '.999',
        'material'  => 'silver',
    ],
]);
