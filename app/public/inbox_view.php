<?php
/**
 * inbox_view.php — View inbox emails with HTML rendering + OTP extraction.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/otp.php';
require_once __DIR__ . '/../src/mailcow.php';

$user = require_login();
$uid = (int)$user['id'];
$email = $_GET['email'] ?? '';
$msg_id = (int)($_GET['msg'] ?? 0);

$inbox = inbox_owned($uid, $email);
if (!$inbox) {
    require_once __DIR__ . '/_layout.php';
    page_header('Inbox', $user);
    echo '<div class="card"><h2>Inbox not found</h2><p><a href="/inboxes.php" class="btn">Back to Inboxes</a></p></div>';
    page_footer();
    exit;
}

$read_raw = null; $otp = null; $html_body = null; $subject_line = '';
if ($msg_id > 0) {
    $raw = mailcow_fetch_message($email, $msg_id);
    if ($raw !== null) {
        $read_raw = $raw;
        $parsed = extract_otp($raw);
        $otp = $parsed['otp'];

        // Extract subject from raw
        if (preg_match('/^hdr\.subject:\s*(.+)$/m', $raw, $m)) {
            $subject_line = trim($m[1]);
        }

        // Extract HTML body
        if (preg_match('/text\.html:\s*(.+?)(?=\n[a-z]|$)/s', $raw, $m)) {
            $html_body = trim($m[1]);
        }

        credit_mutate($uid, -1, 'api_call', "read:$msg_id");
    }
}
$emails = mailcow_fetch_inbox($email);

require_once __DIR__ . '/_layout.php';
page_header($subject_line ?: 'Inbox', $user);
?>
<a href="/inboxes.php" class="back">← Back to Inboxes</a>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
  <div>
    <h2 style="margin:0">📥 <?= htmlspecialchars($email) ?></h2>
    <?php if ($subject_line): ?>
      <p style="font-size:.85rem;color:#94a3b8;margin-top:2px"><?= htmlspecialchars($subject_line) ?></p>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px">
    <a href="/send.php?from=<?= urlencode($email) ?>" class="btn btn-success" style="text-decoration:none">Reply</a>
    <button onclick="refreshInbox()" id="sync-btn" class="btn" style="background:#8b5cf6">Sync</button>
  </div>
</div>

<?php if ($otp !== null): ?>
<div class="card" style="border-color:#059669;text-align:center">
  <div class="sub" style="margin-bottom:8px">🔐 One-Time Password / Code</div>
  <div class="otp-badge"><?= htmlspecialchars($otp) ?></div>
  <p style="margin-top:10px;font-size:.8rem;color:#6b7280">Extracted automatically from the email below.</p>
</div>
<?php endif; ?>

<?php if ($read_raw !== null): ?>
<!-- Email content view -->
<div class="card">
  <a href="?email=<?= urlencode($email) ?>" class="back">← Back to list</a>

  <?php if ($html_body): ?>
    <!-- HTML email -->
    <div class="msg-view" style="background:#fff;padding:0;overflow:hidden">
      <iframe id="email-frame" style="width:100%;min-height:300px;border:none;background:#fff" sandbox="allow-same-origin" onload="this.style.height=this.contentDocument.body.scrollHeight+40+\'px\'"></iframe>
    </div>
    <script>
    document.getElementById('email-frame').srcdoc = <?= json_encode($html_body) ?>;
    </script>
  <?php else: ?>
    <!-- Plain text email -->
    <div class="msg-view"><?= nl2br(htmlspecialchars($read_raw)) ?></div>
  <?php endif; ?>

  <!-- Reply button -->
  <div style="margin-top:16px;display:flex;gap:8px">
    <?php
    $reply_to = '';
    if (preg_match('/^hdr\.from:\s*(.+)$/m', $read_raw ?? '', $m)) {
        $reply_to = trim($m[1]);
        // Extract just email from "Name <email>" format
        if (preg_match('/<([^>]+)>/', $reply_to, $e)) {
            $reply_to = $e[1];
        }
    }
    ?>
    <a href="/send.php?from=<?= urlencode($email) ?>&reply_to=<?= urlencode($reply_to) ?>&reply_subject=<?= urlencode($subject_line) ?>" class="btn btn-success" style="text-decoration:none">✉️ Reply to <?= htmlspecialchars(substr($reply_to, 0, 30)) ?></a>
  </div>
</div>

<?php elseif (!empty($emails)): ?>
<!-- Email list -->
<div class="card" style="padding:8px">
  <?php $i = count($emails); foreach ($emails as $m): ?>
  <a href="?email=<?= urlencode($email) ?>&msg=<?= (int)$m['uid'] ?>" class="email-row">
    <span style="color:#4b5563;font-size:.72rem;width:20px;text-align:right"><?= $i-- ?></span>
    <span class="email-from"><?= htmlspecialchars(substr($m['from'] ?? '?', 0, 28)) ?></span>
    <span class="email-subject"><?= htmlspecialchars(substr($m['subject'] ?? '(no subject)', 0, 50)) ?></span>
    <span class="email-date"><?= htmlspecialchars(substr($m['date'] ?? '', 5, 11)) ?></span>
  </a>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px">
  <div style="font-size:2rem;margin-bottom:8px">📭</div>
  <p style="color:#6b7280;font-size:.95rem">No emails yet</p>
  <p style="color:#4b5563;font-size:.82rem;margin-top:4px">Emails sent to this inbox will appear here.</p>
  <button onclick="refreshInbox()" class="btn btn-ghost" style="margin-top:16px">🔄 Refresh</button>
</div>
<?php endif; ?>

<script>
function refreshInbox() {
  const btn = document.getElementById('sync-btn') || document.querySelector('[onclick="refreshInbox()"]');
  if (btn) { btn.textContent = '⏳ Syncing...'; btn.disabled = true; }
  setTimeout(() => location.reload(), 300);
}
</script>
<?php page_footer(); ?>
