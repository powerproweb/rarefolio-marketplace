<?php
declare(strict_types=1);

$envPath = __DIR__ . '/../.env';
if (!is_file($envPath)) {
    fwrite(STDERR, "missing .env\n");
    exit(1);
}

$env = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($lines)) {
    fwrite(STDERR, "could not read .env\n");
    exit(1);
}
foreach ($lines as $line) {
    $line = trim((string) $line);
    if ($line === '' || $line[0] === '#') continue;
    $eq = strpos($line, '=');
    if ($eq === false) continue;
    $k = trim(substr($line, 0, $eq));
    $v = trim(substr($line, $eq + 1));
    $env[$k] = $v;
}

$host = (string) ($env['DB_HOST'] ?? '');
$name = (string) ($env['DB_NAME'] ?? '');
$user = (string) ($env['DB_USER'] ?? '');
$pass = (string) ($env['DB_PASS'] ?? '');
if ($host === '' || $name === '' || $user === '') {
    fwrite(STDERR, "missing DB env\n");
    exit(1);
}

$tokenId = 'qd-silver-0000705';
$newOwner = 'addr_test1qzcs3jcnnemzpkmcw2swetn3t04tca4cw33qa6u06pdfjcmwrsncarlcyqcls7a59hueldz4ljt4xqfdpu9f35y4uuaq4fnpqn';
$txHash = 'a6380851b7474a6fa2a82df3ce8f553cf673deaaafbdcbdbbb07228caa8599cf';

$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$sel = $pdo->prepare('SELECT id, rarefolio_token_id, current_owner_wallet, updated_at FROM qd_tokens WHERE rarefolio_token_id = ? LIMIT 1');
$sel->execute([$tokenId]);
$before = $sel->fetch();
if (!$before) {
    fwrite(STDERR, "token row not found\n");
    exit(1);
}

$upd = $pdo->prepare('UPDATE qd_tokens SET current_owner_wallet = ?, updated_at = NOW() WHERE rarefolio_token_id = ?');
$upd->execute([$newOwner, $tokenId]);

$sel->execute([$tokenId]);
$after = $sel->fetch();

$out = [
    'ok' => true,
    'token_id' => $tokenId,
    'tx_hash' => $txHash,
    'before_owner' => $before['current_owner_wallet'] ?? null,
    'after_owner' => $after['current_owner_wallet'] ?? null,
    'updated_at' => $after['updated_at'] ?? null,
];
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
