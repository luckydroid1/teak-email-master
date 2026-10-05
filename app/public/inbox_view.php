<?php
/**
 * inbox_view.php — Modern Clean Inbox Viewer with Dark Theme & Safe Links.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/otp.php';
require_once __DIR__ . '/../src/mailcow.php';
require_once __DIR__ . '/../src/receipt.php';

$user = require_login();
$uid = (int)$user['id'];
$email = $_GET['email'] ?? '';
$msg_id = (int)($_GET['msg'] ?? 0);
$view_mode = $_GET['view'] ?? 'text'; // default to native clean text view

$inbox = inbox_owned($uid, $email);
if (!$inbox) {
    require_once __DIR__ . '/_layout.php';
    page_header('Inbox', $user);
    echo '<div class="card" style="text-align:center;padding:40px 20px"><h2>Inbox Not Found</h2><p style="color:#94a3b8;margin-bottom:16px">The requested mailbox does not exist or does not belong to your account.</p><p><a href="/dashboard.php" class="btn">← Back to Dashboard</a></p></div>';
    page_footer();
    exit;
}

// Handle Send Test OTP simulation
$test_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_otp'])) {
    if (csrf_validate()) {
        $test_code = strval(rand(100000, 999999));
        $test_subject = "Your Security Verification Code: $test_code";
        $test_body = "Hello,\n\nYour one-time verification code is:\n\n$test_code\n\nVerify your account by visiting https://teak.email/verify.php?token=" . bin2hex(random_bytes(16)) . "\n\nThis code expires in 10 minutes.\n\n— Teak Email QA Simulator";
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

$read_raw = null; $otp = null; $html_body = null; $clean_text_body = ''; $subject_line = ''; $sender_display = '';
if ($msg_id > 0) {
    $raw = mailcow_fetch_message($email, $msg_id);
    if ($raw !== null) {
        $read_raw = $raw;
        $parsed = extract_otp($raw);
        $otp = $parsed['otp'];

        if (preg_match('/^hdr\.subject:\s*(.+)$/m', $raw, $m)) {
            $raw_subj = trim($m[1]);
            $subject_line = function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($raw_subj) : $raw_subj;
        }
        if (preg_match('/^hdr\.from:\s*(.+)$/m', $raw, $m)) {
            $raw_from = trim($m[1]);
            $sender_display = function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($raw_from) : $raw_from;
        }
        if (preg_match('/text\.html:\s*([\s\S]+?)(?=\n[a-z0-9_.-]+:|$)/i', $raw, $m)) {
            $html_body = trim($m[1]);
        }

        // Extract pure clean text body
        if (preg_match('/text\.utf8:\s*([\s\S]+)$/i', $raw, $m)) {
            $clean_text_body = trim($m[1]);
        } else {
            $clean_text_body = trim($raw);
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

/**
 * Format plain text into clean, dark-themed HTML with clickable links and highlighted OTPs.
 */
function render_clean_email_text(string $text, ?string $otp_code): string {
    // 1. Sanitize HTML entities (XSS safe)
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2. Safe Auto-Linkify (Only matches http:// or https:// URLs)
    $url_pattern = '/(https?:\/\/[^\s<>"\'\)]+)/i';
    $linked = preg_replace_callback($url_pattern, function($m) {
        $url = $m[1];
        $trailing = '';
        if (preg_match('/[.,;:!?]+$/', $url, $pm)) {
            $trailing = $pm[0];
            $url = substr($url, 0, -strlen($trailing));
        }
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow" style="color:#60a5fa;text-decoration:underline;word-break:break-all;font-weight:600">' . $url . '</a>' . $trailing;
    }, $escaped);

    // 3. Inline highlight OTP code if present
    if (!empty($otp_code)) {
        $otp_escaped = htmlspecialchars($otp_code, ENT_QUOTES, 'UTF-8');
        $otp_replacement = '<span style="background:#064e3b;border:1px solid #10b981;color:#a7f3d0;font-family:monospace;font-weight:800;font-size:1.15em;padding:2px 8px;border-radius:6px;letter-spacing:0.1em;cursor:pointer" title="Click to copy OTP" onclick="copyOtpHero(\'' . $otp_escaped . '\')">' . $otp_escaped . '</span>';
        $linked = preg_replace('/\b' . preg_quote($otp_escaped, '/') . '\b/', $otp_replacement, $linked, 1);
    }

    return nl2br($linked);
}

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
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 10px #22c55e;
  animation: pulseGlow 1.8s infinite ease-in-out;
}

