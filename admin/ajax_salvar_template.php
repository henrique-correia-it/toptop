<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['admin_logado'])
    || $_SESSION['admin_logado'] !== true
    || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])
    || !csrf_from_post()) {

    http_response_code(403);
    echo json_encode($response);
    exit;
}

$template_key = trim((string)($_POST['template_key'] ?? ''));
$nome         = trim((string)($_POST['nome']         ?? ''));
$descricao    = trim((string)($_POST['descricao']    ?? ''));
$assunto      = trim((string)($_POST['assunto']      ?? ''));
$corpo        = (string)($_POST['corpo']             ?? '');

if ($template_key === '' || $nome === '' || $assunto === '' || trim($corpo) === '') {
    http_response_code(400);
    $response['mensagem'] = 'Dados incompletos.';
    echo json_encode($response);
    exit;
}

include '../config/database.php';

try {
    $stmt_chk = $conn->prepare("SELECT 1 FROM email_templates WHERE template_key = ? LIMIT 1");
    $stmt_chk->bind_param("s", $template_key);
    $stmt_chk->execute();
    $existe = $stmt_chk->get_result()->num_rows > 0;
    $stmt_chk->close();

    if (!$existe) {
        http_response_code(404);
        $response['mensagem'] = 'O template especificado não existe.';
        echo json_encode($response);
        exit;
    }

    $stmt = $conn->prepare("UPDATE email_templates SET template_name = ?, description = ?, subject = ?, body = ? WHERE template_key = ?");
    $stmt->bind_param("sssss", $nome, $descricao, $assunto, $corpo, $template_key);
    $stmt->execute();
    $stmt->close();

    $response = ['sucesso' => true];
} catch (\Throwable $e) {
    error_log('Erro em ajax_salvar_template.php: ' . $e->getMessage());
    http_response_code(500);
    $response['mensagem'] = 'Erro interno ao atualizar o template.';
}

echo json_encode($response);
exit;
