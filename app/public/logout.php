<?php
/**
 * logout.php — logout.
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
logout();
header('Location: /');
exit;
