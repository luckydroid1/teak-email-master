<?php
/**
 * admin/settings.php — Clean & Spacious Full-Width Layout.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/settings.php';
require_once __DIR__ . '/_admin_layout.php';

$user = require_admin();

$msg_success = null;
$msg_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $msg_error = 'Invalid security token or session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_paypal') {
            $clientId = trim($_POST['paypal_client_id'] ?? '');
            $secret   = trim($_POST['paypal_secret'] ?? '');
            $webhookId = trim($_POST['paypal_webhook_id'] ?? '');
            $mode     = in_array($_POST['paypal_mode'] ?? '', ['sandbox', 'live'], true) ? $_POST['paypal_mode'] : 'sandbox';
            $currency = strtoupper(trim($_POST['paypal_currency'] ?? 'USD'));

            setting_set('paypal_client_id', $clientId);
            setting_set('paypal_secret', $secret);
            setting_set('paypal_webhook_id', $webhookId);
            setting_set('paypal_mode', $mode);
            setting_set('paypal_currency', $currency);

            audit($user['id'], 'admin_save_paypal_settings', 'web', "mode=$mode currency=$currency");
            $msg_success = '✓ PayPal gateway credentials saved successfully!';
        } elseif ($action === 'change_admin_password') {
            $newPass = $_POST['new_password'] ?? '';
            $newPassConfirm = $_POST['new_password_confirm'] ?? '';

            if (strlen($newPass) < 8) {
                $msg_error = 'New password must be at least 8 characters.';
            } elseif ($newPass !== $newPassConfirm) {
                $msg_error = 'Password confirmation does not match.';
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                db()->prepare('UPDATE ia_users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
                audit($user['id'], 'admin_change_own_password', 'web');
                $msg_success = '✓ Admin password changed successfully!';
            }
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
}

$paypalClientId = setting_get('paypal_client_id', '');
$paypalSecret   = setting_get('paypal_secret', '');
$paypalWebhookId = setting_get('paypal_webhook_id', '');
$paypalMode     = setting_get('paypal_mode', 'sandbox');
$paypalCurrency = setting_get('paypal_currency', 'USD');

$isConfigured = !empty($paypalClientId) && !empty($paypalSecret);

admin_header('PayPal & System Settings', $user, 'settings');
?>

<style>
.full-settings-box {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 28px;
}
.full-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}
.form-field {
  margin-bottom: 0;
}
.form-field label {
  font-size: 0.88rem;
  font-weight: 700;
  color: #f1f5f9;
  display: block;
  margin-bottom: 8px;
}
.form-field label .req {
  color: #ef4444;
  margin-left: 2px;
}
.form-field label .opt {
  color: #64748b;
  font-size: 0.78rem;
  font-weight: normal;
}
.form-field input, .form-field select {
  width: 100%;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid #334155;
  background: #0a0e1a;
  color: #f8fafc;
  font-size: 0.92rem;
  margin-bottom: 0;
}
.form-field input:focus, .form-field select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
  outline: none;
}
.pass-input-box {
  position: relative;
}
.pass-show-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: #1e293b;
  border: 1px solid #475569;
  color: #cbd5e1;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 6px;
  cursor: pointer;
}
.pass-show-btn:hover {
  background: #334155;
  color: #fff;
}
@media(max-width: 768px) {
  .full-settings-box { padding: 20px; }
  .full-form-grid { grid-template-columns: 1fr; gap: 16px; }
}
</style>

<?php if ($msg_success): ?>
  <div class="alert alert-s" style="margin-bottom:24px"><?= htmlspecialchars($msg_success) ?></div>
<?php endif; ?>
<?php if ($msg_error): ?>
  <div class="alert alert-e" style="margin-bottom:24px"><?= htmlspecialchars($msg_error) ?></div>
<?php endif; ?>

<!-- 💳 PayPal Gateway Settings Box (Full Width 100% Matching Reference) -->
<div class="full-settings-box">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:12px">
    <h2 style="font-size:1.35rem;font-weight:800;color:#f8fafc;margin:0;display:flex;align-items:center;gap:10px">
      <span>PayPal Payment Gateway Settings</span>
    </h2>
    <?php if ($isConfigured): ?>
      <?php if ($paypalMode === 'live'): ?>
        <span style="background:rgba(34,197,94,0.15);border:1px solid #22c55e;color:#86efac;font-size:0.8rem;font-weight:700;padding:5px 14px;border-radius:20px">LIVE Active</span>
      <?php else: ?>
        <span style="background:rgba(245,158,11,0.15);border:1px solid #f59e0b;color:#fde68a;font-size:0.8rem;font-weight:700;padding:5px 14px;border-radius:20px">SANDBOX Testing</span>
      <?php endif; ?>
    <?php else: ?>
      <span style="background:rgba(100,116,139,0.15);border:1px solid #475569;color:#94a3b8;font-size:0.8rem;font-weight:700;padding:5px 14px;border-radius:20px">Not Configured</span>
    <?php endif; ?>
  </div>

  <p style="font-size:0.9rem;color:#94a3b8;margin:0 0 28px;line-height:1.6">
    Connect your official PayPal developer credentials to automate client subscriptions and checkout payments. Secret keys are encrypted with <strong>AES-GCM 256-bit</strong> at rest.
  </p>

  <form method="POST" action="/admin/settings.php" autocomplete="off">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="save_paypal">

    <div class="full-form-grid">
      <!-- Row 1, Col 1: Environment Mode -->
      <div class="form-field">
        <label for="paypal_mode">Environment Mode <span class="req">*</span></label>
        <select name="paypal_mode" id="paypal_mode">
          <option value="live" <?= $paypalMode === 'live' ? 'selected' : '' ?>>Live (Production / Real Payments)</option>
          <option value="sandbox" <?= $paypalMode === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing / Development)</option>
        </select>
      </div>

      <!-- Row 1, Col 2: PayPal Client ID -->
      <div class="form-field">
        <label for="paypal_client_id">PayPal Client ID <span class="req">*</span></label>
        <input type="text" name="paypal_client_id" id="paypal_client_id" value="<?= htmlspecialchars($paypalClientId) ?>" placeholder="BAAFdGsaqQaf9ye4LyhTG8icYROQZi4OCVy..." autocomplete="new-password" required>
      </div>

      <!-- Row 2, Col 1: PayPal Secret Key -->
      <div class="form-field">
        <label for="paypal_secret">PayPal Secret Key <span class="req">*</span></label>
        <div class="pass-input-box">
          <input type="password" name="paypal_secret" id="paypal_secret" value="<?= htmlspecialchars($paypalSecret) ?>" placeholder="••••••••••••••••••••••••••••••••" autocomplete="new-password" required style="padding-right:75px">
          <button type="button" class="pass-show-btn" onclick="togglePass('paypal_secret', this)">Show</button>
        </div>
      </div>

      <!-- Row 2, Col 2: PayPal Webhook ID -->
      <div class="form-field">
        <label for="paypal_webhook_id">PayPal Webhook ID <span class="opt">(Optional for auto-sync)</span></label>
        <input type="text" name="paypal_webhook_id" id="paypal_webhook_id" value="<?= htmlspecialchars($paypalWebhookId) ?>" placeholder="e.g. 4JH76843... (Webhook ID)" autocomplete="new-password">
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:28px;flex-wrap:wrap">
      <button type="button" onclick="document.getElementById('test-conn-form').submit()" class="btn btn-ghost" style="padding:10px 22px;font-size:0.9rem">Test Connection</button>
      <button type="submit" class="btn" style="padding:10px 26px;font-size:0.9rem;background:#0284c7;font-weight:700">Save Credentials</button>
    </div>
  </form>

  <form method="POST" action="/admin/settings.php" id="test-conn-form" style="display:none">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="test_paypal">
  </form>
</div>

<!-- 🔑 Admin Security Box (Full Width / Separate Section) -->
<div class="full-settings-box" style="border-top:4px solid #f59e0b">
  <div style="margin-bottom:18px">
    <h3 style="font-size:1.2rem;font-weight:800;color:#f8fafc;margin:0 0 4px">🔑 Change Admin Password</h3>
    <p style="font-size:0.86rem;color:#94a3b8;margin:0">Update credentials for currently logged in administrator (<strong><?= htmlspecialchars($user['email']) ?></strong>).</p>
  </div>

  <form method="POST" action="/admin/settings.php" autocomplete="off" style="max-width:640px">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="change_admin_password">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div class="form-field">
        <label for="new_password">New Password (min 8 chars)</label>
        <input type="password" name="new_password" id="new_password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
      </div>

      <div class="form-field">
        <label for="new_password_confirm">Confirm New Password</label>
        <input type="password" name="new_password_confirm" id="new_password_confirm" placeholder="••••••••" required minlength="8" autocomplete="new-password">
      </div>
    </div>

    <button type="submit" class="btn btn-ghost" style="font-weight:700;padding:10px 20px">🔒 Update Password</button>
  </form>
</div>

<script>
function togglePass(inputId, btn) {
  const el = document.getElementById(inputId);
  if (el) {
    if (el.type === 'password') {
      el.type = 'text';
      btn.textContent = 'Hide';
    } else {
      el.type = 'password';
      btn.textContent = 'Show';
    }
  }
}
</script>

<?php admin_footer(); ?>
