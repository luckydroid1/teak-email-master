<?php
/**
 * expenses.php — UI Rekapitulasi & Ekstraksi Struk / Invoice (Receipts & Expenses).
 * Fitur delegasi staf finance / perusahaan untuk pembukuan & ekspor CSV.
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
page_header('Expenses & Receipts', $user);
?>

<div class="card" style="margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.35rem;display:flex;align-items:center;gap:8px">
        🧾 Company Expenses & Receipts
      </h1>
      <p class="sub" style="margin-bottom:0;margin-top:4px">
        Automated invoice parsing & expense management for your finance team.
      </p>
    </div>
    <?php if (!empty($selected_inbox) && count($receipt_items) > 0): ?>
      <a href="/expenses.php?inbox=<?= urlencode($selected_inbox) ?>&currency=<?= urlencode($filter_currency) ?>&action=export_csv" class="btn btn-sm btn-success" style="padding:8px 16px;font-size:0.85rem">
        📥 Export to CSV
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Controls: Inbox Selector & Filter -->
<div class="card" style="padding:16px;margin-bottom:20px">
  <form method="GET" action="/expenses.php" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:flex-end">
    <div>
      <label for="inbox_select" style="font-size:0.8rem;color:#94a3b8">Selected Inbox</label>
      <select name="inbox" id="inbox_select" onchange="this.form.submit()" style="margin-bottom:0">
        <?php if (empty($inboxes)): ?>
          <option value="">No inboxes found</option>
        <?php else: ?>
          <?php foreach ($inboxes as $ib): ?>
            <option value="<?= htmlspecialchars($ib['email_address']) ?>" <?= $ib['email_address'] === $selected_inbox ? 'selected' : '' ?>>
              <?= htmlspecialchars($ib['email_address']) ?>
            </option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </div>

    <div>
      <label for="currency_select" style="font-size:0.8rem;color:#94a3b8">Currency Filter</label>
      <select name="currency" id="currency_select" onchange="this.form.submit()" style="margin-bottom:0">
        <option value="all" <?= $filter_currency === 'all' ? 'selected' : '' ?>>All Currencies</option>
        <option value="USD" <?= $filter_currency === 'USD' ? 'selected' : '' ?>>USD ($)</option>
        <option value="IDR" <?= $filter_currency === 'IDR' ? 'selected' : '' ?>>IDR (Rp)</option>
        <option value="EUR" <?= $filter_currency === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
        <option value="GBP" <?= $filter_currency === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
        <option value="SGD" <?= $filter_currency === 'SGD' ? 'selected' : '' ?>>SGD ($)</option>
      </select>
    </div>
  </form>
</div>

<!-- Expense Metric Cards -->
<div class="stats" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:20px">
  <div class="stat">
    <div class="n" style="color:#60a5fa"><?= $total_receipt_count ?></div>
    <div class="l">Receipts Found</div>
  </div>
  <?php if (empty($total_expenses_by_currency)): ?>
    <div class="stat">
      <div class="n" style="color:#94a3b8">$0.00</div>
      <div class="l">Total Tracked</div>
    </div>
  <?php else: ?>
    <?php foreach ($total_expenses_by_currency as $c => $tot): ?>
      <div class="stat">
        <div class="n" style="color:#34d399">
          <?= htmlspecialchars($c) ?> <?= number_format($tot, 2) ?>
        </div>
        <div class="l">Total (<?= htmlspecialchars($c) ?>)</div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Receipt List / Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:16px;border-bottom:1px solid #1f2937;display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:1rem;margin:0;color:#f8fafc">Captured Invoices & Struk</h2>
    <span style="font-size:0.8rem;color:#94a3b8"><?= count($receipt_items) ?> item(s)</span>
  </div>

  <?php if (empty($selected_inbox)): ?>
    <div style="padding:32px;text-align:center;color:#94a3b8">
      Please create an inbox first to track company expenses.
    </div>
  <?php elseif (empty($receipt_items)): ?>
    <div style="padding:32px;text-align:center;color:#94a3b8">
      <p style="font-size:1.1rem;margin-bottom:6px">📭 No receipts detected yet in this inbox</p>
      <p style="font-size:0.85rem;color:#64748b;max-width:480px;margin:0 auto">
        Forward SaaS subscription emails, vendor receipts, or digital invoices to <strong><?= htmlspecialchars($selected_inbox) ?></strong> to automatically parse expenses.
      </p>
    </div>
  <?php else: ?>
    <!-- Responsive Table Container -->
    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
      <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.88rem;min-width:640px">
        <thead>
          <tr style="background:#0b1120;color:#94a3b8;border-bottom:1px solid #1f2937">
            <th style="padding:12px 16px">Vendor / Merchant</th>
            <th style="padding:12px 16px">Invoice #</th>
            <th style="padding:12px 16px">Date</th>
            <th style="padding:12px 16px;text-align:right">Amount</th>
            <th style="padding:12px 16px;text-align:center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receipt_items as $item): ?>
            <tr style="border-bottom:1px solid #1f2937;transition:background .15s">
              <td style="padding:12px 16px">
                <div style="font-weight:600;color:#f8fafc"><?= htmlspecialchars($item['vendor']) ?></div>
                <div style="font-size:0.75rem;color:#64748b;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?= htmlspecialchars($item['subject']) ?>
                </div>
              </td>
              <td style="padding:12px 16px;font-family:monospace;font-size:0.82rem;color:#cbd5e1">
                <?= htmlspecialchars($item['invoice_no']) ?>
              </td>
              <td style="padding:12px 16px;color:#94a3b8;font-size:0.82rem;white-space:nowrap">
                <?= htmlspecialchars($item['date']) ?>
              </td>
              <td style="padding:12px 16px;text-align:right;font-weight:700;color:#34d399;white-space:nowrap">
                <?= htmlspecialchars($item['currency']) ?> <?= number_format((float)$item['amount'], 2) ?>
              </td>
              <td style="padding:12px 16px;text-align:center;white-space:nowrap">
                <a href="/inbox_view.php?email=<?= urlencode($selected_inbox) ?>&uid=<?= (int)$item['uid'] ?>" class="btn-sm btn-ghost" style="padding:4px 10px;font-size:0.75rem">
                  View Email ↗
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:20px;border-color:#334155;background:#0f172a">
  <h3 style="font-size:0.95rem;color:#93c5fd;margin-bottom:6px">🤖 AI Agent & MCP Integration for Staff</h3>
  <p class="sub" style="font-size:0.82rem;margin-bottom:8px">
    Staff finance can automate downloading these receipts using Cursor, Claude, or script via our MCP tools:
  </p>
  <div style="background:#020617;padding:10px 14px;border-radius:6px;font-family:monospace;font-size:0.8rem;color:#38bdf8">
    Tools: <strong>list_receipts(email)</strong> | <strong>get_receipt(email, uid)</strong> | <strong>GET /api/inboxes/{email}/receipts</strong>
  </div>
</div>

<?php page_footer(); ?>
