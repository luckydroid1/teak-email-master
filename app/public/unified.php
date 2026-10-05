<?php
/**
 * unified.php — Modern Unified Inbox (All-in-One Stream with Smart Triage).
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/credits.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/otp.php';
require_once __DIR__ . '/../src/receipt.php';
require_once __DIR__ . '/../src/unified.php';

$user = require_login();
$uid = (int)$user['id'];

// AJAX polling endpoint for silent live updates without screen reload
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $stream = fetch_unified_inbox($uid, 10, 40);
    $latest_otp = null;
    $latest_otp_inbox = null;
    foreach ($stream as $msg) {
        if ($msg['is_otp'] && !empty($msg['otp_code'])) {
            $latest_otp = $msg['otp_code'];
            $latest_otp_inbox = $msg['inbox_email'];
            break;
        }
    }
    echo json_encode([
        'ok' => true,
        'count' => count($stream),
        'latest_otp' => $latest_otp,
        'latest_otp_inbox' => $latest_otp_inbox,
        'messages' => array_map(function($m) {
            return [
                'uid' => $m['uid'],
                'inbox' => $m['inbox_email'],
                'domain' => $m['inbox_domain'],
                'from' => htmlspecialchars($m['from'], ENT_QUOTES),
                'subject' => htmlspecialchars($m['subject'], ENT_QUOTES),
                'date' => htmlspecialchars(substr($m['date'], 0, 16), ENT_QUOTES),
                'is_otp' => $m['is_otp'],
                'otp_code' => $m['otp_code'],
                'is_receipt' => $m['is_receipt'],
                'receipt_amt' => $m['receipt_amt'] ? number_format((float)$m['receipt_amt'], 2) : null,
                'receipt_curr' => $m['receipt_curr'],
            ];
        }, $stream)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stream = fetch_unified_inbox($uid, 12, 50);
$inboxes = inbox_list($uid);

$latest_global_otp = null;
$latest_global_otp_inbox = null;
foreach ($stream as $m) {
    if ($m['is_otp'] && !empty($m['otp_code'])) {
        $latest_global_otp = $m['otp_code'];
        $latest_global_otp_inbox = $m['inbox_email'];
        break;
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Unified Inbox', $user);
?>

<style>
.triage-tab {
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  border: 1px solid #374151;
  background: #0f172a;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.triage-tab.active {
  background: #2563eb;
  color: #fff;
  border-color: #3b82f6;
}

.stream-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #0a0e1a;
  border: 1px solid #1f2937;
  border-radius: 10px;
  margin-bottom: 8px;
  text-decoration: none;
  color: #e2e8f0;
  transition: all 0.15s;
}
.stream-row:hover {
  border-color: #3b82f6;
  background: #0c1322;
  transform: translateX(2px);
}

.inbox-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(139, 92, 246, 0.15);
  border: 1px solid rgba(139, 92, 246, 0.3);
  color: #c4b5fd;
  white-space: nowrap;
}

.otp-pill {
  background: #065f46;
  border: 1px solid #10b981;
  color: #a7f3d0;
  font-family: monospace;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  transition: all 0.15s;
}
.otp-pill:hover {
  background: #047857;
  color: #fff;
}
</style>

<div style="max-width:980px;margin:10px auto 40px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px;display:flex;align-items:center;gap:10px">
        <span>📬 Unified Inbox Stream</span>
        <span style="font-size:0.75rem;padding:2px 8px;border-radius:12px;background:rgba(34,197,94,0.2);color:#86efac;font-weight:700">● LIVE</span>
      </h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">Real-time aggregated stream from all <?= count($inboxes) ?> connected inboxes.</p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Dashboard</a>
      <a href="/inboxes.php" class="btn btn-sm btn-ghost">+ New Inbox</a>
    </div>
  </div>

  <!-- Global Latest OTP Hero Banner -->
  <div id="global-otp-banner" style="background:linear-gradient(135deg,rgba(16,185,129,0.12) 0%,rgba(6,78,59,0.25) 100%);border:1px solid rgba(16,185,129,0.4);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:<?= $latest_global_otp ? 'flex' : 'none' ?>;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
    <div>
      <div style="color:#86efac;font-size:0.85rem;font-weight:700;display:flex;align-items:center;gap:6px">
        <span>🔐 Latest Incoming OTP Code</span>
        <span style="font-size:0.72rem;color:#94a3b8;font-weight:normal" id="otp-inbox-source"><?= $latest_global_otp_inbox ? 'Received by ' . htmlspecialchars($latest_global_otp_inbox) : '' ?></span>
      </div>
      <div style="font-size:0.8rem;color:#cbd5e1;margin-top:2px">Click the code below to copy instantly to your clipboard.</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <div id="global-otp-val" style="font-family:monospace;font-size:1.8rem;font-weight:800;letter-spacing:0.15em;color:#a7f3d0;background:#064e3b;padding:4px 14px;border-radius:8px;border:1px solid #10b981;cursor:pointer" onclick="copyUnifiedOtp(this.innerText)">
        <?= htmlspecialchars($latest_global_otp ?? '') ?>
      </div>
      <button type="button" class="btn btn-sm btn-success" onclick="copyUnifiedOtp(document.getElementById('global-otp-val').innerText)">📋 Copy</button>
    </div>
  </div>

  <!-- Smart Triage Tabs & Global Filter -->
  <div class="card" style="margin-bottom:16px;padding:14px 18px;background:#111827">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="triage-tab active" id="tab-all" onclick="filterTriage('all')">
          <span>📨 All Messages</span> (<span id="count-all"><?= count($stream) ?></span>)
        </button>
        <button type="button" class="triage-tab" id="tab-otp" onclick="filterTriage('otp')">
          <span>🔐 Auth & OTPs</span>
        </button>
        <button type="button" class="triage-tab" id="tab-receipts" onclick="filterTriage('receipts')">
          <span>🧾 Invoices</span>
        </button>
      </div>

      <div>
        <input type="text" id="stream-search" placeholder="🔍 Search sender, subject, or inbox..." oninput="searchStream()" style="margin:0;padding:6px 14px;font-size:0.85rem;width:240px;background:#060a12;border-color:#334155">
      </div>
    </div>
  </div>

  <!-- Unified Stream List -->
  <div id="stream-container">
    <?php if (empty($stream)): ?>
      <div class="card" style="text-align:center;padding:50px 20px;background:#0a0e1a;border:1px dashed #334155">
        <div style="font-size:2.6rem;margin-bottom:8px">📭</div>
        <h3 style="color:#f8fafc;margin-bottom:6px">Unified Stream is Empty</h3>
        <p style="color:#94a3b8;font-size:0.88rem;max-width:440px;margin:0 auto 16px">
          Emails received across any of your <?= count($inboxes) ?> mailboxes will appear here automatically.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($stream as $m): ?>
        <a href="/inbox_view.php?email=<?= urlencode($m['inbox_email']) ?>&msg=<?= $m['uid'] ?>" 
           class="stream-row stream-item" 
           data-isotp="<?= $m['is_otp'] ? '1' : '0' ?>"
           data-isreceipt="<?= $m['is_receipt'] ? '1' : '0' ?>"
           data-search="<?= strtolower(htmlspecialchars($m['from'] . ' ' . $m['subject'] . ' ' . $m['inbox_email'])) ?>">
          
          <span class="inbox-badge"><?= htmlspecialchars($m['inbox_email']) ?></span>

          <div style="width:160px;font-weight:600;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars(substr($m['from'] ?: 'Unknown', 0, 24)) ?>
          </div>

          <div style="flex:1;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($m['subject']) ?>
          </div>

          <?php if ($m['is_otp'] && !empty($m['otp_code'])): ?>
            <span class="otp-pill" onclick="event.preventDefault();copyUnifiedOtp('<?= htmlspecialchars($m['otp_code'], ENT_QUOTES) ?>')">
              🔐 <?= htmlspecialchars($m['otp_code']) ?>
            </span>
          <?php elseif ($m['is_receipt']): ?>
            <span style="font-size:0.75rem;padding:2px 8px;border-radius:6px;background:rgba(245,158,11,0.15);color:#fde68a;font-weight:700">
              🧾 <?= htmlspecialchars($m['receipt_curr']) ?> <?= number_format((float)($m['receipt_amt'] ?? 0), 2) ?>
            </span>
          <?php endif; ?>

          <div style="font-size:0.76rem;color:#64748b;white-space:nowrap">
            <?= htmlspecialchars(substr($m['date'], 0, 16)) ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
let currentTriage = 'all';

function filterTriage(category) {
  currentTriage = category;
  document.querySelectorAll('.triage-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + category)?.classList.add('active');
  searchStream();
}

function searchStream() {
  const q = (document.getElementById('stream-search')?.value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.stream-item');
  rows.forEach(r => {
    const isOtp = r.getAttribute('data-isotp') === '1';
    const isReceipt = r.getAttribute('data-isreceipt') === '1';
    const text = r.getAttribute('data-search') || '';

    let matchCategory = true;
    if (currentTriage === 'otp' && !isOtp) matchCategory = false;
    if (currentTriage === 'receipts' && !isReceipt) matchCategory = false;

    const matchText = q === '' || text.includes(q);
    r.style.display = (matchCategory && matchText) ? 'flex' : 'none';
  });
}

function copyUnifiedOtp(val) {
  if (!val) return;
  navigator.clipboard.writeText(val.trim()).then(() => {
    const bannerVal = document.getElementById('global-otp-val');
    if (bannerVal) {
      const orig = bannerVal.textContent;
      bannerVal.textContent = 'COPIED!';
      bannerVal.style.background = '#059669';
      setTimeout(() => {
        bannerVal.textContent = orig;
        bannerVal.style.background = '#064e3b';
      }, 1200);
    }
  });
}

// Background silent live poller every 6 seconds
setInterval(() => {
  if (document.hidden) return;
  fetch('/unified.php?ajax=1')
    .then(res => res.json())
    .then(data => {
      if (data && data.ok && data.latest_otp) {
        const banner = document.getElementById('global-otp-banner');
        const valEl = document.getElementById('global-otp-val');
        const srcEl = document.getElementById('otp-inbox-source');
        if (banner && valEl) {
          valEl.textContent = data.latest_otp;
          if (srcEl && data.latest_otp_inbox) srcEl.textContent = 'Received by ' + data.latest_otp_inbox;
          banner.style.display = 'flex';
        }
      }
    })
    .catch(() => {});
}, 6000);
</script>

<?php page_footer(); ?>
