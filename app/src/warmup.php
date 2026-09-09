<?php
/**
 * warmup.php — Email warmup system.
 * Automatically sends/receives emails to build domain reputation.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailcow.php';

/**
 * Get warmup status for a mailbox.
 */
function warmup_status(string $email): array {
    $st = db()->prepare('SELECT * FROM ia_warmup WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'enabled' => false,
            'emails_sent' => 0,
            'emails_received' => 0,
            'score' => 0,
            'status' => 'not_started',
        ];
    }
    return $row;
}

/**
 * Start warmup for a mailbox. Can restart if paused.
 */
function warmup_start(string $email): array {
    $st = db()->prepare('SELECT enabled FROM ia_warmup WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();

    if ($row && $row['enabled'] == 1) {
        return ['error' => 'Warmup already active'];
    }

    $pdo = db();
    if ($row) {
        // Resume paused warmup
        $pdo->prepare('UPDATE ia_warmup SET enabled = 1, status = "warming" WHERE email = ?')
            ->execute([$email]);
    } else {
        // Start new warmup
        $pdo->prepare('INSERT INTO ia_warmup (email, enabled, started_at, status) VALUES (?, 1, NOW(), "warming")')
            ->execute([$email]);
    }

    return ['ok' => true];
}

/**
 * Stop warmup for a mailbox.
 */
function warmup_stop(string $email): array {
    db()->prepare('UPDATE ia_warmup SET enabled = 0, status = "paused" WHERE email = ?')
        ->execute([$email]);
    return ['ok' => true];
}

/**
 * Run warmup cycle — send emails between warmup pairs.
 * Called by cron every hour.
 */
function warmup_tick(): array {
    $pdo = db();

    // Get all active warmup mailboxes
    $active = $pdo->query('SELECT email FROM ia_warmup WHERE enabled = 1 AND status = "warming"')
        ->fetchAll(PDO::FETCH_COLUMN);

    if (count($active) < 2) {
        return ['ok' => true, 'message' => 'Need at least 2 mailboxes for warmup'];
    }

    $sent = 0;

    // Pair up mailboxes and send between them
    for ($i = 0; $i < count($active) - 1; $i += 2) {
        $from = $active[$i];
        $to = $active[$i + 1];

        // Generate warmup email
        $subjects = [
            'Quick check-in',
            'Following up on our conversation',
            'Meeting notes from today',
            'Project update',
            'Quick question about the proposal',
            'Thanks for your help',
            'Re: Next steps',
            'Updated schedule',
            'Important update',
            'Check this out',
        ];

        $bodies = [
            "Hi,\n\nJust wanted to touch base and see how things are going on your end. Let me know if you need anything.\n\nBest regards",
            "Hi,\n\nFollowing up on our last conversation. I've attached the updated timeline for your review.\n\nLet me know your thoughts.",
            "Hi,\n\nHere are the meeting notes from today's call:\n\n1. Project timeline updated\n2. New features added\n3. Budget review next week\n\nPlease review and share any feedback.",
            "Hi,\n\nQuick update on the project — we're on track for the deadline. Everything looks good so far.\n\nWill keep you posted.",
            "Hi,\n\nI have a quick question about the proposal we discussed. When would be a good time to chat?\n\nThanks",
            "Hi,\n\nJust wanted to say thanks for your help with the recent project. Really appreciate it.\n\nBest",
            "Hi,\n\nRe: Our discussion about next steps. I think we should focus on the core features first.\n\nWhat do you think?",
            "Hi,\n\nI've updated the schedule for next week. Please check the shared calendar for the new times.\n\nThanks",
            "Hi,\n\nImportant update — the client has approved the final design. We can move forward with implementation.\n\nGreat work everyone!",
            "Hi,\n\nCheck out this article I found — it's relevant to what we're working on.\n\nLet me know your thoughts.",
        ];

        $subject = $subjects[array_rand($subjects)];
        $body = $bodies[array_rand($bodies)];

        // Send via Postfix
        $msg  = "From: $from\r\n";
        $msg .= "To: <$to>\r\n";
        $msg .= "Subject: $subject\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "Message-ID: <" . bin2hex(random_bytes(8)) . "@warmup.teak.email>\r\n";
        $msg .= "X-Warmup: 1\r\n";
        $msg .= "\r\n$body";

        $ok = smtp_send('127.0.0.1', 25, $from, $to, $msg);

        if ($ok) {
            // Update stats
            $pdo->prepare('UPDATE ia_warmup SET emails_sent = emails_sent + 1 WHERE email = ?')
                ->execute([$from]);
            $pdo->prepare('UPDATE ia_warmup SET emails_received = emails_received + 1 WHERE email = ?')
                ->execute([$to]);
            $sent++;
        }
    }

    // Update reputation scores
    $pdo->exec('UPDATE ia_warmup SET score = LEAST(100, emails_sent + emails_received) WHERE enabled = 1');

    return ['ok' => true, 'sent' => $sent, 'pairs' => intdiv(count($active), 2)];
}
