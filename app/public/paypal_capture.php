<?php
/**
 * paypal_capture.php — Handle return from PayPal, capture payment and upgrade user tier.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/paypal.php';
require_once __DIR__ . '/_layout.php';

$user = require_login();

$token = $_GET['token'] ?? ''; // PayPal Order ID is passed as 'token'
$tier = (int)($_GET['tier'] ?? 1);

$result = null;
$error = null;

if (!empty($token)) {
    $res = paypal_capture_order($token, (int)$user['id']);
    if (!empty($res['success'])) {
        $result = $res;
    } else {
        $error = $res['error'] ?? 'Payment capture failed.';
    }
} else {
    $error = 'No PayPal payment token received.';
}

page_header('Payment Confirmation', $user);
?>

<div class="container" style="max-width:520px;text-align:center">
    <div class="card" style="padding:32px 24px">
        <?php if ($result): ?>
            <div style="font-size:3rem;margin-bottom:12px">🎉</div>
            <h2 style="color:#4ade80;margin-top:0">Payment Successful!</h2>
            <p class="sub">Thank you for your purchase. Your account has been upgraded to <strong>Tier <?= $result['tier'] ?></strong> and <strong><?= number_format((int)$result['credits']) ?> credits</strong> have been added to your balance.</p>
            <div style="font-family:monospace;font-size:0.8rem;background:#0a0e1a;padding:8px;border-radius:6px;margin:16px 0;color:#94a3b8">
                Order ID: <?= htmlspecialchars($token) ?>
            </div>
            <a href="/dashboard.php" class="btn" style="margin-top:12px">Go to Dashboard →</a>
        <?php else: ?>
            <div style="font-size:3rem;margin-bottom:12px">⚠️</div>
            <h2 style="color:#f87171;margin-top:0">Payment Incomplete</h2>
            <p class="sub"><?= htmlspecialchars($error) ?></p>
            <div style="display:flex;gap:10px;justify-content:center;margin-top:16px">
                <a href="/checkout.php?tier=<?= $tier ?>" class="btn">Try Again</a>
                <a href="/dashboard.php" class="btn btn-ghost">Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
page_footer();
