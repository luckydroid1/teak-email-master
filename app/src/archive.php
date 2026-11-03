<?php
/**
 * archive.php — Automated Email Archive Lifecycle & Maildir .Archive Management.
 * Provides graceful grace-period before permanent email purging.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailcow.php';

/**
 * Ensure the .Archive Maildir subfolder exists with proper permissions.
 */
function archive_ensure_dir(string $maildir): string {
    $arch = rtrim($maildir, '/') . '/.Archive';
    foreach (['cur', 'new', 'tmp'] as $sub) {
        $p = "$arch/$sub";
        if (!is_dir($p)) {
            @mkdir($p, 0770, true);
            @chgrp($p, 'postfix');
        }
    }
    return $arch;
}

/**
 * Fetch messages in .Archive folder.
 */
function archive_fetch(string $email): array {
    $maildir = mailcow_maildir_path($email);
    $arch_dir = "$maildir/.Archive";
    if (!is_dir($arch_dir)) return [];

    $result = [];
    foreach (['new', 'cur'] as $sub) {
        $dir = "$arch_dir/$sub";
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
 * Fetch a specific archived message content by UID.
 */
function archive_fetch_message(string $email, int $uid): ?string {
    $maildir = mailcow_maildir_path($email);
    $arch_dir = "$maildir/.Archive";
    if (!is_dir($arch_dir)) return null;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$arch_dir/$sub";
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
 * Move message from active inbox (cur/new) to .Archive/cur.
 */
function archive_move_to_archive(string $email, int $uid): bool {
    $maildir = mailcow_maildir_path($email);
    if (!is_dir($maildir)) return false;

    $arch_dir = archive_ensure_dir($maildir);

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
                $dest = "$arch_dir/cur/$f";
                if (@rename($src, $dest)) {
                    @chmod($dest, 0640);
                    @chgrp($dest, 'postfix');
                    @touch($dest);
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Restore an archived email back to active inbox (cur).
 */
function archive_restore_to_inbox(string $email, int $uid): bool {
    $maildir = mailcow_maildir_path($email);
    $arch_dir = "$maildir/.Archive";
    if (!is_dir($arch_dir)) return false;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$arch_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                $src = "$dir/$f";
                $dest = "$maildir/cur/$f";
                if (@rename($src, $dest)) {
                    @chmod($dest, 0640);
                    @chgrp($dest, 'postfix');
                    @touch($dest);
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Permanently delete a file from .Archive folder.
 */
function archive_delete_file(string $email, int $uid): bool {
    $maildir = mailcow_maildir_path($email);
    $arch_dir = "$maildir/.Archive";
    if (!is_dir($arch_dir)) return false;

    foreach (['new', 'cur'] as $sub) {
        $dir = "$arch_dir/$sub";
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $fileUid = (string)(crc32($f) & 0x7FFFFFFF);
            if ((int)$fileUid === $uid) {
                $src = "$dir/$f";
                return @unlink($src);
            }
        }
    }
    return false;
}

/**
 * Count total archived emails.
 */
function archive_count(string $email): int {
    $maildir = mailcow_maildir_path($email);
    $arch_dir = "$maildir/.Archive";
    if (!is_dir($arch_dir)) return 0;

    $cnt = 0;
    foreach (['new', 'cur'] as $sub) {
        $dir = "$arch_dir/$sub";
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
