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
    $verified_user = verify_email_token($token);
    if ($verified_user) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$verified_user['id'];
        audit((int)$verified_user['id'], 'login_auto_verify', 'web');
        header('Location: /getting-started.php');
        exit;
    } else {
        $error = 'Invalid or expired verification link. If you already verified, please log in.';
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
