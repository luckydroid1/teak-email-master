<?php
/**
 * api_keys.php — generate & revoke API keys.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/apikey.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';
$new_key = null;

if (isset($_GET['revoke'])) {
    apikey_revoke($uid, (int)$_GET['revoke']);
    $success = 'Key revoked';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = apikey_create($uid);
    $new_key = $res['key'];
    $success = 'New API key created — copy it now, it won\'t be shown again';
}
$keys = apikey_list($uid);

require_once __DIR__ . '/_layout.php';
page_header('API Keys', $user);
alert($error, $success);

if ($new_key):
?>
<div class="card" style="border-color:#f59e0b">
  <h2>🔑 New API Key</h2>
  <label>Key (copy now)</label>
  <div class="code"><?= htmlspecialchars($new_key) ?></div>
  <p style="font-size:.8rem;color:#64748b">Use as: <span class="mono">Authorization: Bearer &lt;key&gt;</span></p>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <h2>Generate Key</h2>
    <p class="sub">Create a key for REST API + MCP access.</p>
    <form method="post"><button class="btn" style="width:100%">+ Generate Key</button></form>
  </div>
  <div class="card">
    <h2>My Keys</h2>
    <?php if (empty($keys)): ?>
      <p style="color:#64748b;font-size:.9rem">No active keys</p>
    <?php else: ?>
      <?php foreach ($keys as $k): ?>
      <div class="mb">
        <div>
          <div class="mono"><?= htmlspecialchars($k['key_prefix']) ?>…</div>
          <div class="m">Created <?= htmlspecialchars($k['created_at']) ?> · Rate <?= (int)$k['rate_limit'] ?>/h</div>
        </div>
        <a href="?revoke=<?= (int)$k['id'] ?>" class="btn-danger" onclick="return confirm('Revoke this key?')">Revoke</a>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- AI Agent Section -->
<div style="max-width:720px;margin:30px auto 0">
  <div style="text-align:center;margin-bottom:20px">
    <div style="font-size:2.2rem;margin-bottom:8px">🤖</div>
    <h2 style="font-size:1.3rem;color:#f1f5f9;margin-bottom:6px">Use Teak Email with an AI Agent</h2>
    <p class="sub" style="margin:0;max-width:500px;margin-left:auto;margin-right:auto">
      Teak Email gives you temporary email inboxes. Your AI agent can create inboxes, receive emails, and extract verification codes automatically.
    </p>
  </div>

  <!-- Two connection choices -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
    <div style="background:#111827;border:2px solid #3b82f6;border-radius:10px;padding:18px;text-align:center">
      <div style="font-size:1.4rem;margin-bottom:4px">🤖</div>
      <div style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:4px">MCP (Recommended)</div>
      <div style="font-size:.8rem;color:#94a3b8;margin-bottom:10px">For AI apps like Claude Desktop, Cursor, Windsurf. Paste a config, then talk in plain English.</div>
      <a href="/mcp_setup.php" style="display:inline-block;background:#3b82f6;color:#fff;padding:8px 20px;border-radius:6px;font-size:.85rem;font-weight:600;text-decoration:none">Setup Guide</a>
    </div>
    <div style="background:#111827;border:2px solid #8b5cf6;border-radius:10px;padding:18px;text-align:center">
      <div style="font-size:1.4rem;margin-bottom:4px">💻</div>
      <div style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:4px">REST API</div>
      <div style="font-size:.8rem;color:#94a3b8;margin-bottom:10px">For scripts, curl, Python, Node.js. Run commands with your API key as a Bearer token.</div>
      <a href="/mcp_setup.php#api" style="display:inline-block;background:#8b5cf6;color:#fff;padding:8px 20px;border-radius:6px;font-size:.85rem;font-weight:600;text-decoration:none">Setup Guide</a>
    </div>
  </div>

  <p style="text-align:center;font-size:.84rem;color:#94a3b8;margin-bottom:20px">
    <strong>Not sure?</strong> Choose MCP. See the full <a href="/mcp_setup.php" style="color:#60a5fa">Connect AI Agent</a> guide for step-by-step instructions for each app.
  </p>

  <!-- API key security notice -->
  <div class="card" style="background:#1c1917;border:1px solid #92400e">
    <div style="display:flex;align-items:flex-start;gap:10px">
      <div style="font-size:1.3rem;flex-shrink:0">🔑</div>
      <div>
        <h4 style="margin:0 0 4px;font-size:.9rem;color:#fbbf24">Keep your API key private</h4>
        <p style="margin:0;font-size:.84rem;color:#d6d3d1;line-height:1.6">
          Your API key is shown <strong>only once</strong> when you create it. We cannot show it again.<br>
          Do not share it in public repos, chat logs, or browser history.<br>
          If compromised, revoke it immediately and create a new one above.
        </p>
      </div>
    </div>
  </div>

  <!-- Tier 1 entitlements -->
  <div class="card" style="border-left:4px solid #f59e0b">
    <h3 style="margin-bottom:10px">Tier 1 — What you get</h3>
    <p style="font-size:.82rem;color:#94a3b8;margin-bottom:14px">This is the entry-level plan. All numbers below come directly from the system configuration.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
      <div style="background:#1e293b;padding:12px;border-radius:8px">
        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Credits</div>
        <div style="font-size:1.2rem;font-weight:700;color:#fbbf24">1,000</div>
        <div style="font-size:.76rem;color:#94a3b8">1 credit per email received</div>
      </div>
      <div style="background:#1e293b;padding:12px;border-radius:8px">
        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Inbox slots</div>
        <div style="font-size:1.2rem;font-weight:700;color:#fbbf24">1</div>
        <div style="font-size:.76rem;color:#94a3b8">One active inbox at a time</div>
      </div>
      <div style="background:#1e293b;padding:12px;border-radius:8px">
        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Domains</div>
        <div style="font-size:1.2rem;font-weight:700;color:#fbbf24">1</div>
        <div style="font-size:.76rem;color:#94a3b8">Choose from the pool below</div>
      </div>
      <div style="background:#1e293b;padding:12px;border-radius:8px">
        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Retention</div>
        <div style="font-size:1.2rem;font-weight:700;color:#fbbf24">7 days</div>
        <div style="font-size:.76rem;color:#94a3b8">Emails expire after 7 days</div>
      </div>
    </div>

    <div style="background:#1e293b;padding:14px;border-radius:8px;margin-bottom:12px">
      <h4 style="margin:0 0 8px;font-size:.88rem;color:#f1f5f9">Available domains</h4>
      <p style="font-size:.8rem;color:#94a3b8;margin:0 0 8px">Tier 1 can choose <strong>one</strong> domain. You can use any shared pool domain <em>or</em> a verified custom domain you own.</p>

      <div style="margin-bottom:8px">
        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Shared pool</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <span style="background:#14532d;border:1px solid #166534;color:#86efac;padding:4px 10px;border-radius:6px;font-size:.8rem;font-weight:500">toohumid.com</span>
          <span style="background:#14532d;border:1px solid #166534;color:#86efac;padding:4px 10px;border-radius:6px;font-size:.8rem;font-weight:500">jasa-seo.id</span>
          <span style="background:#14532d;border:1px solid #166534;color:#86efac;padding:4px 10px;border-radius:6px;font-size:.8rem;font-weight:500">jdp.industries</span>
        </div>
      </div>

      <div>
        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Your custom domains (if synced and verified)</div>
        <p style="font-size:.78rem;color:#94a3b8;margin:0">Higher tiers unlock more domains. Run <span class="mono">GET /api/domains</span> to see all domains eligible for your account.</p>
      </div>

      <p style="font-size:.78rem;color:#94a3b8;margin:10px 0 0">
        All pool domains have DKIM, SPF, and DMARC configured — emails land in the inbox, not spam.
        Custom domains must be verified (MX pointing to Teak or no conflicting email provider) before they can be used.
      </p>
    </div>

    <p style="font-size:.8rem;color:#64748b;margin:0">
      <strong>API level:</strong> Basic — list inboxes, create inbox, list emails, read email, extract OTP.
      All endpoints are available. Need more? Upgrade your tier from the dashboard.
    </p>
  </div>

  <!-- Error guidance -->
  <div class="card" style="border-left:4px solid #64748b">
    <h3 style="margin-bottom:14px">Common issues and how to fix them</h3>

    <details style="margin-bottom:8px">
      <summary style="cursor:pointer;padding:8px 0;font-weight:600;font-size:.88rem;color:#f1f5f9">I get "Unauthorized" (401)</summary>
      <div style="padding:8px 0;font-size:.85rem;color:#94a3b8;line-height:1.6">
        <p style="margin:0 0 6px">Your API key is missing or invalid. Make sure:</p>
        <ul style="margin:0;padding-left:20px">
          <li>You copied the full key (starts with <span class="mono">cib_</span>)</li>
          <li>The header is <span class="mono">Authorization: Bearer YOUR_API_KEY</span></li>
          <li>The key has not been revoked — check <a href="/api_keys.php" style="color:#3b82f6">My Keys</a> above</li>
        </ul>
        <p style="margin:8px 0 0">If you lost your key, generate a new one on this page.</p>
      </div>
    </details>

    <details style="margin-bottom:8px">
      <summary style="cursor:pointer;padding:8px 0;font-weight:600;font-size:.88rem;color:#f1f5f9">I get "Domain not available"</summary>
      <div style="padding:8px 0;font-size:.85rem;color:#94a3b8;line-height:1.6">
        <p style="margin:0 0 6px">The domain you requested is not eligible for inbox creation. This can happen if:</p>
        <ul style="margin:0 0 6px;padding-left:20px">
          <li>The domain is not in the shared pool or not a verified custom domain you own</li>
          <li>The domain has a conflicting email provider (e.g. Zoho, Google) and cannot be used with Teak</li>
        </ul>
        <p style="margin:0 0 6px">Use a domain from the <strong>Available domains</strong> section above, or run the "List available domains" curl command to see all eligible domains for your account.</p>
        <p style="margin:0">To use your own domain, sync it via <span class="mono">POST /api/domains</span> (server must have Spaceship credentials configured).</p>
      </div>
    </details>

    <details style="margin-bottom:8px">
      <summary style="cursor:pointer;padding:8px 0;font-weight:600;font-size:.88rem;color:#f1f5f9">I get "Inbox slot limit reached"</summary>
      <div style="padding:8px 0;font-size:.85rem;color:#94a3b8;line-height:1.6">
        <p style="margin:0 0 6px">You only have 1 inbox slot on Tier 1. Delete an old inbox first:</p>
        <pre style="background:#0f172a;border:1px solid #334155;padding:8px 12px;border-radius:6px;font-size:.78rem;color:#93c5fd;overflow-x:auto;margin:0 0 6px">curl -X DELETE -H "Authorization: Bearer YOUR_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes/my-inbox@toohumid.com</pre>
        <p style="margin:0">Or upgrade your tier for more inbox slots.</p>
      </div>
    </details>

    <details style="margin-bottom:8px">
      <summary style="cursor:pointer;padding:8px 0;font-weight:600;font-size:.88rem;color:#f1f5f9">My inbox has no emails yet</summary>
      <div style="padding:8px 0;font-size:.85rem;color:#94a3b8;line-height:1.6">
        <p style="margin:0 0 6px">Make sure you sent a verification email to your new inbox address (e.g. <span class="mono">my-inbox@toohumid.com</span>). The inbox must be active and the sending service must accept mail from your domain.</p>
        <p style="margin:0">Check again in 30 seconds — sometimes delivery takes a moment.</p>
      </div>
    </details>

    <details style="margin-bottom:0">
      <summary style="cursor:pointer;padding:8px 0;font-weight:600;font-size:.88rem;color:#f1f5f9">I get "Not enough credits"</summary>
      <div style="padding:8px 0;font-size:.85rem;color:#94a3b8;line-height:1.6">
        <p style="margin:0 0 6px">Each email read costs 1 credit. Tier 1 includes 1,000 credits. Check your balance:</p>
        <pre style="background:#0f172a;border:1px solid #334155;padding:8px 12px;border-radius:6px;font-size:.78rem;color:#93c5fd;overflow-x:auto;margin:0">curl -s -H "Authorization: Bearer YOUR_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/balance</pre>
      </div>
    </details>
  </div>

  <!-- Helpful links -->
  <div style="text-align:center;margin-top:20px;margin-bottom:20px">
    <p style="font-size:.84rem;color:#64748b">
      New here? Start with <a href="/getting-started.php" style="color:#3b82f6">Getting Started</a>.
      Need a full inbox? Go to <a href="/inboxes.php" style="color:#3b82f6">My Inboxes</a>.
      Questions? <a href="mailto:support@teak.email" style="color:#3b82f6">support@teak.email</a>
    </p>
  </div>
</div>

<?php page_footer(); ?>
