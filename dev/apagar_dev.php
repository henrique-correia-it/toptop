<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

require_developer('/admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Acesso inválido.');
}

if (!csrf_from_post()) {
    http_response_code(403);
    exit('Erro de validação de segurança. Ação não permitida.');
}

$id_a_apagar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id_a_apagar) {
    header('Location: listar_devs.php');
    exit;
}

if ($id_a_apagar === (int)($_SESSION['admin_id'] ?? 0)) {
    header('Location: listar_devs.php?erro=2');
    exit;
}

$conn->begin_transaction();
try {
    $stmt_target = $conn->prepare("SELECT role FROM administradores WHERE id = ? FOR UPDATE");
    $stmt_target->bind_param("i", $id_a_apagar);
    $stmt_target->execute();
    $target = $stmt_target->get_result()->fetch_assoc();
    $stmt_target->close();

    if (!$target || ($target['role'] ?? '') !== 'desenvolvedor') {
        $conn->rollback();
        header('Location: listar_devs.php?erro=1');
        exit;
    }

    $stmt_total = $conn->prepare("SELECT id FROM administradores WHERE role = 'desenvolvedor' FOR UPDATE");
    $stmt_total->execute();
    $total_devs = $stmt_total->get_result()->num_rows;
    $stmt_total->close();

    if ($total_devs <= 1) {
        $conn->rollback();
        header('Location: listar_devs.php?erro=3');
        exit;
    }

    $stmt_delete = $conn->prepare("DELETE FROM administradores WHERE id = ? AND role = 'desenvolvedor'");
    $stmt_delete->bind_param("i", $id_a_apagar);
    $ok = $stmt_delete->execute();
    $stmt_delete->close();

    if ($ok) {
        $conn->commit();
        log_seguranca("Desenvolvedor #{$id_a_apagar} eliminado.", 'dev/apagar_dev.php');
        header('Location: listar_devs.php?sucesso=3');
        exit;
    }

    $conn->rollback();
    header('Location: listar_devs.php?erro=4');
    exit;
} catch (\Throwable $e) {
    $conn->rollback();
    log_exception($e, 'dev/apagar_dev.php');
    header('Location: listar_devs.php?erro=4');
    exit;
}
