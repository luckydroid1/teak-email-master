<?php
/**
 * cleanup_retention.php — Automated physical Maildir & database retention cleanup.
 * Run via cron daily: 0 4 * * * php /var/www/inboxapp/app/scripts/cleanup_retention.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/mailcow.php';
require_once __DIR__ . '/../src/inbox.php';

echo "=== [Retention Cleanup] Starting " . date('Y-m-d H:i:s') . " ===\n";

$pdo = db();

// 1. Mark expired active inboxes as deleted
$st = $pdo->prepare("UPDATE ia_inboxes SET status = 'deleted' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()");
$st->execute();
$expired_inboxes = $st->rowCount();
echo "[1] Expired inboxes marked as deleted: $expired_inboxes\n";

// 2. Physical Maildir cleanup for active inboxes exceeding retention_days
$active_inboxes = $pdo->query("SELECT email_address, local_part, domain, retention_days FROM ia_inboxes WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
$purged_files = 0;

foreach ($active_inboxes as $ib) {
    $retention_days = max(1, (int)($ib['retention_days'] ?? 30));
    $cutoff = time() - ($retention_days * 86400);
    $dom = $ib['domain'];
    $lp = $ib['local_part'];
    
    // Check standard maildir paths
    $paths = [
        "/var/mail/$dom/$lp/Maildir",
        "/var/vmail/$dom/$lp/Maildir",
        "/var/vmail/$dom/$lp"
    ];
    
    foreach ($paths as $base) {
        if (!is_dir($base)) continue;
        foreach (['cur', 'new', 'tmp'] as $sub) {
            $dir = "$base/$sub";
            if (!is_dir($dir)) continue;
            $files = glob("$dir/*");
            if (!$files) continue;
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff) {
                    if (@unlink($file)) {
                        $purged_files++;
                    }
                }
            }
        }
    }
}
echo "[2] Purged old email files beyond retention window: $purged_files files\n";

// 3. Remove Mailcow mailboxes and physical directories for inboxes deleted > 7 days ago
$st_deleted = $pdo->query("SELECT email_address, local_part, domain FROM ia_inboxes WHERE status = 'deleted' AND updated_at < NOW() - INTERVAL 7 DAY");
$deleted_count = 0;
if ($st_deleted) {
    while ($row = $st_deleted->fetch(PDO::FETCH_ASSOC)) {
        mailcow_delete_mailbox($row['email_address']);
        
        $dom = $row['domain'];
        $lp = $row['local_part'];
        foreach (["/var/mail/$dom/$lp", "/var/vmail/$dom/$lp"] as $p) {
            if (is_dir($p)) {
                @exec("rm -rf " . escapeshellarg($p));
            }
        }
        $deleted_count++;
    }
}
echo "[3] Purged fully deleted mailboxes & directories: $deleted_count\n";

echo "=== [Retention Cleanup] Completed successfully ===\n";
