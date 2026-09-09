<?php
/**
 * api.php — REST API CodeInbox.
 *
 * Auth: Authorization: Bearer cib_xxx
 *
 * Endpoints:
 *   GET  /api/inboxes                          → list inboxes
 *   POST /api/inboxes                          → create inbox {domain, local_part}
 *   DELETE /api/inboxes/{email}                → delete inbox
 *   GET  /api/inboxes/{email}/emails           → list emails
 *   GET  /api/inboxes/{email}/emails/{uid}     → read email
 *   GET  /api/inboxes/{email}/otp/{uid}        → extract OTP
 *   GET  /api.php/domains                      → list user domains
 *   POST /api.php/domains/sync                 → sync from Spaceship (server-side creds only)
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/abuse.php';
require_once __DIR__ . '/../src/apikey.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/../src/custom_domains.php';
require_once __DIR__ . '/../src/registrar.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Parse Authorization header
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'authorization') { $auth = $v; break; }
    }
}
$key = preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m) ? $m[1] : '';
$res = apikey_auth($key);
if (isset($res['rate_limited'])) {
    api_json(['error' => 'API rate limit exceeded'], 429);
}
if (!$res) {
    api_json(['error' => 'Unauthorized'], 401);
}
$user = $res['user'];
$uid = (int)$user['id'];

// Route dari PATH_INFO: /inboxes/... 
$path = $_SERVER['PATH_INFO'] ?? '/';
$parts = array_values(array_filter(explode('/', $path), fn($p) => $p !== ''));
$method = $_SERVER['REQUEST_METHOD'];

$segment = $parts[0] ?? '';

switch ($segment) {
    case 'inboxes':
        $email = isset($parts[1]) ? urldecode($parts[1]) : '';
        $sub   = $parts[2] ?? '';
        $uid_num = isset($parts[3]) ? (int)$parts[3] : 0;

        if ($email === '') {
            // List / create
            if ($method === 'GET') {
                api_json(['ok' => true, 'inboxes' => inbox_list($uid)]);
            }
            if ($method === 'POST') {
                $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
                $domain = trim($body['domain'] ?? '');
                $local_part = trim($body['local_part'] ?? '');
                $tier = user_tier($uid);
                $res = inbox_create($uid, $domain, $local_part, $tier);
                if (!isset($res['ok'])) api_json(['error' => $res['error']], 400);
                api_json(['ok' => true, 'email' => $res['email'], 'password' => $res['password']], 201);
            }
            api_json(['error' => 'Method not allowed'], 405);
        }

        if ($sub === 'emails') {
            if ($method === 'GET' && $uid_num === 0) {
                $res = inbox_emails($uid, $email);
                if (!isset($res['ok'])) api_json(['error' => $res['error']], 404);
                api_json(['ok' => true, 'inbox' => $res['inbox'], 'emails' => $res['emails']]);
            }
            if ($method === 'GET' && $uid_num > 0) {
                $res = inbox_read($uid, $email, $uid_num);
                if (!isset($res['ok'])) api_json(['error' => $res['error']], 404);
                api_json(['ok' => true, 'raw' => $res['raw']]);
            }
            api_json(['error' => 'Method not allowed'], 405);
        }

        if ($sub === 'otp' && $method === 'GET' && $uid_num > 0) {
            $res = inbox_otp($uid, $email, $uid_num);
            if (!isset($res['ok'])) api_json(['error' => $res['error']], 404);
            api_json(['ok' => true, 'email' => $res['email'], 'uid' => $res['uid'], 'otp' => $res['otp'], 'text' => $res['text']]);
        }

        if ($method === 'DELETE' && $sub === '') {
            $res = inbox_delete($uid, $email);
            if (!isset($res['ok'])) api_json(['error' => $res['error']], 404);
            api_json(['ok' => true]);
        }

        api_json(['error' => 'Not found'], 404);

    case 'balance':
        api_json(['ok' => true, 'balance' => credit_balance($uid), 'tier' => user_tier($uid)]);

    case 'account':
        if ($method === 'DELETE') {
            // API account deletion — requires password in body
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $password = $body['password'] ?? '';
            if (empty($password)) {
                api_json(['error' => 'Password required for account deletion'], 400);
            }
            if (!password_verify($password, $user['password_hash'])) {
                audit($uid, 'delete_account_failed', '', 'wrong_password_api');
                api_json(['error' => 'Incorrect password'], 401);
            }
            // Execute deletion (same logic as delete_account.php)
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // Delete inboxes + Mailcow mailboxes
                $st = $pdo->prepare('SELECT email_address FROM ia_inboxes WHERE user_id = ?');
                $st->execute([$uid]);
                foreach ($st->fetchAll() as $inbox) {
                    mailcow_delete_mailbox($inbox['email_address']);
                }
                $pdo->prepare("UPDATE ia_inboxes SET status = 'deleted' WHERE user_id = ?")->execute([$uid]);
                $pdo->prepare('DELETE FROM ia_api_keys WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM ia_sent_emails WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM ia_user_domains WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM ia_registrar_creds WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM ia_rate_limits WHERE bucket LIKE ?')->execute(["inbox:$uid%"]);
                $pdo->prepare("UPDATE ia_users SET status = 'banned', password_hash = '' WHERE id = ?")->execute([$uid]);
                $pdo->commit();
                audit($uid, 'account_deleted', '', 'api');
                api_json(['ok' => true, 'message' => 'Account permanently deleted']);
            } catch (Throwable $e) {
                $pdo->rollBack();
                api_json(['error' => 'Deletion failed'], 500);
            }
        }
        api_json(['error' => 'Method not allowed'], 405);

    case 'domains':
        if ($method === 'GET') {
            // List domains eligible for inbox creation
            // Excludes jetdigitalpro.com (blocked) and conflict/unknown user domains
            $domains = custom_domain_list($uid);
            $safeDomains = custom_domain_safe($uid);
            $summary = custom_domain_summary($uid);
            $eligible = inbox_eligible_domains($uid);
            api_json([
                'ok' => true,
                'eligible'     => $eligible['all'],     // domains you can actually create inboxes on
                'pool_domains' => $eligible['pool'],    // shared pool (excl. blocked)
                'custom_domains' => $eligible['custom'],// your verified custom domains
                'domains'      => $domains,              // full user domain list (all classifications)
                'safe_domains' => $safeDomains,         // all verified user domains (may include blocked)
                'summary'      => $summary,
            ]);
        }
        if ($method === 'POST') {
            // AI agent: sync domains from Spaceship
            // SECURITY: Credentials are read from server-side env vars only.
            // The request body is IGNORED for credentials — never accept
            // registrar secrets via API body (they would appear in logs/proxies).
            $registrar = trim((json_decode(file_get_contents('php://input'), true) ?: $_POST)['registrar'] ?? '');
            if ($registrar !== 'spaceship') {
                api_json(['error' => 'Only registrar "spaceship" is supported via API'], 400);
            }

            // Read credentials from server-side environment (set by ops, never from request)
            $authKey = getenv('SPACESHIP_API_KEY') ?: '';
            $authSecret = getenv('SPACESHIP_API_SECRET') ?: '';
            if (empty($authKey) || empty($authSecret)) {
                api_json(['error' => 'Spaceship credentials not configured on server. Contact admin.'], 503);
            }

            // Fetch from Spaceship (server-side, credentials never exposed to client response)
            $domains = registrar_fetch_spaceship($authKey, $authSecret);
            if (isset($domains['error'])) {
                api_json(['error' => $domains['error']], 502);
            }

            // Add safe domains to user (idempotent, no inbox creation)
            $added = 0;
            $existing = 0;
            $conflicts = 0;
            foreach ($domains as $d) {
                $cls = custom_domain_classify($d);
                $addRes = custom_domain_add($uid, $d, 'spaceship', $cls['classification']);
                if (isset($addRes['status']) && $addRes['status'] === 'already_exists') {
                    $existing++;
                } elseif (isset($addRes['ok'])) {
                    $added++;
                }
                // Update status based on classification
                if (str_starts_with($cls['classification'], 'conflict_')) {
                    custom_domain_conflict($uid, $d, $cls['classification'], $cls['mx_summary'], $cls['notes']);
                    $conflicts++;
                } elseif ($cls['classification'] === 'safe') {
                    custom_domain_verify($uid, $d, $cls['classification'], $cls['mx_summary'], $cls['notes']);
                }
            }

            api_json([
                'ok' => true,
                'total' => count($domains),
                'added' => $added,
                'existing' => $existing,
                'conflicts' => $conflicts,
                'safe' => count($domains) - $conflicts,
            ]);
        }
        api_json(['error' => 'Method not allowed'], 405);

    default:
        api_json(['error' => 'Not found'], 404);
}
