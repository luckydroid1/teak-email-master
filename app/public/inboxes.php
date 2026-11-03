<?php
/**
 * inboxes.php — create / list / manage / reset inboxes.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/redeem.php';
require_once __DIR__ . '/../src/mailcow.php';

$user = require_login();
$uid = (int)$user['id'];
$error = $success = '';
$new_email = null; $new_pw = null;
$reset_email = null; $reset_pw = null;

$tier = user_tier($uid);
$tier_info = tier_info($tier);
$st_redeemed = db()->prepare('SELECT 1 FROM ia_codes WHERE redeemed_by = ?');
$st_redeemed->execute([$uid]);
$has_redeemed = (bool)$st_redeemed->fetch();
$eligible = inbox_eligible_domains($uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid form submission or CSRF token expired. Please try again.';
    } elseif (isset($_POST['delete_email'])) {
        $res = inbox_delete($uid, $_POST['delete_email']);
        ($res['ok'] ?? false) ? $success = 'Inbox deleted successfully' : $error = $res['error'] ?? 'Unknown error';
    } elseif (isset($_POST['reset_password_email'])) {
        $r_email = trim($_POST['reset_password_email']);
        if (inbox_owned($uid, $r_email)) {
            $gen_pw = bin2hex(random_bytes(6));
            if (mailcow_reset_mailbox_password($r_email, $gen_pw)) {
                $reset_email = $r_email;
                $reset_pw = $gen_pw;
                $success = "Mailbox password for $r_email has been reset!";
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        } else {
            $error = 'Inbox not found';
        }
    } elseif (isset($_POST['create_inbox'])) {
        $dom = strtolower(trim($_POST['domain'] ?? ''));
        if ($dom === '' && !empty($eligible['all'])) {
            $dom = $eligible['all'][0];
        } elseif (!str_contains($dom, '.')) {
            foreach ($eligible['all'] as $ed) {
                if (str_starts_with($ed, $dom . '.')) {
                    $dom = $ed;
                    break;
                }
            }
        }
        $res = inbox_create($uid, $dom, $_POST['local_part'] ?? '', $tier);
        if (isset($res['ok'])) {
            $new_email = $res['email'];
            $new_pw = $res['password'];
            $success = 'Inbox created successfully!';
        } else {
            $error = $res['error'];
        }
    }
}

$inboxes = inbox_list($uid);
$balance = credit_balance($uid);
$max_slots = $tier_info ? $tier_info['inbox_slots'] : 1;

require_once __DIR__ . '/_layout.php';
page_header('My Inboxes', $user);
alert($error, $success);

if ($new_email):
?>
<div class="card" style="border-color:#22c55e;background:rgba(34,197,94,0.05)">
  <h2 style="color:#86efac;margin-bottom:6px">New Inbox Ready to Receive Mail</h2>
  <p class="sub" style="margin-bottom:14px">Save your IMAP/SMTP credentials below if using an external mail client. You can also view emails right in this dashboard.</p>
  
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
    <div>
      <label>Email Address</label>
      <div style="display:flex;gap:6px">
        <input type="text" readonly value="<?= htmlspecialchars($new_email) ?>" style="margin-bottom:0;font-family:monospace">
        <button class="btn-copy" style="white-space:nowrap" onclick="copyToClipboard('<?= htmlspecialchars($new_email, ENT_QUOTES) ?>', this)">Copy</button>
      </div>
    </div>
    <div>
      <label>Mailbox Password (IMAP/POP3)</label>
      <div style="display:flex;gap:6px">
        <input type="text" readonly value="<?= htmlspecialchars($new_pw) ?>" style="margin-bottom:0;font-family:monospace;color:#fbbf24">
        <button class="btn-copy" style="white-space:nowrap" onclick="copyToClipboard('<?= htmlspecialchars($new_pw, ENT_QUOTES) ?>', this)">Copy</button>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px">
    <a href="/inbox_view.php?email=<?= urlencode($new_email) ?>" class="btn btn-sm btn-success">Open Inbox Viewer →</a>
    <a href="/send.php?from=<?= urlencode($new_email) ?>" class="btn btn-sm" style="background:#2563eb">Compose Email →</a>
  </div>
</div>
<?php endif; ?>

<?php if ($reset_email): ?>
<div class="card" style="border-color:#f59e0b;background:rgba(245,158,11,0.05)">
  <h2 style="color:#fbbf24;margin-bottom:6px">Password Reset for <?= htmlspecialchars($reset_email) ?></h2>
  <p class="sub" style="margin-bottom:12px">Here is your new IMAP/SMTP password. Copy it now:</p>
  <div style="display:flex;gap:6px;max-width:400px">
    <input type="text" readonly value="<?= htmlspecialchars($reset_pw) ?>" style="margin-bottom:0;font-family:monospace;color:#fbbf24">
    <button class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($reset_pw, ENT_QUOTES) ?>', this)">Copy</button>
  </div>
</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h2 style="margin:0">Create New Inbox</h2>
      <span style="font-size:.78rem;color:#94a3b8">Slots: <strong style="color:#f8fafc"><?= count($inboxes) ?> / <?= $max_slots ?></strong></span>
    </div>

    <?php if (false): ?>
      <div style="background:#1c1917;border:1px solid #78350f;padding:14px;border-radius:8px;margin-bottom:12px">
        <p style="color:#fbbf24;font-size:.85rem;margin:0 0 8px"><strong>AppSumo Code Required:</strong> Redeem your license code to activate your inbox slots.</p>
        <a href="/redeem.php" class="btn btn-sm" style="background:#d97706">Redeem Code Now →</a>
      </div>
    <?php else: ?>
    <form method="post" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.textContent='Creating Inbox...';b.disabled=true;}">
      <?php csrf_field(); ?>
      <input type="hidden" name="create_inbox" value="1">

      <?php if ($balance < 60): ?>
      <div style="background:rgba(217,119,6,0.15);border:1px solid #d97706;padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:.82rem;color:#fde68a">
        <strong>Low Credit Balance:</strong> You have <?= number_format($balance) ?> credits remaining. <a href="/redeem.php" style="color:#60a5fa;text-decoration:underline">Redeem code or top-up →</a>
      </div>
      <?php endif; ?>

      <label>Choose Domain</label>
      <?php
      $default_dom = !empty($eligible['pool']) ? $eligible['pool'][0] : (!empty($eligible['custom']) ? $eligible['custom'][0] : 'toohumid.com');
      ?>
      <div class="custom-domain-picker" style="position:relative;width:100%;margin-bottom:14px">
        <input type="hidden" name="domain" id="selected-domain-val" value="<?= htmlspecialchars($default_dom) ?>" required>
        <button type="button" id="domain-picker-btn" onclick="toggleDomainPicker()" style="width:100%;height:44px;background:#060a12;border:1px solid #334155;border-radius:8px;padding:0 14px;color:#f8fafc;font-size:0.9rem;display:flex;justify-content:space-between;align-items:center;cursor:pointer;text-align:left;box-sizing:border-box">
          <span id="domain-picker-label" style="font-family:monospace;font-size:0.88rem;color:#60a5fa">@<?= htmlspecialchars($default_dom) ?></span>
          <span style="font-size:0.75rem;color:#94a3b8">▼</span>
        </button>
        <div id="domain-picker-menu" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:#0d1322;border:1px solid #3b82f6;border-radius:8px;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.6);padding:6px">
          <div style="padding:4px;position:sticky;top:0;background:#0d1322;z-index:2;border-bottom:1px solid #1f2937;margin-bottom:4px">
            <input type="text" id="domain-search-input" placeholder="Search domain..." oninput="filterDomainList(this.value)" style="margin:0;padding:6px 10px;font-size:0.82rem;width:100%;box-sizing:border-box;background:#060a12;border-color:#334155;color:#f8fafc">
          </div>
          <div id="domain-options-list">
            <?php if (!empty($eligible['pool'])): ?>
              <div style="font-size:0.7rem;font-weight:700;color:#64748b;padding:6px 8px 2px;text-transform:uppercase;letter-spacing:0.05em">Shared Domains</div>
              <?php foreach ($eligible['pool'] as $d): ?>
                <div class="domain-opt-item" data-domain="<?= htmlspecialchars(strtolower($d)) ?>" onclick="selectDomainOption('<?= htmlspecialchars($d) ?>')" style="padding:8px 10px;border-radius:6px;cursor:pointer;font-family:monospace;font-size:0.85rem;color:#cbd5e1;transition:background 0.1s" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  @<?= htmlspecialchars($d) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($eligible['custom'])): ?>
              <div style="font-size:0.7rem;font-weight:700;color:#64748b;padding:8px 8px 2px;text-transform:uppercase;letter-spacing:0.05em">Your Verified Custom Domains (<?= count($eligible['custom']) ?>)</div>
              <?php foreach ($eligible['custom'] as $d): ?>
                <div class="domain-opt-item" data-domain="<?= htmlspecialchars(strtolower($d)) ?>" onclick="selectDomainOption('<?= htmlspecialchars($d) ?>')" style="padding:8px 10px;border-radius:6px;cursor:pointer;font-family:monospace;font-size:0.85rem;color:#cbd5e1;transition:background 0.1s" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  @<?= htmlspecialchars($d) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php
      $st_pend_cnt = db()->prepare("SELECT COUNT(*) FROM ia_user_domains WHERE user_id = ? AND status != 'verified'");
      $st_pend_cnt->execute([$uid]);
      $pending_domains_count = (int)$st_pend_cnt->fetchColumn();
      ?>
      <?php if ($pending_domains_count > 0): ?>
        <div style="font-size:0.78rem;color:#94a3b8;margin:-8px 0 14px">
          Have more domains in Spaceship? <a href="/domains.php#required-dns-box" style="color:#38bdf8">Point MX to mail.teak.email</a> to activate them here (<?= $pending_domains_count ?> pending setup).
        </div>
      <?php endif; ?>

      <script>
      function toggleDomainPicker() {
        const m = document.getElementById('domain-picker-menu');
        if (!m) return;
        const isHidden = (m.style.display === 'none' || m.style.display === '');
        m.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
          const inp = document.getElementById('domain-search-input');
          if (inp) { inp.value = ''; filterDomainList(''); inp.focus(); }
        }
      }
      function selectDomainOption(dom) {
        document.getElementById('selected-domain-val').value = dom;
        document.getElementById('domain-picker-label').textContent = '@' + dom;
        document.getElementById('domain-picker-menu').style.display = 'none';
      }
      function filterDomainList(q) {
        q = (q || '').toLowerCase().trim();
        document.querySelectorAll('.domain-opt-item').forEach(el => {
          const d = el.getAttribute('data-domain') || '';
          el.style.display = (q === '' || d.includes(q)) ? 'block' : 'none';
        });
      }
      document.addEventListener('click', function(e) {
        const box = document.querySelector('.custom-domain-picker');
        if (box && !box.contains(e.target)) {
          const m = document.getElementById('domain-picker-menu');
          if (m) m.style.display = 'none';
        }
      });
      </script>

      <label>Username / Local Part</label>
      <input type="text" name="local_part" placeholder="e.g. support, orders.2026, bot-01" required pattern="[a-z0-9][a-z0-9._-]{0,62}[a-z0-9]" title="Lowercase letters, numbers, dots, hyphens" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '')" autofocus>

	      <div style="background:#0a0e1a;padding:10px 12px;border-radius:8px;border:1px solid #1f2937;margin-bottom:14px;font-size:.78rem;color:#94a3b8">
	        Available Balance: <strong style="color:#60a5fa"><?= number_format($balance) ?> credits</strong> · Active Plan: <strong style="color:#cbd5e1"><?= htmlspecialchars($tier_info['name'] ?? ('Tier ' . $tier)) ?></strong>
	      </div>

      <button type="submit" class="btn" style="width:100%">Create Instant Inbox</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <h2 style="margin:0">Your Inboxes (<?= count($inboxes) ?>)</h2>
      <?php if (!empty($inboxes)): ?>
        <?php
        $user_inbox_domains = array_values(array_unique(array_filter(array_map(function($i) {
            return explode('@', $i['email_address'])[1] ?? '';
        }, $inboxes))));
        ?>
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <?php if (count($user_inbox_domains) > 1): ?>
            <select id="inbox-domain-filter" onchange="filterInboxes()" style="margin:0;padding:6px 10px;font-size:.8rem;background:#060a12;border:1px solid #334155;border-radius:6px;color:#e2e8f0;width:auto">
              <option value="">All Domains</option>
              <?php foreach ($user_inbox_domains as $ud): ?>
                <option value="<?= htmlspecialchars(strtolower($ud)) ?>">@<?= htmlspecialchars($ud) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <input type="text" id="inbox-search" placeholder="Search inboxes..." oninput="filterInboxes()" style="margin-bottom:0;padding:6px 10px;font-size:.8rem;width:140px">
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($inboxes)): ?>
      <div style="text-align:center;padding:30px 10px">
        <div style="font-size:2rem;margin-bottom:6px"></div>
        <p style="color:#94a3b8;font-size:.88rem;margin:0">You haven't created any inboxes yet.</p>
        <p style="color:#64748b;font-size:.78rem;margin-top:4px">Use the form on the left to get your first email address.</p>
      </div>
    <?php else: ?>
      <div id="inbox-list-container">
        <?php foreach ($inboxes as $in): ?>
        <div class="mb inbox-card-item" data-email="<?= strtolower(htmlspecialchars($in['email_address'])) ?>">
          <div style="flex:1;min-width:180px">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <span class="e"><?= htmlspecialchars($in['email_address']) ?></span>
              <button type="button" class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?>', this)">Copy</button>
            </div>
            <div class="m">Retention: <?= (int)$in['retention_days'] ?>d · Exp: <?= htmlspecialchars($in['expires_at'] ?? 'Permanent') ?></div>
          </div>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:4px">
            <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>&folder=inbox" class="btn-sm btn-success" style="padding:6px 10px">Inbox</a>
            <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>&folder=archive" class="btn-sm btn-ghost" style="padding:6px 10px;border-color:#475569">Archive</a>
            <a href="/sent.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm btn-ghost" style="padding:6px 10px">Sent</a>
            <a href="/inbox_view.php?email=<?= urlencode($in['email_address']) ?>&folder=spam" class="btn-sm btn-ghost" style="padding:6px 10px;color:#fca5a5;border-color:#7f1d1d">Spam</a>
            <a href="/send.php?from=<?= urlencode($in['email_address']) ?>" class="btn-sm" style="background:#2563eb;padding:6px 10px">Send</a>

            <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Reset mailbox password for <?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?>?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="reset_password_email" value="<?= htmlspecialchars($in['email_address']) ?>">
              <button type="submit" class="btn-sm btn-ghost" style="padding:6px 10px" title="Reset Mailbox Password">Reset</button>
            </form>

            <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Permanently delete <?= htmlspecialchars($in['email_address'], ENT_QUOTES) ?> and all its emails?')">
              <?php csrf_field(); ?>
              <input type="hidden" name="delete_email" value="<?= htmlspecialchars($in['email_address']) ?>">
              <button type="submit" class="btn-danger" style="padding:6px 10px" title="Delete Inbox">Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function filterInboxes() {
  const query = (document.getElementById('inbox-search')?.value || '').toLowerCase().trim();
  const domainFilter = (document.getElementById('inbox-domain-filter')?.value || '').toLowerCase().trim();
  const items = document.querySelectorAll('.inbox-card-item');
  items.forEach(el => {
    const email = el.getAttribute('data-email') || '';
    const matchQuery = (query === '' || email.includes(query));
    const matchDomain = (domainFilter === '' || email.endsWith('@' + domainFilter));
    el.style.display = (matchQuery && matchDomain) ? '' : 'none';
  });
}
</script>

<?php page_footer(); ?>
