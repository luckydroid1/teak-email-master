<?php
/**
 * registrar.php — Multi-registrar domain sync.
 * Supports: Cloudflare, Porkbun, GoDaddy, Namecheap, Gandi, Spaceship, Dynadot, Name.com
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Get list of supported registrars.
 */
function registrar_list(): array {
    return [
        'cloudflare' => [
            'name' => 'Cloudflare',
            'auth_type' => 'token',
            'auth_label' => 'API Token',
            'auth_placeholder' => 'cfut_...',
            'docs_url' => 'https://dash.cloudflare.com/profile/api-tokens',
            'help' => 'Create a token with Zone:Read permission.',
        ],
        'porkbun' => [
            'name' => 'Porkbun',
            'auth_type' => 'key_secret',
            'auth_label' => 'API Key',
            'auth_secret_label' => 'Secret Key',
            'auth_placeholder' => 'pk1_...',
            'auth_secret_placeholder' => 'pk2_...',
            'docs_url' => 'https://porkbun.com/account/api',
            'help' => 'Enable API access in your Porkbun account settings.',
        ],
        'godaddy' => [
            'name' => 'GoDaddy',
            'auth_type' => 'token',
            'auth_label' => 'API Key',
            'auth_placeholder' => '...',
            'docs_url' => 'https://developer.godaddy.com/keys',
            'help' => 'Generate a Production API key at developer.godaddy.com.',
        ],
        'namecheap' => [
            'name' => 'Namecheap',
            'auth_type' => 'key_secret',
            'auth_label' => 'API Key',
            'auth_secret_label' => 'Username',
            'auth_placeholder' => '...',
            'auth_secret_placeholder' => 'your_username',
            'docs_url' => 'https://www.namecheap.com/support/api.aspx',
            'help' => 'Enable API access (requires $50+ balance). Whitelist your IP first.',
        ],
        'gandi' => [
            'name' => 'Gandi',
            'auth_type' => 'token',
            'auth_label' => 'Personal Access Token',
            'auth_placeholder' => 'pat_...',
            'docs_url' => 'https://account.gandi.net/en/organization/api-tokens',
            'help' => 'Create a PAT with Domain:Read permission.',
        ],
        'spaceship' => [
            'name' => 'Spaceship',
            'auth_type' => 'key_secret',
            'auth_label' => 'API Key',
            'auth_secret_label' => 'API Secret',
            'auth_placeholder' => '...',
            'auth_secret_placeholder' => '...',
            'docs_url' => 'https://www.spaceship.com/api/',
            'help' => 'Generate API credentials in Spaceship account settings.',
        ],
        'dynadot' => [
            'name' => 'Dynadot',
            'auth_type' => 'token',
            'auth_label' => 'API Token',
            'auth_placeholder' => '...',
            'docs_url' => 'https://www.dynadot.com/domain/api.html',
            'help' => 'Find your API token in Domain API settings.',
        ],
        'name_com' => [
            'name' => 'Name.com',
            'auth_type' => 'key_secret',
            'auth_label' => 'API Token',
            'auth_secret_label' => 'Username',
            'auth_placeholder' => '...',
            'auth_secret_placeholder' => 'your_username',
            'docs_url' => 'https://www.name.com/account/settings/api-access',
            'help' => 'Generate an API token in your account settings.',
        ],
    ];
}

/**
 * Sync domains from a registrar.
 */
function registrar_sync(string $registrar, string $auth_key, string $auth_secret = ''): array {
    $domains = match($registrar) {
        'cloudflare' => registrar_fetch_cloudflare($auth_key),
        'porkbun'    => registrar_fetch_porkbun($auth_key, $auth_secret),
        'godaddy'    => registrar_fetch_godaddy($auth_key),
        'namecheap'  => registrar_fetch_namecheap($auth_key, $auth_secret),
        'gandi'      => registrar_fetch_gandi($auth_key),
        'spaceship'  => registrar_fetch_spaceship($auth_key, $auth_secret),
        'dynadot'    => registrar_fetch_dynadot($auth_key),
        'name_com'   => registrar_fetch_name_com($auth_key, $auth_secret),
        default      => ['error' => 'Unknown registrar'],
    };

    if (isset($domains['error'])) {
        return $domains;
    }

    // Save synced domains to DB
    $added = 0;
    $existing = 0;
    foreach ($domains as $d) {
        $domain = strtolower($d);
        if (mailcow_domain_exists($domain)) {
            $existing++;
            continue;
        }
        $res = mailcow_add_domain($domain);
        if ($res['ok']) {
            // Track sync source
            db()->prepare('UPDATE domain SET description = ? WHERE domain = ?')
                ->execute(["Synced from $registrar", $domain]);
            $added++;
        }
    }

    return ['ok' => true, 'added' => $added, 'existing' => $existing, 'total' => count($domains)];
}

