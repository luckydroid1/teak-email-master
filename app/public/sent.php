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
$from_filter = strtolower(trim($_GET['from'] ?? ''));

// Get sent emails
if (!empty($from_filter)) {
    $st = db()->prepare('SELECT * FROM ia_sent_emails WHERE user_id = ? AND LOWER(from_email) = ? ORDER BY sent_at DESC LIMIT 50');
    $st->execute([$uid, $from_filter]);
} else {
    $st = db()->prepare('SELECT * FROM ia_sent_emails WHERE user_id = ? ORDER BY sent_at DESC LIMIT 50');
    $st->execute([$uid]);
}
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
  <div>
    <h1 style="margin:0">Sent Emails History</h1>
    <?php if (!empty($from_filter)): ?>
      <div style="font-size:0.85rem;color:#94a3b8;margin-top:4px">
        Filtered by sender: <strong style="color:#60a5fa"><?= htmlspecialchars($from_filter) ?></strong>
        <a href="/sent.php" style="color:#f87171;margin-left:8px;text-decoration:none">(Clear filter)</a>
        <a href="/inbox_view.php?email=<?= urlencode($from_filter) ?>" style="color:#34d399;margin-left:8px;text-decoration:none">← Back to Inbox</a>
      </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if (!empty($from_filter)): ?>
      <a href="/inbox_view.php?email=<?= urlencode($from_filter) ?>" class="btn btn-sm btn-ghost">← Inbox View</a>
    <?php endif; ?>
    <a href="/send.php<?= !empty($from_filter) ? '?from=' . urlencode($from_filter) : '' ?>" class="btn btn-success" style="font-size:.85rem">Compose New</a>
  </div>
</div>

<?php if ($view_email): ?>
<!-- View specific sent email -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid #1f2937;padding-bottom:12px;flex-wrap:wrap;gap:8px">
    <a href="/sent.php<?= !empty($from_filter) ? '?from=' . urlencode($from_filter) : '' ?>" class="back" style="margin-bottom:0">← Back to sent history</a>
    <a href="/send.php?from=<?= urlencode($view_email['from_email']) ?>&to=<?= urlencode($view_email['to_email']) ?>&subject=<?= urlencode('Follow up: ' . $view_email['subject']) ?>" class="btn btn-sm btn-ghost">Send Follow-up</a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;font-size:.85rem">
    <div>
      <span style="color:#64748b">From:</span>
      <div style="font-weight:600;color:#60a5fa"><?= htmlspecialchars($view_email['from_email']) ?></div>
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
    <label style="font-size:0.85rem;color:#94a3b8;font-weight:600;margin-bottom:8px;display:block">Message Body</label>
    <div class="msg-view" style="background:#0a0e1a;padding:20px;border-radius:8px;border:1px solid #1f2937;white-space:pre-wrap;color:#e2e8f0;line-height:1.7"><?= htmlspecialchars($view_email['body']) ?></div>
  </div>
</div>

<?php elseif (empty($sent)): ?>
<div class="card" style="text-align:center;padding:50px 20px;background:#0a0e1a;border:1px dashed #334155">
  <div style="font-size:2.6rem;margin-bottom:10px">📤</div>
  <h3 style="color:#f8fafc;margin-bottom:6px;font-size:1.15rem">No Sent Emails Yet</h3>
  <p class="sub" style="max-width:440px;margin:0 auto 16px;color:#94a3b8;font-size:0.88rem">
    <?= !empty($from_filter) ? 'No outbound emails have been sent from <strong style="color:#60a5fa">' . htmlspecialchars($from_filter) . '</strong> yet.' : 'Outbound emails sent from any of your active inboxes will be logged and displayed here.' ?>
  </p>
  <a href="/send.php<?= !empty($from_filter) ? '?from=' . urlencode($from_filter) : '' ?>" class="btn btn-primary">Send Email →</a>
</div>

<?php else: ?>
<!-- Sent email list -->
<div style="margin-bottom:16px">
  <?php foreach ($sent as $s): ?>
  <a href="/sent.php?msg=<?= (int)$s['id'] ?><?= !empty($from_filter) ? '&from=' . urlencode($from_filter) : '' ?>" class="mail-item" style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;margin-bottom:8px;text-decoration:none;color:#e2e8f0;transition:all 0.15s">
    <span style="font-size:0.75rem;background:#1e293b;color:#94a3b8;padding:2px 8px;border-radius:6px;white-space:nowrap"><?= htmlspecialchars($s['from_email']) ?></span>
    <span style="width:160px;font-weight:600;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">To: <?= htmlspecialchars($s['to_email']) ?></span>
    <span style="flex:1;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars(substr($s['subject'], 0, 70)) ?></span>
    <span style="font-size:0.78rem;color:#64748b;white-space:nowrap"><?= htmlspecialchars(substr($s['sent_at'], 0, 16)) ?></span>
  </a>
  <?php endforeach; ?>
</div>
<p style="font-size:.78rem;color:#64748b;margin-top:8px;text-align:right">Showing last <?= count($sent) ?> sent emails</p>
<?php endif; ?>

<?php page_footer(); ?>
