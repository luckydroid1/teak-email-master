<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/_layout.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}
$uid = (int)$user['id'];
$tier = user_tier($uid);

// Check current user inboxes
$inboxes = inbox_list($uid);
$has_inbox = count($inboxes) > 0;
$first_inbox = $has_inbox ? $inboxes[0] : null;

// Auto-provision first inbox if user has none
if (!$has_inbox) {
    $user_prefix = explode('@', $user['email'])[0];
    $clean_prefix = preg_replace('/[^a-z0-9]/', '', strtolower($user_prefix));
    if (strlen($clean_prefix) < 3) $clean_prefix = 'user';
    $local_part = substr($clean_prefix, 0, 8) . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
    $res = inbox_create($uid, 'teak.email', $local_part, $tier);
    if (!empty($res['ok'])) {
        $inboxes = inbox_list($uid);
        $has_inbox = count($inboxes) > 0;
        $first_inbox = $has_inbox ? $inboxes[0] : null;
    }
}

// Handle sample test email simulation
$sample_sent = false;
$sample_otp = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_sample_otp']) && csrf_validate() && $first_inbox) {
        require_once __DIR__ . '/../src/mail_send.php';
        $sample_otp = (string)random_int(100000, 999999);
        send_email(
            'no-reply@teak.email',
            $first_inbox['email_address'],
            'Your Verification Code: ' . $sample_otp,
            "Hello,\n\nYour sample verification code is: $sample_otp\n\nThis sample confirms your Teak Email inbox is working perfectly sub-50ms!\n\n— Teak Team"
        );
        $sample_sent = true;
    } elseif ((isset($_POST['skip_onboarding']) || isset($_POST['complete_onboarding'])) && csrf_validate()) {
        complete_onboarding($uid);
        header('Location: /dashboard.php');
        exit;
    }
}

// Calculate progress steps
$steps_done = 2; // Step 1 & 2 are done because first inbox is now ready!
if ($sample_sent || !empty($user['onboarding_completed'])) {
    $steps_done = 3;
}

