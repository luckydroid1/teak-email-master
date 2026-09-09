<?php
/**
 * _layout.php — Shared layout for app pages.
 * Landing page (index.php) has its own layout.
 */
declare(strict_types=1);

function page_header(string $title, array $user = null): void {
    $app = cfg()['app_name'];
    $nav = '';
    if ($user) {
        $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $nav = '<nav class="topnav">
            <div class="nav-inner">
                <a href="/dashboard.php" class="nav-brand">📬 Teak Email</a>
                <div class="nav-links">
                    <a href="/dashboard.php" class="nav-link' . ($currentPage === 'dashboard.php' ? ' active' : '') . '">Dashboard</a>
                    <a href="/inboxes.php" class="nav-link' . ($currentPage === 'inboxes.php' ? ' active' : '') . '">My Inboxes</a>
                    <a href="/send.php" class="nav-link' . ($currentPage === 'send.php' ? ' active' : '') . '">Send Email</a>
                    <a href="/sent.php" class="nav-link' . ($currentPage === 'sent.php' ? ' active' : '') . '">Sent</a>
                    <a href="/warmup.php" class="nav-link' . ($currentPage === 'warmup.php' ? ' active' : '') . '">Warmup</a>
                    <a href="/domains.php" class="nav-link' . ($currentPage === 'domains.php' ? ' active' : '') . '">Domains</a>
                    <a href="/buy-domain.php" class="nav-link' . ($currentPage === 'buy-domain.php' ? ' active' : '') . '">Buy Domain</a>
                    <a href="/api_keys.php" class="nav-link' . ($currentPage === 'api_keys.php' ? ' active' : '') . '">API Keys</a>
                </div>
                <div class="nav-user">
                    <span class="nav-email">' . htmlspecialchars($user['email']) . '</span>
                    <a href="/logout.php" class="nav-logout">Logout</a>
                </div>
                <button class="nav-toggle" onclick="const n=document.querySelector(\'.nav-links\');n.classList.toggle(\'open\');this.setAttribute(\'aria-expanded\',n.classList.contains(\'open\'))" aria-label="Toggle navigation menu" aria-expanded="false">☰</button>
            </div>
        </nav>';
    }
    echo '<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<meta name="description" content="Teak Email — Clean email inboxes with API & MCP for builders and AI agents">
<meta property="og:title" content="' . htmlspecialchars($title) . ' — Teak Email">
<meta property="og:description" content="Manage email inboxes with API & MCP integration for developers and AI agents">
<meta property="og:type" content="website">
<meta property="og:url" content="https://teak.email">
<title>' . htmlspecialchars($title) . ' — ' . htmlspecialchars($app) . '</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#0a0e1a;color:#e2e8f0;min-height:100vh;line-height:1.5}
a{color:#60a5fa}

/* Navigation */
.topnav{background:#111827;border-bottom:1px solid #1f2937;position:sticky;top:0;z-index:100}
.nav-inner{max-width:1000px;margin:0 auto;display:flex;align-items:center;padding:0 16px;height:56px;gap:16px}
.nav-brand{font-weight:800;font-size:1.1rem;color:#f9fafb;text-decoration:none;white-space:nowrap}
.nav-links{display:flex;gap:4px;flex:1;overflow-x:auto}
.nav-link{padding:8px 12px;border-radius:8px;font-size:.82rem;font-weight:500;color:#9ca3af;text-decoration:none;white-space:nowrap;transition:all .15s}
.nav-link:hover{background:#1f2937;color:#f9fafb}
.nav-link.active{background:#1e40af;color:#fff}
.nav-user{display:flex;align-items:center;gap:10px;white-space:nowrap}
.nav-email{font-size:.78rem;color:#6b7280;max-width:120px;overflow:hidden;text-overflow:ellipsis}
.nav-logout{font-size:.78rem;color:#6b7280;text-decoration:none;padding:4px 8px;border-radius:6px}
.nav-logout:hover{background:#1f2937;color:#f9fafb}
.nav-toggle{display:none;background:none;border:none;color:#9ca3af;font-size:1.4rem;cursor:pointer;padding:4px 8px}

@media(max-width:768px){
    .nav-links{display:none;position:absolute;top:56px;left:0;right:0;background:#111827;border-bottom:1px solid #1f2937;flex-direction:column;padding:8px;gap:4px}
    .nav-links.open{display:flex}
    .nav-link{padding:12px 16px;font-size:.9rem}
    .nav-user{display:none}
    .nav-toggle{display:block;margin-left:auto}
}

/* Layout */
.container{max-width:900px;margin:0 auto;padding:20px 16px}
h1{font-size:1.4rem}h2{font-size:1.1rem;margin-bottom:10px;color:#f1f5f9}
h3{font-size:1rem;margin-bottom:8px;color:#f1f5f9}
.sub{color:#94a3b8;font-size:.9rem;margin-bottom:16px}

/* Cards */
.card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:20px;margin-bottom:14px}

/* Forms */
label{display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:#d1d5db}
input,select,textarea{width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px;outline:none;transition:border-color .15s}
input:focus,select:focus,textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}

/* Buttons */
.btn{padding:10px 20px;border-radius:8px;border:none;font-weight:600;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;background:#3b82f6;color:#fff;transition:all .15s;text-decoration:none}
.btn:hover{background:#2563eb;transform:translateY(-1px)}
.btn-sm{padding:6px 12px;font-size:.78rem;border-radius:6px;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none}
.btn-success{background:#059669;color:#fff}.btn-success:hover{background:#047857}
.btn-warning{background:#d97706;color:#fff}.btn-warning:hover{background:#b45309}
.btn-danger{background:#dc2626;color:#fff;padding:6px 12px;font-size:.78rem;border-radius:6px;border:none;cursor:pointer}
.btn-ghost{background:transparent;border:1px solid #374151;color:#9ca3af}.btn-ghost:hover{border-color:#6b7280;color:#e2e8f0}

/* Alerts */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:.88rem}
.alert-e{background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b}
.alert-s{background:#14532d;color:#86efac;border:1px solid #166534}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:10px;margin-bottom:16px}
.stat{background:#111827;padding:14px;border-radius:10px;text-align:center;border:1px solid #1f2937}
.stat .n{font-size:1.3rem;font-weight:700;color:#60a5fa}
.stat .l{font-size:.72rem;color:#6b7280;margin-top:2px}

/* Inbox list */
.inbox-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #1f2937;font-size:.88rem;flex-wrap:wrap;gap:8px}
.inbox-item:last-child{border-bottom:none}
.inbox-email{font-weight:600;color:#f9fafb;word-break:break-all}
.inbox-meta{font-size:.78rem;color:#6b7280}
.inbox-actions{display:flex;gap:6px}

/* Email list */
.email-row{display:flex;align-items:center;padding:10px 12px;border-bottom:1px solid #1f2937;cursor:pointer;text-decoration:none;color:#e2e8f0;gap:10px;transition:background .1s}
.email-row:hover{background:#1f2937;border-radius:6px}
.email-from{flex-shrink:0;width:120px;font-size:.82rem;color:#9ca3af;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.email-subject{flex:1;font-weight:500;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.email-date{flex-shrink:0;font-size:.72rem;color:#6b7280}

/* OTP */
.otp-badge{display:inline-block;background:#14532d;border:1px solid #166534;color:#86efac;font-size:1.4rem;font-weight:800;padding:10px 24px;border-radius:12px;letter-spacing:6px;font-family:monospace}

/* Message view */
.msg-view{background:#0a0e1a;padding:20px;border-radius:8px;margin-top:12px;font-size:.85rem;line-height:1.7;max-height:60vh;overflow-y:auto;border:1px solid #1f2937}
.msg-view img{max-width:100%;height:auto;border-radius:6px}
.msg-view table{width:100%;border-collapse:collapse}
.msg-view td,.msg-view th{padding:6px 10px;border:1px solid #1f2937}

/* Code block */
.code{background:#0a0e1a;border:1px solid #1f2937;padding:12px 16px;border-radius:8px;font-family:\'SF Mono\',Menlo,monospace;font-size:.8rem;color:#93c5fd;word-break:break-all;margin-bottom:12px;overflow-x:auto}
.mono{font-family:monospace;font-size:.85rem;color:#93c5fd}

/* Grid */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.grid2{grid-template-columns:1fr}}

/* Back link */
.back{display:inline-flex;align-items:center;gap:4px;color:#60a5fa;text-decoration:none;font-size:.88rem;margin-bottom:16px}
.back:hover{text-decoration:underline}

/* Footer */
.footer{text-align:center;color:#4b5563;font-size:.78rem;margin-top:40px;padding:20px 16px}
.footer a{color:#6b7280;text-decoration:none}
.footer a:hover{color:#9ca3af}

/* Warmup */
.warmup-bar{background:#1f2937;border-radius:20px;height:6px;margin-top:6px;overflow:hidden}
.warmup-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#f59e0b,#ef4444)}

/* Mobile */
@media(max-width:480px){
    .container{padding:12px}
    .card{padding:16px}
    .stats{grid-template-columns:repeat(2,1fr);gap:8px}
    .stat{padding:10px}
    .stat .n{font-size:1.1rem}
    .inbox-item{flex-direction:column;align-items:flex-start}
    .inbox-actions{width:100%;justify-content:flex-end}
}

/* Loading states */
.btn[disabled]{opacity:.7;cursor:not-allowed;pointer-events:none}
@keyframes spin{to{transform:rotate(360deg)}}
.loading::after{content:"";display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;margin-left:6px;vertical-align:middle}

/* Accessibility: focus visible */
:focus-visible{outline:2px solid #3b82f6;outline-offset:2px}
.btn:focus-visible{box-shadow:0 0 0 3px rgba(59,130,246,.4)}
</style></head><body>';
    if ($nav !== '') echo $nav;
    echo '<div class="container">';
}

function page_footer(): void {
    echo '<div class="footer">
        <a href="/privacy.php">Privacy</a> ·
        <a href="/terms.php">Terms</a> ·
        <a href="/getting-started.php">Getting Started</a> ·
        <a href="mailto:support@teak.email">Support</a> ·
        <a href="/delete_account.php" style="color:#dc2626">Delete Account</a>
        <br>Teak Email — Built for builders & AI agents
    </div></div></body></html>';
}

/** Display alert message. */
function alert(?string $error, ?string $success): void {
    if ($error) echo '<div class="alert alert-e">' . htmlspecialchars($error) . '</div>';
    if ($success) echo '<div class="alert alert-s">' . htmlspecialchars($success) . '</div>';
}
