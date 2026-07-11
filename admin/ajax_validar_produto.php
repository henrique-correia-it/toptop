<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';
// Inclui a nova função de validação centralizada
include 'includes/validacao_produto.php';

// Resposta padrão
$response = ['valido' => true, 'campo' => '', 'mensagem' => ''];

// Segurança
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['valido' => false, 'campo' => 'geral', 'mensagem' => 'Sessão inválida.']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$referencia = trim($_POST['referencia'] ?? '');
$id_a_ignorar = (int)($_POST['id'] ?? 0);

try {
    // CORREÇÃO: Utiliza a nova função centralizada para validar
    $resultado_validacao = validarProduto($conn, $nome, $referencia, $id_a_ignorar);
    
    // Se a validação falhar, define a resposta com a mensagem e o campo corretos
    if (!$resultado_validacao['valido']) {
        $response['valido'] = false;
        $response['campo'] = $resultado_validacao['campo'];
        $response['mensagem'] = $resultado_validacao['mensagem'];
        throw new Exception(); // Lança uma exceção para saltar para o 'catch'
    }

} catch (Exception $e) {
    // O 'catch' agora serve apenas para parar a execução se a validação falhar.
}

// Devolve a resposta
header('Content-Type: application/json');
echo json_encode($response);
exit;
