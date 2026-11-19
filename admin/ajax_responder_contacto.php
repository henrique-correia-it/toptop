<?php
// loja-roupa/admin/ajax_responder_contacto.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';
include 'includes/email_handler.php'; // Reutilizamos o gestor de emails

header('Content-Type: application/json');
$response = ['sucesso' => false, 'mensagem' => 'Ocorreu um erro inesperado.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Acesso inválido.');
    }

    $dados = json_decode(file_get_contents('php://input'), true);

    if (!is_admin_logged_in() || !is_array($dados) || !csrf_from_array($dados)) {
        http_response_code(403);
        throw new Exception('Erro de validação de segurança.');
    }

    $id_contacto = (int)($dados['id_contacto'] ?? 0);
    $assunto = trim((string)($dados['assunto'] ?? ''));
    $mensagem = trim((string)($dados['mensagem'] ?? ''));

    if ($id_contacto <= 0 || $assunto === '' || $mensagem === '') {
        throw new Exception('Dados em falta para enviar a resposta.');
    }

    $stmt_c = $conn->prepare("SELECT nome, email FROM contactos WHERE id = ? LIMIT 1");
    $stmt_c->bind_param("i", $id_contacto);
    $stmt_c->execute();
    $contacto_db = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();

    if (!$contacto_db) {
        http_response_code(404);
        throw new Exception('Mensagem de contacto não encontrada.');
    }

    $email_cliente = filter_var($contacto_db['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $nome_cliente = trim((string)($contacto_db['nome'] ?? ''));
    if (!$email_cliente) {
        throw new Exception('O contacto não possui um email válido.');
    }

    // Prepara os dados para o email_handler (id=0 para não gravar no histórico de encomendas)
    $dados_email = [
        'cliente_email' => $email_cliente,
        'cliente_nome' => $nome_cliente,
        'assunto_email' => $assunto,
        'mensagem_para_cliente' => $mensagem,
        'id' => 0,
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
