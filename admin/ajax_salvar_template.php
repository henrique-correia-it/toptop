<?php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json');

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['admin_logado'])
    || $_SESSION['admin_logado'] !== true
    || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

    echo json_encode($response);
    exit;
}

$template_key = $_POST['template_key'] ?? '';
$nome         = $_POST['nome']         ?? '';
$descricao    = $_POST['descricao']    ?? '';
$assunto      = $_POST['assunto']      ?? '';
$corpo        = $_POST['corpo']        ?? '';

if (empty($template_key) || empty($nome) || empty($assunto) || empty($corpo)) {
    $response['mensagem'] = 'Dados incompletos.';
    echo json_encode($response);
    exit;
}

include '../config/database.php';

$stmt = $conn->prepare("UPDATE email_templates SET template_name = ?, description = ?, subject = ?, body = ? WHERE template_key = ?");
$stmt->bind_param("sssss", $nome, $descricao, $assunto, $corpo, $template_key);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        $response = ['sucesso' => true];
    } else {
        $response['mensagem'] = 'O template não foi alterado ou não existe.';
    }
} else {
    $response['mensagem'] = 'Erro ao atualizar base de dados: ' . $conn->error;
}
$stmt->close();

echo json_encode($response);
exit;
