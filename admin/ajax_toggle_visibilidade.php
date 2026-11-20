<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
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

$response = ['sucesso' => false, 'mensagem' => 'ID do produto inválido.'];
$id_produto = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id_produto && $id_produto > 0) {
    try {
        $stmt_update = $conn->prepare("UPDATE produtos SET ativo = 1 - ativo WHERE id = ?");
        $stmt_update->bind_param("i", $id_produto);
        $stmt_update->execute();
        $afetadas = $stmt_update->affected_rows;
        $stmt_update->close();

        if ($afetadas === 0) {
            $response['mensagem'] = 'Produto não encontrado.';
        } else {
            $stmt_get = $conn->prepare("SELECT ativo FROM produtos WHERE id = ?");
            $stmt_get->bind_param("i", $id_produto);
            $stmt_get->execute();
            $row = $stmt_get->get_result()->fetch_assoc();
            $stmt_get->close();
            $novo_estado = (int)($row['ativo'] ?? 0);

            $response = [
                'sucesso' => true,
                'novo_estado' => $novo_estado,
                'label' => $novo_estado === 1 ? 'Visível' : 'Oculto'
            ];
        }
    } catch (\Throwable $e) {
        log_app($e->getMessage(), 'ERROR', 'ajax_toggle_visibilidade.php');
        $response['mensagem'] = 'Erro ao atualizar na base de dados.';
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;
