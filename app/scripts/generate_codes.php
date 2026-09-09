<?php
/**
 * generate_codes.php — generate kode AppSumo (CLI).
 * Usage:
 *   php scripts/generate_codes.php 50 3     # 50 kode tier 3
 *   php scripts/generate_codes.php 10 1 10 5 # 10 kode tier 1, 10 kode tier 5
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/redeem.php';

$args = array_slice($argv, 1);
if (count($args) === 0 || count($args) % 2 !== 0) {
    fwrite(STDERR, "Usage: php generate_codes.php <count> <tier> [count tier ...]\n");
    exit(1);
}

$all = [];
for ($i = 0; $i < count($args); $i += 2) {
    $count = (int)$args[$i];
    $tier = (int)$args[$i + 1];
    $codes = generate_codes($count, $tier);
    foreach ($codes as $c) {
        $all[] = "T$tier\t$c";
    }
    echo "Generated $count codes for tier $tier\n";
}
echo "\n--- Codes ---\n" . implode("\n", $all) . "\n";
