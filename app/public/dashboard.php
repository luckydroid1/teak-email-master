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
    <h1>Welcome, <?= htmlspecialchars(explode('@', $user['email'])[0]) ?> 👋</h1>
    <p><?= $is_advanced ? 'Developer Control Center & System Metrics' : 'Your private inboxes and instant OTP codes' ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <?php if ($is_advanced): ?>
      <a href="/unified.php" class="btn btn-sm" style="background:#8b5cf6">Unified Stream</a>
      <a href="/domains.php" class="btn btn-sm btn-ghost">Domains</a>
      <a href="/api_keys.php" class="btn btn-sm btn-ghost">API Keys</a>
    <?php else: ?>
      <a href="/send.php" class="btn btn-sm" style="background:#2563eb">✉️ Send Email</a>
      <a href="/pricing.php" class="btn btn-sm btn-ghost" style="border-color:#38bdf8;color:#38bdf8">💎 Upgrade</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($quick_error): ?>
  <div class="alert alert-e" style="margin-bottom:16px"><?= htmlspecialchars($quick_error) ?></div>
<?php endif; ?>
<?php if ($quick_success): ?>
  <div class="alert alert-s" style="margin-bottom:16px"><?= $quick_success ?></div>
<?php endif; ?>

<!-- Free Trial Status Banner -->
<?php if ($tier === 0): ?>
  <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-weight:700;color:#34d399;font-size:0.92rem">🌱 Free Sandbox Account Active</div>
      <div style="font-size:0.8rem;color:#cbd5e1">You have <strong><?= number_format($balance) ?> trial operations</strong> remaining. Upgrade to $1/mo for 3,000 credits & custom domains.</div>
    </div>
    <a href="/pricing.php" class="btn btn-sm btn-success" style="padding:6px 14px">Upgrade Plan ($1/mo) →</a>
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
        <div class="hero-email-actions">
          <button type="button" class="btn btn-sm btn-success" onclick="copyToClipboard('<?= htmlspecialchars($first_inbox['email_address']) ?>', this)">
            📋 Copy Email
          </button>
          <a href="/inbox_view.php?email=<?= urlencode($first_inbox['email_address']) ?>" class="btn btn-sm" style="background:#2563eb">
            Open Messages ✉️
          </a>
        </div>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:20px;background:#060b18;border-radius:10px;margin:12px 0">
        <p style="color:#94a3b8;margin-bottom:10px">You don't have an active inbox yet.</p>
        <form method="post" style="display:inline">
          <?php csrf_field(); ?>
          <input type="hidden" name="quick_create_inbox" value="1">
          <button type="submit" class="btn btn-success">⚡ Generate My Inbox Now</button>
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
    <?php endif; ?>
  </div>

  <!-- Recent Inbound Messages & Instant OTP Codes -->
  <div class="otp-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <div>
        <h2 style="margin:0;font-size:1.05rem;color:#f8fafc">⚡ Recent Verification Codes & Messages</h2>
        <p style="margin:2px 0 0;font-size:0.8rem;color:#94a3b8">Codes detected automatically sub-50ms.</p>
      </div>
      <?php if (!empty($inboxes)): ?>
        <a href="/inbox_view.php?email=<?= urlencode($first_inbox['email_address']) ?>" style="font-size:0.8rem;color:#38bdf8;text-decoration:none">View All Messages →</a>
      <?php endif; ?>
    </div>

    <?php if (empty($recent_messages)): ?>
      <div style="text-align:center;padding:28px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #1f2937">
        <div style="font-size:1.4rem;margin-bottom:6px">📬</div>
        <p style="color:#94a3b8;font-size:0.88rem;margin:0">No messages received yet.</p>
        <p style="color:#64748b;font-size:0.78rem;margin:4px 0 0">Copy your email address above and use it to register on apps or request a verification code.</p>
      </div>
    <?php else: ?>
      <?php foreach ($recent_messages as $m): ?>
        <div class="otp-item">
          <div style="flex:1;min-width:180px">
            <div style="font-size:0.88rem;font-weight:700;color:#f8fafc"><?= htmlspecialchars($m['subject']) ?></div>
            <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px">
              From: <strong style="color:#cbd5e1"><?= htmlspecialchars($m['from']) ?></strong> · To: <?= htmlspecialchars($m['inbox_email']) ?>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <?php if (!empty($m['otp_code'])): ?>
              <div class="otp-badge"><?= htmlspecialchars($m['otp_code']) ?></div>
              <button type="button" class="btn btn-sm btn-success" onclick="copyToClipboard('<?= htmlspecialchars($m['otp_code']) ?>', this)">
                📋 Copy
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

  <!-- All Inboxes Accordion (if user has multiple) -->
  <?php if (count($inboxes) > 1): ?>
    <div class="card" style="padding:16px 20px">
      <h3 style="margin:0 0 10px;font-size:0.95rem;color:#f8fafc">All Your Active Inboxes (<?= count($inboxes) ?>)</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:8px">
        <?php foreach ($inboxes as $in): ?>
          <div style="background:#0a0e1a;border:1px solid #1f2937;border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center">
            <span style="font-family:monospace;font-size:0.88rem;color:#e2e8f0"><?= htmlspecialchars($in['email_address']) ?></span>
            <div style="display:flex;gap:4px">
              <button type="button" class="btn btn-sm btn-ghost" style="padding:4px 8px;font-size:0.75rem" onclick="copyToClipboard('<?= htmlspecialchars($in['email_address']) ?>', this)">Copy</button>
              <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>" class="btn btn-sm btn-ghost" style="padding:4px 8px;font-size:0.75rem">Open</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
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
      <div class="stat-val" style="color:#a78bfa"><?= $used_slots ?> <span style="font-size:0.95rem;color:#64748b;font-weight:500">/ <?= $is_admin ? '∞' : $max_slots ?></span></div>
      <div class="stat-lbl">Inbox Slots Used</div>
      <div style="font-size:0.68rem;color:#64748b;margin-top:2px"><?= $is_admin ? 'Unlimited Allocation' : ($max_slots - $used_slots) . ' slots remaining' ?></div>
    </div>

    <div class="stat-card" style="cursor:pointer" onclick="window.location.href='<?= $current_plan['url'] ?>'">
      <div class="stat-icon" style="color:<?= $is_admin ? '#f59e0b' : ($tier === 0 ? '#34d399' : '#fbbf24') ?>">
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
      <div class="stat-val" style="color:#34d399"><?= (int)($tier_info['retention_days'] ?? 30) ?>d</div>
      <div class="stat-lbl">Email Retention</div>
      <div style="font-size:0.68rem;color:#64748b;margin-top:2px">Auto-purged after expiry</div>
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
          <select name="domain" style="margin:0;width:100%;height:44px;box-sizing:border-box" required>
            <?php if (!empty($eligible['pool'])): ?>
              <optgroup label="Shared Domains">
                <?php foreach ($eligible['pool'] as $d): ?>
                  <option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <?php if (!empty($eligible['custom'])): ?>
              <optgroup label="Custom Domains">
                <?php foreach ($eligible['custom'] as $d): ?>
                  <option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <div style="flex:0 0 auto">
          <button type="submit" class="btn btn-primary" style="white-space:nowrap;height:44px;padding:0 24px;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Create Mailbox</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Advanced Developer Cards -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
    <div class="card" style="border-left:4px solid #8b5cf6">
      <h3 style="margin:0 0 6px">AI Agent & MCP Quickstart</h3>
      <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:10px">Connect Claude Desktop, Cursor, or autonomous agent bots via MCP tool server.</p>
      <a href="/api_keys.php" class="btn btn-sm btn-ghost" style="border-color:#8b5cf6;color:#c4b5fd">View API Keys & MCP Config →</a>
    </div>
    <div class="card" style="border-left:4px solid #38bdf8">
      <h3 style="margin:0 0 6px">Custom Domain Mapping</h3>
      <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:10px">Connect your corporate or brand domain with automated DKIM, SPF, and MX verification.</p>
      <a href="/domains.php" class="btn btn-sm btn-ghost" style="border-color:#38bdf8;color:#38bdf8">Manage Domains →</a>
    </div>
  </div>
<?php endif; ?>

<?php page_footer(); ?>
