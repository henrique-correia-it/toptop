<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if (!is_admin_manager()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_from_post()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de validação CSRF.']);
    exit;
}

$response = ['sucesso' => false, 'mensagem' => 'ID do grupo inválido.'];
$id_grupo = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id_grupo && $id_grupo > 0) {
    try {
        $stmt_update = $conn->prepare("UPDATE atributos_grupos SET e_filtravel = 1 - e_filtravel WHERE id = ?");
        $stmt_update->bind_param("i", $id_grupo);
        $stmt_update->execute();
        $afetadas = $stmt_update->affected_rows;
        $stmt_update->close();

        if ($afetadas === 0) {
            $response['mensagem'] = 'Grupo de atributos não encontrado.';
        } else {
            $stmt_get = $conn->prepare("SELECT e_filtravel FROM atributos_grupos WHERE id = ?");
            $stmt_get->bind_param("i", $id_grupo);
            $stmt_get->execute();
            $row = $stmt_get->get_result()->fetch_assoc();
            $stmt_get->close();
            $response = ['sucesso' => true, 'novo_estado' => (int)($row['e_filtravel'] ?? 0)];
        }
    } catch (\Throwable $e) {
        log_app($e->getMessage(), 'ERROR', 'ajax_toggle_filtravel.php');
        $response['mensagem'] = 'Erro ao atualizar na base de dados.';
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;
