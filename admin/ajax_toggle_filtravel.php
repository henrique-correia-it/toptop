<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_from_post()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de validacao CSRF.']);
    exit;
}

$response = ['sucesso' => false, 'mensagem' => 'Acesso negado.'];

if (isset($_SESSION['admin_logado']) && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    $id_grupo = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($id_grupo) {
        try {
            $stmt_get = $conn->prepare("SELECT e_filtravel FROM atributos_grupos WHERE id = ?");
            $stmt_get->bind_param("i", $id_grupo);
            $stmt_get->execute();
            $estado_atual = $stmt_get->get_result()->fetch_assoc()['e_filtravel'];
            $stmt_get->close();

            $novo_estado = 1 - $estado_atual;

            $stmt_update = $conn->prepare("UPDATE atributos_grupos SET e_filtravel = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $novo_estado, $id_grupo);
            $stmt_update->execute();
            $stmt_update->close();

            $response = ['sucesso' => true, 'novo_estado' => $novo_estado];

        } catch (Exception $e) {
            log_app($e->getMessage(), 'ERROR', 'ajax_toggle_filtravel.php');
            $response['mensagem'] = 'Erro ao atualizar na base de dados.';
        }
    } else {
        $response['mensagem'] = 'ID do grupo inválido.';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
