<?php
/**
 * redeem.php — redeem AppSumo code.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/../src/credits.php';

$user = require_login();
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = redeem_code((int)$user['id'], $_POST['code'] ?? '');
    if (isset($res['error'])) {
        $error = $res['error'];
    } else {
        $success = "Code redeemed! Tier $res[tier] — " . number_format($res['credits']) . " credits added. Balance: " . number_format($res['balance']);
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Redeem', $user);
alert($error, $success);
?>
<div class="card" style="max-width:440px;margin:40px auto">
  <h2>Redeem AppSumo Code</h2>
  <p class="sub">Enter the code you received from AppSumo to activate your lifetime access + credits.</p>
  <form method="post">
    <label>License Code</label>
    <input type="text" name="code" placeholder="AS-XXXXXXXXXXXX" required style="text-transform:uppercase" autofocus>
    <button class="btn" style="width:100%" onclick="this.textContent='Redeeming...';this.disabled=true;this.form.submit()">Redeem Code</button>
  </form>
  <p style="margin-top:14px;font-size:.8rem;color:#64748b">Current balance: <span class="mono"><?= number_format(credit_balance((int)$user['id'])) ?> credits</span></p>
</div>
<?php page_footer(); ?>
