<?php
// config/csrf.php

require_once __DIR__ . '/session.php';

if (!function_exists('csrf_token')) {
    function csrf_token(string $key = 'csrf_token'): string
    {
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token, string $key = 'csrf_token'): bool
    {
        return !empty($_SESSION[$key]) && is_string($token) && hash_equals($_SESSION[$key], $token);
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(string $key = 'csrf_token'): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token($key), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_from_post')) {
    function csrf_from_post(string $field = 'csrf_token', string $key = 'csrf_token'): bool
    {
        return csrf_verify($_POST[$field] ?? null, $key);
    }
}

if (!function_exists('csrf_from_array')) {
    function csrf_from_array(array $data, string $field = 'csrf_token', string $key = 'csrf_token'): bool
    {
        return csrf_verify($data[$field] ?? null, $key);
    }
}
