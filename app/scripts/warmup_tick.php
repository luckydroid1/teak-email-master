<?php
/**
 * warmup_tick.php — Cron script for email warmup.
 * Run every hour: 0 * * * *
 */
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/warmup.php';

echo '[' . date('Y-m-d H:i:s') . '] Warmup tick starting...' . PHP_EOL;

$result = warmup_tick();

if ($result['ok']) {
    echo 'OK: Sent ' . ($result['sent'] ?? 0) . ' warmup emails, ' . ($result['pairs'] ?? 0) . ' pairs' . PHP_EOL;
} else {
    echo 'ERROR: ' . ($result['error'] ?? $result['message'] ?? 'Unknown') . PHP_EOL;
}
