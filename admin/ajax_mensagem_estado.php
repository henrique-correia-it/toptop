<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

header('Content-Type: application/json');

$response = ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar a mensagem.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.');
    }

    $dados = json_decode(file_get_contents('php://input'), true);
    if (!is_array($dados)) {
        throw new Exception('Pedido inválido.');
    }

    if (
        !isset($_SESSION['admin_logado']) ||
        $_SESSION['admin_logado'] !== true ||
        empty($dados['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $dados['csrf_token'])
    ) {
        throw new Exception('Erro de validação de segurança.');
    }

    $id = filter_var($dados['id'] ?? null, FILTER_VALIDATE_INT);
    $respondida = filter_var($dados['respondida'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || !in_array($respondida, [0, 1], true)) {
        throw new Exception('Dados inválidos.');
    }

    $stmt = $conn->prepare("UPDATE contactos SET respondida = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Erro ao preparar atualização.');
    }

    $stmt->bind_param("ii", $respondida, $id);
    $stmt->execute();

    if ($stmt->affected_rows < 0) {
        throw new Exception('Erro ao atualizar estado.');
    }

    $stmt->close();

    $response = [
        'sucesso' => true,
        'mensagem' => $respondida === 1 ? 'Mensagem arquivada.' : 'Mensagem movida para recebidas.',
        'respondida' => $respondida,
    ];
} catch (Exception $e) {
    http_response_code(400);
    $response['mensagem'] = $e->getMessage();
}

echo json_encode($response);
exit;
