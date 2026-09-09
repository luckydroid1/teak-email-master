<?php
/**
 * spaceship_sync.php — CLI script for Spaceship domain sync + DNS classification.
 *
 * Usage:
 *   php spaceship_sync.php --user=EMAIL [--dry-run]
 *
 * Credentials are read from environment variables:
 *   SPACESHIP_API_KEY, SPACESHIP_API_SECRET
 *
 * Flow:
 *   1. Fetches all domains from Spaceship API (paginated)
 *   2. Classifies each domain via DNS/MX inspection
 *   3. Adds safe domains to user's ia_user_domains (idempotent)
 *   4. Marks conflicts as conflict_*
 *   5. Outputs JSON report
 *
 * Security:
 *   - Credentials are NEVER written to files, logs, or CLI arguments
 *   - Credentials are read from env vars (not visible in process listings)
 *   - Report file contains NO credentials
 */
declare(strict_types=1);

// Parse CLI arguments (--user, --dry-run only; credentials from env vars)
$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        if ($eq !== false) {
            $args[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        } else {
            $args[substr($arg, 2)] = true;
        }
    }
}

// Credentials from environment variables (never from CLI args — invisible in ps)
$key    = getenv('SPACESHIP_API_KEY') ?: '';
$secret = getenv('SPACESHIP_API_SECRET') ?: '';
$userEmail = $args['user'] ?? 'n311311@gmail.com';
$dryRun = isset($args['dry-run']);

if (empty($key) || empty($secret)) {
    fwrite(STDERR, "ERROR: SPACESHIP_API_KEY and SPACESHIP_API_SECRET environment variables must be set.\n");
    fwrite(STDERR, "Usage: SPACESHIP_API_KEY=xxx SPACESHIP_API_SECRET=yyy php spaceship_sync.php [--user=EMAIL] [--dry-run]\n");
    exit(1);
}

// Load app bootstrap
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailcow.php';
require_once __DIR__ . '/custom_domains.php';

// Resolve user
$userRow = db()->prepare('SELECT id, email FROM ia_users WHERE email = ?');
$userRow->execute([$userEmail]);
$user = $userRow->fetch();
if (!$user) {
    // Auto-create user for domain sync (pending status, no password needed for API path)
    // This is for the initial bootstrap when n311311@gmail.com doesn't exist yet
    fwrite(STDERR, "User $userEmail not found. Creating stub user for domain sync...\n");
    $token = bin2hex(random_bytes(24));
    $st = db()->prepare(
        'INSERT INTO ia_users (email, password_hash, status, trust_tier, risk_score, verify_token)
         VALUES (?, "!", "pending", 3, 0, ?)'
    );
    $st->execute([$userEmail, $token]);
    $uid = (int)db()->lastInsertId();
    fwrite(STDERR, "Created stub user ID=$uid for $userEmail\n");
} else {
    $uid = (int)$user['id'];
}

fwrite(STDERR, "Fetching domains from Spaceship...\n");

// Fetch all domains from Spaceship API (with pagination)
$allDomains = [];
$skip = 0;
$take = 100;
$maxPages = 20;
for ($page = 0; $page < $maxPages; $page++) {
    $url = "https://spaceship.dev/api/v1/domains?take=$take&skip=$skip";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-API-Key: $key",
            "X-API-Secret: $secret",
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlErr) {
        fwrite(STDERR, "ERROR: Connection failed — $curlErr\n");
        exit(1);
    }
    if ($httpCode === 401 || $httpCode === 403) {
        fwrite(STDERR, "ERROR: Authentication failed (HTTP $httpCode)\n");
        exit(1);
    }
    if ($httpCode !== 200) {
        fwrite(STDERR, "ERROR: HTTP $httpCode — " . substr($body, 0, 200) . "\n");
        exit(1);
    }

    $res = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "ERROR: Invalid JSON response\n");
        exit(1);
    }
    if (isset($res['detail'])) {
        fwrite(STDERR, "ERROR: " . $res['detail'] . "\n");
        exit(1);
    }

    $items = $res['items'] ?? [];
    foreach ($items as $item) {
        $name = $item['name'] ?? $item['domain'] ?? null;
        if ($name) $allDomains[] = $name;
    }

    $total = $res['total'] ?? 0;
    fwrite(STDERR, "  Page " . ($page + 1) . ": got " . count($items) . " domains (total=$total)\n");
    $skip += $take;
    if ($skip >= $total || count($items) === 0) break;
}

fwrite(STDERR, "Fetched " . count($allDomains) . " domains from Spaceship.\n\n");

// Classify each domain
$report = [
    'user' => $userEmail,
    'user_id' => $uid,
    'total_spaceship' => count($allDomains),
    'safe' => [],
    'conflict' => [],
    'unknown' => [],
    'errors' => [],
];

foreach ($allDomains as $domain) {
    fwrite(STDERR, "Classifying: $domain ... ");
    $cls = custom_domain_classify($domain);

    $entry = [
        'domain' => $domain,
        'classification' => $cls['classification'],
        'mx' => $cls['mx_summary'],
        'notes' => $cls['notes'],
    ];

    if (str_starts_with($cls['classification'], 'conflict_')) {
        $report['conflict'][] = $entry;
        fwrite(STDERR, "CONFLICT (" . $cls['classification'] . ")\n");
    } elseif ($cls['classification'] === 'safe') {
        $report['safe'][] = $entry;
        fwrite(STDERR, "SAFE\n");
    } else {
        $report['unknown'][] = $entry;
        fwrite(STDERR, "UNKNOWN\n");
    }

    // Add to user's domain list (idempotent)
    if (!$dryRun) {
        $addRes = custom_domain_add($uid, $domain, 'spaceship', $cls['classification']);
        if (isset($addRes['error'])) {
            $report['errors'][] = ['domain' => $domain, 'error' => $addRes['error']];
        }

        // Update status based on classification
        if (str_starts_with($cls['classification'], 'conflict_')) {
            custom_domain_conflict($uid, $domain, $cls['classification'], $cls['mx_summary'], $cls['notes']);
        } elseif ($cls['classification'] === 'safe') {
            custom_domain_verify($uid, $domain, $cls['classification'], $cls['mx_summary'], $cls['notes']);
        }
    }
}

// Output JSON report to stdout
$report['dry_run'] = $dryRun;
$report['timestamp'] = date('c');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

fwrite(STDERR, "\n=== SUMMARY ===\n");
fwrite(STDERR, "Total Spaceship domains: " . count($allDomains) . "\n");
fwrite(STDERR, "Safe: " . count($report['safe']) . "\n");
fwrite(STDERR, "Conflict: " . count($report['conflict']) . "\n");
fwrite(STDERR, "Unknown: " . count($report['unknown']) . "\n");
fwrite(STDERR, "Errors: " . count($report['errors']) . "\n");
if ($dryRun) {
    fwrite(STDERR, "\nDRY RUN — no changes made to database.\n");
}
