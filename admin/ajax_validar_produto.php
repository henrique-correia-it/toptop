<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';
// Inclui a nova função de validação centralizada
include 'includes/validacao_produto.php';

header('Content-Type: application/json');

// Resposta padrão
$response = ['valido' => true, 'campo' => '', 'mensagem' => ''];

// Segurança
if (!is_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['valido' => false, 'campo' => 'geral', 'mensagem' => 'Sessão inválida.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valido' => false, 'campo' => 'geral', 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!csrf_from_post()) {
    http_response_code(403);
    echo json_encode(['valido' => false, 'campo' => 'geral', 'mensagem' => 'Erro de segurança (CSRF). Recarregue a página.']);
    exit;
}

$nome = trim((string)($_POST['nome'] ?? ''));
$referencia = trim((string)($_POST['referencia'] ?? ''));
$id_a_ignorar = (int)($_POST['id'] ?? 0);

try {
    $resultado_validacao = validarProduto($conn, $nome, $referencia, $id_a_ignorar);

    if (!$resultado_validacao['valido']) {
        $response['valido'] = false;
        $response['campo'] = $resultado_validacao['campo'];
        $response['mensagem'] = $resultado_validacao['mensagem'];
    }
} catch (\Throwable $e) {
    log_app('Erro na validação de produto: ' . $e->getMessage(), 'ERROR', 'ajax_validar_produto.php');
    http_response_code(500);
    $response = ['valido' => false, 'campo' => 'geral', 'mensagem' => 'Erro interno ao validar o produto. Tente novamente.'];
}

echo json_encode($response);
exit;
