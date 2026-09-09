<?php
/**
 * verify.php — verify email via token from email.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

$error = $success = '';
$token = $_GET['token'] ?? '';
if ($token !== '') {
    if (verify_email_token($token)) {
        $success = 'Email verified! You can now login.';
    } else {
        $error = 'Invalid or expired verification link.';
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Verify Email');
alert($error, $success);
?>
<div class="card" style="max-width:400px;margin:40px auto;text-align:center">
  <h2>Email Verification</h2>
  <p style="margin:12px 0"><a href="/login.php" class="btn">Go to Login</a></p>
</div>
<?php page_footer(); ?>
