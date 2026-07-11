<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Acesso inválido.'); }
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) { exit('Não autorizado.'); }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { exit('CSRF inválido.'); }

$ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($id) => $id > 0)));

if (empty($ids)) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhuma reserva selecionada.'];
    header('Location: reservas_stock.php'); exit;
}

$conn->begin_transaction();
try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmtItems = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id IN ($placeholders) AND variacao_id IS NOT NULL");
    $stmtItems->bind_param($types, ...$ids);
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

    $stmtCancel = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id IN ($placeholders) AND estado = 'incompleta'");
    $stmtCancel->bind_param($types, ...$ids);
    $stmtCancel->execute();
    $cancelados = $stmtCancel->affected_rows;
    $stmtCancel->close();

    $conn->commit();
    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $cancelados . ' reserva(s) cancelada(s) e stock reposto com sucesso!'];
} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Erro ao cancelar reservas.'];
}

header('Location: reservas_stock.php'); exit;
