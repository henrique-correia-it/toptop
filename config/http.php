<?php
// config/http.php

if (!function_exists('json_response')) {
    function json_response($payload, int $status = 200, int $flags = JSON_UNESCAPED_UNICODE): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, $flags);
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
        json_response(['sucesso' => false, 'mensagem' => $mensagem] + $extra, $status);
    }
}

if (!function_exists('json_input')) {
    function json_input(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
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
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }
}
