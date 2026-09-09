<?php
/**
 * sent.php — View sent emails.
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
<a href="/dashboard.php" class="back">← Back to Dashboard</a>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
  <h1 style="margin:0">📤 Sent Emails</h1>
  <a href="/send.php" class="btn btn-success" style="text-decoration:none">✉️ New Email</a>
</div>

<?php if ($view_email): ?>
<!-- View specific sent email -->
<div class="card">
  <a href="/sent.php" class="back">← Back to list</a>
  <div style="margin-bottom:12px">
    <div style="font-size:.82rem;color:#6b7280">From</div>
    <div style="font-weight:600;color:#f9fafb"><?= htmlspecialchars($view_email['from_email']) ?></div>
  </div>
  <div style="margin-bottom:12px">
    <div style="font-size:.82rem;color:#6b7280">To</div>
    <div style="font-weight:600;color:#f9fafb"><?= htmlspecialchars($view_email['to_email']) ?></div>
  </div>
  <div style="margin-bottom:12px">
    <div style="font-size:.82rem;color:#6b7280">Subject</div>
    <div style="font-weight:600;color:#f9fafb"><?= htmlspecialchars($view_email['subject']) ?></div>
  </div>
  <div style="margin-bottom:12px">
    <div style="font-size:.82rem;color:#6b7280">Sent</div>
    <div style="color:#9ca3af"><?= htmlspecialchars($view_email['sent_at']) ?></div>
  </div>
  <div style="border-top:1px solid #1f2937;padding-top:12px;margin-top:12px">
    <div class="msg-view" style="background:#0a0e1a;padding:16px;border-radius:8px;border:1px solid #1f2937;white-space:pre-wrap"><?= htmlspecialchars($view_email['body']) ?></div>
  </div>
</div>

<?php elseif (empty($sent)): ?>
<div class="card" style="text-align:center;padding:40px">
  <div style="font-size:2rem;margin-bottom:8px">📤</div>
  <p style="color:#6b7280;font-size:.95rem">No sent emails yet</p>
  <p style="color:#4b5563;font-size:.82rem;margin-top:4px">Emails you send will appear here.</p>
  <a href="/send.php" class="btn" style="margin-top:16px;text-decoration:none">Send First Email →</a>
</div>

<?php else: ?>
<!-- Sent email list -->
<div class="card" style="padding:8px">
  <?php foreach ($sent as $s): ?>
  <a href="/sent.php?msg=<?= (int)$s['id'] ?>" class="email-row">
    <span class="email-from" style="width:100px">To: <?= htmlspecialchars(substr($s['to_email'], 0, 20)) ?></span>
    <span class="email-subject"><?= htmlspecialchars(substr($s['subject'], 0, 45)) ?></span>
    <span class="email-date"><?= htmlspecialchars(substr($s['sent_at'], 5, 11)) ?></span>
  </a>
  <?php endforeach; ?>
</div>
<p style="font-size:.78rem;color:#4b5563;margin-top:8px">Showing last <?= count($sent) ?> sent emails</p>
<?php endif; ?>

<?php page_footer(); ?>
