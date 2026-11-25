<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Location: clientes.php");
    exit;
}
if (!csrf_from_post()) {
    http_response_code(403);
    header("Location: clientes.php?erro=csrf");
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header("Location: clientes.php?erro=1");
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE encomendas SET cliente_id = NULL WHERE cliente_id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_moradas WHERE cliente_id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_password_resets WHERE cliente_id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_email_verifications WHERE cliente_id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $removidos = $stmt->affected_rows;
    $stmt->close();

    if ($removidos === 0) {
        $conn->rollback();
        header("Location: clientes.php?erro=1");
        exit;
    }

    $conn->commit();
    log_seguranca("Conta de cliente #{$id} eliminada pelo admin.", 'apagar_cliente_admin.php');
} catch (Throwable $e) {
    $conn->rollback();
    log_exception($e, "apagar_cliente_admin.php cliente#{$id}");
    header("Location: clientes.php?erro=1");
    exit;
}

// Se o cliente apagado estava em sessão ativa, limpa
if (isset($_SESSION['cliente_id']) && (int)$_SESSION['cliente_id'] === $id) {
    unset($_SESSION['cliente_logado'], $_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_email']);
}

header("Location: clientes.php?sucesso=1");
exit;
