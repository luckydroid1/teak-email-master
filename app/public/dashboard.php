<?php
/**
 * dashboard.php — Modern Account Overview & Quick Actions.
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
$eligible = inbox_eligible_domains($uid);

$redeemed = db()->prepare('SELECT tier, redeemed_at FROM ia_codes WHERE redeemed_by = ?');
$redeemed->execute([$uid]);
$redemptions = $redeemed->fetchAll();
$has_redeemed = count($redemptions) > 0 || (int)$user['is_admin'] === 1;

// Handle Quick Inbox Creation directly from Dashboard
$quick_error = '';
$quick_success = '';
$new_quick_email = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_create_inbox'])) {
    if (!csrf_validate()) {
        $quick_error = 'Session expired or invalid submission. Please try again.';
    } elseif (!$has_redeemed && (int)$user['is_admin'] !== 1) {
        $quick_error = 'Please activate your tier license before creating inboxes.';
    } else {
        $dom = trim($_POST['domain'] ?? '');
        $prefix = trim($_POST['local_part'] ?? '');
        if (empty($prefix)) {
            $prefix = 'agent-' . substr(bin2hex(random_bytes(4)), 0, 7);
        }
        $res = inbox_create($uid, $dom, $prefix, $tier);
        if (isset($res['ok']) && $res['ok']) {
            $new_quick_email = $res['email'];
            $quick_success = '🎉 Inbox <strong>' . htmlspecialchars($res['email']) . '</strong> created successfully!';
            $inboxes = inbox_list($uid);
            $used_slots = count($inboxes);
            $balance = credit_balance($uid);
        } else {
            $quick_error = $res['error'] ?? 'Failed to create inbox.';
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Dashboard', $user);
?>

<style>
/* Dashboard Specific Modern Styles */
.dash-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.dash-title h1 {
  font-size: 1.65rem;
  font-weight: 800;
  color: #f8fafc;
  letter-spacing: -0.02em;
  margin-bottom: 4px;
}
.dash-title p {
  color: #94a3b8;
  font-size: 0.9rem;
  margin: 0;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
.stat-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 12px;
  padding: 18px 20px;
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
  top: 16px;
  right: 16px;
  font-size: 1.3rem;
  opacity: 0.8;
}
.stat-card .stat-val {
  font-size: 1.7rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 4px;
}
.stat-card .stat-lbl {
  color: #94a3b8;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.quick-box {
  background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
  border: 1px solid rgba(139, 92, 246, 0.35);
  border-radius: 14px;
  padding: 20px 24px;
  margin-bottom: 24px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
}

.quick-form-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr auto;
  gap: 10px;
  align-items: center;
}

.inbox-table-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 24px;
}
.inbox-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 16px;
  background: #0a0e1a;
  border: 1px solid #1f2937;
  border-radius: 10px;
  margin-bottom: 10px;
  transition: all 0.15s;
}
.inbox-row:hover {
  border-color: #3b82f6;
  background: #0c1322;
}

