<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/csrf.php';

$was_admin = isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_from_post()) {
    header('Location: ' . ($was_admin ? '/admin' : '/'));
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'] ?: '/',
        'domain'   => $params['domain'] ?: '',
        'secure'   => (bool)$params['secure'],
        'httponly' => (bool)$params['httponly'],
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}
session_destroy();

header('Location: ' . ($was_admin ? '/entrar' : '/'));
exit;
