<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

header('Content-Type: application/json');

if (!is_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido']); exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados) || !csrf_from_array($dados)) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'CSRF inválido']); exit;
}

$id = (int)($dados['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT id FROM encomendas WHERE id = ? AND estado = 'incompleta' FOR UPDATE");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(['sucesso' => false, 'mensagem' => 'Reserva não encontrada ou já cancelada']); exit;
    }
    $stmt->close();

    $stmtCancel = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id = ? AND estado = 'incompleta'");
    $stmtCancel->bind_param('i', $id);
    $stmtCancel->execute();
    $atualizada = $stmtCancel->affected_rows === 1;
    $stmtCancel->close();

    if (!$atualizada) {
        $conn->rollback();
        echo json_encode(['sucesso' => false, 'mensagem' => 'Reserva já foi processada.']); exit;
    }

    $stmtItems = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ? AND variacao_id IS NOT NULL");
    $stmtItems->bind_param('i', $id);
    $stmtItems->execute();
    $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtItems->close();

    if (!empty($items)) {
        $stmtRestore = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
        foreach ($items as $item) {
            $stmtRestore->bind_param('ii', $item['quantidade'], $item['variacao_id']);
            $stmtRestore->execute();
        }
        $stmtRestore->close();
    }

    $conn->commit();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Reserva cancelada e stock reposto.']);
} catch (\Throwable $e) {
    $conn->rollback();
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao cancelar reserva.']);
}
exit;