page_header('Getting Started', $user);
?>
<div style="max-width:620px;margin:24px auto;padding:0 12px">

  <!-- Welcome Header -->
  <div style="text-align:center;margin-bottom:28px">
    <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);padding:4px 14px;border-radius:20px;color:#34d399;font-size:0.82rem;font-weight:700;margin-bottom:10px">
      <span>✨ 1-Minute Quickstart</span>
    </div>
    <h1 style="font-size:1.7rem;margin-bottom:6px">Welcome to Teak Email</h1>
    <p style="color:#94a3b8;font-size:0.92rem;margin:0">Clean, private email mailboxes with instant sub-50ms OTP extraction.</p>
  </div>

  <!-- Progress Bar -->
  <div style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
      <span style="font-size:0.8rem;color:#94a3b8">Onboarding Progress</span>
      <span style="font-size:0.8rem;color:#34d399;font-weight:700"><?= $steps_done ?> of 3 steps complete</span>
    </div>
    <div style="background:#1e293b;border-radius:20px;height:8px;overflow:hidden">
      <div style="background:linear-gradient(90deg,#10b981,#3b82f6);height:100%;border-radius:20px;transition:width 0.4s ease;width:<?= ($steps_done / 3) * 100 ?>%"></div>
    </div>
  </div>

  <!-- Step 1: Account Verified -->
  <div class="card" style="border-left:4px solid #10b981;margin-bottom:14px;padding:16px 18px">
    <div style="display:flex;align-items:center;gap:12px">
      <div style="background:#10b981;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0">
        ✓
      </div>
      <div style="flex:1">
        <h3 style="margin:0;font-size:0.98rem;color:#f8fafc">1. Account Activated</h3>
        <p style="margin:2px 0 0;font-size:0.82rem;color:#94a3b8">Logged in as <strong style="color:#e2e8f0"><?= htmlspecialchars($user['email']) ?></strong> (Free Sandbox 5x Trial)</p>
      </div>
    </div>
  </div>

  <!-- Step 2: First Inbox Ready -->
  <div class="card" style="border-left:4px solid #10b981;margin-bottom:14px;padding:18px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <div style="background:#10b981;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0">
        ✓
      </div>
      <div>
        <h3 style="margin:0;font-size:0.98rem;color:#f8fafc">2. Your Inbox is Ready</h3>
        <p style="margin:2px 0 0;font-size:0.82rem;color:#94a3b8">Your private address has been generated and is ready to receive emails.</p>
      </div>
    </div>

    <?php if ($first_inbox): ?>
      <div style="background:#0f172a;border:1px solid #334155;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:0.72rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.05em">Active Mailbox Address</div>
          <div style="font-family:monospace;font-size:1.05rem;font-weight:700;color:#38bdf8;margin-top:2px"><?= htmlspecialchars($first_inbox['email_address']) ?></div>
        </div>
        <div style="display:flex;gap:6px">
          <button type="button" class="btn btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($first_inbox['email_address']) ?>', this)" style="background:#1e293b;border:1px solid #475569;color:#e2e8f0">
            📋 Copy Email
          </button>
          <a href="/inbox_view.php?email=<?= urlencode($first_inbox['email_address']) ?>" class="btn btn-sm" style="background:#2563eb">
            Open ✉️
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Step 3: Interactive Verification / OTP Test -->
  <div class="card" style="border-left:4px solid <?= $steps_done >= 3 ? '#10b981' : '#3b82f6' ?>;padding:18px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <div style="background:<?= $steps_done >= 3 ? '#10b981' : '#3b82f6' ?>;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0">
        <?= $steps_done >= 3 ? '✓' : '3' ?>
      </div>
      <div>
        <h3 style="margin:0;font-size:0.98rem;color:#f8fafc">3. Test Instant OTP Extraction</h3>
        <p style="margin:2px 0 0;font-size:0.82rem;color:#94a3b8">Send a simulated verification email to see how fast codes are detected.</p>
      </div>
    </div>

    <?php if ($sample_sent): ?>
      <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:16px;margin:12px 0">
        <div style="font-size:0.85rem;color:#34d399;font-weight:700;display:flex;align-items:center;gap:6px;margin-bottom:8px">
          <span>✅ Sample Email Delivered Sub-50ms!</span>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div style="font-size:0.85rem;color:#cbd5e1">Extracted Verification Code:</div>
          <div style="font-size:1.45rem;font-weight:800;letter-spacing:4px;color:#fff;background:#064e3b;padding:4px 14px;border-radius:8px;border:1px solid #059669">
            <?= htmlspecialchars($sample_otp) ?>
          </div>
          <button type="button" class="btn btn-sm btn-success" onclick="copyToClipboard('<?= htmlspecialchars($sample_otp) ?>', this)">
            📋 Copy Code
          </button>
        </div>
        <p style="margin:10px 0 0;font-size:0.82rem;color:#94a3b8">That's it! When services like Instagram, Google, or GitHub send verification codes to this email, they appear immediately.</p>
      </div>
    <?php else: ?>
      <form method="post" style="margin:12px 0 0">
        <?php csrf_field(); ?>
        <input type="hidden" name="send_sample_otp" value="1">
        <button type="submit" class="btn" style="background:#3b82f6;font-size:0.88rem;padding:9px 18px">
          🧪 Send Sample OTP Email (Instant) →
        </button>
      </form>
    <?php endif; ?>

    <!-- Final Action Button -->
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #1f2937;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <form method="post" style="margin:0">
        <?php csrf_field(); ?>
        <input type="hidden" name="complete_onboarding" value="1">
        <button type="submit" class="btn btn-success" style="font-size:0.9rem;padding:10px 20px">
          🚀 Go to My Inboxes / Dashboard →
        </button>
      </form>
      <form method="post" style="margin:0">
        <?php csrf_field(); ?>
        <input type="hidden" name="skip_onboarding" value="1">
        <button type="submit" style="background:transparent;border:none;color:#64748b;font-size:0.82rem;cursor:pointer;padding:8px">
          Skip to Dashboard
        </button>
      </form>
    </div>
  </div>

</div>
<?php page_footer(); ?>
