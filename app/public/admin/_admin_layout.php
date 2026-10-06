<?php
/**
 * admin/_admin_layout.php — Admin navigation header and layout helper.
 */
declare(strict_types=1);

function admin_header(string $title, array $user, string $activeTab = 'overview'): void {
    require_once __DIR__ . '/../_layout.php';
    page_header($title . ' — Teak Admin', $user);
    ?>
    <style>
    .admin-header-box {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .admin-header-title {
      flex: 1;
      min-width: 260px;
    }
    .admin-header-title h1 {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: clamp(1.2rem, 4vw, 1.5rem);
      margin-bottom: 6px;
      line-height: 1.3;
    }
    .admin-header-title p {
      margin: 0;
      font-size: 0.85rem;
      color: #94a3b8;
      line-height: 1.5;
    }
    .admin-tabs-nav {
      display: flex;
      flex-wrap: nowrap;
      gap: 8px;
      border-bottom: 1px solid #1f2937;
      margin-bottom: 24px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 8px;
      scrollbar-width: none;
    }
    .admin-tabs-nav::-webkit-scrollbar {
      display: none;
    }
    .admin-tab-item {
      white-space: nowrap;
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      font-size: 0.82rem;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.15s;
    }
    @media (max-width: 640px) {
      .admin-header-box {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .admin-back-btn {
        width: 100%;
        text-align: center;
      }
    }
    </style>

    <div class="container">
        <div class="admin-header-box">
            <div class="admin-header-title">
                <h1>
                    <span>👑 Admin Control Center</span>
                    <span style="font-size:0.7rem;padding:2px 8px;border-radius:12px;background:#f59e0b;color:#000;font-weight:700;letter-spacing:0.5px">MASTER</span>
                </h1>
                <p>Manage system configurations, PayPal payment gateways, registered users, and mailbox infrastructure.</p>
            </div>
            <div>
                <a href="/dashboard.php" class="btn btn-sm btn-ghost admin-back-btn">← Back to User Area</a>
            </div>
        </div>

        <div class="admin-tabs-nav">
            <a href="/admin/index.php" class="admin-tab-item btn btn-sm <?= $activeTab === 'overview' ? '' : 'btn-ghost' ?>">📊 Overview & Metrics</a>
            <a href="/admin/settings.php" class="admin-tab-item btn btn-sm <?= $activeTab === 'settings' ? '' : 'btn-ghost' ?>">💳 PayPal & Settings</a>
            <a href="/admin/users.php" class="admin-tab-item btn btn-sm <?= $activeTab === 'users' ? '' : 'btn-ghost' ?>">👥 Users & Roles</a>
            <a href="/admin/transactions.php" class="admin-tab-item btn btn-sm <?= $activeTab === 'transactions' ? '' : 'btn-ghost' ?>">💰 Transactions Log</a>
        </div>
    <?php
}

function admin_footer(): void {
    page_footer();
}

