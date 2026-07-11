<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

header('Content-Type: application/json');
$response = ['sucesso' => false, 'mensagem' => 'Ação inválida.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $response['mensagem'] = 'Acesso negado ou sessão expirada.';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$titulo = trim($_POST['titulo'] ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');

try {
    switch ($action) {
        case 'add':
            if (empty($titulo) || empty($conteudo)) {
                throw new Exception('O título e o conteúdo são obrigatórios.');
            }
            $stmt = $conn->prepare("INSERT INTO guias_tamanho (titulo, conteudo) VALUES (?, ?)");
            $stmt->bind_param("ss", $titulo, $conteudo);
            if ($stmt->execute()) {
                $response = ['sucesso' => true, 'id' => $conn->insert_id];
            } else {
                throw new Exception('Erro ao adicionar o guia.');
            }
            break;

        case 'edit':
            if (!$id || empty($titulo) || empty($conteudo)) {
                throw new Exception('Dados incompletos para editar.');
            }
            $stmt = $conn->prepare("UPDATE guias_tamanho SET titulo = ?, conteudo = ? WHERE id = ?");
            $stmt->bind_param("ssi", $titulo, $conteudo, $id);
            if ($stmt->execute()) {
                $response = ['sucesso' => true];
            } else {
                throw new Exception('Erro ao atualizar o guia.');
            }
            break;

        case 'delete':
            if (!$id) {
                throw new Exception('ID do guia em falta.');
            }
            $stmt = $conn->prepare("DELETE FROM guias_tamanho WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ['sucesso' => true];
            } else {
                throw new Exception('Erro ao apagar o guia.');
            }
            break;
    }
} catch (Exception $e) {
    log_app($e->getMessage(), 'ERROR', 'ajax_operations_guias.php');
    $response['mensagem'] = $e->getMessage();
}

echo json_encode($response);
exit;
