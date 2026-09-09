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
    <h1 style="font-size:1.4rem;margin-bottom:4px">✉️ Send Email</h1>
    <p class="sub" style="margin:0">Send from any of your inboxes. 1 recipient only.</p>
  </div>

  <?php alert($error, $success); ?>

  <?php if (empty($inboxes)): ?>
    <div class="card">
      <p style="color:#94a3b8">You don't have any inboxes yet.</p>
      <a href="/inboxes.php" class="btn" style="margin-top:12px">Create Inbox</a>
    </div>
  <?php else: ?>
  <div class="card">
    <form method="post">
      <?php csrf_field(); ?>
      <input type="hidden" name="send" value="1">

      <label>From</label>
      <select name="from" required style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">
        <?php foreach ($inboxes as $in): ?>
          <option value="<?= htmlspecialchars($in['email_address']) ?>" <?= $from === $in['email_address'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($in['email_address']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>To</label>
      <input type="email" name="to" value="<?= htmlspecialchars($to) ?>" required placeholder="recipient@example.com"
             style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">

      <label>Subject</label>
      <input type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" required placeholder="Your email subject"
             style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">

      <label>Message</label>
      <textarea name="body" required rows="8" placeholder="Type your message..."
                style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px;resize:vertical;font-family:inherit"><?= htmlspecialchars($body) ?></textarea>

      <button type="submit" class="btn" style="width:100%" onclick="this.textContent='Sending...';this.disabled=true;this.form.submit()">Send Email</button>
    </form>

    <p style="font-size:.75rem;color:#475569;margin-top:12px;text-align:center">
      ⚠️ Limited to 1 recipient per message. No CC/BCC allowed.
    </p>
  </div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
