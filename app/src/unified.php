<?php
/**
 * Unified Inbox helper methods.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailcow.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/receipt.php';

/**
 * Fetch all emails across all active inboxes owned by user.
 * Returns sorted list (newest first) with categorized metadata (is_otp, is_receipt, otp_code).
 * Includes security validation to strictly enforce user isolation.
 */
function fetch_unified_inbox(int $uid, int $limit_per_inbox = 15, int $max_total = 60): array {
    $st = db()->prepare("SELECT email_address, local_part, domain FROM ia_inboxes WHERE user_id = ? AND status = 'active' ORDER BY id DESC");
    $st->execute([$uid]);
    $inboxes = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($inboxes)) {
        return [];
    }

    $all_messages = [];

    foreach ($inboxes as $ib) {
        $email = $ib['email_address'];
        // Strict email syntax regex to prevent path traversal
        if (!preg_match('/^[a-z0-9._-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $email)) {
            continue;
        }

        $mails = mailcow_fetch_inbox($email);
        $slice = array_slice($mails, 0, $limit_per_inbox);

        foreach ($slice as $m) {
            $raw = mailcow_fetch_message($email, (int)$m['uid']);
            $otp_res = extract_otp($raw ?? '');
            $rec_res = extract_receipt($raw ?? '');

            $all_messages[] = [
                'uid'          => (int)$m['uid'],
                'inbox_email'  => $email,
                'inbox_domain' => $ib['domain'],
                'from'         => $m['from'] ?? '',
                'subject'      => $m['subject'] ?? '(No Subject)',
                'date'         => $m['date'] ?? '',
                '_mtime'       => $m['_mtime'] ?? 0,
                'is_otp'       => !empty($otp_res['otp']),
                'otp_code'     => $otp_res['otp'] ?? null,
                'is_receipt'   => (bool)$rec_res['is_receipt'],
                'receipt_amt'  => $rec_res['amount'] ?? null,
                'receipt_curr' => $rec_res['currency'] ?? 'USD',
                'raw'          => $raw
            ];
        }
    }

    // Sort all emails across all inboxes by modification timestamp descending
    usort($all_messages, function($a, $b) {
        return $b['_mtime'] <=> $a['_mtime'];
    });

    return array_slice($all_messages, 0, $max_total);
}
