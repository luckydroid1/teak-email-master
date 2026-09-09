<?php
/**
 * signup.php — signup.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

// Start session early (before any output) so CSRF cookie can be sent
csrf_token();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $res = register_user($_POST['email'] ?? '', $_POST['password'] ?? '');
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            // Redirect to check-email page — clearer UX
            header('Location: /pending.php?email=' . urlencode($_POST['email']));
            exit;
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Sign Up');
alert($error, null);
?>
<div class="card" style="max-width:400px;margin:40px auto">
  <h2>Create Account</h2>
  <p class="sub">Free to start. Redeem your AppSumo code after verifying.</p>
  <form method="post" id="signup-form">
    <?php csrf_field(); ?>
    <label for="su-email">Email</label>
    <input type="email" name="email" id="su-email" required autofocus autocomplete="email">
    <label for="su-password">Password</label>
    <div style="position:relative">
      <input type="password" name="password" id="su-password" required minlength="8" autocomplete="new-password" style="padding-right:50px">
      <button type="button" onclick="const p=document.getElementById('su-password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b7280;font-size:.78rem;cursor:pointer;padding:4px">Show</button>
    </div>
    <p style="font-size:.75rem;color:#64748b;margin:-6px 0 12px">Minimum 8 characters</p>
    <!-- Honeypot: bots fill this, humans don't see it -->
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <input type="text" name="website_url" tabindex="-1" autocomplete="off">
    </div>
    <button class="btn" style="width:100%" id="signup-btn" onclick="this.textContent='Creating account...';this.disabled=true;this.form.submit()">Sign Up</button>
  </form>
  <p style="text-align:center;margin-top:14px;font-size:.85rem;color:#64748b">Already have an account? <a href="/login.php" style="color:#3b82f6">Login</a></p>
</div>
<?php page_footer(); ?>