.mail-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: #0a0e1a;
  border: 1px solid #1f2937;
  border-radius: 10px;
  margin-bottom: 8px;
  text-decoration: none;
  color: #e2e8f0;
  transition: all 0.15s;
}
.mail-item:hover {
  border-color: #3b82f6;
  background: #0c1322;
  transform: translateX(2px);
}
</style>

<!-- Top Navigation & Controls Bar -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:10px">
    <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Dashboard</a>
    <a href="/unified.php" class="btn btn-sm btn-ghost">📬 Unified Stream</a>
    <a href="/inboxes.php" class="btn btn-sm btn-ghost">All Inboxes</a>
  </div>

  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="post" style="display:inline;margin:0" onsubmit="const b=this.querySelector('button');b.textContent='⚡ Sending...';b.disabled=true;">
      <?php csrf_field(); ?>
      <input type="hidden" name="send_test_otp" value="1">
      <button type="submit" class="btn btn-sm btn-warning" title="Simulate an instant incoming verification email">⚡ Send Test OTP</button>
    </form>
    <a href="?email=<?= urlencode($email) ?>&export=json" class="btn-copy" style="text-decoration:none">📥 Export JSON</a>
    <button class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($email, ENT_QUOTES) ?>', this)">📋 Copy Address</button>
    <button onclick="refreshInbox()" id="sync-btn" class="btn btn-sm" style="background:#8b5cf6">🔄 Sync (<span id="sync-timer">5s</span>)</button>
  </div>
</div>

<!-- Mailbox Status Banner -->
<div class="card" style="margin-bottom:20px;background:linear-gradient(180deg,#111827 0%,#0f172a 100%)">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:1.25rem;font-weight:800;color:#f8fafc;display:flex;align-items:center;gap:10px">
        <span>📬 <?= htmlspecialchars($email) ?></span>
        <span class="pulse-dot" title="Listening live for incoming mail"></span>
      </div>
      <div style="font-size:0.82rem;color:#94a3b8;margin-top:4px">
        Live Auto-Sync Active · Retention: <?= (int)$inbox['retention_days'] ?> Days · Total: <strong style="color:#e2e8f0"><?= $total_emails ?> messages</strong>
      </div>
    </div>

    <?php if ($read_raw === null && !empty($emails)): ?>
      <div>
        <input type="text" id="email-filter" placeholder="🔍 Search messages..." oninput="filterMessages()" style="margin-bottom:0;padding:8px 14px;font-size:0.85rem;width:200px">
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($read_raw !== null): ?>
<!-- 📧 Detailed Single Message View -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid #1f2937;padding-bottom:12px;flex-wrap:wrap;gap:10px">
    <a href="?email=<?= urlencode($email) ?>" class="btn btn-sm btn-ghost">← Back to Message List</a>

    <div style="display:flex;gap:8px;align-items:center">
      <?php if ($html_body): ?>
        <a href="?email=<?= urlencode($email) ?>&msg=<?= $msg_id ?>&view=<?= $view_mode === 'html' ? 'text' : 'html' ?>" class="btn-copy" style="text-decoration:none">
          <?= $view_mode === 'html' ? '📄 Plain Text View' : '🎨 HTML Card View' ?>
        </a>
      <?php endif; ?>

      <a href="?email=<?= urlencode($email) ?>&msg=<?= $msg_id ?>&export=eml" class="btn-copy" style="text-decoration:none">📥 Download .EML</a>
      <?php
      $rec_info = extract_receipt($raw);
      $reply_to = $sender_display;
      if (preg_match('/<([^>]+)>/', $sender_display, $e)) {
          $reply_to = $e[1];
      }
      ?>
      <?php if ($rec_info['is_receipt']): ?>
        <span class="btn-sm btn-warning" style="cursor:default">🧾 <?= htmlspecialchars($rec_info['currency'] ?? 'USD') ?> <?= number_format((float)($rec_info['amount'] ?? 0), 2) ?></span>
      <?php endif; ?>
      <a href="/send.php?from=<?= urlencode($email) ?>&reply_to=<?= urlencode($reply_to) ?>&reply_subject=<?= urlencode($subject_line) ?>" class="btn btn-sm btn-success">✉️ Reply</a>
    </div>
  </div>

  <div style="margin-bottom:18px;background:#0a0e1a;padding:16px;border-radius:10px;border:1px solid #1f2937">
    <h2 style="font-size:1.25rem;color:#f8fafc;margin-bottom:8px"><?= htmlspecialchars($subject_line ?: '(No Subject)') ?></h2>
    <div style="font-size:0.85rem;color:#94a3b8;display:flex;flex-direction:column;gap:4px">
      <div><strong style="color:#cbd5e1">From:</strong> <?= htmlspecialchars($sender_display ?: 'Unknown') ?></div>
      <div><strong style="color:#cbd5e1">To:</strong> <?= htmlspecialchars($email) ?></div>
    </div>
  </div>

  <?php if ($html_body && $view_mode === 'html'): ?>
    <div style="background:#fff;padding:0;overflow:hidden;border-radius:10px;border:1px solid #374151">
      <iframe id="email-frame" style="width:100%;min-height:300px;border:none;background:#fff;display:block" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
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
            frame.style.height = '400px';
          }
        });
      }
    })();
    </script>
  <?php else: ?>
    <!-- Clean Dark-Themed Text View with Native Clickable Links -->
    <div class="msg-view" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:0.95rem;line-height:1.8;background:#0a0e1a;border-radius:10px;padding:24px;border:1px solid #1f2937;color:#e2e8f0;white-space:normal">
      <?= render_clean_email_text($clean_text_body, $otp) ?>
    </div>
  <?php endif; ?>
