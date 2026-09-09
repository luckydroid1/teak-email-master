<?php
/**
 * daily_rent.php — cron harian:
 * 1. Debit rent inbox aktif (2 credits/inbox/hari)
 * 2. Expire inbox lewat retention
 * 3. Bersihkan mailbox Mailcow yang sudah lama deleted
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/inbox.php';
inbox_daily_rent();
echo "[daily_rent] done\n";
