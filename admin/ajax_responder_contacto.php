<?php
// loja-roupa/admin/ajax_responder_contacto.php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';
include 'includes/email_handler.php'; // Reutilizamos o gestor de emails

header('Content-Type: application/json');
$response = ['sucesso' => false, 'mensagem' => 'Ocorreu um erro inesperado.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Acesso inválido.');
    }

    $dados = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['admin_logado']) || !isset($dados['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $dados['csrf_token'])) {
        throw new Exception('Erro de validação de segurança.');
    }

    $id_contacto = (int)($dados['id_contacto'] ?? 0);
    $assunto = trim($dados['assunto'] ?? '');
    $mensagem = trim($dados['mensagem'] ?? '');
    $email_cliente = filter_var($dados['email_cliente'] ?? '', FILTER_VALIDATE_EMAIL);
    $nome_cliente = trim($dados['nome_cliente'] ?? '');

    if ($id_contacto <= 0 || empty($assunto) || empty($mensagem) || !$email_cliente) {
        throw new Exception('Dados em falta para enviar a resposta.');
    }

    // Prepara os dados para o email_handler
    $dados_email = [
        'cliente_email' => $email_cliente,
        'cliente_nome' => $nome_cliente,
        'assunto_email' => $assunto,
        'mensagem_para_cliente' => $mensagem,
        // Adiciona dados vazios para compatibilidade com a função, se necessário
        'id' => $id_contacto,
        'token' => '' 
    ];

    // Envia o email usando a função 'personalizado' do email_handler
    enviarEmailEncomenda('personalizado', $dados_email);

    // Se o email foi enviado com sucesso, marca a mensagem como respondida
    $stmt = $conn->prepare("UPDATE contactos SET respondida = 1 WHERE id = ?");
    $stmt->bind_param("i", $id_contacto);
    $stmt->execute();
    $stmt->close();

    $response = ['sucesso' => true, 'mensagem' => 'Email de resposta enviado e mensagem arquivada com sucesso!'];

} catch (Exception $e) {
    http_response_code(400);
    log_app($e->getMessage(), 'ERROR', 'ajax_responder_contacto.php');
    $response['mensagem'] = $e->getMessage();
}

echo json_encode($response);
exit;
