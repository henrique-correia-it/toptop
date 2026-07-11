<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/interface_labels.php';
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

// Permitir apenas admins e superadmins
$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['admin', 'superadmin', 'desenvolvedor'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não tem permissão para esta ação.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_from_post()) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de validacao CSRF.']);
    exit;
}

$key         = $_POST['key'] ?? '';
$title       = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';

if (empty($key) || empty($title)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos.']);
    exit;
}

if (saveInterfaceString($key, $title, $description)) {
    echo json_encode([
        'sucesso' => true,
        'id' => $key,
        'title' => $title,
        'description' => $description
    ]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao guardar na base de dados.']);
}
?>
