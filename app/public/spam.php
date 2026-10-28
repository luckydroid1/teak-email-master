<?php
/**
 * spam.php — Dedicated Spam & Quarantine Management.
 * Review filtered junk, restore false positives, and clear unwanted spam.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/spam.php';

$user = require_login();
$uid = (int)$user['id'];
$inboxes = inbox_list($uid);

$selected_email = strtolower(trim($_GET['email'] ?? ''));
if (!empty($selected_email) && !inbox_owned($uid, $selected_email)) {
    $selected_email = '';
}

// Handle spam actions
$flash_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spam_action'])) {
    if (csrf_validate()) {
        $act = $_POST['spam_action'];
        $target_email = strtolower(trim($_POST['target_email'] ?? ''));
        $target_uid = (int)($_POST['target_uid'] ?? 0);

        if (inbox_owned($uid, $target_email)) {
            if ($act === 'restore' && $target_uid > 0) {
                if (spam_restore_to_inbox($target_email, $target_uid)) {
                    $flash_msg = "Email restored to inbox ($target_email).";
                }
            } elseif ($act === 'delete' && $target_uid > 0) {
                if (spam_delete_file($target_email, $target_uid)) {
                    $flash_msg = "Spam email permanently deleted.";
                }
            } elseif ($act === 'empty_all') {
                $junk_list = spam_fetch_junk($target_email);
                foreach ($junk_list as $j) {
                    spam_delete_file($target_email, (int)$j['uid']);
                }
                $flash_msg = "Spam folder emptied for $target_email.";
            }
        }
    }
}

// Collect spam items
$all_spam = [];
$total_spam_count = 0;

$inboxes_to_check = !empty($selected_email) ? [['email_address' => $selected_email]] : $inboxes;

foreach ($inboxes_to_check as $ib) {
    $emb = $ib['email_address'];
    $items = spam_fetch_junk($emb);
    foreach ($items as $item) {
        $item['inbox'] = $emb;
        $all_spam[] = $item;
    }
}

$total_spam_count = count($all_spam);

// Specific message view
$view_msg_id = (int)($_GET['msg'] ?? 0);
$view_item = null;
$view_raw = null;
if ($view_msg_id > 0 && !empty($selected_email)) {
    $view_raw = spam_fetch_message($selected_email, $view_msg_id);
    if ($view_raw !== null) {
        foreach ($all_spam as $it) {
            if ((int)$it['uid'] === $view_msg_id && $it['inbox'] === $selected_email) {
                $view_item = $it;
                break;
            }
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Spam & Quarantine', $user);
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="margin:0;display:flex;align-items:center;gap:10px">
      <span>Spam & Quarantine</span>
      <span style="font-size:0.75rem;background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);padding:3px 10px;border-radius:20px;font-weight:600">🛡️ Auto-Filter Active</span>
    </h1>
    <p style="color:#94a3b8;font-size:0.85rem;margin:4px 0 0">
      Suspicious emails, lottery scams, and unwanted solicitations are automatically isolated here to keep your main inboxes clean.
    </p>
  </div>

  <?php if (!empty($selected_email)): ?>
    <div>
      <form method="post" style="margin:0" onsubmit="return confirm('Empty all spam in <?= htmlspecialchars($selected_email) ?> permanently?')">
        <?php csrf_field(); ?>
        <input type="hidden" name="spam_action" value="empty_all">
        <input type="hidden" name="target_email" value="<?= htmlspecialchars($selected_email) ?>">
        <button type="submit" class="btn btn-sm btn-ghost" style="color:#ef4444;border-color:rgba(239,68,68,0.3)">Empty Spam for <?= htmlspecialchars($selected_email) ?></button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($flash_msg): ?>
  <div class="alert alert-s" style="margin-bottom:16px"><?= htmlspecialchars($flash_msg) ?></div>
<?php endif; ?>

<!-- Filter by inbox pill row -->
<?php if (count($inboxes) > 1): ?>
  <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:10px;margin-bottom:16px;flex-wrap:wrap">
    <a href="/spam.php" class="btn btn-sm <?= empty($selected_email) ? 'btn-primary' : 'btn-ghost' ?>">
      All Inboxes (<?= $total_spam_count ?>)
    </a>
    <?php foreach ($inboxes as $ib): ?>
      <?php
      $cnt = spam_count($ib['email_address']);
      $is_active = ($selected_email === $ib['email_address']);
      ?>
      <a href="/spam.php?email=<?= urlencode($ib['email_address']) ?>" class="btn btn-sm <?= $is_active ? 'btn-warning' : 'btn-ghost' ?>" style="<?= $cnt > 0 ? 'font-weight:700' : 'opacity:0.75' ?>">
        <?= htmlspecialchars($ib['email_address']) ?>
        <span style="background:rgba(255,255,255,0.15);padding:1px 6px;border-radius:10px;font-size:0.72rem;margin-left:4px"><?= $cnt ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($view_raw !== null && $view_item): ?>
<!-- Single Spam Message Viewer -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid #1f2937;padding-bottom:12px;flex-wrap:wrap;gap:10px">
    <a href="/spam.php<?= !empty($selected_email) ? '?email=' . urlencode($selected_email) : '' ?>" class="btn btn-sm btn-ghost">← Back to Spam List</a>

    <div style="display:flex;gap:8px;align-items:center">
      <form method="post" style="display:inline;margin:0">
        <?php csrf_field(); ?>
        <input type="hidden" name="spam_action" value="restore">
        <input type="hidden" name="target_email" value="<?= htmlspecialchars($view_item['inbox']) ?>">
        <input type="hidden" name="target_uid" value="<?= (int)$view_item['uid'] ?>">
        <button type="submit" class="btn btn-sm btn-success" title="Restore to main inbox">✓ Not Spam (Restore to Inbox)</button>
      </form>

      <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Permanently delete this email?')">
        <?php csrf_field(); ?>
        <input type="hidden" name="spam_action" value="delete">
        <input type="hidden" name="target_email" value="<?= htmlspecialchars($view_item['inbox']) ?>">
        <input type="hidden" name="target_uid" value="<?= (int)$view_item['uid'] ?>">
        <button type="submit" class="btn btn-sm btn-ghost" style="color:#ef4444;border-color:rgba(239,68,68,0.4)">🗑 Delete</button>
      </form>
    </div>
  </div>

  <div style="margin-bottom:16px;background:rgba(239,68,68,0.06);padding:14px;border-radius:8px;border:1px solid rgba(239,68,68,0.25)">
    <div style="display:flex;align-items:center;gap:8px;color:#fca5a5;font-size:0.85rem;font-weight:600;margin-bottom:6px">
      <span>⚠️ Isolated in Spam Folder</span>
    </div>
    <div style="font-size:0.85rem;color:#94a3b8">
      <div><strong>Subject:</strong> <span style="color:#f8fafc"><?= htmlspecialchars($view_item['subject'] ?: '(No Subject)') ?></span></div>
      <div><strong>From:</strong> <span style="color:#cbd5e1"><?= htmlspecialchars($view_item['from']) ?></span></div>
      <div><strong>Inbox:</strong> <span style="color:#60a5fa"><?= htmlspecialchars($view_item['inbox']) ?></span></div>
      <div><strong>Date:</strong> <?= htmlspecialchars($view_item['date']) ?></div>
    </div>
  </div>

  <div class="msg-view" style="font-family:monospace;font-size:0.88rem;line-height:1.6;background:#0a0e1a;border-radius:8px;padding:20px;border:1px solid #1f2937;color:#cbd5e1;white-space:pre-wrap;max-height:450px;overflow-y:auto">
    <?= htmlspecialchars($view_raw) ?>
  </div>
</div>

<?php elseif (!empty($all_spam)): ?>
<!-- Spam Email List -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid #1f2937;background:#0d1322;font-size:0.85rem;font-weight:700;color:#94a3b8;display:flex;justify-content:space-between">
    <span>Quarantined Spam Messages (<?= count($all_spam) ?>)</span>
    <span style="font-weight:400;color:#64748b">Click to inspect or restore</span>
  </div>

  <div style="display:flex;flex-direction:column">
    <?php foreach ($all_spam as $s): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #182234;gap:12px;flex-wrap:wrap">
        <a href="/spam.php?email=<?= urlencode($s['inbox']) ?>&msg=<?= (int)$s['uid'] ?>" style="flex:1;min-width:240px;text-decoration:none;color:#e2e8f0">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <span style="font-size:0.75rem;background:#1e293b;color:#94a3b8;padding:2px 8px;border-radius:6px"><?= htmlspecialchars($s['inbox']) ?></span>
            <strong style="color:#f1f5f9;font-size:0.92rem"><?= htmlspecialchars(substr($s['from'] ?? 'Unknown', 0, 30)) ?></strong>
          </div>
          <div style="color:#94a3b8;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars(substr($s['subject'] ?? '(No Subject)', 0, 75)) ?>
          </div>
        </a>

        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:0.78rem;color:#64748b;margin-right:8px"><?= htmlspecialchars(substr($s['date'] ?? '', 0, 16)) ?></span>

          <form method="post" style="display:inline;margin:0">
            <?php csrf_field(); ?>
            <input type="hidden" name="spam_action" value="restore">
            <input type="hidden" name="target_email" value="<?= htmlspecialchars($s['inbox']) ?>">
            <input type="hidden" name="target_uid" value="<?= (int)$s['uid'] ?>">
            <button type="submit" class="btn btn-sm btn-ghost" style="color:#34d399;padding:4px 8px;font-size:0.78rem" title="Restore to Inbox">✓ Restore</button>
          </form>

          <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Permanently delete?')">
            <?php csrf_field(); ?>
            <input type="hidden" name="spam_action" value="delete">
            <input type="hidden" name="target_email" value="<?= htmlspecialchars($s['inbox']) ?>">
            <input type="hidden" name="target_uid" value="<?= (int)$s['uid'] ?>">
            <button type="submit" class="btn btn-sm btn-ghost" style="color:#ef4444;padding:4px 8px;font-size:0.78rem" title="Delete">🗑</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php else: ?>
<!-- Clean State -->
<div class="card" style="text-align:center;padding:50px 20px;background:#0a0e1a;border:1px dashed #334155">
  <div style="font-size:2.6rem;margin-bottom:10px">🛡️</div>
  <h3 style="color:#f8fafc;margin-bottom:6px;font-size:1.15rem">Your Inboxes Are Clean</h3>
  <p style="color:#94a3b8;font-size:0.88rem;max-width:440px;margin:0 auto 16px">
    No spam or quarantined messages detected. The automated spam filter is actively protecting all your custom inboxes.
  </p>
  <div style="display:flex;gap:10px;justify-content:center">
    <a href="/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
    <a href="/inboxes.php" class="btn btn-ghost">View All Inboxes</a>
  </div>
</div>
<?php endif; ?>

<?php
page_footer();
