<?php
// admin/ajax_get_valores.php
include '../config/database.php';
require_once __DIR__ . '/../config/session.php';

// --- INÍCIO DA CORREÇÃO ---
// A verificação agora permite que qualquer tipo de admin logado (admin ou superadmin)
// possa buscar os valores, o que é necessário para adicionar/editar produtos.
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}
// --- FIM DA CORREÇÃO ---

// Valida se o ID do grupo foi enviado
if (!isset($_GET['grupo_id'])) {
    http_response_code(400); // Bad Request
    exit('ID do grupo em falta.');
}

$grupo_id = (int)$_GET['grupo_id'];

$stmt = $conn->prepare("SELECT id, valor FROM atributos_valores WHERE grupo_id = ? ORDER BY ordem ASC, valor ASC");
$stmt->bind_param("i", $grupo_id);
$stmt->execute();
$result = $stmt->get_result();
$valores = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Devolve a resposta em formato JSON para o JavaScript ler
header('Content-Type: application/json');
echo json_encode($valores);
?>
