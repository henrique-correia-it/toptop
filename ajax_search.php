<?php
// loja-roupa/ajax_search.php

include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';

// Remove tags HTML do termo de pesquisa (o prepared statement protege contra SQLi)
$termo_pesquisa = trim(strip_tags($_GET['q'] ?? ''));
$resultados = [];

// Apenas executa a pesquisa se o termo tiver pelo menos 2 caracteres
if (strlen($termo_pesquisa) >= 2) {
    $termo_like = "%" . $termo_pesquisa . "%";

    $stmt = $conn->prepare(
        "SELECT id, nome, preco, preco_promocional, foto_principal, atributos 
         FROM produtos 
         WHERE ativo = 1 AND (nome LIKE ? OR referencia LIKE ?)
         ORDER BY nome ASC
         LIMIT 5"
    );
    $stmt->bind_param("ss", $termo_like, $termo_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $resultados[] = $row;
    }
    $stmt->close();
}

json_response($resultados);
