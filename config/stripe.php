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

function stripeRequest(string $method, string $endpoint, array $data = [], string $secret_key = '', array $extra_headers = []): array {
    $url = 'https://api.stripe.com/v1/' . $endpoint;
    $headers = array_merge(['Content-Type: application/x-www-form-urlencoded'], $extra_headers);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secret_key . ':',
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = ($response === false) ? curl_error($ch) : '';
    curl_close($ch);

    if ($response === false) {
        if (function_exists('log_stripe')) {
            log_stripe("Erro cURL em {$method} {$endpoint}: {$curl_err}", 'ERROR');
        }
        return ['_http_code' => 0, '_curl_error' => $curl_err];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    $decoded['_http_code'] = $http_code;
    return $decoded;
}

/**
 * Verifica a assinatura HMAC-SHA256 do webhook Stripe.
 * Retorna o evento descodificado ou null se inválido/expirado.
 */
function stripeVerifyWebhookSignature(string $payload, string $sig_header, string $webhook_secret): ?array {
    if ($webhook_secret === '' || $sig_header === '') {
        return null;
    }

    $parts = explode(',', $sig_header);
    $timestamp = null;
    $signatures = [];

    foreach ($parts as $part) {
        [$key, $val] = array_pad(explode('=', trim($part), 2), 2, '');
        $key = trim($key);
        $val = trim($val);
        if ($key === 't')  $timestamp    = $val;
        if ($key === 'v1') $signatures[] = $val;
    }

    if (!$timestamp || !ctype_digit($timestamp) || empty($signatures)) return null;

    // Rejeita eventos com mais de 5 minutos (recomendação oficial Stripe para prevenir replay attacks)
    if (abs(time() - (int)$timestamp) > 300) return null;

    $signed_payload = $timestamp . '.' . $payload;
    $expected       = hash_hmac('sha256', $signed_payload, $webhook_secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : null;
        }
    }

    return null;
}
