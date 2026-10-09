<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require __DIR__ . '/_layout.php';

$email = trim($_REQUEST['email'] ?? '');
$redirect = trim($_REQUEST['redirect'] ?? '');
$alert_msg = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {
    if ($email === '') {
        $alert_msg = 'Please provide your registered email address.';
        $alert_type = 'error';
    } else {
        $res = resend_verification_email($email);
        if (!empty($res['error'])) {
            $alert_msg = $res['error'];
            $alert_type = 'error';
        } else {
            $alert_msg = 'A fresh verification link has been sent to ' . htmlspecialchars($email) . '. Please check your inbox and spam folder.';
            $alert_type = 'success';
        }
    }
}

page_header('Check Your Email');
?>
<div class="card" style="max-width:480px;margin:50px auto;text-align:center;padding:32px 24px">
  <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:14px;background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.25);margin-bottom:18px;color:#38bdf8">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="2" y="4" width="20" height="16" rx="2"></rect>
      <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
    </svg>
  </div>

  <h2 style="font-size:1.6rem;font-weight:700;color:#f8fafc;margin-bottom:8px">Check Your Email</h2>
  
  <?php if ($email !== ''): ?>
    <p class="sub" style="margin-bottom:20px">We sent a verification link to:<br><strong style="color:#38bdf8;word-break:break-all"><?= htmlspecialchars($email) ?></strong></p>
  <?php else: ?>
    <p class="sub" style="margin-bottom:20px">We sent a verification link to your email address.</p>
  <?php endif; ?>

  <?php if ($alert_msg !== ''): ?>
    <div style="padding:12px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:18px;text-align:left;<?= $alert_type === 'success' ? 'background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#34d399;' : 'background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#f87171;' ?>">
      <?= htmlspecialchars($alert_msg) ?>
    </div>
  <?php endif; ?>

  <div style="background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;padding:18px 20px;margin:20px 0;text-align:left">
    <p style="font-size:0.85rem;font-weight:600;color:#94a3b8;margin-bottom:10px">What to do next:</p>
    <ol style="font-size:0.82rem;color:#cbd5e1;padding-left:18px;line-height:1.8;margin:0">
      <li>Open your email inbox (and spam/junk folder)</li>
      <li>Find the message from <strong>Teak Email</strong></li>
      <li>Click the verification link</li>
      <li>You'll be able to login immediately</li>
    </ol>
  </div>

  <?php if ($email !== ''): ?>
    <form method="POST" action="/pending.php" style="margin-top:20px">
      <input type="hidden" name="action" value="resend">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:10px">Didn't receive the email or link expired?</p>
      <button type="submit" id="resend-btn" class="btn btn-ghost" style="font-size:0.85rem;padding:9px 18px;border-radius:8px;cursor:pointer;width:100%;max-width:280px">
        Resend Verification Email
      </button>
    </form>
  <?php endif; ?>

  <p style="font-size:0.82rem;color:#64748b;margin-top:18px">
    Wrong email? <a href="/signup.php<?= $redirect !== '' ? '?redirect=' . urlencode($redirect) : '' ?>" style="color:#38bdf8;text-decoration:underline">Try a different address</a>
  </p>

  <div style="margin-top:24px;padding-top:16px;border-top:1px solid #1f2937">
    <a href="/login.php<?= $redirect !== '' ? '?redirect=' . urlencode($redirect) : '' ?>" class="btn btn-primary" style="text-decoration:none;display:inline-block;padding:10px 24px;border-radius:8px">Go to Login</a>
  </div>
</div>

<script>
const resendBtn = document.getElementById('resend-btn');
if (resendBtn) {
  resendBtn.addEventListener('click', function() {
    this.disabled = true;
    this.innerText = 'Sending...';
    this.form.submit();
  });
}
</script>

<?php page_footer(); ?>
