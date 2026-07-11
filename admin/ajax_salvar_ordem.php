<?php
include '../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

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

$response = ['sucesso' => false];

if (isset($_SESSION['admin_logado']) && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    $ordem_ids = json_decode($_POST['ordem'] ?? '[]', true);
    if (is_array($ordem_ids) && !empty($ordem_ids)) {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE atributos_valores SET ordem = ? WHERE id = ?");
            foreach ($ordem_ids as $index => $id) {
                $stmt->bind_param("ii", $index, $id);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();
            $response['sucesso'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            log_app($e->getMessage(), 'ERROR', 'ajax_salvar_ordem.php');
        }
    }
}
header('Content-Type: application/json');
echo json_encode($response);
exit;
