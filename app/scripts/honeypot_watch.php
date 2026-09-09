<?php
/**
 * honeypot_watch.php — cron: pantau inbox honeypot.
 * Jika ada email masuk ke honeypot → suspend user pemilik + ban IP.
 * Jalankan tiap 5 menit.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/abuse.php';

foreach (cfg()['honeypots'] as $hp) {
    foreach (cfg()['pool_domains'] as $dom) {
        $addr = "$hp@$dom";
        $emails = mailcow_fetch_inbox_silent($addr);
        if (count($emails) === 0) continue;

        // Cari siapa yang create inbox dengan local part ini (harusnya tidak ada)
        $st = db()->prepare('SELECT user_id FROM ia_inboxes WHERE email_address = ? AND status = \'active\'');
        $st->execute([$addr]);
        $owner = $st->fetch();

        if ($owner) {
            db()->prepare("UPDATE ia_users SET status = 'suspended' WHERE id = ?")->execute([$owner['user_id']]);
            db()->prepare("UPDATE ia_inboxes SET status = 'suspended' WHERE email_address = ?")->execute([$addr]);
            audit((int)$owner['user_id'], 'honeypot_suspend', '', $addr);
            echo "[HONEYPOT] Suspended user {$owner['user_id']} for $addr\n";
        } else {
            // Email ke honeypot tanpa owner = someone probing infra → catat
            audit(null, 'honeypot_probe', '', $addr);
            echo "[HONEYPOT] Probe detected on $addr\n";
        }
        // Hapus email honeypot biar ga numpuk
        mailcow_delete_mailbox($addr);
    }
}
echo "[honeypot] done\n";

function mailcow_fetch_inbox_silent(string $email): array {
    $user = escapeshellarg($email);
    $container = escapeshellarg(cfg()['dovecot_container']);
    $out = shell_exec("docker exec $container doveadm search -u $user mailbox INBOX ALL 2>/dev/null");
    if (!$out || trim($out) === '') return [];
    return ['probe'];
}
