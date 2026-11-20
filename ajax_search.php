<?php
// loja-roupa/ajax_search.php

include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/http.php';
require_once __DIR__ . '/config/url_helpers.php';

// Remove tags HTML do termo de pesquisa e limita comprimento
$termo_pesquisa = mb_substr(trim(strip_tags((string)($_GET['q'] ?? ''))), 0, 100, 'UTF-8');
$resultados = [];

// Apenas executa a pesquisa se o termo tiver pelo menos 2 caracteres
if (mb_strlen($termo_pesquisa, 'UTF-8') >= 2) {
    $termo_escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo_pesquisa);
    $termo_like = "%" . $termo_escapado . "%";

    $stmt = $conn->prepare(
        "SELECT p.id, p.nome, p.preco, p.preco_promocional, p.foto_principal, p.atributos
         FROM produtos p
         WHERE p.ativo = 1
           AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0)
           AND (p.nome LIKE ? ESCAPE '\\\\' OR p.referencia LIKE ? ESCAPE '\\\\')
         ORDER BY p.nome ASC
         LIMIT 5"
    );
    $stmt->bind_param("ss", $termo_like, $termo_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['slug'] = criar_slug($row['nome'] . '-' . $row['id']);
        $row['url'] = '/produto/' . $row['slug'];
        $resultados[] = $row;
    }
    $stmt->close();
}

send_no_cache_headers();
json_response($resultados);
