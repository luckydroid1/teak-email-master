<?php
/**
 * pricing.php — Upgrade Plan & Buy Credits selector for authenticated users.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/_layout.php';

$user = current_user();
$uid = $user ? (int)$user['id'] : 0;
$current_tier = $user && function_exists('user_tier') ? user_tier($uid) : 1;
$current_balance = $user && function_exists('credit_balance') ? credit_balance($uid) : 0;

// Fetch dynamic tier prices from ia_settings if available
$db_settings = [];
try {
    $rows = db()->query("SELECT setting_key, setting_value FROM ia_settings WHERE setting_key LIKE 'price_tier_%'")->fetchAll();
    foreach ($rows as $r) {
        $db_settings[$r['setting_key']] = (float)$r['setting_value'];
    }
} catch (Throwable $e) {}

$tiers = [
    0 => [
        'name' => 'Free Sandbox',
        'price' => 0.00,
        'slots' => 1,
        'credits' => '5 (Trial)',
        'retention' => '2 Days',
        'domains' => '0 Custom Domains',
        'features' => ['1 Active Inbox Slot', '5 Instant Trial Credits (5x Operations)', 'Shared Pool Domain (@teak.email)', '2-Day Email Retention', 'Sub-50ms OTP Parser', 'REST API Test Access']
    ],
    1 => [
        'name' => 'Tier 1 Plan',
        'price' => $db_settings['price_tier_1'] ?? 1.00,
        'slots' => 3,
        'credits' => '3,000',
        'retention' => '7 Days',
        'domains' => '1 Custom Domain',
        'features' => ['3 Active Inbox Slots', '3,000 Credits Included', '1 Custom Domain Slot', '7-Day Email Retention', 'Sub-50ms OTP Parser', 'REST API + MCP Access']
    ],
    2 => [
        'name' => 'Tier 2 Plan',
        'price' => $db_settings['price_tier_2'] ?? 7.00,
        'slots' => 25,
        'credits' => '25,000',
        'retention' => '14 Days',
        'domains' => '3 Custom Domains',
        'popular' => true,
        'features' => ['25 Active Inbox Slots', '25,000 Credits Included', '3 Custom Domain Slots', '14-Day Email Retention', 'Claude & Cursor MCP Native Tool', 'Automated Expense OCR']
    ],
    3 => [
        'name' => 'Tier 3 Plan',
        'price' => $db_settings['price_tier_3'] ?? 17.00,
        'slots' => 70,
        'credits' => '70,000',
        'retention' => '30 Days',
        'domains' => '5 Custom Domains',
        'features' => ['70 Active Inbox Slots', '70,000 Credits Included', '5 Custom Domain Slots', '30-Day Email Retention', 'Priority Outbound Queue', 'Webhooks & Unified Stream']
    ],
    4 => [
        'name' => 'Tier 4 Plan',
        'price' => $db_settings['price_tier_4'] ?? 27.00,
        'slots' => 125,
        'credits' => '125,000',
        'retention' => '45 Days',
        'domains' => '10 Custom Domains',
        'features' => ['125 Active Mailboxes', '125,000 API Credits', '10 Custom Domain Slots', '45-Day Email Retention', 'High-Volume Agent Swarms', 'Direct Engineering Support']
    ],
    5 => [
        'name' => 'Tier 5 Plan',
        'price' => $db_settings['price_tier_5'] ?? 37.00,
        'slots' => 190,
        'credits' => '190,000',
        'retention' => '60 Days',
        'domains' => '20 Custom Domains',
        'features' => ['190 Scalable Inboxes', '190,000 API Credits', '20 Custom Domain Slots', '60-Day Full Retention', 'Custom IP Pool Allocation', 'Full White-Glove Support']
    ]
];

page_header('Plans & Upgrades', $user);
?>

<style>
.pricing-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 16px;
  box-sizing: border-box;
}
.pricing-page-header {
  text-align: center;
  max-width: 720px;
  margin: 0 auto 32px;
}
.pricing-page-header h1 {
  font-size: 1.85rem;
  font-weight: 800;
  color: #f8fafc;
  letter-spacing: -0.02em;
  margin-bottom: 8px;
}
.pricing-page-header p {
  color: #94a3b8;
  font-size: 0.9rem;
  line-height: 1.5;
}

.plan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 20px;
  margin-bottom: 36px;
}

.plan-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
  padding: 22px 20px;
  display: flex;
  flex-direction: column;
  position: relative;
  transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
}

.plan-card.featured {
  border-color: rgba(59, 130, 246, 0.6);
  background: linear-gradient(180deg, #111e38 0%, #111827 100%);
  box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.4);
}

.plan-card.current-active {
  border-color: #10b981;
  background: linear-gradient(180deg, #06281e 0%, #111827 100%);
}

.plan-badge {
  position: absolute;
  top: 14px;
  right: 14px;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 12px;
}

.badge-pop {
  background: rgba(59, 130, 246, 0.2);
  border: 1px solid rgba(59, 130, 246, 0.4);
  color: #60a5fa;
}

.badge-active {
  background: rgba(16, 185, 129, 0.2);
  border: 1px solid rgba(16, 185, 129, 0.4);
  color: #34d399;
}

.plan-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #f8fafc;
  margin-bottom: 6px;
}

.plan-price {
  font-size: 1.9rem;
  font-weight: 800;
  color: #f8fafc;
  margin-bottom: 16px;
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.plan-price span {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0 0 20px 0;
  flex-grow: 1;
}

.plan-features li {
  font-size: 0.83rem;
  color: #cbd5e1;
  margin-bottom: 10px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  line-height: 1.4;
}

.plan-features li svg {
  color: #38bdf8;
  flex-shrink: 0;
  margin-top: 2px;
}

.redeem-box {
  background: #0f172a;
  border: 1px dashed #334155;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  max-width: 600px;
  margin: 0 auto 30px;
}

@media (max-width: 640px) {
  .pricing-page-header h1 { font-size: 1.6rem; }
  .plan-grid { grid-template-columns: 1fr; }
}
</style>

<div class="pricing-container">
  <div style="margin-bottom: 16px;">
    <a href="/dashboard.php" style="color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:0.88rem;font-weight:600;padding:6px 12px;border-radius:8px;background:rgba(56,189,248,0.08);border:1px solid rgba(56,189,248,0.2)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      <span>← Back to Dashboard / My Account</span>
    </a>
  </div>

  <div class="pricing-page-header">
    <h1>Choose Your Plan & Upgrade Credits</h1>
    <p>Non-expiring API credits, instant sub-50ms OTP extraction, and multi-brand mailbox scaling.</p>
  </div>

  <?php if ($user && $current_balance === 0): ?>
    <div style="background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;margin-bottom:24px;padding:14px 16px;border-radius:10px;font-size:0.88rem;line-height:1.5">
      <span>💡 <strong>Account Status:</strong> You currently have <strong>0 credits</strong>. Select any plan below to activate your inboxes and receive instant credits.</span>
    </div>
  <?php endif; ?>

  <div class="plan-grid">
    <?php foreach ($tiers as $t_num => $t): ?>
      <?php
        $is_active = ($user && $current_tier === $t_num);
        $is_featured = !empty($t['popular']) && !$is_active;
      ?>
      <div class="plan-card <?= $is_active ? 'current-active' : ($is_featured ? 'featured' : '') ?>">
        <?php if ($is_active): ?>
          <span class="plan-badge badge-active">Current Plan</span>
        <?php elseif (!empty($t['popular'])): ?>
          <span class="plan-badge badge-pop">Most Popular</span>
        <?php endif; ?>

        <div class="plan-title"><?= htmlspecialchars($t['name']) ?></div>
        <div class="plan-price">
          $<?= number_format($t['price'], 2) ?>
          <span>/ month</span>
        </div>

        <ul class="plan-features">
          <?php foreach ($t['features'] as $f): ?>
            <li>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span><?= htmlspecialchars($f) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($t_num === 0): ?>
          <?php if ($user): ?>
            <a href="/dashboard.php" class="btn btn-ghost" style="width:100%;box-sizing:border-box;padding:12px 0;font-size:0.9rem;text-align:center">
              <?= $is_active ? 'Active Sandbox (Go to App) →' : 'Included in Account' ?>
            </a>
          <?php else: ?>
            <a href="/signup.php" class="btn btn-ghost" style="width:100%;box-sizing:border-box;padding:12px 0;font-size:0.9rem;text-align:center;border-color:#10b981;color:#6ee7b7">
              Try Free Sandbox (5x) →
            </a>
          <?php endif; ?>
        <?php elseif ($user): ?>
          <a href="/checkout.php?tier=<?= $t_num ?>" class="btn <?= $is_active ? 'btn-ghost' : 'btn-primary' ?>" style="width:100%;box-sizing:border-box;padding:12px 0;font-size:0.9rem;text-align:center">
            <?= $is_active ? 'Re-up / Add Credits →' : 'Upgrade to Tier ' . $t_num . ' →' ?>
          </a>
        <?php else: ?>
          <a href="/signup.php?redirect=<?= urlencode('/checkout.php?tier=' . $t_num) ?>" class="btn btn-primary" style="width:100%;box-sizing:border-box;padding:12px 0;font-size:0.9rem;text-align:center">
            Get Started ($<?= number_format($t['price'], 2) ?>/mo) →
          </a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="redeem-box">
    <h3 style="font-size:1rem;color:#f8fafc;margin-bottom:6px">Have an AppSumo or Lifetime License Key?</h3>
    <p style="font-size:0.83rem;color:#94a3b8;margin-bottom:14px;line-height:1.4">Redeem your voucher code to unlock lifetime inboxes and permanent credit allocations.</p>
    <a href="/redeem.php" class="btn btn-ghost" style="font-size:0.85rem;padding:8px 18px;display:inline-block">Redeem License Key →</a>
  </div>
</div>

<?php page_footer(); ?>
