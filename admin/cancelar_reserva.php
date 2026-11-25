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

    $stmtItems = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id = ? AND variacao_id IS NOT NULL ORDER BY variacao_id ASC");
    $stmtItems->bind_param('i', $id);
    $stmtItems->execute();
    $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtItems->close();

    $stmtDelItems = $conn->prepare("DELETE FROM encomenda_itens WHERE encomenda_id = ?");
    $stmtDelItems->bind_param('i', $id);
    $stmtDelItems->execute();
    $stmtDelItems->close();

    $stmtDel = $conn->prepare("DELETE FROM encomendas WHERE id = ? AND estado = 'incompleta'");
    $stmtDel->bind_param('i', $id);
    $stmtDel->execute();
    $removida = $stmtDel->affected_rows === 1;
    $stmtDel->close();

    if (!$removida) {
        $conn->rollback();
        echo json_encode(['sucesso' => false, 'mensagem' => 'Reserva já foi processada.']); exit;
    }

    if (!empty($items)) {
        $stmtRestore = $conn->prepare("UPDATE produto_variacoes SET quantidade = quantidade + ? WHERE id = ?");
        foreach ($items as $item) {
            $stmtRestore->bind_param('ii', $item['quantidade'], $item['variacao_id']);
            $stmtRestore->execute();
        }
        $stmtRestore->close();
    }

    $conn->commit();
    log_app("Reserva da encomenda #{$id} cancelada e stock reposto.", 'INFO', 'cancelar_reserva.php');
    echo json_encode(['sucesso' => true, 'mensagem' => 'Reserva cancelada e stock reposto.']);
} catch (\Throwable $e) {
    $conn->rollback();
    log_exception($e, "cancelar_reserva.php enc#{$id}");
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao cancelar reserva.']);
}
exit;
