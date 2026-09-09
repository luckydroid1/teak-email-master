<?php
/**
 * mcp_setup.php — Connect your AI agent to Teak Email.
 * Beginner-friendly documentation for MCP and REST API setup.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/apikey.php';

$user = require_login();
$uid = (int)$user['id'];
$keys = apikey_list($uid);
$app_url = cfg()['app_url'];

require_once __DIR__ . '/_layout.php';
page_header('Connect AI Agent', $user);
?>
<div style="max-width:720px;margin:30px auto;padding:0 16px">

  <!-- Hero -->
  <div style="text-align:center;margin-bottom:32px">
    <div style="font-size:2.4rem;margin-bottom:8px">📬</div>
    <h1 style="font-size:1.6rem;color:#f1f5f9;margin-bottom:8px">Connect Your AI Agent to Teak Email</h1>
    <p style="font-size:.92rem;color:#94a3b8;max-width:520px;margin:0 auto;line-height:1.7">
      <strong>Teak Email</strong> gives you temporary email inboxes. Your AI agent can create inboxes, receive emails, and extract verification codes automatically.
      <br><br>
      There are two ways to connect: <strong>MCP</strong> (for AI apps) or <strong>REST API</strong> (for scripts and custom code).
    </p>
  </div>

  <?php if (empty($keys)): ?>
  <!-- No API key yet -->
  <div class="card" style="border:1px solid #f59e0b;background:#1c1917;text-align:center;padding:28px">
    <div style="font-size:1.8rem;margin-bottom:10px">🔑</div>
    <h2 style="font-size:1.1rem;color:#fbbf24;margin-bottom:8px">You need an API key first</h2>
    <p style="font-size:.88rem;color:#94a3b8;margin-bottom:16px;max-width:400px;margin-left:auto;margin-right:auto">
      An API key is a secret code that lets your AI agent talk to Teak Email. You create it once, copy it, and use it in your app settings.
    </p>
    <a href="/api_keys.php" class="btn" style="text-decoration:none;background:#f59e0b;color:#000;padding:10px 24px;font-size:.9rem">Generate API Key</a>
  </div>

  <?php else: ?>

  <!-- Recommendation Card -->
  <div style="background:linear-gradient(135deg,#1e3a5f 0%,#1a2744 100%);border:2px solid #3b82f6;border-radius:12px;padding:24px;margin-bottom:28px;text-align:center">
    <div style="font-size:1.1rem;font-weight:700;color:#60a5fa;margin-bottom:6px">Not sure which to choose?</div>
    <div style="font-size:1.5rem;font-weight:800;color:#f1f5f9;margin-bottom:8px">Choose MCP</div>
    <p style="font-size:.88rem;color:#94a3b8;margin:0;max-width:480px;margin-left:auto;margin-right:auto;line-height:1.6">
      MCP is the easiest option. You paste a short config into your AI app (like Claude Desktop or Cursor), and the agent handles everything automatically. No coding required.
    </p>
  </div>

  <!-- Two paths: MCP vs API -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:28px">
    <a href="#mcp" style="display:block;background:#111827;border:2px solid #3b82f6;border-radius:10px;padding:20px;text-decoration:none;text-align:center;transition:border-color .2s" onmouseover="this.style.borderColor='#60a5fa'" onmouseout="this.style.borderColor='#3b82f6'">
      <div style="font-size:1.6rem;margin-bottom:6px">🤖</div>
      <div style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:4px">AI App (MCP)</div>
      <div style="font-size:.8rem;color:#94a3b8">For Claude Desktop, Cursor, Windsurf, and similar AI tools</div>
    </a>
    <a href="#api" style="display:block;background:#111827;border:2px solid #8b5cf6;border-radius:10px;padding:20px;text-decoration:none;text-align:center;transition:border-color .2s" onmouseover="this.style.borderColor='#a78bfa'" onmouseout="this.style.borderColor='#8b5cf6'">
      <div style="font-size:1.6rem;margin-bottom:6px">💻</div>
      <div style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:4px">Script (API)</div>
      <div style="font-size:.8rem;color:#94a3b8">For curl, Python, Node.js, or custom code</div>
    </a>
  </div>

  <!-- ============================================ -->
  <!-- SECTION: MCP (AI App)                        -->
  <!-- ============================================ -->
  <div id="mcp" style="margin-bottom:36px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <div style="background:#3b82f6;color:#fff;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem">MCP</div>
      <h2 style="font-size:1.2rem;color:#f1f5f9;margin:0">AI App Setup (MCP)</h2>
    </div>

    <p style="font-size:.88rem;color:#94a3b8;margin-bottom:18px;line-height:1.6">
      <strong>MCP</strong> (Model Context Protocol) is a standard way for AI apps to connect to external tools. Once configured, you just tell your agent what to do in plain English, and it calls the Teak Email API automatically.
    </p>

    <!-- Claude Desktop -->
    <div class="card" style="border-left:4px solid #f59e0b;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="background:#f59e0b;color:#000;width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">1</div>
        <h3 style="margin:0;font-size:1rem;color:#f1f5f9">Claude Desktop</h3>
      </div>

      <p style="font-size:.84rem;color:#94a3b8;margin-bottom:10px">Open Claude Desktop settings and find the MCP configuration:</p>

      <div style="background:#1e293b;border-radius:8px;padding:12px 14px;margin-bottom:10px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Where to find it:</div>
        <div style="font-size:.85rem;color:#e2e8f0">
          <strong>macOS:</strong> Claude Desktop menu bar &rarr; Settings &rarr; Developer &rarr; Edit Config<br>
          <strong>Windows:</strong> Claude Desktop system tray &rarr; Settings &rarr; Developer &rarr; Edit Config
        </div>
        <div style="font-size:.78rem;color:#64748b;margin-top:8px">
          This opens <span class="mono" style="color:#93c5fd">claude_desktop_config.json</span>. Add the section below.
        </div>
      </div>

      <div style="position:relative;margin-bottom:10px">
        <div class="code" id="claude-config" style="font-size:.78rem;line-height:1.5;padding:14px;padding-right:50px;white-space:pre;overflow-x:auto">{
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
        <button onclick="navigator.clipboard.writeText(document.getElementById('claude-config').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:8px;right:8px;background:#334155;border:none;color:#94a3b8;padding:6px 12px;border-radius:6px;font-size:.75rem;cursor:pointer">Copy</button>
      </div>

      <div style="background:#1e293b;border-radius:8px;padding:12px 14px;margin-bottom:10px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Steps:</div>
        <ol style="margin:0;padding-left:20px;font-size:.84rem;color:#94a3b8;line-height:1.8">
          <li>Replace <span class="mono" style="color:#fbbf24">YOUR_API_KEY</span> with your actual key (starts with <span class="mono" style="color:#93c5fd">cib_</span>)</li>
          <li>Save the file</li>
          <li><strong>Quit and reopen</strong> Claude Desktop (MCP only loads on startup)</li>
          <li>Start a new chat and try the test prompt below</li>
        </ol>
      </div>

      <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px 14px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Test prompt (paste into Claude):</div>
        <div style="font-size:.88rem;color:#e2e8f0;font-style:italic">
          "List my available domains on Teak Email"
        </div>
      </div>
    </div>

    <!-- Cursor -->
    <div class="card" style="border-left:4px solid #10b981;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="background:#10b981;color:#fff;width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">2</div>
        <h3 style="margin:0;font-size:1rem;color:#f1f5f9">Cursor</h3>
      </div>

      <p style="font-size:.84rem;color:#94a3b8;margin-bottom:10px">Open Cursor settings and navigate to MCP:</p>

      <div style="background:#1e293b;border-radius:8px;padding:12px 14px;margin-bottom:10px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Where to find it:</div>
        <div style="font-size:.85rem;color:#e2e8f0">
          <strong>Settings &rarr; MCP</strong> (or press <span class="mono" style="color:#93c5fd">Ctrl+Shift+J</span> and search "MCP")
        </div>
        <div style="font-size:.78rem;color:#94a3b8;margin-top:6px;font-style:italic">
          Note: Cursor's UI changes frequently. The exact label may say "MCP Servers", "Tools & MCP", or similar.
        </div>
      </div>

      <div style="position:relative;margin-bottom:10px">
        <div class="code" id="cursor-config" style="font-size:.78rem;line-height:1.5;padding:14px;padding-right:50px;white-space:pre;overflow-x:auto">{
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
        <button onclick="navigator.clipboard.writeText(document.getElementById('cursor-config').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:8px;right:8px;background:#334155;border:none;color:#94a3b8;padding:6px 12px;border-radius:6px;font-size:.75rem;cursor:pointer">Copy</button>
      </div>

      <div style="background:#1e293b;border-radius:8px;padding:12px 14px;margin-bottom:10px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Steps:</div>
        <ol style="margin:0;padding-left:20px;font-size:.84rem;color:#94a3b8;line-height:1.8">
          <li>Click <strong>"Add new MCP server"</strong> or paste into the JSON config</li>
          <li>Replace <span class="mono" style="color:#fbbf24">YOUR_API_KEY</span> with your actual key</li>
          <li>Save and make sure the server shows as <span style="color:#10b981;font-weight:600">active</span> (green dot)</li>
          <li>Open Composer (Ctrl+I) and try the test prompt</li>
        </ol>
      </div>

      <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px 14px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Test prompt (paste into Cursor Composer):</div>
        <div style="font-size:.88rem;color:#e2e8f0;font-style:italic">
          "List my available domains on Teak Email"
        </div>
      </div>
    </div>

    <!-- Windsurf / Other -->
    <div class="card" style="border-left:4px solid #8b5cf6;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="background:#8b5cf6;color:#fff;width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">3</div>
        <h3 style="margin:0;font-size:1rem;color:#f1f5f9">Windsurf / Other MCP Clients</h3>
      </div>

      <p style="font-size:.84rem;color:#94a3b8;margin-bottom:10px">
        Any app that supports MCP can connect. Look for an "MCP" or "MCP Servers" section in your app's settings, and paste this config:
      </p>

      <div style="position:relative;margin-bottom:10px">
        <div class="code" id="generic-config" style="font-size:.78rem;line-height:1.5;padding:14px;padding-right:50px;white-space:pre;overflow-x:auto">{
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
        <button onclick="navigator.clipboard.writeText(document.getElementById('generic-config').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:8px;right:8px;background:#334155;border:none;color:#94a3b8;padding:6px 12px;border-radius:6px;font-size:.75rem;cursor:pointer">Copy</button>
      </div>

      <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px 14px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">Test prompt:</div>
        <div style="font-size:.88rem;color:#e2e8f0;font-style:italic">
          "List my available domains on Teak Email"
        </div>
      </div>
    </div>

    <!-- ZCode -->
    <div class="card" style="border-left:4px solid #64748b;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="background:#64748b;color:#fff;width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">4</div>
        <h3 style="margin:0;font-size:1rem;color:#f1f5f9">ZCode (This Environment)</h3>
      </div>

      <p style="font-size:.84rem;color:#94a3b8;margin-bottom:10px;line-height:1.6">
        ZCode is the coding environment you are using right now. It <strong>cannot automatically inherit</strong> your browser's API key. You have two options:
      </p>

      <div style="background:#1e293b;border-radius:8px;padding:12px 14px;margin-bottom:10px">
        <div style="font-size:.82rem;color:#fbbf24;font-weight:600;margin-bottom:6px">Option A: Set environment variable</div>
        <p style="font-size:.82rem;color:#94a3b8;margin:0 0 8px">Set the key in your terminal before using ZCode commands:</p>
        <div style="position:relative">
          <div class="code" id="zcode-env" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">export TEAK_EMAIL_API_KEY="YOUR_API_KEY"</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('zcode-env').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
      </div>

      <div style="background:#1e293b;border-radius:8px;padding:12px 14px;margin-bottom:10px">
        <div style="font-size:.82rem;color:#fbbf24;font-weight:600;margin-bottom:6px">Option B: Configure MCP client</div>
        <p style="font-size:.82rem;color:#94a3b8;margin:0 0 8px">If you are using ZCode with an MCP-compatible agent (like Cursor or Claude), configure the MCP client using the JSON config above (sections 1-3).</p>
      </div>

      <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px 14px">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:6px">ZCode skill command:</div>
        <div style="font-size:.82rem;color:#93c5fd;font-family:monospace">/teak-email</div>
        <div style="font-size:.78rem;color:#94a3b8;margin-top:6px">
          This loads the built-in Teak Email skill. You still need the <span class="mono" style="color:#93c5fd">TEAK_EMAIL_API_KEY</span> environment variable set.
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================ -->
  <!-- SECTION: REST API (Script)                   -->
  <!-- ============================================ -->
  <div id="api" style="margin-bottom:36px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <div style="background:#8b5cf6;color:#fff;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem">API</div>
      <h2 style="font-size:1.2rem;color:#f1f5f9;margin:0">Script Setup (REST API)</h2>
    </div>

    <!-- What is an API key -->
    <div class="card" style="border-left:4px solid #f59e0b;margin-bottom:16px">
      <h3 style="font-size:1rem;color:#f1f5f9;margin-bottom:10px">What is an API key?</h3>
      <p style="font-size:.85rem;color:#94a3b8;line-height:1.7;margin-bottom:10px">
        An API key is a secret code that proves your identity when your script calls the Teak Email API. Think of it like a password, but specifically for your code (not for logging into the website).
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div style="background:#14532d;border:1px solid #166534;border-radius:8px;padding:12px">
          <div style="font-size:.78rem;color:#86efac;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">API Key</div>
          <div style="font-size:.82rem;color:#d1d5db;line-height:1.5">
            Used by your code to call the API.<br>
            Starts with <span class="mono" style="color:#fbbf24">cib_</span>.<br>
            Created in <a href="/api_keys.php" style="color:#60a5fa">API Keys</a> tab.
          </div>
        </div>
        <div style="background:#1e293b;border:1px solid #334155;border-radius:8px;padding:12px">
          <div style="font-size:.78rem;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Inbox Password</div>
          <div style="font-size:.82rem;color:#d1d5db;line-height:1.5">
            Used by IMAP/POP3 clients to read mail.<br>
            Random string returned when creating an inbox.<br>
            Different from your API key.
          </div>
        </div>
      </div>
      <p style="font-size:.8rem;color:#92400e;margin:12px 0 0;line-height:1.5">
        <strong>Important:</strong> Your API key is shown <strong>only once</strong> when you create it. Copy it immediately. If you lose it, generate a new one at <a href="/api_keys.php" style="color:#60a5fa">API Keys</a>.
      </p>
    </div>

    <!-- Complete copy-paste flow -->
    <div class="card" style="border-left:4px solid #10b981;margin-bottom:16px">
      <h3 style="font-size:1rem;color:#f1f5f9;margin-bottom:6px">Complete Flow: Create, Read, Extract OTP</h3>
      <p style="font-size:.82rem;color:#64748b;margin-bottom:14px">
        Copy and paste each command. Replace <span class="mono" style="color:#fbbf24">YOUR_API_KEY</span> with your actual key.
        We use <span class="mono" style="color:#86efac">toohumid.com</span> (a shared pool domain) in all examples.
      </p>

      <!-- Step 1: Set API key -->
      <div style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <div style="background:#3b82f6;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">1</div>
          <span style="font-size:.88rem;font-weight:600;color:#f1f5f9">Set your API key</span>
        </div>
        <div style="position:relative">
          <div class="code" id="flow-setkey" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">export TEAK_EMAIL_API_KEY="YOUR_API_KEY"</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('flow-setkey').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
      </div>

      <!-- Step 2: List domains -->
      <div style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <div style="background:#3b82f6;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">2</div>
          <span style="font-size:.88rem;font-weight:600;color:#f1f5f9">List available domains</span>
        </div>
        <div style="position:relative">
          <div class="code" id="flow-domains" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/domains | python3 -m json.tool</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('flow-domains').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
      </div>

      <!-- Step 3: Create inbox -->
      <div style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <div style="background:#3b82f6;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">3</div>
          <span style="font-size:.88rem;font-weight:600;color:#f1f5f9">Create an inbox</span>
        </div>
        <div style="position:relative">
          <div class="code" id="flow-create" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">curl -s -X POST \
  -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"domain":"toohumid.com","local_part":"my-inbox"}' \
  <?= htmlspecialchars($app_url) ?>/api/inboxes</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('flow-create').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
        <div style="font-size:.78rem;color:#64748b;margin-top:4px">
          Returns: <span class="mono" style="color:#93c5fd">{"ok":true,"email":"my-inbox@toohumid.com","password":"..."}</span>
        </div>
      </div>

      <!-- Step 4: List emails -->
      <div style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <div style="background:#3b82f6;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">4</div>
          <span style="font-size:.88rem;font-weight:600;color:#f1f5f9">Check for emails</span>
        </div>
        <div style="position:relative">
          <div class="code" id="flow-list" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes/my-inbox@toohumid.com/emails</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('flow-list').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
      </div>

      <!-- Step 5: Extract OTP -->
      <div style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <div style="background:#3b82f6;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">5</div>
          <span style="font-size:.88rem;font-weight:600;color:#f1f5f9">Extract the OTP code</span>
        </div>
        <div style="position:relative">
          <div class="code" id="flow-otp" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">curl -s -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes/my-inbox@toohumid.com/otp/1</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('flow-otp').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
        <div style="font-size:.78rem;color:#64748b;margin-top:4px">
          Returns: <span class="mono" style="color:#93c5fd">{"ok":true,"otp":"123456","text":"Your verification code is: 123456..."}</span>
        </div>
      </div>

      <!-- Step 6: Clean up -->
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <div style="background:#3b82f6;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0">6</div>
          <span style="font-size:.88rem;font-weight:600;color:#f1f5f9">Delete the inbox when done</span>
        </div>
        <div style="position:relative">
          <div class="code" id="flow-delete" style="font-size:.78rem;line-height:1.5;padding:10px;padding-right:50px;white-space:pre;overflow-x:auto">curl -s -X DELETE \
  -H "Authorization: Bearer $TEAK_EMAIL_API_KEY" \
  <?= htmlspecialchars($app_url) ?>/api/inboxes/my-inbox@toohumid.com</div>
          <button onclick="navigator.clipboard.writeText(document.getElementById('flow-delete').textContent);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)" style="position:absolute;top:6px;right:6px;background:#334155;border:none;color:#94a3b8;padding:4px 8px;border-radius:4px;font-size:.7rem;cursor:pointer">Copy</button>
        </div>
      </div>
    </div>

    <!-- Available domains note -->
    <div class="card" style="border-left:4px solid #64748b;margin-bottom:16px">
      <h3 style="font-size:.95rem;color:#f1f5f9;margin-bottom:8px">Available Domains</h3>
      <p style="font-size:.84rem;color:#94a3b8;line-height:1.6;margin-bottom:10px">
        Tier 1 users can create inboxes on these <strong>shared pool domains</strong>:
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
        <span style="background:#14532d;border:1px solid #166534;color:#86efac;padding:4px 12px;border-radius:6px;font-size:.82rem;font-weight:500">toohumid.com</span>
        <span style="background:#14532d;border:1px solid #166534;color:#86efac;padding:4px 12px;border-radius:6px;font-size:.82rem;font-weight:500">jasa-seo.id</span>
        <span style="background:#14532d;border:1px solid #166534;color:#86efac;padding:4px 12px;border-radius:6px;font-size:.82rem;font-weight:500">jdp.industries</span>
      </div>
      <p style="font-size:.8rem;color:#64748b;margin:0;line-height:1.5">
        If you own a custom domain and it is synced and verified with Teak Email, it will also appear in your eligible domains.
        Run <span class="mono" style="color:#93c5fd">GET /api/domains</span> to see the full list for your account.
        Custom domains require MX records pointing to Teak Email's mail server.
      </p>
    </div>
  </div>

  <!-- ============================================ -->
  <!-- TROUBLESHOOTING                              -->
  <!-- ============================================ -->
  <div style="margin-bottom:28px">
    <h2 style="font-size:1.1rem;color:#f1f5f9;margin-bottom:14px">Troubleshooting</h2>

    <details style="margin-bottom:8px;background:#111827;border:1px solid #1f2937;border-radius:8px">
      <summary style="cursor:pointer;padding:12px 14px;font-weight:600;font-size:.88rem;color:#f1f5f9">
        "Agent says it cannot connect"
      </summary>
      <div style="padding:0 14px 14px;font-size:.84rem;color:#94a3b8;line-height:1.7">
        <p style="margin:0 0 8px">This usually means the MCP server is not running or the config is wrong.</p>
        <ul style="margin:0;padding-left:20px">
          <li>Make sure <span class="mono" style="color:#93c5fd">npx</span> is installed (run <span class="mono" style="color:#93c5fd">npx --version</span> in your terminal)</li>
          <li>Make sure you replaced <span class="mono" style="color:#fbbf24">YOUR_API_KEY</span> with your actual key</li>
          <li>Restart your AI app after changing the config (Claude Desktop, Cursor, etc.)</li>
          <li>Check that the MCP server shows as active in your app's settings</li>
        </ul>
      </div>
    </details>

    <details style="margin-bottom:8px;background:#111827;border:1px solid #1f2937;border-radius:8px">
      <summary style="cursor:pointer;padding:12px 14px;font-weight:600;font-size:.88rem;color:#f1f5f9">
        "401 Unauthorized"
      </summary>
      <div style="padding:0 14px 14px;font-size:.84rem;color:#94a3b8;line-height:1.7">
        <p style="margin:0 0 8px">Your API key is missing or invalid.</p>
        <ul style="margin:0;padding-left:20px">
          <li>Check that the key starts with <span class="mono" style="color:#93c5fd">cib_</span></li>
          <li>Make sure the header is <span class="mono" style="color:#93c5fd">Authorization: Bearer YOUR_API_KEY</span></li>
          <li>Check if the key has been revoked &mdash; go to <a href="/api_keys.php" style="color:#60a5fa">API Keys</a></li>
          <li>If you lost your key, generate a new one</li>
        </ul>
      </div>
    </details>

    <details style="margin-bottom:8px;background:#111827;border:1px solid #1f2937;border-radius:8px">
      <summary style="cursor:pointer;padding:12px 14px;font-weight:600;font-size:.88rem;color:#f1f5f9">
        "Domain not available"
      </summary>
      <div style="padding:0 14px 14px;font-size:.84rem;color:#94a3b8;line-height:1.7">
        <p style="margin:0 0 8px">The domain you requested is not eligible for inbox creation.</p>
        <ul style="margin:0;padding-left:20px">
          <li>Use a pool domain: <span class="mono" style="color:#86efac">toohumid.com</span>, <span class="mono" style="color:#86efac">jasa-seo.id</span>, or <span class="mono" style="color:#86efac">jdp.industries</span></li>
          <li>Custom domains must be synced and verified before use</li>
          <li>Run <span class="mono" style="color:#93c5fd">GET /api/domains</span> to see all eligible domains for your account</li>
        </ul>
      </div>
    </details>

    <details style="margin-bottom:8px;background:#111827;border:1px solid #1f2937;border-radius:8px">
      <summary style="cursor:pointer;padding:12px 14px;font-weight:600;font-size:.88rem;color:#f1f5f9">
        "No emails yet"
      </summary>
      <div style="padding:0 14px 14px;font-size:.84rem;color:#94a3b8;line-height:1.7">
        <p style="margin:0 0 8px">Your inbox is empty. Make sure:</p>
        <ul style="margin:0;padding-left:20px">
          <li>You sent a verification email to your new inbox address (e.g. <span class="mono" style="color:#93c5fd">my-inbox@toohumid.com</span>)</li>
          <li>The sending service accepted the email (check for bounce messages)</li>
          <li>Wait 30 seconds and check again &mdash; delivery can take a moment</li>
        </ul>
      </div>
    </details>

    <details style="margin-bottom:8px;background:#111827;border:1px solid #1f2937;border-radius:8px">
      <summary style="cursor:pointer;padding:12px 14px;font-weight:600;font-size:.88rem;color:#f1f5f9">
        "MCP server not found"
      </summary>
      <div style="padding:0 14px 14px;font-size:.84rem;color:#94a3b8;line-height:1.7">
        <p style="margin:0 0 8px">The MCP server package could not be found or started.</p>
        <ul style="margin:0;padding-left:20px">
          <li>Run <span class="mono" style="color:#93c5fd">npx -y codeinbox-mcp</span> in your terminal to test if the package downloads</li>
          <li>Make sure you have Node.js 18+ installed (<span class="mono" style="color:#93c5fd">node --version</span>)</li>
          <li>Check that your AI app's MCP config uses the correct JSON format (see sections above)</li>
          <li>Some corporate networks block npm &mdash; try from a personal network</li>
        </ul>
      </div>
    </details>
  </div>

  <!-- Helpful links -->
  <div style="text-align:center;margin-bottom:20px;padding:20px;background:#111827;border-radius:10px">
    <p style="font-size:.88rem;color:#f1f5f9;margin-bottom:10px;font-weight:600">Quick Links</p>
    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:12px">
      <a href="/api_keys.php" style="color:#60a5fa;font-size:.85rem;text-decoration:none;padding:6px 14px;background:#1e293b;border-radius:6px;border:1px solid #334155">API Keys</a>
      <a href="/getting-started.php" style="color:#60a5fa;font-size:.85rem;text-decoration:none;padding:6px 14px;background:#1e293b;border-radius:6px;border:1px solid #334155">Getting Started</a>
      <a href="/inboxes.php" style="color:#60a5fa;font-size:.85rem;text-decoration:none;padding:6px 14px;background:#1e293b;border-radius:6px;border:1px solid #334155">My Inboxes</a>
      <a href="/dashboard.php" style="color:#60a5fa;font-size:.85rem;text-decoration:none;padding:6px 14px;background:#1e293b;border-radius:6px;border:1px solid #334155">Dashboard</a>
      <a href="mailto:support@teak.email" style="color:#60a5fa;font-size:.85rem;text-decoration:none;padding:6px 14px;background:#1e293b;border-radius:6px;border:1px solid #334155">support@teak.email</a>
    </div>
  </div>

  <?php endif; ?>
</div>
<?php page_footer(); ?>
