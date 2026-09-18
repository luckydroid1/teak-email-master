<?php
/**
 * migrate_admin_paypal.php — Apply schema updates for Admin Area & PayPal.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$pdo = db();

echo "Running migrations for Admin Area & PayPal...\n";

// 1. Add is_admin to ia_users if not exists
try {
    $st = $pdo->query("SHOW COLUMNS FROM ia_users LIKE 'is_admin'");
    if (!$st->fetch()) {
        $pdo->exec("ALTER TABLE ia_users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        echo "✓ Added `is_admin` column to `ia_users`.\n";
    } else {
        echo "- `is_admin` column already exists in `ia_users`.\n";
    }
} catch (Exception $e) {
    echo "! Error checking is_admin column: " . $e->getMessage() . "\n";
}

// 2. Create ia_settings table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ia_settings (
      `key` VARCHAR(64) PRIMARY KEY,
      `value` TEXT NULL,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✓ Table `ia_settings` created/verified.\n";
} catch (Exception $e) {
    echo "! Error creating ia_settings table: " . $e->getMessage() . "\n";
}

// 3. Create ia_payments table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ia_payments (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      provider VARCHAR(32) NOT NULL DEFAULT 'paypal',
      order_id VARCHAR(128) NOT NULL UNIQUE,
      tier TINYINT UNSIGNED NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      currency VARCHAR(8) NOT NULL DEFAULT 'USD',
      status ENUM('created','completed','failed','refunded') NOT NULL DEFAULT 'created',
      raw_payload JSON NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_user_payments (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✓ Table `ia_payments` created/verified.\n";
} catch (Exception $e) {
    echo "! Error creating ia_payments table: " . $e->getMessage() . "\n";
}

// 4. Ensure master admin exists (e.g. y3s@gmx.com or first user)
try {
    $st = $pdo->prepare("UPDATE ia_users SET is_admin = 1 WHERE email IN ('y3s@gmx.com', 'admin@teak.email')");
    $st->execute();
    if ($st->rowCount() > 0) {
        echo "✓ Granted admin role to master user.\n";
    } else {
        // If no matching user, promote the earliest user if any
        $pdo->exec("UPDATE ia_users SET is_admin = 1 ORDER BY id ASC LIMIT 1");
        echo "✓ Promoted first user as master admin.\n";
    }
} catch (Exception $e) {
    echo "! Error updating master admin: " . $e->getMessage() . "\n";
}

echo "Migration finished.\n";
