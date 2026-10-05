<?php
/**
 * send.php — Modern Responsive Email Composer.
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

// Pre-fill from inbox_view.php reply
if (!empty($_GET['reply_to'])) {
    $to = $_GET['reply_to'];
    $subject = 'Re: ' . ($_GET['reply_subject'] ?? '');
}

// Validate ownership
if ($from && !inbox_owned($uid, $from)) {
    $error = 'Sender inbox not found or access denied.';
    $from = '';
}

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    if (!csrf_validate()) {
        $error = 'Invalid form submission or session expired. Please try again.';
    } elseif (empty($from) || empty($to) || empty($subject) || empty($body)) {
        $error = 'All fields (Sender, Recipient, Subject, and Body) are required.';
    } else {
        $result = send_email($from, $to, $subject, $body);
        if (isset($result['ok'])) {
            $success = '🚀 Email dispatched successfully to <strong>' . htmlspecialchars($to) . '</strong>!';
            $to = ''; $subject = ''; $body = '';
        } else {
            $error = $result['error'] ?? 'Failed to send email.';
        }
    }
}

$inboxes = inbox_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('Compose Email', $user);
?>

<div style="max-width:620px;margin:10px auto 40px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px">✉️ Compose & Send Email</h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">Send an outgoing message from your verified domain address.</p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/sent.php" class="btn btn-sm btn-ghost">📬 Sent History</a>
      <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Dashboard</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-e" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-s" style="margin-bottom:20px"><?= $success ?></div>
  <?php endif; ?>

  <?php if (empty($inboxes)): ?>
    <div class="card" style="text-align:center;padding:40px 20px;background:#0a0e1a;border:1px dashed #334155">
      <div style="font-size:2.4rem;margin-bottom:8px">📭</div>
      <h3 style="color:#f8fafc;margin-bottom:6px">No Active Inboxes Found</h3>
      <p style="color:#94a3b8;font-size:0.88rem;margin-bottom:16px">You need at least one verified inbox to send messages.</p>
      <a href="/dashboard.php" class="btn">Create an Inbox First →</a>
    </div>
  <?php else: ?>
    <div class="card" style="border-top:4px solid #3b82f6;padding:24px">
      <form method="POST" action="/send.php" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Sending Email...';b.disabled=true;}">
        <?php csrf_field(); ?>
        <input type="hidden" name="send" value="1">

        <div style="margin-bottom:14px">
          <label style="font-size:0.82rem;font-weight:600;color:#cbd5e1;display:block;margin-bottom:6px">From (Sending Address)</label>
          <select name="from" required style="margin:0;background:#060a12;border-color:#334155">
            <?php foreach ($inboxes as $in): ?>
              <option value="<?= htmlspecialchars($in['email_address']) ?>" <?= $from === $in['email_address'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($in['email_address']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom:14px">
          <label style="font-size:0.82rem;font-weight:600;color:#cbd5e1;display:block;margin-bottom:6px">To (Recipient Address)</label>
          <input type="email" name="to" value="<?= htmlspecialchars($to) ?>" required placeholder="e.g. client@example.com" style="margin:0;background:#060a12;border-color:#334155">
        </div>

        <div style="margin-bottom:14px">
          <label style="font-size:0.82rem;font-weight:600;color:#cbd5e1;display:block;margin-bottom:6px">Subject</label>
          <input type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" required placeholder="Brief topic or inquiry" style="margin:0;background:#060a12;border-color:#334155">
        </div>

        <div style="margin-bottom:18px">
          <label style="font-size:0.82rem;font-weight:600;color:#cbd5e1;display:block;margin-bottom:6px">Message Content</label>
          <textarea name="body" required rows="9" placeholder="Write your message here..." style="margin:0;background:#060a12;border-color:#334155;resize:vertical;font-family:inherit"><?= htmlspecialchars($body) ?></textarea>
        </div>

        <button type="submit" class="btn" style="width:100%;background:#2563eb;font-weight:700">🚀 Dispatch Email</button>
      </form>

      <p style="font-size:0.75rem;color:#64748b;margin:14px 0 0;text-align:center">
        🛡️ Strict single-recipient deliverability safeguards active. Automated SPF/DKIM signature applied.
      </p>
    </div>
  <?php endif; ?>

</div>

<?php page_footer(); ?>
