<?php
include '../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

if (!is_admin_manager()) {
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

if (is_admin_manager()) {
    $id = (int)($_POST['id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $novo_nome = trim($_POST['nome'] ?? '');

    if ($id > 0 && !empty($novo_nome) && in_array($tipo, ['grupo', 'valor'])) {
        try {
            $tabela = $tipo === 'grupo' ? 'atributos_grupos' : 'atributos_valores';
            $coluna_nome = $tipo === 'grupo' ? 'nome' : 'valor';

            $stmt = $conn->prepare("UPDATE {$tabela} SET {$coluna_nome} = ? WHERE id = ?");
            $stmt->bind_param("si", $novo_nome, $id);
            $stmt->execute();
            $response = ['sucesso' => true, 'mensagem' => ucfirst($tipo).' atualizado com sucesso!'];
            
        } catch (Exception $e) {
            if ($conn->errno !== 1062) {
                log_app($e->getMessage(), 'ERROR', 'ajax_editar_atributo.php');
            }
            $response['mensagem'] = 'Erro ao atualizar.';
            if ($conn->errno == 1062) {
                 $response['mensagem'] = 'Este nome já existe. Por favor, escolha outro.';
            }
        }
    } else {
        $response['mensagem'] = 'Dados inválidos.';
    }
}
header('Content-Type: application/json');
echo json_encode($response);
exit;
