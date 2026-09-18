<?php
/**
 * admin/transactions.php — PayPal transactions and ledger log.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/_admin_layout.php';

$user = require_admin();

$pdo = db();

// Fetch PayPal transactions
$payments = $pdo->query("
    SELECT p.*, u.email 
    FROM ia_payments p 
    LEFT JOIN ia_users u ON p.user_id = u.id 
    ORDER BY p.id DESC 
    LIMIT 100
")->fetchAll();

admin_header('Transactions', $user, 'transactions');
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="margin:0">PayPal Order & Payment History</h2>
        <span style="font-size:0.85rem;color:#94a3b8">Latest 100 transactions</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:0.88rem">
            <thead>
                <tr style="border-bottom:2px solid #374151;text-align:left;color:#94a3b8">
                    <th style="padding:10px 8px">ID</th>
                    <th style="padding:10px 8px">Date</th>
                    <th style="padding:10px 8px">User</th>
                    <th style="padding:10px 8px">PayPal Order ID</th>
                    <th style="padding:10px 8px">Tier</th>
                    <th style="padding:10px 8px">Amount</th>
                    <th style="padding:10px 8px">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:24px;color:#64748b">
                            No PayPal transactions recorded yet. Once users upgrade via PayPal checkout, transactions will appear here.
                        </td>
                    </tr>
                <?php else: foreach ($payments as $p): ?>
                    <tr style="border-bottom:1px solid #1f2937">
                        <td style="padding:10px 8px;color:#64748b">#<?= $p['id'] ?></td>
                        <td style="padding:10px 8px;color:#94a3b8"><?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></td>
                        <td style="padding:10px 8px;font-weight:600"><?= htmlspecialchars($p['email'] ?? 'User #'.$p['user_id']) ?></td>
                        <td style="padding:10px 8px;font-family:monospace;color:#38bdf8"><?= htmlspecialchars($p['order_id']) ?></td>
                        <td style="padding:10px 8px">
                            <span style="background:#1e293b;border:1px solid #334155;color:#fbbf24;font-size:0.75rem;padding:2px 6px;border-radius:8px">Tier <?= $p['tier'] ?></span>
                        </td>
                        <td style="padding:10px 8px;font-weight:700;color:#f59e0b">$<?= number_format((float)$p['amount'], 2) ?> <?= htmlspecialchars($p['currency']) ?></td>
                        <td style="padding:10px 8px">
                            <span style="padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:700;background:<?= $p['status']==='completed'?'rgba(34,197,94,0.15)':'rgba(245,158,11,0.15)' ?>;color:<?= $p['status']==='completed'?'#4ade80':'#fbbf24' ?>">
                                <?= strtoupper($p['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
admin_footer();
