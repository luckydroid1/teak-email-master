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
<div class="card card-highlight" style="max-width:420px;margin:48px auto">
  <div style="text-align:center;margin-bottom:16px">
    <div style="margin-bottom:12px;display:flex;justify-content:center"><img src="/logo-app.png?v=1" alt="BuyDomains By Network Solutions" style="height:44px;width:auto;display:block" /></div>
    <h2 style="font-size:1.3rem;margin-bottom:2px">Create Account</h2>
    <p class="sub" style="margin:0">Instant access to clean inboxes & developer API</p>
  </div>

  <form method="post" id="signup-form" onsubmit="const b=document.getElementById('signup-btn');if(b){b.textContent='Creating account...';b.disabled=true;}">
    <?php csrf_field(); ?>
    <label for="su-email">Email Address</label>
    <input type="email" name="email" id="su-email" required autofocus autocomplete="email" placeholder="name@example.com">
    
    <label for="su-password">Password</label>
    <div style="position:relative;margin-bottom:4px">
      <input type="password" name="password" id="su-password" required minlength="8" autocomplete="new-password" placeholder="••••••••" style="padding-right:50px;margin-bottom:0">
      <button type="button" onclick="const p=document.getElementById('su-password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;font-size:.78rem;cursor:pointer;padding:4px;font-weight:600">Show</button>
    </div>
    <p style="font-size:.75rem;color:#94a3b8;margin:0 0 14px">Minimum 8 characters (letters, numbers, symbols)</p>
    
    <!-- Honeypot: bots fill this, humans don't see it -->
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <input type="text" name="website_url" tabindex="-1" autocomplete="off">
    </div>
    
    <button type="submit" class="btn" style="width:100%" id="signup-btn">Create Free Account →</button>
  </form>
  
  <p style="text-align:center;margin-top:16px;font-size:.85rem;color:#94a3b8">Already have an account? <a href="/login.php" style="color:#60a5fa;font-weight:600">Log In</a></p>
</div>
<?php page_footer(); ?>
