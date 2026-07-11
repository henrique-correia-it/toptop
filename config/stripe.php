<?php
function getStripeKeys(): array {
    $env_path = dirname(__DIR__) . '/.env';

    $mode = getLojaConfig('stripe_mode', 'live');

    if (file_exists($env_path)) {
        $env_vars = parse_ini_file($env_path);
        $prefix = ($mode === 'test') ? 'STRIPE_TEST_' : 'STRIPE_LIVE_';

        return [
            'secret'         => $env_vars[$prefix . 'SECRET_KEY'] ?? '',
            'public'         => $env_vars[$prefix . 'PUBLIC_KEY'] ?? '',
            'webhook_secret' => $env_vars[$prefix . 'WEBHOOK_SECRET'] ?? '',
            'mode'           => $mode
        ];
    }
    return ['secret' => '', 'public' => '', 'webhook_secret' => '', 'mode' => $mode];
}

function stripeRequest(string $method, string $endpoint, array $data = [], string $secret_key = ''): array {
    $url = 'https://api.stripe.com/v1/' . $endpoint;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secret_key . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true) ?? [];
    $decoded['_http_code'] = $http_code;
    return $decoded;
}

/**
 * Verifica a assinatura HMAC-SHA256 do webhook Stripe.
 * Retorna o evento descodificado ou null se inválido/expirado.
 */
function stripeVerifyWebhookSignature(string $payload, string $sig_header, string $webhook_secret): ?array {
    $parts = explode(',', $sig_header);
    $timestamp = null;
    $signatures = [];

    foreach ($parts as $part) {
        [$key, $val] = array_pad(explode('=', $part, 2), 2, '');
        if ($key === 't')  $timestamp    = $val;
        if ($key === 'v1') $signatures[] = $val;
    }

    if (!$timestamp || empty($signatures)) return null;

    // Rejeita eventos com mais de 5 minutos (recomendação oficial Stripe para prevenir replay attacks)
    if (abs(time() - (int)$timestamp) > 300) return null;

    $signed_payload = $timestamp . '.' . $payload;
    $expected       = hash_hmac('sha256', $signed_payload, $webhook_secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return json_decode($payload, true);
        }
    }

    return null;
}
