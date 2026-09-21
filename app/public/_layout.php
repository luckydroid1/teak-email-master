<?php
/**
 * _layout.php — Shared layout for app pages.
 * Landing page (index.php) has its own layout.
 */
declare(strict_types=1);

function page_header(string $title, array $user = null): void {
    $app = cfg()['app_name'] ?? 'Teak Email';
    $nav = '';
    if ($user) {
        $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $uid = (int)$user['id'];
        $balance = function_exists('credit_balance') ? credit_balance($uid) : 0;
        $tier = function_exists('user_tier') ? user_tier($uid) : 1;

        $isDashboard = ($currentPage === 'dashboard.php');
        $isInboxes = in_array($currentPage, ['inboxes.php', 'inbox_view.php'], true);
        $isSend = ($currentPage === 'send.php');
        $isSent = ($currentPage === 'sent.php');
        $isWarmup = ($currentPage === 'warmup.php');
	        $isExpenses = ($currentPage === 'expenses.php');
	        $isDomains = ($currentPage === 'domains.php');
	        $isBuyDomain = ($currentPage === 'buy-domain.php');
	        $isApi = in_array($currentPage, ['api_keys.php', 'mcp_setup.php'], true);

	        $isAdmin = in_array($currentPage, ['index.php', 'settings.php', 'users.php', 'transactions.php'], true) && strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false;
	        $adminLink = !empty($user['is_admin'])
	            ? '<a href="/admin/index.php" class="nav-link' . ($isAdmin ? ' active' : '') . '" style="color:#f59e0b;font-weight:700">👑 Admin</a>'
	            : '';

	        $nav = '<nav class="topnav">
	            <div class="nav-inner">
	                <a href="/dashboard.php" class="nav-brand" style="display:flex;align-items:center" title="BuyDomains By Network Solutions"><img src="/logo-app.png?v=1" alt="BuyDomains By Network Solutions" style="height:32px;width:auto;display:block" /></a>
	                <div class="nav-links" id="main-nav-links">
	                    <a href="/dashboard.php" class="nav-link' . ($isDashboard ? ' active' : '') . '">Dashboard</a>
	                    <a href="/inboxes.php" class="nav-link' . ($isInboxes ? ' active' : '') . '">My Inboxes</a>
	                    <a href="/expenses.php" class="nav-link' . ($isExpenses ? ' active' : '') . '">🧾 Expenses</a>
	                    <a href="/send.php" class="nav-link' . ($isSend ? ' active' : '') . '">Send Email</a>
	                    <a href="/sent.php" class="nav-link' . ($isSent ? ' active' : '') . '">Sent</a>
	                    <a href="/warmup.php" class="nav-link' . ($isWarmup ? ' active' : '') . '">Warmup</a>
	                    <a href="/domains.php" class="nav-link' . ($isDomains ? ' active' : '') . '">Domains</a>
	                    <a href="/buy-domain.php" class="nav-link' . ($isBuyDomain ? ' active' : '') . '">Buy Domain</a>
	                    <a href="/api_keys.php" class="nav-link' . ($isApi ? ' active' : '') . '">API Keys</a>
	                    ' . $adminLink . '
	                    <div class="nav-mobile-user">
	                        <div class="nav-mobile-info">
	                            <span class="nav-credit-badge" title="Remaining Credits">⚡ ' . number_format($balance) . ' <span style="opacity:0.75;font-size:0.75rem">credits</span></span>
	                            <span class="nav-tier-badge" title="Lifetime Tier">Tier ' . $tier . '</span>
	                        </div>
	                        <div class="nav-mobile-account">
	                            <span class="nav-email-mobile" title="' . htmlspecialchars($user['email']) . '">👤 ' . htmlspecialchars($user['email']) . '</span>
	                            <a href="/logout.php" class="nav-logout">Logout</a>
	                        </div>
	                    </div>
	                </div>
	                <div class="nav-user">
	                    <span class="nav-credit-badge" title="Remaining Credits">⚡ ' . number_format($balance) . ' <span style="opacity:0.75;font-size:0.75rem">credits</span></span>
	                    <span class="nav-tier-badge" title="Lifetime Tier">T' . $tier . '</span>
	                    <span class="nav-email" title="' . htmlspecialchars($user['email']) . '">' . htmlspecialchars($user['email']) . '</span>
	                    <a href="/logout.php" class="nav-logout">Logout</a>
	                </div>
                <button class="nav-toggle" onclick="const n=document.getElementById(\'main-nav-links\');n.classList.toggle(\'open\');this.setAttribute(\'aria-expanded\',n.classList.contains(\'open\'))" aria-label="Toggle navigation menu" aria-expanded="false">☰</button>
            </div>
        </nav>';
    }
	    echo '<!DOCTYPE html><html lang="en"><head>
		<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
		<!-- Disable Cloudflare Email Obfuscation -->
		<!--email_off-->
		<link rel="preload" href="/logo-app.png" as="image">
		<link rel="preload" href="/logo.png" as="image">
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
	img{content-visibility:auto}

	/* Navigation */
	.topnav{background:#111827;border-bottom:1px solid #1f2937;position:sticky;top:0;z-index:100;backdrop-filter:blur(8px)}
	.nav-inner{max-width:1050px;margin:0 auto;display:flex;align-items:center;padding:0 16px;height:56px;gap:12px}
	.nav-brand{font-weight:800;font-size:1.1rem;color:#f9fafb;text-decoration:none;white-space:nowrap;display:flex;align-items:center;gap:6px}
	.nav-brand img{height:32px;width:auto;display:block;aspect-ratio:300/85}
.nav-links{display:flex;gap:3px;flex:1;overflow-x:auto}
.nav-link{padding:6px 11px;border-radius:7px;font-size:.82rem;font-weight:500;color:#9ca3af;text-decoration:none;white-space:nowrap;transition:all .15s}
.nav-link:hover{background:#1f2937;color:#f9fafb}
.nav-link.active{background:#2563eb;color:#fff}
.nav-user{display:flex;align-items:center;gap:8px;white-space:nowrap}
.nav-mobile-user{display:none}
.nav-credit-badge{background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;font-size:.78rem;font-weight:700;padding:3px 8px;border-radius:12px}
.nav-tier-badge{background:#1e293b;border:1px solid #334155;color:#fbbf24;font-size:.75rem;font-weight:700;padding:2px 7px;border-radius:10px}
.nav-email{font-size:.78rem;color:#94a3b8;max-width:120px;overflow:hidden;text-overflow:ellipsis}
.nav-logout{font-size:.78rem;color:#9ca3af;text-decoration:none;padding:4px 8px;border-radius:6px;border:1px solid #374151}
.nav-logout:hover{background:#1f2937;color:#f9fafb;border-color:#4b5563}
.nav-toggle{display:none;background:none;border:none;color:#9ca3af;font-size:1.4rem;cursor:pointer;padding:4px 8px}

@media(max-width:880px){
    .nav-links{display:none;position:absolute;top:56px;left:0;right:0;background:#111827;border-bottom:1px solid #1f2937;flex-direction:column;padding:12px 16px;gap:6px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.5)}
    .nav-links.open{display:flex}
    .nav-link{padding:10px 14px;font-size:.9rem}
    .nav-user{display:none}
    .nav-toggle{display:block;margin-left:auto}
    .nav-mobile-user{display:flex;flex-direction:column;gap:10px;padding-top:12px;margin-top:8px;border-top:1px solid #1f2937}
    .nav-mobile-info{display:flex;gap:8px;align-items:center}
    .nav-mobile-account{display:flex;justify-content:space-between;align-items:center;gap:8px}
    .nav-email-mobile{font-size:.82rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
}

/* Layout */
.container{max-width:960px;margin:0 auto;padding:24px 16px;overflow-x:hidden}
h1{font-size:1.45rem;color:#f8fafc;word-wrap:break-word}
h2{font-size:1.15rem;margin-bottom:10px;color:#f1f5f9;word-wrap:break-word}
h3{font-size:1rem;margin-bottom:8px;color:#f1f5f9;word-wrap:break-word}
.sub{color:#94a3b8;font-size:.88rem;margin-bottom:16px;line-height:1.5;word-wrap:break-word}

/* Cards */
.card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);overflow-wrap:break-word;word-break:break-word}
.card-highlight{border-color:#3b82f6;box-shadow:0 0 0 1px rgba(59,130,246,0.3)}

/* Forms */
label{display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:#d1d5db}
input,select,textarea{width:100%;max-width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#0a0e1a;color:#e2e8f0;font-size:.9rem;margin-bottom:12px;outline:none;transition:border-color .15s}
input:focus,select:focus,textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}

@media(max-width:640px){
    .container{padding:16px 12px}
    .card{padding:16px 14px}
    .email-from{width:110px !important}
    .stats{grid-template-columns:1fr 1fr !important}
    .mb{flex-direction:column;align-items:flex-start !important;gap:8px}
}

/* Buttons */
.btn{padding:10px 20px;border-radius:8px;border:none;font-weight:600;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#3b82f6;color:#fff;transition:all .15s;text-decoration:none}
.btn:hover{background:#2563eb;transform:translateY(-1px)}
.btn-sm{padding:6px 12px;font-size:.78rem;border-radius:6px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;gap:4px;text-decoration:none;border:none;cursor:pointer}
.btn-success{background:#059669;color:#fff}.btn-success:hover{background:#047857}
.btn-warning{background:#d97706;color:#fff}.btn-warning:hover{background:#b45309}
.btn-danger{background:#dc2626;color:#fff;padding:6px 12px;font-size:.78rem;border-radius:6px;border:none;cursor:pointer}
.btn-danger:hover{background:#b91c1c}
.btn-ghost{background:transparent;border:1px solid #374151;color:#9ca3af}.btn-ghost:hover{border-color:#6b7280;color:#e2e8f0}
.btn-copy{background:#1e293b;border:1px solid #334155;color:#94a3b8;font-size:.75rem;padding:4px 8px;border-radius:6px;cursor:pointer;transition:all .15s}
.btn-copy:hover{background:#334155;color:#f8fafc}

/* Alerts */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.88rem;display:flex;align-items:center;justify-content:space-between;gap:8px}
.alert-e{background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b}
.alert-s{background:#14532d;color:#86efac;border:1px solid #166534}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:18px}
.stat{background:#111827;padding:16px;border-radius:12px;text-align:center;border:1px solid #1f2937}
.stat .n{font-size:1.4rem;font-weight:800;color:#60a5fa}
.stat .l{font-size:.75rem;color:#94a3b8;margin-top:2px;font-weight:500}

/* List Item Styles */
.mb{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #1f2937;gap:12px;flex-wrap:wrap}
.mb:last-child{border-bottom:none}
.e{font-weight:600;color:#f8fafc;font-size:.92rem;word-break:break-all}
.m{font-size:.78rem;color:#6b7280;margin-top:2px}

/* Email list */
.email-row{display:flex;align-items:center;padding:12px;border-bottom:1px solid #1f2937;cursor:pointer;text-decoration:none;color:#e2e8f0;gap:12px;transition:background .15s}
.email-row:hover{background:#1f2937;border-radius:8px}
.email-from{flex-shrink:0;width:140px;font-size:.82rem;font-weight:600;color:#cbd5e1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.email-subject{flex:1;font-weight:500;font-size:.85rem;color:#f8fafc;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.email-date{flex-shrink:0;font-size:.74rem;color:#64748b}

/* OTP */
.otp-badge{display:inline-block;background:#064e3b;border:1px solid #059669;color:#6ee7b7;font-size:1.6rem;font-weight:800;padding:10px 24px;border-radius:12px;letter-spacing:6px;font-family:monospace;cursor:pointer;transition:transform .15s}
.otp-badge:hover{transform:scale(1.03)}

/* Message view */
.msg-view{background:#0a0e1a;padding:20px;border-radius:8px;margin-top:12px;font-size:.85rem;line-height:1.7;max-height:60vh;overflow-y:auto;border:1px solid #1f2937}
.msg-view img{max-width:100%;height:auto;border-radius:6px}
.msg-view table{width:100%;border-collapse:collapse}
.msg-view td,.msg-view th{padding:6px 10px;border:1px solid #1f2937}

/* Code block */
.code{background:#0a0e1a;border:1px solid #1f2937;padding:12px 16px;border-radius:8px;font-family:\'SF Mono\',Menlo,monospace;font-size:.82rem;color:#93c5fd;word-break:break-all;margin-bottom:12px;overflow-x:auto;position:relative}
.mono{font-family:\'SF Mono\',Menlo,monospace;font-size:.84rem;color:#93c5fd}

/* Grid */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:640px){.grid2{grid-template-columns:1fr}}

/* Back link */
.back{display:inline-flex;align-items:center;gap:6px;color:#60a5fa;text-decoration:none;font-size:.88rem;margin-bottom:16px;font-weight:500}
.back:hover{text-decoration:underline}

/* Footer */
.footer{text-align:center;color:#64748b;font-size:.8rem;margin-top:48px;padding:24px 16px;border-top:1px solid #1f2937}
.footer a{color:#94a3b8;text-decoration:none}
.footer a:hover{color:#f8fafc;text-decoration:underline}

/* Toast */
#toast-container{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast{background:#1e293b;border:1px solid #3b82f6;color:#f8fafc;padding:10px 16px;border-radius:8px;font-size:.85rem;box-shadow:0 10px 15px -3px rgba(0,0,0,0.5);animation:fadeIn .2s ease-out}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* Loading states */
.btn[disabled]{opacity:.7;cursor:not-allowed;pointer-events:none}
@keyframes spin{to{transform:rotate(360deg)}}
.loading::after{content:"";display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;margin-left:6px;vertical-align:middle}

:focus-visible{outline:2px solid #3b82f6;outline-offset:2px}
</style>
<script>
function copyToClipboard(text, btnElement) {
    navigator.clipboard.writeText(text).then(() => {
        showToast("Copied to clipboard: " + (text.length > 25 ? text.substring(0,25) + "..." : text));
        if (btnElement) {
            const original = btnElement.innerHTML;
            btnElement.innerHTML = "✓ Copied!";
            btnElement.style.color = "#86efac";
            setTimeout(() => {
                btnElement.innerHTML = original;
                btnElement.style.color = "";
            }, 1800);
        }
    }).catch(err => {
        showToast("Failed to copy");
    });
}

function showToast(msg) {
    let container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        document.body.appendChild(container);
    }
    const t = document.createElement("div");
    t.className = "toast";
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => {
        t.style.transition = "opacity 0.3s";
        t.style.opacity = "0";
        setTimeout(() => t.remove(), 300);
    }, 2500);
}
</script>
</head><body>';
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
	        <br><span style="margin-top:6px;display:inline-block">Teak Email — Built for builders & AI agents</span>
	    </div></div><!--/email_off--></body></html>';
	}

/** Display alert message. */
function alert(?string $error, ?string $success): void {
    if ($error) echo '<div class="alert alert-e"><span>' . htmlspecialchars($error) . '</span><button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#fca5a5;cursor:pointer;font-weight:bold">✕</button></div>';
    if ($success) echo '<div class="alert alert-s"><span>' . htmlspecialchars($success) . '</span><button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#86efac;cursor:pointer;font-weight:bold">✕</button></div>';
}
