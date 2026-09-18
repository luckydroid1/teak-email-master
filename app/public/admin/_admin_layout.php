<?php
/**
 * admin/_admin_layout.php — Admin navigation header and layout helper.
 */
declare(strict_types=1);

function admin_header(string $title, array $user, string $activeTab = 'overview'): void {
    require_once __DIR__ . '/../_layout.php';
    page_header($title . ' — Teak Admin', $user);
    ?>
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="display:flex;align-items:center;gap:8px">
                    <span>👑 Admin Control Center</span>
                    <span style="font-size:0.75rem;padding:2px 8px;border-radius:12px;background:#f59e0b;color:#000;font-weight:700">MASTER</span>
                </h1>
                <p class="sub" style="margin-bottom:0">Manage system configurations, PayPal payment gateways, registered users, and mailbox infrastructure.</p>
            </div>
            <div>
                <a href="/dashboard.php" class="btn btn-sm btn-ghost">← Back to User Area</a>
            </div>
        </div>

        <div style="display:flex;gap:8px;border-bottom:1px solid #1f2937;margin-bottom:24px;overflow-x:auto;padding-bottom:2px">
            <a href="/admin/index.php" class="btn btn-sm <?= $activeTab === 'overview' ? '' : 'btn-ghost' ?>">📊 Overview & Metrics</a>
            <a href="/admin/settings.php" class="btn btn-sm <?= $activeTab === 'settings' ? '' : 'btn-ghost' ?>">💳 PayPal & Settings</a>
            <a href="/admin/users.php" class="btn btn-sm <?= $activeTab === 'users' ? '' : 'btn-ghost' ?>">👥 Users & Roles</a>
            <a href="/admin/transactions.php" class="btn btn-sm <?= $activeTab === 'transactions' ? '' : 'btn-ghost' ?>">💰 Transactions Log</a>
        </div>
    <?php
}

function admin_footer(): void {
    page_footer();
}
