<?php
/**
 * inboxes.php — create / list / manage / reset inboxes.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/../src/mailcow.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';
$new_email = null; $new_pw = null;
$reset_email = null; $reset_pw = null;

$tier = user_tier($uid);
$tier_info = tier_info($tier);
$st_redeemed = db()->prepare('SELECT 1 FROM ia_codes WHERE redeemed_by = ?');
$st_redeemed->execute([$uid]);
$has_redeemed = (bool)$st_redeemed->fetch();
$eligible = inbox_eligible_domains($uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission or CSRF token expired. Please try again.';
    } elseif (isset($_POST['delete_email'])) {
        $res = inbox_delete($uid, $_POST['delete_email']);
        ($res['ok'] ?? false) ? $success = 'Inbox deleted successfully' : $error = $res['error'] ?? 'Unknown error';
    } elseif (isset($_POST['reset_password_email'])) {
        $r_email = trim($_POST['reset_password_email']);
        if (inbox_owned($uid, $r_email)) {
            $gen_pw = bin2hex(random_bytes(6));
            if (mailcow_reset_mailbox_password($r_email, $gen_pw)) {
                $reset_email = $r_email;
                $reset_pw = $gen_pw;
                $success = "Mailbox password for $r_email has been reset!";
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        } else {
            $error = 'Inbox not found';
        }
    } elseif (isset($_POST['create_inbox'])) {
        if (!$has_redeemed) {
            $error = 'Please redeem your AppSumo code first to activate your account slots';
        } else {
            $res = inbox_create($uid, $_POST['domain'] ?? '', $_POST['local_part'] ?? '', $tier);
            if (isset($res['ok'])) {
                $new_email = $res['email'];
                $new_pw = $res['password'];
                $success = '🎉 Inbox created successfully!';
            } else {
                $error = $res['error'];
            }
        }
    }
}

$inboxes = inbox_list($uid);
$balance = credit_balance($uid);
$max_slots = $tier_info ? $tier_info['inbox_slots'] : 1;

require_once __DIR__ . '/_layout.php';
page_header('My Inboxes', $user);
alert($error, $success);

if ($new_email):
?>
<div class="card" style="border-color:#22c55e;background:rgba(34,197,94,0.05)">
  <h2 style="color:#86efac;margin-bottom:6px">🎉 New Inbox Ready to Receive Mail</h2>
  <p class="sub" style="margin-bottom:14px">Save your IMAP/SMTP credentials below if using an external mail client. You can also view emails right in this dashboard.</p>
  
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
    <div>
      <label>Email Address</label>
      <div style="display:flex;gap:6px">
        <input type="text" readonly value="<?= htmlspecialchars($new_email) ?>" style="margin-bottom:0;font-family:monospace">
        <button class="btn-copy" style="white-space:nowrap" onclick="copyToClipboard('<?= htmlspecialchars($new_email, ENT_QUOTES) ?>', this)">Copy</button>
      </div>
    </div>
    <div>
      <label>Mailbox Password (IMAP/POP3)</label>
      <div style="display:flex;gap:6px">
        <input type="text" readonly value="<?= htmlspecialchars($new_pw) ?>" style="margin-bottom:0;font-family:monospace;color:#fbbf24">
        <button class="btn-copy" style="white-space:nowrap" onclick="copyToClipboard('<?= htmlspecialchars($new_pw, ENT_QUOTES) ?>', this)">Copy</button>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px">
    <a href="/inbox_view.php?email=<?= urlencode($new_email) ?>" class="btn btn-sm btn-success">Open Inbox Viewer →</a>
    <a href="/send.php?from=<?= urlencode($new_email) ?>" class="btn btn-sm" style="background:#2563eb">Compose Email →</a>
  </div>
</div>
<?php endif; ?>

<?php if ($reset_email): ?>
<div class="card" style="border-color:#f59e0b;background:rgba(245,158,11,0.05)">
  <h2 style="color:#fbbf24;margin-bottom:6px">🔑 Password Reset for <?= htmlspecialchars($reset_email) ?></h2>
  <p class="sub" style="margin-bottom:12px">Here is your new IMAP/SMTP password. Copy it now:</p>
  <div style="display:flex;gap:6px;max-width:400px">
    <input type="text" readonly value="<?= htmlspecialchars($reset_pw) ?>" style="margin-bottom:0;font-family:monospace;color:#fbbf24">
    <button class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($reset_pw, ENT_QUOTES) ?>', this)">Copy</button>
  </div>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h2 style="margin:0">✨ Create New Inbox</h2>
      <span style="font-size:.78rem;color:#94a3b8">Slots: <strong style="color:#f8fafc"><?= count($inboxes) ?> / <?= $max_slots ?></strong></span>
    </div>

    <?php if (!$has_redeemed): ?>
      <div style="background:#1c1917;border:1px solid #78350f;padding:14px;border-radius:8px;margin-bottom:12px">
        <p style="color:#fbbf24;font-size:.85rem;margin:0 0 8px">⚠️ <strong>AppSumo Code Required:</strong> Redeem your license code to activate your inbox slots.</p>
        <a href="/redeem.php" class="btn btn-sm" style="background:#d97706">Redeem Code Now →</a>
      </div>
    <?php else: ?>
    <form method="post" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Creating Inbox...';b.disabled=true;}">
      <?php csrf_field(); ?>
      <input type="hidden" name="create_inbox" value="1">

      <?php if ($balance < 60): ?>
      <div style="background:rgba(217,119,6,0.15);border:1px solid #d97706;padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:.82rem;color:#fde68a">
        ⚡ <strong>Low Credit Balance:</strong> You have <?= number_format($balance) ?> credits remaining. <a href="/redeem.php" style="color:#60a5fa;text-decoration:underline">Redeem code or top-up →</a>
      </div>
      <?php endif; ?>
      
      <label>Choose Clean Domain</label>
      <select name="domain" required>
        <?php foreach ($eligible['all'] as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?> (Clean & DKIM/SPF Ready)</option>
        <?php endforeach; ?>
      </select>
      
      <label>Username / Local Part</label>
      <input type="text" name="local_part" placeholder="e.g. support, orders.2026, bot-01" required pattern="[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]" title="Lowercase letters, numbers, dots, hyphens" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '')" autofocus>
      
      <div style="background:#0a0e1a;padding:10px 12px;border-radius:8px;border:1px solid #1f2937;margin-bottom:14px;font-size:.78rem;color:#94a3b8">
        ⚡ Balance: <strong style="color:#60a5fa"><?= number_format($balance) ?> credits</strong> · Monthly rent: 60 credits/inbox
      </div>
      
      <button type="submit" class="btn" style="width:100%">⚡ Create Instant Inbox</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <h2 style="margin:0">📋 Your Inboxes (<?= count($inboxes) ?>)</h2>
      <?php if (!empty($inboxes)): ?>
        <input type="text" id="inbox-search" placeholder="🔍 Search inboxes..." oninput="filterInboxes()" style="margin-bottom:0;padding:6px 10px;font-size:.8rem;width:150px">
      <?php endif; ?>
    </div>

    <?php if (empty($inboxes)): ?>
      <div style="text-align:center;padding:30px 10px">
        <div style="font-size:2rem;margin-bottom:6px">📭</div>
        <p style="color:#94a3b8;font-size:.88rem;margin:0">You haven't created any inboxes yet.</p>
        <p style="color:#64748b;font-size:.78rem;margin-top:4px">Use the form on the left to get your first email address.</p>
      </div>
    <?php else: ?>
      <div id="inbox-list-container">
        <?php foreach ($inboxes as $in): ?>
        <div class="mb inbox-card-item" data-email="<?= strtolower(htmlspecialchars($in['email_address'])) ?>">
          <div style="flex:1;min-width:180px">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <span class="e"><?= htmlspecialchars($in['email_address']) ?></span>
              <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?>', this)">Copy</button>
            </div>
            <div class="m">Retention: <?= (int)$in['retention_days'] ?>d · Exp: <?= htmlspecialchars($in['expires_at'] ?? 'Permanent') ?></div>
          </div>
          <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">
            <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>" class="btn-sm btn-success">View</a>
            <a href="/send.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm" style="background:#2563eb">Send</a>
            
            <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Reset mailbox password for <?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?>?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="reset_password_email" value="<?= htmlspecialchars($in['email_address']) ?>">
              <button type="submit" class="btn-sm btn-ghost" title="Reset Mailbox Password">🔑</button>
            </form>

            <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Permanently delete <?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?> and all its emails?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="delete_email" value="<?= htmlspecialchars($in['email_address']) ?>">
              <button type="submit" class="btn-danger">✕</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function filterInboxes() {
  const query = (document.getElementById('inbox-search')?.value || '').toLowerCase().trim();
  const items = document.querySelectorAll('.inbox-card-item');
  items.forEach(el => {
    const email = el.getAttribute('data-email') || '';
    el.style.display = email.includes(query) ? '' : 'none';
  });
}
</script>

<?php page_footer(); ?>
