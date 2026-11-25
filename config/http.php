<?php
// config/http.php

if (!function_exists('json_response')) {
    function json_response($payload, int $status = 200, int $flags = JSON_UNESCAPED_UNICODE): void
    {
        $encoded = json_encode($payload, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            $status = 500;
            $encoded = '{"sucesso":false,"mensagem":"Erro ao serializar resposta."}';
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo $encoded;
        exit;
    }
}

if (!function_exists('json_success')) {
    function json_success(array $payload = [], int $status = 200): void
    {
        if (!array_key_exists('sucesso', $payload)) {
            $payload = ['sucesso' => true] + $payload;
        }

        json_response($payload, $status);
    }
}

if (!function_exists('json_error')) {
    function json_error(string $mensagem, int $status = 400, array $extra = []): void
    {
        if ($status >= 500 && function_exists('log_app')) {
            log_app("HTTP {$status} JSON: {$mensagem}", 'ERROR', 'http.php');
        } elseif ($status === 403 && function_exists('log_seguranca')) {
            log_seguranca("HTTP 403 JSON: {$mensagem}", 'http.php');
        } elseif (($status === 400 || $status === 422) && function_exists('log_app')) {
            log_app("HTTP {$status} JSON: {$mensagem}", 'WARNING', 'http.php');
        }
        json_response(['sucesso' => false, 'mensagem' => $mensagem] + $extra, $status);
    }
}

if (!function_exists('json_input')) {
    function json_input(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('send_no_cache_headers')) {
    function send_no_cache_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
