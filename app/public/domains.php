<?php
/**
 * domains.php — Modern Domain Management & Setup Wizard.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/mailcow.php';
require_once __DIR__ . '/../src/registrar.php';
require_once __DIR__ . '/../src/custom_domains.php';
require_once __DIR__ . '/../src/redeem.php';

$user = require_login();
$uid = (int)$user['id'];
$tier = user_tier($uid);
$error = $success = '';

// Handle add domain manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_domain'])) {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        if (!preg_match('/^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
            $error = 'Invalid domain name. Example: mydomain.com';
        } else {
            $res = custom_domain_add($uid, $domain, 'manual');
            if ($res['ok']) {
                $records = custom_domain_verify_dns_records($domain);
                $dns_report = [
                    'domain' => $domain,
                    'records' => $records
                ];
                $new_st = $records['mx']['valid'] ? 'verified' : (!empty($records['mx']['record']) && $records['mx']['record'] !== 'none' ? 'conflict' : 'pending');
                db()->prepare('UPDATE ia_user_domains SET status = ?, mx_summary = ? WHERE user_id = ? AND domain = ?')
                    ->execute([$new_st, $records['mx']['record'] ?? '', $uid, $domain]);

                if (($res['status'] ?? '') === 'already_exists') {
                    $success = "Domain '$domain' is already in your domain list! Live DNS status checked below.";
                } else {
                    $success = "Domain '$domain' added! Configure the DNS records below to start receiving emails.";
                }
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
            $result = registrar_sync($uid, $registrar, $auth_key, $auth_secret);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $success = "Synced {$result['total']} domains from registrar ({$result['added']} newly added to your account, {$result['existing']} already existed).";
            }
        }
    }
}

// Handle Live DNS Health Check
$dns_report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_dns_domain'])) {
    if (csrf_validate()) {
        require_once __DIR__ . '/../src/custom_domains.php';
        $chk_dom = strtolower(trim($_POST['check_dns_domain']));
        $records = custom_domain_verify_dns_records($chk_dom);
        $dns_report = [
            'domain' => $chk_dom,
            'records' => $records
        ];
        // Automatically sync updated live status to database
        $new_st = $records['mx']['valid'] ? 'verified' : (!empty($records['mx']['record']) && $records['mx']['record'] !== 'none' ? 'conflict' : 'pending');
        db()->prepare('UPDATE ia_user_domains SET status = ?, mx_summary = ? WHERE user_id = ? AND domain = ?')
            ->execute([$new_st, $records['mx']['record'] ?? '', $uid, $chk_dom]);
    }
}

$st_ud = db()->prepare('SELECT * FROM ia_user_domains WHERE user_id = ? ORDER BY status = "verified" DESC, domain ASC');
$st_ud->execute([$uid]);
$user_custom_domains = $st_ud->fetchAll(PDO::FETCH_ASSOC);

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

  <?php if ($tier === 0): ?>
    <div style="background:rgba(234,179,8,0.08);border:1px solid rgba(234,179,8,0.25);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-weight:700;color:#fde047;font-size:0.92rem">Custom Domains Locked (Free Sandbox)</div>
        <div style="font-size:0.82rem;color:#cbd5e1">Custom domain inboxes are available on Tier 1 ($1/mo) and above. Upgrade your plan to link brand domains and receive incoming emails directly.</div>
      </div>
      <a href="/pricing.php" class="btn btn-sm" style="background:#eab308;color:#0f172a;font-weight:700;white-space:nowrap">Upgrade Plan →</a>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-e" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-s" style="margin-bottom:20px"><?= $success ?></div>
  <?php endif; ?>

  <?php if ($dns_report): ?>
  <div class="card" style="border-color:#3b82f6;background:rgba(59,130,246,0.06);margin-bottom:20px">
    <h3 style="color:#93c5fd;margin:0 0 10px;font-size:1.05rem">DNS Verification Status: <?= htmlspecialchars($dns_report['domain']) ?></h3>
    <div style="font-size:0.88rem;line-height:2">
      <div style="display:flex;align-items:center;gap:8px">
        <strong style="color:#cbd5e1;width:140px">MX Record:</strong>
        <?= $dns_report['records']['mx']['valid'] ? '<span style="color:#86efac;font-weight:700">Verified (' . htmlspecialchars($dns_report['records']['mx']['record'] ?? 'mail.teak.email') . ')</span>' : '<span style="color:#fca5a5;font-weight:700">Missing (Point MX to mail.teak.email)</span>' ?>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <strong style="color:#cbd5e1;width:140px">SPF Protection:</strong>
        <?= $dns_report['records']['spf']['valid'] ? '<span style="color:#86efac;font-weight:700">Verified (' . htmlspecialchars($dns_report['records']['spf']['record']) . ')</span>' : '<span style="color:#fbbf24;font-weight:700">Optional / Recommended (v=spf1 mx a ~all)</span>' ?>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <strong style="color:#cbd5e1;width:140px">DKIM Signature:</strong>
        <?= $dns_report['records']['dkim']['valid'] ? '<span style="color:#86efac;font-weight:700">Verified (' . htmlspecialchars($dns_report['records']['dkim']['selector'] ?? 'mail') . '._domainkey active)</span>' : '<span style="color:#94a3b8;font-weight:500">Unconfigured (Add TXT mail._domainkey)</span>' ?>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <strong style="color:#cbd5e1;width:140px">DMARC Policy:</strong>
        <?= $dns_report['records']['dmarc']['valid'] ? '<span style="color:#86efac;font-weight:700">Active (' . htmlspecialchars($dns_report['records']['dmarc']['record']) . ')</span>' : '<span style="color:#94a3b8;font-weight:500">Unconfigured (_dmarc TXT)</span>' ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Choose Addition Mode: Manual vs Automatic -->
  <div style="margin-bottom:20px">
    <div style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:700;margin-bottom:10px">Choose Setup Method:</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div id="choice-manual" onclick="switchDomainTab('manual')" style="cursor:pointer;padding:14px 16px;border-radius:10px;border:2px solid #3b82f6;background:#0d1829;transition:all 0.15s">
        <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#60a5fa;font-size:0.95rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
          1. Manual DNS Setup
        </div>
        <p style="margin:4px 0 0;font-size:0.78rem;color:#94a3b8">Enter domain name, then point the 4 DNS records at your registrar or DNS manager.</p>
      </div>
      <div id="choice-sync" onclick="switchDomainTab('sync')" style="cursor:pointer;padding:14px 16px;border-radius:10px;border:1px solid #1f2937;background:#0a0e1a;transition:all 0.15s">
        <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#c084fc;font-size:0.95rem">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          2. Auto-Sync via Registrar API
        </div>
        <p style="margin:4px 0 0;font-size:0.78rem;color:#94a3b8">Bulk import domains automatically via Spaceship, Cloudflare, or Namecheap API.</p>
      </div>
    </div>
  </div>

  <!-- Tab 1: Manual Add Domain -->
  <div id="pane-manual" class="tab-pane active card" style="border-left:4px solid #3b82f6">
    <h3 style="margin:0 0 6px;font-size:1.05rem;color:#93c5fd">Connect Domain Manually</h3>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Enter the domain you wish to use. The system will register it to your account and generate 4 verified DNS records.</p>

    <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?php csrf_field(); ?>
      <input type="hidden" name="add_domain" value="1">
      <div style="flex:1;min-width:240px">
        <input type="text" name="domain" placeholder="e.g. mybrand.com or customdomain.io" required style="margin:0" autofocus>
      </div>
      <button type="submit" class="btn" style="white-space:nowrap">Register Domain</button>
    </form>
  </div>

  <!-- Tab 2: Registrar Auto Sync -->
  <div id="pane-sync" class="tab-pane card" style="border-left:4px solid #8b5cf6">
    <h3 style="margin:0 0 6px;font-size:1.05rem;color:#c084fc">Import Domains via Registrar API</h3>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Manage multiple domains at Spaceship, Cloudflare, or Namecheap? Enter your API key to import them into your account in one click.</p>

    <form method="POST">
      <?php csrf_field(); ?>
      <input type="hidden" name="sync_registrar" value="1">

      <label>Choose Your Registrar</label>
      <select name="registrar" id="registrar-select" required>
        <option value="">Select registrar...</option>
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

      <button type="submit" class="btn" style="background:#8b5cf6;width:100%">Import &amp; Sync Domains</button>
    </form>
  </div>

  <!-- Required DNS Records Box with 1-Click Copy -->
  <div class="card" id="required-dns-box" style="border-left:4px solid #f59e0b;margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:1.05rem;color:#fde68a">Required DNS Records for Spaceship / DNS Manager</h3>
      <span style="font-size:0.76rem;color:#94a3b8">Add these records at your Registrar (Spaceship / Cloudflare / Namecheap)</span>
    </div>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px">Point these records to route incoming emails to Teak Email server:</p>

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
      <span style="color:#f8fafc;font-family:monospace">v=spf1 mx ~all</span>
      <button type="button" class="btn-copy" onclick="copyToClipboard('v=spf1 mx ~all', this)">Copy</button>
    </div>

    <!-- DKIM Record -->
    <div class="dns-row">
      <span style="font-weight:700;color:#60a5fa">TXT</span>
      <span style="color:#cbd5e1;font-family:monospace">mail._domainkey</span>
      <span style="color:#f8fafc;font-family:monospace">v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8...</span>
      <button type="button" class="btn-copy" onclick="copyToClipboard('v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1vjJ', this)">Copy</button>
    </div>

    <!-- DMARC Record -->
    <div class="dns-row">
      <span style="font-weight:700;color:#60a5fa">TXT</span>
      <span style="color:#cbd5e1;font-family:monospace">_dmarc</span>
      <span style="color:#f8fafc;font-family:monospace">v=DMARC1; p=none;</span>
      <button type="button" class="btn-copy" onclick="copyToClipboard('v=DMARC1; p=none;', this)">Copy</button>
    </div>
  </div>

  <!-- Connected Domains List -->
  <div class="card" style="margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:1.1rem;color:#f8fafc">My Connected Domains (<?= !empty($user_custom_domains) ? count($user_custom_domains) : count($domains) ?>)</h3>
      <span style="font-size:0.8rem;color:#94a3b8">Only domains with MX pointing to <strong>mail.teak.email</strong> can receive incoming emails.</span>
    </div>

    <?php
    $list_to_render = !empty($user_custom_domains) ? $user_custom_domains : array_map(fn($d) => ['domain' => $d['domain'], 'status' => $d['active'] ? 'verified' : 'pending', 'mx_summary' => ''], $domains);
    ?>

    <?php if (empty($list_to_render)): ?>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">No custom domains connected yet.</p>
    <?php else: ?>
      <?php foreach ($list_to_render as $d): ?>
        <?php $d_status = $d['status'] ?? 'pending'; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;margin-bottom:8px;flex-wrap:wrap;gap:10px">
          <div>
            <span style="font-weight:700;font-size:0.95rem;color:#f8fafc"><?= htmlspecialchars($d['domain']) ?></span>
            <?php if ($d_status === 'verified'): ?>
              <span style="background:rgba(34,197,94,0.15);border:1px solid #22c55e;color:#86efac;font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px">Active &amp; Receiving</span>
            <?php elseif ($d_status === 'conflict'): ?>
              <span style="background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px" title="MX: <?= htmlspecialchars($d['mx_summary'] ?? '') ?>">Conflict (Points to <?= htmlspecialchars($d['mx_summary'] ?? 'external') ?>)</span>
            <?php else: ?>
              <span style="background:rgba(234,179,8,0.15);border:1px solid #eab308;color:#fde047;font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px">Pending DNS (Need MX Record)</span>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:8px;align-items:center">
            <form method="POST" style="display:inline;margin:0">
              <?php csrf_field(); ?>
              <input type="hidden" name="check_dns_domain" value="<?= htmlspecialchars($d['domain']) ?>">
              <button type="submit" class="btn-sm btn-ghost" title="Check live DNS records">Verify DNS</button>
            </form>
            <?php if ($d_status === 'verified'): ?>
              <a href="/inboxes.php?domain=<?= urlencode($d['domain']) ?>" class="btn-sm btn-success">+ Create Inbox</a>
            <?php else: ?>
              <a href="#required-dns-box" onclick="document.getElementById('required-dns-box').scrollIntoView({behavior:'smooth'})" class="btn-sm btn-ghost" style="border-color:#eab308;color:#fde047">Setup DNS</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
function switchDomainTab(tab) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  const cardManual = document.getElementById('choice-manual');
  const cardSync = document.getElementById('choice-sync');

  if (tab === 'manual') {
    document.getElementById('pane-manual').classList.add('active');
    if (cardManual) {
      cardManual.style.borderColor = '#3b82f6';
      cardManual.style.background = '#0d1829';
    }
    if (cardSync) {
      cardSync.style.borderColor = '#1f2937';
      cardSync.style.background = '#0a0e1a';
    }
  } else {
    document.getElementById('pane-sync').classList.add('active');
    if (cardSync) {
      cardSync.style.borderColor = '#8b5cf6';
      cardSync.style.background = '#180d29';
    }
    if (cardManual) {
      cardManual.style.borderColor = '#1f2937';
      cardManual.style.background = '#0a0e1a';
    }
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
