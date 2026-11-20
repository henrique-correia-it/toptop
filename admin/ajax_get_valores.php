<?php
// admin/ajax_get_valores.php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

$grupo_id = filter_input(INPUT_GET, 'grupo_id', FILTER_VALIDATE_INT);
if (!$grupo_id || $grupo_id <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do grupo em falta ou inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, valor FROM atributos_valores WHERE grupo_id = ? ORDER BY ordem ASC, valor ASC");
$stmt->bind_param("i", $grupo_id);
$stmt->execute();
$result = $stmt->get_result();
$valores = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($valores);
