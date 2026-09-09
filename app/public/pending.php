<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require __DIR__ . '/_layout.php';

$email = htmlspecialchars($_GET['email'] ?? '');
page_header('Check Your Email');
?>
<div class="card" style="max-width:480px;margin:60px auto;text-align:center">
  <div style="font-size:3rem;margin-bottom:16px">📧</div>
  <h2>Check Your Email</h2>
  <?php if ($email): ?>
    <p class="sub">We sent a verification link to:<br><strong style="color:#3b82f6"><?= $email ?></strong></p>
  <?php else: ?>
    <p class="sub">We sent a verification link to your email address.</p>
  <?php endif; ?>

  <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:16px;margin:20px 0;text-align:left">
    <p style="font-size:.9rem;color:#94a3b8;margin-bottom:8px"><strong>What to do next:</strong></p>
    <ol style="font-size:.85rem;color:#cbd5e1;padding-left:20px;line-height:1.8">
      <li>Open your email inbox</li>
      <li>Find the email from <strong>Teak Email</strong></li>
      <li>Click the verification link</li>
      <li>You'll be able to login after verifying</li>
    </ol>
  </div>

  <p style="font-size:.85rem;color:#64748b;margin-top:16px">
    Didn't receive it? Check your spam folder, or
    <a href="/signup.php" style="color:#3b82f6">try a different email</a>.
  </p>

  <div style="margin-top:24px;padding-top:16px;border-top:1px solid #334155">
    <a href="/login.php" class="btn" style="text-decoration:none">Go to Login</a>
  </div>
</div>
<?php page_footer(); ?>
