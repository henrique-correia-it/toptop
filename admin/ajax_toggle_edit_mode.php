<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

// Check permissions
if (!is_admin_manager()) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sem permissões.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !csrf_from_array($input)) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão inválida.']);
    exit;
}

// Toggle session variable
if (isset($_SESSION['global_edit_mode']) && $_SESSION['global_edit_mode'] === true) {
    $_SESSION['global_edit_mode'] = false;
} else {
    $_SESSION['global_edit_mode'] = true;
}

echo json_encode(['sucesso' => true, 'edit_mode' => $_SESSION['global_edit_mode']]);