@media(max-width: 900px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media(max-width: 680px) {
  .quick-box { padding: 16px; }
  .quick-form-grid { grid-template-columns: 1fr; gap: 10px; }
  .stat-grid { grid-template-columns: 1fr; gap: 12px; }
  .inbox-row { flex-direction: column; align-items: flex-start; }
  .inbox-actions { width: 100%; justify-content: flex-start; }
}
</style>

<div class="dash-header">
  <div class="dash-title">
    <h1>Welcome back, <?= htmlspecialchars(explode('@', $user['email'])[0]) ?> 👋</h1>
    <p>Manage your active inboxes, OTP extractions, and AI MCP automations.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <a href="/unified.php" class="btn" style="background:#8b5cf6;font-size:0.85rem">📬 Unified Inbox Stream</a>
    <a href="/inbox_view.php?email=<?= !empty($inboxes) ? urlencode($inboxes[0]['email_address']) : '' ?>" class="btn" style="background:#10b981;font-size:0.85rem">⚡ Live OTP Viewer</a>
    <a href="/send.php" class="btn btn-ghost" style="font-size:0.85rem">✉️ Send Email</a>
  </div>
</div>

<?php if ($quick_error): ?>
  <div class="alert alert-e" style="margin-bottom:20px"><?= htmlspecialchars($quick_error) ?></div>
<?php endif; ?>
<?php if ($quick_success): ?>
  <div class="alert alert-s" style="margin-bottom:20px"><?= $quick_success ?></div>
<?php endif; ?>

<!-- 4 Key Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon">⚡</div>
    <div class="stat-val" style="color:#60a5fa"><?= number_format($balance) ?></div>
    <div class="stat-lbl">Credits Available</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">📥</div>
    <div class="stat-val" style="color:#a78bfa"><?= $used_slots ?> <span style="font-size:0.95rem;color:#64748b;font-weight:500">/ <?= $max_slots ?></span></div>
    <div class="stat-lbl">Inbox Slots Used</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">👑</div>
    <div class="stat-val" style="color:#fbbf24">Tier <?= $tier ?></div>
    <div class="stat-lbl">Account Tier</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">🕒</div>
    <div class="stat-val" style="color:#34d399"><?= (int)($tier_info['retention_days'] ?? 30) ?>d</div>
    <div class="stat-lbl">Email Retention</div>
  </div>
</div>

<!-- ⚡ 1-Click Quick Inbox Creator -->
<div class="quick-box">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
    <div>
      <h3 style="margin:0;font-size:1.05rem;color:#c4b5fd;display:flex;align-items:center;gap:8px">
        <span>⚡ Quick Create Inbox</span>
        <span style="font-size:0.75rem;padding:2px 8px;border-radius:12px;background:rgba(139,92,246,0.25);color:#ddd6fe;font-weight:600">Instant Activation</span>
      </h3>
      <p style="margin:4px 0 0;font-size:0.82rem;color:#cbd5e1">Generate a clean, disposable or permanent email address ready to receive OTPs in seconds.</p>
    </div>
    <button type="button" onclick="randomizePrefix()" class="btn btn-sm btn-ghost" style="color:#ddd6fe;border-color:rgba(139,92,246,0.4)">🎲 Randomize Name</button>
  </div>

  <form method="POST" action="/dashboard.php" style="margin:0">
    <?php csrf_field(); ?>
    <input type="hidden" name="quick_create_inbox" value="1">
    
    <div class="quick-form-grid">
      <div>
        <input type="text" id="quick_local_part" name="local_part" placeholder="e.g. auth-bot-01 (or blank for random)" pattern="[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]?" style="margin:0;background:#060a12;border-color:#334155;width:100%" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '')">
      </div>
      <div>
        <select name="domain" style="margin:0;background:#060a12;border-color:#334155;width:100%" required>
          <?php if (!empty($eligible['pool'])): ?>
            <optgroup label="🌐 Shared Domain Pool">
              <?php foreach ($eligible['pool'] as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
          <?php if (!empty($eligible['custom'])): ?>
            <optgroup label="🔒 Your Custom Domains">
              <?php foreach ($eligible['custom'] as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn" style="background:#8b5cf6;white-space:nowrap;width:100%">⚡ Create Inbox</button>
      </div>
    </div>
  </form>
</div>

<!-- Active Inboxes List -->
<div class="inbox-table-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
      <h2 style="margin:0;font-size:1.15rem;color:#f8fafc">📥 Active Inboxes (<?= count($inboxes) ?>)</h2>
      <p style="margin:2px 0 0;font-size:0.8rem;color:#94a3b8">Click "View Messages" to monitor incoming emails and real-time OTP codes.</p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/inboxes.php" class="btn btn-sm btn-ghost">+ Advanced Settings</a>
    </div>
  </div>

  <?php if (empty($inboxes)): ?>
    <div style="text-align:center;padding:40px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #334155">
      <div style="font-size:2.2rem;margin-bottom:8px">📭</div>
      <h3 style="margin:0 0 6px;color:#f1f5f9">No Inboxes Active</h3>
      <p style="color:#94a3b8;font-size:0.85rem;max-width:400px;margin:0 auto 14px">Use the Quick Creator above to generate your first mailbox.</p>
    </div>
  <?php else: ?>
    <?php foreach ($inboxes as $in): ?>
      <div class="inbox-row">
        <div style="flex:1;min-width:200px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-family:monospace;font-size:0.95rem;font-weight:700;color:#f8fafc"><?= htmlspecialchars($in['email_address']) ?></span>
            <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?>', this)">📋 Copy</button>
          </div>
          <div style="font-size:0.76rem;color:#64748b;margin-top:4px">
            <?= (int)$in['retention_days'] ?>d Retention · Ready for OTP extraction
          </div>
        </div>

        <div class="inbox-actions" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>" class="btn-sm btn-success" style="padding:6px 14px;font-weight:600">⚡ View Messages</a>
          <a href="/expenses.php?inbox=<?= urlencode($in['email_address']) ?>" class="btn-sm btn-ghost" title="Extract Receipts & Invoices" style="border-color:#334155">🧾 Receipts</a>
          <a href="/send.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm btn-ghost" style="border-color:#334155">✉️ Send</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Quick Integrations Banner -->
<div class="grid2">
  <div class="card" style="border-left:4px solid #8b5cf6">
    <div style="font-size:1.3rem;margin-bottom:6px">🤖 AI Agent & MCP Integration</div>
    <p class="sub">Equip Claude Desktop, Cursor, or your autonomous LLM with email retrieval and OTP extraction tools.</p>
    <a href="/mcp_setup.php" class="btn btn-sm" style="background:#1e293b;border:1px solid #334155;color:#f8fafc">Setup MCP Server →</a>
  </div>

  <div class="card" style="border-left:4px solid #3b82f6">
    <div style="font-size:1.3rem;margin-bottom:6px">🌐 Custom Brand Domains</div>
    <p class="sub">Attach your personal domain names to receive incoming business emails with full SPF/DKIM verification.</p>
    <a href="/domains.php" class="btn btn-sm" style="background:#1e293b;border:1px solid #334155;color:#f8fafc">Manage Domains →</a>
  </div>
</div>

<script>
function randomizePrefix() {
  const adjectives = ['alpha', 'bravo', 'turbo', 'swift', 'agent', 'auth', 'verify', 'matrix', 'bot', 'nexus'];
  const adj = adjectives[Math.floor(Math.random() * adjectives.length)];
  const num = Math.floor(1000 + Math.random() * 9000);
  const input = document.getElementById('quick_local_part');
  if (input) {
    input.value = adj + '-' + num;
  }
}
</script>

<?php page_footer(); ?>
