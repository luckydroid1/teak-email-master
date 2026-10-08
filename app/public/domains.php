<?php
/**
 * domains.php — Modern Domain Management & Setup Wizard.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/mailcow.php';
require_once __DIR__ . '/../src/registrar.php';

$user = require_login();
$error = $success = '';

// Handle add domain manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_domain'])) {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        if (!preg_match('/^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
            $error = 'Invalid domain name. Example: mydomain.com';
        } elseif (mailcow_domain_exists($domain)) {
            $error = "Domain '$domain' is already registered in the system.";
        } else {
            $res = mailcow_add_domain($domain);
            if ($res['ok']) {
                $success = "Domain '$domain' added! Configure the DNS records below to start receiving emails.";
            } else {
                $error = $res['error'] ?? 'Failed to add domain';
            }
        }
    }
}

// Handle auto-sync from registrar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_registrar'])) {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $registrar = $_POST['registrar'] ?? '';
        $auth_key = trim($_POST['auth_key'] ?? '');
        $auth_secret = trim($_POST['auth_secret'] ?? '');

        if (empty($registrar) || empty($auth_key)) {
            $error = 'Please select a registrar and enter your API credentials.';
        } else {
            $result = registrar_sync($registrar, $auth_key, $auth_secret);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $success = "Synced {$result['total']} domains from registrar ({$result['added']} new, {$result['existing']} existing).";
            }
        }
    }
}

// Handle Live DNS Health Check
$dns_report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_dns_domain'])) {
    if (csrf_validate()) {
        $chk_dom = strtolower(trim($_POST['check_dns_domain']));
        $mx_records = @dns_get_record($chk_dom, DNS_MX) ?: [];
        $txt_records = @dns_get_record($chk_dom, DNS_TXT) ?: [];

        $has_mx = false;
        foreach ($mx_records as $mx) {
            if (isset($mx['target']) && str_contains(strtolower($mx['target']), 'teak.email')) {
                $has_mx = true;
                break;
            }
        }
        $has_spf = false;
        foreach ($txt_records as $txt) {
            if (isset($txt['txt']) && str_contains($txt['txt'], 'v=spf1')) {
                $has_spf = true;
                break;
            }
        }
        $dns_report = [
            'domain' => $chk_dom,
            'has_mx' => $has_mx,
            'has_spf' => $has_spf,
            'mx_count' => count($mx_records),
            'txt_count' => count($txt_records)
        ];
    }
}

$domains = mailcow_list_domains();
$registrars = registrar_list();

require_once __DIR__ . '/_layout.php';
page_header('Custom Domains', $user);
?>

<style>
.tab-btn {
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.88rem;
  border: 1px solid #374151;
  background: #0f172a;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.15s;
}
.tab-btn.active {
  background: #2563eb;
  color: #fff;
  border-color: #3b82f6;
}
.tab-pane { display: none; }
.tab-pane.active { display: block; }

.dns-row {
  display: grid;
  grid-template-columns: 80px 80px 1fr auto;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  background: #0a0e1a;
  border-radius: 8px;
  border: 1px solid #1f2937;
  margin-bottom: 8px;
  font-size: 0.85rem;
}
@media(max-width: 650px) {
  .dns-row { grid-template-columns: 1fr; gap: 8px; }
}
</style>

<div style="max-width:760px;margin:10px auto 40px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px">Custom Brand Domains</h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">Add your own domain names to create branded inboxes with full SPF & DKIM support.</p>
    </div>
    <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Back to Dashboard</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-e" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-s" style="margin-bottom:20px"><?= $success ?></div>
  <?php endif; ?>

  <?php if ($dns_report): ?>
  <div class="card" style="border-color:#3b82f6;background:rgba(59,130,246,0.06);margin-bottom:20px">
    <h3 style="color:#93c5fd;margin:0 0 10px;font-size:1.05rem">DNS Verification Status: <?= htmlspecialchars($dns_report['domain']) ?></h3>
    <div style="font-size:0.88rem;line-height:1.8">
      <div style="display:flex;align-items:center;gap:8px">
        <span>MX Record (Incoming Mail):</span>
        <?= $dns_report['has_mx'] ? '<span style="color:#86efac;font-weight:700">✓ Verified (Pointing to mail.teak.email)</span>' : '<span style="color:#fca5a5;font-weight:700">✗ Missing (Point MX to mail.teak.email)</span>' ?>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <span>SPF Protection Record:</span>
        <?= $dns_report['has_spf'] ? '<span style="color:#86efac;font-weight:700">✓ Verified (SPF TXT record active)</span>' : '<span style="color:#fbbf24;font-weight:700">Optional (Add TXT v=spf1)</span>' ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Add Domain Tabs (Manual vs Registrar Auto-Sync) -->
  <div style="display:flex;gap:10px;margin-bottom:16px">
    <button type="button" class="tab-btn active" id="tab-btn-manual" onclick="switchDomainTab('manual')">➕ Add Domain Manually</button>
    <button type="button" class="tab-btn" id="tab-btn-sync" onclick="switchDomainTab('sync')">Registrar Auto-Sync</button>
  </div>

  <!-- Tab 1: Manual Add Domain -->
  <div id="pane-manual" class="tab-pane active card" style="border-left:4px solid #3b82f6">
    <h3 style="margin:0 0 6px;font-size:1.05rem">Connect Domain via DNS</h3>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Type your domain name below. We will provide the exact DNS records to point to Teak Email.</p>

    <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?php csrf_field(); ?>
      <input type="hidden" name="add_domain" value="1">
      <div style="flex:1;min-width:240px">
        <input type="text" name="domain" placeholder="e.g. mycompany.com or mail.brand.io" required style="margin:0" autofocus>
      </div>
      <button type="submit" class="btn" style="white-space:nowrap">➕ Add Domain</button>
    </form>
  </div>

  <!-- Tab 2: Registrar Auto Sync -->
  <div id="pane-sync" class="tab-pane card" style="border-left:4px solid #8b5cf6">
    <h3 style="margin:0 0 6px;font-size:1.05rem">Auto-Import from Registrar API</h3>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Automatically import and configure domain records from your registrar account.</p>

    <form method="POST">
      <?php csrf_field(); ?>
      <input type="hidden" name="sync_registrar" value="1">

      <label>Select Registrar</label>
      <select name="registrar" id="registrar-select" required>
        <option value="">Choose registrar...</option>
        <?php foreach ($registrars as $k => $r): ?>
          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <div id="auth-token-field" style="display:none">
        <label id="auth-label">API Key / Token</label>
        <input type="text" name="auth_key" id="auth-key" placeholder="Enter API Key...">
        <p style="font-size:0.76rem;color:#64748b;margin:-8px 0 12px" id="auth-help"></p>
      </div>

      <div id="auth-key-secret-field" style="display:none">
        <label id="auth-secret-label">Secret Key</label>
        <input type="text" name="auth_secret" id="auth-secret" placeholder="Enter Secret Key...">
        <p style="font-size:0.76rem;color:#64748b;margin:-8px 0 12px" id="auth-help2"></p>
      </div>

      <button type="submit" class="btn" style="background:#8b5cf6;width:100%">Sync All Domains</button>
    </form>
  </div>

  <!-- Required DNS Records Box with 1-Click Copy -->
  <div class="card" style="border-left:4px solid #f59e0b;margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:1.05rem;color:#fde68a">Required DNS Records</h3>
      <span style="font-size:0.76rem;color:#94a3b8">Add these records at your DNS Manager (Cloudflare/Namecheap/GoDaddy)</span>
    </div>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Point these 3 records to route incoming emails to Teak Email server:</p>

    <!-- MX Record -->
    <div class="dns-row">
      <span style="font-weight:700;color:#60a5fa">MX</span>
      <span style="color:#cbd5e1;font-family:monospace">@</span>
      <span style="color:#f8fafc;font-family:monospace">mail.teak.email <span style="color:#94a3b8;font-size:0.75rem">(Priority 10)</span></span>
      <button type="button" class="btn-copy" onclick="copyToClipboard('mail.teak.email', this)">Copy</button>
    </div>

    <!-- SPF Record -->
    <div class="dns-row">
      <span style="font-weight:700;color:#60a5fa">TXT</span>
      <span style="color:#cbd5e1;font-family:monospace">@</span>
      <span style="color:#f8fafc;font-family:monospace">v=spf1 mx a ~all</span>
      <button type="button" class="btn-copy" onclick="copyToClipboard('v=spf1 mx a ~all', this)">Copy</button>
    </div>

    <!-- CNAME Record -->
    <div class="dns-row">
      <span style="font-weight:700;color:#60a5fa">CNAME</span>
      <span style="color:#cbd5e1;font-family:monospace">mta-sts</span>
      <span style="color:#f8fafc;font-family:monospace">mta-sts.teak.email</span>
      <button type="button" class="btn-copy" onclick="copyToClipboard('mta-sts.teak.email', this)">Copy</button>
    </div>
  </div>

  <!-- Connected Domains List -->
  <div class="card" style="margin-top:20px">
    <h3 style="margin:0 0 14px;font-size:1.1rem;color:#f8fafc">Connected Domains (<?= count($domains) ?>)</h3>
    <?php if (empty($domains)): ?>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">No custom domains connected yet.</p>
    <?php else: ?>
      <?php foreach ($domains as $d): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;margin-bottom:8px;flex-wrap:wrap;gap:10px">
          <div>
            <span style="font-weight:700;font-size:0.95rem;color:#f8fafc"><?= htmlspecialchars($d['domain']) ?></span>
            <?php if ($d['active']): ?>
              <span style="background:rgba(34,197,94,0.15);border:1px solid #22c55e;color:#86efac;font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px">✓ Active</span>
            <?php else: ?>
              <span style="background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px">Pending DNS</span>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:8px;align-items:center">
            <form method="POST" style="display:inline;margin:0">
              <?php csrf_field(); ?>
              <input type="hidden" name="check_dns_domain" value="<?= htmlspecialchars($d['domain']) ?>">
              <button type="submit" class="btn-sm btn-ghost" title="Check live DNS records">Verify DNS</button>
            </form>
            <a href="/inboxes.php?domain=<?= urlencode($d['domain']) ?>" class="btn-sm btn-success">+ Create Inbox</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
function switchDomainTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  if (tab === 'manual') {
    document.getElementById('tab-btn-manual').classList.add('active');
    document.getElementById('pane-manual').classList.add('active');
  } else {
    document.getElementById('tab-btn-sync').classList.add('active');
    document.getElementById('pane-sync').classList.add('active');
  }
}

const regSelect = document.getElementById('registrar-select');
if (regSelect) {
  regSelect.addEventListener('change', function() {
    const v = this.value;
    const tf = document.getElementById('auth-token-field');
    const sf = document.getElementById('auth-key-secret-field');
    const hl = document.getElementById('auth-label');
    const hp = document.getElementById('auth-help');
    if (!v) {
      tf.style.display = 'none';
      sf.style.display = 'none';
      return;
    }
    if (v === 'spaceship' || v === 'namecheap') {
      tf.style.display = 'block';
      sf.style.display = 'block';
      hl.textContent = 'API Key';
      hp.textContent = 'Found in your ' + v + ' account developer settings.';
    } else {
      tf.style.display = 'block';
      sf.style.display = 'none';
      hl.textContent = 'API Token';
      hp.textContent = 'Found in your registrar API tokens dashboard.';
    }
  });
}
</script>

<?php page_footer(); ?>
