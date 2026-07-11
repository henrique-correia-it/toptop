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

// Ativa/desativa o header dinâmico (esconder no scroll)
if (isset($_POST['header_auto_hide'])) {
    $valor = ($_POST['header_auto_hide'] === '1') ? '1' : '0';
    setLojaConfig('header_auto_hide', $valor);
    $response = ['sucesso' => true, 'estado' => $valor];
} else {
    $response['mensagem'] = 'Nenhum dado recebido.';
}

echo json_encode($response);
exit;
