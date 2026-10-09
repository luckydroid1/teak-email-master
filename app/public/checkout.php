<?php
/**
 * checkout.php — Create PayPal Order and redirect to PayPal Approval URL.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/paypal.php';
require_once __DIR__ . '/../src/redeem.php';

$user = require_login();

$tier = (int)($_GET['tier'] ?? $_POST['tier'] ?? 1);
if ($tier < 1 || $tier > 5) {
    $tier = 1;
}

$info = tier_info($tier) ?? tier_info(1);
$error = null;

// Process PayPal checkout initiation (via POST or explicit pay action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['action']) && $_GET['action'] === 'pay')) {
    $res = paypal_create_order((int)$user['id'], $tier);
    if (!empty($res['approval_url'])) {
        header('Location: ' . $res['approval_url'], true, 302);
        exit;
    } else {
        $error = $res['error'] ?? 'Could not connect to PayPal. Please check PayPal credentials in Admin Settings.';
    }
}

require_once __DIR__ . '/_layout.php';
page_header("Checkout Tier $tier", $user);
?>

<div class="container" style="max-width:560px;margin:30px auto">
    <a href="/pricing.php" class="back" style="color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:0.88rem;margin-bottom:18px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      <span>Back to All Plans</span>
    </a>

    <div class="card" style="background:#111827;border:1px solid #1f2937;border-radius:16px;padding:28px 24px;box-shadow:0 20px 40px -15px rgba(0,0,0,0.5)">
        <h2 style="margin:0 0 6px;font-size:1.45rem;color:#f8fafc;font-weight:700">Subscribe / Upgrade Plan</h2>
        <p class="sub" style="margin:0 0 20px;font-size:0.85rem;color:#94a3b8">Instant account activation and non-expiring credit allocation via PayPal.</p>

        <?php if ($error): ?>
            <div class="alert alert-e" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;font-size:0.88rem">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div style="background:#0a0e1a;border:1px solid #1f2937;border-radius:12px;padding:20px;margin-bottom:24px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
                <div>
                    <div style="font-weight:700;font-size:1.15rem;color:#f8fafc"><?= htmlspecialchars($info['name'] ?? "Tier $tier") ?></div>
                    <div style="font-size:0.8rem;color:#94a3b8">Billed securely via PayPal</div>
                </div>
                <div style="font-size:1.8rem;font-weight:800;color:#38bdf8">
                    $<?= number_format((float)($info['price'] ?? 1), 2) ?>
                </div>
            </div>
            <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;color:#cbd5e1;line-height:1.9;border-top:1px solid #1f2937;padding-top:14px">
                <li>✓ <strong><?= number_format((int)$info['credits']) ?></strong> API/Email credits included</li>
                <li>✓ <strong><?= $info['inbox_slots'] ?></strong> active inbox slots</li>
                <li>✓ <strong><?= $info['domains'] ?></strong> custom domain sync slots</li>
                <li>✓ <?= $info['retention'] ?>-day email retention</li>
                <li>✓ REST API + Native MCP Server access</li>
            </ul>
        </div>

        <a href="/checkout.php?action=pay&tier=<?= $tier ?>" class="btn" style="width:100%;box-sizing:border-box;padding:14px;font-size:1rem;font-weight:700;background:linear-gradient(135deg, #0070ba 0%, #003087 100%);color:#fff;border-radius:10px;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 14px rgba(0,112,186,0.4)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.283-.58 3.69-2.82 5.56-6.66 5.56H9.722l-1.42 8.986a.641.641 0 0 1-.633.542l-.593.156z"/>
            </svg>
            <span>Pay with PayPal ($<?= number_format((float)($info['price'] ?? 1), 2) ?>) →</span>
        </a>

        <div style="text-align:center;font-size:0.78rem;color:#64748b;margin-top:16px;display:flex;align-items:center;justify-content:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span>Processed via official PayPal Gateway (Cards & Balance accepted)</span>
        </div>
    </div>
</div>

<?php
page_footer();
