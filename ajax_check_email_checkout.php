<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';
require_once __DIR__ . '/config/cliente_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Pedido invalido.', 405);
}

$dados = json_input();

$email = customer_clean_email($dados['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Email invalido.', 400);
}

if (is_cliente_logged_in() || (isset($_SESSION['admin_logado']) && $_SESSION['admin_role'] === 'desenvolvedor')) {
    json_success(['existe' => false]);
}

$customer = customer_find_by_email($conn, $email);
json_success([
    'existe' => $customer && (int)$customer['ativo'] === 1,
    'login_url' => '/entrar?next=/checkout',
]);
