<?php
/**
 * dashboard.php — account overview.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';

$user = require_login();
$uid = (int)$user['id'];
$balance = credit_balance($uid);
$inboxes = inbox_list($uid);
$tier = user_tier($uid);
$tier_info = tier_info($tier);
$credits_left = $tier_info ? $tier_info['credits'] : 0;
$redeemed = db()->prepare('SELECT tier, redeemed_at FROM ia_codes WHERE redeemed_by = ?');
$redeemed->execute([$uid]);
$redemptions = $redeemed->fetchAll();
$has_redeemed = count($redemptions) > 0;

require_once __DIR__ . '/_layout.php';
page_header('Dashboard', $user);
?>
<div class="stats">
  <div class="stat"><div class="n"><?= number_format($balance) ?></div><div class="l">Credits</div></div>
  <div class="stat"><div class="n"><?= count($inboxes) ?></div><div class="l">Inboxes</div></div>
  <div class="stat"><div class="n">T<?= $tier ?></div><div class="l">Lifetime Tier</div></div>
  <div class="stat"><div class="n"><?= $tier_info ? $tier_info['inbox_slots'] : 0 ?></div><div class="l">Inbox Slots</div></div>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <a href="/send.php" class="btn" style="text-decoration:none">Send Email</a>
  <a href="/inboxes.php" class="btn" style="text-decoration:none;background:#8b5cf6">New Inbox</a>
</div>

<?php if (!$has_redeemed): ?>
<div class="card" style="border-color:#3b82f6">
  <h2>🎁 Activate Your Lifetime Deal</h2>
  <p class="sub">You haven't redeemed your AppSumo code yet. Once redeemed, your tier + credits unlock.</p>
  <a href="/redeem.php" class="btn">Redeem Code</a>
</div>
<?php endif; ?>

<?php if (empty($inboxes)): ?>
<div class="card">
  <h2>📥 No inboxes yet</h2>
  <p class="sub">Create your first inbox — 2 clicks and you're receiving email.</p>
  <a href="/inboxes.php" class="btn">Create Inbox</a>
</div>
<?php else: ?>
<div class="card">
  <h2>📥 Your Inboxes</h2>
  <?php foreach ($inboxes as $in): ?>
  <div class="mb">
    <div>
      <div class="e"><?= htmlspecialchars($in['email_address']) ?></div>
      <div class="m">Expires: <?= htmlspecialchars($in['expires_at'] ?? '-') ?> · Retention: <?= (int)$in['retention_days'] ?> days</div>
    </div>
    <div style="display:flex;gap:6px">
      <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>" class="btn-sm">View</a>
      <a href="/send.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm" style="background:#10b981">Send</a>
      <a href="/inboxes.php?delete=<?= urlencode($in['email_address']) ?>" class="btn-danger" onclick="return confirm('Delete this inbox?')">Delete</a>
    </div>
  </div>
  <?php endforeach; ?>
  <p style="margin-top:12px"><a href="/inboxes.php" class="btn">+ New Inbox</a></p>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2>🤖 Connect Your Agent</h2>
    <p class="sub">REST API + MCP server ready. Your agent can read OTPs automatically.</p>
    <a href="/mcp_setup.php" class="btn">MCP Setup Guide</a>
  </div>
  <div class="card">
    <h2>🔑 API Keys</h2>
    <p class="sub">Generate a key for API access.</p>
    <a href="/api_keys.php" class="btn">Manage Keys</a>
  </div>
</div>
<?php page_footer(); ?>
