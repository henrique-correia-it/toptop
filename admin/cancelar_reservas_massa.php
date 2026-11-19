<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Acesso inválido.'); }
if (!is_admin_logged_in()) { http_response_code(403); exit('Não autorizado.'); }
if (!csrf_from_post()) { http_response_code(403); exit('CSRF inválido.'); }

$raw_ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
$ids = array_values(array_unique(array_filter(array_map('intval', $raw_ids), fn($id) => $id > 0)));

if (empty($ids)) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhuma reserva selecionada.'];
    header('Location: reservas_stock.php'); exit;
}

$conn->begin_transaction();
try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    // Bloqueia e filtra apenas encomendas que ainda estão efetivamente 'incompleta'
    $stmtLock = $conn->prepare("SELECT id FROM encomendas WHERE id IN ($placeholders) AND estado = 'incompleta' FOR UPDATE");
    $stmtLock->bind_param($types, ...$ids);
    $stmtLock->execute();
    $rowsLock = $stmtLock->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLock->close();

    $ids_validos = array_map(fn($r) => (int)$r['id'], $rowsLock);
    if (empty($ids_validos)) {
        $conn->rollback();
        $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Nenhuma das reservas selecionadas se encontra ativa.'];
        header('Location: reservas_stock.php'); exit;
    }

    $ph_validos = implode(',', array_fill(0, count($ids_validos), '?'));
    $types_validos = str_repeat('i', count($ids_validos));

    $stmtItems = $conn->prepare("SELECT variacao_id, quantidade FROM encomenda_itens WHERE encomenda_id IN ($ph_validos) AND variacao_id IS NOT NULL");
    $stmtItems->bind_param($types_validos, ...$ids_validos);
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

    $stmtCancel = $conn->prepare("UPDATE encomendas SET estado = 'cancelada' WHERE id IN ($ph_validos) AND estado = 'incompleta'");
    $stmtCancel->bind_param($types_validos, ...$ids_validos);
    $stmtCancel->execute();
    $cancelados = $stmtCancel->affected_rows;
    $stmtCancel->close();

    $conn->commit();
    $_SESSION['flash_message'] = ['tipo' => 'sucesso', 'texto' => $cancelados . ' reserva(s) cancelada(s) e stock reposto com sucesso!'];
} catch (\Throwable $e) {
    $conn->rollback();
    error_log($e->getMessage());
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Erro ao cancelar reservas.'];
}

header('Location: reservas_stock.php'); exit;
