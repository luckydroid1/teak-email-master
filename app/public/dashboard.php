<?php
/**
 * dashboard.php — account overview & quick actions.
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
$max_slots = $tier_info ? $tier_info['inbox_slots'] : 1;
$used_slots = count($inboxes);

$redeemed = db()->prepare('SELECT tier, redeemed_at FROM ia_codes WHERE redeemed_by = ?');
$redeemed->execute([$uid]);
$redemptions = $redeemed->fetchAll();
$has_redeemed = count($redemptions) > 0;

require_once __DIR__ . '/_layout.php';
page_header('Dashboard', $user);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;color:#f8fafc;margin:0">Welcome back, <?= htmlspecialchars(explode('@', $user['email'])[0]) ?> 👋</h1>
    <p style="color:#94a3b8;font-size:.88rem;margin-top:4px">Manage your clean inboxes, API keys, and email activities.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="/send.php" class="btn" style="background:#10b981;font-size:.85rem">✉️ Send Email</a>
    <a href="/inboxes.php" class="btn" style="background:#8b5cf6;font-size:.85rem">➕ New Inbox</a>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="n" style="color:#60a5fa"><?= number_format($balance) ?></div>
    <div class="l">⚡ Credits Available</div>
  </div>
  <div class="stat">
    <div class="n" style="color:#a78bfa"><?= $used_slots ?> <span style="font-size:0.9rem;color:#6b7280;font-weight:500">/ <?= $max_slots ?></span></div>
    <div class="l">📥 Inbox Slots</div>
  </div>
  <div class="stat">
    <div class="n" style="color:#fbbf24">Tier <?= $tier ?></div>
    <div class="l">👑 Lifetime Plan</div>
  </div>
  <div class="stat">
    <div class="n" style="color:#34d399"><?= (int)($tier_info['retention_days'] ?? 30) ?>d</div>
    <div class="l">🕒 Retention Window</div>
  </div>
</div>

<?php if (!$has_redeemed): ?>
<div class="card card-highlight" style="background:linear-gradient(135deg,rgba(59,130,246,0.1) 0%,rgba(139,92,246,0.1) 100%)">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h2 style="margin:0 0 4px;font-size:1.1rem;color:#93c5fd">🎁 Activate Your Lifetime Deal Code</h2>
      <p style="margin:0;font-size:.85rem;color:#cbd5e1">Redeem your AppSumo voucher code to unlock your full tier slot allocation and non-expiring credits.</p>
    </div>
    <a href="/redeem.php" class="btn" style="white-space:nowrap">Redeem Code →</a>
  </div>
</div>
<?php endif; ?>

<!-- Quick Setup Checklist -->
<div class="card" style="border-left:4px solid #3b82f6">
  <h3 style="margin-bottom:12px;font-size:.95rem">🚀 Quick Start Checklist</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
    <div style="background:#0a0e1a;padding:12px;border-radius:8px;border:1px solid #1f2937">
      <div style="font-size:.82rem;font-weight:700;color:<?= $has_redeemed ? '#86efac' : '#fbbf24' ?>">
        <?= $has_redeemed ? '✓ 1. License Active' : '1. Redeem License' ?>
      </div>
      <p style="font-size:.75rem;color:#94a3b8;margin:4px 0 0"><?= $has_redeemed ? 'Tier ' . $tier . ' unlocked' : '<a href="/redeem.php" style="color:#60a5fa">Enter code →</a>' ?></p>
    </div>

    <div style="background:#0a0e1a;padding:12px;border-radius:8px;border:1px solid #1f2937">
      <div style="font-size:.82rem;font-weight:700;color:<?= !empty($inboxes) ? '#86efac' : '#fbbf24' ?>">
        <?= !empty($inboxes) ? '✓ 2. Inbox Created (' . count($inboxes) . ')' : '2. Create First Inbox' ?>
      </div>
      <p style="font-size:.75rem;color:#94a3b8;margin:4px 0 0"><?= !empty($inboxes) ? 'Ready to receive OTP' : '<a href="/inboxes.php" style="color:#60a5fa">Create inbox →</a>' ?></p>
    </div>

    <div style="background:#0a0e1a;padding:12px;border-radius:8px;border:1px solid #1f2937">
      <div style="font-size:.82rem;font-weight:700;color:#93c5fd">
        3. Connect AI / API
      </div>
      <p style="font-size:.75rem;color:#94a3b8;margin:4px 0 0"><a href="/api_keys.php" style="color:#60a5fa">Get API Key & MCP →</a></p>
    </div>
  </div>
</div>

<?php if (empty($inboxes)): ?>
<div class="card" style="text-align:center;padding:36px 20px">
  <div style="font-size:2.4rem;margin-bottom:8px">📥</div>
  <h2>No Inboxes Created Yet</h2>
  <p class="sub" style="max-width:460px;margin:0 auto 16px">Create a dedicated inbox in 2 clicks. Use it to grab OTP codes or connect it to your AI agent.</p>
  <a href="/inboxes.php" class="btn">Create Your First Inbox →</a>
</div>
<?php else: ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
    <h2 style="margin:0">📥 Active Inboxes (<?= count($inboxes) ?>)</h2>
    <a href="/inboxes.php" class="btn-sm" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0">+ Create Another</a>
  </div>
  
  <?php foreach ($inboxes as $in): ?>
  <div class="mb">
    <div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="e"><?= htmlspecialchars($in['email_address']) ?></span>
        <button class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?>', this)">📋 Copy</button>
      </div>
      <div class="m">Retention: <?= (int)$in['retention_days'] ?> days · Expires: <?= htmlspecialchars($in['expires_at'] ?? 'Never') ?></div>
    </div>
    <div style="display:flex;gap:6px;align-items:center">
      <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>" class="btn-sm btn-success">View Messages</a>
      <a href="/send.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm" style="background:#2563eb">Compose</a>
      <form action="/inboxes.php" method="post" style="display:inline;margin:0" onsubmit="return confirm('Delete this inbox?')">
        <?php csrf_field(); ?>
        <input type="hidden" name="delete_email" value="<?= htmlspecialchars($in['email_address']) ?>">
        <button type="submit" class="btn-danger">Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card" style="border-left:4px solid #f59e0b">
    <div style="font-size:1.4rem;margin-bottom:4px">⚡</div>
    <h2>Credit Top-Up & Upgrades</h2>
    <p class="sub">Need more credits or extra inbox slots? Stack additional AppSumo codes or top up instantly.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="/redeem.php" class="btn btn-sm" style="background:#f59e0b;color:#000;font-weight:700">🎁 Redeem / Stack Code</a>
      <a href="mailto:sales@teak.email?subject=Credit%20Top-up%20Request" class="btn btn-sm btn-ghost">💳 Request Custom Tier</a>
    </div>
  </div>
  <div class="card" style="border-left:4px solid #3b82f6">
    <div style="font-size:1.4rem;margin-bottom:4px">🤖</div>
    <h2>AI Agents & MCP Server</h2>
    <p class="sub">Integrate with Claude Desktop, Cursor, Windsurf, or custom LLM agents via Model Context Protocol.</p>
    <a href="/mcp_setup.php" class="btn btn-sm" style="background:#1e293b;border:1px solid #334155;color:#f8fafc">View MCP Guide →</a>
  </div>
</div>

<?php page_footer(); ?>
