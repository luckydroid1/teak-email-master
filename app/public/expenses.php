<?php
/**
 * expenses.php — Modern Financial Invoices & Receipts Management.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/inbox.php';
require_once __DIR__ . '/../src/receipt.php';
require_once __DIR__ . '/../src/mailcow.php';

$user = require_login();
$uid = (int)$user['id'];

$inboxes = inbox_list($uid);
$selected_inbox = $_GET['inbox'] ?? ($inboxes[0]['email_address'] ?? '');
$filter_currency = $_GET['currency'] ?? 'all';
$action = $_GET['action'] ?? '';

// Handle export to CSV
if ($action === 'export_csv' && !empty($selected_inbox)) {
    if (!inbox_owned($uid, $selected_inbox)) {
        http_response_code(403);
        die('Forbidden');
    }
    $all_messages = mailcow_fetch_inbox($selected_inbox);
    $filename = 'expenses_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $selected_inbox) . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['UID', 'Date', 'Vendor / Merchant', 'Currency', 'Amount', 'Invoice / Reference #', 'Subject', 'From Email']);

    foreach ($all_messages as $m) {
        $raw = mailcow_fetch_message($selected_inbox, (int)$m['uid']);
        $rec = extract_receipt($raw ?? '');
        if ($rec['is_receipt']) {
            if ($filter_currency !== 'all' && $rec['currency'] !== $filter_currency) {
                continue;
            }
            fputcsv($out, [
                $m['uid'],
                $rec['date'] ?: ($m['date'] ?? ''),
                $rec['vendor'] ?: 'Unknown',
                $rec['currency'] ?: 'USD',
                $rec['amount'] !== null ? number_format((float)$rec['amount'], 2, '.', '') : '0.00',
                $rec['invoice_number'] ?: '-',
                $m['subject'] ?? '',
                $m['from'] ?? ''
            ]);
        }
    }
    fclose($out);
    exit;
}

// Fetch and extract receipts for the selected inbox
$receipt_items = [];
$total_expenses_by_currency = [];
$total_receipt_count = 0;

if (!empty($selected_inbox) && inbox_owned($uid, $selected_inbox)) {
    $all_messages = mailcow_fetch_inbox($selected_inbox);
    foreach ($all_messages as $m) {
        $raw = mailcow_fetch_message($selected_inbox, (int)$m['uid']);
        $rec = extract_receipt($raw ?? '');
        if ($rec['is_receipt']) {
            $total_receipt_count++;
            $curr = $rec['currency'] ?: 'USD';
            $amt = $rec['amount'] ?? 0.0;
            if (!isset($total_expenses_by_currency[$curr])) {
                $total_expenses_by_currency[$curr] = 0.0;
            }
            $total_expenses_by_currency[$curr] += (float)$amt;

            if ($filter_currency !== 'all' && $curr !== $filter_currency) {
                continue;
            }

            $receipt_items[] = [
                'uid' => $m['uid'],
                'date' => $rec['date'] ?: ($m['date'] ?? '-'),
                'vendor' => $rec['vendor'] ?: 'Unknown Vendor',
                'currency' => $curr,
                'amount' => $amt,
                'invoice_no' => $rec['invoice_number'] ?: '-',
                'subject' => $m['subject'] ?? '(No Subject)',
                'from' => $m['from'] ?? '',
                'summary' => $rec['summary']
            ];
        }
    }
}

require_once __DIR__ . '/_layout.php';
page_header('Receipts & Expenses', $user);
?>

<div style="max-width:880px;margin:10px auto 40px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:0 0 4px">Company Expenses & Receipts</h1>
      <p style="color:#94a3b8;font-size:0.88rem;margin:0">Automated invoice scanning and structured financial bookkeeping.</p>
    </div>
    <div style="display:flex;gap:8px">
      <?php if (!empty($selected_inbox) && count($receipt_items) > 0): ?>
        <a href="/expenses.php?inbox=<?= urlencode($selected_inbox) ?>&currency=<?= urlencode($filter_currency) ?>&action=export_csv" class="btn btn-sm btn-success">Export CSV</a>
      <?php endif; ?>
      <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Dashboard</a>
    </div>
  </div>

  <!-- Inbox Selector & Filter Bar -->
  <?php if (empty($inboxes)): ?>
    <div class="card" style="text-align:center;padding:40px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #334155;margin-bottom:20px">
      <h3 style="margin:0 0 6px;color:#f1f5f9">No Active Inboxes</h3>
      <p style="color:#94a3b8;font-size:0.85rem;max-width:400px;margin:0 auto 14px">
        Create an inbox first to start tracking receipts and invoices automatically.
      </p>
      <a href="/dashboard.php" class="btn btn-sm btn-primary">Create Your First Inbox →</a>
    </div>
  <?php else: ?>
  <div class="card" style="margin-bottom:20px;padding:16px 20px;background:#111827">
    <form method="GET" action="/expenses.php" style="display:grid;grid-template-columns:1.5fr 1fr auto;gap:10px;align-items:center;margin:0">
      <div>
        <label style="font-size:0.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;display:block">Select Mailbox</label>
        <select name="inbox" onchange="this.form.submit()" style="margin:0;background:#060a12;border-color:#334155">
          <?php foreach ($inboxes as $in): ?>
            <option value="<?= htmlspecialchars($in['email_address']) ?>" <?= $selected_inbox === $in['email_address'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($in['email_address']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label style="font-size:0.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;display:block">Currency</label>
        <select name="currency" onchange="this.form.submit()" style="margin:0;background:#060a12;border-color:#334155">
          <option value="all" <?= $filter_currency === 'all' ? 'selected' : '' ?>>All Currencies</option>
          <?php foreach (array_keys($total_expenses_by_currency) as $curr_code): ?>
            <option value="<?= htmlspecialchars($curr_code) ?>" <?= $filter_currency === $curr_code ? 'selected' : '' ?>>
              <?= htmlspecialchars($curr_code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="align-self:end">
        <noscript><button type="submit" class="btn btn-sm">Filter</button></noscript>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Total Summary Card -->
  <?php if (!empty($total_expenses_by_currency)): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ($total_expenses_by_currency as $curr => $total): ?>
      <div class="card" style="padding:16px 20px;border-left:4px solid #10b981;margin-bottom:0">
        <div style="font-size:0.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase">Total <?= htmlspecialchars($curr) ?></div>
        <div style="font-size:1.5rem;font-weight:800;color:#86efac;margin-top:4px"><?= htmlspecialchars($curr) ?> <?= number_format((float)$total, 2) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Receipts List -->
  <div class="card">
    <h2 style="font-size:1.15rem;color:#f8fafc;margin:0 0 16px">Extracted Invoices (<?= count($receipt_items) ?>)</h2>

    <?php if (empty($receipt_items)): ?>
      <div style="text-align:center;padding:40px 16px;background:#0a0e1a;border-radius:10px;border:1px dashed #334155">
        <div style="font-size:2.2rem;margin-bottom:8px"></div>
        <h3 style="margin:0 0 6px;color:#f1f5f9">No Invoices Detected</h3>
        <p style="color:#94a3b8;font-size:0.85rem;max-width:400px;margin:0 auto">
          When this inbox receives Stripe receipts, PayPal statements, or merchant invoices, they will automatically parse here.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($receipt_items as $item): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:#0a0e1a;border:1px solid #1f2937;border-radius:10px;margin-bottom:10px;flex-wrap:wrap;gap:12px">
          <div>
            <div style="font-weight:700;font-size:1rem;color:#f8fafc;display:flex;align-items:center;gap:8px">
              <span><?= htmlspecialchars($item['vendor']) ?></span>
              <span style="font-size:0.75rem;padding:2px 8px;border-radius:8px;background:rgba(59,130,246,0.15);color:#93c5fd;font-weight:600">Inv #<?= htmlspecialchars($item['invoice_no']) ?></span>
            </div>
            <div style="font-size:0.8rem;color:#94a3b8;margin-top:4px">
              <?= htmlspecialchars($item['date']) ?> · <?= htmlspecialchars(substr($item['subject'], 0, 50)) ?>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:12px">
            <div style="font-size:1.2rem;font-weight:800;color:#86efac;text-align:right">
              <?= htmlspecialchars($item['currency']) ?> <?= number_format((float)$item['amount'], 2) ?>
            </div>
            <a href="/inbox_view.php?email=<?= urlencode($selected_inbox) ?>&msg=<?= (int)$item['uid'] ?>" class="btn-sm btn-ghost">View Raw →</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php page_footer(); ?>
