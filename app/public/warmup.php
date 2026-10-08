<?php
/**
 * warmup.php — Modern Domain Warmup & Deliverability Dashboard.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/warmup.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';

// Handle start/stop warmup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission or session expired. Please try again.';
    } else {
        $email = $_POST['email'] ?? '';
        if (!inbox_owned($uid, $email)) {
            $error = 'Inbox not found or access denied.';
        } elseif (isset($_POST['start_warmup'])) {
            $res = warmup_start($email);
            $res['ok'] ? $success = "Warmup cycle started for <strong>$email</strong>" : $error = $res['error'];
        } elseif (isset($_POST['stop_warmup'])) {
            warmup_stop($email);
            $success = "⏸️ Warmup cycle paused for <strong>$email</strong>";
        }
    }
}

$inboxes = inbox_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('Email Warmup', $user);
?>

<div style="max-width:760px;margin:10px auto 40px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px">Email & Domain Warmup</h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">Simulate human engagement to build sender reputation and maximize inbox placement.</p>
    </div>
    <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Back to Dashboard</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-e" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-s" style="margin-bottom:20px"><?= $success ?></div>
  <?php endif; ?>

  <!-- Guidance Cards -->
  <div class="grid2" style="margin-bottom:20px">
    <div class="card" style="border-left:4px solid #f59e0b;margin-bottom:0">
      <h3 style="margin:0 0 6px;font-size:1.05rem;color:#fde68a">New Domains (Requires Warmup)</h3>
      <div style="font-size:0.84rem;color:#cbd5e1;line-height:1.7">
        <div>✓ Automatic peer-to-peer email exchanges</div>
        <div>✓ Simulated opens & thread engagement</div>
        <div>✓ Reaches optimal score over 2–3 weeks</div>
      </div>
    </div>

    <div class="card" style="border-left:4px solid #10b981;margin-bottom:0">
      <h3 style="margin:0 0 6px;font-size:1.05rem;color:#86efac">Aged Domains (Skip Warmup)</h3>
      <div style="font-size:0.84rem;color:#cbd5e1;line-height:1.7">
        <p style="margin:0">Aged domains with established historical DNS records do not require warmup and are ready for peak volume immediately.</p>
        <a href="/domains.php" style="color:#34d399;font-weight:600;font-size:0.8rem;display:inline-block;margin-top:6px">Manage Custom Domains →</a>
      </div>
    </div>
  </div>

  <!-- Inboxes Warmup Status -->
  <div class="card">
    <h2 style="font-size:1.15rem;color:#f8fafc;margin:0 0 16px">Mailbox Warmup Status (<?= count($inboxes) ?>)</h2>

    <?php if (empty($inboxes)): ?>
      <div style="text-align:center;padding:40px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #334155">
        <p style="color:#94a3b8;font-size:0.88rem;margin:0 0 12px">Create at least 2 inboxes to start automated warmup exchanges.</p>
        <a href="/dashboard.php" class="btn btn-sm">Create Inbox Now</a>
      </div>
    <?php else: ?>
      <?php foreach ($inboxes as $in):
        $status = warmup_status($in['email_address']);
      ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;margin-bottom:10px;flex-wrap:wrap;gap:12px">
          <div style="flex:1;min-width:220px">
            <div style="font-weight:700;font-size:0.95rem;color:#f8fafc"><?= htmlspecialchars($in['email_address']) ?></div>
            <div style="font-size:0.8rem;color:#94a3b8;margin-top:4px">
              <?php if ($status['enabled']): ?>
                <span style="color:#22c55e;font-weight:700">Active Warming</span> ·
                Sent: <?= $status['emails_sent'] ?> ·
                Received: <?= $status['emails_received'] ?> ·
                Score: <strong style="color:#f8fafc"><?= $status['score'] ?>/100</strong>
              <?php else: ?>
                <span style="color:#64748b">Inactive / Paused</span>
              <?php endif; ?>
            </div>
            <?php if ($status['enabled']): ?>
              <div style="background:#1e293b;border-radius:20px;height:6px;margin-top:8px;overflow:hidden">
                <div style="background:linear-gradient(90deg,#f59e0b,#22c55e);height:100%;width:<?= min(100, $status['score']) ?>%;border-radius:20px"></div>
              </div>
            <?php endif; ?>
          </div>

          <div>
            <?php if ($status['enabled']): ?>
              <form method="POST" style="display:inline;margin:0" onsubmit="const b=this.querySelector('button');b.textContent='Pausing...';b.disabled=true;">
                <?php csrf_field(); ?>
                <input type="hidden" name="email" value="<?= htmlspecialchars($in['email_address']) ?>">
                <button type="submit" name="stop_warmup" class="btn-sm btn-danger">Pause</button>
              </form>
            <?php else: ?>
              <form method="POST" style="display:inline;margin:0" onsubmit="const b=this.querySelector('button');b.textContent='Starting...';b.disabled=true;">
                <?php csrf_field(); ?>
                <input type="hidden" name="email" value="<?= htmlspecialchars($in['email_address']) ?>">
                <button type="submit" name="start_warmup" class="btn-sm btn-success">Start Warmup</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php page_footer(); ?>
