<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require __DIR__ . '/_layout.php';
page_header('Terms of Service');
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
  color: #3b82f6;
  font-weight: bold;
}
.prohibit-list li::before {
  content: "✕";
  color: #f87171 !important;
  font-weight: bold;
}
</style>

<div class="legal-card">
  <h1>Terms of Service</h1>
  <div class="legal-meta">Version 1.2 • Last updated: September 14, 2026</div>

  <div class="legal-section">
    <h2>1. Agreement to Terms</h2>
    <p>By accessing or using Teak Email ("the Service"), you agree to be bound by these Terms. If you disagree with any part, you may not use the Service.</p>
  </div>

  <div class="legal-section">
    <h2>2. Scope of Service</h2>
    <p>Teak Email provides private email inboxes, REST API access, and Model Context Protocol (MCP) server integration for developers, agencies, and autonomous AI agents. Features include inbox generation, automated OTP parsing, and email routing.</p>
  </div>

  <div class="legal-section">
    <h2>3. Account & Security</h2>
    <ul>
      <li>You are responsible for safeguarding your login credentials and API keys.</li>
      <li>API keys grant direct access to your mailbox actions and must never be shared publicly.</li>
      <li>You are fully responsible for all activity conducted under your account.</li>
      <li>Users must be at least 18 years of age or possess legal authority.</li>
    </ul>
  </div>

  <div class="legal-section">
    <h2>4. Acceptable Use Policy</h2>
    <p>Teak Email operates clean, high-reputation mail infrastructure. You agree NOT to:</p>
    <ul class="prohibit-list">
      <li>Send unsolicited bulk marketing or spam email.</li>
      <li>Execute phishing, fraud, or social engineering campaigns.</li>
      <li>Distribute malware, trojans, or unauthorized automation scripts.</li>
      <li>Interfere with server integrity or bypass API rate limits.</li>
      <li>Resell or redistribute platform access without authorization.</li>
    </ul>
  </div>

  <div class="legal-section">
    <h2>5. API & MCP Fair Usage</h2>
    <ul>
      <li>API calls are metered and subject to tier-based hourly rate limits.</li>
      <li>We reserve the right to throttle or suspend keys exhibiting abuse or denial-of-service patterns.</li>
      <li>MCP servers are provided for structured LLM tool calling.</li>
    </ul>
  </div>

  <div class="legal-section">
    <h2>6. Privacy & Data Handling</h2>
    <p>Your incoming email content is private to your account. We never sell or inspect message bodies, except as automated by your own retention and parsing rules. See our <a href="/privacy.php">Privacy Policy</a>.</p>
  </div>

  <div class="legal-section">
    <h2>7. Limitation of Liability</h2>
    <p>Teak Email is provided on an "as-is" basis. We are not liable for indirect damages, service downtime, or third-party email delivery delays outside our control.</p>
  </div>

  <div class="legal-section">
    <h2>8. Account Termination</h2>
    <p>We may suspend accounts that violate our acceptable use policy. You may delete your account and all associated data at any time via <a href="/delete_account.php" style="color:#f87171">self-service deletion</a>.</p>
  </div>

  <div class="legal-section">
    <h2>9. Contact</h2>
    <p>Questions regarding these terms? Reach us at <a href="mailto:support@teak.email">support@teak.email</a>.</p>
  </div>
</div>

<?php page_footer(); ?>
