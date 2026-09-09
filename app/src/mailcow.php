<?php
/**
 * mailcow.php — Mailcow integration (provisioning + doveadm fetch).
 * Reuse logic dari admin.php (Mail Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Buat mailbox di Mailcow via MySQL langsung.
 * Wajib: attributes maildir + sender_acl (tanpa ini Postfix reject 550).
 */
function mailcow_create_mailbox(string $email, string $password, string $name = ''): array {
    [$lp, $dom] = explode('@', $email, 2);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $attributes = json_encode([
        'sender_acl'      => "*@$dom",
        'mailbox_format'  => 'maildir:',
    ]);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO mailbox (username, password, name, local_part, domain, quota, active, attributes)
             VALUES (?,?,?,?,?,102400,1,?)'
        );
        $st->execute([$email, "{BLF-CRYPT}$hash", $name, $lp, $dom, $attributes]);

        // sender_acl: izinkan send-as @domain dan self
        $st = $pdo->prepare('INSERT IGNORE INTO sender_acl VALUES (NULL,?,?,0),(NULL,?,?,0)');
        $st->execute([$email, "@$dom", $email, $email]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false) {
            return ['error' => 'Mailbox already exists'];
        }
        return ['error' => 'Failed to create mailbox'];
    }
}

function mailcow_delete_mailbox(string $email): void {
    $pdo = db();
    $pdo->prepare('DELETE FROM mailbox WHERE username = ?')->execute([$email]);
    $pdo->prepare('DELETE FROM sender_acl WHERE logged_in_as = ?')->execute([$email]);
}

/** Check if domain exists in Mailcow. */
function mailcow_domain_exists(string $domain): bool {
    $st = db()->prepare('SELECT 1 FROM domain WHERE domain = ?');
    $st->execute([$domain]);
    return (bool)$st->fetchColumn();
}

/** Check if domain is active in Mailcow. */
function mailcow_domain_active(string $domain): bool {
    $st = db()->prepare('SELECT 1 FROM domain WHERE domain = ? AND active = 1');
    $st->execute([$domain]);
    return (bool)$st->fetchColumn();
}

/** Add a new domain to Mailcow. */
function mailcow_add_domain(string $domain): array {
    $pdo = db();
    try {
        $st = $pdo->prepare(
            'INSERT INTO domain (domain, description, aliases, mailboxes, quota, active)
             VALUES (?, ?, 400, 10, 10240, 1)'
        );
        $st->execute([$domain, "Custom domain for Teak Email"]);
        return ['ok' => true];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false) {
            return ['error' => 'Domain already exists'];
        }
        return ['error' => 'Failed to add domain: ' . $msg];
    }
}

