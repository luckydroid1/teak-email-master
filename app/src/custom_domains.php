<?php
/**
 * custom_domains.php — User-scoped domain management.
 * Each user has their own domain list via ia_user_domains.
 * NO global pool_domains fallback. Data cannot leak across users.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailcow.php';

// Known external MX providers that conflict with Teak
// If a domain points to these, it has an existing email provider
const CONFLICT_PROVIDERS = [
    'google'       => ['google.com', 'googlemail.com', 'gmail-smtp-in.l.google.com'],
    'microsoft'    => ['outlook.com', 'protection.outlook.com', 'microsoft.com'],
    'zoho'         => ['zoho.com', 'zoho.eu', 'zoho.in'],
    'spacemail'    => ['spacemail.com', 'spf.spacemail.com'],
    'protonmail'   => ['protonmail.ch', 'proton.ch'],
    'yandex'       => ['yandex.net', 'yandex.ru'],
    'fastmail'     => ['fastmail.com', 'messagingengine.com'],
    'rackpace'     => ['emailsrvr.com'],
    'mimecast'     => ['mimecast.com'],
    'proofpoint'   => ['pphosted.com', 'ppe-hosted.com'],
    'barracuda'    => ['barracudanetworks.com'],
    'mailchannels' => ['mailchannels.net'],
];

/**
 * List domains for a specific user. No cross-user access.
 */
function custom_domain_list(int $user_id): array {
    $st = db()->prepare(
        'SELECT id, domain, source, status, classification, mx_summary, notes, synced_at, created_at
         FROM ia_user_domains WHERE user_id = ? ORDER BY created_at DESC'
    );
    $st->execute([$user_id]);
    return $st->fetchAll();
}

/**
 * Add a domain to a user's list (idempotent).
 * Does NOT create mailbox/inbox. Does NOT modify DNS.
 */
function custom_domain_add(int $user_id, string $domain, string $source = 'manual', string $classification = null): array {
    $domain = strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
        return ['error' => 'Invalid domain name'];
    }

    // Check if user already has this domain
    $st = db()->prepare('SELECT id FROM ia_user_domains WHERE user_id = ? AND domain = ?');
    $st->execute([$user_id, $domain]);
    if ($st->fetch()) {
        return ['ok' => true, 'status' => 'already_exists'];
    }

    // Add to user's domain list (pending by default)
    $st = db()->prepare(
        'INSERT INTO ia_user_domains (user_id, domain, source, status, classification)
         VALUES (?, ?, ?, "pending", ?)'
    );
    $st->execute([$user_id, $domain, $source, $classification]);

    // Also add to Mailcow domain table if not exists (for Postfix virtual transport)
    if (!mailcow_domain_exists($domain)) {
        mailcow_add_domain($domain);
    }

    return ['ok' => true, 'status' => 'added'];
}

/**
 * Mark a domain as verified/safe after DNS check passes.
 */
function custom_domain_verify(int $user_id, string $domain, string $classification, string $mx_summary = '', string $notes = ''): void {
    db()->prepare(
        'UPDATE ia_user_domains SET status = "verified", classification = ?, mx_summary = ?, notes = ?
         WHERE user_id = ? AND domain = ?'
    )->execute([$classification, $mx_summary, $notes, $user_id, $domain]);
}

/**
 * Mark a domain as conflict (has existing external email provider).
 */
function custom_domain_conflict(int $user_id, string $domain, string $classification, string $mx_summary = '', string $notes = ''): void {
    db()->prepare(
        'UPDATE ia_user_domains SET status = "conflict", classification = ?, mx_summary = ?, notes = ?
         WHERE user_id = ? AND domain = ?'
    )->execute([$classification, $mx_summary, $notes, $user_id, $domain]);
}

/**
 * Get only verified/safe domains for a user (for inbox creation).
 */
function custom_domain_safe(int $user_id): array {
    $st = db()->prepare(
        'SELECT domain FROM ia_user_domains WHERE user_id = ? AND status = "verified" ORDER BY domain'
    );
    $st->execute([$user_id]);
    return array_column($st->fetchAll(), 'domain');
}

/**
 * Check if a domain is safe for a specific user.
 */
function custom_domain_is_safe(int $user_id, string $domain): bool {
    $st = db()->prepare(
        'SELECT 1 FROM ia_user_domains WHERE user_id = ? AND domain = ? AND status = "verified"'
    );
    $st->execute([$user_id, $domain]);
    return (bool)$st->fetchColumn();
}

/**
 * Inspect DNS/MX for a domain and classify it.
 * Returns classification: safe | conflict_* | unknown
 * Does NOT modify DNS records.
 */
function custom_domain_classify(string $domain): array {
    $mx = @dns_get_record($domain, DNS_MX);
    if ($mx === false || empty($mx)) {
        return [
            'classification' => 'safe',
            'mx_summary' => '(no MX records)',
            'notes' => 'No external email provider detected. Safe to configure for Teak.',
        ];
    }

    $mxHosts = array_column($mx, 'target');
    $mxStr = implode(', ', $mxHosts);

    // Check against known conflict providers
    foreach (CONFLICT_PROVIDERS as $provider => $patterns) {
        foreach ($mxHosts as $host) {
            $hostLower = strtolower($host);
            foreach ($patterns as $pattern) {
                if (str_ends_with($hostLower, $pattern) || str_contains($hostLower, $pattern)) {
                    return [
                        'classification' => "conflict_$provider",
                        'mx_summary' => $mxStr,
                        'notes' => "Existing email provider detected: $provider (MX: $host). DO NOT modify DNS.",
                    ];
                }
            }
        }
    }

    // Check if MX points to Teak's server (mail.pesat.ai or 94.100.26.189)
    foreach ($mxHosts as $host) {
        $hostLower = strtolower($host);
        if (str_contains($hostLower, 'pesat.ai') || $host === '94.100.26.189') {
            return [
                'classification' => 'safe',
                'mx_summary' => $mxStr,
                'notes' => 'MX already points to Teak server. Ready for use.',
            ];
        }
    }

    // Unknown MX provider — flag but don't auto-reject
    return [
        'classification' => 'unknown',
        'mx_summary' => $mxStr,
        'notes' => "Unknown MX provider(s): $mxStr. Manual review required before configuring.",
    ];
}

/**
 * Get classification summary for a user's domains.
 */
function custom_domain_summary(int $user_id): array {
    $domains = custom_domain_list($user_id);
    $summary = [
        'total' => count($domains),
        'safe' => 0,
        'conflict' => 0,
        'unknown' => 0,
        'pending' => 0,
    ];
    foreach ($domains as $d) {
        if ($d['status'] === 'pending') $summary['pending']++;
        elseif ($d['status'] === 'verified') $summary['safe']++;
        elseif ($d['status'] === 'conflict') $summary['conflict']++;
        else $summary['unknown']++;
    }
    return $summary;
}
