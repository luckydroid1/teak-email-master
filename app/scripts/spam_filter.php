<?php
/**
 * spam_filter.php — Background spam daemon & quarantine scanner.
 * Scans all active mailboxes and moves detected spam/scam/phishing emails to .Junk folder.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/mailcow.php';
require_once __DIR__ . '/../src/spam.php';

try {
    $pdo = db();
    $st = $pdo->query("SELECT email_address FROM ia_inboxes WHERE status = 'active'");
    $inboxes = $st->fetchAll(PDO::FETCH_COLUMN);

    $total_moved = 0;
    foreach ($inboxes as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $moved = spam_auto_filter_inbox($email);
        $total_moved += $moved;
    }

    if ($total_moved > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Quarantined $total_moved spam messages.\n";
    }
} catch (Throwable $e) {
    error_log("[spam_filter] Error: " . $e->getMessage());
}
