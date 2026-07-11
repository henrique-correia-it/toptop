<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido']); exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!hash_equals($_SESSION['csrf_token'] ?? '', $dados['csrf_token'] ?? '')) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido']); exit;
}

$id    = (int)($dados['id']   ?? 0);
$ativo = (int)($dados['ativo'] ?? 0) ? 1 : 0;

if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit;
}

$stmt = $conn->prepare("UPDATE clientes SET ativo = ?, data_atualizacao = NOW() WHERE id = ?");
$stmt->bind_param('ii', $ativo, $id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'sucesso'  => true,
    'mensagem' => $ativo ? 'Conta ativada.' : 'Conta suspensa.',
]);
