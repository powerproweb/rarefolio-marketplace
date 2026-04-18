<?php
declare(strict_types=1);

use RareFolio\Api\Response;
use RareFolio\Api\Validator;
use RareFolio\Config;
use RareFolio\Db;

/**
 * GET /api/v1/listings
 *
 * Returns tokens currently in a listed state. This is a reasonable
 * placeholder until a dedicated `qd_listings` table (with price, expiry,
 * currency) lands in Phase 2 of the marketplace plan.
 *
 * Query params:
 *   bar     (optional) — filter to one silver bar (e.g. E101837)
 *   limit   (optional) — 1..100, default 20
 *   offset  (optional) — 0..10000, default 0
 */

$barRaw = isset($_GET['bar']) ? (string) $_GET['bar'] : '';
$bar    = null;
if ($barRaw !== '') {
    try {
        $bar = Validator::barSerial($barRaw);
    } catch (InvalidArgumentException $e) {
        Response::badRequest($e->getMessage());
        exit;
    }
}
$limit  = Validator::boundedInt($_GET['limit']  ?? null, 1, 100, 20);
$offset = Validator::boundedInt($_GET['offset'] ?? null, 0, 10000, 0);

if (!Config::get('DB_NAME') || !Config::get('DB_USER')) {
    Response::error(503, 'database not configured');
    exit;
}

try {
    $pdo = Db::pdo();

    $where  = "listing_status IN ('listed_fixed','listed_auction')";
    $binds  = [];

    if ($bar !== null) {
        $where .= "
            AND (
                JSON_UNQUOTE(JSON_EXTRACT(cip25_json, '$.bar_serial'))          = :bar
                OR JSON_UNQUOTE(JSON_EXTRACT(cip25_json, '$.attributes.bar_serial')) = :bar
                OR JSON_UNQUOTE(JSON_EXTRACT(cip25_json, '$.properties.bar_serial')) = :bar
                OR collection_slug LIKE :bar_like
            )
        ";
        $binds[':bar']      = $bar;
        $binds[':bar_like'] = '%' . $bar . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM qd_tokens WHERE $where");
    $countStmt->execute($binds);
    $total = (int) $countStmt->fetchColumn();

    $listStmt = $pdo->prepare("
        SELECT
            rarefolio_token_id, collection_slug, title, character_name, edition,
            listing_status, asset_fingerprint, updated_at
        FROM qd_tokens
        WHERE $where
        ORDER BY updated_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $listStmt->execute($binds);
    $rows = $listStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[api v1 listings_index] ' . $e->getMessage());
    Response::error(500, 'database error');
    exit;
}

Response::ok([
    'total'    => $total,
    'limit'    => $limit,
    'offset'   => $offset,
    'listings' => array_map(fn (array $r): array => [
        'cnft_id'           => $r['rarefolio_token_id'],
        'title'             => $r['title'],
        'character_name'    => $r['character_name'],
        'edition'           => $r['edition'],
        'collection'        => $r['collection_slug'],
        'listing_status'    => $r['listing_status'],
        'asset_fingerprint' => $r['asset_fingerprint'],
        'updated_at'        => $r['updated_at'],
    ], $rows),
]);
