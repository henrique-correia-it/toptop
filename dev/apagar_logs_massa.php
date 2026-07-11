<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || $_SESSION['admin_role'] !== 'desenvolvedor') {
    header("Location: /admin/admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_from_post()) {
    http_response_code(403);
    exit('Erro de validacao CSRF.');
}

include '../config/database.php';

if (isset($_POST['tudo']) && $_POST['tudo'] == '1') {
    // Limpar tudo
    $conn->query("TRUNCATE TABLE admin_login_logs");
    $_SESSION['flash_message'] = ['texto' => 'Todos os registos foram limpos com sucesso.', 'tipo' => 'sucesso'];
} elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
    // Limpar selecionados
    $ids = array_map('intval', $_POST['ids']);
    $ids_placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $conn->prepare("DELETE FROM admin_login_logs WHERE id IN ($ids_placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['flash_message'] = ['texto' => count($ids) . ' registo(s) removido(s) com sucesso.', 'tipo' => 'sucesso'];
}

header("Location: login_logs.php");
exit;
