<?php
/**
 * redeem.php — AppSumo code redemption.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/credits.php';
require_once __DIR__ . '/auth.php';

/** Tier table: [credits, inbox_slots, domains, api_full, retention_days] */
const TIER_TABLE = [
    1 => ['credits' => 1000,  'inbox_slots' => 1,  'domains' => 1,  'api' => 'basic',  'retention' => 7],
    2 => ['credits' => 2500,  'inbox_slots' => 3,  'domains' => 1,  'api' => 'basic',  'retention' => 14],
    3 => ['credits' => 6000,  'inbox_slots' => 8,  'domains' => 2,  'api' => 'full',   'retention' => 30],
    4 => ['credits' => 12000, 'inbox_slots' => 20, 'domains' => 5,  'api' => 'full',   'retention' => 45],
    5 => ['credits' => 25000, 'inbox_slots' => 50, 'domains' => 10, 'api' => 'full',   'retention' => 60],
];

function tier_info(int $tier): ?array {
    return TIER_TABLE[$tier] ?? null;
}

/** Redeem code. Requires user to be logged in. */
function redeem_code(int $uid, string $code): array {
    $code = strtoupper(trim($code));
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
