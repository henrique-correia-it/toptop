<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

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

$id = (int)($dados['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']); exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT id FROM encomendas WHERE id = ? AND estado = 'incompleta'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(['sucesso' => false, 'mensagem' => 'Reserva não encontrada ou já cancelada']); exit;
    }
    $stmt->close();

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

    $stmtCancel = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id = ? AND estado = 'incompleta'");
    $stmtCancel->bind_param('i', $id);
    $stmtCancel->execute();
    $stmtCancel->close();

    $conn->commit();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Reserva cancelada e stock reposto.']);
} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao cancelar reserva.']);
}
exit;
