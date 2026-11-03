<?php
/**
 * cleanup_retention.php — Automated 2-Stage Email Lifecycle & Maildir Retention Cleanup.
 *
 * Retention Policy:
 * - Paid Accounts (Tier 1..5): 30 days Active -> 14 days Archive -> Permanent Zero-Data Purge (Day 44)
 * - Free Sandbox (Tier 0): 3 days Active -> 3 days Archive -> Permanent Zero-Data Purge (Day 6)
 * - Spam Quarantine (.Junk): 14 days grace -> Permanent Purge
 *
 * Cron schedule: Daily at 03:00 (0 3 * * * php /var/www/inboxapp/scripts/cleanup_retention.php)
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/mailcow.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/archive.php';

echo "=== [Email Lifecycle & Retention Cleanup] Starting " . date('Y-m-d H:i:s') . " ===\n";

$pdo = db();

// 1. Mark expired active inboxes as deleted
$st = $pdo->prepare("UPDATE ia_inboxes SET status = 'deleted' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()");
$st->execute();
$expired_inboxes = $st->rowCount();
echo "[1] Expired inboxes marked as deleted: $expired_inboxes\n";

// 2. Automated Lifecycle Processing for all active inboxes
$active_inboxes = $pdo->query("SELECT user_id, email_address, local_part, domain FROM ia_inboxes WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

$total_archived = 0;
$total_purged = 0;
$total_spam_purged = 0;

$tier_cache = [];

foreach ($active_inboxes as $ib) {
    $uid = (int)$ib['user_id'];
    if (!isset($tier_cache[$uid])) {
        $tier_cache[$uid] = user_tier($uid);
    }
    $tier = $tier_cache[$uid];

    // Define retention days based on tier
    if ($tier === 0) {
        $active_days = 3;
        $archive_grace_days = 3;
    } else {
        $active_days = 30;
        $archive_grace_days = 14;
    }

    $active_cutoff = time() - ($active_days * 86400);
    $archive_cutoff = time() - ($archive_grace_days * 86400);
    $spam_cutoff = time() - (14 * 86400);

    $email = $ib['email_address'];
    $maildir = mailcow_maildir_path($email);
    if (!is_dir($maildir)) continue;

    $arch_dir = archive_ensure_dir($maildir);
    $junk_dir = "$maildir/.Junk";

    // --- STEP A: Active Inbox -> Grace Archive ---
    foreach (['cur', 'new'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath)) continue;

            $mtime = @filemtime($filepath) ?: 0;
            if ($mtime < $active_cutoff) {
                // Move gracefully to .Archive/cur
                $dest = "$arch_dir/cur/$f";
                if (@rename($filepath, $dest)) {
                    @chmod($dest, 0640);
                    @chgrp($dest, 'postfix');
                    @touch($dest); // Sets archive entry timestamp to NOW so 14-day grace period starts now
                    $total_archived++;
                }
            }
        }
    }

    // --- STEP B: Grace Archive -> Permanent Zero-Data Purge ---
    foreach (['cur', 'new'] as $sub) {
        $dir = "$arch_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath)) continue;

            $mtime = @filemtime($filepath) ?: 0;
            if ($mtime < $archive_cutoff) {
                if (@unlink($filepath)) {
                    $total_purged++;
                }
            }
        }
    }

    // --- STEP C: Spam Quarantine Auto-Purge (> 14 days) ---
    if (is_dir($junk_dir)) {
        foreach (['cur', 'new'] as $sub) {
            $dir = "$junk_dir/$sub";
            if (!is_dir($dir)) continue;
            $files = @scandir($dir);
            if ($files === false) continue;

            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $filepath = "$dir/$f";
                if (!is_file($filepath)) continue;

                $mtime = @filemtime($filepath) ?: 0;
                if ($mtime < $spam_cutoff) {
                    if (@unlink($filepath)) {
                        $total_spam_purged++;
                    }
                }
            }
        }
    }
}

echo "[2] Inactive emails moved to Archive: $total_archived\n";
echo "[3] Expired archive emails permanently purged: $total_purged\n";
echo "[4] Old spam messages cleaned: $total_spam_purged\n";

// 3. Remove Mailcow mailboxes and physical directories for inboxes deleted > 7 days ago
$st_deleted = $pdo->query("SELECT email_address, local_part, domain FROM ia_inboxes WHERE status = 'deleted' AND (expires_at IS NOT NULL AND expires_at < NOW() - INTERVAL 7 DAY)");
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
echo "[5] Purged fully deleted mailboxes & directories: $deleted_count\n";

echo "=== [Email Lifecycle & Retention Cleanup] Completed successfully ===\n";
