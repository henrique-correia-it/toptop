<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cliente_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido']); exit;
}

if (!is_cliente_logged_in()) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autenticado']); exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!hash_equals($_SESSION['csrf_token'], $dados['csrf_token'] ?? '')) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido']); exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];
$password   = $dados['password'] ?? '';

if ($password === '') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Introduz a tua palavra-passe para confirmar.']); exit;
}

$stmt = $conn->prepare("SELECT password_hash FROM clientes WHERE id = ? AND ativo = 1");
$stmt->bind_param('i', $cliente_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cliente || !password_verify($password, $cliente['password_hash'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Palavra-passe incorreta.']); exit;
}

$conn->begin_transaction();
try {
    // Preserva encomendas — apenas desliga o cliente_id
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
} catch (Throwable $e) {
    $conn->rollback();
    error_log('apagar_conta_cliente: ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno. Tenta mais tarde.']); exit;
}

// Remove apenas a sessão de cliente — sessão de admin fica intacta (caso dev)
logout_cliente();

$isAdminAinda = isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true;
$redirect = $isAdminAinda
    ? ($_SESSION['admin_role'] === 'desenvolvedor' ? '/dev' : '/admin')
    : '/';

echo json_encode(['sucesso' => true, 'redirect' => $redirect]);
