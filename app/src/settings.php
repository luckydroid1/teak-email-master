<?php
/**
 * settings.php — Dynamic Key-Value configuration management.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Get a setting value by key, falling back to default.
 */
function setting_get(string $key, ?string $default = null): ?string {
    try {
        $st = db()->prepare('SELECT `value` FROM ia_settings WHERE `key` = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        if ($row && $row['value'] !== null) {
            return (string)$row['value'];
        }
    } catch (Exception $e) {
        // Fallback gracefully if table not migrated yet
    }
    return $default;
}

/**
 * Set a setting key-value pair.
 */
function setting_set(string $key, ?string $value): bool {
    try {
        $st = db()->prepare('INSERT INTO ia_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        return $st->execute([$key, $value]);
    } catch (Exception $e) {
        error_log('setting_set error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get multiple settings as associative array.
 */
function settings_get_all(array $keys): array {
    $out = [];
    foreach ($keys as $k => $def) {
        if (is_int($k)) {
            $out[$def] = setting_get($def, null);
        } else {
            $out[$k] = setting_get($k, $def);
        }
    }
    return $out;
}
