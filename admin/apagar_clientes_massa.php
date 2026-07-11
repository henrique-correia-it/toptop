<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: clientes.php");
    exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: clientes.php?erro=csrf");
    exit;
}

$ids_raw = $_POST['ids'] ?? [];
if (!is_array($ids_raw) || empty($ids_raw)) {
    header("Location: clientes.php");
    exit;
}
$ids = array_values(array_unique(array_filter(array_map('intval', $ids_raw), fn($id) => $id > 0)));
if (empty($ids)) {
    header("Location: clientes.php");
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE encomendas SET cliente_id = NULL WHERE cliente_id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_moradas WHERE cliente_id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_password_resets WHERE cliente_id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM cliente_email_verifications WHERE cliente_id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM clientes WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids); $stmt->execute(); $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('apagar_clientes_massa: ' . $e->getMessage());
    header("Location: clientes.php?erro=1");
    exit;
}

if (isset($_SESSION['cliente_id']) && in_array((int)$_SESSION['cliente_id'], $ids, true)) {
    unset($_SESSION['cliente_logado'], $_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_email']);
}

header("Location: clientes.php?sucesso=2");
exit;
