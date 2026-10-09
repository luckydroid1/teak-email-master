<?php
/**
 * redeem.php — AppSumo code redemption.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/credits.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';

/** Tier table: [name, credits, inbox_slots, domains, api_full, retention_days, price_usd] */
const TIER_TABLE = [
    1 => ['name' => 'Starter Runtime',   'price' => 1,  'credits' => 1000,   'inbox_slots' => 5,   'domains' => 1,  'api' => 'basic',  'retention' => 30],
    2 => ['name' => 'Agent Pro',          'price' => 5,  'credits' => 5000,   'inbox_slots' => 15,  'domains' => 3,  'api' => 'full',   'retention' => 60],
    3 => ['name' => 'Business Agency',    'price' => 10, 'credits' => 15000,  'inbox_slots' => 30,  'domains' => 5,  'api' => 'full',   'retention' => 90],
    4 => ['name' => 'Enterprise Cluster', 'price' => 25, 'credits' => 50000,  'inbox_slots' => 100, 'domains' => 15, 'api' => 'full',   'retention' => 180],
    5 => ['name' => 'Unlimited Scale',    'price' => 50, 'credits' => 150000, 'inbox_slots' => 250, 'domains' => 50, 'api' => 'full',   'retention' => 365],
];

function tier_info(int $tier): ?array {
    $def = TIER_TABLE[$tier] ?? null;
    if (!$def) return null;

    $price = setting_get("tier_{$tier}_price");
    $credits = setting_get("tier_{$tier}_credits");
    $inbox_slots = setting_get("tier_{$tier}_inbox_slots");
    $domains = setting_get("tier_{$tier}_domains");
    $retention = setting_get("tier_{$tier}_retention");

    return [
        'price'       => ($price !== null && $price !== '') ? (float)$price : $def['price'],
        'credits'     => ($credits !== null && $credits !== '') ? (int)$credits : $def['credits'],
        'inbox_slots' => ($inbox_slots !== null && $inbox_slots !== '') ? (int)$inbox_slots : $def['inbox_slots'],
        'domains'     => ($domains !== null && $domains !== '') ? (int)$domains : $def['domains'],
        'api'         => $def['api'],
        'retention'   => ($retention !== null && $retention !== '') ? (int)$retention : $def['retention'],
    ];
}

/** Redeem code. Requires user to be logged in. */
function redeem_code(int $uid, string $code): array {
    $code = preg_replace('/\s+/', '', strtoupper(trim($code)));
    $st = db()->prepare('SELECT * FROM ia_codes WHERE code = ?');
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row) {
        audit($uid, 'redeem_fail', 'web', 'invalid code');
        return ['error' => 'Invalid code'];
    }
    if ($row['status'] === 'redeemed') {
        audit($uid, 'redeem_fail', 'web', 'already redeemed');
        return ['error' => 'Code already redeemed'];
    }
    $tier = (int)$row['tier'];
    $credits = (int)$row['credits'];
    $res = credit_mutate($uid, $credits, 'redeem', "tier=$tier code=$code");
    if (!$res['ok']) return ['error' => $res['error']];
    db()->prepare("UPDATE ia_codes SET status='redeemed', redeemed_by=?, redeemed_at=NOW() WHERE id = ?")
        ->execute([$uid, $row['id']]);
    audit($uid, 'redeem', 'web', "tier=$tier credits=$credits");
    return ['ok' => true, 'tier' => $tier, 'credits' => $credits, 'balance' => $res['balance']];
}

/** Tier user dari kode yang pernah di-redeem. */
function user_tier(int $uid): int {
    $st = db()->prepare(
        'SELECT tier FROM ia_codes WHERE redeemed_by = ? ORDER BY redeemed_at DESC LIMIT 1'
    );
    $st->execute([$uid]);
    return (int)($st->fetchColumn() ?: 1);
}

/** Generate N kode AppSumo (dipakai script generate_codes.php). */
function generate_codes(int $count, int $tier): array {
    $codes = [];
    $st = db()->prepare('INSERT INTO ia_codes (code, tier, credits) VALUES (?,?,?)');
    for ($i = 0; $i < $count; $i++) {
        $code = 'AS-' . strtoupper(bin2hex(random_bytes(6)));  // AS-XXXXXXXXXXXX
        $t = tier_info($tier);
        if (!$t) throw new RuntimeException('Invalid tier');
        $st->execute([$code, $tier, $t['credits']]);
        $codes[] = $code;
    }
    return $codes;
}
