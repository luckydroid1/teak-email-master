<?php
/**
 * dashboard.php — Modern Account Overview & Quick Actions.
 * Supports Simple Mode (Beginner Friendly) & Advanced Mode (Power User).
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/../src/unified.php';

$user = require_login();
$uid = (int)$user['id'];
$balance = credit_balance($uid);
$inboxes = inbox_list($uid);
$tier = user_tier($uid);
$tier_info = tier_info($tier);
$max_slots = $tier_info ? $tier_info['inbox_slots'] : 1;
$used_slots = count($inboxes);
$eligible = inbox_eligible_domains($uid);

$ui_mode = current_ui_mode();
$is_advanced = ($ui_mode === 'advanced');

$tier_plan_details = [
    0 => ['name' => 'Free Sandbox', 'sub' => 'Developer Sandbox (5x Trial)'],
    1 => ['name' => 'Tier 1 · Starter', 'sub' => 'Starter Plan (3 Inboxes)'],
    2 => ['name' => 'Tier 2 · Pro Builder', 'sub' => 'Pro Builder Plan (25 Inboxes)'],
    3 => ['name' => 'Tier 3 · Business', 'sub' => 'Business Plan (70 Inboxes)'],
    4 => ['name' => 'Tier 4 · Scale', 'sub' => 'High-Volume Scale (125 Inboxes)'],
    5 => ['name' => 'Tier 5 · Enterprise', 'sub' => 'Enterprise Plan (190 Inboxes)'],
];

$is_admin = ((int)($user['is_admin'] ?? 0) === 1);
if ($is_admin) {
    $current_plan = ['name' => 'System Admin', 'sub' => 'Root / Unlimited Access', 'url' => '/admin/index.php', 'action' => 'Admin Panel →'];
} else {
    $current_plan = $tier_plan_details[$tier] ?? ['name' => 'Tier ' . $tier, 'sub' => 'Active Plan'];
    $current_plan['url'] = '/pricing.php';
    $current_plan['action'] = ($tier === 0 ? 'Upgrade Plan →' : 'Change Plan →');
}

$redeemed = db()->prepare('SELECT tier, redeemed_at FROM ia_codes WHERE redeemed_by = ?');
$redeemed->execute([$uid]);
$redemptions = $redeemed->fetchAll();
$has_redeemed = count($redemptions) > 0 || (int)$user['is_admin'] === 1;

// Handle PayPal payment cancellation sync
if (isset($_GET['payment']) && $_GET['payment'] === 'cancelled') {
    $cancel_token = trim($_GET['token'] ?? '');
    if ($cancel_token !== '') {
        db()->prepare("UPDATE ia_payments SET status = 'cancelled' WHERE order_id = ? AND user_id = ? AND status = 'created'")
            ->execute([$cancel_token, $uid]);
    } else {
        db()->prepare("UPDATE ia_payments SET status = 'cancelled' WHERE user_id = ? AND status = 'created' ORDER BY id DESC LIMIT 1")
            ->execute([$uid]);
    }
}

// Handle Quick Inbox Creation
$quick_error = '';
$quick_success = '';
$new_quick_email = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_create_inbox'])) {
    if (!csrf_validate()) {
        $quick_error = 'Session expired. Please try again.';
    } else {
        $dom = strtolower(trim($_POST['domain'] ?? ''));
        if ($dom === '' && !empty($eligible['all'])) {
            $dom = $eligible['all'][0];
        }
        $prefix = trim($_POST['local_part'] ?? '');
        if (empty($prefix)) {
            $u_prefix = explode('@', $user['email'])[0];
            $clean_u = preg_replace('/[^a-z0-9]/', '', strtolower($u_prefix));
            if (strlen($clean_u) < 3) $clean_u = 'user';
            $prefix = substr($clean_u, 0, 8) . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
        }
        $res = inbox_create($uid, $dom, $prefix, $tier);
        if (isset($res['ok']) && $res['ok']) {
            $new_quick_email = $res['email'];
            $quick_success = 'Inbox <strong>' . htmlspecialchars($res['email']) . '</strong> created successfully!';
            $inboxes = inbox_list($uid);
            $used_slots = count($inboxes);
            $balance = credit_balance($uid);
        } else {
            $quick_error = $res['error'] ?? 'Failed to create inbox.';
        }
    }
}

// AJAX polling endpoint for live background refresh without page reload
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $recent = [];
    try {
        $recent = fetch_unified_inbox($uid, 4, 10);
    } catch (Throwable $e) {}
    echo json_encode([
        'ok' => true,
        'count' => count($recent),
        'messages' => array_map(function($m) {
            return [
                'uid' => $m['uid'],
                'inbox_email' => $m['inbox_email'],
                'from' => $m['from'],
                'subject' => $m['subject'],
                'date' => $m['date'],
                'otp_code' => $m['otp_code']
            ];
        }, $recent)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Fetch recent messages for instant OTP feed
$recent_messages = [];
try {
    $recent_messages = fetch_unified_inbox($uid, 3, 5);
} catch (Throwable $e) {}

$first_inbox = !empty($inboxes) ? $inboxes[0] : null;

require_once __DIR__ . '/_layout.php';
page_header('Dashboard', $user);
?>

<style>
/* Dashboard Styling */
.dash-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.dash-title h1 {
  font-size: 1.55rem;
  font-weight: 800;
  color: #f8fafc;
  letter-spacing: -0.02em;
  margin-bottom: 2px;
}
.dash-title p {
  color: #94a3b8;
  font-size: 0.88rem;
  margin: 0;
}

