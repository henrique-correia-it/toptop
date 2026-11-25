<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !is_developer()
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

$required_placeholders = [
    'verificacao_email'   => ['{link_verificacao}'],
    'recuperacao_cliente' => ['{link_recuperacao}'],
    'recuperacao_admin'   => ['{link_recuperacao}'],
];
if (isset($required_placeholders[$template_key])) {
    foreach ($required_placeholders[$template_key] as $req_ph) {
        if (strpos($corpo, $req_ph) === false) {
            http_response_code(400);
            $response['mensagem'] = "Este template requer o placeholder obrigatório {$req_ph} no corpo do email.";
            echo json_encode($response);
            exit;
        }
    }
}

$system_keys = [
    'verificacao_email',
    'recuperacao_cliente',
    'recuperacao_admin',
    'password_alterada',
    'conta_eliminada',
    'nova_encomenda_loja',
    'novo_contacto_loja',
];

include '../config/database.php';

try {
    $has_scope_col = false;
    if ($res_col = $conn->query("SHOW COLUMNS FROM email_templates LIKE 'scope'")) {
        $has_scope_col = $res_col->num_rows > 0;
        $res_col->free();
    }

    $select_sql = $has_scope_col
        ? "SELECT scope FROM email_templates WHERE template_key = ? LIMIT 1"
        : "SELECT template_key FROM email_templates WHERE template_key = ? LIMIT 1";

    $stmt_chk = $conn->prepare($select_sql);
    $stmt_chk->bind_param("s", $template_key);
    $stmt_chk->execute();
    $row = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if (!$row) {
        http_response_code(404);
        $response['mensagem'] = 'O template especificado não existe.';
        echo json_encode($response);
        exit;
    }

    $isSystemTpl = $has_scope_col
        ? (($row['scope'] ?? 'loja') === 'sistema')
        : in_array($template_key, $system_keys, true);

    if (!$isSystemTpl) {
        http_response_code(403);
        $response['mensagem'] = 'Este endpoint gere apenas templates de sistema.';
        echo json_encode($response);
        exit;
    }

    $stmt = $conn->prepare("UPDATE email_templates SET template_name = ?, description = ?, subject = ?, body = ? WHERE template_key = ?");
    $stmt->bind_param("sssss", $nome, $descricao, $assunto, $corpo, $template_key);
    $stmt->execute();
    $stmt->close();

    log_app("Template de sistema '{$template_key}' atualizado.", 'INFO', 'dev/ajax_salvar_template_sistema.php');
    $response = ['sucesso' => true];
} catch (\Throwable $e) {
    log_exception($e, 'dev/ajax_salvar_template_sistema.php');
    http_response_code(500);
    $response['mensagem'] = 'Erro interno ao atualizar o template.';
}

echo json_encode($response);
exit;
