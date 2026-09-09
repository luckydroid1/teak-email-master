<?php
/**
 * login.php — login.
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
        $res = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            // Check if first-time user → redirect to onboarding
            $u = current_user();
            if ($u && needs_onboarding($u)) {
                header('Location: /getting-started.php');
            } else {
                header('Location: /dashboard.php');
            }
            exit;
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Login');
alert($error, null);
?>
<div class="card" style="max-width:400px;margin:40px auto">
  <h2>Login</h2>
  <form method="post" id="login-form">
    <?php csrf_field(); ?>
    <label for="email">Email</label>
    <input type="email" name="email" id="email" required autofocus autocomplete="email">
    <label for="password">Password</label>
    <div style="position:relative">
      <input type="password" name="password" id="password" required autocomplete="current-password" style="padding-right:50px">
      <button type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b7280;font-size:.78rem;cursor:pointer;padding:4px">Show</button>
    </div>
    <button class="btn" style="width:100%" id="login-btn" onclick="this.textContent='Logging in...';this.disabled=true;this.form.submit()">Login</button>
  </form>
  <p style="text-align:center;margin-top:10px;font-size:.85rem"><a href="/forgot_password.php" style="color:#3b82f6">Forgot password?</a></p>
  <p style="text-align:center;margin-top:14px;font-size:.85rem;color:#64748b">No account? <a href="/signup.php" style="color:#3b82f6">Sign up</a></p>
</div>
<?php page_footer(); ?>
