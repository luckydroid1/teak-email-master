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
        $success = '🎉 New API key generated! Copy it now (it will not be shown again).';
    }
}
$keys = apikey_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('API Keys & Agent Integration', $user);
alert($error, $success);

if ($new_key):
?>
<div class="card" style="border-color:#f59e0b;background:rgba(245,158,11,0.06)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
    <h2 style="color:#fbbf24;margin:0">🔑 Your New API Key</h2>
    <span style="font-size:.78rem;color:#fca5a5;font-weight:600">⚠️ Shown once — Copy immediately</span>
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
    <h2>⚡ Generate API Key</h2>
    <p class="sub">Create an authentication token to control inboxes, fetch OTPs, and connect MCP servers.</p>
    <form method="post" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Generating...';b.disabled=true;}">
      <?php csrf_field(); ?>
      <input type="hidden" name="generate_key" value="1">
      <button type="submit" class="btn" style="width:100%">+ Generate New Secret Key</button>
    </form>
  </div>

  <div class="card">
    <h2>📋 Active API Keys (<?= count($keys) ?>)</h2>
    <?php if (empty($keys)): ?>
      <p style="color:#94a3b8;font-size:.88rem">No active API keys. Click "+ Generate" to create one.</p>
    <?php else: ?>
      <?php foreach ($keys as $k): ?>
      <div class="mb">
        <div>
          <div class="mono" style="font-weight:700"><?= htmlspecialchars($k['key_prefix']) ?>••••••••••••••••</div>
          <div class="m">Created <?= htmlspecialchars($k['created_at']) ?> · Rate limit: <?= (int)$k['rate_limit'] ?> req/hr</div>
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

<!-- Code Snippets Section -->
<div class="card" style="margin-top:20px">
  <h2 style="margin-bottom:4px">💻 Ready-to-Use Code Snippets</h2>
  <p class="sub" style="margin-bottom:16px">Use your API key to automate email workflows with your favorite tools.</p>

  <!-- Curl Example -->
  <div style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
      <span style="font-size:.84rem;font-weight:700;color:#cbd5e1">cURL (List Inboxes)</span>
      <button class="btn-copy" onclick="copyToClipboard(document.getElementById('curl-code').innerText, this)">Copy cURL</button>
    </div>
    <div class="code" id="curl-code">curl -s -H "Authorization: Bearer <?= htmlspecialchars($new_key ?? 'YOUR_API_KEY') ?>" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes</div>
  </div>

  <!-- Python Example -->
  <div style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
      <span style="font-size:.84rem;font-weight:700;color:#cbd5e1">Python (Fetch Latest OTP)</span>
      <button class="btn-copy" onclick="copyToClipboard(document.getElementById('python-code').innerText, this)">Copy Python</button>
    </div>
    <div class="code" id="python-code">import urllib.request, json

API_KEY = "<?= htmlspecialchars($new_key ?? 'YOUR_API_KEY') ?>"
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
      "args": ["-y", "@teak/mcp-server"],
      "env": {
        "TEAK_API_KEY": "<?= htmlspecialchars($new_key ?? 'YOUR_API_KEY') ?>"
      }
    }
  }
}</div>
  </div>
</div>

<?php page_footer(); ?>
