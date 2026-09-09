<?php
/**
 * index.php — Landing + pricing page. Juga berfungsi sebagai custom 404 handler.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

// If not root URI, this is a 404 (due to try_files fallback)
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($request_uri !== '/' && $request_uri !== '/index.php') {
    http_response_code(404);
    require_once __DIR__ . '/_layout.php';
    page_header('404 — Not Found');
    echo '<div class="card" style="text-align:center;max-width:400px;margin:80px auto">
      <h2>🔍 Page Not Found</h2>
      <p style="color:#94a3b8;margin-top:12px">The page you\'re looking for doesn\'t exist.</p>
      <p style="margin-top:20px"><a href="/" class="btn">Back to Home</a></p>
    </div>';
    page_footer();
    exit;
}

$app = 'Teak Email';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teak Email — Clean Email Inboxes for Builders & AI Agents</title>
<meta name="description" content="Create a fresh inbox in seconds. Use it yourself or with AI agents via API/MCP. Lifetime access, non-expiring credits, no monthly fee.">
<meta property="og:title" content="Teak Email — Clean Email Inboxes for Builders & AI Agents">
<meta property="og:description" content="Create a fresh inbox in seconds. Use it yourself or with AI agents via API & MCP. Lifetime access, non-expiring credits, no monthly fee.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://teak.email">
<meta property="og:image" content="https://teak.email/favicon.svg">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='0.9em' font-size='90'>📬</text></svg>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,system-ui,sans-serif;background:#0f172a;color:#e2e8f0;line-height:1.6}
.hero{background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%);color:#fff;padding:70px 20px 60px;text-align:center}
.hero h1{font-size:clamp(1.8rem,4vw,2.8rem);max-width:760px;margin:0 auto 14px;font-weight:800}
.hero p{font-size:1.05rem;opacity:.9;max-width:640px;margin:0 auto}
.badge{display:inline-block;background:#f59e0b;color:#000;font-size:.75rem;font-weight:700;padding:4px 14px;border-radius:20px;text-transform:uppercase;margin-bottom:14px}
.main{max-width:860px;margin:0 auto;padding:40px 20px 60px}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px;margin-bottom:16px}
.card h2{color:#f1f5f9;margin-bottom:10px}
.card p{color:#94a3b8;font-size:.95rem}
.pricing{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:24px 0}
.tier{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;text-align:center;position:relative}
.tier.pop{border-color:#3b82f6;box-shadow:0 0 0 1px #3b82f6}
.tier .price{font-size:1.7rem;font-weight:800;color:#60a5fa}
.tier .name{font-size:.85rem;color:#94a3b8;margin-bottom:8px;font-weight:600}
.tier ul{list-style:none;font-size:.8rem;color:#cbd5e1;margin-top:10px;text-align:left}
.tier ul li{padding:3px 0}
.tier .badge-pop{position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#f59e0b;color:#000;font-size:.65rem;font-weight:700;padding:2px 10px;border-radius:10px;text-transform:uppercase}
.cta{display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;font-weight:700;padding:12px 28px;border-radius:8px;margin-top:8px}
.cta:hover{background:#2563eb}
.features{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:640px){.features{grid-template-columns:1fr}}
.feat{background:#1e293b;border:1px solid #334155;border-radius:10px;padding:16px}
.feat h3{font-size:.95rem;color:#f1f5f9;margin-bottom:6px}
.feat p{font-size:.85rem;color:#94a3b8}
.faq details{background:#1e293b;border:1px solid #334155;border-radius:8px;margin-bottom:10px;padding:14px 18px}
.faq summary{cursor:pointer;font-weight:600;font-size:.92rem;color:#f1f5f9}
.faq details p{margin-top:8px;color:#94a3b8;font-size:.88rem}
.btn-line{display:inline-block;border:1px solid #3b82f6;color:#60a5fa;text-decoration:none;font-weight:600;padding:10px 22px;border-radius:8px;margin-left:8px}
footer{text-align:center;padding:30px;color:#475569;font-size:.82rem}
</style>
</head>
<body>
<div class="hero">
  <span class="badge">Lifetime Deal · No Subscription</span>
  <h1>Clean email inboxes for builders & AI agents.</h1>
  <p>Create a fresh inbox in 2 clicks. Use it yourself or with your AI agent via API & MCP. Custom clean domains, no CAPTCHA wall, no blocklists.</p>
  <p style="margin-top:24px">
    <a href="/signup.php" class="cta">Create Free Account</a>
    <a href="/login.php" class="btn-line">Login</a>
    <a href="/getting-started.php" class="btn-line" style="border-color:#10b981;color:#34d399">📖 Getting Started</a>
  </p>
  <p class="sub" style="text-align:center;margin-top:16px;color:#86efac;font-size:.9rem;max-width:500px;margin-left:auto;margin-right:auto">Live now — create your first inbox in 30 seconds.</p>
</div>

<div class="main">
  <div class="card">
<h2>Why Teak Email?</h2>
  <p>Your AI agent needs an email to verify accounts and grab OTPs. Gmail blocks automation. Temp mail is banned everywhere. Teak Email gives you clean, dedicated inboxes on custom domains — readable by humans or by your agent through a simple API.</p>
  </div>

  <h2 style="margin:24px 0 8px">Lifetime Access + Included Credits</h2>
  <p class="sub" style="color:#94a3b8;font-size:.9rem">Pay once for platform access. Use included non-expiring credits anytime. Top up only when your usage grows. No monthly fee required.</p>

  <div class="pricing">
    <div class="tier">
      <div class="name">Tier 1</div>
      <div class="price">$37</div>
      <ul><li>1,000 credits</li><li>1 inbox</li><li>Basic API</li><li>7-day retention</li></ul>
      <a class="cta" style="font-size:.85rem;padding:8px 16px" href="https://appsumo.com" target="_blank" rel="noopener">Get on AppSumo</a>
    </div>
    <div class="tier">
      <div class="name">Tier 2</div>
      <div class="price">$67</div>
      <ul><li>2,500 credits</li><li>3 inboxes</li><li>Basic API</li><li>14-day retention</li></ul>
      <a class="cta" style="font-size:.85rem;padding:8px 16px" href="https://appsumo.com" target="_blank" rel="noopener">Get on AppSumo</a>
    </div>
    <div class="tier pop">
      <span class="badge-pop">Most Popular</span>
      <div class="name">Tier 3</div>
      <div class="price">$97</div>
      <ul><li>6,000 credits</li><li>8 inboxes</li><li>Full API</li><li>30-day retention</li></ul>
      <a class="cta" style="font-size:.85rem;padding:8px 16px" href="https://appsumo.com" target="_blank" rel="noopener">Get on AppSumo</a>
    </div>
    <div class="tier">
      <div class="name">Tier 4</div>
      <div class="price">$147</div>
      <ul><li>12,000 credits</li><li>20 inboxes</li><li>Full API</li><li>45-day retention</li></ul>
      <a class="cta" style="font-size:.85rem;padding:8px 16px" href="https://appsumo.com" target="_blank" rel="noopener">Get on AppSumo</a>
    </div>
    <div class="tier">
      <div class="name">Tier 5</div>
      <div class="price">$197</div>
      <ul><li>25,000 credits</li><li>50 inboxes</li><li>Full API + priority</li><li>60-day retention</li></ul>
      <a class="cta" style="font-size:.85rem;padding:8px 16px" href="https://appsumo.com" target="_blank" rel="noopener">Get on AppSumo</a>
    </div>
  </div>

  <div class="features">
    <div class="feat"><h3>⚡ 2-Click Inbox</h3><p>Pick a domain, type a name, done. Your inbox is live and receiving.</p></div>
    <div class="feat"><h3>🤖 API & MCP Ready</h3><p>REST API + MCP server so your AI agent can list inboxes and grab OTPs automatically.</p></div>
    <div class="feat"><h3>🌐 Your Domain or Ours</h3><p>Use our clean shared domains, or bring your own (coming soon).</p></div>
    <div class="feat"><h3>🔒 Receive-Only, Clean Reputation</h3><p>No bulk sending. Clean infrastructure designed for receiving codes.</p></div>
  </div>

  <h2 style="margin:32px 0 8px">How this Lifetime Deal works</h2>
  <div class="card">
    <p>This deal gives you <strong style="color:#f1f5f9">lifetime platform access</strong> — no recurring subscription. Each tier includes <strong style="color:#f1f5f9">non-expiring credits</strong> used for platform activity (inbox rent, emails received, API calls). If your usage grows, top up. If not, pay nothing more.</p>
  </div>

  <h2 style="margin:24px 0 8px">FAQ</h2>
  <div class="faq">
    <details open><summary>Is this really a lifetime deal?</summary><p>Yes — for platform access. Your account access is lifetime. Included credits are a one-time allowance, not a monthly reset.</p></details>
    <details><summary>Do credits expire?</summary><p>No. Credits are non-expiring. Use them whenever you need.</p></details>
    <details><summary>Do I need a top-up?</summary><p>Only if your usage grows. Small users may never need one.</p></details>
    <details><summary>Why not unlimited usage?</summary><p>Unlimited usage breaks long-term economics. Metered usage keeps the product sustainable — so we can support users for years.</p></details>
    <details><summary>Will I lose access later?</summary><p>No. Your lifetime access stays active.</p></details>
  </div>
</div>

<footer>© 2026 <?= htmlspecialchars($app) ?> · Built for builders & AI agents · <a href="/login.php" style="color:#3b82f6">Login</a></footer>
</body>
</html>
