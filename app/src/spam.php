<?php
/**
 * spam.php — Intelligent Spam Detection, Quarantine & Maildir .Junk Management.
 * Protects inboxes from junk, phishing, scam broadcasts, and unwanted clutter.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailcow.php';

/**
 * Ensure the .Junk Maildir subfolder exists with proper permissions.
 */
function spam_ensure_junk_dir(string $maildir): string {
    $junk = rtrim($maildir, '/') . '/.Junk';
    if (!is_dir("$junk/cur")) {
        @mkdir("$junk/cur", 0770, true);
        @chgrp("$junk/cur", 'postfix');
    }
    if (!is_dir("$junk/new")) {
        @mkdir("$junk/new", 0770, true);
        @chgrp("$junk/new", 'postfix');
    }
    if (!is_dir("$junk/tmp")) {
        @mkdir("$junk/tmp", 0770, true);
        @chgrp("$junk/tmp", 'postfix');
    }
    return $junk;
}

/**
 * Intelligent spam scoring and heuristic detection.
 * Returns ['is_spam' => bool, 'score' => int, 'reasons' => array]
 */
function spam_detect(string $raw_email, array $headers = [], string $body = '', string $subject = ''): array {
    $score = 0;
    $reasons = [];

    // 1. Check Spam Headers (SpamAssassin / Rspamd / Postfix headers)
    if (!empty($headers['x-spam-flag']) && strtoupper(trim($headers['x-spam-flag'])) === 'YES') {
        $score += 10;
        $reasons[] = 'Upstream mail filter flagged as spam (X-Spam-Flag: YES)';
    }
    if (!empty($headers['x-spam-status']) && stripos($headers['x-spam-status'], 'Yes') !== false) {
        $score += 10;
        $reasons[] = 'SpamAssassin verified spam status';
    }
    if (!empty($headers['authentication-results'])) {
        $auth = strtolower($headers['authentication-results']);
        if (str_contains($auth, 'spf=fail') || str_contains($auth, 'spf=softfail')) {
            $score += 3;
            $reasons[] = 'Sender SPF verification failed';
        }
        if (str_contains($auth, 'dkim=fail')) {
            $score += 3;
            $reasons[] = 'DKIM signature invalid or failed';
        }
    }

    $text_to_check = strtolower($subject . ' ' . $body);

    // 2. High-confidence Scam / Phishing patterns (Score +6 each)
    $scam_patterns = [
        'congratulations you won'       => 'Lottery / Prize winning claim',
        'lottery winner'                => 'Lottery scam keyword',
        'claim your prize'              => 'Prize claim lure',
        'wire transfer of $'            => 'Advance-fee wire transfer',
        'inheritance fund'              => 'Inheritance scam',
        'western union transfer'        => 'Unsolicited wire transfer service',
        'urgent assistance needed'      => 'Urgent money transfer lure',
        'crypto giveaway'               => 'Cryptocurrency giveaway scam',
        'double your bitcoin'           => 'Crypto multiplier scam',
        'verify your bank account immediately' => 'Bank credential phishing',
        'your account will be suspended in 24' => 'Urgency-based credential phishing',
    ];
    foreach ($scam_patterns as $pattern => $label) {
        if (str_contains($text_to_check, $pattern)) {
            $score += 6;
            $reasons[] = $label;
        }
    }

    // 3. High-confidence Gambling / Adult / Pharma spam (Score +5 each)
    $spam_keywords = [
        'slot gacor'           => 'Online gambling / slot promotion',
        'judi online'          => 'Gambling promotion',
        'situs judi'           => 'Gambling website',
        'maxwin'               => 'Gambling slot lure',
        'casino bonus'         => 'Casino marketing',
        'free spins no deposit'=> 'Gambling lure',
        'viagra'               => 'Unsolicited pharmaceutical',
        'cialis'               => 'Unsolicited pharmaceutical',
        'hot singles in your'  => 'Adult dating spam',
    ];
    foreach ($spam_keywords as $keyword => $label) {
        if (str_contains($text_to_check, $keyword)) {
            $score += 5;
            $reasons[] = $label;
        }
    }

    // 4. Suspicious Attachments (Executable / Script payloads)
    if (preg_match('/\.(exe|bat|cmd|vbs|scr|pif|iso|vbe|hta)\b/i', $raw_email)) {
        $score += 8;
        $reasons[] = 'Dangerous executable attachment payload detected';
    }

    // Threshold: score >= 5 is considered spam
    $is_spam = ($score >= 5);

    return [
        'is_spam' => $is_spam,
        'score'   => $score,
        'reasons' => $reasons,
    ];
}

/**
 * Scan an inbox Maildir and automatically move incoming spam into .Junk folder.
 * Keeps the main inbox clean and focused purely on authentic emails & OTPs.
 * Returns count of filtered spam messages.
 */
