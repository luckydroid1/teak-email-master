<?php
/**
 * api_keys.php — generate & manage API keys with copyable SDK snippets.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/apikey.php';
require_once __DIR__ . '/../src/credits.php';

$user = require_login();
$uid = (int)$user['id'];
$app_url = cfg()['app_url'] ?? 'https://teak.email';
$error = $success = '';
$new_key = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (isset($_POST['revoke_id'])) {
        apikey_revoke($uid, (int)$_POST['revoke_id']);
        $success = 'API Key revoked successfully';
    } elseif (isset($_POST['generate_key'])) {
        $res = apikey_create($uid);
        $new_key = $res['key'];
        $success = 'New API key generated! You can copy it now or toggle Show anytime.';
    }
}
$keys = apikey_list($uid);
$active_key_for_snippets = $new_key ?? (!empty($keys[0]['full_key']) ? $keys[0]['full_key'] : 'YOUR_API_KEY');

require_once __DIR__ . '/_layout.php';
page_header('MCP / API Keys & Agent Integration', $user);
alert($error, $success);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px">MCP / API Keys & Integration</h1>
    <p style="color:#94a3b8;font-size:0.88rem;margin:0">Create and view authentication keys for AI agents, Claude Desktop, Cursor, and scripts.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="/docs.php" class="btn btn-sm btn-ghost" style="border-color:#38bdf8;color:#38bdf8">Public Documentation →</a>
    <a href="/mcp_setup.php" class="btn btn-sm btn-ghost">Interactive Client Setup</a>
  </div>
</div>

<?php
if ($new_key):
?>
<div class="card" style="border-color:#f59e0b;background:rgba(245,158,11,0.06)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
    <h2 style="color:#fbbf24;margin:0">Your New MCP / API Key</h2>
    <span style="font-size:.78rem;color:#34d399;font-weight:600">Saved to your account</span>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:10px">
    <input type="text" readonly value="<?= htmlspecialchars($new_key) ?>" style="font-family:monospace;margin-bottom:0;color:#93c5fd">
    <button class="btn btn-warning" onclick="copyToClipboard('<?= htmlspecialchars($new_key, ENT_QUOTES) ?>', this)">Copy Key</button>
  </div>
  <p style="font-size:.8rem;color:#94a3b8;margin:0">Authenticate API requests using: <span class="mono">Authorization: Bearer <?= htmlspecialchars($new_key) ?></span></p>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2>Generate MCP / API Key</h2>
    <p class="sub">Create an authentication token to control inboxes, fetch OTPs, and connect MCP servers.</p>
    <form method="post" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Generating...';b.disabled=true;}">
      <?php csrf_field(); ?>
      <input type="hidden" name="generate_key" value="1">
      <button type="submit" class="btn" style="width:100%">+ Generate New Secret Key</button>
    </form>
  </div>

  <div class="card">
    <h2>Active MCP / API Keys (<?= count($keys) ?>)</h2>
    <?php if (empty($keys)): ?>
      <p style="color:#94a3b8;font-size:.88rem">No active API keys. Click "+ Generate" to create one.</p>
    <?php else: ?>
      <?php foreach ($keys as $k): ?>
      <div class="mb" style="align-items:flex-start">
        <div style="flex:1;min-width:200px">
          <?php if (!empty($k['full_key'])): ?>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <span class="mono key-val" id="key-val-<?= $k['id'] ?>" data-full="<?= htmlspecialchars($k['full_key']) ?>" data-masked="<?= htmlspecialchars($k['key_prefix']) ?>••••••••••••••••" style="font-weight:700;color:#93c5fd">
                <?= htmlspecialchars($k['key_prefix']) ?>••••••••••••••••
              </span>
              <button type="button" class="btn-copy" style="padding:2px 8px;font-size:0.75rem" onclick="toggleKeyReveal(<?= $k['id'] ?>, this)">Show</button>
              <button type="button" class="btn-copy" style="padding:2px 8px;font-size:0.75rem" onclick="copyToClipboard('<?= htmlspecialchars($k['full_key'], ENT_QUOTES) ?>', this)">Copy</button>
            </div>
          <?php else: ?>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <span class="mono" style="font-weight:700;color:#64748b"><?= htmlspecialchars($k['key_prefix']) ?>••••••••••••••••</span>
              <span style="font-size:0.72rem;color:#f59e0b">(Legacy key — regenerate to reveal)</span>
            </div>
          <?php endif; ?>
          <div class="m" style="margin-top:4px">Created <?= htmlspecialchars($k['created_at']) ?> · Limit: <?= (int)$k['rate_limit'] ?> req/hr</div>
        </div>
        <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Revoke this key immediately? Any active agents using this key will lose access.')">
          <?php csrf_field(); ?>
          <input type="hidden" name="revoke_id" value="<?= (int)$k['id'] ?>">
          <button type="submit" class="btn-danger">Revoke</button>
        </form>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleKeyReveal(id, btn) {
  const el = document.getElementById('key-val-' + id);
  if (!el) return;
  const isMasked = el.textContent.trim().includes('•••');
  if (isMasked) {
    el.textContent = el.getAttribute('data-full');
    el.style.color = '#38bdf8';
    btn.textContent = 'Hide';
  } else {
    el.textContent = el.getAttribute('data-masked');
    el.style.color = '#93c5fd';
    btn.textContent = 'Show';
  }
}
</script>

<!-- Code Snippets Section -->
<div class="card" style="margin-top:20px">
  <h2 style="margin-bottom:4px">Ready-to-Use Code Snippets</h2>
  <p class="sub" style="margin-bottom:16px">Use your API key to automate email workflows with your favorite tools.</p>

	  <!-- Curl Example -->
	  <div style="margin-bottom:16px">
	    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
	      <span style="font-size:.84rem;font-weight:700;color:#cbd5e1">cURL (List Inboxes)</span>
	      <button class="btn-copy" onclick="copyToClipboard(document.getElementById('curl-code').innerText, this)">Copy cURL</button>
	    </div>
	    <div class="code" id="curl-code">curl -s -H "Authorization: Bearer <?= htmlspecialchars($active_key_for_snippets) ?>" \
	  <?= htmlspecialchars($app_url) ?>/api/inboxes</div>
	  </div>

	  <!-- Python Example -->
	  <div style="margin-bottom:16px">
	    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
	      <span style="font-size:.84rem;font-weight:700;color:#cbd5e1">Python (Fetch Latest OTP)</span>
	      <button class="btn-copy" onclick="copyToClipboard(document.getElementById('python-code').innerText, this)">Copy Python</button>
	    </div>
	    <div class="code" id="python-code">import urllib.request, json

API_KEY = "<?= htmlspecialchars($active_key_for_snippets) ?>"
BASE_URL = "<?= htmlspecialchars($app_url) ?>/api"

req = urllib.request.Request(f"{BASE_URL}/inboxes", headers={"Authorization": f"Bearer {API_KEY}"})
inboxes = json.loads(urllib.request.urlopen(req).read().decode())["inboxes"]
print("Active inboxes:", inboxes)</div>
	  </div>

	  <!-- MCP Quick Config -->
	  <div>
	    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
	      <span style="font-size:.84rem;font-weight:700;color:#cbd5e1">Claude Desktop / Cursor MCP Config</span>
	      <a href="/mcp_setup.php" style="font-size:.78rem;color:#60a5fa">Full MCP Guide →</a>
	    </div>
	    <div class="code" id="mcp-code">{
	  "mcpServers": {
	    "teak-email": {
	      "command": "npx",
	      "args": ["-y", "codeinbox-mcp"],
	      "env": {
	        "TEAK_API_KEY": "<?= htmlspecialchars($active_key_for_snippets) ?>"
	      }
	    }
	  }
	}</div>
        "CODEINBOX_API_KEY": "<?= htmlspecialchars($new_key ?? 'YOUR_API_KEY') ?>",
        "CODEINBOX_API_URL": "<?= htmlspecialchars($app_url) ?>/api"
      }
    }
  }
}</div>
  </div>

  <!-- Webhook Integration Card -->
  <div style="margin-top:20px;padding-top:16px;border-top:1px solid #1f2937">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
      <span style="font-size:.84rem;font-weight:700;color:#cbd5e1">Inbound Webhook JSON Spec (Real-Time Push)</span>
      <button class="btn-copy" onclick="copyToClipboard(document.getElementById('webhook-code').innerText, this)">Copy Payload Spec</button>
    </div>
    <p style="font-size:.8rem;color:#94a3b8;margin:0 0 8px">When a new email is received, our webhook engine pushes this standardized event to your agent endpoint:</p>
    <div class="code" id="webhook-code">{
  "event": "email.received",
  "inbox": "test@teak.email",
  "from": "Auth Service <security@app.com>",
  "subject": "Your verification code: 998877",
  "otp": "998877",
  "date": "2026-09-11T08:00:00Z",
  "text": "Your one-time code is 998877..."
}</div>
  </div>
</div>

<?php page_footer(); ?>