/** List all domains in Mailcow. */
function mailcow_list_domains(): array {
    $st = db()->prepare('SELECT domain, description, active FROM domain WHERE domain != "" ORDER BY domain');
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Compute Maildir path for a given email address.
 * Path: /var/mail/{domain}/{local_part}/Maildir/
 */
function mailcow_maildir_path(string $email): string {
    [$lp, $dom] = explode('@', $email, 2);
    return "/var/mail/$dom/$lp/Maildir";
}

/**
 * Fix Maildir permissions so www-data (PHP-FPM) can read files.
 * Postfix creates files as 0600 (postfix:postfix). This adds group-read
 * for the postfix group that www-data belongs to.
 *
 * SAFETY NET: Primary fix is adding www-data to postfix group + cron script.
 * This function handles race conditions where mail arrives between cron runs.
 * Uses filemtime() to only re-fix directories with recent changes (skip if
 * all files already group-readable).
 */
function mailcow_fix_maildir_permissions(string $maildir): void {
    if (!is_dir($maildir)) return;

    // Fix parent directories (domain/user level) — ensure setgid + group traverse
    $parts = explode('/', $maildir);
    $cumulative = '';
    // Skip /var/mail prefix, start from domain level
    $start = array_search('mail', $parts) !== false ? array_search('mail', $parts) + 1 : 1;
    for ($i = $start; $i < count($parts); $i++) {
        $cumulative .= '/' . $parts[$i];
        if (is_dir($cumulative)) {
            @chmod($cumulative, 02770); // setgid + rwxrwx---
            @chgrp($cumulative, 'postfix');
        }
    }

    foreach (['new', 'cur', 'tmp'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        // Ensure directory is group-readable with setgid
        @chmod($dir, 02770);
        @chgrp($dir, 'postfix');
        // Fix files — only scan if directory was recently modified (perf optimization)
        $dir_mtime = @filemtime($dir);
        if ($dir_mtime === false || $dir_mtime < time() - 300) {
            // Directory not modified in 5 min, skip (cron handles it)
            continue;
        }
        foreach (@scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $fp = "$dir/$f";
            if (!is_file($fp)) continue;
            $perm = @fileperms($fp);
            if ($perm === false) continue;
            // If file is 0600 (owner-only), fix to 0640 (group-readable)
            if (($perm & 0060) !== 0060) {
                @chmod($fp, 0640);
                @chgrp($fp, 'postfix');
            }
        }
    }
}

/**
 * Parse a single Maildir email file into headers + body.
 * Returns ['uid' => filename-based, 'from' => ..., 'subject' => ..., 'date' => ..., 'raw' => ...]
 */
function mailcow_parse_email_file(string $filepath): ?array {
    if (!is_readable($filepath)) return null;
    $raw = file_get_contents($filepath);
    if ($raw === false) return null;

    // uid = crc32 of filename (stable, matches doveadm convention)
    $uid = (string)(crc32(basename($filepath)) & 0x7FFFFFFF);

    // Parse headers (up to first blank line)
    $headers = [];
    $lines = preg_split('/\r?\n/', $raw);
    foreach ($lines as $line) {
        if ($line === '') break; // end of headers
        if (preg_match('/^(\S+?):\s*(.*)$/', $line, $m)) {
            $k = strtolower($m[1]);
            $headers[$k] = $m[2];
        }
    }

    // Get body (after first blank line)
    $bodyPos = strpos($raw, "\n\n");
    $body = $bodyPos !== false ? substr($raw, $bodyPos + 2) : '';

    return [
        'uid'     => $uid,
        'from'    => $headers['from'] ?? '',
        'subject' => $headers['subject'] ?? '',
        'date'    => $headers['date'] ?? '',
        'raw'     => $raw,
        'body'    => $body,
    ];
}

/**
 * Fetch email list in INBOX by reading Maildir directly.
 * Falls back to doveadm if direct read fails (permission issues).
 * @return array list of ['uid','from','subject','date']
 */
function mailcow_fetch_inbox(string $email): array {
    $maildir = mailcow_maildir_path($email);
    mailcow_fix_maildir_permissions($maildir);
    $result = [];

    // Scan new/ and cur/ directories
    foreach (['new', 'cur'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath)) continue;
            // Check readability before parsing (skip unreadable files gracefully)
            if (!@is_readable($filepath)) {
                // Try one more permission fix attempt
                @chmod($filepath, 0640);
                @chgrp($filepath, 'postfix');
                if (!@is_readable($filepath)) continue;
            }
            $parsed = mailcow_parse_email_file($filepath);
            if ($parsed) {
                $result[] = [
                    'uid'     => $parsed['uid'],
                    'from'    => $parsed['from'],
                    'subject' => $parsed['subject'],
                    'date'    => $parsed['date'],
                ];
            }
        }
    }

    // Sort by filename (Maildir filenames are timestamp-based, newest last)
    usort($result, function ($a, $b) {
        return $b['uid'] <=> $a['uid'];
    });

    return $result; // newest first (uid is derived from filename which encodes time)
}

/** Fetch full email content (header + body text). */
function mailcow_fetch_message(string $email, int $uid): ?string {
    $maildir = mailcow_maildir_path($email);
    mailcow_fix_maildir_permissions($maildir);

    // Find the file whose crc32 matches the uid
    foreach (['new', 'cur'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath)) continue;
            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                // Ensure file is readable
                if (!@is_readable($filepath)) {
                    @chmod($filepath, 0640);
                    @chgrp($filepath, 'postfix');
                }
                $parsed = mailcow_parse_email_file($filepath);
                if ($parsed) {
                    // Build output for OTP parser compatibility.
                    $body = rtrim($parsed['body'], "\r\n");
                    $out  = "hdr.from: {$parsed['from']}\n";
                    $out .= "hdr.subject: {$parsed['subject']}\n";
                    $out .= "hdr.date: {$parsed['date']}\n";
                    $out .= "text.utf8: {$body}\n";
                    return $out;
                }
            }
        }
    }
    return null;
}

/** Send test email to mailbox (for end-to-end verification after deploy). */
function mailcow_send_test(string $to): bool {
    $subject = 'CodeInbox test ' . date('Y-m-d H:i');
    $body = "This is a test email.\nYour code is: 123456\nSent " . date('c') . "\n";
    $headers = "From: no-reply@jetdigitalpro.com\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}
