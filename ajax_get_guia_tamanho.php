<?php
include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';

send_no_cache_headers();

$guia_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$guia_id) {
    json_error('ID inválido.', 400);
}

$stmt = $conn->prepare("SELECT titulo, conteudo FROM guias_tamanho WHERE id = ?");
$stmt->bind_param("i", $guia_id);
$stmt->execute();
$guia = $stmt->get_result()->fetch_assoc();

if ($guia) {
    json_success(['guia' => $guia]);
} else {
    json_error('Guia não encontrado.', 404);
}
