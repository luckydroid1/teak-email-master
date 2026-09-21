<?php
/**
 * paypal.php — PayPal REST API v2 helper.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/redeem.php';
require_once __DIR__ . '/credits.php';

function paypal_config(): array {
    return [
        'client_id' => setting_get('paypal_client_id', ''),
        'secret'    => setting_get('paypal_secret', ''),
        'mode'      => setting_get('paypal_mode', 'sandbox'), // sandbox | live
        'currency'  => setting_get('paypal_currency', 'USD'),
    ];
}

function paypal_api_base(): string {
    $mode = setting_get('paypal_mode', 'sandbox');
    return ($mode === 'live') ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

/**
 * Get OAuth2 Access Token from PayPal REST API.
 */
function paypal_access_token(): ?string {
    $cfg = paypal_config();
    if (empty($cfg['client_id']) || empty($cfg['secret'])) {
        return null;
    }

    $url = paypal_api_base() . '/v1/oauth2/token';
    $auth = base64_encode($cfg['client_id'] . ':' . $cfg['secret']);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) {
        error_log("PayPal Auth Error (code $code): " . ($resp ?: 'no response'));
        return null;
    }

    $data = json_decode($resp, true);
    return $data['access_token'] ?? null;
}

/**
 * Create a PayPal Order for a given tier.
 */
function paypal_create_order(int $userId, int $tier): array {
    $token = paypal_access_token();
    if (!$token) {
        return ['error' => 'PayPal is not configured yet. Please enter Client ID and Secret in Admin Settings.'];
    }

    $info = tier_info($tier);
    if (!$info) {
        return ['error' => 'Invalid plan tier.'];
    }

    $price = number_format((float)($info['price'] ?? 1), 2, '.', '');
    $currency = setting_get('paypal_currency', 'USD');
    $app_url = rtrim(cfg()['app_url'] ?? 'https://teak.email', '/');

    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'user_' . $userId . '_tier_' . $tier . '_' . time(),
                'description'  => "Teak Email — Tier $tier Plan Subscription",
                'amount' => [
                    'currency_code' => $currency,
                    'value'         => $price,
                ],
                'custom_id' => json_encode(['user_id' => $userId, 'tier' => $tier]),
            ]
        ],
        'application_context' => [
            'brand_name'          => 'Teak Email',
            'landing_page'        => 'NO_PREFERENCE',
            'user_action'         => 'PAY_NOW',
            'return_url'          => $app_url . '/paypal_capture.php?tier=' . $tier,
            'cancel_url'          => $app_url . '/dashboard.php?payment=cancelled',
        ]
    ];

    $url = paypal_api_base() . '/v2/checkout/orders';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if ($code !== 201 || empty($data['id'])) {
        error_log("PayPal Create Order Error (HTTP $code): " . (string)$resp);
        return ['error' => 'Failed to initiate PayPal order. Please try again.'];
    }

    $orderId = $data['id'];

    // Record initial order in ia_payments
    try {
        $st = db()->prepare('INSERT INTO ia_payments (user_id, provider, order_id, tier, amount, currency, status, raw_payload) VALUES (?, "paypal", ?, ?, ?, ?, "created", ?)');
        $st->execute([$userId, $orderId, $tier, (float)$price, $currency, json_encode($data)]);
    } catch (Exception $e) {
        error_log("Failed to log payment order: " . $e->getMessage());
    }

    $approvalUrl = null;
    foreach ($data['links'] ?? [] as $link) {
        if (($link['rel'] ?? '') === 'approve') {
            $approvalUrl = $link['href'];
            break;
        }
    }

    return [
        'order_id'     => $orderId,
        'approval_url' => $approvalUrl,
    ];
}

/**
 * Capture a PayPal Order and fulfill user tier/credits.
 */
function paypal_capture_order(string $orderId, int $userId): array {
    $token = paypal_access_token();
    if (!$token) {
        return ['error' => 'PayPal auth failure.'];
    }

    $url = paypal_api_base() . '/v2/checkout/orders/' . urlencode($orderId) . '/capture';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    $status = $data['status'] ?? 'UNKNOWN';

	    if ($code === 200 || $code === 201 || $status === 'COMPLETED') {
	        $pdo = db();
	        // Check if already fulfilled to prevent double-crediting / replay attacks
	        $st = $pdo->prepare('SELECT user_id, tier, status FROM ia_payments WHERE order_id = ?');
	        $st->execute([$orderId]);
	        $payment = $st->fetch();

	        if ($payment) {
	            // Validasi kepemilikan order
	            if ((int)$payment['user_id'] !== $userId) {
	                return ['error' => 'Order ownership mismatch.'];
	            }
	            if ($payment['status'] === 'completed') {
	                $info = tier_info((int)$payment['tier']) ?? tier_info(1);
	                return [
	                    'success'  => true,
	                    'tier'     => (int)$payment['tier'],
	                    'credits'  => (int)($info['credits'] ?? 3000),
	                    'order_id' => $orderId,
	                ];
	            }
	            $tier = (int)$payment['tier'];
	        } else {
	            $tier = 1;
	        }

	        $info = tier_info($tier) ?? tier_info(1);
	        $credits = (int)($info['credits'] ?? 3000);

	        // Update payment status atomically
	        try {
	            $st = $pdo->prepare('UPDATE ia_payments SET status = "completed", raw_payload = ? WHERE order_id = ?');
	            $st->execute([json_encode($data), $orderId]);
	        } catch (Exception $e) {}

	        // Fulfill credits and upgrade tier
	        credit_mutate($userId, $credits, 'topup', "paypal_order=$orderId tier=$tier");

	        try {
	            $st = $pdo->prepare('UPDATE ia_users SET trust_tier = ?, status = "active" WHERE id = ?');
	            $st->execute([$tier, $userId]);
	        } catch (Exception $e) {}

	        audit($userId, 'paypal_payment_complete', 'web', "order_id=$orderId tier=$tier amount=" . ($info['price'] ?? 0));

	        return [
	            'success'  => true,
	            'tier'     => $tier,
	            'credits'  => $credits,
	            'order_id' => $orderId,
	        ];
	    }

    return ['error' => 'PayPal payment was not completed (Status: ' . $status . ').'];
}