/* Hero Mailbox Card (Simple Mode) */
.hero-mailbox-card {
  background: linear-gradient(135deg, #0b1329 0%, #171b38 100%);
  border: 1px solid rgba(59, 130, 246, 0.35);
  border-radius: 14px;
  padding: 22px;
  margin-bottom: 20px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
}
.hero-email-box {
  background: #060b18;
  border: 1px solid #1e293b;
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin: 12px 0;
}
.hero-email-text {
  font-family: monospace;
  font-size: 1.15rem;
  font-weight: 700;
  color: #38bdf8;
  word-break: break-all;
}

/* OTP Stream Box */
.otp-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 20px;
}
.otp-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 12px 14px;
  background: #0a0e1a;
  border: 1px solid #1f2937;
  border-radius: 10px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.otp-item:hover {
  border-color: #38bdf8;
}
.otp-badge {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: 3px;
  color: #fff;
  background: #064e3b;
  padding: 3px 12px;
  border-radius: 6px;
  border: 1px solid #059669;
}

/* Stat Cards (Advanced Mode) */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.stat-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 12px;
  padding: 16px 18px;
  position: relative;
  overflow: hidden;
  transition: transform 0.15s, border-color 0.15s;
}
.stat-card:hover {
  transform: translateY(-2px);
  border-color: #374151;
}
.stat-card .stat-icon {
  position: absolute;
  top: 14px;
  right: 14px;
  font-size: 1.2rem;
  opacity: 0.8;
}
.stat-card .stat-val {
  font-size: 1.55rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 2px;
}
.stat-card .stat-lbl {
  color: #94a3b8;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

@media(max-width: 900px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media(max-width: 640px) {
  .hero-mailbox-card { padding: 16px; }
  .hero-email-box { flex-direction: column; align-items: flex-start; }
  .hero-email-actions { width: 100%; display: flex; gap: 8px; }
  .hero-email-actions .btn { flex: 1; }
  .otp-item { flex-direction: column; align-items: flex-start; }
  .stat-grid { grid-template-columns: 1fr; gap: 10px; }
}
</style>

	<div class="dash-header">
	  <div class="dash-title">
	    <h1>Welcome, <?= htmlspecialchars(explode('@', $user['email'])[0]) ?></h1>
	    <p><?= $is_advanced ? 'Developer Control Center & System Metrics' : 'Your private inboxes and instant OTP codes' ?></p>
	  </div>
	  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
		    <?php if ($is_advanced): ?>
		      <a href="/unified.php" class="btn btn-sm" style="background:#8b5cf6">Unified Stream</a>
		      <a href="/domains.php" class="btn btn-sm btn-ghost">Domains</a>
		      <a href="/api_keys.php" class="btn btn-sm btn-ghost">MCP / API</a>
		    <?php else: ?>
	      <a href="/send.php" class="btn btn-sm" style="background:#2563eb">Send Email</a>
	      <a href="/pricing.php" class="btn btn-sm btn-ghost" style="border-color:#38bdf8;color:#38bdf8">Upgrade</a>
	    <?php endif; ?>
	  </div>
	</div>

		<?php if ($quick_error): ?>
		  <div class="alert alert-e" style="margin-bottom:16px"><?= htmlspecialchars($quick_error) ?></div>
		<?php endif; ?>
		<?php if ($quick_success): ?>
		  <div class="alert alert-s" style="margin-bottom:16px"><?= $quick_success ?></div>
		<?php endif; ?>
		<?php if (isset($_GET['payment']) && $_GET['payment'] === 'cancelled'): ?>
		  <div class="alert alert-w" style="margin-bottom:16px;background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.3);color:#fde047">Payment was cancelled. No charges were made. You can choose a plan anytime at <a href="/pricing.php" style="color:#38bdf8;text-decoration:underline">Plans &amp; Upgrades</a>.</div>
		<?php endif; ?>

	<!-- Free Trial Status Banner -->
	<?php if ($tier === 0): ?>
	  <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
	    <div>
	      <div style="font-weight:700;color:#34d399;font-size:0.92rem">Free Sandbox Account Active</div>
	      <div style="font-size:0.8rem;color:#cbd5e1">You have <strong><?= number_format($balance) ?> trial operations</strong> remaining. Upgrade to $1/mo for 3,000 credits & custom domains.</div>
	    </div>
	    <a href="/pricing.php" class="btn btn-sm btn-success" style="padding:6px 14px">Upgrade Plan ($1/mo) →</a>
	  </div>
	<?php endif; ?>

		<!-- Email Retention Lifecycle Notice -->
		<?php if ($is_advanced): ?>
		<div style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
		  <div>
		    <div style="font-weight:700;color:#93c5fd;font-size:0.88rem;display:flex;align-items:center;gap:6px">
		      <span>Email Retention Lifecycle Active</span>
		      <span style="font-size:0.72rem;background:rgba(59,130,246,0.2);color:#93c5fd;padding:1px 6px;border-radius:10px;font-weight:normal">Auto-Clean</span>
		    </div>
		    <div style="font-size:0.8rem;color:#94a3b8;margin-top:2px">
		      <?= $tier === 0 ? 'Free Sandbox emails are kept for <strong>3 days active</strong>, then moved to <strong>Archive for 3 days</strong> before permanent zero-data deletion.' : 'Emails are kept for <strong>30 days active</strong>, then moved to <strong>Archive for 14 days</strong> before permanent zero-data deletion.' ?>
		    </div>
		  </div>
		  <div style="display:flex;gap:8px;align-items:center">
		    <span style="font-size:0.75rem;color:#64748b">Need backups?</span>
		    <a href="/inbox_view.php<?= $first_inbox ? '?email=' . urlencode($first_inbox['email_address']) : '' ?>" class="btn btn-sm btn-ghost" style="border-color:#3b82f6;color:#93c5fd">Export JSON / EML →</a>
		  </div>
		</div>
		<?php else: ?>
		<div style="background:rgba(59,130,246,0.04);border:1px solid rgba(59,130,246,0.18);border-radius:10px;padding:10px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
		  <div style="font-size:0.82rem;color:#94a3b8;display:flex;align-items:center;gap:8px">
		    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#38bdf8"></span>
		    <span>Emails are auto-cleaned periodically to keep your inbox fast and clutter-free.</span>
		  </div>
		  <span style="font-size:0.75rem;color:#64748b"><?= $tier === 0 ? '3-day trial storage' : '30-day active storage' ?></span>
		</div>
		<?php endif; ?>

		<?php if (!$is_advanced): ?>
		  <!-- ═════════════════════════════════════════════════════════════════ -->
		  <!-- SIMPLE MODE (NEWBIE / CLEAN INTERFACE)                            -->
		  <!-- ═════════════════════════════════════════════════════════════════ -->

		  <!-- Hero: Primary Active Mailbox -->
		  <div class="hero-mailbox-card">
		    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
		      <div>
		        <span style="font-size:0.75rem;padding:2px 8px;border-radius:10px;background:rgba(56,189,248,0.2);color:#7dd3fc;font-weight:700">PRIMARY INBOX</span>
		        <h2 style="margin:4px 0 0;font-size:1.15rem;color:#f8fafc">Ready to Receive Verification Codes</h2>
		      </div>
		      <div style="font-size:0.8rem;color:#94a3b8">
		        Active inboxes: <strong style="color:#f8fafc"><?= count($inboxes) ?> / <?= $is_admin ? '∞' : $max_slots ?></strong>
		      </div>
		    </div>

		    <?php if ($first_inbox): ?>
		      <div class="hero-email-box">
		        <div class="hero-email-text"><?= htmlspecialchars($first_inbox['email_address']) ?></div>
			        <div class="hero-email-actions" style="display:flex;gap:8px;flex-wrap:wrap">
			          <button type="button" class="btn btn-sm btn-ghost" onclick="copyToClipboard('<?= htmlspecialchars($first_inbox['email_address']) ?>', this)">
			            Copy
			          </button>
			          <a href="/inbox_view.php?email=<?= urlencode($first_inbox['email_address']) ?>&folder=inbox" class="btn btn-sm btn-primary">
			            Open Inbox →
			          </a>
			          <a href="/inbox_view.php?email=<?= urlencode($first_inbox['email_address']) ?>&folder=spam" class="btn btn-sm btn-ghost" style="border-color:#b91c1c;color:#fca5a5">
			            Spam
			          </a>
			        </div>
		      </div>
		    <?php else: ?>
		      <div style="text-align:center;padding:20px;background:#060b18;border-radius:10px;margin:12px 0">
		        <p style="color:#94a3b8;margin-bottom:10px">You don't have an active inbox yet.</p>
		        <form method="post" style="display:inline">
		          <?php csrf_field(); ?>
		          <input type="hidden" name="quick_create_inbox" value="1">
		          <button type="submit" class="btn btn-success">Generate My Inbox</button>
		        </form>
		      </div>
		    <?php endif; ?>

	    <!-- Quick 1-Click Additional Inbox Creation (if quota allows) -->
	    <?php if ($used_slots < $max_slots || $is_admin): ?>
	      <form method="post" style="margin:10px 0 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
	        <?php csrf_field(); ?>
	        <input type="hidden" name="quick_create_inbox" value="1">
	        <div style="flex:1;max-width:320px;min-width:200px">
	          <input type="text" name="local_part" placeholder="Custom name (e.g. test-bot) or leave blank for auto" style="width:100%;margin:0;padding:7px 12px;font-size:0.85rem;box-sizing:border-box">
	        </div>
	        <button type="submit" class="btn btn-sm btn-ghost" style="border-color:#38bdf8;color:#38bdf8;padding:7px 14px;white-space:nowrap">
	          + Create Another Inbox
	        </button>
	      </form>
	    <?php else: ?>
	      <div style="margin-top:12px;padding:8px 14px;background:rgba(255,255,255,0.03);border:1px solid #1f2937;border-radius:8px;font-size:0.8rem;color:#94a3b8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
	        <span>Inbox quota filled (<strong><?= $used_slots ?> / <?= $max_slots ?></strong> active)</span>
	        <a href="/pricing.php" style="color:#38bdf8;text-decoration:none;font-weight:600">Upgrade Plan for More Slots →</a>
	      </div>
	    <?php endif; ?>
	  </div>

  <!-- Recent Inbound Messages & Instant OTP Codes -->
  <div class="otp-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <h2 style="margin:0;font-size:1.05rem;color:#f8fafc">Recent Verification Codes & Messages</h2>
        <span class="dash-live-badge" style="font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:12px;background:rgba(16,185,129,0.12);color:#34d399;border:1px solid rgba(16,185,129,0.3);display:inline-flex;align-items:center;gap:5px">
          <span style="width:6px;height:6px;border-radius:50%;background:#34d399;display:inline-block"></span> Live (<span class="dash-sync-timer">5s</span>)
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-sm btn-ghost dash-btn-refresh" onclick="triggerDashRefresh(this)" style="font-size:0.75rem;padding:4px 10px;border-color:#38bdf8;color:#38bdf8;display:inline-flex;align-items:center;gap:5px">
          <span class="dash-refresh-icon" style="display:inline-block">↻</span> Refresh
        </button>
        <?php if (!empty($inboxes)): ?>
          <a href="/inbox_view.php?email=<?= urlencode($first_inbox['email_address']) ?>" style="font-size:0.8rem;color:#38bdf8;text-decoration:none">View All Messages →</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (count($inboxes) > 1 && !empty($recent_messages)): ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:8px 14px;background:#0d1322;border:1px solid #1f2937;border-radius:8px;flex-wrap:wrap">
        <label for="dash-inbox-filter" style="font-size:0.82rem;color:#94a3b8;margin:0;font-weight:600">Filter by Inbox:</label>
        <select id="dash-inbox-filter" class="dash-inbox-filter-select" onchange="filterDashInbox(this.value)" style="margin:0;padding:6px 12px;font-size:0.82rem;background:#060a12;border:1px solid #334155;border-radius:6px;color:#e2e8f0;flex:1;min-width:200px;max-width:340px">
          <option value="all">All Inboxes (<?= count($inboxes) ?>)</option>
          <?php foreach ($inboxes as $ib): ?>
            <option value="<?= htmlspecialchars(strtolower($ib['email_address'])) ?>">
              <?= htmlspecialchars($ib['email_address']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="dash-filter-count" style="font-size:0.75rem;color:#64748b;margin-left:auto"><?= count($recent_messages) ?> messages</span>
      </div>
    <?php endif; ?>

    <div class="dash-messages-container">
      <?php if (empty($recent_messages)): ?>
        <div style="text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
          <p style="color:#94a3b8;font-size:0.88rem;margin:0">No messages received yet.</p>
          <p style="color:#64748b;font-size:0.78rem;margin:4px 0 0">Copy your email address above and use it to register on apps or request a verification code.</p>
        </div>
      <?php else: ?>
        <div class="dash-filter-empty" style="display:none;text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
          <p style="color:#94a3b8;font-size:0.88rem;margin:0">No recent messages found for this inbox.</p>
        </div>
        <?php foreach ($recent_messages as $m): ?>
          <div class="otp-item" data-inbox="<?= htmlspecialchars(strtolower($m['inbox_email'])) ?>">
            <div style="flex:1;min-width:180px">
              <div style="font-size:0.88rem;font-weight:700;color:#f8fafc"><?= htmlspecialchars($m['subject']) ?></div>
              <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px">
                From: <strong style="color:#cbd5e1"><?= htmlspecialchars($m['from']) ?></strong> · To: <span style="color:#60a5fa"><?= htmlspecialchars($m['inbox_email']) ?></span>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if (!empty($m['otp_code'])): ?>
                <div class="otp-badge"><?= htmlspecialchars($m['otp_code']) ?></div>
                <button type="button" class="btn btn-sm btn-success" onclick="copyToClipboard('<?= htmlspecialchars($m['otp_code']) ?>', this)">
                  Copy
                </button>
              <?php endif; ?>
              <a href="/inbox_view.php?email=<?= urlencode($m['inbox_email']) ?>&msg_id=<?= (int)$m['uid'] ?>" class="btn btn-sm btn-ghost" style="border-color:#374151">
                Read →
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- All Inboxes Accordion (if user has multiple) -->
  <?php if (count($inboxes) > 1): ?>
    <div class="card" style="padding:16px 20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px">
        <div>
          <h3 style="margin:0;font-size:0.95rem;color:#f8fafc">All Your Active Inboxes (<?= count($inboxes) ?>)</h3>
          <p style="margin:2px 0 0;font-size:0.78rem;color:#94a3b8">Quick access to individual mailbox folders</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <?php if (count($inboxes) > 4): ?>
            <input type="text" id="dash-quick-inbox-search" placeholder="Search inboxes..." oninput="filterDashActiveInboxes(this.value)" style="margin:0;padding:5px 10px;font-size:0.78rem;background:#060a12;border:1px solid #334155;border-radius:6px;width:150px;color:#f8fafc">
          <?php endif; ?>
          <a href="/inboxes.php" class="btn btn-sm btn-ghost" style="font-size:0.75rem;padding:5px 10px">Manage All →</a>
        </div>
      </div>

      <div id="dash-inboxes-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(290px, 1fr));gap:10px;max-height:<?= count($inboxes) > 6 ? '380px' : 'none' ?>;overflow-y:<?= count($inboxes) > 6 ? 'auto' : 'visible' ?>;padding-right:2px">
        <?php foreach ($inboxes as $in): ?>
          <div class="dash-inbox-card" data-email="<?= strtolower(htmlspecialchars($in['email_address'])) ?>" style="background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;padding:12px 14px;display:flex;flex-direction:column;justify-content:space-between;gap:10px;transition:border-color 0.15s">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
              <span style="font-family:monospace;font-size:0.86rem;font-weight:600;color:#f1f5f9;word-break:break-all"><?= htmlspecialchars($in['email_address']) ?></span>
              <button type="button" class="btn btn-sm btn-ghost" style="padding:2px 8px;font-size:0.72rem;flex-shrink:0" onclick="copyToClipboard('<?= htmlspecialchars($in['email_address']) ?>', this)">Copy</button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:4px;width:100%;border-top:1px solid #182234;padding-top:8px">
              <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>&folder=inbox" class="btn btn-sm btn-ghost" style="padding:4px 0;font-size:0.73rem;text-align:center;justify-content:center">Inbox</a>
              <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>&folder=archive" class="btn btn-sm btn-ghost" style="padding:4px 0;font-size:0.73rem;text-align:center;justify-content:center;color:#94a3b8">Archive</a>
              <a href="/sent.php?from=<?= urlencode($in['email_address']) ?>" class="btn btn-sm btn-ghost" style="padding:4px 0;font-size:0.73rem;text-align:center;justify-content:center">Sent</a>
              <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>&folder=spam" class="btn btn-sm btn-ghost" style="padding:4px 0;font-size:0.73rem;text-align:center;justify-content:center;color:#fca5a5;border-color:rgba(239,68,68,0.3)">Spam</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <script>
    function filterDashActiveInboxes(q) {
      q = (q || '').toLowerCase().trim();
      document.querySelectorAll('.dash-inbox-card').forEach(el => {
        const em = el.getAttribute('data-email') || '';
        el.style.display = (q === '' || em.includes(q)) ? 'flex' : 'none';
      });
    }
    </script>
  <?php endif; ?>

	<?php else: ?>
	  <!-- ═════════════════════════════════════════════════════════════════ -->
	  <!-- ADVANCED MODE (DEVELOPER & POWER USER TOOLS)                      -->
	  <!-- ═════════════════════════════════════════════════════════════════ -->

	  <!-- 4 Key Stat Cards -->
	  <div class="stat-grid">
	    <div class="stat-card" style="cursor:pointer" onclick="window.location.href='<?= $is_admin ? '/admin/index.php' : '/pricing.php' ?>'">
	      <div class="stat-icon" style="color:#60a5fa">
	        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
	      </div>
	      <div class="stat-val" style="color:#60a5fa"><?= $is_admin ? '∞' : number_format($balance) ?></div>
	      <div class="stat-lbl">Credits Available</div>
	      <a href="<?= $is_admin ? '/admin/index.php' : '/pricing.php' ?>" style="font-size:0.72rem;color:<?= $is_admin ? '#f59e0b' : '#38bdf8' ?>;margin-top:4px;display:inline-block;text-decoration:none;font-weight:600">
	        <?= $is_admin ? 'Admin Bypass Active →' : '+ Top Up Credits →' ?>
	      </a>
	    </div>

	    <div class="stat-card">
	      <div class="stat-icon" style="color:#a78bfa">
	        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path></svg>
	      </div>
	      <div class="stat-val" style="color:#a78bfa"><?= count($inboxes) ?> <span style="font-size:1rem;color:#64748b">/ <?= $is_admin ? '∞' : $max_slots ?></span></div>
	      <div class="stat-lbl">Inbox Slots Used</div>
	      <a href="/inboxes.php" style="font-size:0.72rem;color:#a78bfa;margin-top:4px;display:inline-block;text-decoration:none;font-weight:600">+ Manage Inboxes →</a>
	    </div>

	    <div class="stat-card" style="cursor:pointer" onclick="window.location.href='<?= $current_plan['url'] ?>'">
	      <div class="stat-icon" style="color:<?= $is_admin ? '#f59e0b' : '#fbbf24' ?>">
	        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
	      </div>
	      <div class="stat-val" style="font-size:1.35rem;color:<?= $is_admin ? '#f59e0b' : ($tier === 0 ? '#34d399' : '#fbbf24') ?>"><?= htmlspecialchars($current_plan['name']) ?></div>
	      <div class="stat-lbl"><?= htmlspecialchars($current_plan['sub']) ?></div>
	      <a href="<?= $current_plan['url'] ?>" style="font-size:0.68rem;color:<?= $is_admin ? '#f59e0b' : '#38bdf8' ?>;margin-top:2px;display:inline-block;text-decoration:none"><?= htmlspecialchars($current_plan['action']) ?></a>
	    </div>

	    <div class="stat-card">
	      <div class="stat-icon" style="color:#34d399">
	        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
	      </div>
	      <div class="stat-val" style="color:#34d399"><?= $tier === 0 ? '3d + 3d' : '30d + 14d' ?></div>
	      <div class="stat-lbl">Email Retention</div>
	      <div style="font-size:0.68rem;color:#64748b;margin-top:2px"><?= $tier === 0 ? '3d active · 3d archive' : '30d active · 14d archive' ?></div>
	    </div>
	  </div>

  <!-- Full Quick Creator (with Domain Selector) -->
  <div class="card" style="background:#0f172a;border:1px solid #334155;border-radius:12px;margin-bottom:20px;padding:20px">
    <div style="margin-bottom:12px">
      <h3 style="margin:0;font-size:1.05rem;color:#f8fafc;font-weight:700">Create Inbox with Specific Domain</h3>
      <p style="margin:3px 0 0;font-size:0.82rem;color:#94a3b8">Choose a custom username and domain for your dedicated inbox.</p>
    </div>
    <form method="post" style="margin:0">
      <?php csrf_field(); ?>
      <input type="hidden" name="quick_create_inbox" value="1">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <div style="flex:1 1 240px;min-width:180px">
          <input type="text" name="local_part" placeholder="username / agent-id" style="margin:0;width:100%;height:44px;box-sizing:border-box" required pattern="[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]" title="Lowercase letters, numbers, dots, hyphens" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '')">
        </div>
        <div style="flex:1 1 240px;min-width:180px">
          <?php
          $dash_def_dom = !empty($eligible['pool']) ? $eligible['pool'][0] : (!empty($eligible['custom']) ? $eligible['custom'][0] : 'toohumid.com');
          ?>
          <div class="custom-domain-picker-dash" style="position:relative;width:100%">
            <input type="hidden" name="domain" id="selected-domain-dash-val" value="<?= htmlspecialchars($dash_def_dom) ?>" required>
            <button type="button" id="domain-picker-dash-btn" onclick="toggleDashDomainPicker()" style="width:100%;height:44px;background:#060a12;border:1px solid #334155;border-radius:8px;padding:0 14px;color:#f8fafc;font-size:0.9rem;display:flex;justify-content:space-between;align-items:center;cursor:pointer;text-align:left;box-sizing:border-box">
              <span id="domain-picker-dash-label" style="font-family:monospace;font-size:0.88rem;color:#60a5fa">@<?= htmlspecialchars($dash_def_dom) ?></span>
              <span style="font-size:0.75rem;color:#94a3b8">▼</span>
            </button>
            <div id="domain-picker-dash-menu" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:#0d1322;border:1px solid #3b82f6;border-radius:8px;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.6);padding:6px">
              <div style="padding:4px;position:sticky;top:0;background:#0d1322;z-index:2;border-bottom:1px solid #1f2937;margin-bottom:4px">
                <input type="text" id="domain-dash-search" placeholder="Search domain..." oninput="filterDashDomainList(this.value)" style="margin:0;padding:6px 10px;font-size:0.82rem;width:100%;box-sizing:border-box;background:#060a12;border-color:#334155;color:#f8fafc">
              </div>
              <div id="domain-dash-options-list">
                <?php if (!empty($eligible['pool'])): ?>
                  <div style="font-size:0.7rem;font-weight:700;color:#64748b;padding:6px 8px 2px;text-transform:uppercase;letter-spacing:0.05em">Shared Domains</div>
                  <?php foreach ($eligible['pool'] as $d): ?>
                    <div class="domain-dash-opt-item" data-domain="<?= htmlspecialchars(strtolower($d)) ?>" onclick="selectDashDomainOption('<?= htmlspecialchars($d) ?>')" style="padding:8px 10px;border-radius:6px;cursor:pointer;font-family:monospace;font-size:0.85rem;color:#cbd5e1;transition:background 0.1s" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                      @<?= htmlspecialchars($d) ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($eligible['custom'])): ?>
                  <div style="font-size:0.7rem;font-weight:700;color:#64748b;padding:8px 8px 2px;text-transform:uppercase;letter-spacing:0.05em">Your Verified Custom Domains (<?= count($eligible['custom']) ?>)</div>
                  <?php foreach ($eligible['custom'] as $d): ?>
                    <div class="domain-dash-opt-item" data-domain="<?= htmlspecialchars(strtolower($d)) ?>" onclick="selectDashDomainOption('<?= htmlspecialchars($d) ?>')" style="padding:8px 10px;border-radius:6px;cursor:pointer;font-family:monospace;font-size:0.85rem;color:#cbd5e1;transition:background 0.1s" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                      @<?= htmlspecialchars($d) ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <script>
          function toggleDashDomainPicker() {
            const m = document.getElementById('domain-picker-dash-menu');
            if (!m) return;
            const isHidden = (m.style.display === 'none' || m.style.display === '');
            m.style.display = isHidden ? 'block' : 'none';
            if (isHidden) {
              const inp = document.getElementById('domain-dash-search');
              if (inp) { inp.value = ''; filterDashDomainList(''); inp.focus(); }
            }
          }
          function selectDashDomainOption(dom) {
            document.getElementById('selected-domain-dash-val').value = dom;
            document.getElementById('domain-picker-dash-label').textContent = '@' + dom;
            document.getElementById('domain-picker-dash-menu').style.display = 'none';
          }
          function filterDashDomainList(q) {
            q = (q || '').toLowerCase().trim();
            document.querySelectorAll('.domain-dash-opt-item').forEach(el => {
              const d = el.getAttribute('data-domain') || '';
              el.style.display = (q === '' || d.includes(q)) ? 'block' : 'none';
            });
          }
          document.addEventListener('click', function(e) {
            const box = document.querySelector('.custom-domain-picker-dash');
            if (box && !box.contains(e.target)) {
              const m = document.getElementById('domain-picker-dash-menu');
              if (m) m.style.display = 'none';
            }
          });
          </script>
        </div>
        <div style="flex:0 0 auto">
          <button type="submit" class="btn btn-primary" style="white-space:nowrap;height:44px;padding:0 24px;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Create Mailbox</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Recent Inbound Messages & Instant OTP Codes (Advanced Mode) -->
  <div class="otp-card" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <h2 style="margin:0;font-size:1.05rem;color:#f8fafc">Recent Inbound Messages & OTP Stream</h2>
        <span class="dash-live-badge" style="font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:12px;background:rgba(16,185,129,0.12);color:#34d399;border:1px solid rgba(16,185,129,0.3);display:inline-flex;align-items:center;gap:5px">
          <span style="width:6px;height:6px;border-radius:50%;background:#34d399;display:inline-block"></span> Live (<span class="dash-sync-timer">5s</span>)
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-sm btn-ghost dash-btn-refresh" onclick="triggerDashRefresh(this)" style="font-size:0.75rem;padding:4px 10px;border-color:#38bdf8;color:#38bdf8;display:inline-flex;align-items:center;gap:5px">
          <span class="dash-refresh-icon" style="display:inline-block">↻</span> Refresh
        </button>
        <a href="/unified.php" style="font-size:0.8rem;color:#38bdf8;text-decoration:none">Open Full Unified Stream →</a>
      </div>
    </div>

    <?php if (count($inboxes) > 1 && !empty($recent_messages)): ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:8px 14px;background:#0d1322;border:1px solid #1f2937;border-radius:8px;flex-wrap:wrap">
        <label for="dash-adv-inbox-filter" style="font-size:0.82rem;color:#94a3b8;margin:0;font-weight:600">Filter by Inbox:</label>
        <select id="dash-adv-inbox-filter" class="dash-inbox-filter-select" onchange="filterDashInbox(this.value)" style="margin:0;padding:6px 12px;font-size:0.82rem;background:#060a12;border:1px solid #334155;border-radius:6px;color:#e2e8f0;flex:1;min-width:200px;max-width:340px">
          <option value="all">All Inboxes (<?= count($inboxes) ?>)</option>
          <?php foreach ($inboxes as $ib): ?>
            <option value="<?= htmlspecialchars(strtolower($ib['email_address'])) ?>">
              <?= htmlspecialchars($ib['email_address']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="dash-filter-count" style="font-size:0.75rem;color:#64748b;margin-left:auto"><?= count($recent_messages) ?> messages</span>
      </div>
    <?php endif; ?>

    <div class="dash-messages-container">
      <?php if (empty($recent_messages)): ?>
        <div style="text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
          <p style="color:#94a3b8;font-size:0.88rem;margin:0">No messages received yet.</p>
          <p style="color:#64748b;font-size:0.78rem;margin:4px 0 0">Incoming emails to any of your active inboxes will appear here automatically.</p>
        </div>
      <?php else: ?>
        <div class="dash-filter-empty" style="display:none;text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
          <p style="color:#94a3b8;font-size:0.88rem;margin:0">No recent messages found for this inbox.</p>
        </div>
        <?php foreach ($recent_messages as $m): ?>
          <div class="otp-item" data-inbox="<?= htmlspecialchars(strtolower($m['inbox_email'])) ?>">
            <div style="flex:1;min-width:180px">
              <div style="font-size:0.88rem;font-weight:700;color:#f8fafc"><?= htmlspecialchars($m['subject']) ?></div>
              <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px">
                From: <strong style="color:#cbd5e1"><?= htmlspecialchars($m['from']) ?></strong> · To: <span style="color:#60a5fa"><?= htmlspecialchars($m['inbox_email']) ?></span>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if (!empty($m['otp_code'])): ?>
                <div class="otp-badge"><?= htmlspecialchars($m['otp_code']) ?></div>
                <button type="button" class="btn btn-sm btn-success" onclick="copyToClipboard('<?= htmlspecialchars($m['otp_code']) ?>', this)">
                  Copy
                </button>
              <?php endif; ?>
              <a href="/inbox_view.php?email=<?= urlencode($m['inbox_email']) ?>&msg_id=<?= (int)$m['uid'] ?>" class="btn btn-sm btn-ghost" style="border-color:#374151">
                Read →
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Advanced Developer Cards -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
    <div class="card" style="border-left:4px solid #8b5cf6">
      <h3 style="margin:0 0 6px">AI Agent & MCP / API Integration</h3>
      <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:10px">Connect Claude Desktop, Cursor, or autonomous agent bots via MCP tool server.</p>
      <a href="/api_keys.php" class="btn btn-sm btn-ghost" style="border-color:#8b5cf6;color:#c4b5fd">View MCP / API Config & Keys →</a>
    </div>
    <div class="card" style="border-left:4px solid #38bdf8">
      <h3 style="margin:0 0 6px">Custom Domain Mapping</h3>
      <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:10px">Connect your corporate or brand domain with automated DKIM, SPF, and MX verification.</p>
      <a href="/domains.php" class="btn btn-sm btn-ghost" style="border-color:#38bdf8;color:#38bdf8">Manage Domains →</a>
    </div>
  </div>
<?php endif; ?>

<script>
let dashSyncSecs = 5;
let dashIsRefreshing = false;

function filterDashInbox(selected) {
  const items = document.querySelectorAll('.otp-item');
  let count = 0;
  items.forEach(el => {
    const inbox = (el.getAttribute('data-inbox') || '').toLowerCase();
    if (selected === 'all' || inbox === selected) {
      el.style.display = 'flex';
      count++;
    } else {
      el.style.display = 'none';
    }
  });
  const countEls = document.querySelectorAll('.dash-filter-count');
  countEls.forEach(el => el.textContent = count + ' messages');
  const emptyNotices = document.querySelectorAll('.dash-filter-empty');
  emptyNotices.forEach(el => {
    el.style.display = (count === 0) ? 'block' : 'none';
  });
}

async function triggerDashRefresh(btn = null) {
  if (dashIsRefreshing) return;
  dashIsRefreshing = true;
  if (btn) {
    btn.disabled = true;
    const ico = btn.querySelector('.dash-refresh-icon');
    if (ico) ico.style.transform = 'rotate(360deg)';
  }
  
  try {
    const res = await fetch('/dashboard.php?ajax=1');
    const data = await res.json();
    if (data.ok && Array.isArray(data.messages)) {
      renderDashMessages(data.messages);
    }
  } catch (e) {
    console.error('Dash refresh failed:', e);
  } finally {
    dashIsRefreshing = false;
    dashSyncSecs = 5;
    if (btn) {
      btn.disabled = false;
      const ico = btn.querySelector('.dash-refresh-icon');
      if (ico) setTimeout(() => { ico.style.transform = ''; }, 400);
    }
  }
}

function renderDashMessages(messages) {
  const containers = document.querySelectorAll('.dash-messages-container');
  containers.forEach(container => {
    if (messages.length === 0) {
      container.innerHTML = `
        <div style="text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
          <p style="color:#94a3b8;font-size:0.88rem;margin:0">No messages received yet.</p>
          <p style="color:#64748b;font-size:0.78rem;margin:4px 0 0">Incoming emails to any of your active inboxes will appear here automatically.</p>
        </div>`;
      return;
    }

    let html = `<div class="dash-filter-empty" style="display:none;text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">No recent messages found for this inbox.</p>
    </div>`;

    messages.forEach(m => {
      const inboxLower = (m.inbox_email || '').toLowerCase();
      const escSubject = escapeHtml(m.subject || '(No Subject)');
      const escFrom = escapeHtml(m.from || '');
      const escInbox = escapeHtml(m.inbox_email || '');
      const escOtp = m.otp_code ? escapeHtml(m.otp_code) : '';
      const readUrl = '/inbox_view.php?email=' + encodeURIComponent(m.inbox_email) + '&msg_id=' + encodeURIComponent(m.uid);

      let otpBlock = '';
      if (escOtp) {
        otpBlock = `
          <div class="otp-badge">${escOtp}</div>
          <button type="button" class="btn btn-sm btn-success" onclick="copyToClipboard('${escOtp}', this)">Copy</button>
        `;
      }

      html += `
        <div class="otp-item" data-inbox="${inboxLower}">
          <div style="flex:1;min-width:180px">
            <div style="font-size:0.88rem;font-weight:700;color:#f8fafc">${escSubject}</div>
            <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px">
              From: <strong style="color:#cbd5e1">${escFrom}</strong> · To: <span style="color:#60a5fa">${escInbox}</span>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            ${otpBlock}
            <a href="${readUrl}" class="btn btn-sm btn-ghost" style="border-color:#374151">Read →</a>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;
  });

  const activeFilter = document.querySelector('.dash-inbox-filter-select');
  if (activeFilter) {
    filterDashInbox(activeFilter.value);
  }
}

function escapeHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

setInterval(() => {
  if (document.hidden) return;
  dashSyncSecs--;
  document.querySelectorAll('.dash-sync-timer').forEach(el => {
    el.textContent = Math.max(1, dashSyncSecs) + 's';
  });
  if (dashSyncSecs <= 0) {
    dashSyncSecs = 5;
    triggerDashRefresh();
  }
}, 1000);
</script>

<?php page_footer(); ?>
