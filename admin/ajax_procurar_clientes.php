<?php
// loja-roupa/admin/ajax_procurar_clientes.php

require_once __DIR__ . '/../config/session.php';
include '../config/database.php';
require_once __DIR__ . '/../includes/CustomerService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode([]);
    exit;
}

$termo = trim($_GET['q'] ?? '');
$resultados = [];

if (strlen($termo) >= 2) {
    $termo_like = "%" . $termo . "%";
    // Procura em contas registadas e encomendas antigas/convidado.
    $stmt = $conn->prepare(
        "SELECT cliente_nome, cliente_email, cliente_telefone
         FROM (
            SELECT nome AS cliente_nome, email AS cliente_email, telefone AS cliente_telefone
            FROM clientes
            WHERE nome LIKE ? OR email LIKE ? OR telefone LIKE ?
            UNION
            SELECT DISTINCT cliente_nome, cliente_email, cliente_telefone
            FROM encomendas
            WHERE cliente_nome LIKE ? OR cliente_email LIKE ? OR cliente_telefone LIKE ?
         ) clientes_encontrados
         ORDER BY cliente_nome ASC
         LIMIT 10"
    );
    $stmt->bind_param("ssssss", $termo_like, $termo_like, $termo_like, $termo_like, $termo_like, $termo_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $resultados[] = $row;
    }
    $stmt->close();
}

echo json_encode($resultados);
exit;
