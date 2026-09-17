<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require __DIR__ . '/_layout.php';
page_header('Privacy Policy');
?>
<style>
.legal-card {
  max-width: 760px;
  margin: 40px auto 60px;
  background: var(--surface, #111827);
  border: 1px solid var(--surface-border, #1f2937);
  border-radius: 14px;
  padding: 40px 36px;
  line-height: 1.7;
}
.legal-card h1 {
  font-size: 1.8rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin-bottom: 4px;
  color: #f8fafc;
}
.legal-meta {
  color: #64748b;
  font-size: 0.85rem;
  font-family: monospace;
  margin-bottom: 28px;
  padding-bottom: 16px;
  border-bottom: 1px solid #1f2937;
}
.legal-section {
  margin-bottom: 28px;
}
.legal-section h2 {
  font-size: 1.15rem;
  font-weight: 700;
  color: #f1f5f9;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.legal-section p {
  color: #94a3b8;
  font-size: 0.94rem;
  margin-bottom: 10px;
}
.legal-section ul {
  list-style: none;
  padding-left: 4px;
}
.legal-section ul li {
  color: #cbd5e1;
  font-size: 0.92rem;
  padding: 4px 0;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.legal-section ul li::before {
  content: "•";
  color: #10b981;
  font-weight: bold;
}
</style>

<div class="legal-card">
  <h1>Privacy Policy</h1>
  <div class="legal-meta">Version 1.2 • Last updated: September 14, 2026</div>

  <div class="legal-section">
    <h2>1. Information We Collect</h2>
    <p>We collect only the minimum data required to deliver the service:</p>
    <ul>
      <li><strong>Account Details:</strong> Email address and a cryptographically salted password hash (bcrypt).</li>
      <li><strong>Authentication Tokens:</strong> API key prefixes for identification and audit tracking.</li>
      <li><strong>Billing Data:</strong> Lifetime Deal voucher redemptions (we do not process or store raw credit cards).</li>
    </ul>
  </div>

  <div class="legal-section">
    <h2>2. Email Content & Privacy</h2>
    <p>Incoming emails received by your mailboxes are isolated to your account. We do not inspect, monetize, or sell your message bodies to advertising networks or AI training datasets. Messages are automatically purged according to your tier retention window (7 to 60 days).</p>
  </div>

  <div class="legal-section">
    <h2>3. How Information Is Used</h2>
    <ul>
      <li>To provision mailboxes and route incoming emails.</li>
      <li>To execute real-time OTP parsing for authenticated API and MCP clients.</li>
      <li>To enforce anti-abuse and rate-limiting safeguards.</li>
    </ul>
  </div>

  <div class="legal-section">
    <h2>4. Security Architecture</h2>
    <p>Teak Email runs on hardened Linux servers with full TLS/HTTPS encryption, strict HTTP-only session cookies, and isolated Mailcow mail storage. DNS and edge protection are routed via Cloudflare.</p>
  </div>

  <div class="legal-section">
    <h2>5. Data Retention & Deletion</h2>
    <p>You maintain full ownership of your data. You can delete specific inboxes, purge message logs, or permanently remove your entire account and all associated records via <a href="/delete_account.php" style="color:#f87171">Self-Service Account Deletion</a>.</p>
  </div>

  <div class="legal-section">
    <h2>6. Contact Us</h2>
    <p>If you have any questions or data privacy inquiries, contact our team at <a href="mailto:support@teak.email">support@teak.email</a>.</p>
  </div>
</div>

<?php page_footer(); ?>
