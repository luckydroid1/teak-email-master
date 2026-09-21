<?php
/**
 * admin/users.php — User management, role elevation, and tier credits adjustments.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/credits.php';
require_once __DIR__ . '/_admin_layout.php';

$user = require_admin();

$pdo = db();
$msg_success = null;
$msg_error = null;

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	    if (!csrf_validate()) {
	        $msg_error = 'Invalid security token or session expired. Please refresh and try again.';
	    } else {
	        $action = $_POST['action'] ?? '';
	        $targetId = (int)($_POST['target_user_id'] ?? 0);

	        if ($action === 'toggle_admin' && $targetId > 0) {
	            $st = $pdo->prepare('SELECT is_admin, email FROM ia_users WHERE id = ?');
	            $st->execute([$targetId]);
	            $target = $st->fetch();
	            if ($target) {
	                $newAdmin = empty($target['is_admin']) ? 1 : 0;
	                $pdo->prepare('UPDATE ia_users SET is_admin = ? WHERE id = ?')->execute([$newAdmin, $targetId]);
	                audit($user['id'], 'admin_toggle_role', 'web', "target_id=$targetId is_admin=$newAdmin");
	                $msg_success = "Admin role for {$target['email']} " . ($newAdmin ? 'granted' : 'revoked') . '.';
	            }
	        } elseif ($action === 'adjust_credits' && $targetId > 0) {
	            $delta = (int)($_POST['credit_delta'] ?? 0);
	            if ($delta !== 0) {
	                credit_mutate($targetId, $delta, 'admin_adjust', "by_admin={$user['id']}");
	                audit($user['id'], 'admin_credit_adjust', 'web', "target_id=$targetId delta=$delta");
	                $msg_success = "Adjusted credits by " . ($delta > 0 ? "+$delta" : "$delta") . " for user #$targetId.";
	            }
	        } elseif ($action === 'set_tier' && $targetId > 0) {
	            $tier = (int)($_POST['tier'] ?? 1);
	            $pdo->prepare('UPDATE ia_users SET trust_tier = ? WHERE id = ?')->execute([$tier, $targetId]);
	            audit($user['id'], 'admin_set_tier', 'web', "target_id=$targetId tier=$tier");
	            $msg_success = "User #$targetId tier updated to Tier $tier.";
	        }
	    }
	}

// Fetch all users with balance
$users = $pdo->query("
    SELECT u.*, 
        COALESCE((SELECT balance_after FROM ia_credit_ledger WHERE user_id = u.id ORDER BY id DESC LIMIT 1), 0) as balance,
        (SELECT COUNT(*) FROM ia_inboxes WHERE user_id = u.id AND status = 'active') as active_inboxes
    FROM ia_users u 
    ORDER BY u.id DESC
")->fetchAll();

admin_header('Users & Roles', $user, 'users');
?>

<?php if ($msg_success): ?>
    <div class="alert alert-s" style="margin-bottom:20px">
        <span><?= htmlspecialchars($msg_success) ?></span>
    </div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="margin:0">Registered Accounts</h2>
        <span style="font-size:0.85rem;color:#94a3b8">Total: <strong><?= count($users) ?></strong> users</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:0.88rem">
            <thead>
                <tr style="border-bottom:2px solid #374151;text-align:left;color:#94a3b8">
                    <th style="padding:10px 8px">ID</th>
                    <th style="padding:10px 8px">Email</th>
                    <th style="padding:10px 8px">Role</th>
                    <th style="padding:10px 8px">Tier</th>
                    <th style="padding:10px 8px">Credits</th>
                    <th style="padding:10px 8px">Inboxes</th>
                    <th style="padding:10px 8px">Status</th>
                    <th style="padding:10px 8px;text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom:1px solid #1f2937">
                        <td style="padding:10px 8px;color:#64748b">#<?= $u['id'] ?></td>
                        <td style="padding:10px 8px;font-weight:600"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="padding:10px 8px">
                            <?php if (!empty($u['is_admin'])): ?>
                                <span style="background:rgba(245,158,11,0.15);color:#f59e0b;padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:700">👑 Admin</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:0.8rem">User</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 8px">
                            <span style="background:#1e293b;border:1px solid #334155;color:#fbbf24;font-size:0.75rem;padding:2px 6px;border-radius:8px">Tier <?= $u['trust_tier'] ?></span>
                        </td>
                        <td style="padding:10px 8px;font-family:monospace;color:#38bdf8"><?= number_format((int)$u['balance']) ?></td>
                        <td style="padding:10px 8px"><?= $u['active_inboxes'] ?></td>
                        <td style="padding:10px 8px">
                            <span style="padding:2px 6px;border-radius:4px;font-size:0.72rem;background:<?= $u['status']==='active'?'rgba(34,197,94,0.15)':'rgba(239,68,68,0.15)' ?>;color:<?= $u['status']==='active'?'#4ade80':'#f87171' ?>">
                                <?= htmlspecialchars($u['status']) ?>
                            </span>
                        </td>
                        <td style="padding:10px 8px;text-align:right">
                            <div style="display:inline-flex;gap:6px">
	                                <form method="POST" action="/admin/users.php" style="display:inline" onsubmit="return confirm('Change admin role for this user?')">
	                                    <?php csrf_field(); ?>
	                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" style="padding:4px 8px;font-size:0.75rem">
                                        <?= !empty($u['is_admin']) ? 'Demote' : 'Promote Admin' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
admin_footer();