</div>

<?php elseif (!empty($emails)): ?>
<!-- 📋 Message List View -->
<div style="margin-bottom:16px" id="email-list-container">
  <?php $i = $total_emails - (($page - 1) * $per_page); foreach ($display_emails as $m): ?>
    <a href="?email=<?= urlencode($email) ?>&msg=<?= (int)$m['uid'] ?>" class="mail-item email-item-row" data-from="<?= strtolower(htmlspecialchars($m['from'] ?? '')) ?>" data-subject="<?= strtolower(htmlspecialchars($m['subject'] ?? '')) ?>">
      <span style="color:#64748b;font-size:0.75rem;width:24px;text-align:right"><?= $i-- ?></span>
      <div style="width:200px;font-weight:600;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        <?= htmlspecialchars(substr($m['from'] ?? 'Unknown Sender', 0, 32)) ?>
      </div>
      <div style="flex:1;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        <?= htmlspecialchars(substr($m['subject'] ?? '(No Subject)', 0, 80)) ?>
      </div>
      <div style="font-size:0.78rem;color:#64748b;white-space:nowrap">
        <?= htmlspecialchars(substr($m['date'] ?? '', 0, 16)) ?>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($total_pages > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:16px">
  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <a href="?email=<?= urlencode($email) ?>&p=<?= $p ?>" class="btn-sm <?= $p === $page ? 'btn-success' : 'btn-ghost' ?>" style="min-width:34px;text-align:center"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- 📭 Empty State with Realtime Listening Animation -->
<div class="card" style="text-align:center;padding:50px 20px;background:#0a0e1a;border:1px dashed #334155">
  <div style="font-size:2.6rem;margin-bottom:10px">📭</div>
  <h3 style="color:#f8fafc;margin-bottom:6px;font-size:1.15rem">Waiting for Incoming Emails...</h3>
  <p style="color:#94a3b8;font-size:0.88rem;max-width:460px;margin:0 auto 18px">
    Send any verification code or email to <strong style="color:#60a5fa"><?= htmlspecialchars($email) ?></strong>.<br>
    This viewer automatically checks for new mail every <strong>5 seconds</strong>.
  </p>
  <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
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
function copyOtpHero(val) {
  if (!val) return;
  navigator.clipboard.writeText(val).then(() => {
    alert('Code ' + val + ' copied to clipboard!');
  });
}

function refreshInbox() {
  const btn = document.getElementById('sync-btn');
  if (btn) { btn.textContent = '⏳ Syncing...'; btn.disabled = true; }
  setTimeout(() => location.reload(), 200);
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
let timeLeft = 5;
const timerEl = document.getElementById('sync-timer');
let countdownInterval = setInterval(() => {
  timeLeft--;
  if (timerEl) timerEl.textContent = timeLeft + 's';
  if (timeLeft <= 0) {
    if (!document.hidden) {
      location.reload();
    } else {
      timeLeft = 5;
    }
  }
}, 1000);
<?php endif; ?>
</script>

<?php page_footer(); ?>
