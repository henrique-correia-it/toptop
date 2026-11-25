<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

// Acesso permitido apenas a gestores da loja
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !is_admin_manager()
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode($response);
    exit;
}

include '../config/database.php';

// Atualiza layouts bento
if (isset($_POST['home_bento_layouts']) && is_string($_POST['home_bento_layouts'])) {
    $layouts = json_decode($_POST['home_bento_layouts'], true);
    if (is_array($layouts)) {
        setLojaConfig('home_bento_layouts', json_encode($layouts));
        $response = ['sucesso' => true];
    } else {
        $response['mensagem'] = 'Dados de layout inválidos.';
    }
} else {
    $response['mensagem'] = 'Nenhum dado recebido.';
}

echo json_encode($response);
exit;
