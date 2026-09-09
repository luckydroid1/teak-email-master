<?php
/**
 * db.php — PDO singleton + helper.
 */
declare(strict_types=1);

function cfg(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg()['db'];
        $pdo = new PDO($c['dsn'], $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Jalankan schema.sql (idempotent). */
function db_migrate(): void {
    $sql = file_get_contents(__DIR__ . '/../schema.sql');
    if ($sql === false) return;
    db()->exec($sql);
}
