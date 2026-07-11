<?php
// ajax_get_address_config.php
require_once __DIR__ . '/config/http.php';

$iso = $_GET['iso'] ?? '';
if (!preg_match('/^[A-Z]{2}$/i', $iso)) {
    json_response(['erro' => 'ISO inválido'], 400);
}

$url = "https://chromium-i18n.appspot.com/ssl-address/data/" . strtoupper($iso);

function fetchUrl($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Address Proxy');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'PHP Address Proxy']]);
    return @file_get_contents($url, false, $ctx);
}

$response = fetchUrl($url);

if ($response === false || empty($response)) {
    json_response(['erro' => 'Não foi possível obter dados (timeout ou bloqueio)'], 502);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
    exit;
}
