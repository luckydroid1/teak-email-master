<?php
/**
 * resellerclub.php — ResellerClub API integration for domain registration.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Get ResellerClub credentials from config.
 */
function resellerclub_config(): array {
    return [
        'user_id'     => cfg()['resellerclub_user_id'] ?? '',
        'api_key'     => cfg()['resellerclub_api_key'] ?? '',
        'customer_id' => cfg()['resellerclub_customer_id'] ?? '',
        'base_url'    => 'https://httpapi.com/reseller/api/v1',
    ];
}

/**
 * Check if ResellerClub credentials are configured.
 */
function resellerclub_configured(): bool {
    $config = resellerclub_config();
    return !empty($config['user_id']) && !empty($config['api_key']) && !empty($config['customer_id']);
}

/**
 * Check if domain is available for registration.
 */
function domain_check(string $name, array $tlds = ['com','net','org','io','co']): array {
    $config = resellerclub_config();
    if (empty($config['user_id']) || empty($config['api_key'])) {
        return [];
    }

    $results = [];
    foreach ($tlds as $tld) {
        $domain = "$name.$tld";
        $url = "{$config['base_url']}/domains/available.json"
             . "?auth-userid={$config['user_id']}"
             . "&api-key={$config['api_key']}"
             . "&domain-name=" . urlencode($name)
             . "&tlds=$tld";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($res['status'])) {
            $results[] = [
                'domain' => $domain,
                'available' => ($res['status'] === 'true' || $res['status'] === true),
                'price' => $res['price'] ?? null,
                'currency' => $res['currency'] ?? 'USD',
            ];
        }
    }

    return $results;
}

/**
 * Register a domain via ResellerClub.
 */
function domain_register(string $domain, string $years = '1'): array {
    $config = resellerclub_config();
    if (empty($config['user_id']) || empty($config['api_key'])) {
        return ['error' => 'ResellerClub API credentials not configured. Set resellerclub_user_id and resellerclub_api_key in config.php.'];
    }

    // Validate customer ID
    $customer_id = $config['customer_id'];
    if (empty($customer_id)) {
        return ['error' => 'ResellerClub customer ID not configured. Set resellerclub_customer_id in config.php.'];
    }

    // Validate domain format
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]\.[a-z]{2,}$/', $domain)) {
        return ['error' => 'Invalid domain format'];
    }

    $url = "{$config['base_url']}/domains/register.json";
    $params = [
        'auth-userid' => $config['user_id'],
        'api-key' => $config['api_key'],
        'domain-name' => $domain,
        'years' => $years,
        'registrar-username' => $customer_id,
        'ns1' => 'ns1.mail.pesat.ai',
        'ns2' => 'ns2.mail.pesat.ai',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError) {
        return ['error' => 'Connection to ResellerClub failed: ' . ($curlError ?: 'Unknown error')];
    }

    $res = json_decode($response, true);
    if ($res === null) {
        return ['error' => 'Invalid response from ResellerClub API'];
    }

    if (isset($res['status']) && $res['status'] === 'true') {
        // Add domain to mail system automatically
        require_once __DIR__ . '/mailcow.php';
        $mail_res = mailcow_add_domain($domain);

        return [
            'ok' => true,
            'domain' => $domain,
            'order_id' => $res['orderid'] ?? null,
            'mailcow' => $mail_res,
        ];
    }

    return ['error' => $res['message'] ?? 'Registration failed. Check your ResellerClub account balance and settings.'];
}

/**
 * Get ResellerClub customer ID.
 */
function resellerclub_get_customer_id(): ?string {
    return cfg()['resellerclub_customer_id'] ?? null;
}

/**
 * Get domain pricing from ResellerClub.
 */
function domain_pricing(): array {
    $config = resellerclub_config();
    if (empty($config['user_id']) || empty($config['api_key'])) {
        return [];
    }

    $url = "{$config['base_url']}/products/levels.json"
         . "?auth-userid={$config['user_id']}"
         . "&api-key={$config['api_key']}"
         . "&type=domain";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $res['products']['product'] ?? [];
}
