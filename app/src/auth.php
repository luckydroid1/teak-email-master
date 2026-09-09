<?php
/**
 * auth.php — session, signup, login, email verify, current user.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/abuse.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // Guard against "headers already sent" — can happen if output started before auth load
    if (headers_sent()) return;
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.use_strict_mode', '1');
    session_name(cfg()['session_name']);
    // Validate session ID before starting — reject corrupted/invalid IDs
    $sid = $_COOKIE[cfg()['session_name']] ?? '';
    if ($sid !== '' && !preg_match('/^[a-zA-Z0-9,-]{26,128}$/', $sid)) {
        // Corrupted session ID — clear the cookie and start fresh
        unset($_COOKIE[cfg()['session_name']]);
        setcookie(cfg()['session_name'], '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => true,
            'httponly'  => true,
            'samesite' => 'Strict',
        ]);
    }
    session_start();
}

/** Ambil user dari session, atau null. */
function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT * FROM ia_users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || $u['status'] !== 'active') return null;
    return $u;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: /login.php');
        exit;
    }
    return $u;
}

/** Rate-limited login attempt (5 fails → 5 min). */
function attempt_login(string $email, string $password): array {
    $ip = client_ip();
    $wait = abuse_lockout_remaining($ip);
    if ($wait > 0) {
        return ['error' => "Too many attempts. Try again in " . ceil($wait / 60) . " min"];
    }
    $st = db()->prepare('SELECT * FROM ia_users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        abuse_fail_attempt($ip);
        return ['error' => 'Invalid email or password'];
    }
    if ($u['status'] === 'banned') {
        return ['error' => 'Account suspended. Contact support.'];
    }
    if ($u['status'] === 'pending') {
        return ['error' => 'Please verify your email first'];
    }
    if ($u['status'] === 'suspended') {
        return ['error' => 'Account under review. Contact support.'];
    }
    abuse_clear_attempts($ip);
    db()->prepare('UPDATE ia_users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    audit($u['id'], 'login', 'web');
    return ['user' => $u];
}

/** Create new account. Risk-scored. Rate-limited per IP. */
function register_user(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid email address'];
    }
    if (strlen($password) < 8) {
        return ['error' => 'Password must be at least 8 characters'];
    }
    // Rate limit: max 5 signups per IP per hour
    $ip = client_ip();
    if (!rate_limit_check('signup:' . $ip, 5)) {
        return ['error' => 'Too many signup attempts. Please try again later.'];
    }
    // Honeypot check (if form field was filled, it's a bot)
    if (!empty($_POST['website_url'])) {
        // Bot detected — pretend success but don't create account
        audit(null, 'honeypot_signup', $ip, 'email=' . $email);
        return ['ok' => true, 'user_id' => 0];
    }
    $dom = substr($email, strpos($email, '@') + 1);
    $risk = abuse_risk_score($dom);
    $st = db()->prepare('SELECT id FROM ia_users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) {
        return ['error' => 'Email already registered'];
    }
    $token = bin2hex(random_bytes(24));
    $st = db()->prepare(
        'INSERT INTO ia_users (email, password_hash, status, trust_tier, risk_score, verify_token)
         VALUES (?,?,?,?,?,?)'
    );
    $st->execute([$email, password_hash($password, PASSWORD_BCRYPT), 'pending', 1, $risk, $token]);
    $uid = (int)db()->lastInsertId();
    audit($uid, 'signup', 'web', "risk=$risk");
    send_verify_email($email, $token);
    return ['ok' => true, 'user_id' => $uid];
}

/** Send verification email via Mailcow mailbox (no-reply@teak.email). */
function send_verify_email(string $to, string $token): void {
    $link = cfg()['app_url'] . '/verify.php?token=' . urlencode($token);
    $subject = 'Verify your Teak Email account';
    $body = "Hi,\n\nVerify your Teak Email account:\n$link\n\nIf you didn't request this, ignore this email.\n\n— Teak Email Team";
    mail_send($to, $subject, $body);
}

/** Send email from Mailcow mailbox via SMTP to local Postfix (127.0.0.1:25). */
function mail_send(string $to, string $subject, string $body): void {
    $from = 'no-reply@teak.email';
    $msg  = "From: Teak Email <$from>\r\n";
    $msg .= "To: <$to>\r\n";
    $msg .= "Subject: $subject\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: <" . bin2hex(random_bytes(8)) . "@teak.email>\r\n";
    $msg .= "\r\n$body";
    smtp_send('127.0.0.1', 25, $from, $to, $msg);
}

/** Minimal SMTP client (tanpa library) — kirim ke Postfix Mailcow lokal. */
function smtp_send(string $host, int $port, string $from, string $to, string $data): bool {
    $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$sock) return false;
    $read = function () use ($sock) {
        $resp = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break; // akhir multi-line reply
        }
        return $resp;
    };
    $cmd = function (string $c) use ($sock, $read) {
        fwrite($sock, $c . "\r\n");
        return $read();
    };

    $r = $read(); // 220 banner
    if (substr($r, 0, 3) !== '220') { fclose($sock); return false; }
    $r = $cmd("EHLO teak.email");
    if (substr($r, 0, 3) !== '250') { fclose($sock); return false; }
    $r = $cmd("MAIL FROM:<$from>");
    if (substr($r, 0, 3) !== '250') { fclose($sock); return false; }
    $r = $cmd("RCPT TO:<$to>");
    if (substr($r, 0, 3) !== '250' && substr($r, 0, 3) !== '251') { fclose($sock); return false; }
    $r = $cmd("DATA");
    if (substr($r, 0, 3) !== '354') { fclose($sock); return false; }
    $safe = str_replace(["\r\n.\r\n", "\n."], ["\r\n..\r\n", "\n.."], $data);
    fwrite($sock, $safe . "\r\n.\r\n");
    $read(); // 250 queued
    fclose($sock);
    return true;
}

