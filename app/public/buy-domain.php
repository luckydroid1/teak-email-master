<?php
/**
 * buy-domain.php — Buy a domain directly from Teak Email.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/resellerclub.php';
require_once __DIR__ . '/../src/mailcow.php';

$user = require_login();
$error = $success = '';
$results = [];
$buying = false;
$configured = resellerclub_configured();

// Handle domain check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_domain'])) {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = strtolower(trim($_POST['domain_name'] ?? ''));
        $tld = strtolower(trim($_POST['tld'] ?? 'com'));
        if (empty($name) || !preg_match('/^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/', $name)) {
            $error = 'Invalid domain name. Use lowercase letters, numbers, and hyphens.';
        } else {
            $tlds_to_check = $tld ? [$tld, 'com', 'net', 'org', 'io', 'co'] : ['com', 'net', 'org', 'io', 'co'];
            $tlds_to_check = array_values(array_unique($tlds_to_check));
            $results = domain_check($name, $tlds_to_check);
            if (empty($results)) {
                $error = 'Could not check domain availability. Please try again.';
            }
        }
    }
}

// Handle domain purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_domain'])) {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $buying = true;
        $domain = $_POST['domain'] ?? '';
        if (empty($domain)) {
            $error = 'No domain selected';
        } else {
            $result = domain_register($domain);
            if (isset($result['ok'])) {
                $success = "Domain '$domain' registered successfully! DNS records configured automatically.";
            } else {
                $error = $result['error'] ?? 'Registration failed';
            }
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Buy Domain', $user);
?>
<div style="max-width:640px;margin:30px auto">

  <a href="/domains.php" class="back">← Back to Domains</a>

  <div style="text-align:center;margin-bottom:24px">
    <h1 style="font-size:1.5rem;margin-bottom:6px">🛒 Buy a Domain</h1>
    <p class="sub" style="margin:0">Register a new domain directly from Teak Email. Auto-configured for email.</p>
  </div>

  <?php alert($error, $success); ?>

  <?php if (!$success): ?>

  <?php if (!$configured): ?>
  <!-- Private Beta / Support State -->
  <div class="card" style="border-left:4px solid #3b82f6;text-align:center;padding:36px 20px">
    <div style="font-size:2.4rem;margin-bottom:8px">🌐</div>
    <h2 style="margin-bottom:8px">Instant Domain Registration (Private Beta)</h2>
    <p style="color:#94a3b8;font-size:.9rem;max-width:500px;margin:0 auto 16px">
      Direct domain provisioning is currently rolling out to select enterprise accounts. You can instantly connect any existing domain from Cloudflare, Namecheap, GoDaddy, or Spaceship in the Domains tab.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <a href="/domains.php" class="btn" style="font-size:.88rem">⚡ Connect Existing Domain →</a>
      <a href="mailto:support@teak.email?subject=Direct%20Domain%20Registration%20Inquiry" class="btn btn-ghost" style="font-size:.88rem">Contact Support</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($configured): ?>
  <!-- Domain Search -->
  <div class="card" style="border-left:4px solid #3b82f6">
    <h3 style="margin:0 0 8px;font-size:1rem">🔍 Search Domain</h3>
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
      <?php csrf_field(); ?>
      <input type="hidden" name="check_domain" value="1">
      <div style="flex:1;min-width:200px">
        <label style="font-size:.8rem;color:#94a3b8;margin-bottom:4px">Domain name</label>
        <div style="display:flex">
          <input type="text" name="domain_name" placeholder="yourbrand" required
                 style="flex:1;border-radius:8px 0 0 8px;margin-bottom:0">
          <select name="tld" style="width:auto;border-radius:0 8px 8px 0;margin-bottom:0;border-left:none">
            <option value="com">.com</option>
            <option value="net">.net</option>
            <option value="org">.org</option>
            <option value="io">.io</option>
            <option value="co">.co</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn">Search</button>
    </form>
  </div>

  <!-- Results -->
  <?php if (!empty($results)): ?>
  <div class="card">
    <h3 style="margin:0 0 12px;font-size:1rem">Results</h3>
    <?php foreach ($results as $r): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #1f2937;flex-wrap:wrap;gap:8px">
      <div>
        <div style="font-weight:600;color:#f9fafb;font-size:1rem"><?= htmlspecialchars($r['domain']) ?></div>
        <div style="font-size:.8rem;margin-top:2px">
          <?php if ($r['available']): ?>
            <span style="color:#22c55e">✅ Available</span>
            <?php if ($r['price']): ?>
              <span style="color:#94a3b8;margin-left:8px">$<?= number_format($r['price'], 2) ?>/year</span>
            <?php endif; ?>
          <?php else: ?>
            <span style="color:#ef4444">❌ Taken</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($r['available']): ?>
      <form method="post" style="margin:0">
        <?php csrf_field(); ?>
        <input type="hidden" name="buy_domain" value="1">
        <input type="hidden" name="domain" value="<?= htmlspecialchars($r['domain']) ?>">
        <button type="submit" class="btn btn-success" onclick="return confirm('Buy <?= htmlspecialchars($r['domain']) ?>?')">
          🛒 Buy <?= $r['price'] ? '$' . number_format($r['price'], 2) : '' ?>
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Pricing Info -->
  <div class="card" style="border-left:4px solid #f59e0b">
    <h3 style="margin:0 0 8px;font-size:1rem">💰 Pricing</h3>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:8px">Wholesale prices via ResellerClub. No markup.</p>
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <tr style="border-bottom:1px solid #1f2937">
        <td style="padding:6px 0;color:#94a3b8">.com</td>
        <td style="padding:6px 0;color:#f9fafb;text-align:right">~$7.49/yr</td>
      </tr>
      <tr style="border-bottom:1px solid #1f2937">
        <td style="padding:6px 0;color:#94a3b8">.net</td>
        <td style="padding:6px 0;color:#f9fafb;text-align:right">~$7.49/yr</td>
      </tr>
      <tr style="border-bottom:1px solid #1f2937">
        <td style="padding:6px 0;color:#94a3b8">.org</td>
        <td style="padding:6px 0;color:#f9fafb;text-align:right">~$6.99/yr</td>
      </tr>
      <tr style="border-bottom:1px solid #1f2937">
        <td style="padding:6px 0;color:#94a3b8">.io</td>
        <td style="padding:6px 0;color:#f9fafb;text-align:right">~$39.99/yr</td>
      </tr>
      <tr>
        <td style="padding:6px 0;color:#94a3b8">.co</td>
        <td style="padding:6px 0;color:#f9fafb;text-align:right">~$9.99/yr</td>
      </tr>
    </table>
    <p style="font-size:.78rem;color:#4b5563;margin-top:8px">Prices are wholesale. Domain auto-added to your Domains list with MX/DNS configured.</p>
  </div>
  <?php endif; /* configured */ ?>

  <?php else: ?>

  <!-- Success -->
  <div class="card" style="border-color:#22c55e;text-align:center;padding:30px">
    <div style="font-size:2rem;margin-bottom:8px">🎉</div>
    <h2 style="margin-bottom:8px">Domain Registered!</h2>
    <p style="color:#94a3b8">Your domain has been added to your Domains list with email configured.</p>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:20px">
      <a href="/domains.php" class="btn" style="text-decoration:none">View Domains</a>
      <a href="/inboxes.php" class="btn btn-success" style="text-decoration:none">Create Inbox</a>
    </div>
  </div>

  <?php endif; ?>

  <!-- How it works -->
  <div class="card">
    <h3 style="margin:0 0 8px;font-size:1rem">ℹ️ How It Works</h3>
    <div style="font-size:.85rem;color:#94a3b8;line-height:1.8">
      <p>1. Search for your desired domain name</p>
      <p>2. Click "Buy" on an available domain</p>
      <p>3. Domain is registered via ResellerClub</p>
      <p>4. MX, SPF, and DKIM records are configured automatically</p>
      <p>5. Create an inbox on your new domain!</p>
    </div>
  </div>

</div>
<?php page_footer(); ?>
