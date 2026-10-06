<?php
/**
 * mailcow.php — Mailcow integration (provisioning + doveadm fetch).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

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

        $st = $pdo->prepare('INSERT IGNORE INTO sender_acl VALUES (NULL,?,?,0),(NULL,?,?,0)');
        $st->execute([$email, "@$dom", $email, $email]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false) {
            $suggestion = $lp . '-' . rand(11, 99);
            return ['error' => "The email '$email' is already taken. Try '$suggestion@$dom' or choose a different name."];
        }
        return ['error' => 'Failed to create mailbox: ' . $msg];
    }
}

function mailcow_delete_mailbox(string $email): void {
    $pdo = db();
    $pdo->prepare('DELETE FROM mailbox WHERE username = ?')->execute([$email]);
    $pdo->prepare('DELETE FROM sender_acl WHERE logged_in_as = ?')->execute([$email]);
}

function mailcow_reset_mailbox_password(string $email, string $new_password): bool {
    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    $pdo = db();
    $st = $pdo->prepare('UPDATE mailbox SET password = ? WHERE username = ?');
    return $st->execute(["{BLF-CRYPT}$hash", $email]);
}

function mailcow_domain_exists(string $domain): bool {
    $pdo = db();
    $st = $pdo->prepare('SELECT COUNT(*) FROM domain WHERE domain = ?');
    $st->execute([$domain]);
    return (int)$st->fetchColumn() > 0;
}

function mailcow_domain_active(string $domain): bool {
    $pdo = db();
    $st = $pdo->prepare('SELECT active FROM domain WHERE domain = ?');
    $st->execute([$domain]);
    return (bool)$st->fetchColumn();
}

function mailcow_add_domain(string $domain, string $description = ''): array {
    $domain = strtolower(trim($domain));
    if (mailcow_domain_exists($domain)) {
        return ['ok' => true, 'domain' => $domain, 'existing' => true];
    }
    $pdo = db();
    try {
        $st = $pdo->prepare(
            'INSERT INTO domain (domain, description, aliases, mailboxes, maxquota, quota, transport, backupmx, active)
             VALUES (?, ?, 0, 0, 0, 0, \'virtual\', 0, 1)'
        );
        $st->execute([$domain, $description ?: "Added via Teak Email"]);
        return ['ok' => true, 'domain' => $domain, 'existing' => false];
    } catch (Throwable $e) {
        return ['error' => 'Failed to add domain: ' . $e->getMessage()];
    }
}

function mailcow_list_domains(): array {
    $pdo = db();
    $st = $pdo->query('SELECT domain, description, active FROM domain ORDER BY domain ASC');
    return $st->fetchAll();
}

function mailcow_maildir_path(string $email): string {
    [$lp, $dom] = explode('@', $email, 2);
    $lp = preg_replace('/[^a-zA-Z0-9._-]/', '', $lp);
    $dom = preg_replace('/[^a-zA-Z0-9.-]/', '', $dom);

    $candidates = [
        "/var/mail/$dom/$lp/Maildir",
        "/var/mail/$dom/$lp",
        "/var/vmail/$dom/$lp/Maildir",
        "/var/vmail/$dom/$lp",
    ];

    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            return $cand;
        }
    }

    return "/var/mail/$dom/$lp/Maildir";
}

function mailcow_fix_maildir_permissions(string $maildir): void {
    if (!is_dir($maildir)) return;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$maildir/$sub";
        if (!is_dir($dir)) continue;
        @chmod($dir, 02770);
        @chgrp($dir, 'postfix');

        $has_unreadable = false;
        $files = @scandir($dir);
        if ($files) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $fp = "$dir/$f";
                if (is_file($fp) && !@is_readable($fp)) {
                    $has_unreadable = true;
                    break;
                }
            }
        }

        if ($has_unreadable) {
            @exec("sudo /usr/bin/chmod -R 0660 " . escapeshellarg($dir));
        }
    }
}

/**
 * Robust MIME parser to extract clean plain text and clean HTML.
 */
