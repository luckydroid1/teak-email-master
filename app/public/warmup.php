<?php
/**
 * warmup.php — Email warmup dashboard.
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
    $email = $_POST['email'] ?? '';
    if (!inbox_owned($uid, $email)) {
        $error = 'Inbox not found';
    } elseif (isset($_POST['start_warmup'])) {
        $res = warmup_start($email);
        $res['ok'] ? $success = "Warmup started for $email" : $error = $res['error'];
    } elseif (isset($_POST['stop_warmup'])) {
        warmup_stop($email);
        $success = "Warmup paused for $email";
    }
}

$inboxes = inbox_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('Email Warmup', $user);
?>
<div style="max-width:640px;margin:30px auto">

  <div style="text-align:center;margin-bottom:24px">
    <h1 style="font-size:1.5rem;margin-bottom:6px">🔥 Email Warmup</h1>
    <p class="sub" style="margin:0">Automatically send/receive emails to build your domain reputation.</p>
  </div>

  <?php alert($error, $success); ?>

  <!-- How it works -->
  <div class="card" style="border-left:4px solid #f59e0b">
    <h3 style="margin:0 0 8px;font-size:1rem">How Warmup Works</h3>
    <div style="font-size:.85rem;color:#94a3b8;line-height:1.7">
      <p>✅ Your inboxes send natural-looking emails to each other</p>
      <p>✅ Emails are opened and read automatically</p>
      <p>✅ Domain reputation builds over 2-4 weeks</p>
      <p>✅ Better inbox placement for your real emails</p>
    </div>
    <p style="font-size:.8rem;color:#64748b;margin-top:10px">💡 <strong>Tip:</strong> Add at least 2 inboxes for best results. More inboxes = faster warmup.</p>
  </div>

  <?php if (empty($inboxes)): ?>
  <div class="card">
    <p style="color:#94a3b8">Create at least 2 inboxes to start warmup.</p>
    <a href="/inboxes.php" class="btn" style="margin-top:12px">Create Inbox</a>
  </div>
  <?php else: ?>

  <!-- Warmup status per inbox -->
  <div class="card">
    <h3 style="margin:0 0 12px;font-size:1rem">Your Inboxes</h3>
    <?php foreach ($inboxes as $in):
      $status = warmup_status($in['email_address']);
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #334155;flex-wrap:wrap;gap:8px">
      <div style="flex:1;min-width:200px">
        <div style="font-weight:600;font-size:.9rem;color:#e2e8f0"><?= htmlspecialchars($in['email_address']) ?></div>
        <div style="font-size:.8rem;color:#64748b;margin-top:2px">
          <?php if ($status['enabled']): ?>
            <span style="color:#22c55e">🔥 Warming</span> ·
            Sent: <?= $status['emails_sent'] ?> ·
            Received: <?= $status['emails_received'] ?> ·
            Score: <?= $status['score'] ?>/100
          <?php else: ?>
            <span style="color:#64748b">Inactive</span>
          <?php endif; ?>
        </div>
        <?php if ($status['enabled']): ?>
        <div style="background:#1e293b;border-radius:20px;height:6px;margin-top:6px;overflow:hidden">
          <div style="background:linear-gradient(90deg,#f59e0b,#ef4444);height:100%;width:<?= min(100, $status['score']) ?>%;border-radius:20px"></div>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <?php if ($status['enabled']): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="email" value="<?= htmlspecialchars($in['email_address']) ?>">
            <button type="submit" name="stop_warmup" style="background:#7f1d1d;color:#fca5a5;padding:6px 14px;border-radius:6px;border:none;font-size:.8rem;cursor:pointer" onclick="this.textContent='Pausing...';this.disabled=true;this.form.submit()">Pause Warmup</button>
          </form>
        <?php else: ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="email" value="<?= htmlspecialchars($in['email_address']) ?>">
            <button type="submit" name="start_warmup" style="background:#14532d;color:#86efac;padding:6px 14px;border-radius:6px;border:none;font-size:.8rem;cursor:pointer">Start Warmup</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Warmup pairs info -->
  <?php
  $active_count = 0;
  foreach ($inboxes as $in) {
      $s = warmup_status($in['email_address']);
      if ($s['enabled']) $active_count++;
  }
  ?>
  <div class="card" style="border-left:4px solid #3b82f6">
    <h3 style="margin:0 0 8px;font-size:1rem">📊 Warmup Stats</h3>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <div>
        <div style="font-size:1.4rem;font-weight:700;color:#3b82f6"><?= $active_count ?></div>
        <div style="font-size:.75rem;color:#64748b">Active inboxes</div>
      </div>
      <div>
        <div style="font-size:1.4rem;font-weight:700;color:#22c55e"><?= intdiv($active_count, 2) ?></div>
        <div style="font-size:.75rem;color:#64748b">Warmup pairs</div>
      </div>
      <div>
        <div style="font-size:1.4rem;font-weight:700;color:#f59e0b">~<?= $active_count * 5 ?></div>
        <div style="font-size:.75rem;color:#64748b">Emails/day</div>
      </div>
    </div>
    <?php if ($active_count < 2): ?>
    <p style="font-size:.85rem;color:#fbbf24;margin-top:10px">⚠️ Add at least 2 inboxes and start warmup on both for the system to work.</p>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</div>
<?php page_footer(); ?>
