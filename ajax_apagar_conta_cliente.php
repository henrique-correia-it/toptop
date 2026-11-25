<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';
require_once __DIR__ . '/config/rate_limit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido']); exit;
}

if (!is_cliente_logged_in()) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autenticado']); exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados) || !csrf_from_array($dados)) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido']); exit;
}
if (!rate_limit_allow('apagar_conta_cliente', 5, 600, (string)($_SESSION['cliente_id'] ?? ''))) {
    http_response_code(429);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Demasiadas tentativas. Aguarda alguns minutos.']); exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];
$password   = $dados['password'] ?? '';

if ($password === '') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Introduz a tua palavra-passe para confirmar.']); exit;
}

$stmt = $conn->prepare("SELECT nome, email, password_hash FROM clientes WHERE id = ? AND ativo = 1");
$stmt->bind_param('i', $cliente_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cliente || !password_verify($password, $cliente['password_hash'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Palavra-passe incorreta.']); exit;
}

$conn->begin_transaction();
try {
    // Preserva encomendas - apenas desliga o cliente_id
    $stmt = $conn->prepare("UPDATE encomendas SET cliente_id = NULL WHERE cliente_id = ?");
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_moradas WHERE cliente_id = ?");
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_password_resets WHERE cliente_id = ?");
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    log_seguranca("Conta de cliente #{$cliente_id} ({$cliente['email']}) eliminada pelo próprio.", 'ajax_apagar_conta_cliente.php');
} catch (Throwable $e) {
    $conn->rollback();
    log_exception($e, "ajax_apagar_conta_cliente.php cliente#{$cliente_id}");
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno. Tenta mais tarde.']); exit;
}

// Remove apenas a sessão de cliente - sessão de admin fica intacta (caso dev)
logout_cliente();

if (!empty($cliente['email'])) {
    require_once __DIR__ . '/admin/includes/email_handler.php';
    enviarEmailTemplate('conta_eliminada', (string)$cliente['email'], (string)($cliente['nome'] ?? ''), [
        '{nome_cliente}' => (string)($cliente['nome'] ?? ''),
    ]);
}

$redirect = is_admin_logged_in()
    ? (is_developer() ? '/dev' : '/admin')
    : '/';

echo json_encode(['sucesso' => true, 'redirect' => $redirect]);
