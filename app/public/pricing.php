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
    1 => [
        'name' => 'Starter Runtime',
        'price' => $db_settings['price_tier_1'] ?? 1.00,
        'slots' => 5,
        'credits' => '1,000',
        'retention' => '30 Days',
        'domains' => '1 Custom Domain',
        'features' => ['5 Isolated Inboxes', 'Sub-50ms OTP Parser', 'Native MCP Integration', '1,000 API/Email Credits', 'Community Discord Support']
    ],
    2 => [
        'name' => 'Agent Pro',
        'price' => $db_settings['price_tier_2'] ?? 5.00,
        'slots' => 15,
        'credits' => '5,000',
        'retention' => '60 Days',
        'domains' => '3 Custom Domains',
        'popular' => true,
        'features' => ['15 Active Inboxes', 'Unlimited OTP Extractions', 'Claude/Cursor MCP Native Tool', '5,000 Non-Expiring Credits', '3 Custom Sending Domains', 'Automated Expense OCR']
    ],
    3 => [
        'name' => 'Business Agency',
        'price' => $db_settings['price_tier_3'] ?? 10.00,
        'slots' => 30,
        'credits' => '15,000',
        'retention' => '90 Days',
        'domains' => '5 Custom Domains',
        'features' => ['30 Multi-Brand Inboxes', '15,000 High-Speed Credits', 'Priority Outbound Queue', '5 Custom Domains + DKIM', 'Webhooks & Unified Stream', 'Dedicated SLA Support']
    ],
    4 => [
        'name' => 'Enterprise Cluster',
        'price' => $db_settings['price_tier_4'] ?? 25.00,
        'slots' => 100,
        'credits' => '50,000',
        'retention' => '180 Days',
        'domains' => '15 Custom Domains',
        'features' => ['100 Isolated Mailboxes', '50,000 API Credits', 'High-Volume Agent Swarms', '15 Custom Domains', '180-Day Data Retention', 'Direct Engineering Hotline']
    ],
    5 => [
        'name' => 'Unlimited Scale',
        'price' => $db_settings['price_tier_5'] ?? 50.00,
        'slots' => 250,
        'credits' => '150,000',
        'retention' => '365 Days',
        'domains' => 'Unlimited Domains',
        'features' => ['250 Scalable Inboxes', '150,000 API Credits', 'Custom IP Pool Allocation', 'Unlimited Domains', '1-Year Full Retention', 'White-Glove Architecture Review']
    ]
];

page_header('Plans & Upgrades', $user);
?>

<style>
.pricing-page-header {
  text-align: center;
  max-width: 720px;
  margin: 0 auto 36px;
}
.pricing-page-header h1 {
  font-size: 2rem;
  font-weight: 800;
  color: #f8fafc;
  letter-spacing: -0.02em;
  margin-bottom: 8px;
}
.pricing-page-header p {
  color: #94a3b8;
  font-size: 0.95rem;
}

.plan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.plan-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  position: relative;
  transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
}

.plan-card:hover {
  transform: translateY(-3px);
  border-color: #3b82f6;
  box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.4);
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
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
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
  font-size: 1.15rem;
  font-weight: 700;
  color: #f8fafc;
  margin-bottom: 6px;
}

.plan-price {
  font-size: 2.1rem;
  font-weight: 800;
  color: #f8fafc;
  margin-bottom: 16px;
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.plan-price span {
  font-size: 0.85rem;
  color: #94a3b8;
  font-weight: 500;
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0 0 24px 0;
  flex-grow: 1;
}

.plan-features li {
  font-size: 0.84rem;
  color: #cbd5e1;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.plan-features li svg {
  color: #38bdf8;
  flex-shrink: 0;
}

.redeem-box {
  background: #0f172a;
  border: 1px dashed #334155;
  border-radius: 12px;
  padding: 20px 24px;
  text-align: center;
  max-width: 600px;
  margin: 0 auto;
}
</style>

<div class="pricing-page-header">
  <h1>Choose Your Plan & Upgrade Credits</h1>
  <p>Non-expiring API credits, instant sub-50ms OTP extraction, and multi-brand mailbox scaling.</p>
</div>

<?php if ($user && $current_balance === 0): ?>
  <div class="alert alert-s" style="background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;margin-bottom:28px">
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
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span><?= htmlspecialchars($f) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($user): ?>
        <a href="/checkout.php?tier=<?= $t_num ?>" class="btn <?= $is_active ? 'btn-ghost' : 'btn-primary' ?>" style="width:100%;padding:11px 0;font-size:0.9rem">
          <?= $is_active ? 'Re-up / Add Credits →' : 'Upgrade to Tier ' . $t_num . ' →' ?>
        </a>
      <?php else: ?>
        <a href="/signup.php?redirect=<?= urlencode('/checkout.php?tier=' . $t_num) ?>" class="btn btn-primary" style="width:100%;padding:11px 0;font-size:0.9rem">
          Get Started ($<?= number_format($t['price'], 2) ?>/mo) →
        </a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="redeem-box">
  <h3 style="font-size:1.05rem;color:#f8fafc;margin-bottom:6px">Have an AppSumo or Lifetime License Key?</h3>
  <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Redeem your voucher code to unlock lifetime inboxes and permanent credit allocations.</p>
  <a href="/redeem.php" class="btn btn-ghost" style="font-size:0.85rem;padding:8px 18px">Redeem License Key →</a>
</div>

<?php page_footer(); ?>
