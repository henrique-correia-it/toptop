<?php
// loja-roupa/admin/ajax_procurar_produtos.php

require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(['erro' => 'Acesso não autorizado.']);
    exit;
}

$termo = trim($_GET['q'] ?? '');
$resultados = [];

if (strlen($termo) >= 2) {
    $termo_like = "%" . $termo . "%";
    $stmt = $conn->prepare(
        "SELECT id, nome, referencia, preco, preco_promocional, foto_principal, atributos, 
         (SELECT SUM(pv.quantidade) FROM produto_variacoes pv WHERE pv.produto_id = p.id) as stock_total
         FROM produtos p
         WHERE ativo = 1 AND (nome LIKE ? OR referencia LIKE ?)
         ORDER BY nome ASC
         LIMIT 10"
    );
    $stmt->bind_param("ss", $termo_like, $termo_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['tem_variacoes'] = !empty($row['atributos']) && $row['atributos'] !== '{}' && $row['atributos'] !== '[]';
        $resultados[] = $row;
    }
    $stmt->close();
}

echo json_encode($resultados);
exit;
