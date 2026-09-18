<?php
/**
 * checkout.php — Create PayPal Order and redirect to PayPal Approval URL.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/paypal.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/_layout.php';

$user = require_login();

$tier = (int)($_GET['tier'] ?? $_POST['tier'] ?? 1);
if ($tier < 1 || $tier > 5) {
    $tier = 1;
}

$info = tier_info($tier) ?? tier_info(1);

$error = null;
$loading = false;

// If POST request, create the PayPal order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = paypal_create_order((int)$user['id'], $tier);
    if (!empty($res['approval_url'])) {
        header('Location: ' . $res['approval_url']);
        exit;
    } else {
        $error = $res['error'] ?? 'Could not initiate PayPal checkout. Please ensure PayPal credentials are set in Admin.';
    }
}

page_header("Checkout Tier $tier", $user);
?>

<div class="container" style="max-width:560px">
    <a href="/dashboard.php" class="back">← Back to Dashboard</a>

    <div class="card">
        <h2 style="margin-top:0;display:flex;align-items:center;gap:8px">
            <span>💳 Subscribe / Upgrade Plan</span>
        </h2>
        <p class="sub">Instant account activation and non-expiring credit allocation via PayPal.</p>

        <?php if ($error): ?>
            <div class="alert alert-e" style="margin-bottom:16px">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div style="background:#0a0e1a;border:1px solid #1f2937;border-radius:8px;padding:16px;margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <div style="font-weight:700;font-size:1.1rem;color:#f8fafc">Tier <?= $tier ?> Plan</div>
                    <div style="font-size:0.8rem;color:#94a3b8">Billed via PayPal</div>
                </div>
                <div style="font-size:1.6rem;font-weight:800;color:#38bdf8">
                    $<?= number_format((float)($info['price'] ?? 1), 2) ?>
                </div>
            </div>
            <ul style="list-style:none;padding:0;font-size:0.85rem;color:#94a3b8;line-height:1.8;border-top:1px solid #1f2937;padding-top:10px">
                <li>✓ <strong><?= number_format((int)$info['credits']) ?></strong> credits included</li>
                <li>✓ <strong><?= $info['inbox_slots'] ?></strong> active inbox slots</li>
                <li>✓ <strong><?= $info['domains'] ?></strong> custom domain sync slots</li>
                <li>✓ <?= $info['retention'] ?>-day email retention</li>
                <li>✓ REST API + MCP Server access</li>
            </ul>
        </div>

        <form method="POST" action="/checkout.php">
            <input type="hidden" name="tier" value="<?= $tier ?>">
            <button type="submit" class="btn" style="width:100%;padding:12px;font-size:1rem;background:#0070ba;color:#fff">
                <span>Pay with PayPal ($<?= number_format((float)($info['price'] ?? 1), 2) ?>) →</span>
            </button>
        </form>

        <div style="text-align:center;font-size:0.75rem;color:#64748b;margin-top:14px">
            🔒 Secure checkout processed directly via PayPal REST API.
        </div>
    </div>
</div>

<?php
page_footer();
