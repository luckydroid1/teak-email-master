<?php
/**
 * inbox_view.php — View inbox emails with HTML rendering + OTP extraction + copy.
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
    echo '<div class="card" style="text-align:center;padding:30px"><h2>Inbox Not Found</h2><p style="color:#94a3b8;margin-bottom:16px">The requested inbox does not exist or does not belong to your account.</p><p><a href="/inboxes.php" class="btn">Back to Inboxes</a></p></div>';
    page_footer();
    exit;
}

// Handle Send Test OTP simulation
$test_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_otp'])) {
    if (csrf_validate()) {
        $test_code = strval(rand(100000, 999999));
        $test_subject = "Your Security Verification Code: $test_code";
        $test_body = "Hello,\n\nYour one-time verification code is:\n\n$test_code\n\nThis code expires in 10 minutes. If you did not request this, please ignore.\n\n— Teak Email QA Simulator";
        mail_send($email, $test_subject, $test_body);
        $test_sent = true;
    }
}

// Handle Export Raw EML
if (isset($_GET['export']) && $_GET['export'] === 'eml' && $msg_id > 0) {
    $raw = mailcow_fetch_message($email, $msg_id);
    if ($raw !== null) {
        header('Content-Type: message/rfc822');
        header('Content-Disposition: attachment; filename="email-' . $msg_id . '.eml"');
        echo $raw;
        exit;
    }
}

// Handle Export JSON
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    $all_messages = mailcow_fetch_inbox($email);
    $export_data = [
        'inbox' => $email,
        'exported_at' => date('c'),
        'total_emails' => count($all_messages),
        'messages' => []
    ];
    foreach ($all_messages as $m) {
        $uid_num = (int)$m['uid'];
        $raw = mailcow_fetch_message($email, $uid_num);
        $parsed = extract_otp($raw ?? '');
        $export_data['messages'][] = [
            'uid' => $uid_num,
            'from' => function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($m['from'] ?? '') : ($m['from'] ?? ''),
            'subject' => function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($m['subject'] ?? '') : ($m['subject'] ?? ''),
            'date' => $m['date'] ?? '',
            'otp' => $parsed['otp'],
            'raw' => $raw,
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="inbox-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $email) . '.json"');
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$read_raw = null; $otp = null; $html_body = null; $subject_line = ''; $sender_display = '';
if ($msg_id > 0) {
    $raw = mailcow_fetch_message($email, $msg_id);
    if ($raw !== null) {
        $read_raw = $raw;
        $parsed = extract_otp($raw);
        $otp = $parsed['otp'];

        // Extract subject from raw & decode MIME headers
        if (preg_match('/^hdr\.subject:\s*(.+)$/m', $raw, $m)) {
            $raw_subj = trim($m[1]);
            $subject_line = function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($raw_subj) : $raw_subj;
        }
        if (preg_match('/^hdr\.from:\s*(.+)$/m', $raw, $m)) {
            $raw_from = trim($m[1]);
            $sender_display = function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($raw_from) : $raw_from;
        }

        // Extract HTML body safely
        if (preg_match('/text\.html:\s*([\s\S]+?)(?=\n[a-z0-9_.-]+:|$)/i', $raw, $m)) {
            $html_body = trim($m[1]);
        }

        credit_mutate($uid, -1, 'api_call', "read:$msg_id");
    }
}
$emails = mailcow_fetch_inbox($email);
$total_emails = count($emails);
$per_page = 25;
$page = max(1, (int)($_GET['p'] ?? 1));
$total_pages = max(1, (int)ceil($total_emails / $per_page));
$display_emails = array_slice($emails, ($page - 1) * $per_page, $per_page);

require_once __DIR__ . '/_layout.php';
page_header($subject_line ?: 'Inbox ' . $email, $user);
?>
<style>
@keyframes pulseGlow {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}
.pulse-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 8px #22c55e;
  animation: pulseGlow 2s infinite ease-in-out;
}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
  <a href="/inboxes.php" class="back" style="margin-bottom:0">← Back to All Inboxes</a>
  
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="post" style="display:inline;margin:0" onsubmit="const b=this.querySelector('button');b.textContent='⚡ Sending...';b.disabled=true;">
      <?php csrf_field(); ?>
      <input type="hidden" name="send_test_otp" value="1">
      <button type="submit" class="btn btn-sm btn-warning" title="Simulate an instant incoming OTP email">⚡ Send Test OTP</button>
    </form>
    <a href="?email=<?= urlencode($email) ?>&export=json" class="btn-copy" style="text-decoration:none">📥 Export JSON</a>
    <button class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($email, ENT_QUOTES) ?>', this)">📋 Copy Address</button>
    <a href="/send.php?from=<?= urlencode($email) ?>" class="btn btn-sm btn-success">Compose</a>
    <button onclick="refreshInbox()" id="sync-btn" class="btn btn-sm" style="background:#8b5cf6">🔄 Sync (<span id="sync-timer">8s</span>)</button>
  </div>
</div>

<div class="card" style="margin-bottom:16px;background:linear-gradient(180deg,#111827 0%,#0f172a 100%)">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-size:1.15rem;font-weight:700;color:#f8fafc;display:flex;align-items:center;gap:8px">
        <span>📬 <?= htmlspecialchars($email) ?></span>
        <span class="pulse-dot" title="Listening for incoming mail"></span>
      </div>
      <div style="font-size:.78rem;color:#94a3b8;margin-top:2px">
        Live incoming listener active · Retention: <?= (int)$inbox['retention_days'] ?> days · Total: <strong style="color:#e2e8f0"><?= $total_emails ?> messages</strong>
      </div>
    </div>
    
    <?php if ($read_raw === null && !empty($emails)): ?>
      <input type="text" id="email-filter" placeholder="🔍 Filter messages..." oninput="filterMessages()" style="margin-bottom:0;padding:6px 12px;font-size:.82rem;width:180px">
    <?php endif; ?>
  </div>
</div>

<?php if ($otp !== null): ?>
<div class="card" style="border-color:#059669;background:rgba(5,150,105,0.08);text-align:center;padding:24px 16px">
  <div class="sub" style="margin-bottom:6px;color:#86efac;font-weight:600">🔐 Detected Verification Code / OTP</div>
  <div class="otp-badge" title="Click to copy OTP" onclick="copyToClipboard('<?= htmlspecialchars($otp, ENT_QUOTES) ?>', this)"><?= htmlspecialchars($otp) ?></div>
  <p style="margin-top:10px;font-size:.82rem;color:#94a3b8">Click code above to copy to clipboard instantly.</p>
</div>
<?php endif; ?>

<?php if ($read_raw !== null): ?>
<!-- Email content view -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid #1f2937;padding-bottom:12px;flex-wrap:wrap;gap:8px">
    <a href="?email=<?= urlencode($email) ?>" class="back" style="margin-bottom:0">← Back to message list</a>
    
    <div style="display:flex;gap:6px;align-items:center">
      <a href="?email=<?= urlencode($email) ?>&msg=<?= $msg_id ?>&export=eml" class="btn-copy" style="text-decoration:none">📥 Download .EML</a>
      <?php
      $reply_to = $sender_display;
      if (preg_match('/<([^>]+)>/', $sender_display, $e)) {
          $reply_to = $e[1];
      }
      ?>
      <a href="/send.php?from=<?= urlencode($email) ?>&reply_to=<?= urlencode($reply_to) ?>&reply_subject=<?= urlencode($subject_line) ?>" class="btn btn-sm btn-success">✉️ Reply to Sender</a>
    </div>
  </div>

  <div style="margin-bottom:14px">
    <h2 style="font-size:1.2rem;color:#f8fafc;margin-bottom:6px"><?= htmlspecialchars($subject_line ?: '(No Subject)') ?></h2>
    <div style="font-size:.82rem;color:#94a3b8;display:flex;gap:12px;flex-wrap:wrap">
      <span><strong>From:</strong> <?= htmlspecialchars($sender_display ?: 'Unknown') ?></span>
      <span><strong>To:</strong> <?= htmlspecialchars($email) ?></span>
    </div>
  </div>

  <?php if ($html_body): ?>
    <!-- HTML email -->
    <div class="msg-view" style="background:#fff;padding:0;overflow:hidden;border-radius:8px">
      <iframe id="email-frame" style="width:100%;min-height:350px;border:none;background:#fff;display:block" sandbox="allow-same-origin"></iframe>
    </div>
    <script>
    (function() {
      const frame = document.getElementById('email-frame');
      if (frame) {
        frame.srcdoc = <?= json_encode($html_body) ?>;
        frame.addEventListener('load', () => {
          try {
            if (frame.contentDocument && frame.contentDocument.body) {
              const h = frame.contentDocument.body.scrollHeight;
              if (h > 0) frame.style.height = (h + 30) + 'px';
            }
          } catch (e) {
            frame.style.height = '500px';
          }
        });
      }
    })();
    </script>
  <?php else: ?>
    <!-- Plain text email -->
    <div class="msg-view" style="white-space:pre-wrap"><?= htmlspecialchars($read_raw) ?></div>
  <?php endif; ?>
</div>

<?php elseif (!empty($emails)): ?>
<!-- Email list -->
<div class="card" style="padding:4px" id="email-list-table">
  <?php $i = $total_emails - (($page - 1) * $per_page); foreach ($display_emails as $m): ?>
  <a href="?email=<?= urlencode($email) ?>&msg=<?= (int)$m['uid'] ?>" class="email-row email-item-row" data-from="<?= strtolower(htmlspecialchars($m['from'] ?? '')) ?>" data-subject="<?= strtolower(htmlspecialchars($m['subject'] ?? '')) ?>">
    <span style="color:#64748b;font-size:.72rem;width:24px;text-align:right"><?= $i-- ?></span>
    <span class="email-from"><?= htmlspecialchars(substr($m['from'] ?? 'Unknown', 0, 32)) ?></span>
    <span class="email-subject"><?= htmlspecialchars(substr($m['subject'] ?? '(No Subject)', 0, 60)) ?></span>
    <span class="email-date"><?= htmlspecialchars(substr($m['date'] ?? '', 0, 16)) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($total_pages > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:12px">
  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <a href="?email=<?= urlencode($email) ?>&p=<?= $p ?>" class="btn-sm <?= $p === $page ? 'btn-success' : 'btn-ghost' ?>" style="min-width:32px;text-align:center"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card" style="text-align:center;padding:48px 20px">
  <div style="font-size:2.4rem;margin-bottom:8px">📭</div>
  <h3 style="color:#f8fafc;margin-bottom:4px">Waiting for Incoming Emails...</h3>
  <p style="color:#94a3b8;font-size:.85rem;max-width:440px;margin:0 auto 16px">Send an email or OTP verification code to <strong style="color:#60a5fa"><?= htmlspecialchars($email) ?></strong>. This page automatically syncs every 8 seconds.</p>
  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
    <form method="post" style="display:inline;margin:0">
      <?php csrf_field(); ?>
      <input type="hidden" name="send_test_otp" value="1">
      <button type="submit" class="btn btn-warning">⚡ Send Instant Test OTP</button>
    </form>
    <button onclick="refreshInbox()" class="btn btn-ghost">🔄 Sync Manually</button>
  </div>
</div>
<?php endif; ?>

<script>
function refreshInbox() {
  const btn = document.getElementById('sync-btn');
  if (btn) { btn.textContent = '⏳ Syncing...'; btn.disabled = true; }
  setTimeout(() => location.reload(), 300);
}

function filterMessages() {
  const query = (document.getElementById('email-filter')?.value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.email-item-row');
  rows.forEach(r => {
    const from = r.getAttribute('data-from') || '';
    const subject = r.getAttribute('data-subject') || '';
    r.style.display = (from.includes(query) || subject.includes(query)) ? 'flex' : 'none';
  });
}

<?php if ($read_raw === null): ?>
let timeLeft = 8;
const timerEl = document.getElementById('sync-timer');
let countdownInterval = setInterval(() => {
  timeLeft--;
  if (timerEl) timerEl.textContent = timeLeft + 's';
  if (timeLeft <= 0) {
    if (!document.hidden) {
      location.reload();
    } else {
      timeLeft = 8;
    }
  }
}, 1000);
<?php endif; ?>
</script>

<?php page_footer(); ?>
