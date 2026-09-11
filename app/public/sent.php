<?php
/**
 * sent.php — View sent emails history.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/inbox.php';

$user = require_login();
$uid = (int)$user['id'];

// Get sent emails
$st = db()->prepare('SELECT * FROM ia_sent_emails WHERE user_id = ? ORDER BY sent_at DESC LIMIT 50');
$st->execute([$uid]);
$sent = $st->fetchAll(PDO::FETCH_ASSOC);

// Get specific email
$msg_id = (int)($_GET['msg'] ?? 0);
$view_email = null;
if ($msg_id > 0) {
    $st = db()->prepare('SELECT * FROM ia_sent_emails WHERE id = ? AND user_id = ?');
    $st->execute([$msg_id, $uid]);
    $view_email = $st->fetch(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/_layout.php';
page_header('Sent Emails', $user);
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
  <h1 style="margin:0">📤 Sent Emails History</h1>
  <a href="/send.php" class="btn btn-success" style="font-size:.85rem">✉️ Compose New</a>
</div>

<?php if ($view_email): ?>
<!-- View specific sent email -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid #1f2937;padding-bottom:12px;flex-wrap:wrap;gap:8px">
    <a href="/sent.php" class="back" style="margin-bottom:0">← Back to sent history</a>
    <a href="/send.php?from=<?= urlencode($view_email['from_email']) ?>&to=<?= urlencode($view_email['to_email']) ?>&subject=<?= urlencode('Follow up: ' . $view_email['subject']) ?>" class="btn btn-sm btn-ghost">✉️ Send Follow-up</a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;font-size:.85rem">
    <div>
      <span style="color:#64748b">From:</span>
      <div style="font-weight:600;color:#f8fafc"><?= htmlspecialchars($view_email['from_email']) ?></div>
    </div>
    <div>
      <span style="color:#64748b">To:</span>
      <div style="font-weight:600;color:#f8fafc"><?= htmlspecialchars($view_email['to_email']) ?></div>
    </div>
    <div>
      <span style="color:#64748b">Subject:</span>
      <div style="font-weight:600;color:#f8fafc"><?= htmlspecialchars($view_email['subject']) ?></div>
    </div>
    <div>
      <span style="color:#64748b">Sent At:</span>
      <div style="color:#94a3b8"><?= htmlspecialchars($view_email['sent_at']) ?></div>
    </div>
  </div>

  <div style="border-top:1px solid #1f2937;padding-top:14px">
    <label>Message Content</label>
    <div class="msg-view" style="background:#0a0e1a;padding:16px;border-radius:8px;border:1px solid #1f2937;white-space:pre-wrap"><?= htmlspecialchars($view_email['body']) ?></div>
  </div>
</div>

<?php elseif (empty($sent)): ?>
<div class="card" style="text-align:center;padding:48px 20px">
  <div style="font-size:2.4rem;margin-bottom:8px">📤</div>
  <h3>No Sent Emails Yet</h3>
  <p class="sub" style="max-width:400px;margin:0 auto 16px">Outbound emails sent from any of your active inboxes will be logged and displayed here.</p>
  <a href="/send.php" class="btn">Send First Email →</a>
</div>

<?php else: ?>
<!-- Sent email list -->
<div class="card" style="padding:4px">
  <?php foreach ($sent as $s): ?>
  <a href="/sent.php?msg=<?= (int)$s['id'] ?>" class="email-row">
    <span class="email-from" style="width:140px">To: <?= htmlspecialchars(substr($s['to_email'], 0, 24)) ?></span>
    <span class="email-subject"><?= htmlspecialchars(substr($s['subject'], 0, 50)) ?></span>
    <span class="email-date"><?= htmlspecialchars(substr($s['sent_at'], 0, 16)) ?></span>
  </a>
  <?php endforeach; ?>
</div>
<p style="font-size:.78rem;color:#64748b;margin-top:8px;text-align:right">Showing last <?= count($sent) ?> sent emails</p>
<?php endif; ?>

<?php page_footer(); ?>