function spam_auto_filter_inbox(string $email): int {
    $maildir = mailcow_maildir_path($email);
    if (!is_dir($maildir)) return 0;

    $junk_dir = spam_ensure_junk_dir($maildir);
    $moved_count = 0;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath) || !@is_readable($filepath)) continue;

            $parsed = mailcow_parse_email_file($filepath);
            if (!$parsed) continue;

            $headers = [];
            $lines = explode("\n", substr($parsed['raw'], 0, 4000));
            foreach ($lines as $l) {
                if (preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $l, $m)) {
                    $headers[strtolower($m[1])] = trim($m[2]);
                }
            }

            $check = spam_detect($parsed['raw'], $headers, $parsed['body'], $parsed['subject']);
            if ($check['is_spam']) {
                $dest = "$junk_dir/cur/$f";
                if (@rename($filepath, $dest)) {
                    @chmod($dest, 0640);
                    @chgrp($dest, 'postfix');
                    $moved_count++;
                }
            }
        }
    }

    return $moved_count;
}

/**
 * Fetch messages in .Junk (Spam) folder.
 */
function spam_fetch_junk(string $email): array {
    $maildir = mailcow_maildir_path($email);
    $junk_dir = "$maildir/.Junk";
    if (!is_dir($junk_dir)) return [];

    $result = [];
    foreach (['new', 'cur'] as $sub) {
        $dir = "$junk_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath)) continue;

            if (!@is_readable($filepath)) {
                @chmod($filepath, 0640);
                @chgrp($filepath, 'postfix');
                if (!@is_readable($filepath)) continue;
            }

            $parsed = mailcow_parse_email_file($filepath);
            if ($parsed) {
                $mtime = @filemtime($filepath) ?: 0;
                $result[] = [
                    'uid'     => $parsed['uid'],
                    'from'    => $parsed['from'],
                    'subject' => $parsed['subject'],
                    'date'    => $parsed['date'],
                    '_mtime'  => $mtime,
                    'file'    => $f,
                ];
            }
        }
    }

    usort($result, function ($a, $b) {
        return $b['_mtime'] <=> $a['_mtime'];
    });

    return $result;
}

/**
 * Fetch a specific spam message content by UID.
 */
function spam_fetch_message(string $email, int $uid): ?string {
    $maildir = mailcow_maildir_path($email);
    $junk_dir = "$maildir/.Junk";
    if (!is_dir($junk_dir)) return null;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$junk_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $filepath = "$dir/$f";
            if (!is_file($filepath)) continue;

            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                if (!@is_readable($filepath)) {
                    @chmod($filepath, 0640);
                    @chgrp($filepath, 'postfix');
                }
                $parsed = mailcow_parse_email_file($filepath);
                if ($parsed) {
                    $body = rtrim($parsed['body'], "\r\n");
                    $out  = "hdr.from: {$parsed['from']}\n";
                    $out .= "hdr.subject: {$parsed['subject']}\n";
                    $out .= "hdr.date: {$parsed['date']}\n";
                    if (!empty($parsed['html'])) {
                        $out .= "text.html: {$parsed['html']}\n";
                    }
                    $out .= "text.utf8: {$body}\n";
                    return $out;
                }
            }
        }
    }
    return null;
}

/**
 * Manually move an email from regular inbox to .Junk (Mark as Spam).
 */
function spam_move_to_junk(string $email, int $uid): bool {
    $maildir = mailcow_maildir_path($email);
    $junk_dir = spam_ensure_junk_dir($maildir);

    foreach (['new', 'cur'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                $src = "$dir/$f";
                $dst = "$junk_dir/cur/$f";
                if (@rename($src, $dst)) {
                    @chmod($dst, 0640);
                    @chgrp($dst, 'postfix');
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Restore an email from .Junk back to main inbox (Mark as Not Spam).
 */
function spam_restore_to_inbox(string $email, int $uid): bool {
    $maildir = mailcow_maildir_path($email);
    $junk_dir = "$maildir/.Junk";
    if (!is_dir($junk_dir)) return false;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$junk_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                $src = "$dir/$f";
                $dst = "$maildir/cur/$f";
                if (@rename($src, $dst)) {
                    @chmod($dst, 0640);
                    @chgrp($dst, 'postfix');
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Permanently delete an email from .Junk (Spam) folder.
 */
function spam_delete_file(string $email, int $uid): bool {
    $maildir = mailcow_maildir_path($email);
    $junk_dir = "$maildir/.Junk";
    if (!is_dir($junk_dir)) return false;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$junk_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                return @unlink("$dir/$f");
            }
        }
    }
    return false;
}

/**
 * Count total spam messages for an inbox.
 */
function spam_count(string $email): int {
    $maildir = mailcow_maildir_path($email);
    $junk_dir = "$maildir/.Junk";
    if (!is_dir($junk_dir)) return 0;

    $cnt = 0;
    foreach (['new', 'cur'] as $sub) {
        $dir = "$junk_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..' && is_file("$dir/$f")) {
                $cnt++;
            }
        }
    }
    return $cnt;
}
