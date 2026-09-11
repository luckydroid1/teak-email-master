<?php
/**
 * send.php — Send email from an inbox. 1 recipient, no CC/BCC.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/mail_send.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';

$from = $_GET['from'] ?? $_POST['from'] ?? '';
$to = $_POST['to'] ?? '';
$subject = $_POST['subject'] ?? '';
$body = $_POST['body'] ?? '';

// Pre-fill from inbox_view.php
if (!empty($_GET['reply_to'])) {
    $to = $_GET['reply_to'];
    $subject = 'Re: ' . ($_GET['reply_subject'] ?? '');
}

// Validate ownership
if ($from && !inbox_owned($uid, $from)) {
    $error = 'Inbox not found';
    $from = '';
}

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($from) || empty($to) || empty($subject) || empty($body)) {
        $error = 'All fields are required';
    } else {
        $result = send_email($from, $to, $subject, $body);
        if (isset($result['ok'])) {
            $success = 'Email sent successfully!';
            $to = ''; $subject = ''; $body = '';
        } else {
            $error = $result['error'];
        }
    }
}

// Get list of user's inboxes
$inboxes = inbox_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('Send Email', $user);
?>
<div style="max-width:560px;margin:30px auto">
  <a href="/dashboard.php" class="back">← Back to Dashboard</a>

  <div style="text-align:center;margin-bottom:20px">
    <h1 style="font-size:1.5rem;margin-bottom:4px">✉️ Compose Email</h1>
    <p class="sub" style="margin:0">Send an email from your verified clean inbox (1 recipient per message).</p>
  </div>

  <?php alert($error, $success); ?>

  <?php if ($success): ?>
    <div style="text-align:center;margin-bottom:16px">
      <a href="/sent.php" class="btn btn-sm btn-ghost">📬 View in Sent Box →</a>
    </div>
  <?php endif; ?>

  <?php if (empty($inboxes)): ?>
    <div class="card" style="text-align:center;padding:30px">
      <p style="color:#94a3b8">You don't have any inboxes yet to send from.</p>
      <a href="/inboxes.php" class="btn" style="margin-top:12px">Create an Inbox First →</a>
    </div>
  <?php else: ?>
  <div class="card">
    <form method="post" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Sending Email...';b.disabled=true;}">
      <?php csrf_field(); ?>
      <input type="hidden" name="send" value="1">

      <label>From (Sending Inbox)</label>
      <select name="from" required style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">
        <?php foreach ($inboxes as $in): ?>
          <option value="<?= htmlspecialchars($in['email_address']) ?>" <?= $from === $in['email_address'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($in['email_address']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>To (Recipient Address)</label>
      <input type="email" name="to" value="<?= htmlspecialchars($to) ?>" required placeholder="user@company.com"
             style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">

      <label>Subject</label>
      <input type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" required placeholder="Regarding our conversation..."
             style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">

      <label>Message Body</label>
      <textarea name="body" required rows="8" placeholder="Write your message here..."
                style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px;resize:vertical;font-family:inherit"><?= htmlspecialchars($body) ?></textarea>

      <button type="submit" class="btn" style="width:100%">🚀 Send Message</button>
    </form>

    <p style="font-size:.75rem;color:#64748b;margin-top:12px;text-align:center">
      ℹ️ Clean sending reputation policy: 1 recipient per message. Bulk/spam sending is automatically throttled.
    </p>
  </div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
