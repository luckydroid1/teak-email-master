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
<div class="card" style="max-width:400px;margin:40px auto">
  <h2>Forgot Password</h2>
  <p class="sub">Enter your email address and we'll send you a link to reset your password.</p>
  <?php if ($success): ?>
    <p style="text-align:center;margin-top:14px"><a href="/login.php" class="btn">Back to Login</a></p>
  <?php else: ?>
    <form method="post">
      <?php csrf_field(); ?>
      <label for="fp-email">Email</label>
      <input type="email" name="email" id="fp-email" required autofocus placeholder="you@example.com" autocomplete="email">
      <button class="btn" style="width:100%" onclick="this.textContent='Sending...';this.disabled=true;this.form.submit()">Send Reset Link</button>
    </form>
    <p style="text-align:center;margin-top:14px;font-size:.85rem;color:#64748b">Remember your password? <a href="/login.php" style="color:#3b82f6">Login</a></p>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
