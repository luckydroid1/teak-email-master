<?php
/**
 * credits.php — ledger-based credits system (non-expiring).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function credit_balance(int $uid): int {
    $st = db()->prepare('SELECT balance_after FROM ia_credit_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$uid]);
    $r = $st->fetch();
    return $r ? (int)$r['balance_after'] : 0;
}

/**
 * Tambah/kurangi credits dengan catatan ledger (atomic).
 * @return array [ok: bool, error?: string, balance?: int]
 */
function credit_mutate(int $uid, int $delta, string $type, string $ref = ''): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $bal = credit_balance($uid);
        $new = $bal + $delta;
        if ($new < 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Insufficient credits'];
        }
        $st = $pdo->prepare(
            'INSERT INTO ia_credit_ledger (user_id, delta, balance_after, type, ref) VALUES (?,?,?,?,?)'
        );
        $st->execute([$uid, $delta, $new, $type, $ref]);
        $pdo->commit();
        return ['ok' => true, 'balance' => $new];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Credit operation failed'];
    }
}

/** Pastikan saldo cukup. */
function credit_assert(int $uid, int $amount): bool {
    return credit_balance($uid) >= $amount;
}

/** Riwayat ledger user (terbaru dulu). */
function credit_history(int $uid, int $limit = 50): array {
    $st = db()->prepare(
        'SELECT delta, balance_after, type, ref, created_at FROM ia_credit_ledger
         WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit
    );
    $st->execute([$uid]);
    return $st->fetchAll();
}