// ─── Cloudflare ──────────────────────────────────────────────
function registrar_fetch_cloudflare(string $token): array {
    $ch = curl_init('https://api.cloudflare.com/client/v4/zones?per_page=100');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (!$res['success']) return ['error' => 'Cloudflare API error: ' . ($res['errors'][0]['message'] ?? 'Unknown')];
    return array_column($res['result'], 'name');
}

// ─── Porkbun ─────────────────────────────────────────────────
function registrar_fetch_porkbun(string $key, string $secret): array {
    $ch = curl_init('https://api.porkbun.com/api/domain/list');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['secretapikey' => $secret]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => "$key:$secret",
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (isset($res['status']) && $res['status'] === 'ERROR') return ['error' => 'Porkbun: ' . ($res['message'] ?? 'Unknown')];
    return array_column($res['domains'] ?? [], 'domain');
}

// ─── GoDaddy ─────────────────────────────────────────────────
function registrar_fetch_godaddy(string $key): array {
    $ch = curl_init('https://api.godaddy.com/v1/domains?limit=1000');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: sso-key $key", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (isset($res['code'])) return ['error' => 'GoDaddy: ' . ($res['message'] ?? 'Unknown')];
    return array_column($res ?? [], 'domain');
}

// ─── Namecheap ───────────────────────────────────────────────
function registrar_fetch_namecheap(string $key, string $username): array {
    $url = 'https://api.namecheap.com/xml.response?ApiUser=' . urlencode($username)
         . '&ApiKey=' . urlencode($key) . '&UserName=' . urlencode($username)
         . '&Command=namecheap.domains.getList&PageSize=100';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $xml = curl_exec($ch);
    curl_close($ch);
    if (!$xml) return ['error' => 'Namecheap: Connection failed'];
    $doc = simplexml_load_string($xml);
    if (!$doc) return ['error' => 'Namecheap: Invalid response'];
    $ns = $doc->getNamespaces(true);
    $domains = [];
    foreach ($doc->CommandResponse->domains->domain as $d) {
        $domains[] = (string)$d['Name'];
    }
    return $domains;
}

// ─── Gandi ───────────────────────────────────────────────────
function registrar_fetch_gandi(string $token): array {
    $ch = curl_init('https://api.gandi.net/v5/domain/domains');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (isset($res['errors'])) return ['error' => 'Gandi: ' . ($res['errors'][0]['message'] ?? 'Unknown')];
    return array_column($res ?? [], 'fqdn');
}

// ─── Spaceship ───────────────────────────────────────────────
// Official API: https://spaceship.dev/api/v1
// Auth: X-API-Key + X-API-Secret headers
// Response: {items:[{name:...}], total:N}
function registrar_fetch_spaceship(string $key, string $secret): array {
    $all = [];
    $skip = 0;
    $take = 100;
    $maxPages = 20; // safety limit: 2000 domains max
    for ($page = 0; $page < $maxPages; $page++) {
        $url = "https://spaceship.dev/api/v1/domains?take=$take&skip=$skip";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "X-API-Key: $key",
                "X-API-Secret: $secret",
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr) {
            return ['error' => 'Spaceship: Connection failed — ' . ($curlErr ?: 'No response')];
        }
        if ($httpCode === 401 || $httpCode === 403) {
            return ['error' => 'Spaceship: Authentication failed (HTTP ' . $httpCode . ')'];
        }
        if ($httpCode !== 200) {
            return ['error' => 'Spaceship: HTTP ' . $httpCode . ' — ' . substr($body, 0, 200)];
        }

        $res = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Spaceship: Invalid JSON response'];
        }
        if (isset($res['detail'])) {
            return ['error' => 'Spaceship: ' . $res['detail']];
        }

        $items = $res['items'] ?? [];
        foreach ($items as $item) {
            $name = $item['name'] ?? $item['domain'] ?? null;
            if ($name) $all[] = $name;
        }

        $total = $res['total'] ?? 0;
        $skip += $take;
        if ($skip >= $total || count($items) === 0) break;
    }
    if (empty($all) && !isset($res['error'])) {
        return ['error' => 'Spaceship: No domains returned (total=0)'];
    }
    return $all;
}

// ─── Dynadot ─────────────────────────────────────────────────
function registrar_fetch_dynadot(string $token): array {
    $ch = curl_init('https://api.dynadot.com/rest/v1/domain/list?api_token=' . urlencode($token));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (isset($res['error'])) return ['error' => 'Dynadot: ' . $res['error']];
    $domains = [];
    foreach ($res['domain_list_result']['domain'] ?? [] as $d) {
        $domains[] = $d['name'];
    }
    return $domains;
}

// ─── Name.com ────────────────────────────────────────────────
function registrar_fetch_name_com(string $token, string $username): array {
    $ch = curl_init('https://api.name.com/v4/domains');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$username:$token",
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (isset($res['error'])) return ['error' => 'Name.com: ' . ($res['error']['message'] ?? 'Unknown')];
    return array_column($res['domains'] ?? [], 'domain_name');
}
