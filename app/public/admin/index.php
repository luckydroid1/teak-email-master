<?php
/**
 * admin/index.php — System metrics & infrastructure overview.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/_admin_layout.php';

$user = require_admin();

$pdo = db();

// Fetch metrics
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM ia_users')->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM ia_users WHERE status = 'active'")->fetchColumn();
$totalInboxes = (int)$pdo->query("SELECT COUNT(*) FROM ia_inboxes WHERE status = 'active'")->fetchColumn();
$totalPayments = (int)$pdo->query("SELECT COUNT(*) FROM ia_payments WHERE status = 'completed'")->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ia_payments WHERE status = 'completed'")->fetchColumn();
$totalApiKeys = (int)$pdo->query("SELECT COUNT(*) FROM ia_api_keys WHERE status = 'active'")->fetchColumn();

// Recent signups
$recentUsers = $pdo->query("SELECT id, email, status, is_admin, trust_tier, created_at FROM ia_users ORDER BY id DESC LIMIT 5")->fetchAll();

// Recent payments
$recentPayments = $pdo->query("SELECT p.*, u.email FROM ia_payments p LEFT JOIN ia_users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 5")->fetchAll();

admin_header('Overview', $user, 'overview');
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px">
    <div class="card" style="margin-bottom:0">
        <div style="font-size:0.8rem;color:#94a3b8;font-weight:600;text-transform:uppercase">Total Users</div>
        <div style="font-size:1.8rem;font-weight:800;color:#38bdf8;margin-top:4px"><?= number_format($totalUsers) ?></div>
        <div style="font-size:0.75rem;color:#64748b"><?= number_format($activeUsers) ?> active</div>
    </div>
    <div class="card" style="margin-bottom:0">
        <div style="font-size:0.8rem;color:#94a3b8;font-weight:600;text-transform:uppercase">Active Inboxes</div>
        <div style="font-size:1.8rem;font-weight:800;color:#4ade80;margin-top:4px"><?= number_format($totalInboxes) ?></div>
        <div style="font-size:0.75rem;color:#64748b">Provisioned in mail engine</div>
    </div>
    <div class="card" style="margin-bottom:0">
        <div style="font-size:0.8rem;color:#94a3b8;font-weight:600;text-transform:uppercase">PayPal Revenue</div>
        <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:4px">$<?= number_format($totalRevenue, 2) ?></div>
        <div style="font-size:0.75rem;color:#64748b"><?= number_format($totalPayments) ?> orders completed</div>
    </div>
    <div class="card" style="margin-bottom:0">
        <div style="font-size:0.8rem;color:#94a3b8;font-weight:600;text-transform:uppercase">Active API Keys</div>
        <div style="font-size:1.8rem;font-weight:800;color:#c084fc;margin-top:4px"><?= number_format($totalApiKeys) ?></div>
        <div style="font-size:0.75rem;color:#64748b">AI Agent MCP endpoints</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">Recent Users</h3>
            <a href="/admin/users.php" class="btn btn-sm btn-ghost">View All →</a>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
                <tr style="border-bottom:1px solid #374151;text-align:left;color:#94a3b8">
                    <th style="padding:6px 0">Email</th>
                    <th style="padding:6px 0">Role</th>
                    <th style="padding:6px 0">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentUsers)): ?>
                    <tr><td colspan="3" style="padding:12px 0;color:#64748b">No users found.</td></tr>
                <?php else: foreach ($recentUsers as $ru): ?>
                    <tr style="border-bottom:1px solid #1f2937">
                        <td style="padding:8px 0;font-weight:500"><?= htmlspecialchars($ru['email']) ?></td>
                        <td style="padding:8px 0"><?= !empty($ru['is_admin']) ? '<span style="color:#f59e0b;font-weight:700">Admin</span>' : 'User' ?></td>
                        <td style="padding:8px 0">
                            <span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:0.72rem;background:<?= $ru['status']==='active'?'rgba(34,197,94,0.15)':'rgba(239,68,68,0.15)' ?>;color:<?= $ru['status']==='active'?'#4ade80':'#f87171' ?>">
                                <?= htmlspecialchars($ru['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">Recent PayPal Orders</h3>
            <a href="/admin/transactions.php" class="btn btn-sm btn-ghost">View All →</a>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
                <tr style="border-bottom:1px solid #374151;text-align:left;color:#94a3b8">
                    <th style="padding:6px 0">User</th>
                    <th style="padding:6px 0">Amount</th>
                    <th style="padding:6px 0">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="3" style="padding:12px 0;color:#64748b">No PayPal transactions recorded yet.</td></tr>
                <?php else: foreach ($recentPayments as $rp): ?>
                    <tr style="border-bottom:1px solid #1f2937">
                        <td style="padding:8px 0;font-weight:500"><?= htmlspecialchars($rp['email'] ?? 'User #'.$rp['user_id']) ?></td>
                        <td style="padding:8px 0;font-weight:700;color:#f59e0b">$<?= number_format((float)$rp['amount'], 2) ?></td>
                        <td style="padding:8px 0">
                            <span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:0.72rem;background:<?= $rp['status']==='completed'?'rgba(34,197,94,0.15)':'rgba(245,158,11,0.15)' ?>;color:<?= $rp['status']==='completed'?'#4ade80':'#fbbf24' ?>">
                                <?= htmlspecialchars($rp['status']) ?>
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