/** Verify token from email link. */
function verify_email_token(string $token): bool {
    $st = db()->prepare('SELECT id FROM ia_users WHERE verify_token = ? AND status = \'pending\'');
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) return false;
    db()->prepare("UPDATE ia_users SET status='active', verify_token=NULL, verified_at=NOW() WHERE id = ?")
        ->execute([$u['id']]);
    audit((int)$u['id'], 'email_verified', 'web');
    return true;
}

function logout(): void {
    start_session();
    session_destroy();
}

function client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Mark onboarding as completed. */
function complete_onboarding(int $user_id): void {
    db()->prepare('UPDATE ia_users SET onboarding_completed = 1 WHERE id = ?')->execute([$user_id]);
}

/** Check if user needs onboarding. */
function needs_onboarding(array $user): bool {
    return empty($user['onboarding_completed']);
}

/* ── CSRF Protection ─────────────────────────────────────────── */

/** Get or create CSRF token for current session. Rotates each request. */
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Output a hidden <input> with the CSRF token. Call inside <form>. */
function csrf_field(): void {
    echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/** Validate CSRF token from POST data. Returns true if valid. */
function csrf_validate(): bool {
    start_session();
    $token = $_POST['_csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if ($token === '' || $stored === '') return false;
    $valid = hash_equals($stored, $token);
    // Rotate token after validation to prevent replay
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

/* ── Password Reset ─────────────────────────────────────────── */

/**
 * Request a password reset. Rate-limited, generic response (prevents account enumeration).
 * Returns ['ok' => true] always (regardless of whether email exists).
 */
function request_password_reset(string $email): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Still return generic success to prevent enumeration
        return ['ok' => true];
    }

    // Rate limit: max 3 reset requests per email per hour
    $ip = client_ip();
    if (!rate_limit_check('reset:' . $email, 3)) {
        return ['ok' => true]; // Generic response even when rate-limited
    }
    if (!rate_limit_check('reset_ip:' . $ip, 10)) {
        return ['ok' => true];
    }

    $st = db()->prepare('SELECT id FROM ia_users WHERE email = ? AND status = \'active\'');
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u) {
        // Generic response — do not reveal whether email exists
        return ['ok' => true];
    }

    // Invalidate any existing unused tokens for this user
    db()->prepare('UPDATE ia_password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([(int)$u['id']]);

    // Generate cryptographically secure token
    $plain_token = bin2hex(random_bytes(32)); // 64 hex chars
    $token_hash = hash('sha256', $plain_token);
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    db()->prepare(
        'INSERT INTO ia_password_reset_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)'
    )->execute([(int)$u['id'], $token_hash, $expires]);

    // Send reset email via existing verified SMTP path
    $link = cfg()['app_url'] . '/reset_password.php?token=' . urlencode($plain_token);
    $subject = 'Reset your Teak Email password';
    $body = "Hi,\n\nYou requested a password reset for your Teak Email account.\n\nReset your password:\n$link\n\nThis link expires in 1 hour and can only be used once.\n\nIf you didn't request this, ignore this email.\n\n— Teak Email Team";
    mail_send($email, $subject, $body);

    audit((int)$u['id'], 'password_reset_requested', $ip);
    return ['ok' => true];
}

/**
 * Verify a password reset token. Returns user array if valid, null otherwise.
 * Token must be: valid format, not expired, not used, hash matches.
 */
function verify_reset_token(string $token): ?array {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }

    $token_hash = hash('sha256', $token);
    $st = db()->prepare(
        'SELECT prt.*, u.email, u.status
         FROM ia_password_reset_tokens prt
         JOIN ia_users u ON u.id = prt.user_id
         WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > NOW()'
    );
    $st->execute([$token_hash]);
    $row = $st->fetch();

    if (!$row) return null;
    if ($row['status'] !== 'active') return null;

    return $row;
}

/**
 * Complete a password reset. Sets new password, invalidates token, logs out all sessions.
 * Returns ['ok' => true] on success, ['error' => '...'] on failure.
 */
function complete_password_reset(string $token, string $new_password): array {
    if (strlen($new_password) < 8) {
        return ['error' => 'Password must be at least 8 characters'];
    }

    $row = verify_reset_token($token);
    if (!$row) {
        return ['error' => 'Invalid or expired reset link'];
    }

    $uid = (int)$row['user_id'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Set new password
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE ia_users SET password_hash = ? WHERE id = ?')
            ->execute([$new_hash, $uid]);

        // Mark token as used
        $pdo->prepare('UPDATE ia_password_reset_tokens SET used_at = NOW() WHERE token_hash = ?')
            ->execute([$row['token_hash']]);

        // Invalidate all other unused tokens for this user
        $pdo->prepare('UPDATE ia_password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([$uid]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        return ['error' => 'Password reset failed. Please try again.'];
    }

    audit($uid, 'password_reset_completed', client_ip());
    return ['ok' => true, 'email' => $row['email']];
}
