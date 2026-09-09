<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require __DIR__ . '/_layout.php';
page_header('Privacy Policy');
?>
<div class="card" style="max-width:680px;margin:40px auto;line-height:1.7">
    <h2>Privacy Policy</h2>
    <p style="color:#94a3b8;font-size:0.9em;margin-top:4px">Last updated: August 5, 2026</p>

    <h3>1. Information We Collect</h3>
    <p>When you create an account, we collect your email address and a hashed version of your password. We do not collect names, phone numbers, or payment information directly — payments are processed through AppSumo.</p>

    <h3>2. Email Content</h3>
    <p>Emails received at your inboxes are stored securely and are accessible only by you. We do not read, sell, or share your email content with third parties. Emails are automatically deleted based on your retention settings.</p>

    <h3>3. How We Use Your Information</h3>
    <ul>
        <li>To provide and maintain the Teak Email service</li>
        <li>To authenticate your account and prevent abuse</li>
        <li>To send important service notifications (e.g., account verification)</li>
    </ul>

    <h3>4. Data Storage & Security</h3>
    <p>Your data is stored on encrypted servers. We use industry-standard security measures including bcrypt password hashing, HTTPS encryption, and secure session cookies. Our infrastructure runs on Mailcow (self-hosted email stack) behind Cloudflare protection.</p>

    <h3>5. Third-Party Services</h3>
    <ul>
        <li><strong>Cloudflare:</strong> CDN, DNS, and DDoS protection</li>
        <li><strong>AppSumo:</strong> Payment processing (we do not store payment data)</li>
    </ul>

    <h3>6. Data Retention</h3>
    <p>Emails are retained based on your inbox settings. Account data is kept as long as your account is active. Upon account deletion, all associated data is permanently removed within 30 days.</p>

    <h3>7. Your Rights</h3>
    <ul>
        <li>Access your data via the dashboard or API</li>
        <li>Delete your inboxes and associated emails at any time</li>
        <li>Delete your account and all associated data via <a href="/delete_account.php">self-service account deletion</a> (requires password confirmation)</li>
        <li>Contact support at <a href="mailto:support@teak.email">support@teak.email</a> for any data requests</li>
    </ul>

    <h3>8. Changes to This Policy</h3>
    <p>We may update this policy from time to time. Significant changes will be communicated via email or dashboard notification.</p>

    <h3>9. Contact</h3>
    <p>Questions about this policy? Contact us at <a href="mailto:support@teak.email">support@teak.email</a>.</p>
</div>
<?php page_footer(); ?>
