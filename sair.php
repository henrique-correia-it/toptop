<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/cliente_auth.php';

$was_admin = isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true;

session_unset();
session_destroy();

header('Location: ' . ($was_admin ? '/entrar' : '/'));
exit;
