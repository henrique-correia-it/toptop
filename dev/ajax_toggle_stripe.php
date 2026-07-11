<?php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

// Acesso EXCLUSIVO a Desenvolvedores
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['admin_logado'])
    || $_SESSION['admin_logado'] !== true
    || $_SESSION['admin_role'] !== 'desenvolvedor'
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode($response);
    exit;
}

include '../config/database.php';

// Atualiza o modo do Stripe
if (isset($_POST['stripe_mode'])) {
    $mode = $_POST['stripe_mode'];
    if (in_array($mode, ['test', 'live'])) {
        setLojaConfig('stripe_mode', $mode);
        $response = ['sucesso' => true, 'modo_atual' => $mode];
    } else {
        $response['mensagem'] = 'Valor inválido para stripe_mode.';
    }
} else {
    $response['mensagem'] = 'Nenhum dado recebido.';
}

echo json_encode($response);
exit;
