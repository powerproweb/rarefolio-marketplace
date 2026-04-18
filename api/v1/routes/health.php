<?php
declare(strict_types=1);

use RareFolio\Api\Response;
use RareFolio\Config;
use RareFolio\Db;

/**
 * GET /api/v1/health
 *
 * Returns service liveness. Never fails — even if DB is down, we return
 * "ok": true with a "db" field set to "unavailable" so frontends can render
 * a proper "integration partially down" state.
 */

$db = 'skipped';
if (Config::get('DB_NAME') && Config::get('DB_USER')) {
    try {
        Db::pdo()->query('SELECT 1')->fetchColumn();
        $db = 'ok';
    } catch (Throwable) {
        $db = 'unavailable';
    }
}

Response::ok([
    'service'     => 'rarefolio-marketplace-api',
    'version'     => 'v1',
    'environment' => Config::get('APP_ENV', 'unknown'),
    'time_utc'    => gmdate('c'),
    'db'          => $db,
]);
