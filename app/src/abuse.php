<?php
/**
 * abuse.php — anti-abuse: rate limit, trust tiers, honeypot, risk scoring.
 * Prinsip: user normal lancar, abuse jadi mahal & lambat.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Rate limit counter per bucket per jam. */
function rate_limit_check(string $bucket, int $max): bool {
    $period = date('YmdH');
    $pdo = db();
    $st = $pdo->prepare(
        'INSERT INTO ia_rate_limits (bucket, period, hits) VALUES (?,?,1)
         ON DUPLICATE KEY UPDATE hits = hits + 1'
    );
    $st->execute([$bucket, $period]);
    $st = $pdo->prepare('SELECT hits FROM ia_rate_limits WHERE bucket = ? AND period = ?');
    $st->execute([$bucket, $period]);
    $hits = (int)$st->fetchColumn();
    return $hits <= $max;
}

/** Trust tier gating. */
function trust_limits(int $uid): array {
    $st = db()->prepare('SELECT trust_tier, created_at FROM ia_users WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    $age_days = (int)floor((time() - strtotime($u['created_at'])) / 86400);
    $tier = (int)$u['trust_tier'];

    // Trust increases gradually based on account age
    if ($age_days >= 60 && $tier < 3) {
        db()->prepare('UPDATE ia_users SET trust_tier = 3 WHERE id = ?')->execute([$uid]);
        $tier = 3;
    } elseif ($age_days >= 14 && $tier < 2) {
        db()->prepare('UPDATE ia_users SET trust_tier = 2 WHERE id = ?')->execute([$uid]);
        $tier = 2;
    }

    $map = [
        1 => ['max_inboxes_per_hour' => 3,  'max_inboxes' => 5,  'max_api_per_hour' => 60],
        2 => ['max_inboxes_per_hour' => 5,  'max_inboxes' => 15, 'max_api_per_hour' => 120],
        3 => ['max_inboxes_per_hour' => 10, 'max_inboxes' => 999, 'max_api_per_hour' => 600],
        4 => ['max_inboxes_per_hour' => 20, 'max_inboxes' => 999, 'max_api_per_hour' => 1200],
        5 => ['max_inboxes_per_hour' => 50, 'max_inboxes' => 999, 'max_api_per_hour' => 3000],
    ];
    return $map[$tier] ?? $map[3];
}

/** Login lockout (5 fails → 5 min). Key: ip. Uses DB (no APCu dependency). */
function abuse_fail_attempt(string $ip): void {
    db()->prepare(
        'INSERT INTO ia_rate_limits (bucket, period, hits) VALUES (?,?,1)
         ON DUPLICATE KEY UPDATE hits = hits + 1'
    )->execute(['fail:' . $ip, date('YmdH')]);
}

function abuse_clear_attempts(string $ip): void {
    db()->prepare('DELETE FROM ia_rate_limits WHERE bucket = ?')->execute(['fail:' . $ip]);
}

function abuse_lockout_remaining(string $ip): int {
    $st = db()->prepare('SELECT hits FROM ia_rate_limits WHERE bucket = ? AND period = ?');
    $st->execute(['fail:' . $ip, date('YmdH')]);
    $hits = (int)$st->fetchColumn();
    return $hits >= 5 ? 300 : 0;
}

/** Risk score signup (0-10). Dihitung dari domain email. */
function abuse_risk_score(string $email_domain): int {
    $score = 0;
    $tld = strtolower(substr($email_domain, strrpos($email_domain, '.') + 1));
    $high_risk_tlds = ['ru', 'su', 'by', 'ir', 'cn', 'tk', 'ml', 'ga', 'cf', 'click', 'download', 'date', 'work', 'stream', 'men', 'loan'];
    if (in_array($tld, $high_risk_tlds, true)) $score += 4;
    $anon_domains = ['protonmail.com', 'proton.me', 'duck.com', 'guerrillamail.com', 'mailinator.com', 'temp-mail.org', '10minutemail.com', 'sharklasers.com'];
    if (in_array($email_domain, $anon_domains, true)) $score += 3;
    return $score;
}

/** Cek honeypot: jika email ke honeypot → auto-suspend + ban IP. */
function honeypot_hit(string $email_address): bool {
    $lp = strtolower(substr($email_address, 0, strpos($email_address, '@')));
    return in_array($lp, cfg()['honeypots'], true);
}

/** Audit log helper. */
function audit(?int $uid, string $action, string $ip = '', string $detail = ''): void {
    if ($ip === '') {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    }
    db()->prepare(
        'INSERT INTO ia_audit (user_id, action, ip, detail) VALUES (?,?,?,?)'
    )->execute([$uid, $action, $ip, $detail]);
}
