<?php
/**
 * domains.php — Manage custom domains (manual + auto-sync from registrars).
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
    $domain = strtolower(trim($_POST['domain'] ?? ''));
    if (!preg_match('/^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
        $error = 'Invalid domain name';
    } elseif (mailcow_domain_exists($domain)) {
        $error = 'Domain already added';
    } else {
        $res = mailcow_add_domain($domain);
        if ($res['ok']) {
            $success = "Domain '$domain' added! Set up DNS records below.";
        } else {
            $error = $res['error'] ?? 'Failed to add domain';
        }
    }
}

// Handle auto-sync from registrar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_registrar'])) {
    $registrar = $_POST['registrar'] ?? '';
    $auth_key = trim($_POST['auth_key'] ?? '');
    $auth_secret = trim($_POST['auth_secret'] ?? '');

    if (empty($registrar) || empty($auth_key)) {
        $error = 'Please select a registrar and enter your API key';
    } else {
        $result = registrar_sync($registrar, $auth_key, $auth_secret);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $success = "Synced from {$result['total']} domains: {$result['added']} new, {$result['existing']} already existed.";
        }
    }
}

$domains = mailcow_list_domains();
$registrars = registrar_list();

require_once __DIR__ . '/_layout.php';
page_header('Domains', $user);
?>
<div style="max-width:640px;margin:30px auto">

  <div style="text-align:center;margin-bottom:24px">
    <h1 style="font-size:1.5rem;margin-bottom:6px">🌐 Your Domains</h1>
    <p class="sub" style="margin:0">Add domains manually or sync from your registrar.</p>
  </div>

  <?php alert($error, $success); ?>

  <!-- Auto-Sync from Registrar -->
  <div class="card" style="border-left:4px solid #8b5cf6">
    <h3 style="margin:0 0 4px;font-size:1rem">⚡ Auto-Sync from Registrar</h3>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:14px">Connect your registrar account to import all domains automatically.</p>

    <form method="post">
      <input type="hidden" name="sync_registrar" value="1">

      <label style="font-size:.8rem;color:#94a3b8;display:block;margin-bottom:4px">Registrar</label>
      <select name="registrar" id="registrar-select" required
              style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px">
        <option value="">Select a registrar...</option>
        <?php foreach ($registrars as $key => $r): ?>
          <option value="<?= $key ?>"><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Dynamic auth fields -->
      <div id="auth-fields">
        <div id="auth-token-field" style="display:none">
          <label style="font-size:.8rem;color:#94a3b8;display:block;margin-bottom:4px" id="auth-label">API Key</label>
          <input type="text" name="auth_key" id="auth-key" placeholder="..."
                 style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:8px">
          <p style="font-size:.75rem;color:#64748b;margin:0 0 12px" id="auth-help"></p>
        </div>
        <div id="auth-key-secret-field" style="display:none">
          <label style="font-size:.8rem;color:#94a3b8;display:block;margin-bottom:4px" id="auth-secret-label">Secret Key</label>
          <input type="text" name="auth_secret" id="auth-secret" placeholder="..."
                 style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.9rem;margin-bottom:8px">
          <p style="font-size:.75rem;color:#64748b;margin:0 0 12px" id="auth-help2"></p>
        </div>
      </div>

      <button type="submit" class="btn" style="background:#8b5cf6;width:100%">⚡ Sync Domains</button>
    </form>
  </div>

  <!-- Manual Add -->
  <div class="card" style="border-left:4px solid #3b82f6">
    <h3 style="margin:0 0 4px;font-size:1rem">➕ Add Domain Manually</h3>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:12px">Enter a domain name to add it directly.</p>
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="add_domain" value="1">
      <div style="flex:1;min-width:200px">
        <input type="text" name="domain" placeholder="yourdomain.com" required
               style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0;font-size:.95rem">
      </div>
      <button type="submit" class="btn" style="margin-bottom:0">Add</button>
    </form>
  </div>

  <?php if (!empty($domains)): ?>
  <!-- DNS Instructions -->
  <div class="card" style="border-left:4px solid #f59e0b">
    <h3 style="margin:0 0 8px;font-size:1rem">⚠️ DNS Setup Required</h3>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:12px">After adding a domain, add these DNS records at your registrar:</p>
    <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:14px;font-size:.85rem">
      <table style="width:100%;border-collapse:collapse">
        <tr style="border-bottom:1px solid #334155">
          <td style="padding:6px 0;color:#94a3b8;font-weight:600">Type</td>
          <td style="padding:6px 0;color:#94a3b8;font-weight:600">Name</td>
          <td style="padding:6px 0;color:#94a3b8;font-weight:600">Value</td>
        </tr>
        <tr style="border-bottom:1px solid #1e293b">
          <td style="padding:6px 0;color:#60a5fa">MX</td>
          <td style="padding:6px 0;color:#e2e8f0">@</td>
          <td style="padding:6px 0;color:#e2e8f0;font-family:monospace;font-size:.8rem">mail.teak.email (priority 10)</td>
        </tr>
        <tr style="border-bottom:1px solid #1e293b">
          <td style="padding:6px 0;color:#60a5fa">TXT</td>
          <td style="padding:6px 0;color:#e2e8f0">@</td>
          <td style="padding:6px 0;color:#e2e8f0;font-family:monospace;font-size:.8rem">v=spf1 mx a ~all</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:#60a5fa">CNAME</td>
          <td style="padding:6px 0;color:#e2e8f0">mta-sts</td>
          <td style="padding:6px 0;color:#e2e8f0;font-family:monospace;font-size:.8rem">mta-sts.teak.email</td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Domain list -->
  <div class="card">
    <h3 style="margin:0 0 12px;font-size:1rem">Your Domains</h3>
    <?php foreach ($domains as $d): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #334155;font-size:.9rem;flex-wrap:wrap;gap:8px">
        <div>
          <span style="font-weight:600;color:#e2e8f0"><?= htmlspecialchars($d['domain']) ?></span>
          <?php if ($d['active']): ?>
            <span style="background:#14532d;color:#86efac;font-size:.7rem;padding:2px 8px;border-radius:10px;margin-left:8px">Active</span>
          <?php else: ?>
            <span style="background:#7f1d1d;color:#fca5a5;font-size:.7rem;padding:2px 8px;border-radius:10px;margin-left:8px">Pending DNS</span>
          <?php endif; ?>
          <?php if (!empty($d['description']) && str_starts_with($d['description'], 'Synced')): ?>
            <span style="background:#1e293b;color:#94a3b8;font-size:.7rem;padding:2px 8px;border-radius:10px;margin-left:4px">🔗 <?= htmlspecialchars($d['description']) ?></span>
          <?php endif; ?>
        </div>
        <a href="/inboxes.php?domain=<?= urlencode($d['domain']) ?>" style="color:#3b82f6;font-size:.85rem;text-decoration:none">Create inbox →</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Shared domains -->
  <div class="card">
    <h3 style="margin:0 0 8px;font-size:1rem">📦 Shared Domains</h3>
    <p style="font-size:.85rem;color:#94a3b8;margin-bottom:12px">Use these domains without any DNS setup:</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php $shared = cfg()['pool_domains'] ?? []; foreach ($shared as $sd): ?>
        <span style="background:#1e293b;border:1px solid #334155;padding:6px 14px;border-radius:20px;font-size:.85rem;color:#cbd5e1">
          <?= htmlspecialchars($sd) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
// Registrar selector — show/hide auth fields dynamically
const registrars = <?= json_encode($registrars) ?>;
const sel = document.getElementById('registrar-select');
const tokenField = document.getElementById('auth-token-field');
const ksField = document.getElementById('auth-key-secret-field');

sel.addEventListener('change', () => {
  const r = registrars[sel.value];
  if (!r) { tokenField.style.display = 'none'; ksField.style.display = 'none'; return; }

  if (r.auth_type === 'token') {
    tokenField.style.display = 'block';
    ksField.style.display = 'none';
    document.getElementById('auth-label').textContent = r.auth_label;
    document.getElementById('auth-key').placeholder = r.auth_placeholder;
    document.getElementById('auth-help').innerHTML = r.help + ' <a href="' + r.docs_url + '" target="_blank" style="color:#3b82f6">Get key →</a>';
  } else {
    tokenField.style.display = 'block';
    ksField.style.display = 'block';
    document.getElementById('auth-label').textContent = r.auth_label;
    document.getElementById('auth-key').placeholder = r.auth_placeholder;
    document.getElementById('auth-secret-label').textContent = r.auth_secret_label;
    document.getElementById('auth-secret').placeholder = r.auth_secret_placeholder;
    document.getElementById('auth-help').innerHTML = r.help;
    document.getElementById('auth-help2').innerHTML = '<a href="' + r.docs_url + '" target="_blank" style="color:#3b82f6">Get credentials →</a>';
  }
});
</script>

<?php page_footer(); ?>
