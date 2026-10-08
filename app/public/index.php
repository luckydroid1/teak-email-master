<?php
/**
 * index.php — Modern AI Runtime x Agent Wave Landing & Pricing Experience.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/redeem.php';

$t1 = tier_info(1);
$t2 = tier_info(2);
$t3 = tier_info(3);
$t4 = tier_info(4);
$t5 = tier_info(5);

// If not root URI, this is a 404 (due to try_files fallback)
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($request_uri !== '/' && $request_uri !== '/index.php') {
    http_response_code(404);
    require_once __DIR__ . '/_layout.php';
    page_header('404 — Not Found');
    echo '<div class="card" style="text-align:center;max-width:400px;margin:80px auto">
      <h2>🔍 Page Not Found</h2>
      <p style="color:#94a3b8;margin-top:12px">The page you are looking for does not exist.</p>
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
<link rel="preload" href="/logo.png" as="image">
<title>Teak Email — The Clean Email Runtime for AI Agents, Multi-Brand Ops & Developers</title>
<meta name="description" content="One email engine for multi-brand operators, instant sub-50ms OTP extraction for autonomous AI agents via MCP protocol, and isolated private sandboxes for developers.">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg-page: #060913;
  --bg-surface: #0c1222;
  --bg-surface-elevated: #111a30;
  --bg-card: rgba(12, 18, 34, 0.75);
  --border-subtle: rgba(255, 255, 255, 0.08);
  --border-strong: rgba(56, 189, 248, 0.25);
  --border-glow: rgba(56, 189, 248, 0.4);
  --text-primary: #f8fafc;
  --text-secondary: #94a3b8;
  --text-tertiary: #64748b;
  --cyan: #38bdf8;
  --cyan-glow: rgba(56, 189, 248, 0.15);
  --emerald: #10b981;
  --emerald-glow: rgba(16, 185, 129, 0.15);
  --purple: #8b5cf6;
  --purple-glow: rgba(139, 92, 246, 0.15);
  --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  --font-mono: 'JetBrains Mono', monospace;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  background-color: var(--bg-page);
  color: var(--text-primary);
  font-family: var(--font-sans);
  font-size: 16px;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
  position: relative;
}

/* Background Ambient Lights */
.bg-ambient {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}
.ambient-glow-1 {
  position: absolute;
  top: -15%;
  left: 20%;
  width: 700px;
  height: 500px;
  background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, rgba(139, 92, 246, 0.05) 50%, transparent 70%);
  filter: blur(80px);
}
.ambient-glow-2 {
  position: absolute;
  top: 40%;
  right: -10%;
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, rgba(56, 189, 248, 0.04) 50%, transparent 70%);
  filter: blur(90px);
}
.grid-mesh {
  position: absolute;
  inset: 0;
  background-image: 
    linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(ellipse 70% 50% at 50% 0%, #000 60%, transparent 100%);
  -webkit-mask-image: radial-gradient(ellipse 70% 50% at 50% 0%, #000 60%, transparent 100%);
}

a { color: inherit; text-decoration: none; }
.container { max-width: 1180px; margin: 0 auto; padding: 0 24px; position: relative; z-index: 1; }

/* Navigation */
.nav-wrap {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(6, 9, 19, 0.82);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--border-subtle);
}
.nav-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 68px;
}
.brand-group {
  display: flex;
  align-items: center;
  gap: 12px;
}
.brand-logo {
  height: 26px;
  width: auto;
  display: block;
}
.brand-pill {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--cyan);
  background: var(--cyan-glow);
  border: 1px solid rgba(56, 189, 248, 0.3);
  padding: 3px 10px;
  border-radius: 20px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.pulse-dot {
  width: 6px;
  height: 6px;
  background: #10b981;
  border-radius: 50%;
  box-shadow: 0 0 8px #10b981;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0% { transform: scale(0.95); opacity: 0.8; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.95); opacity: 0.8; }
}
.nav-menu {
  display: flex;
  align-items: center;
  gap: 28px;
}
.nav-item {
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-weight: 500;
  transition: color 0.15s;
}
.nav-item:hover { color: #fff; }
.nav-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-family: var(--font-sans);
  font-size: 0.9rem;
  font-weight: 700;
  padding: 10px 20px;
  border-radius: 10px;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: pointer;
  border: 1px solid transparent;
}
.btn-primary {
  background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
  color: #fff;
  box-shadow: 0 0 20px rgba(37, 99, 235, 0.35);
}
.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 0 30px rgba(37, 99, 235, 0.55);
}
.btn-glow {
  background: linear-gradient(135deg, #38bdf8 0%, #3b82f6 100%);
  color: #040814;
  font-weight: 800;
  box-shadow: 0 0 25px rgba(56, 189, 248, 0.4);
}
.btn-glow:hover {
  transform: translateY(-2px);
  box-shadow: 0 0 35px rgba(56, 189, 248, 0.7);
  background: #7dd3fc;
}
.btn-ghost {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border-subtle);
  color: var(--text-secondary);
}
.btn-ghost:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.18);
  color: #fff;
}
.btn-sm {
  padding: 7px 14px;
  font-size: 0.82rem;
  border-radius: 8px;
}

