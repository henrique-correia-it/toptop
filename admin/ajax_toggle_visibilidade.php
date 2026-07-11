<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
include '../config/database.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
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

$response = ['sucesso' => false, 'mensagem' => 'Erro desconhecido.'];

$id_produto = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id_produto) {
    try {
        $stmt_get = $conn->prepare("SELECT ativo FROM produtos WHERE id = ?");
        $stmt_get->bind_param("i", $id_produto);
        $stmt_get->execute();
        $res = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($res) {
            $novo_estado = 1 - $res['ativo'];

            $stmt_update = $conn->prepare("UPDATE produtos SET ativo = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $novo_estado, $id_produto);
            $stmt_update->execute();
            $stmt_update->close();

            $response = [
                'sucesso' => true,
                'novo_estado' => $novo_estado,
                'label' => $novo_estado == 1 ? 'Visível' : 'Oculto'
            ];
        } else {
            $response['mensagem'] = 'Produto não encontrado.';
        }
    } catch (Exception $e) {
        log_app($e->getMessage(), 'ERROR', 'ajax_toggle_visibilidade.php');
        $response['mensagem'] = 'Erro ao atualizar na base de dados.';
    }
} else {
    $response['mensagem'] = 'ID do produto inválido.';
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
