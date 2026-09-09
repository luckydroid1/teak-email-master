<?php
/**
 * apikey.php — API key management.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Generate new API key. Returns plaintext (shown once). */
function apikey_create(int $uid): array {
    $key = 'cib_' . bin2hex(random_bytes(16)); // cib_ + 32 hex
    $prefix = substr($key, 0, 12);
    $st = db()->prepare(
        'INSERT INTO ia_api_keys (user_id, key_hash, key_prefix) VALUES (?,?,?)'
    );
    $st->execute([$uid, hash('sha256', $key), $prefix]);
    return ['key' => $key, 'prefix' => $prefix];
}

function apikey_list(int $uid): array {
    $st = db()->prepare(
        'SELECT id, key_prefix, scopes, rate_limit, status, created_at FROM ia_api_keys
         WHERE user_id = ? AND status = \'active\' ORDER BY id DESC'
    );
    $st->execute([$uid]);
    return $st->fetchAll();
}

function apikey_revoke(int $uid, int $key_id): void {
    db()->prepare("UPDATE ia_api_keys SET status='revoked' WHERE id = ? AND user_id = ?")
        ->execute([$key_id, $uid]);
}

/** Validasi Bearer key → user. Rate-limited per key. */
function apikey_auth(string $key): ?array {
    if ($key === '') return null;
    $st = db()->prepare('SELECT * FROM ia_api_keys WHERE key_hash = ? AND status = \'active\'');
    $st->execute([hash('sha256', $key)]);
    $row = $st->fetch();
    if (!$row) return null;
    $uid = (int)$row['user_id'];
    $limits = trust_limits($uid);
    $max = (int)$row['rate_limit'] > 0 ? (int)$row['rate_limit'] : $limits['max_api_per_hour'];
    if (!rate_limit_check("api:$uid", $max)) {
        return ['rate_limited' => true];
    }
    $st = db()->prepare('SELECT * FROM ia_users WHERE id = ? AND status = \'active\'');
    $st->execute([$uid]);
    $user = $st->fetch();
    if (!$user) return null;
    return ['user' => $user, 'key' => $row];
}
