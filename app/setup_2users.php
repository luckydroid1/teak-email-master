<?php
// setup_2users.php — buat 2 user A & B dengan inbox masing-masing
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/redeem.php';
require_once __DIR__ . '/src/inbox.php';
require_once __DIR__ . '/src/apikey.php';

$pdo = db();

// Bersihkan test sebelumnya
$pdo->prepare("DELETE FROM ia_users WHERE email LIKE 'qa.user.%'")->execute();

// Hash bcrypt untuk password 'QaTest123!' (sama untuk semua user test)
$hash = '$2y$10$5vxcv3mS3buIJXNn0VEsM.KF4zLIzRas2/bfxEeGxGdaO5eE/N3Zu';

foreach (['qa.user.a@gmail.com', 'qa.user.b@gmail.com'] as $email) {
    $pdo->prepare("INSERT INTO ia_users (email, password_hash, status, trust_tier, verified_at) VALUES (?, ?, 'active', 3, NOW())")
        ->execute([$email, $hash]);
}

// Buat inbox untuk User A
$uidA = (int)$pdo->query("SELECT id FROM ia_users WHERE email = 'qa.user.a@gmail.com'")->fetchColumn();
$pdo->prepare("INSERT INTO ia_inboxes (user_id, email_address, local_part, domain, retention_days, expires_at) VALUES (?, ?, ?, ?, 30, DATE_ADD(NOW(), INTERVAL 30 DAY))")
    ->execute([$uidA, 'userA.inbox@jetdigitalpro.com', 'userA.inbox', 'jetdigitalpro.com']);

// Buat inbox untuk User B
$uidB = (int)$pdo->query("SELECT id FROM ia_users WHERE email = 'qa.user.b@gmail.com'")->fetchColumn();
$pdo->prepare("INSERT INTO ia_inboxes (user_id, email_address, local_part, domain, retention_days, expires_at) VALUES (?, ?, ?, ?, 30, DATE_ADD(NOW(), INTERVAL 30 DAY))")
    ->execute([$uidB, 'userB.inbox@jetdigitalpro.com', 'userB.inbox', 'jetdigitalpro.com']);

// Generate API keys
$ka = apikey_create($uidA);
$kb = apikey_create($uidB);

file_put_contents('/tmp/A_KEY', $ka['key']);
file_put_contents('/tmp/B_KEY', $kb['key']);

echo "A_KEY=" . $ka['key'] . "\n";
echo "B_KEY=" . $kb['key'] . "\n";
echo "UID_A=$uidA\n";
echo "UID_B=$uidB\n";