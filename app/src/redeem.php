<?php
/**
 * redeem.php — AppSumo code redemption.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/credits.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';

/** Tier table: [credits, inbox_slots, domains, api_full, retention_days, price_usd] */
const TIER_TABLE = [
    1 => ['name' => 'Tier 1 Plan', 'price' => 1,  'credits' => 3000,   'inbox_slots' => 3,   'domains' => 1,  'api' => 'basic',  'retention' => 7],
    2 => ['name' => 'Tier 2 Plan', 'price' => 7,  'credits' => 25000,  'inbox_slots' => 25,  'domains' => 3,  'api' => 'full',   'retention' => 14],
    3 => ['name' => 'Tier 3 Plan', 'price' => 17, 'credits' => 70000,  'inbox_slots' => 70,  'domains' => 5,  'api' => 'full',   'retention' => 30],
    4 => ['name' => 'Tier 4 Plan', 'price' => 27, 'credits' => 125000, 'inbox_slots' => 125, 'domains' => 10, 'api' => 'full',   'retention' => 45],
    5 => ['name' => 'Tier 5 Plan', 'price' => 37, 'credits' => 190000, 'inbox_slots' => 190, 'domains' => 20, 'api' => 'full',   'retention' => 60],
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

/** Tier user dari kode voucher, pembayaran PayPal, atau trust_tier. */
function user_tier(int $uid): int {
    $tier = 1;
    try {
        // 1. Check direct user record
        $st = db()->prepare('SELECT trust_tier FROM ia_users WHERE id = ?');
        $st->execute([$uid]);
        $user_tier = (int)$st->fetchColumn();
        if ($user_tier > $tier) $tier = $user_tier;

        // 2. Check completed PayPal payments
        $st2 = db()->prepare('SELECT MAX(tier) FROM ia_payments WHERE user_id = ? AND status = "completed"');
        $st2->execute([$uid]);
        $pay_tier = (int)$st2->fetchColumn();
        if ($pay_tier > $tier) $tier = $pay_tier;

        // 3. Check AppSumo / voucher redemptions
        $st3 = db()->prepare('SELECT MAX(tier) FROM ia_codes WHERE redeemed_by = ?');
        $st3->execute([$uid]);
        $code_tier = (int)$st3->fetchColumn();
        if ($code_tier > $tier) $tier = $code_tier;
    } catch (Throwable $e) {}

    return max(1, min(5, $tier));
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
