<?php
/**
 * mcp_setup.php — Modern Interactive Setup for AI Agents & MCP.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/apikey.php';

$user = require_login();
$uid = (int)$user['id'];
$keys = apikey_list($uid);
$app_url = cfg()['app_url'] ?? 'https://teak.email';

require_once __DIR__ . '/_layout.php';
page_header('Connect AI Agents & MCP', $user);
?>

<style>
.client-tab {
  padding: 10px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.88rem;
  border: 1px solid #374151;
  background: #0f172a;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  align-items: center;
  gap: 8px;
}
.client-tab.active {
  background: #2563eb;
  color: #fff;
  border-color: #3b82f6;
}
.client-pane { display: none; }
.client-pane.active { display: block; }
</style>

<div style="max-width:800px;margin:10px auto 40px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px">🤖 Connect AI Agents (MCP Server)</h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">Give Claude Desktop, Cursor, or your autonomous agents tools to manage inboxes and grab OTP codes.</p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/api_keys.php" class="btn btn-sm btn-ghost">🔑 Manage API Keys</a>
      <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Dashboard</a>
    </div>
  </div>

  <?php if (empty($keys)): ?>
  <div class="card" style="border-left:4px solid #f59e0b;background:rgba(245,158,11,0.08);padding:24px;margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
      <div>
        <h3 style="margin:0 0 4px;font-size:1.05rem;color:#fde68a">🔑 API Key Required for MCP</h3>
        <p style="margin:0;font-size:0.85rem;color:#cbd5e1">Generate your secret API key to populate all configuration blocks automatically.</p>
      </div>
      <a href="/api_keys.php" class="btn btn-sm btn-warning">Generate Key Now →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- AI Client Selector Tabs -->
  <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <button type="button" class="client-tab active" id="tab-claude" onclick="switchClientTab('claude')">
      <span>🟠</span> Claude Desktop
    </button>
    <button type="button" class="client-tab" id="tab-cursor" onclick="switchClientTab('cursor')">
      <span>⚡</span> Cursor AI
    </button>
    <button type="button" class="client-tab" id="tab-windsurf" onclick="switchClientTab('windsurf')">
      <span>🌊</span> Windsurf / Others
    </button>
    <button type="button" class="client-tab" id="tab-curl" onclick="switchClientTab('curl')">
      <span>💻</span> REST API (cURL / Python)
    </button>
  </div>

  <!-- Pane 1: Claude Desktop -->
  <div id="pane-claude" class="client-pane active card" style="border-left:4px solid #f59e0b">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:1.1rem;color:#fde68a">Claude Desktop MCP Configuration</h3>
      <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('claude-cfg-json').innerText, this)">📋 Copy Config JSON</button>
    </div>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:12px">
      Add this to your <span style="font-family:monospace;color:#60a5fa">claude_desktop_config.json</span>:
    </p>

    <div class="code" id="claude-cfg-json">{
  "mcpServers": {
    "teak-email": {
      "command": "npx",
      "args": ["-y", "codeinbox-mcp"],
      "env": {
        "CODEINBOX_API_KEY": "<?= !empty($keys) ? htmlspecialchars($keys[0]['key_prefix']) . '••••••••••••••••' : 'YOUR_API_KEY' ?>",
        "CODEINBOX_API_URL": "<?= htmlspecialchars($app_url) ?>/api"
      }
    }
  }
}</div>

    <div style="background:#0a0e1a;padding:14px;border-radius:8px;border:1px solid #1f2937;margin-top:14px;font-size:0.84rem;color:#cbd5e1">
      <strong style="color:#f8fafc">Where to paste on your computer:</strong>
      <ul style="margin:6px 0 0;padding-left:20px;line-height:1.7;color:#94a3b8">
        <li><strong>macOS:</strong> <span style="font-family:monospace;color:#93c5fd">~/Library/Application Support/Claude/claude_desktop_config.json</span></li>
        <li><strong>Windows:</strong> <span style="font-family:monospace;color:#93c5fd">%APPDATA%\Claude\claude_desktop_config.json</span></li>
      </ul>
    </div>
  </div>

  <!-- Pane 2: Cursor AI -->
  <div id="pane-cursor" class="client-pane card" style="border-left:4px solid #10b981">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:1.1rem;color:#86efac">Cursor MCP Server Setup</h3>
      <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('cursor-cfg-json').innerText, this)">📋 Copy Config JSON</button>
    </div>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:12px">
      Open Cursor <strong>Settings ➔ MCP ➔ Add New MCP Server</strong>:
    </p>

    <div class="code" id="cursor-cfg-json">{
  "mcpServers": {
    "teak-email": {
      "command": "npx",
      "args": ["-y", "codeinbox-mcp"],
      "env": {
        "CODEINBOX_API_KEY": "YOUR_API_KEY",
        "CODEINBOX_API_URL": "<?= htmlspecialchars($app_url) ?>/api"
      }
    }
  }
}</div>
  </div>

  <!-- Pane 3: Windsurf / Others -->
  <div id="pane-windsurf" class="client-pane card" style="border-left:4px solid #8b5cf6">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:1.1rem;color:#c4b5fd">Generic MCP Client</h3>
      <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('generic-cfg-json').innerText, this)">📋 Copy Config JSON</button>
    </div>
    <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:12px">
      Standard Model Context Protocol package for any compatible IDE or terminal agent:
    </p>

    <div class="code" id="generic-cfg-json">{
  "mcpServers": {
    "teak-email": {
      "command": "npx",
      "args": ["-y", "codeinbox-mcp"],
      "env": {
        "CODEINBOX_API_KEY": "YOUR_API_KEY",
        "CODEINBOX_API_URL": "<?= htmlspecialchars($app_url) ?>/api"
      }
    }
  }
}</div>
  </div>

  <!-- Pane 4: REST API (cURL / Python) -->
  <div id="pane-curl" class="client-pane card" style="border-left:4px solid #3b82f6">
    <h3 style="margin:0 0 12px;font-size:1.1rem;color:#93c5fd">Direct REST API Examples</h3>

    <div style="margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <span style="font-size:0.85rem;font-weight:700;color:#e2e8f0">1. List Active Inboxes (cURL)</span>
        <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('code-curl-list').innerText, this)">📋 Copy</button>
      </div>
      <div class="code" id="code-curl-list">curl -s -H "Authorization: Bearer YOUR_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes</div>
    </div>

    <div style="margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <span style="font-size:0.85rem;font-weight:700;color:#e2e8f0">2. Create New Inbox (cURL)</span>
        <button type="button" class="btn-copy" onclick="copyToClipboard(document.getElementById('code-curl-create').innerText, this)">📋 Copy</button>
      </div>
      <div class="code" id="code-curl-create">curl -s -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"toohumid.com","local_part":"agentbot01"}' \
  <?= htmlspecialchars($app_url) ?>/api/inboxes</div>
    </div>
  </div>

  <!-- Natural Language Prompts Examples -->
  <div class="card" style="margin-top:20px;background:linear-gradient(180deg,#111827 0%,#0f172a 100%)">
    <h3 style="margin:0 0 12px;font-size:1.05rem;color:#f8fafc">💬 What you can ask your AI Agent once connected:</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div style="background:#0a0e1a;padding:12px;border-radius:8px;border:1px solid #1f2937;font-size:0.84rem;color:#cbd5e1">
        "Create a new inbox on @toohumid.com for my GitHub registration."
      </div>
      <div style="background:#0a0e1a;padding:12px;border-radius:8px;border:1px solid #1f2937;font-size:0.84rem;color:#cbd5e1">
        "Check my latest email and extract the 6-digit OTP verification code."
      </div>
    </div>
  </div>

</div>

<script>
function switchClientTab(target) {
  document.querySelectorAll('.client-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.client-pane').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + target)?.classList.add('active');
  document.getElementById('pane-' + target)?.classList.add('active');
}
</script>

<?php page_footer(); ?>