function mailcow_clean_body(string $raw_body, array $headers = []): array {
    $extracted_text = '';
    $extracted_html = '';

    // Detect multipart boundary (from header or directly from body)
    $boundary = '';
    $ct = $headers['content-type'] ?? '';
    if (preg_match('/boundary=["\']?([^"\'\s;]+)["\']?/i', $ct, $bm)) {
        $boundary = $bm[1];
    } elseif (preg_match('/^--([^\r\n]+)/m', $raw_body, $bm)) {
        $boundary = trim($bm[1]);
    }

    if (!empty($boundary)) {
        $parts = explode("--$boundary", $raw_body);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '--') continue;

            $subparts = preg_split('/\r?\n\r?\n/', $part, 2);
            $subhead = strtolower($subparts[0] ?? '');
            $subbody = $subparts[1] ?? '';

            if (str_contains($subhead, 'quoted-printable')) {
                $subbody = quoted_printable_decode($subbody);
            } elseif (str_contains($subhead, 'base64')) {
                $subbody = base64_decode(trim($subbody));
            }

            if (str_contains($subhead, 'text/html') && empty($extracted_html)) {
                $extracted_html = $subbody;
            } elseif (str_contains($subhead, 'text/plain') && empty($extracted_text)) {
                $extracted_text = $subbody;
            }
        }
    } else {
        $cte = strtolower($headers['content-transfer-encoding'] ?? '');
        $body = $raw_body;
        if (str_contains($cte, 'quoted-printable') || str_contains($body, '=\r\n') || str_contains($body, '=\n')) {
            $body = quoted_printable_decode($body);
        } elseif (str_contains($cte, 'base64')) {
            $body = base64_decode(trim($body));
        }

        if (str_contains(strtolower($ct), 'text/html')) {
            $extracted_html = $body;
        } else {
            $extracted_text = $body;
        }
    }

    // Fallback: If plain text is empty, generate clean text from HTML
    if (empty($extracted_text)) {
        if (!empty($extracted_html)) {
            $extracted_text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $extracted_html));
        } else {
            $extracted_text = quoted_printable_decode($raw_body);
        }
    }

    return [
        'text' => trim($extracted_text),
        'html' => trim($extracted_html)
    ];
}

function mailcow_parse_email_file(string $filepath): ?array {
    if (!is_readable($filepath)) return null;
    $raw = file_get_contents($filepath);
    if ($raw === false) return null;

    $uid = (string)(crc32(basename($filepath)) & 0x7FFFFFFF);

    $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
    $header_text = $parts[0] ?? '';
    $raw_body = $parts[1] ?? '';

    // Unfold multiline headers
    $unfolded_headers = preg_replace('/\r?\n[ \t]+/', ' ', $header_text);
    $headers = [];
    $lines = preg_split('/\r?\n/', $unfolded_headers);
    foreach ($lines as $line) {
        if (preg_match('/^(\S+?):\s*(.*)$/', $line, $m)) {
            $k = strtolower($m[1]);
            $headers[$k] = $m[2];
        }
    }

    $parsed_bodies = mailcow_clean_body($raw_body, $headers);

    return [
        'uid'     => $uid,
        'from'    => $headers['from'] ?? '',
        'subject' => $headers['subject'] ?? '',
        'date'    => $headers['date'] ?? '',
        'raw'     => $raw,
        'body'    => $parsed_bodies['text'],
        'html'    => $parsed_bodies['html']
    ];
}

function mailcow_fetch_inbox(string $email): array {
    $maildir = mailcow_maildir_path($email);
    mailcow_fix_maildir_permissions($maildir);
    $result = [];

    foreach (['new', 'cur'] as $sub) {
        $dir = "$maildir/$sub";
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
                ];
            }
        }
    }

    usort($result, function ($a, $b) {
        return $b['_mtime'] <=> $a['_mtime'];
    });

    return $result;
}

function mailcow_fetch_message(string $email, int $uid): ?string {
    $maildir = mailcow_maildir_path($email);
    mailcow_fix_maildir_permissions($maildir);

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

function mailcow_send_test(string $to): bool {
    $subject = 'Teak Email test ' . date('Y-m-d H:i');
    $body = "This is a test email.\nYour code is: 123456\nSent " . date('c') . "\n";
    $from = 'no-reply@teak.email';
    $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}
