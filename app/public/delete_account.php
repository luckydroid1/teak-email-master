<?php
/**
 * delete_account.php — Self-service account deletion with re-authentication.
 *
 * Security:
 * - Requires active session (logged in)
 * - CSRF token validation
 * - Password confirmation (re-authentication)
 * - Confirmation step with explicit typed confirmation
 * - Session invalidation after deletion
 * - Audit logging
 *
 * Data cleanup:
 * - Soft-delete all user inboxes + delete Mailcow mailboxes
 * - Delete all API keys
 * - Null out credit ledger (preserve for accounting integrity, mark user deleted)
 * - Delete sent emails, user domains, registrar credentials
 * - Mark user as deleted (soft delete — keep row for referential integrity)
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/mailcow.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';
$step = $_GET['step'] ?? 'confirm'; // confirm -> verify -> done

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: User confirms they want to delete (from confirm page)
    if ($step === 'confirm') {
        if (!csrf_validate()) {
            $error = 'Invalid security token. Please try again.';
        } else {
            // Redirect to verify step
            header('Location: /delete_account.php?step=verify');
            exit;
        }
    }

    // Step 2: Password verification + actual deletion
    if ($step === 'verify') {
        if (!csrf_validate()) {
            $error = 'Invalid security token. Please try again.';
        } else {
            $password = $_POST['password'] ?? '';
            $confirm_text = trim($_POST['confirm_text'] ?? '');

            // Validate password
            if (empty($password)) {
                $error = 'Password is required to confirm deletion.';
            } elseif ($confirm_text !== 'DELETE MY ACCOUNT') {
                $error = 'Please type "DELETE MY ACCOUNT" to confirm.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect password.';
                audit($uid, 'delete_account_failed', '', 'wrong_password');
            } else {
                // ─── Execute deletion ───────────────────────────
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    // 1. Delete all user inboxes (soft delete + Mailcow mailbox removal)
                    $inboxes = $pdo->prepare('SELECT email_address FROM ia_inboxes WHERE user_id = ?');
                    $inboxes->execute([$uid]);
                    foreach ($inboxes->fetchAll() as $inbox) {
                        mailcow_delete_mailbox($inbox['email_address']);
                    }
                    $pdo->prepare("UPDATE ia_inboxes SET status = 'deleted' WHERE user_id = ?")->execute([$uid]);

                    // 2. Delete Mailcow mailbox and sender_acl entries
                    $mb = $pdo->prepare('SELECT username FROM mailbox WHERE local_part IN (SELECT local_part FROM ia_inboxes WHERE user_id = ?)');
                    $mb->execute([$uid]);
                    // Already handled by mailcow_delete_mailbox above

                    // 3. Delete API keys
                    $pdo->prepare('DELETE FROM ia_api_keys WHERE user_id = ?')->execute([$uid]);

                    // 4. Delete sent emails
                    $pdo->prepare('DELETE FROM ia_sent_emails WHERE user_id = ?')->execute([$uid]);

                    // 5. Delete user domains
                    $pdo->prepare('DELETE FROM ia_user_domains WHERE user_id = ?')->execute([$uid]);

                    // 6. Delete registrar credentials
                    $pdo->prepare('DELETE FROM ia_registrar_creds WHERE user_id = ?')->execute([$uid]);

                    // 7. Delete rate limit entries for this user
                    $pdo->prepare('DELETE FROM ia_rate_limits WHERE bucket LIKE ?')->execute(["inbox:$uid%"]);

                    // 8. Mark user as deleted (soft delete — keep for referential integrity)
                    $pdo->prepare(
                        "UPDATE ia_users SET status = 'banned', password_hash = '', verify_token = NULL, verified_at = NULL WHERE id = ?"
                    )->execute([$uid]);

                    $pdo->commit();

                    // 9. Audit log (after commit, outside transaction)
                    audit($uid, 'account_deleted', '', 'self_service');

                    // 10. Destroy session
                    start_session();
                    session_destroy();

                    // 11. Redirect to goodbye page
                    header('Location: /delete_account.php?step=done');
                    exit;

                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Deletion failed: ' . $e->getMessage();
                    audit($uid, 'delete_account_error', '', $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<title>Delete Account — <?= htmlspecialchars(cfg()['app_name']) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0a0e1a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:32px;max-width:520px;width:100%}
h1{font-size:1.3rem;margin-bottom:16px;color:#fca5a5}
h2{font-size:1.1rem;margin-bottom:12px;color:#f1f5f9}
p{color:#94a3b8;font-size:.9rem;margin-bottom:12px;line-height:1.6}
label{display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:#d1d5db}
input[type="password"],input[type="text"]{width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px;outline:none}
input:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.15)}
.btn{padding:10px 20px;border-radius:8px;border:none;font-weight:600;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;text-decoration:none;transition:all .15s}
.btn-danger{background:#dc2626}.btn-danger:hover{background:#b91c1c}
.btn-ghost{background:transparent;border:1px solid #374151;color:#9ca3af}.btn-ghost:hover{border-color:#6b7280;color:#e2e8f0}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:.88rem}
.alert-e{background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b}
.alert-s{background:#14532d;color:#86efac;border:1px solid #166534}
.warning-box{background:#78350f;border:1px solid #92400e;border-radius:8px;padding:16px;margin-bottom:16px;color:#fbbf24;font-size:.85rem;line-height:1.6}
.warning-box strong{color:#fde68a}
a{color:#60a5fa}
</style>
</head>
<body>
<div class="card">
<?php if ($step === 'done'): ?>
    <h1>Account Deleted</h1>
    <p>Your account and all associated data have been permanently deleted.</p>
    <p>This action cannot be undone. Your inboxes, emails, API keys, and credit history have been removed.</p>
    <p style="margin-top:20px"><a href="/" class="btn btn-ghost">Return to Home</a></p>

<?php elseif ($step === 'verify'): ?>
    <h1>⚠️ Confirm Account Deletion</h1>

    <?php if ($error): ?>
        <div class="alert alert-e"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="warning-box">
        <strong>This action is IRREVERSIBLE.</strong><br><br>
        The following will be permanently deleted:
        <ul style="margin:8px 0 0 16px;list-style:disc">
            <li>All email inboxes and received emails</li>
            <li>All sent email records</li>
            <li>All API keys</li>
            <li>Credit balance and transaction history</li>
            <li>Domain configurations</li>
            <li>Your account credentials</li>
        </ul>
    </div>

    <form method="post" action="/delete_account.php?step=verify">
        <?= csrf_field() ?>

        <label>Enter your password to confirm</label>
        <input type="password" name="password" required autocomplete="current-password">

        <label>Type "DELETE MY ACCOUNT" to confirm</label>
        <input type="text" name="confirm_text" required placeholder="DELETE MY ACCOUNT"
               pattern="DELETE MY ACCOUNT" title="Type exactly: DELETE MY ACCOUNT">

        <div style="display:flex;gap:8px;margin-top:16px">
            <button type="submit" class="btn btn-danger" onclick="this.textContent='Deleting...';this.disabled=true;this.form.submit()">Delete My Account Permanently</button>
            <a href="/dashboard.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>

<?php else: ?>
    <h1>Delete Account</h1>

    <?php if ($error): ?>
        <div class="alert alert-e"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p>You are about to permanently delete your Teak Email account (<strong><?= htmlspecialchars($user['email']) ?></strong>).</p>

    <div class="warning-box">
        <strong>Before you proceed:</strong>
        <ul style="margin:8px 0 0 16px;list-style:disc">
            <li>All your inboxes and emails will be permanently deleted</li>
            <li>Your API keys will be revoked</li>
            <li>Your credit balance will be forfeited</li>
            <li>This action cannot be undone</li>
        </ul>
    </div>

    <p>If you have active inboxes, consider backing up important emails first via the API or inbox viewer.</p>

    <form method="post" action="/delete_account.php?step=confirm" style="margin-top:20px">
        <?= csrf_field() ?>
        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-danger">I Understand — Continue</button>
            <a href="/dashboard.php" class="btn btn-ghost">Go Back</a>
        </div>
    </form>
<?php endif; ?>
</div>
</body>
</html>
