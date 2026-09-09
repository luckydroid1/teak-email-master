<?php
/**
 * reset_password.php — Set a new password via reset token.
 *
 * Security:
 * - Token verified against hashed DB value
 * - Token single-use (marked used after successful reset)
 * - Token expires after 1 hour
 * - CSRF token validation on form submit
 * - Password policy: min 8 characters
 * - All other tokens for the user invalidated on reset
 * - Audit logging
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

// Start session early (before any output) so CSRF cookie can be sent
csrf_token();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// If no token provided, show error
if ($token === '') {
    $error = 'No reset token provided.';
} else {
    // Verify token exists and is valid (not expired, not used)
    $token_row = verify_reset_token($token);
    if (!$token_row) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

// Handle form submission (only if token is valid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            $res = complete_password_reset($token, $password);
            if (isset($res['error'])) {
                $error = $res['error'];
            } else {
                $success = 'Your password has been reset successfully! You can now login.';
            }
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Reset Password');
alert($error, $success);
?>
<div class="card" style="max-width:400px;margin:40px auto">
  <?php if ($success): ?>
    <h2>Password Reset Complete</h2>
    <p style="text-align:center;margin:12px 0;color:#86efac">Your password has been updated.</p>
    <p style="text-align:center;margin-top:14px"><a href="/login.php" class="btn">Login with New Password</a></p>
  <?php elseif ($error && !$token_row): ?>
    <h2>Reset Failed</h2>
    <p style="text-align:center;margin:12px 0">
      <a href="/forgot_password.php" class="btn">Request New Reset Link</a>
    </p>
  <?php else: ?>
    <h2>Set New Password</h2>
    <form method="post">
      <?php csrf_field(); ?>
      <label for="rp-password">New Password</label>
      <div style="position:relative">
        <input type="password" name="password" id="rp-password" required minlength="8" autofocus placeholder="At least 8 characters" autocomplete="new-password" style="padding-right:50px">
        <button type="button" onclick="const p=document.getElementById('rp-password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b7280;font-size:.78rem;cursor:pointer;padding:4px">Show</button>
      </div>
      <p style="font-size:.75rem;color:#64748b;margin:-6px 0 12px">Minimum 8 characters</p>
      <label for="rp-password2">Confirm New Password</label>
      <input type="password" name="password2" id="rp-password2" required minlength="8" placeholder="Re-enter password" autocomplete="new-password">
      <button class="btn" style="width:100%" onclick="this.textContent='Resetting...';this.disabled=true;this.form.submit()">Reset Password</button>
    </form>
    <p style="text-align:center;margin-top:14px;font-size:.85rem;color:#64748b"><a href="/login.php" style="color:#3b82f6">Back to Login</a></p>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
