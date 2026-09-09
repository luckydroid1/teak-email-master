<?php
/**
 * CodeInbox — Config template.
 * Copy to config.php and fill in real values. DO NOT commit config.php.
 */

return [
    // App
    'app_name'    => 'CodeInbox',
    'app_url'     => 'https://inbox.pesat.ai',       // ganti ke domain brand
    'session_name'=> 'CIB_SESS',

    // Database (Mailcow MySQL — reuse existing credentials)
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;port=13306;dbname=mailcow;charset=utf8mb4',
        'user' => 'mailcow',
        'pass' => '',
    ],

    // Cloudflare API (for listing/selecting domain pool)
    'cf_token' => '',

    // Domain pool for shared inboxes (must be active in Mailcow)
    'pool_domains' => [
        'jetdigitalpro.com',
        'toohumid.com',
    ],

    // Honeypot local parts (emails never given to users)
    'honeypots' => [
        'admin', 'postmaster', 'abuse', 'support', 'nobody', 'webmaster',
    ],

    // Docker container Dovecot (for doveadm fetch)
    'dovecot_container' => 'mailcowdockerized-dovecot-mailcow-1',

    // Security
    'pepper' => '',   // random string for token hashing
];