.nowrap { white-space: nowrap; }
.d-desk { display: inline; }
@media (max-width: 768px) {
  .d-desk { display: none; }
}

/* Hero Section */
.hero {
  padding: 90px 0 60px;
  text-align: center;
}
.hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-mono);
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--cyan);
  background: rgba(56, 189, 248, 0.08);
  border: 1px solid rgba(56, 189, 248, 0.25);
  padding: 6px 14px;
  border-radius: 30px;
  margin-bottom: 24px;
}
.hero-title {
  font-size: clamp(2.3rem, 5.5vw, 3.8rem);
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.035em;
  color: #f8fafc;
  max-width: 980px;
  margin: 0 auto 20px;
  text-wrap: balance;
}
.gradient-text {
  background: linear-gradient(135deg, #38bdf8 15%, #a855f7 60%, #ec4899 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.hero-desc {
  font-size: clamp(1rem, 2vw, 1.2rem);
  color: var(--text-secondary);
  max-width: 780px;
  margin: 0 auto 36px;
  line-height: 1.65;
}
.hero-cta-group {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-bottom: 54px;
  flex-wrap: wrap;
}

/* Interactive AI Runtime Terminal Window */
.runtime-terminal-wrap {
  max-width: 960px;
  margin: 0 auto 80px;
  background: rgba(11, 16, 30, 0.85);
  border: 1px solid var(--border-strong);
  border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 40px rgba(56, 189, 248, 0.1);
  overflow: hidden;
  backdrop-filter: blur(20px);
}
.terminal-header {
  background: #080d1a;
  border-bottom: 1px solid var(--border-subtle);
  padding: 12px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.terminal-dots {
  display: flex;
  align-items: center;
  gap: 7px;
}
.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.dot-red { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green { background: #10b981; }

.terminal-tabs {
  display: flex;
  gap: 6px;
}
.terminal-tab-btn {
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-tertiary);
  font-family: var(--font-mono);
  font-size: 0.78rem;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}
.terminal-tab-btn:hover { color: #f8fafc; background: rgba(255,255,255,0.03); }
.terminal-tab-btn.active {
  color: var(--cyan);
  background: rgba(56, 189, 248, 0.1);
  border-color: rgba(56, 189, 248, 0.3);
}

.terminal-body {
  padding: 24px;
  text-align: left;
  font-family: var(--font-mono);
  font-size: 0.88rem;
  line-height: 1.7;
  color: #cbd5e1;
  overflow-x: auto;
}
.code-pane { display: none; }
.code-pane.active { display: block; }
.c-kw { color: #f43f5e; font-weight: 600; }
.c-str { color: #34d399; }
.c-num { color: #fbbf24; }
.c-fn { color: #60a5fa; }
.c-var { color: #e2e8f0; }
.c-com { color: #64748b; font-style: italic; }
.c-tag { color: #c084fc; }

.terminal-live-bar {
  background: rgba(6, 10, 20, 0.95);
  border-top: 1px solid var(--border-subtle);
  padding: 12px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.78rem;
  color: var(--text-tertiary);
}
.terminal-latency {
  color: #10b981;
  font-family: var(--font-mono);
  font-weight: 700;
}

/* Stats / Metrics Bar */
.metrics-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  padding: 24px;
  background: var(--bg-card);
  border: 1px solid var(--border-subtle);
  border-radius: 16px;
  margin-bottom: 90px;
}
.metric-col {
  text-align: center;
}
.metric-val {
  font-size: 2.2rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #f8fafc;
  line-height: 1.1;
  margin-bottom: 4px;
}
.metric-lbl {
  font-size: 0.82rem;
  color: var(--text-secondary);
  font-weight: 500;
}

/* Bento Grid Architecture / Audiences */
.section {
  padding: 60px 0;
}
.section-tag {
  font-family: var(--font-mono);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--cyan);
  letter-spacing: 0.1em;
  margin-bottom: 8px;
}
.section-head {
  text-align: center;
  margin-bottom: 50px;
}
.section-title {
  font-size: clamp(1.8rem, 3.5vw, 2.6rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #f8fafc;
  margin-bottom: 12px;
}
.section-sub {
  font-size: 1.05rem;
  color: var(--text-secondary);
  max-width: 620px;
  margin: 0 auto;
}

.bento-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-bottom: 40px;
}
.bento-card {
  background: var(--bg-card);
  border: 1px solid var(--border-subtle);
  border-radius: 18px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.bento-card:hover {
  transform: translateY(-4px);
  border-color: var(--border-strong);
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.6);
}
.bento-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--cyan), transparent);
  opacity: 0;
  transition: opacity 0.25s;
}
.bento-card:hover::before { opacity: 1; }

.bento-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  margin-bottom: 20px;
}
.bento-num {
  font-family: var(--font-mono);
  font-size: 0.75rem;
  color: var(--cyan);
  font-weight: 700;
  margin-bottom: 8px;
}
.bento-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: #f8fafc;
  margin-bottom: 12px;
  letter-spacing: -0.02em;
}
.bento-desc {
  font-size: 0.92rem;
  color: var(--text-secondary);
  line-height: 1.6;
  margin-bottom: 24px;
}
.bento-list {
  list-style: none;
  font-size: 0.86rem;
  color: #cbd5e1;
  border-top: 1px solid var(--border-subtle);
  padding-top: 18px;
  margin-bottom: 24px;
}
.bento-list li {
  padding: 6px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.bento-list li span.check {
  color: #10b981;
  font-weight: bold;
}

/* Pricing Ladder (Synced with Admin Settings & PayPal) */
.pricing-ladder {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
  margin-bottom: 50px;
}
.pricing-card {
  background: var(--bg-card);
  border: 1px solid var(--border-subtle);
  border-radius: 16px;
  padding: 26px 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  transition: all 0.25s;
}
.pricing-card:hover {
  transform: translateY(-4px);
  border-color: var(--border-strong);
  box-shadow: 0 15px 30px -10px rgba(0,0,0,0.5);
}
.pricing-card.featured {
  background: linear-gradient(180deg, rgba(14, 25, 48, 0.9) 0%, rgba(10, 16, 32, 0.9) 100%);
  border-color: #38bdf8;
  box-shadow: 0 0 30px rgba(56, 189, 248, 0.2);
}
.pricing-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: #38bdf8;
  color: #040814;
  font-family: var(--font-mono);
  font-size: 0.68rem;
  font-weight: 800;
  padding: 3px 12px;
  border-radius: 20px;
  text-transform: uppercase;
  white-space: nowrap;
}
.p-tier {
  font-family: var(--font-mono);
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-tertiary);
  margin-bottom: 4px;
}
.p-cost {
  font-size: 2.2rem;
  font-weight: 800;
  color: #f8fafc;
  line-height: 1.1;
  letter-spacing: -0.03em;
  margin-bottom: 2px;
}
.p-cost span {
  font-size: 0.9rem;
  color: var(--text-tertiary);
  font-weight: 500;
}
.p-sub {
  font-size: 0.75rem;
  color: var(--text-tertiary);
  margin-bottom: 16px;
}
.p-specs {
  list-style: none;
  font-size: 0.82rem;
  color: var(--text-secondary);
  border-top: 1px solid var(--border-subtle);
  padding-top: 14px;
  margin-bottom: 24px;
}
.p-specs li {
  padding: 5px 0;
  display: flex;
  gap: 6px;
}
.p-specs li strong { color: #f8fafc; }

/* FAQ Section */
.faq-wrap {
  max-width: 820px;
  margin: 0 auto;
}
.faq-item {
  border-bottom: 1px solid var(--border-subtle);
  padding: 22px 0;
}
.faq-item summary {
  cursor: pointer;
  font-weight: 700;
  font-size: 1.05rem;
  list-style: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #f8fafc;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after {
  content: "+";
  font-family: var(--font-mono);
  font-size: 1.3rem;
  color: var(--cyan);
}
.faq-item[open] summary::after { content: "−"; }
.faq-item p {
  margin-top: 12px;
  font-size: 0.92rem;
  color: var(--text-secondary);
  line-height: 1.65;
}

/* Footer */
footer {
  border-top: 1px solid var(--border-subtle);
  background: #04060d;
  padding: 50px 0 40px;
  margin-top: 60px;
  font-size: 0.88rem;
  color: var(--text-tertiary);
}
.footer-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}
.footer-links {
  display: flex;
  gap: 24px;
}
.footer-links a:hover { color: #f8fafc; }

	/* Mobile Responsiveness */
	@media (max-width: 1080px) {
	  .pricing-ladder { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
	  .bento-grid { grid-template-columns: 1fr; }
	}
	@media (max-width: 768px) {
	  .hero { padding: 48px 0 36px; }
	  .nav-menu { display: none; }
	  .hero-tag { font-size: 0.72rem; padding: 5px 12px; gap: 6px; }
	  .hero-title { font-size: 1.95rem; line-height: 1.25; margin-bottom: 16px; }
	  .hero-desc { font-size: 0.92rem; line-height: 1.6; margin-bottom: 28px; }
	  .hero-cta-group { flex-direction: column; width: 100%; max-width: 360px; margin: 0 auto 44px; gap: 12px; }
	  .hero-cta-group .btn { width: 100%; padding: 13px 20px; font-size: 0.92rem; }
	  .metrics-strip { grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 18px 12px; }
	  .metric-val { font-size: 1.45rem; }
	  .metric-lbl { font-size: 0.72rem; }
	  .terminal-tabs { width: 100%; overflow-x: auto; scrollbar-width: none; }
	  .terminal-tabs::-webkit-scrollbar { display: none; }
	  .code-pane { font-size: 0.76rem; padding: 16px 14px; }
	}
	@media (max-width: 540px) {
	  .container { padding: 0 16px; }
	  .nav-inner { height: 58px; }
	  .brand-pill { display: none; }
	  .nav-actions { gap: 8px; }
	  .nav-actions .btn { padding: 6px 11px; font-size: 0.78rem; white-space: nowrap; }
	  .hero { padding: 36px 0 28px; }
	  .hero-tag { font-size: 0.68rem; padding: 4px 10px; gap: 5px; flex-wrap: wrap; justify-content: center; border-radius: 12px; }
	  .hero-title { font-size: 1.72rem; line-height: 1.24; letter-spacing: -0.025em; }
	  .hero-desc { font-size: 0.88rem; line-height: 1.55; }
	  .metrics-strip { grid-template-columns: 1fr 1fr; gap: 10px; }
	  .metric-col { padding: 8px; }
	  .footer-inner { flex-direction: column; text-align: center; }
	  .footer-links { justify-content: center; flex-wrap: wrap; gap: 16px; }
	}
</style>
</head>
<body>

<div class="bg-ambient">
  <div class="ambient-glow-1"></div>
  <div class="ambient-glow-2"></div>
  <div class="grid-mesh"></div>
</div>

<!-- Sticky Navigation -->
<header class="nav-wrap">
  <div class="container">
    <div class="nav-inner">
      <div class="brand-group">
        <a href="/" aria-label="Teak.Email">
          <img src="/logo.png" alt="Teak.Email" class="brand-logo" />
        </a>
        <div class="brand-pill">
          <span class="pulse-dot"></span>
          <span>Runtime Live</span>
        </div>
      </div>

      <nav class="nav-menu">
        <a href="#runtime" class="nav-item">Protocol</a>
        <a href="#audiences" class="nav-item">Workflows</a>
        <a href="#pricing" class="nav-item">Pricing</a>
        <a href="#faq" class="nav-item">FAQ</a>
      </nav>

      <div class="nav-actions">
        <a href="/login.php" class="btn btn-ghost btn-sm">Log In</a>
        <a href="/signup.php" class="btn btn-primary btn-sm">Start for $<?= htmlspecialchars((string)($t1['price'] ?? 1)) ?>/mo</a>
      </div>
    </div>
  </div>
</header>

<!-- Hero Section -->
<main>
<section class="hero">
  <div class="container">
	    <div class="hero-tag">
	      <span>● Protocol v2.4</span>
	      <span>•</span>
	      <span>Sub-50ms OTP</span>
	      <span>•</span>
	      <span>MCP Native</span>
	    </div>

    <h1 class="hero-title">
      The Clean Email Runtime for<br class="d-desk">
      <span class="gradient-text">Autonomous AI Agents</span>,<br class="d-desk">
      <span class="nowrap">Multi-Brand Ops</span> &amp; Developers.
    </h1>

    <p class="hero-desc">
      One unified email engine. Equip Claude/Cursor AI with native MCP email tools, manage client brand inboxes in one consolidated stream, and test transactional app flows in an isolated private sandbox.
    </p>

    <div class="hero-cta-group">
      <a href="/signup.php" class="btn btn-glow">Deploy Your First Inbox →</a>
      <a href="/api_keys.php" class="btn btn-ghost">View MCP & REST API Docs</a>
    </div>

    <!-- AI Runtime Code Terminal Simulator -->
    <div class="runtime-terminal-wrap" id="runtime">
      <div class="terminal-header">
        <div class="terminal-dots">
          <span class="dot dot-red"></span>
          <span class="dot dot-yellow"></span>
          <span class="dot dot-green"></span>
          <span style="margin-left: 8px; font-family: var(--font-mono); font-size: 0.78rem; color: #64748b">teak-runtime-v2 ~ bash</span>
        </div>
        <div class="terminal-tabs">
          <button type="button" class="terminal-tab-btn active" onclick="switchTab('mcp', this)">MCP Tool (AI)</button>
          <button type="button" class="terminal-tab-btn" onclick="switchTab('python', this)">Python SDK</button>
          <button type="button" class="terminal-tab-btn" onclick="switchTab('curl', this)">cURL (Extract OTP)</button>
          <button type="button" class="terminal-tab-btn" onclick="switchTab('stream', this)">Unified Feed</button>
        </div>
      </div>

      <div class="terminal-body">
        <!-- Tab 1: MCP Tool -->
        <div id="pane-mcp" class="code-pane active">
          <span class="c-com">// Claude Desktop / Cursor / OpenDevin MCP Integration</span><br>
          <span class="c-kw">{</span><br>
          &nbsp;&nbsp;<span class="c-str">"mcpServers"</span>: <span class="c-kw">{</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="c-str">"teak-email"</span>: <span class="c-kw">{</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-str">"command"</span>: <span class="c-str">"npx"</span>,<br>
          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-str">"args"</span>: [<span class="c-str">"-y"</span>, <span class="c-str">"codeinbox-mcp"</span>],<br>
          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="c-str">"env"</span>: <span class="c-kw">{</span> <span class="c-str">"TEAK_API_KEY"</span>: <span class="c-str">"cib_live_9f3c2e8a7b1d..."</span> <span class="c-kw">}</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="c-kw">}</span><br>
          &nbsp;&nbsp;<span class="c-kw">}</span><br>
          <span class="c-kw">}</span><br><br>
          <span class="c-com"># Agent Prompt: "Read incoming auth email and give me the 6-digit confirmation code"</span><br>
          <span class="c-fn">→ Result:</span> <span class="c-str">"OTP Token Extracted: 749-182"</span> <span class="c-var">(Execution latency: 38ms)</span>
        </div>

        <!-- Tab 2: Python SDK -->
        <div id="pane-python" class="code-pane">
          <span class="c-kw">import</span> <span class="c-var">requests</span><br><br>
          <span class="c-com"># Create new dynamic mailbox in 1 line</span><br>
          <span class="c-var">resp</span> = <span class="c-var">requests</span>.<span class="c-fn">post</span>(<span class="c-str">"https://teak.email/api/inboxes"</span>, <span class="c-var">headers</span>={<br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="c-str">"Authorization"</span>: <span class="c-str">"Bearer cib_live_9f3c2e8a..."</span><br>
          }, <span class="c-var">json</span>={<span class="c-str">"domain"</span>: <span class="c-str">"toohumid.com"</span>, <span class="c-str">"local_part"</span>: <span class="c-str">"agent-runner"</span>})<br><br>
          <span class="c-fn">print</span>(<span class="c-var">resp</span>.<span class="c-fn">json</span>())<br>
          <span class="c-com"># Output: {"ok": true, "email": "agent-runner@toohumid.com", "password": "..."}</span>
        </div>

        <!-- Tab 3: cURL -->
        <div id="pane-curl" class="code-pane">
          <span class="c-kw">curl</span> -X GET <span class="c-str">"https://teak.email/api/inboxes/agent@toohumid.com/otp/1"</span> \<br>
          &nbsp;&nbsp;-H <span class="c-str">"Authorization: Bearer cib_live_9f3c2e8a..."</span><br><br>
          <span class="c-com"># Output JSON:</span><br>
          <span class="c-kw">{</span><br>
          &nbsp;&nbsp;<span class="c-str">"ok"</span>: <span class="c-kw">true</span>,<br>
          &nbsp;&nbsp;<span class="c-str">"email"</span>: <span class="c-str">"agent@toohumid.com"</span>,<br>
          &nbsp;&nbsp;<span class="c-str">"otp"</span>: <span class="c-str">"582910"</span>,<br>
          &nbsp;&nbsp;<span class="c-str">"summary"</span>: <span class="c-str">"Your security code is 582910"</span><br>
          <span class="c-kw">}</span>
        </div>

        <!-- Tab 4: Unified Feed -->
        <div id="pane-stream" class="code-pane">
          <span class="c-com">// Real-time consolidated multi-domain websocket & stream</span><br>
          <span class="c-kw">{</span><br>
          &nbsp;&nbsp;<span class="c-str">"stream_id"</span>: <span class="c-str">"stream_global_mesh"</span>,<br>
          &nbsp;&nbsp;<span class="c-str">"active_inboxes"</span>: <span class="c-num">25</span>,<br>
          &nbsp;&nbsp;<span class="c-str">"unread_total"</span>: <span class="c-num">3</span>,<br>
          &nbsp;&nbsp;<span class="c-str">"security"</span>: <span class="c-kw">{</span> <span class="c-str">"spf"</span>: <span class="c-str">"PASS"</span>, <span class="c-str">"dkim"</span>: <span class="c-str">"PASS"</span>, <span class="c-str">"dmarc"</span>: <span class="c-str">"PASS"</span> <span class="c-kw">}</span><br>
          <span class="c-kw">}</span>
        </div>
      </div>

      <div class="terminal-live-bar">
        <span>● Active Mail Node: <strong style="color:#f8fafc">core-sgp1.teak.email</strong></span>
        <span class="terminal-latency">⚡ Latency: 12ms (Operational)</span>
      </div>
    </div>

    <!-- Performance Metrics Strip -->
    <div class="metrics-strip">
      <div class="metric-col">
        <div class="metric-val">&lt;50ms</div>
        <div class="metric-lbl">Deterministic OTP Parsing</div>
      </div>
      <div class="metric-col">
        <div class="metric-val">100%</div>
        <div class="metric-lbl">SPF / DKIM / DMARC Compliance</div>
      </div>
      <div class="metric-col">
        <div class="metric-val">AES-256</div>
        <div class="metric-lbl">Encrypted-at-Rest Security</div>
      </div>
      <div class="metric-col">
        <div class="metric-val">PayPal</div>
        <div class="metric-lbl">Instant Live Subscriptions</div>
      </div>
    </div>
  </div>
</section>

<!-- Bento Grid: Workflows & Architecture -->
<section id="audiences" class="section">
  <div class="container">
    <div class="section-head">
      <div class="section-tag">01 // Architectural Workflows</div>
      <h2 class="section-title">Engineered for 3 High-Growth Demands</h2>
      <p class="section-sub">Ditch consumer webmail limits, blacklisted temp mail, and expensive enterprise contracts.</p>
    </div>

    <div class="bento-grid">
      <!-- Bento 1 -->
      <div class="bento-card">
        <div>
          <div class="bento-icon" style="background:rgba(56, 189, 248, 0.12);color:#38bdf8">🌐</div>
          <div class="bento-num">01 / BUSINESS OPERATORS</div>
          <h3 class="bento-title">Unified Multi-Brand Inboxes</h3>
          <p class="bento-desc">Consolidate all client & venture email addresses into one central stream. Auto-sync domains from Spaceship, Cloudflare, or Namecheap without DNS headache.</p>
          <ul class="bento-list">
            <li><span class="check">✓</span> 1 Screen to view all client communications</li>
            <li><span class="check">✓</span> Automated receipt & invoice OCR parsing</li>
            <li><span class="check">✓</span> IMAP / SMTP credentials for every mailbox</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=3" class="btn btn-ghost btn-sm">Explore Multi-Brand Inboxes →</a>
      </div>

      <!-- Bento 2 -->
      <div class="bento-card" style="border-color:rgba(139, 92, 246, 0.35);background:linear-gradient(180deg, rgba(17, 18, 40, 0.9) 0%, rgba(12, 14, 28, 0.9) 100%)">
        <div>
          <div class="bento-icon" style="background:rgba(139, 92, 246, 0.15);color:#a855f7">🤖</div>
          <div class="bento-num" style="color:#a855f7">02 / AUTONOMOUS AI AGENTS</div>
          <h3 class="bento-title">Native MCP Server & OTP Tool</h3>
          <p class="bento-desc">Equip Claude Desktop, Cursor, and LLM automation loops with instant email reading. Extract 4-8 digit verification codes with sub-50ms deterministic speed.</p>
          <ul class="bento-list">
            <li><span class="check">✓</span> Official <code style="color:#38bdf8">codeinbox-mcp</code> npm package</li>
            <li><span class="check">✓</span> Automated authentication loops & signup bots</li>
            <li><span class="check">✓</span> Zero captcha or phone verification hurdles</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=2" class="btn btn-primary btn-sm">Get MCP API Key &amp; Inboxes →</a>
      </div>

      <!-- Bento 3 -->
      <div class="bento-card">
        <div>
          <div class="bento-icon" style="background:rgba(16, 185, 129, 0.12);color:#10b981">⚡</div>
          <div class="bento-num" style="color:#10b981">03 / SOFTWARE DEVELOPERS</div>
          <h3 class="bento-title">Private Testing Sandbox</h3>
          <p class="bento-desc">A private Mailinator alternative for QA engineers. Test transactional email signups, password resets, and HTML templates without spam leaks.</p>
          <ul class="bento-list">
            <li><span class="check">✓</span> High IP reputation (no false spam drops)</li>
            <li><span class="check">✓</span> Inspect raw MIME RFC822 headers & HTML</li>
            <li><span class="check">✓</span> REST API ready for CI/CD pipelines</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=1" class="btn btn-ghost btn-sm">Launch Developer Sandbox →</a>
      </div>
    </div>
  </div>
</section>

<!-- Pricing Ladder Section (Dynamic Data from Database & PayPal Integration) -->
<section id="pricing" class="section">
  <div class="container">
    <div class="section-head">
      <div class="section-tag">02 // Flexible Pricing</div>
      <h2 class="section-title">Transparent Plans. Start at $<?= htmlspecialchars((string)($t1['price'] ?? 1)) ?>/mo.</h2>
      <p class="section-sub">Simple subscriptions via PayPal. Change plan or cancel anytime with non-expiring credits.</p>
    </div>

    <div class="pricing-ladder">
      <!-- Tier 1 -->
      <div class="pricing-card">
        <div>
          <div class="p-tier">TIER 01 / STARTER</div>
          <div class="p-cost">$<?= htmlspecialchars((string)($t1['price'] ?? 1)) ?><span>/mo</span></div>
          <div class="p-sub">billed via PayPal</div>
          <ul class="p-specs">
            <li><strong><?= number_format((int)($t1['credits'] ?? 3000)) ?></strong> credits / mo</li>
            <li><strong><?= htmlspecialchars((string)($t1['inbox_slots'] ?? 3)) ?></strong> active inbox slots</li>
            <li>Basic REST API access</li>
            <li><?= htmlspecialchars((string)($t1['retention'] ?? 7)) ?>-day retention</li>
            <li>Shared domain pool (<?= htmlspecialchars((string)($t1['domains'] ?? 1)) ?> dom)</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=1" class="btn btn-ghost btn-sm" style="width:100%">Start for $<?= htmlspecialchars((string)($t1['price'] ?? 1)) ?>/mo</a>
      </div>

      <!-- Tier 2: Pro Builder (Featured) -->
      <div class="pricing-card featured">
        <span class="pricing-badge">Most Popular</span>
        <div>
          <div class="p-tier" style="color:var(--cyan)">TIER 02 / PRO BUILDER</div>
          <div class="p-cost" style="color:#38bdf8">$<?= htmlspecialchars((string)($t2['price'] ?? 7)) ?><span>/mo</span></div>
          <div class="p-sub">billed via PayPal</div>
          <ul class="p-specs">
            <li><strong><?= number_format((int)($t2['credits'] ?? 25000)) ?></strong> credits / mo</li>
            <li><strong><?= htmlspecialchars((string)($t2['inbox_slots'] ?? 25)) ?></strong> active inboxes</li>
            <li><strong>Full API + MCP Server</strong></li>
            <li><?= htmlspecialchars((string)($t2['retention'] ?? 14)) ?>-day retention</li>
            <li>Custom domain sync (<?= htmlspecialchars((string)($t2['domains'] ?? 3)) ?> doms)</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=2" class="btn btn-primary btn-sm" style="width:100%">Start Pro Plan →</a>
      </div>

      <!-- Tier 3 -->
      <div class="pricing-card">
        <div>
          <div class="p-tier">TIER 03 / BUSINESS</div>
          <div class="p-cost">$<?= htmlspecialchars((string)($t3['price'] ?? 17)) ?><span>/mo</span></div>
          <div class="p-sub">billed via PayPal</div>
          <ul class="p-specs">
            <li><strong><?= number_format((int)($t3['credits'] ?? 70000)) ?></strong> credits / mo</li>
            <li><strong><?= htmlspecialchars((string)($t3['inbox_slots'] ?? 70)) ?></strong> active inboxes</li>
            <li>Full API + MCP Server</li>
            <li><?= htmlspecialchars((string)($t3['retention'] ?? 30)) ?>-day retention</li>
            <li>Multiple domains (<?= htmlspecialchars((string)($t3['domains'] ?? 5)) ?> doms)</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=3" class="btn btn-ghost btn-sm" style="width:100%">Start Business</a>
      </div>

      <!-- Tier 4 -->
      <div class="pricing-card">
        <div>
          <div class="p-tier">TIER 04 / SCALE</div>
          <div class="p-cost">$<?= htmlspecialchars((string)($t4['price'] ?? 27)) ?><span>/mo</span></div>
          <div class="p-sub">billed via PayPal</div>
          <ul class="p-specs">
            <li><strong><?= number_format((int)($t4['credits'] ?? 125000)) ?></strong> credits / mo</li>
            <li><strong><?= htmlspecialchars((string)($t4['inbox_slots'] ?? 125)) ?></strong> inbox slots</li>
            <li>Priority API + MCP routing</li>
            <li><?= htmlspecialchars((string)($t4['retention'] ?? 45)) ?>-day retention</li>
            <li>High rate limit (<?= htmlspecialchars((string)($t4['domains'] ?? 10)) ?> doms)</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=4" class="btn btn-ghost btn-sm" style="width:100%">Start Scale Plan</a>
      </div>

      <!-- Tier 5 -->
      <div class="pricing-card">
        <div>
          <div class="p-tier">TIER 05 / ENTERPRISE</div>
          <div class="p-cost">$<?= htmlspecialchars((string)($t5['price'] ?? 37)) ?><span>/mo</span></div>
          <div class="p-sub">billed via PayPal</div>
          <ul class="p-specs">
            <li><strong><?= number_format((int)($t5['credits'] ?? 190000)) ?></strong> credits / mo</li>
            <li><strong><?= htmlspecialchars((string)($t5['inbox_slots'] ?? 190)) ?></strong> inbox slots</li>
            <li>Dedicated mail cluster IP</li>
            <li><?= htmlspecialchars((string)($t5['retention'] ?? 60)) ?>-day retention</li>
            <li>VIP SLA support (<?= htmlspecialchars((string)($t5['domains'] ?? 20)) ?> doms)</li>
          </ul>
        </div>
        <a href="/checkout.php?tier=5" class="btn btn-ghost btn-sm" style="width:100%">Start Enterprise</a>
      </div>
    </div>
  </div>
</section>

<!-- FAQ Section -->
<section id="faq" class="section">
  <div class="container">
    <div class="section-head">
      <div class="section-tag">03 // Questions & Support</div>
      <h2 class="section-title">Frequently Asked Questions</h2>
    </div>

    <div class="faq-wrap">
      <details class="faq-item" open>
        <summary>How does the PayPal subscription and credit allocation work?</summary>
        <p>When you select a plan tier, checkout is processed securely via PayPal REST API. Once verified, your account tier and credits are instantly updated in realtime. Credits never expire and carry over permanently.</p>
      </details>

      <details class="faq-item">
        <summary>How do I connect Teak Email with Claude Desktop or Cursor AI?</summary>
        <p>Simply install our official MCP server (<code>npx -y codeinbox-mcp</code>) and provide your Teak API key. Your AI agents will automatically gain tools to list inboxes, receive emails, and extract OTP codes natively.</p>
      </details>

      <details class="faq-item">
        <summary>Can I attach my own custom domain names?</summary>
        <p>Yes. You can add any domain you own by adding 3 simple DNS records (MX, SPF TXT, and MTA-STS). We also support auto-importing domains from registrar APIs such as Spaceship, Cloudflare, and Namecheap.</p>
      </details>

      <details class="faq-item">
        <summary>Is Teak Email private and spam-free?</summary>
        <p>Unlike public disposable email sites where anyone can read messages, all Teak Email mailboxes are strictly isolated and protected by user authentication. All sensitive records are encrypted at rest with AES-256-GCM.</p>
      </details>
    </div>
  </div>
</section>
</main>

<!-- Footer -->
<footer>
  <div class="container">
    <div class="footer-inner">
      <div style="display:flex;align-items:center;gap:10px">
        <img src="/logo.png" alt="Teak.Email" style="height:20px;opacity:0.8" />
        <span>© <?= date('Y') ?> Teak Email. The modern clean email infrastructure.</span>
      </div>
      <div class="footer-links">
        <a href="/terms.php">Terms of Service</a>
        <a href="/privacy.php">Privacy Policy</a>
        <a href="/login.php">Log In</a>
        <a href="/signup.php">Register</a>
      </div>
    </div>
  </div>
</footer>

<script>
function switchTab(paneId, btn) {
  document.querySelectorAll('.terminal-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.code-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  const target = document.getElementById('pane-' + paneId);
  if (target) target.classList.add('active');
}
</script>

</body>
</html>
