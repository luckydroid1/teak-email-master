<?php
/**
 * inboxes.php — create / list / delete inboxes.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';
$new_email = null; $new_pw = null;

$tier = user_tier($uid);
$tier_info = tier_info($tier);
$st_redeemed = db()->prepare('SELECT 1 FROM ia_codes WHERE redeemed_by = ?');
$st_redeemed->execute([$uid]);
$has_redeemed = (bool)$st_redeemed->fetch();
$eligible = inbox_eligible_domains($uid);

if (isset($_GET['delete'])) {
    $res = inbox_delete($uid, $_GET['delete']);
    ($res['ok'] ?? false) ? $success = 'Inbox deleted' : $error = $res['error'] ?? 'Unknown error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$has_redeemed) {
        $error = 'Please redeem your AppSumo code first';
    } else {
        $res = inbox_create($uid, $_POST['domain'] ?? '', $_POST['local_part'] ?? '', $tier);
        if (isset($res['ok'])) {
            $new_email = $res['email'];
            $new_pw = $res['password'];
            $success = 'Inbox created!';
        } else {
            $error = $res['error'];
        }
    }
}

$inboxes = inbox_list($uid);
$balance = credit_balance($uid);

require_once __DIR__ . '/_layout.php';
page_header('Inboxes', $user);
alert($error, $success);

if ($new_email):
?>
<div class="card" style="border-color:#22c55e">
  <h2>🎉 Inbox Created!</h2>
  <label>Email Address</label>
  <div class="code"><?= htmlspecialchars($new_email) ?></div>
  <label>Password (save now — shown once)</label>
  <div class="code"><?= htmlspecialchars($new_pw) ?></div>
  <p style="font-size:.8rem;color:#64748b">Use these credentials in your mail client, or just use the built-in viewer below.</p>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2>✨ Create Inbox</h2>
    <?php if (!$has_redeemed): ?>
      <p class="sub" style="color:#fbbf24">⚠️ Redeem your AppSumo code first to create inboxes.</p>
      <a href="/redeem.php" class="btn">Redeem Code</a>
    <?php else: ?>
    <form method="post">
      <label>Domain</label>
      <select name="domain" required>
        <?php foreach ($eligible['all'] as $d): ?>
          <option><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Local Part</label>
      <input type="text" name="local_part" placeholder="e.g. orders.2026" required pattern="[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]" title="Lowercase letters, numbers, dots, hyphens">
      <p style="font-size:.78rem;color:#64748b;margin-bottom:10px">Balance: <span class="mono"><?= number_format($balance) ?> credits</span> · Inbox rent: 60 credits/month</p>
      <button class="btn" style="width:100%" onclick="this.textContent='Creating...';this.disabled=true;this.form.submit()">Create Inbox</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>📋 My Inboxes</h2>
    <?php if (empty($inboxes)): ?>
      <p style="color:#64748b;font-size:.9rem">No inboxes yet</p>
    <?php else: ?>
      <?php foreach ($inboxes as $in): ?>
      <div class="mb">
        <div>
          <div class="e"><?= htmlspecialchars($in['email_address']) ?></div>
          <div class="m">Exp: <?= htmlspecialchars($in['expires_at'] ?? '-') ?></div>
        </div>
        <div style="display:flex;gap:6px">
          <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>" class="btn-sm">View</a>
          <a href="/send.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm" style="background:#10b981">Send</a>
          <a href="/inboxes.php?delete=<?= urlencode($in['email_address']) ?>" class="btn-danger" onclick="return confirm('Delete this inbox?')">Delete</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
