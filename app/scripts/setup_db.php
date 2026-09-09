<?php
/**
 * setup_db.php — jalankan migrasi schema (CLI).
 * Usage: php scripts/setup_db.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
db_migrate();
echo "Schema OK\n";
