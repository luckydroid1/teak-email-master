<?php
/**
 * forgot_password.php — Request a password reset link.
 *
 * Security:
 * - CSRF token validation
 * - Rate limited (3 per email per hour, 10 per IP per hour)
 * - Generic response always (prevents account enumeration)
 * - Never reveals whether email exists
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

// Start session early (before any output) so CSRF cookie can be sent
csrf_token();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $res = request_password_reset($email);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            // Generic success — always shown regardless of whether email exists
            $success = 'If an account with that email exists, we sent a password reset link. Please check your inbox (and spam folder).';
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Forgot Password');
alert($error, $success);
?>
<div class="card card-highlight" style="max-width:420px;margin:48px auto">
  <div style="text-align:center;margin-bottom:16px">
    <div style="font-size:2rem;margin-bottom:4px">🔑</div>
    <h2 style="font-size:1.3rem;margin-bottom:2px">Reset Password</h2>
    <p class="sub" style="margin:0">We will email you a secure link to reset your password</p>
  </div>
  <?php if ($success): ?>
    <div style="text-align:center;margin-top:14px">
      <a href="/login.php" class="btn">Return to Log In →</a>
    </div>
  <?php else: ?>
    <form method="post" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Sending Link...';b.disabled=true;}">
      <?php csrf_field(); ?>
      <label for="fp-email">Account Email</label>
      <input type="email" name="email" id="fp-email" required autofocus placeholder="name@example.com" autocomplete="email">
      <button type="submit" class="btn" style="width:100%">Send Reset Link →</button>
    </form>
    <p style="text-align:center;margin-top:16px;font-size:.85rem;color:#94a3b8">Remember your password? <a href="/login.php" style="color:#60a5fa;font-weight:600">Log In</a></p>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
