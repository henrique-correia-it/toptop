<?php
// config/session.php

// Apenas define os parâmetros e inicia a sessão se ela ainda não estiver ativa.
if (session_status() === PHP_SESSION_NONE) {
    // Garante que a sessão usa apenas cookies
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    // Define os parâmetros do cookie de sessão de forma segura (incluindo Cloudflare/proxies)
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($forwardedProto === 'https')
               || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
               || (strpos($cfVisitor, '"https"') !== false);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Inicia a sessão
    session_start();
}
