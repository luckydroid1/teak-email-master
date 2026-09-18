<?php
/**
 * admin/settings.php — PayPal credentials and system configuration.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/settings.php';
require_once __DIR__ . '/_admin_layout.php';

$user = require_admin();

$msg_success = null;
$msg_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_paypal') {
        $clientId = trim($_POST['paypal_client_id'] ?? '');
        $secret   = trim($_POST['paypal_secret'] ?? '');
        $mode     = in_array($_POST['paypal_mode'] ?? '', ['sandbox', 'live'], true) ? $_POST['paypal_mode'] : 'sandbox';
        $currency = strtoupper(trim($_POST['paypal_currency'] ?? 'USD'));

        setting_set('paypal_client_id', $clientId);
        setting_set('paypal_secret', $secret);
        setting_set('paypal_mode', $mode);
        setting_set('paypal_currency', $currency);

        audit($user['id'], 'admin_save_paypal_settings', 'web', "mode=$mode currency=$currency");
        $msg_success = 'PayPal credentials & configuration successfully saved!';
    } elseif ($action === 'test_paypal') {
        require_once __DIR__ . '/../../src/paypal.php';
        $token = paypal_access_token();
        if ($token) {
            $msg_success = '✓ Connection to PayPal API succeeded! OAuth Token received.';
        } else {
            $msg_error = '✗ Connection to PayPal API failed. Please verify your Client ID, Secret, and Environment mode.';
        }
    }
}

$paypalClientId = setting_get('paypal_client_id', '');
$paypalSecret   = setting_get('paypal_secret', '');
$paypalMode     = setting_get('paypal_mode', 'sandbox');
$paypalCurrency = setting_get('paypal_currency', 'USD');

admin_header('PayPal & Settings', $user, 'settings');
?>

<?php if ($msg_success): ?>
    <div class="alert alert-s" style="margin-bottom:20px">
        <span><?= htmlspecialchars($msg_success) ?></span>
    </div>
<?php endif; ?>

<?php if ($msg_error): ?>
    <div class="alert alert-e" style="margin-bottom:20px">
        <span><?= htmlspecialchars($msg_error) ?></span>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1.4fr 0.6fr;gap:24px">
    <div class="card">
        <h2 style="margin-top:0;display:flex;align-items:center;gap:8px">
            <span>💳 PayPal Gateway Configuration</span>
        </h2>
        <p class="sub">Enter your PayPal REST API credentials below. When configured, checkout buttons on the pricing table and user dashboard will generate real PayPal orders.</p>

        <form method="POST" action="/admin/settings.php">
            <input type="hidden" name="action" value="save_paypal">

            <div style="margin-bottom:16px">
                <label for="paypal_mode">Environment Mode</label>
                <select name="paypal_mode" id="paypal_mode">
                    <option value="sandbox" <?= $paypalMode === 'sandbox' ? 'selected' : '' ?>>🧪 Sandbox (Testing / Development)</option>
                    <option value="live" <?= $paypalMode === 'live' ? 'selected' : '' ?>>🚀 Live (Production Real Payments)</option>
                </select>
                <div style="font-size:0.75rem;color:#64748b;margin-top:4px">Use Sandbox for testing accounts or Live when ready to charge real credit cards / balances.</div>
            </div>

            <div style="margin-bottom:16px">
                <label for="paypal_client_id">PayPal Client ID</label>
                <input type="text" name="paypal_client_id" id="paypal_client_id" value="<?= htmlspecialchars($paypalClientId) ?>" placeholder="e.g. AVSkX... or your live client id" autocomplete="off" required>
            </div>

            <div style="margin-bottom:16px">
                <label for="paypal_secret">PayPal Secret Key</label>
                <input type="password" name="paypal_secret" id="paypal_secret" value="<?= htmlspecialchars($paypalSecret) ?>" placeholder="e.g. EK9x..." autocomplete="off" required>
            </div>

            <div style="margin-bottom:20px">
                <label for="paypal_currency">Currency Code</label>
                <input type="text" name="paypal_currency" id="paypal_currency" value="<?= htmlspecialchars($paypalCurrency) ?>" placeholder="USD" style="max-width:140px">
                <div style="font-size:0.75rem;color:#64748b;margin-top:4px">Default: <strong>USD</strong>. Matches your $1, $7, $17, $27, $37 tier pricing.</div>
            </div>

            <div style="display:flex;gap:12px;align-items:center">
                <button type="submit" class="btn">💾 Save PayPal Settings</button>
            </div>
        </form>
    </div>

    <div>
        <div class="card">
            <h3 style="margin-top:0">🔍 Test API Connection</h3>
            <p class="sub">Verify if the provided Client ID and Secret Key can authenticate with the PayPal OAuth2 server.</p>
            <form method="POST" action="/admin/settings.php">
                <input type="hidden" name="action" value="test_paypal">
                <button type="submit" class="btn btn-sm btn-ghost" style="width:100%">⚡ Test PayPal API Connection</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0">💡 How to Get Credentials</h3>
            <ol style="font-size:0.85rem;color:#94a3b8;padding-left:20px;line-height:1.6">
                <li>Go to <a href="https://developer.paypal.com/dashboard/applications" target="_blank" style="color:#38bdf8">developer.paypal.com</a></li>
                <li>Login with your PayPal Business Account.</li>
                <li>Create an App under <strong>Apps & Credentials</strong>.</li>
                <li>Copy the <strong>Client ID</strong> and <strong>Secret Key</strong> into the fields on the left.</li>
                <li>Click <strong>Save</strong> and run the test connection.</li>
            </ol>
        </div>
    </div>
</div>

<?php
admin_footer();
