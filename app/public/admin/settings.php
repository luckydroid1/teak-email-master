<?php
/**
 * admin/settings.php — PayPal credentials and system configuration with Modern 2-Column UX.
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
            $msg_success = '✓ PayPal gateway configuration successfully saved!';
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

admin_page_header('System & Payment Settings', $user);
?>

<style>
.settings-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 24px;
}
.settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}
.form-group {
  margin-bottom: 0;
}
.form-group label {
  font-size: 0.84rem;
  font-weight: 700;
  color: #cbd5e1;
  display: block;
  margin-bottom: 6px;
}
.form-group label .req {
  color: #ef4444;
  margin-left: 2px;
}
.form-group label .opt {
  color: #64748b;
  font-size: 0.75rem;
  font-weight: normal;
}
.form-group input, .form-group select {
  width: 100%;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid #334155;
  background: #0a0e1a;
  color: #f8fafc;
  font-size: 0.9rem;
  margin-bottom: 0;
}
.form-group input:focus, .form-group select:focus {
  border-color: #3b82f6;
  outline: none;
}
.pass-wrapper {
  position: relative;
}
.pass-toggle-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: #1e293b;
  border: 1px solid #334155;
  color: #94a3b8;
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: 6px;
  cursor: pointer;
}
@media(max-width: 768px) {
  .settings-grid { grid-template-columns: 1fr; gap: 14px; }
}
</style>

<div style="max-width:960px;margin:0 auto">

  <?php if ($msg_success): ?>
    <div class="alert alert-s" style="margin-bottom:20px"><?= htmlspecialchars($msg_success) ?></div>
  <?php endif; ?>
  <?php if ($msg_error): ?>
    <div class="alert alert-e" style="margin-bottom:20px"><?= htmlspecialchars($msg_error) ?></div>
  <?php endif; ?>

  <!-- PayPal Payment Gateway Settings Box (Exact Reference UX) -->
  <div class="settings-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px">
      <h2 style="font-size:1.3rem;font-weight:800;color:#f8fafc;margin:0">PayPal Payment Gateway Settings</h2>
      <?php if ($isConfigured): ?>
        <?php if ($paypalMode === 'live'): ?>
          <span style="background:rgba(34,197,94,0.15);border:1px solid #22c55e;color:#86efac;font-size:0.75rem;font-weight:700;padding:4px 12px;border-radius:20px">LIVE Active</span>
        <?php else: ?>
          <span style="background:rgba(245,158,11,0.15);border:1px solid #f59e0b;color:#fde68a;font-size:0.75rem;font-weight:700;padding:4px 12px;border-radius:20px">SANDBOX Testing</span>
        <?php endif; ?>
      <?php else: ?>
        <span style="background:rgba(100,116,139,0.15);border:1px solid #475569;color:#94a3b8;font-size:0.75rem;font-weight:700;padding:4px 12px;border-radius:20px">Not Configured</span>
      <?php endif; ?>
    </div>

    <p style="font-size:0.86rem;color:#94a3b8;margin:0 0 20px;line-height:1.6">
      Connect your official PayPal developer credentials to automate client subscriptions and checkout payments. Secret keys are encrypted with <strong>AES-GCM 256-bit</strong> at rest.
    </p>

    <form method="POST" action="/admin/settings.php" autocomplete="off" id="paypal-form">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="save_paypal">

      <div class="settings-grid">
        <!-- Environment Mode -->
        <div class="form-group">
          <label for="paypal_mode">Environment Mode <span class="req">*</span></label>
          <select name="paypal_mode" id="paypal_mode">
            <option value="live" <?= $paypalMode === 'live' ? 'selected' : '' ?>>Live (Production / Real Payments)</option>
            <option value="sandbox" <?= $paypalMode === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing / Development)</option>
          </select>
        </div>

        <!-- PayPal Client ID -->
        <div class="form-group">
          <label for="paypal_client_id">PayPal Client ID <span class="req">*</span></label>
          <input type="text" name="paypal_client_id" id="paypal_client_id" value="<?= htmlspecialchars($paypalClientId) ?>" placeholder="BAAFdGsaqQaf9ye4LyhTG8icYROQZi4OCVy..." autocomplete="new-password" required>
        </div>

        <!-- PayPal Secret Key -->
        <div class="form-group">
          <label for="paypal_secret">PayPal Secret Key <span class="req">*</span></label>
          <div class="pass-wrapper">
            <input type="password" name="paypal_secret" id="paypal_secret" value="<?= htmlspecialchars($paypalSecret) ?>" placeholder="••••••••••••••••••••••••••••••••" autocomplete="new-password" required style="padding-right:65px">
            <button type="button" class="pass-toggle-btn" onclick="togglePass('paypal_secret', this)">Show</button>
          </div>
        </div>

        <!-- PayPal Webhook ID -->
        <div class="form-group">
          <label for="paypal_webhook_id">PayPal Webhook ID <span class="opt">(Optional for auto-sync)</span></label>
          <input type="text" name="paypal_webhook_id" id="paypal_webhook_id" value="<?= htmlspecialchars($paypalWebhookId) ?>" placeholder="e.g. 4JH76843... (Webhook ID)" autocomplete="new-password">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:20px;flex-wrap:wrap">
        <button type="button" onclick="document.getElementById('test-conn-form').submit()" class="btn btn-ghost" style="padding:10px 20px;font-size:0.88rem">Test Connection</button>
        <button type="submit" class="btn" style="padding:10px 24px;font-size:0.88rem;background:#0284c7;font-weight:700">Save Credentials</button>
      </div>
    </form>

    <form method="POST" action="/admin/settings.php" id="test-conn-form" style="display:none">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="test_paypal">
    </form>
  </div>

  <!-- Admin Account Security Card -->
  <div class="settings-card" style="max-width:540px">
    <h3 style="font-size:1.1rem;font-weight:800;color:#f8fafc;margin:0 0 4px">🔑 Change Admin Password</h3>
    <p style="font-size:0.82rem;color:#94a3b8;margin:0 0 16px">Update credentials for currently logged in administrator (<strong><?= htmlspecialchars($user['email']) ?></strong>).</p>

    <form method="POST" action="/admin/settings.php" autocomplete="off">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="change_admin_password">

      <div style="margin-bottom:12px" class="form-group">
        <label for="new_password">New Password (min 8 characters)</label>
        <input type="password" name="new_password" id="new_password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
      </div>

      <div style="margin-bottom:16px" class="form-group">
        <label for="new_password_confirm">Confirm New Password</label>
        <input type="password" name="new_password_confirm" id="new_password_confirm" placeholder="••••••••" required minlength="8" autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-sm btn-ghost" style="width:100%">🔒 Update Admin Password</button>
    </form>
  </div>

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

<?php admin_page_footer(); ?>
