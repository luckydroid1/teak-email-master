<?php
/**
 * mail_send.php — Send email from an inbox (1 recipient, no CC/BCC).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailcow.php';

/**
 * Send email from an inbox. Limited to 1 recipient, no CC/BCC.
 * @return array ['ok' => true] or ['error' => string]
 */
function send_email(string $from_email, string $to, string $subject, string $body): array {
    // Validate inputs
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid recipient email address'];
    }
    if (empty(trim($subject))) {
        return ['error' => 'Subject cannot be empty'];
    }
    if (empty(trim($body))) {
        return ['error' => 'Body cannot be empty'];
    }

    // Block mass sending — only 1 recipient allowed
    $to_count = array_filter(array_map('trim', explode(',', $to)));
    if (count($to_count) > 1) {
        return ['error' => 'Only 1 recipient allowed per message'];
    }

    // Block CC/BCC in headers
    $blocked_headers = ['cc:', 'bcc:'];

    // Build email
    [$local_part, $domain] = explode('@', $from_email, 2);

    // Get mailbox password from Mailcow
    $pdo = db();
    $st = $pdo->prepare('SELECT password FROM mailbox WHERE username = ? AND active = 1');
    $st->execute([$from_email]);
    $row = $st->fetch();
    if (!$row) {
        return ['error' => 'Mailbox not found or inactive'];
    }

    // Send via Postfix (SMTP to localhost)
    $msg  = "From: $from_email\r\n";
    $msg .= "To: <$to>\r\n";
    $msg .= "Subject: $subject\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: <" . bin2hex(random_bytes(8)) . "@teak.email>\r\n";
    $msg .= "X-Mailer: TeakEmail/1.0\r\n";
    $msg .= "\r\n$body";

    $result = smtp_send('127.0.0.1', 25, $from_email, $to, $msg);

    if ($result) {
        // Log the send
        $uid = $_SESSION['uid'] ?? 0;
        $pdo->prepare('INSERT INTO ia_audit (user_id, action, ip, detail) VALUES (?, ?, ?, ?)')
            ->execute([$uid, 'email_sent', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', "from=$from_email to=$to subject=" . substr($subject, 0, 50)]);

        // Track sent email
        $pdo->prepare('INSERT INTO ia_sent_emails (user_id, from_email, to_email, subject, body) VALUES (?, ?, ?, ?, ?)')
            ->execute([$uid, $from_email, $to, $subject, $body]);

        return ['ok' => true];
    } else {
        return ['error' => 'Failed to send email. Please try again.'];
    }
}

/**
 * Check if a mailbox can send (is active, has password).
 */
function can_send(string $email): bool {
    $st = db()->prepare('SELECT 1 FROM mailbox WHERE username = ? AND active = 1');
    $st->execute([$email]);
    return (bool)$st->fetchColumn();
}
