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
<div class="card card-highlight" style="max-width:420px;margin:48px auto">
  <div style="text-align:center;margin-bottom:16px">
    <div style="font-size:2rem;margin-bottom:4px">📬</div>
    <h2 style="font-size:1.3rem;margin-bottom:2px">Welcome Back</h2>
    <p class="sub" style="margin:0">Log in to your Teak Email dashboard</p>
  </div>

  <form method="post" id="login-form" onsubmit="const b=document.getElementById('login-btn');if(b){b.textContent='Logging in...';b.disabled=true;}">
    <?php csrf_field(); ?>
    <label for="email">Email Address</label>
    <input type="email" name="email" id="email" required autofocus autocomplete="email" placeholder="name@example.com">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
      <label for="password" style="margin-bottom:0">Password</label>
      <a href="/forgot_password.php" style="color:#60a5fa;font-size:.78rem">Forgot?</a>
    </div>
    
    <div style="position:relative;margin-bottom:14px">
      <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••" style="padding-right:50px;margin-bottom:0">
      <button type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;font-size:.78rem;cursor:pointer;padding:4px;font-weight:600">Show</button>
    </div>
    
    <button type="submit" class="btn" style="width:100%" id="login-btn">Log In →</button>
  </form>
  
  <p style="text-align:center;margin-top:16px;font-size:.85rem;color:#94a3b8">Don't have an account? <a href="/signup.php" style="color:#60a5fa;font-weight:600">Create Account</a></p>
</div>
<?php page_footer(); ?>
