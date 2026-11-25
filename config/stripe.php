<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/loja_config.php';

function getStripeKeys(): array {
    $mode = getLojaConfig('stripe_mode', 'live');
    $env_vars = tt_load_env();

    if (!empty($env_vars)) {
        $prefix = ($mode === 'test') ? 'STRIPE_TEST_' : 'STRIPE_LIVE_';

        return [
            'secret'         => (string) ($env_vars[$prefix . 'SECRET_KEY'] ?? ''),
            'public'         => (string) ($env_vars[$prefix . 'PUBLIC_KEY'] ?? ''),
            'webhook_secret' => (string) ($env_vars[$prefix . 'WEBHOOK_SECRET'] ?? ''),
            'mode'           => $mode
        ];
    }
    return ['secret' => '', 'public' => '', 'webhook_secret' => '', 'mode' => $mode];
}

function stripeRequest(string $method, string $endpoint, array $data = [], string $secret_key = '', array $extra_headers = []): array {
    $method = strtoupper(trim($method));
    $clean_endpoint = ltrim($endpoint, '/');
    $url = 'https://api.stripe.com/v1/' . $clean_endpoint;

    $is_write = in_array($method, ['POST', 'PUT', 'PATCH'], true);
    if ($method === 'GET' && !empty($data)) {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $url .= $sep . http_build_query($data);
    }

    $headers = $is_write
        ? array_merge(['Content-Type: application/x-www-form-urlencoded'], $extra_headers)
        : $extra_headers;

    $ch = curl_init($url);
    if ($ch === false) {
        if (function_exists('log_stripe')) {
            log_stripe("Falha ao inicializar cURL em {$method} {$clean_endpoint}", 'ERROR');
        }
        return ['_http_code' => 0, '_curl_error' => 'curl_init failed'];
    }

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
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($is_write && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = ($response === false) ? curl_error($ch) : '';
    curl_close($ch);

    if ($response === false) {
        if (function_exists('log_stripe')) {
            log_stripe("Erro cURL em {$method} {$clean_endpoint}: {$curl_err}", 'ERROR');
        }
        return ['_http_code' => 0, '_curl_error' => $curl_err];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        if (function_exists('log_stripe')) {
            log_stripe("Resposta não-JSON da Stripe em {$method} {$clean_endpoint} (HTTP {$http_code})", 'WARNING');
        }
        $decoded = [];
    }
    if (function_exists('log_stripe')) {
        if ($http_code >= 400) {
            $errMsg  = (string)($decoded['error']['message'] ?? 'Erro desconhecido');
            $errCode = (string)($decoded['error']['code'] ?? ($decoded['error']['type'] ?? 'api_error'));
            log_stripe("Stripe {$method} {$clean_endpoint} falhou (HTTP {$http_code}, {$errCode}): {$errMsg}", 'ERROR', 'stripe.php');
        } elseif ($is_write) {
            $objId = (string)($decoded['id'] ?? '');
            log_stripe("Stripe {$method} {$clean_endpoint} OK (HTTP {$http_code}" . ($objId !== '' ? ", id:{$objId}" : '') . ")", 'INFO', 'stripe.php');
        }
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
        if (function_exists('log_stripe')) {
            log_stripe("Webhook Stripe rejeitado: secret ou cabeçalho Stripe-Signature em falta.", 'WARNING', 'stripe.php');
        }
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

    if (!$timestamp || !ctype_digit($timestamp) || empty($signatures)) {
        if (function_exists('log_stripe')) {
            log_stripe("Webhook Stripe rejeitado: formato de assinatura inválido.", 'WARNING', 'stripe.php');
        }
        if (function_exists('log_seguranca')) {
            log_seguranca("Webhook Stripe com formato de assinatura inválido.", 'stripe_webhook');
        }
        return null;
    }

    // Rejeita eventos com mais de 5 minutos (recomendação oficial Stripe para prevenir replay attacks)
    if (abs(time() - (int)$timestamp) > 300) {
        if (function_exists('log_stripe')) {
            log_stripe("Webhook Stripe rejeitado: timestamp expirado (>300s).", 'WARNING', 'stripe.php');
        }
        if (function_exists('log_seguranca')) {
            log_seguranca("Webhook Stripe com timestamp expirado.", 'stripe_webhook');
        }
        return null;
    }

    $signed_payload = $timestamp . '.' . $payload;
    $expected       = hash_hmac('sha256', $signed_payload, $webhook_secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : null;
        }
    }

    if (function_exists('log_stripe')) {
        log_stripe("Webhook Stripe rejeitado: assinatura HMAC inválida.", 'ERROR', 'stripe.php');
    }
    if (function_exists('log_seguranca')) {
        log_seguranca("Webhook Stripe com assinatura HMAC inválida.", 'stripe_webhook');
    }
    return null;
}
