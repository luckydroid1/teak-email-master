<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/_layout.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

// Check which steps are completed
$has_inbox = false;
$inbox_count = 0;
if ($user) {
    $st = db()->prepare('SELECT COUNT(*) FROM ia_inboxes WHERE user_id = ?');
    $st->execute([$user['id']]);
    $inbox_count = (int)$st->fetchColumn();
    $has_inbox = $inbox_count > 0;
}

// Handle skip onboarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_onboarding'])) {
    complete_onboarding($user['id']);
    header('Location: /dashboard.php');
    exit;
}

// Handle mark complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_onboarding'])) {
    complete_onboarding($user['id']);
    header('Location: /dashboard.php');
    exit;
}

// Calculate progress
$steps_done = 1; // Step 1 always done (logged in)
if ($has_inbox) $steps_done = 2;
if ($user['onboarding_completed']) $steps_done = 3;

page_header('Getting Started', $user);
?>
<div style="max-width:560px;margin:30px auto">

  <!-- Progress bar -->
  <div style="margin-bottom:28px">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
      <span style="font-size:.8rem;color:#94a3b8">Progress</span>
      <span style="font-size:.8rem;color:#3b82f6" id="progress-text"><?= $steps_done ?>/3 steps</span>
    </div>
    <div style="background:#1e293b;border-radius:20px;height:8px;overflow:hidden">
      <div id="progress-bar" style="background:linear-gradient(90deg,#3b82f6,#8b5cf6);height:100%;border-radius:20px;transition:width 0.5s ease;width:<?= ($steps_done / 3) * 100 ?>%"></div>
    </div>
  </div>

  <!-- Step 1: Account -->
  <div id="step1" class="card" style="border-left:4px solid <?= $steps_done >= 1 ? '#10b981' : '#3b82f6' ?>;opacity:1;transition:all 0.3s">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
      <div style="background:<?= $steps_done >= 1 ? '#10b981' : '#3b82f6' ?>;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">
        <?= $steps_done >= 1 ? '✓' : '1' ?>
      </div>
      <div>
        <h3 style="margin:0;font-size:1rem">Create Your Account</h3>
        <p style="margin:0;font-size:.8rem;color:#64748b">Sign up with your email</p>
      </div>
    </div>
    <?php if ($steps_done >= 1): ?>
      <div style="background:#14532d;border:1px solid #166534;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#86efac;margin-top:8px">
        ✅ You're logged in as <strong><?= htmlspecialchars($user['email']) ?></strong>
      </div>
    <?php else: ?>
      <a href="/signup.php" class="btn" style="text-decoration:none;margin-top:8px;font-size:.85rem">Sign Up Free →</a>
    <?php endif; ?>
  </div>

  <!-- Step 2: First Inbox -->
  <div id="step2" class="card" style="border-left:4px solid <?= $has_inbox ? '#10b981' : '#8b5cf6' ?>;opacity:<?= $steps_done >= 1 ? '1' : '0.4' ?>;transition:all 0.3s">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
      <div style="background:<?= $has_inbox ? '#10b981' : '#8b5cf6' ?>;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">
        <?= $has_inbox ? '✓' : '2' ?>
      </div>
      <div>
        <h3 style="margin:0;font-size:1rem">Create Your First Inbox</h3>
        <p style="margin:0;font-size:.8rem;color:#64748b">Pick a domain, name your inbox</p>
      </div>
    </div>
    <?php if ($has_inbox): ?>
      <div style="background:#14532d;border:1px solid #166534;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#86efac;margin-top:8px">
        ✅ You have <?= $inbox_count ?> inbox<?= $inbox_count > 1 ? 'es' : '' ?> — ready to receive emails!
      </div>
    <?php elseif ($steps_done >= 1): ?>
      <p style="font-size:.85rem;color:#94a3b8;margin:8px 0">Choose a domain and give your inbox a name. It takes 5 seconds.</p>
      <a href="/inboxes.php" class="btn" style="text-decoration:none;margin-top:4px;font-size:.85rem;background:#8b5cf6">Create Inbox →</a>
    <?php else: ?>
      <p style="font-size:.85rem;color:#64748b;margin-top:8px">Complete Step 1 first.</p>
    <?php endif; ?>
  </div>

  <!-- Step 3: Done! -->
  <div id="step3" class="card" style="border-left:4px <?= $steps_done >= 3 ? 'solid #10b981' : 'dashed #334155' ?>;opacity:<?= $steps_done >= 2 ? '1' : '0.4' ?>;transition:all 0.3s">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
      <div style="background:<?= $steps_done >= 3 ? '#10b981' : '#475569' ?>;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">
        <?= $steps_done >= 3 ? '✓' : '3' ?>
      </div>
      <div>
        <h3 style="margin:0;font-size:1rem">You're All Set!</h3>
        <p style="margin:0;font-size:.8rem;color:#64748b">Start using Teak Email</p>
      </div>
    </div>
    <?php if ($steps_done >= 2 && !$user['onboarding_completed']): ?>
      <p style="font-size:.85rem;color:#94a3b8;margin:8px 0">Your inbox is ready. Go to your dashboard to see incoming emails and extract OTP codes.</p>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
        <a href="/dashboard.php" class="btn" style="text-decoration:none;font-size:.85rem">Go to Dashboard →</a>
        <form method="post" style="margin:0">
          <input type="hidden" name="complete_onboarding" value="1">
          <button type="submit" style="background:transparent;border:1px solid #334155;color:#94a3b8;padding:10px 18px;border-radius:8px;font-size:.85rem;cursor:pointer">Skip, I know the way →</button>
        </form>
      </div>
    <?php elseif ($steps_done >= 3): ?>
      <div style="background:#14532d;border:1px solid #166534;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#86efac;margin-top:8px">
        ✅ Onboarding complete!
      </div>
      <a href="/dashboard.php" class="btn" style="text-decoration:none;margin-top:12px;font-size:.85rem">Go to Dashboard →</a>
    <?php else: ?>
      <p style="font-size:.85rem;color:#64748b;margin-top:8px">Complete Step 2 first.</p>
    <?php endif; ?>
  </div>

  <!-- Quick links -->
  <div style="margin-top:24px;padding:16px;background:#1e293b;border-radius:12px;border:1px solid #334155">
    <p style="font-size:.85rem;color:#64748b;margin-bottom:10px;font-weight:600">Quick Links</p>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <a href="/api_keys.php" style="color:#3b82f6;font-size:.85rem;text-decoration:none">🔑 API Keys</a>
      <a href="/mcp_setup.php" style="color:#3b82f6;font-size:.85rem;text-decoration:none">🤖 MCP Setup</a>
      <a href="/redeem.php" style="color:#3b82f6;font-size:.85rem;text-decoration:none">🎟️ Redeem Code</a>
      <a href="/privacy.php" style="color:#64748b;font-size:.85rem;text-decoration:none">📄 Privacy</a>
    </div>
  </div>

  <!-- Help -->
  <div style="text-align:center;margin-top:20px">
    <p style="font-size:.8rem;color:#475569">Need help? <a href="mailto:support@teak.email" style="color:#3b82f6">support@teak.email</a></p>
  </div>

</div>
<?php page_footer(); ?>
