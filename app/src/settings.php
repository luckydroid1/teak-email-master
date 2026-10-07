<?php
/**
 * settings.php — Dynamic Key-Value configuration management with AES-256-GCM encryption at rest.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const SENSITIVE_SETTINGS_KEYS = [
    'paypal_secret',
    'paypal_client_id',
    'paypal_webhook_id',
    'spaceship_api_secret',
    'cloudflare_api_token',
];

/**
 * Encrypt sensitive setting value using AES-256-GCM.
 */
function setting_encrypt(string $plaintext): string {
    if ($plaintext === '') return '';
    $pepper = cfg()['pepper'] ?? 'default_cib_secure_key_2026';
    $key = hash('sha256', $pepper, true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc:gcm:' . base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt sensitive setting value.
 */
function setting_decrypt(string $value): string {
    if (!str_starts_with($value, 'enc:gcm:')) {
        return $value; // Plaintext fallback for legacy records
    }
    $raw = base64_decode(substr($value, 8));
    if (strlen($raw) < 28) return '';
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $pepper = cfg()['pepper'] ?? 'default_cib_secure_key_2026';
    $key = hash('sha256', $pepper, true);
    $dec = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $dec !== false ? $dec : '';
}

/**
 * Get a setting value by key, falling back to default.
 */
function setting_get(string $key, ?string $default = null): ?string {
    try {
        $st = db()->prepare('SELECT `value` FROM ia_settings WHERE `key` = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        if ($row && $row['value'] !== null) {
            $val = (string)$row['value'];
            return in_array($key, SENSITIVE_SETTINGS_KEYS, true) || str_starts_with($val, 'enc:gcm:')
                ? setting_decrypt($val)
                : $val;
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
        $storedValue = $value;
        if ($value !== null && in_array($key, SENSITIVE_SETTINGS_KEYS, true)) {
            $storedValue = setting_encrypt($value);
        }
        $st = db()->prepare('INSERT INTO ia_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        return $st->execute([$key, $storedValue]);
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
