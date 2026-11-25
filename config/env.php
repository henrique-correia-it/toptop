<?php
// config/env.php
// Carregador centralizado e com cache em memória do ficheiro .env.

if (!function_exists('tt_load_env')) {
    function tt_load_env(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $env_path = dirname(__DIR__) . '/.env';
        if (!is_file($env_path) || !is_readable($env_path)) {
            $cache = [];
            return $cache;
        }

        $parsed = @parse_ini_file($env_path, false, INI_SCANNER_RAW);
        $cache = is_array($parsed) ? $parsed : [];
        return $cache;
    }
}

if (!function_exists('tt_env')) {
    function tt_env(string $key, string $default = ''): string
    {
        $vars = tt_load_env();
        return array_key_exists($key, $vars) ? (string)$vars[$key] : $default;
    }
}
