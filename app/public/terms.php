<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require __DIR__ . '/_layout.php';
page_header('Terms of Service');
?>
<div class="card" style="max-width:680px;margin:40px auto;line-height:1.7">
    <h2>Terms of Service</h2>
    <p style="color:#94a3b8;font-size:0.9em;margin-top:4px">Last updated: August 5, 2026</p>

    <h3>1. Acceptance of Terms</h3>
    <p>By accessing or using Teak Email ("the Service"), you agree to these Terms of Service. If you do not agree, please do not use the Service.</p>

    <h3>2. Description of Service</h3>
    <p>Teak Email provides email inbox management, API access, and MCP (Model Context Protocol) server integration for developers, agencies, and AI agent builders. The Service includes a web dashboard, REST API, and optional MCP server access.</p>

    <h3>3. Account Responsibilities</h3>
    <ul>
        <li>You are responsible for maintaining the security of your account credentials</li>
        <li>You must not share your API keys with unauthorized parties</li>
        <li>You are responsible for all activity that occurs under your account</li>
        <li>You must be at least 18 years old to use this Service</li>
    </ul>

    <h3>4. Acceptable Use</h3>
    <p>You agree NOT to use the Service to:</p>
    <ul>
        <li>Send unsolicited bulk email (spam)</li>
        <li>Conduct phishing or social engineering attacks</li>
        <li>Violate any applicable laws or regulations</li>
        <li>Interfere with or disrupt the Service or servers</li>
        <li>Attempt to gain unauthorized access to other accounts or systems</li>
        <li>Resell the Service without written authorization</li>
    </ul>

    <h3>5. API & MCP Usage</h3>
    <ul>
        <li>API access is subject to rate limits (displayed in your dashboard)</li>
        <li>API keys must be kept secret and rotated regularly</li>
        <li>MCP server access is provided "as-is" for developer integration</li>
        <li>We reserve the right to throttle or suspend API access for abuse</li>
    </ul>

    <h3>6. Data & Privacy</h3>
    <p>Your email content and account data are handled according to our <a href="/privacy.php">Privacy Policy</a>. We do not sell, share, or access your email content except as required for service operation.</p>

    <h3>7. Limitation of Liability</h3>
    <p>Teak Email is provided "as-is" without warranties. We are not liable for any damages arising from use of the Service, including but not limited to data loss, service interruption, or unauthorized access.</p>

    <h3>8. Termination</h3>
    <p>We reserve the right to suspend or terminate accounts that violate these Terms, with or without notice. You may delete your account at any time through the dashboard.</p>

    <h3>9. Changes to Terms</h3>
    <p>We may modify these Terms at any time. Continued use of the Service after changes constitutes acceptance of the new Terms.</p>

    <h3>10. Contact</h3>
    <p>Questions about these Terms? Contact us at <a href="mailto:support@teak.email">support@teak.email</a>.</p>
</div>
<?php page_footer(); ?>
